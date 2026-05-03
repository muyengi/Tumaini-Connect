<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$stationName = trim($_POST['station_name'] ?? '');
$phone       = $_POST['phone'] ?? '';

if ($stationName === '' || $phone === '') {
    echo json_encode(['ok' => false, 'error' => 'Station name and mobile number are required.']);
    exit;
}

if (login_station_coordinator($conn, $stationName, $phone)) {
    $stmt = $conn->prepare(
        'SELECT id, name FROM centers WHERE LOWER(name) = LOWER(?) LIMIT 1'
    );
    $stmt->bind_param('s', $stationName);
    $stmt->execute();
    $center = $stmt->get_result()->fetch_assoc();

    if ($center) {
        $_SESSION['report_center_id']   = (int)    $center['id'];
        $_SESSION['report_center_name'] = (string) $center['name'];
        echo json_encode([
            'ok'          => true,
            'center_id'   => (int)    $center['id'],
            'center_name' => (string) $center['name'],
        ]);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Invalid station name or mobile number.']);
