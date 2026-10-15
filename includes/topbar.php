<?php
// includes/topbar.php
?>
<nav class="navbar navbar-expand-lg px-3 py-2">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-secondary me-3" id="menu-toggle"><i class="fas fa-bars"></i></button>
        <span class="fs-5 fw-bold text-wood d-none d-md-block">Dashboard</span>
    </div>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav ms-auto mt-2 mt-lg-0 align-items-center">
            <li class="nav-item me-3">
                <a class="btn btn-wood btn-sm" href="pos.php"><i class="fas fa-plus-circle me-1"></i> POS</a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <span><?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User' ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <div class="dropdown-header">
                        <strong><?= isset($_SESSION['role_name']) ? htmlspecialchars($_SESSION['role_name']) : 'Role' ?></strong>
                        <?php if (isset($_SESSION['branch_name'])): ?>
                            <br><small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($_SESSION['branch_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="profile.php"><i class="fas fa-id-card fa-fw me-2"></i> Profile</a>
                    <a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout</a>
                </div>
            </li>
        </ul>
    </div>
</nav>