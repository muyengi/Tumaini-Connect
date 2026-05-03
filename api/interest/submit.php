<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/sms.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$fullName    = trim($_POST['full_name']    ?? '');
$gender      = trim($_POST['gender']       ?? '');
$age         = ($_POST['age'] ?? '') !== '' ? (int) $_POST['age'] : null;
$phone       = normalize_phone($_POST['phone'] ?? '');
$requestType = trim($_POST['request_type'] ?? '');
$region      = trim($_POST['region']       ?? '');
$district    = trim($_POST['district']     ?? '');
$ward        = trim($_POST['ward']         ?? '');
$notes       = trim($_POST['notes']        ?? '');

/* centre_id from session (station report session) or from POST if public */
$centerId = !empty($_SESSION['report_center_id'])
    ? (int) $_SESSION['report_center_id']
    : (int) ($_POST['center_id'] ?? 0);

$allowedRequest = ['baptism', 'bible_study', 'prayer', 'visit', 'church_connection'];
$allowedGender  = ['Male', 'Female', 'Other'];

if ($fullName === '' || $phone === '' || $centerId <= 0 || !in_array($requestType, $allowedRequest, true)) {
    echo json_encode(['ok' => false, 'error' => 'Please fill all required fields.']);
    exit;
}

if ($gender !== '' && !in_array($gender, $allowedGender, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid gender value.']);
    exit;
}

if ($age !== null && ($age < 1 || $age > 120)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid age.']);
    exit;
}

/* Verify center exists */
$chk = $conn->prepare('SELECT id FROM centers WHERE id = ? LIMIT 1');
$chk->bind_param('i', $centerId);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid station. Please verify your station first.']);
    exit;
}
$chk->close();

/* Build notes: prepend location if given */
$locationLine = implode(' / ', array_filter([$region, $district, $ward]));
if ($locationLine !== '') {
    $notes = trim('Location: ' . $locationLine . ($notes !== '' ? "\n" . $notes : ''));
}

$stmt = $conn->prepare(
    'INSERT INTO interests (center_id, full_name, gender, age, phone, request_type, additional_notes, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
);
$stmt->bind_param('ississs', $centerId, $fullName, $gender, $age, $phone, $requestType, $notes);

if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => 'Could not save your request. Please try again.']);
    exit;
}
$newInterestId = (int) $conn->insert_id;
$stmt->close();

// Notify station coordinator by SMS
$centerRow = $conn->prepare('SELECT name, coordinator_phone FROM centers WHERE id = ? LIMIT 1');
$centerRow->bind_param('i', $centerId);
$centerRow->execute();
$centerData = $centerRow->get_result()->fetch_assoc();
if ($centerData) {
    sms_notify_coordinator_new_interest([
        'full_name'    => $fullName,
        'phone'        => $phone,
        'request_type' => $requestType,
    ], $centerData);
}

echo json_encode(['ok' => true]);
