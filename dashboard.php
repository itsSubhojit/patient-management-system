<?php
require_once 'includes/config.php';
requireLogin();

// Get dashboard statistics
try {
    $stats = [];
    
    // Total patients
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
    $stats['total_patients'] = $stmt->fetch()['count'];
    
    // Today's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE() AND status != 'cancelled'");
    $stmt->execute();
    $stats['today_appointments'] = $stmt->fetch()['count'];
    
    // Pending appointments
    if (hasRole('doctor')) {
        // Get doctor id from session user id
        $doctor_user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$doctor_user_id]);
        $doctor_row = $stmt->fetch();
        $doctor_id = $doctor_row ? $doctor_row['id'] : 0;
        // Count confirmed appointments for this doctor
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE status = 'confirmed' AND doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $stats['pending_appointments'] = $stmt->fetch()['count'];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'");
        $stmt->execute();
        $stats['pending_appointments'] = $stmt->fetch()['count'];
    }
    
    // Total doctors
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors");
    $stats['total_doctors'] = $stmt->fetch()['count'];
    
    // Recent appointments
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.last_name, p.patient_id, u.full_name as doctor_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_appointments = $stmt->fetchAll();
    
    // Recent patients
    $stmt = $pdo->prepare("
        SELECT patient_id, first_name, last_name, phone, created_at
        FROM patients
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_patients = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error fetching dashboard data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="dashboard-wrapper">
        <div class="container-fluid px-4">
            <main class="dashboard-main">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="dashboard-title display-6 fw-bold mb-0 d-flex align-items-center gap-2">
                        
                        Dashboard
                    </h1>
                </div>

                <?php displayAlert(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4 justify-content-center">
                    <?php if (hasRole('doctor')): ?>
                        <div class="col-12 col-md-4 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Total Patients
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['total_patients']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-primary">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Today's Appointments
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['today_appointments']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-success">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Pending Appointments
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['pending_appointments']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-warning">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Total Patients
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['total_patients']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-primary">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Today's Appointments
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['today_appointments']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-success">
                                        <i class="fas fa-calendar-day fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Pending Appointments
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['pending_appointments']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-warning">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card modern-stat-card h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label text-uppercase mb-2">
                                            Total Doctors
                                        </div>
                                        <div class="stat-value display-5 fw-bold">
                                            <?php echo number_format($stats['total_doctors']); ?>
                                        </div>
                                    </div>
                                    <div class="stat-icon bg-gradient-info">
                                        <i class="fas fa-user-md fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="row">
                    <?php $showRecentAppointments = !hasRole('doctor'); ?>
                    <?php if ($showRecentAppointments): ?>
                        <!-- Recent Appointments -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-calendar-alt"></i> Recent Appointments
                                    </h6>
                                    <a href="appointments.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_appointments)): ?>
                                        <p class="text-muted">No appointments found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive scrollable-table">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Patient</th>
                                                        <th>Doctor</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_appointments as $appointment): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_id']); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                        <td>
                                                            <?php echo formatDate($appointment['appointment_date']); ?><br>
                                                            <small class="text-muted"><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo getStatusColor($appointment['status']); ?>">
                                                                <?php echo ucfirst($appointment['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <!-- Recent Patients -->
                        <div class="col-lg-6 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-users"></i> Recent Patients
                                    </h6>
                                    <a href="patients.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_patients)): ?>
                                        <p class="text-muted">No patients found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive scrollable-table">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Patient ID</th>
                                                        <th>Name</th>
                                                        <th>Phone</th>
                                                        <th>Registered</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_patients as $patient): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                                        <td><?php echo formatDate($patient['created_at']); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Recent Patients full width for doctors -->
                        <div class="col-lg-12 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-users"></i> Recent Patients
                                    </h6>
                                    <a href="patients.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_patients)): ?>
                                        <p class="text-muted">No patients found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive scrollable-table">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Patient ID</th>
                                                        <th>Name</th>
                                                        <th>Phone</th>
                                                        <th>Registered</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_patients as $patient): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                                        <td><?php echo formatDate($patient['created_at']); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-bolt"></i> Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                        <?php
                                        $quickActions = [];
                                        if (!hasRole('doctor')) {
                                            $quickActions[] = [
                                                'href' => 'patients.php?action=add',
                                                'class' => 'btn-success',
                                                'icon' => 'fa-user-plus',
                                                'label' => 'Add New Patient'
                                            ];
                                            $quickActions[] = [
                                                'href' => 'appointments.php?action=add',
                                                'class' => 'btn-primary',
                                                'icon' => 'fa-calendar-plus',
                                                'label' => 'Schedule Appointment'
                                            ];
                                        }
                                        $quickActions[] = [
                                            'href' => 'patients.php',
                                            'class' => 'btn-info',
                                            'icon' => 'fa-search',
                                            'label' => 'Search Patients'
                                        ];
                                        $colClass = 'col-12 col-md-' . (count($quickActions) > 0 ? intval(12 / count($quickActions)) : 12) . ' mb-3 mb-md-0';
                                        foreach ($quickActions as $action): ?>
                                            <div class="<?php echo $colClass; ?>">
                                                <a href="<?php echo $action['href']; ?>" class="btn <?php echo $action['class']; ?> w-100 text-center d-flex flex-column align-items-center justify-content-center" style="height: 60px;">
                                                    <i class="fas <?php echo $action['icon']; ?> fa-1x mb-2"></i>
                                                    <span class="fs-7"><?php echo $action['label']; ?></span>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>

<?php
function getStatusColor($status) {
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
?>