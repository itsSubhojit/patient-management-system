<?php
require_once 'includes/config.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: dashboard.php');
    exit;
}
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'add') {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $specialization = sanitize($_POST['specialization']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $license_number = sanitize($_POST['license_number']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $doctor_id = generateId('DOC', 'doctors', 'doctor_id');
    try {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password, role, is_active, address, phone) VALUES (?, ?, ?, ?, 'doctor', 1, ?, ?)");
        $stmt->execute([$full_name, $username, $email, $password, $address, $phone]);
        $user_id = $pdo->lastInsertId();
        $stmt2 = $pdo->prepare("INSERT INTO doctors (doctor_id, user_id, specialization, license_number, consultation_fee) VALUES (?, ?, ?, ?, ?)");
        $stmt2->execute([$doctor_id, $user_id, $specialization, $license_number, $consultation_fee]);
        $success = 'Doctor added successfully!';
        redirect('doctors.php');
    } catch (PDOException $e) {
        $error = 'Error adding doctor: ' . $e->getMessage();
    }
}

// Handle edit doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'edit' && isset($_GET['id'])) {
    $doctor_id = (int)$_GET['id'];
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $specialization = sanitize($_POST['specialization']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $license_number = sanitize($_POST['license_number']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = trim($_POST['password'] ?? '');
    try {
        // Get user_id from doctor
        $stmt = $pdo->prepare("SELECT user_id FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        $row = $stmt->fetch();
        if ($row) {
            $user_id = $row['user_id'];
            if ($new_password !== '') {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, is_active = ?, address = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$full_name, $username, $email, $is_active, $address, $phone, $hashed_password, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, is_active = ?, address = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $username, $email, $is_active, $address, $phone, $user_id]);
            }
            $stmt2 = $pdo->prepare("UPDATE doctors SET specialization = ?, license_number = ?, consultation_fee = ? WHERE id = ?");
            $stmt2->execute([$specialization, $license_number, $consultation_fee, $doctor_id]);
            $success = 'Doctor updated successfully!';
            redirect('doctors.php');
        } else {
            $error = 'Doctor not found.';
        }
    } catch (PDOException $e) {
        $error = 'Error updating doctor: ' . $e->getMessage();
    }
}
$doctors = [];
try {
    $stmt = $pdo->query("SELECT d.*, u.full_name, u.email, u.is_active FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.full_name");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Error fetching doctors: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid">
    <div class="dashboard-wrapper">
        <main class="dashboard-main">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="dashboard-title display-6 fw-bold mb-0 d-flex align-items-center gap-2">
                    Doctors Management
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="doctors.php?action=add" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add Doctor
                    </a>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"> <?php echo $error; ?> </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"> <?php echo $success; ?> </div>
            <?php endif; ?>
            <?php if ($action == 'add' || ($action == 'edit' && isset($_GET['id']))): ?>
                <?php
                $edit_doctor = null;
                if ($action == 'edit' && isset($_GET['id'])) {
                    $edit_id = (int)$_GET['id'];
                    $stmt = $pdo->prepare("SELECT d.*, u.full_name, u.username, u.email, u.is_active, u.phone, u.address FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
                    $stmt->execute([$edit_id]);
                    $edit_doctor = $stmt->fetch();
                }
                ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['full_name']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['username']) : ''; ?>">
                            </div>
                            <?php if ($action == 'edit'): ?>
                            <div class="mb-3">
                                <label for="password" class="form-label">Change Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['email']) : ''; ?>">
                            </div>
                            <?php if ($action == 'add'): ?>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="specialization" class="form-label">Specialization *</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['specialization']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address *</label>
                                <input type="text" class="form-control" id="address" name="address" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['address']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number *</label>
                                <input type="text" class="form-control" id="phone" name="phone" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['phone']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="license_number" class="form-label">License Number *</label>
                                <input type="text" class="form-control" id="license_number" name="license_number" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['license_number']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="consultation_fee" class="form-label">Consultation Fee *</label>
                                <input type="number" step="0.01" class="form-control" id="consultation_fee" name="consultation_fee" required value="<?php echo $edit_doctor ? htmlspecialchars($edit_doctor['consultation_fee']) : ''; ?>">
                            </div>
                            <?php if ($action == 'edit'): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($edit_doctor && $edit_doctor['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $action == 'edit' ? 'Update Doctor' : 'Add Doctor'; ?>
                            </button>
                            <a href="doctors.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Doctors List (<?php echo count($doctors); ?> total)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle text-center">
                                <thead class="table-light">
                                    <tr>
                                        <th>Doctor ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Specialization</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctors as $doctor): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($doctor['doctor_id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($doctor['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                        <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                        <td>
                                            <?php if ($doctor['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="doctors.php?action=edit&id=<?php echo $doctor['id']; ?>" class="btn btn-warning btn-sm me-1" title="Edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
