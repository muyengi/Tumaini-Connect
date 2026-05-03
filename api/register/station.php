<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$unionId          = (int)   ($_POST['union_id']          ?? 0);
$conferenceId     = (int)   ($_POST['conference_id']      ?? 0);
$name             = trim(          $_POST['name']             ?? '');
$type             = trim(          $_POST['type']             ?? 'Church');
$region           = trim(          $_POST['region']           ?? '');
$district         = trim(          $_POST['district']         ?? '');
$ward             = trim(          $_POST['ward']             ?? '');
$coordinatorName  = trim(          $_POST['coordinator_name'] ?? '');
$coordinatorPhone = normalize_phone($_POST['coordinator_phone'] ?? '');
$coordinatorEmail = trim(          $_POST['coordinator_email'] ?? '');
$place            = trim(          $_POST['place']             ?? '');
$latitude         = $_POST['latitude']  !== '' ? (float) ($_POST['latitude']  ?? 0) : null;
$longitude        = $_POST['longitude'] !== '' ? (float) ($_POST['longitude'] ?? 0) : null;

// Clamp coordinate ranges
if ($latitude  !== null && ($latitude  < -90  || $latitude  > 90))  $latitude  = null;
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $longitude = null;

$allowedTypes = ['Church', 'Home', 'School', 'Group', 'Institution'];

if (
    $unionId <= 0 ||
    $conferenceId <= 0 ||
    $name === '' ||
    $region === '' ||
    $district === '' ||
    $ward === '' ||
    $coordinatorName === '' ||
    $coordinatorPhone === '' ||
    !in_array($type, $allowedTypes, true)
) {
    echo json_encode(['ok' => false, 'error' => 'Please fill all required fields correctly.']);
    exit;
}

/* Validate email if provided */
if ($coordinatorEmail !== '' && !filter_var($coordinatorEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

/* Check for duplicate station name */
$dup = $conn->prepare('SELECT id FROM centers WHERE LOWER(name) = LOWER(?) LIMIT 1');
$dup->bind_param('s', $name);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    echo json_encode(['ok' => false, 'error' => 'A station with this name already exists.']);
    exit;
}
$dup->close();

/* Verify union + conference belong together */
$chk = $conn->prepare('SELECT id FROM conferences WHERE id = ? AND union_id = ? LIMIT 1');
$chk->bind_param('ii', $conferenceId, $unionId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'Selected conference does not belong to the selected union.']);
    exit;
}
$chk->close();

/* Insert station */
$stmt = $conn->prepare(
    'INSERT INTO centers (union_id, conference_id, name, type, region, district, ward,
     place, latitude, longitude,
     coordinator_name, coordinator_phone, coordinator_email)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param(
    'iissssssddsss',
    $unionId, $conferenceId, $name, $type, $region, $district, $ward,
    $place, $latitude, $longitude,
    $coordinatorName, $coordinatorPhone, $coordinatorEmail
);

if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => 'Could not register station. Please try again.']);
    exit;
}

$centerId = (int) $stmt->insert_id;
$stmt->close();

/* Create coordinator user account */
$accountEmail        = 'station' . $centerId . '@tumaini.local';
$accountPasswordHash = password_hash($coordinatorPhone, PASSWORD_DEFAULT);

$user = $conn->prepare(
    'INSERT INTO users (name, email, password, role, union_id, conference_id)
     VALUES (?, ?, ?, "coordinator", ?, ?)'
);
$user->bind_param('sssii', $coordinatorName, $accountEmail, $accountPasswordHash, $unionId, $conferenceId);
$user->execute();
$user->close();

echo json_encode([
    'ok'               => true,
    'station_name'     => $name,
    'coordinator_phone' => $coordinatorPhone,
]);
