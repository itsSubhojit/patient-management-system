<?php
require_once 'includes/config.php';
requireLogin();

$action = $_GET['action'] ?? 'list';
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if action is in POST data (from button submissions)
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
    
    if ($action == 'add') {
        // Add new appointment
        $patient_id = (int)$_POST['patient_id'];
        $doctor_id = (int)$_POST['doctor_id'];
        $appointment_date = sanitize($_POST['appointment_date']);
        $appointment_time = sanitize($_POST['appointment_time']);
        $reason = sanitize($_POST['reason']);
        $notes = sanitize($_POST['notes']);

        if (empty($patient_id) || empty($doctor_id) || empty($appointment_date) || empty($appointment_time)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // Check for conflicting appointments for doctor
                $stmt = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
                $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
                $doctor_conflict = $stmt->fetch();

                // Check for conflicting appointments for patient with same doctor/date/time
                $stmt2 = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'");
                $stmt2->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time]);
                $patient_conflict = $stmt2->fetch();

                if ($doctor_conflict) {
                    $error = 'This time slot is already booked for the selected doctor';
                } elseif ($patient_conflict) {
                    $error = 'This patient already has an appointment with this doctor at the selected date and time.';
                } else {
                    $appointment_id = generateId('APT', 'appointments', 'appointment_id');
                    $stmt = $pdo->prepare("INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$appointment_id, $patient_id, $doctor_id, $appointment_date, $appointment_time, $reason, $notes, $_SESSION['user_id']]);
                    showAlert('Appointment scheduled successfully! Appointment ID: ' . $appointment_id, 'success');
                    redirect('appointments.php');
                }
            } catch (PDOException $e) {
                $error = 'Error scheduling appointment: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'update_status' && isset($_POST['appointment_id'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        $status = sanitize($_POST['status']);
        
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $appointment_id]);
            
            showAlert('Appointment status updated successfully!', 'success');
            redirect('appointments.php');
        } catch (PDOException $e) {
            $error = 'Error updating appointment status: ' . $e->getMessage();
        }
    } elseif ($action == 'complete_with_diagnosis' && isset($_POST['appointment_id'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        $chief_complaint = sanitize($_POST['chief_complaint']);
        $diagnosis = sanitize($_POST['diagnosis']);
        $prescribed_medications = sanitize($_POST['prescribed_medications']);
        $treatment = sanitize($_POST['treatment']);
        $follow_up_notes = sanitize($_POST['follow_up_notes']);
        $next_appointment_date = !empty($_POST['next_appointment_date']) ? sanitize($_POST['next_appointment_date']) : null;
        try {
            // Get appointment details
            $stmt = $pdo->prepare("SELECT patient_id, doctor_id, appointment_date FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            if ($appointment) {
                // Insert into medical history (with next appointment date)
                $stmt = $pdo->prepare("INSERT INTO medical_history (patient_id, doctor_id, visit_date, chief_complaint, diagnosis, treatment, prescribed_medications, follow_up_date, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $appointment['patient_id'],
                    $appointment['doctor_id'],
                    $appointment['appointment_date'],
                    $chief_complaint,
                    $diagnosis,
                    $treatment,
                    $prescribed_medications,
                    $next_appointment_date,
                    $follow_up_notes
                ]);
                // Update appointment status to completed
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$appointment_id]);
                showAlert('Appointment completed and medical history recorded successfully!', 'success');
            } else {
                $error = 'Appointment not found.';
            }
            redirect('appointments.php');
        } catch (PDOException $e) {
            $error = 'Error completing appointment: ' . $e->getMessage();
        }
    }
}

// Get doctors for dropdown
try {
    $doctors_stmt = $pdo->query("SELECT d.id, d.doctor_id, u.full_name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.is_active = 1 ORDER BY u.full_name");
    $doctors = $doctors_stmt->fetchAll();
} catch (PDOException $e) {
    $doctors = [];
}

