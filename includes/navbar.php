<nav class="navbar navbar-expand-lg modern-navbar fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <span class="icon-circle bg-gradient-primary text-white"><i class="fas fa-hospital-alt"></i></span>
            <span class="fw-bold fs-4"><?php echo SITE_NAME; ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-2">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2" href="patients.php">
                        <i class="fas fa-users"></i> <span>Patients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-2" href="appointments.php">
                        <i class="fas fa-calendar-alt"></i> <span>Appointments</span>
                    </a>
                </li>
                <?php if (hasRole('admin')): ?>
                 <li class="nav-item">
                     <a class="nav-link d-flex align-items-center gap-2" href="doctors.php">
                         <i class="fas fa-user-md"></i> <span>Doctors</span>
                     </a>
                 </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <span class="icon-circle bg-gradient-secondary text-white"><i class="fas fa-user-circle"></i></span>
                        <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($_SESSION['role']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>