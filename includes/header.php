<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/functions.php';
$user = current_user();
$flash = get_flash();
$homeHref = '/Tumaini-Connect/';
if ($user) {
    $homeHref = role_home((string) ($user['role'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? e($pageTitle) : 'Tumaini Connect' ?></title>
    <link href="/Tumaini-Connect/assets/vendors/mdi/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="/Tumaini-Connect/assets/vendors/css/vendor.bundle.base.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="/Tumaini-Connect/assets/css/style.css" rel="stylesheet">
    <link href="/Tumaini-Connect/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="container-scroller">

    <!-- ======= Top Navbar ======= -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
        <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
            <a class="navbar-brand brand-logo" href="<?= e($homeHref) ?>">
                <span class="fw-bold fs-5 text-white">Tumaini Connect</span>
            </a>
            <a class="navbar-brand brand-logo-mini" href="<?= e($homeHref) ?>">
                <span class="fw-bold text-white">TC</span>
            </a>
        </div>
        <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
            <?php if ($user): ?>
            <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
                <span class="mdi mdi-menu"></span>
            </button>
            <ul class="navbar-nav navbar-nav-right">
                <li class="nav-item nav-profile dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#"
                       data-bs-toggle="dropdown" id="profileDropdown" role="button"
                       aria-expanded="false">
                        <span class="tc-nav-avatar d-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
                              style="width:36px;height:36px;background:var(--tc-gold,#f9bf3f);color:#1a2236!important;font-size:.95rem;flex-shrink:0;">
                            <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                        </span>
                        <span class="d-none d-md-inline">
                            <span class="nav-profile-name fw-semibold" style="color:#fff;"><?= e($user['name']) ?></span>
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end navbar-dropdown shadow" aria-labelledby="profileDropdown">
                        <div class="px-3 py-2 border-bottom">
                            <div class="fw-semibold"><?= e($user['name']) ?></div>
                            <div class="text-muted small"><?= e(role_label($user['role'])) ?></div>
                        </div>
                        <a class="dropdown-item py-2" href="/Tumaini-Connect/modules/profile/index.php">
                            <i class="mdi mdi-account-cog me-2 text-primary"></i> My Profile
                        </a>
                        <div class="dropdown-divider my-1"></div>
                        <a class="dropdown-item py-2" href="/Tumaini-Connect/auth/logout.php">
                            <i class="mdi mdi-logout me-2 text-danger"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
            <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
                <span class="mdi mdi-format-line-spacing"></span>
            </button>
            <?php else: ?>
            <ul class="navbar-nav navbar-nav-right">
                <li class="nav-item">
                    <a class="nav-link" href="/Tumaini-Connect/auth/login.php">
                        <i class="mdi mdi-login me-1"></i> Login
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </nav>
    <!-- ======= End Top Navbar ======= -->

    <div class="container-fluid page-body-wrapper">

        <!-- ======= Sidebar ======= -->
        <?php if ($user): include __DIR__ . '/sidebar.php'; endif; ?>
        <!-- ======= End Sidebar ======= -->

        <div class="main-panel">
            <div class="content-wrapper">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <div class="page-header">
                    <h3 class="page-title">
                        <span class="page-title-icon bg-gradient-primary text-white me-2">
                            <i class="mdi mdi-home"></i>
                        </span>
                        <?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?>
                    </h3>
                </div>
