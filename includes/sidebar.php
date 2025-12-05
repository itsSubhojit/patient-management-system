<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'patients.php') !== false) ? 'active' : ''; ?>" href="patients.php">
                    <i class="fas fa-users"></i> Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'appointments.php') !== false) ? 'active' : ''; ?>" href="appointments.php">
                    <i class="fas fa-calendar-alt"></i> Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'medical-history.php') !== false) ? 'active' : ''; ?>" href="medical-history.php">
                    <i class="fas fa-notes-medical"></i> Medical History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'prescriptions.php') !== false) ? 'active' : ''; ?>" href="prescriptions.php">
                    <i class="fas fa-pills"></i> Prescriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'lab-tests.php') !== false) ? 'active' : ''; ?>" href="lab-tests.php">
                    <i class="fas fa-flask"></i> Lab Tests
                </a>
            </li>
            
            <?php if (hasRole('admin') || hasRole('doctor')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'billing.php') !== false) ? 'active' : ''; ?>" href="billing.php">
                    <i class="fas fa-dollar-sign"></i> Billing
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasRole('admin')): ?>
            <hr>
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Administration</span>
            </h6>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'users.php') !== false) ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users-cog"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'doctors.php') !== false) ? 'active' : ''; ?>" href="doctors.php">
                    <i class="fas fa-user-md"></i> Doctors
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'system-settings.php') !== false) ? 'active' : ''; ?>" href="system-settings.php">
                    <i class="fas fa-cogs"></i> System Settings
                </a>
            </li>
            <?php endif; ?>

            <hr>
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Reports</span>
            </h6>
            <li class="nav-item">
                <a class="nav-link" href="reports/daily.php">
                    <i class="fas fa-calendar-day"></i> Daily Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports/monthly.php">
                    <i class="fas fa-calendar-month"></i> Monthly Reports
                </a>
            </li>
            
            <hr>
            <li class="nav-item">
                <a class="nav-link" href="backup.php">
                    <i class="fas fa-database"></i> Backup & Restore
                </a>
            </li>
        </ul>
    </div>
</nav>