// Get patients for dropdown
try {
    if (hasRole('doctor')) {
        // Doctors should not schedule appointments (for now)
        $patients = [];
    } else {
        // For admin and other roles, show all patients
        $patients_stmt = $pdo->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name");
        $patients = $patients_stmt->fetchAll();
    }
} catch (PDOException $e) {
    $patients = [];
}

// Search and filter appointments
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$doctor_filter = $_GET['doctor'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(a.appointment_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
}

if (hasRole('doctor')) {
    // Only show appointments for this doctor
    $doctor_user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT d.id FROM doctors d WHERE d.user_id = ?");
    $stmt->execute([$doctor_user_id]);
    $doctor_row = $stmt->fetch();
    $doctor_id = $doctor_row ? $doctor_row['id'] : 0;
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_id;
    // If no explicit status filter, only show confirmed appointments for doctor
    if (!$status_filter) {
        $where_conditions[] = "a.status = 'confirmed'";
    }
} elseif ($doctor_filter) {
    $where_conditions[] = "a.doctor_id = ?";
    $params[] = $doctor_filter;
    if (!$status_filter) {
        $where_conditions[] = "a.status = 'confirmed'";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get appointments with patient and doctor info
try {
    $count_query = "SELECT COUNT(*) as total FROM appointments a 
                    JOIN patients p ON a.patient_id = p.id 
                    JOIN doctors d ON a.doctor_id = d.id 
                    JOIN users u ON d.user_id = u.id 
                    $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_appointments = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_appointments / $limit);
    
    $query = "SELECT a.*, p.patient_id, p.first_name, p.last_name, p.phone, 
                     d.doctor_id, u.full_name as doctor_name, d.specialization
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              JOIN doctors d ON a.doctor_id = d.id
              JOIN users u ON d.user_id = u.id
              $where_clause
              ORDER BY a.created_at ASC
              LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching appointments: ' . $e->getMessage();
    $appointments = [];
    $total_appointments = 0;
    $total_pages = 0;
}

// Pre-select patient if coming from patient page
$selected_patient_id = $_GET['patient_id'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="dashboard-wrapper">
            <main class="dashboard-main">
                <?php if ($action == 'list'): ?>
                <!-- Appointments List -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="dashboard-title display-6 fw-bold mb-0 d-flex align-items-center gap-2">
                        Appointments Management
                    </h1>
                    <?php if (!hasRole('doctor')): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="appointments.php?action=add" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php displayAlert(); ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-control" name="status">
                                    <option value="">All Status</option>
                                    <option value="scheduled" <?php echo $status_filter == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="no-show" <?php echo $status_filter == 'no-show' ? 'selected' : ''; ?>>No-show</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" name="doctor">
                                    <option value="">All Doctors</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctor_filter == $doctor['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($doctor['full_name'] . ' - ' . $doctor['specialization']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>
                        <?php if ($search || $status_filter || $date_filter || $doctor_filter): ?>
                        <div class="mt-2">
                            <a href="appointments.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Appointments Table -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Appointments (<?php echo number_format($total_appointments); ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No appointments found</h5>
                                <p>Start by <a href="appointments.php?action=add">scheduling your first appointment</a></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Appointment ID</th>
                                            <th>Patient</th>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($appointment['appointment_id']); ?></strong>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($appointment['patient_id']); ?> • 
                                                    <?php echo htmlspecialchars($appointment['phone']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($appointment['doctor_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($appointment['specialization']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo formatDate($appointment['appointment_date']); ?></strong><br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($appointment['reason']): ?>
                                                    <?php echo htmlspecialchars(substr($appointment['reason'], 0, 50)); ?>
                                                    <?php if (strlen($appointment['reason']) > 50): ?>...<?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getAppointmentStatusColor($appointment['status']); ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="appointments.php?action=view&id=<?php echo $appointment['id']; ?>" class="btn btn-info btn-action-icon" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($appointment['status'] == 'scheduled'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit" name="action" value="update_status" class="btn btn-success btn-action-icon" title="Confirm" style="margin-right: 5px; border-radius: 0px;">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" name="action" value="update_status" class="btn btn-danger btn-action-icon" title="Cancel" style="border-radius: 0 20px 20px 0;" onclick="return confirm('Are you sure you want to cancel this appointment?')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($appointment['status'] == 'confirmed'): ?>
                                                        <button type="button" class="btn btn-primary btn-action-icon" title="Complete with Diagnosis" data-bs-toggle="modal" data-bs-target="#diagnosisModal<?php echo $appointment['id']; ?>">
                                                            <i class="fas fa-check-double"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Appointments pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo buildQueryString(['search' => $search, 'status' => $status_filter, 'date' => $date_filter, 'doctor' => $doctor_filter]); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo buildQueryString(['search' => $search, 'status' => $status_filter, 'date' => $date_filter, 'doctor' => $doctor_filter]); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo buildQueryString(['search' => $search, 'status' => $status_filter, 'date' => $date_filter, 'doctor' => $doctor_filter]); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action == 'add'): ?>
                <!-- Schedule Appointment Form -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="dashboard-title display-6 fw-bold mb-0 d-flex align-items-center gap-2">
                        Schedule New Appointment
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" id="appointmentForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="patient_id">Patient *</label>
                                        <select class="form-control" id="patient_id" name="patient_id" required>
                                            <option value="">Select Patient</option>
                                            <?php foreach ($patients as $patient): ?>
                                                <option value="<?php echo $patient['id']; ?>" <?php echo ($selected_patient_id == $patient['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="doctor_id">Doctor *</label>
                                        <select class="form-control" id="doctor_id" name="doctor_id" required>
                                            <option value="">Select Doctor</option>
                                            <?php foreach ($doctors as $doctor): ?>
                                                <option value="<?php echo $doctor['id']; ?>">
                                                    <?php echo htmlspecialchars($doctor['full_name'] . ' - ' . $doctor['specialization']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="appointment_date">Appointment Date *</label>
                                        <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="appointment_time">Appointment Time *</label>
                                        <select class="form-control" id="appointment_time" name="appointment_time" required>
                                            <option value="">Select Time</option>
                                            <?php
                                            // Generate time slots from 9 AM to 5 PM
                                            for ($hour = 9; $hour <= 17; $hour++) {
                                                for ($minute = 0; $minute < 60; $minute += 30) {
                                                    $time = sprintf('%02d:%02d', $hour, $minute);
                                                    $display_time = date('g:i A', strtotime($time));
                                                    echo "<option value=\"$time\">$display_time</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="reason">Reason for Visit</label>
                                <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="Brief description of the reason for this appointment..."></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label for="notes">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any additional notes or special instructions..."></textarea>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Schedule Appointment
                                </button>
                                <a href="appointments.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif ($action == 'view' && isset($_GET['id'])): ?>
<?php
    $view_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT a.*, p.patient_id, p.first_name, p.last_name, p.phone, d.doctor_id, u.full_name as doctor_name, d.specialization FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id JOIN users u ON d.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$view_id]);
    $appointment = $stmt->fetch();
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="dashboard-title display-6 fw-bold mb-0 d-flex align-items-center gap-2">
        Appointment Details
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="appointments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>
<?php if ($appointment): ?>
<div class="card mb-4">
    <div class="card-body">
        <h5><strong>Appointment ID:</strong> <?php echo htmlspecialchars($appointment['appointment_id']); ?></h5>
        <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name'] . ' (' . $appointment['patient_id'] . ')'); ?></p>
        <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name'] . ' - ' . $appointment['specialization']); ?></p>
        <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
        <p><strong>Status:</strong> <span class="badge bg-<?php echo getAppointmentStatusColor($appointment['status']); ?>"><?php echo ucfirst($appointment['status']); ?></span></p>
    </div>
</div>
<?php else: ?>
<div class="alert alert-danger">Appointment not found.</div>
<?php endif; ?>
<?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Diagnosis Modals (Outside main container) -->
    <?php if ($action == 'list' && !empty($appointments)): ?>
        <?php foreach ($appointments as $appointment): ?>
        <?php if ($appointment['status'] == 'confirmed'): ?>
        <div class="modal fade" id="diagnosisModal<?php echo $appointment['id']; ?>" tabindex="-1" aria-labelledby="diagnosisModalLabel<?php echo $appointment['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="diagnosisModalLabel<?php echo $appointment['id']; ?>">
                            <i class="fas fa-notes-medical"></i> Complete Appointment & Add Diagnosis
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                            <input type="hidden" name="action" value="complete_with_diagnosis">
                            
                            <div class="alert alert-info mb-3">
                                <strong><i class="fas fa-user"></i> Patient:</strong> <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?> (<?php echo htmlspecialchars($appointment['patient_id']); ?>)<br>
                                <strong><i class="fas fa-calendar"></i> Date & Time:</strong> <?php echo formatDate($appointment['appointment_date']) . ' at ' . date('g:i A', strtotime($appointment['appointment_time'])); ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="chief_complaint<?php echo $appointment['id']; ?>" class="form-label"><strong>Chief Complaint</strong></label>
                                <textarea class="form-control" id="chief_complaint<?php echo $appointment['id']; ?>" name="chief_complaint" rows="2" placeholder="What is the patient's main complaint or reason for visit?"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="diagnosis<?php echo $appointment['id']; ?>" class="form-label"><strong>Diagnosis *</strong></label>
                                <textarea class="form-control" id="diagnosis<?php echo $appointment['id']; ?>" name="diagnosis" rows="2" required placeholder="Enter your diagnosis..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="prescribed_medications<?php echo $appointment['id']; ?>" class="form-label"><strong>Prescribed Medications</strong></label>
                                <textarea class="form-control" id="prescribed_medications<?php echo $appointment['id']; ?>" name="prescribed_medications" rows="2" placeholder="List medications with dosage and frequency..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="treatment<?php echo $appointment['id']; ?>" class="form-label"><strong>Treatment Plan</strong></label>
                                <textarea class="form-control" id="treatment<?php echo $appointment['id']; ?>" name="treatment" rows="2" placeholder="Describe the treatment plan and recommendations..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="next_appointment_date<?php echo $appointment['id']; ?>" class="form-label"><strong>Next Appointment Date</strong></label>
                                <input type="date" class="form-control" id="next_appointment_date<?php echo $appointment['id']; ?>" name="next_appointment_date">
                            </div>
                            
                            <div class="mb-3">
                                <label for="follow_up_notes<?php echo $appointment['id']; ?>" class="form-label"><strong>Follow-up Notes</strong></label>
                                <textarea class="form-control" id="follow_up_notes<?php echo $appointment['id']; ?>" name="follow_up_notes" rows="2" placeholder="Any follow-up instructions or additional notes..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Complete & Save to History
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check availability when date/time/doctor changes
        document.getElementById('appointmentForm')?.addEventListener('change', function(e) {
            if (e.target.name === 'doctor_id' || e.target.name === 'appointment_date' || e.target.name === 'appointment_time') {
                checkAvailability();
            }
        });

        function checkAvailability() {
            const doctorId = document.getElementById('doctor_id').value;
            const date = document.getElementById('appointment_date').value;
            const time = document.getElementById('appointment_time').value;

            if (doctorId && date && time) {
                // Here you would typically make an AJAX call to check availability
                // For now, we'll just validate on form submission
            }
        }
    </script>
</body>
</html>

<?php
function getAppointmentStatusColor($status) {
    switch ($status) {
        case 'confirmed':
            return 'success';
        case 'completed':
            return 'primary';
        case 'cancelled':
            return 'danger';
        case 'no-show':
            return 'warning';
        default:
            return 'secondary';
    }
}

function buildQueryString($params) {
    $query_parts = [];
    foreach ($params as $key => $value) {
        if (!empty($value)) {
            $query_parts[] = $key . '=' . urlencode($value);
        }
    }
    return $query_parts ? '&' . implode('&', $query_parts) : '';
}
?>