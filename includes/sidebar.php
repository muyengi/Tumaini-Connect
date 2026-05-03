<?php
$user = current_user();
$role = $user['role'] ?? '';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = '/Tumaini-Connect';
if (str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = '/' . ltrim($requestPath, '/');

function tc_active(array $segments, string $currentPath): string {
    foreach ($segments as $segment) {
        $segment = '/' . trim($segment, '/');
        if ($currentPath === $segment || str_starts_with($currentPath, $segment . '/')) {
            return 'active';
        }
    }
    return '';
}
?>
<nav class="sidebar sidebar-offcanvas tc-sidebar" id="sidebar">
    <ul class="nav">

        <li class="nav-item nav-profile tc-sidebar-profile">
            <a href="#" class="nav-link">
                <div class="nav-profile-image">
                    <span class="mdi mdi-account-circle" style="font-size:2.4rem;line-height:1;color:#b66dff;"></span>
                    <span class="login-status online"></span>
                </div>
                <div class="nav-profile-text d-flex flex-column">
                    <span class="font-weight-bold mb-2"><?= e($user['name'] ?? 'Guest') ?></span>
                    <span class="text-secondary text-small"><?= e(role_label($role)) ?></span>
                </div>
            </a>
        </li>

        <?php if (is_admin_role($role)): ?>
        <li class="nav-item <?= tc_active(['/admin/dashboard.php'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/admin/dashboard.php">
                <span class="menu-title">Dashboard</span>
                <i class="mdi mdi-home menu-icon"></i>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-category"><span class="nav-link">Management</span></li>

        <?php if ($role === 'tanzania_admin'): ?>
        <li class="nav-item <?= tc_active(['/modules/unions'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/unions/index.php">
                <span class="menu-title">Unions</span>
                <i class="mdi mdi-source-branch menu-icon"></i>
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($role, ['tanzania_admin', 'union_admin'], true)): ?>
        <li class="nav-item <?= tc_active(['/modules/conferences'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/conferences/index.php">
                <span class="menu-title">Conferences / Fields</span>
                <i class="mdi mdi-bank menu-icon"></i>
            </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($role, ['tanzania_admin', 'union_admin', 'conference_admin'], true)): ?>
        <li class="nav-item <?= tc_active(['/modules/centers'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/centers/index.php">
                <span class="menu-title">Stations</span>
                <i class="mdi mdi-home-city menu-icon"></i>
            </a>
        </li>

        <li class="nav-item <?= tc_active(['/modules/users'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/users/index.php">
                <span class="menu-title">Users</span>
                <i class="mdi mdi-account-multiple menu-icon"></i>
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-category"><span class="nav-link">Outreach</span></li>

        <li class="nav-item <?= tc_active(['/modules/interests'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/interests/index.php">
                <span class="menu-title">Interests</span>
                <i class="mdi mdi-account-details menu-icon"></i>
            </a>
        </li>

        <li class="nav-item <?= tc_active(['/modules/followups'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/followups/index.php">
                <span class="menu-title">Follow-up</span>
                <i class="mdi mdi-refresh menu-icon"></i>
            </a>
        </li>

        <li class="nav-item <?= tc_active(['/modules/reports'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/reports/index.php">
                <span class="menu-title">Reports</span>
                <i class="mdi mdi-chart-bar menu-icon"></i>
            </a>
        </li>

        <li class="nav-category"><span class="nav-link">External</span></li>

        <li class="nav-item">
            <a class="nav-link" href="/Tumaini-Connect/modules/interests/public_form.php" target="_blank">
                <span class="menu-title">Public Form</span>
                <i class="mdi mdi-earth menu-icon"></i>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="/Tumaini-Connect/auth/logout.php">
                <span class="menu-title">Logout</span>
                <i class="mdi mdi-logout menu-icon"></i>
            </a>
        </li>

        <li class="nav-item <?= tc_active(['/modules/profile'], $requestPath) ?>">
            <a class="nav-link" href="/Tumaini-Connect/modules/profile/index.php">
                <span class="menu-title">My Profile</span>
                <i class="mdi mdi-account-cog menu-icon"></i>
            </a>
        </li>

    </ul>
</nav>
