<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function has_role(array $roles): bool
{
    return isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], $roles, true);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_role(array $roles): void
{
    if (!has_role($roles)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function normalize_phone(string $phone): string
{
    return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
}

function wa_link(string $phone): string
{
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '0')) {
        $clean = '255' . substr($clean, 1);
    }
    return 'https://wa.me/' . $clean;
}

function role_label(string $role): string
{
    $map = [
        'tanzania_admin' => 'Tanzania Admin',
        'union_admin' => 'Union Admin',
        'conference_admin' => 'Conference Admin',
        'coordinator' => 'Coordinator',
        'followup' => 'Follow-up Officer',
    ];

    return $map[$role] ?? $role;
}

function role_home(string $role): string
{
    if ($role === 'coordinator') {
        return '/Tumaini-Connect/modules/interests/index.php';
    }

    if ($role === 'followup') {
        return '/Tumaini-Connect/modules/followups/index.php';
    }

    return '/Tumaini-Connect/admin/dashboard.php';
}

function is_admin_role(string $role): bool
{
    return in_array($role, ['tanzania_admin', 'union_admin', 'conference_admin'], true);
}

function coordinator_station_id(?array $user): ?int
{
    if (!$user || ($user['role'] ?? '') !== 'coordinator') {
        return null;
    }

    $email = (string) ($user['email'] ?? '');
    if (preg_match('/^station(\d+)@tumaini\.local$/', $email, $matches) === 1) {
        return (int) $matches[1];
    }

    return null;
}
