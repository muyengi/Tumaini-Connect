<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function require_login(): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: /Tumaini-Connect/auth/login.php');
        exit;
    }
}

function login_user(mysqli $conn, string $email, string $password): bool
{
    $sql = 'SELECT id, name, email, password, role, union_id, conference_id FROM users WHERE email = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    unset($user['password']);
    $_SESSION['user'] = $user;
    session_regenerate_id(true);
    return true;
}

function login_station_coordinator(mysqli $conn, string $stationName, string $phonePassword): bool
{
    $stationName = trim($stationName);
    if ($stationName === '') {
        return false;
    }

    $cleanPhone = normalize_phone($phonePassword);
    if ($cleanPhone === '') {
        return false;
    }

    $sql = 'SELECT c.id center_id, c.name center_name, c.coordinator_name, c.coordinator_phone, c.union_id, c.conference_id
            FROM centers c
            WHERE LOWER(c.name) = LOWER(?)
            LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $stationName);
    $stmt->execute();
    $center = $stmt->get_result()->fetch_assoc();

    if (!$center || normalize_phone((string) $center['coordinator_phone']) !== $cleanPhone) {
        return false;
    }

    $userEmail = 'station' . (int) $center['center_id'] . '@tumaini.local';

    $userSql = 'SELECT id, name, email, role, union_id, conference_id FROM users WHERE email = ? LIMIT 1';
    $userStmt = $conn->prepare($userSql);
    $userStmt->bind_param('s', $userEmail);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();

    if (!$user) {
        $createSql = 'INSERT INTO users (name, email, password, role, union_id, conference_id) VALUES (?, ?, ?, "coordinator", ?, ?)';
        $createStmt = $conn->prepare($createSql);
        $passwordHash = password_hash($cleanPhone, PASSWORD_DEFAULT);
        $name = (string) $center['coordinator_name'];
        $unionId = (int) $center['union_id'];
        $conferenceId = (int) $center['conference_id'];
        $createStmt->bind_param('sssii', $name, $userEmail, $passwordHash, $unionId, $conferenceId);
        if (!$createStmt->execute()) {
            return false;
        }

        $user = [
            'id' => $createStmt->insert_id,
            'name' => $name,
            'email' => $userEmail,
            'role' => 'coordinator',
            'union_id' => $unionId,
            'conference_id' => $conferenceId,
        ];
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
        'union_id' => (int) $user['union_id'],
        'conference_id' => (int) $user['conference_id'],
    ];
    session_regenerate_id(true);

    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
