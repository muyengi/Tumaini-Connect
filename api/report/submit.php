<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$centerId = (int) ($_SESSION['report_center_id'] ?? 0);
if ($centerId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated. Please verify your station first.']);
    exit;
}

// Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS meeting_reports (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        center_id       INT UNSIGNED NOT NULL,
        report_date     DATE         NOT NULL,
        elderly_men     INT UNSIGNED NOT NULL DEFAULT 0,
        elderly_women   INT UNSIGNED NOT NULL DEFAULT 0,
        children_boys   INT UNSIGNED NOT NULL DEFAULT 0,
        children_girls  INT UNSIGNED NOT NULL DEFAULT 0,
        members_attended INT UNSIGNED NOT NULL DEFAULT 0,
        images          TEXT         NULL,
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mr_center (center_id),
        KEY idx_mr_date   (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Validate and sanitise inputs
$reportDate = $_POST['report_date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) {
    $reportDate = date('Y-m-d');
}

$elderlyMen    = max(0, (int) ($_POST['elderly_men']     ?? 0));
$elderlyWomen  = max(0, (int) ($_POST['elderly_women']   ?? 0));
$childrenBoys  = max(0, (int) ($_POST['children_boys']   ?? 0));
$childrenGirls = max(0, (int) ($_POST['children_girls']  ?? 0));
$membersAtt    = max(0, (int) ($_POST['members_attended'] ?? 0));

// File uploads
$uploadDir = __DIR__ . '/../../uploads/reports/' . $centerId . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    // Prevent direct PHP execution inside uploads
    file_put_contents($uploadDir . '.htaccess', "Options -ExecCGI\nRemoveHandler .php .phtml .php3 .phar\nAddType text/plain .php .phtml .php3 .phar\n");
}

$allowedExt  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'];
$allowedMime = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/quicktime',
];
$savedFiles  = [];

if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $count = count($_FILES['images']['name']);
    for ($i = 0; $i < $count && count($savedFiles) < 10; $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        $origName = (string) $_FILES['images']['name'][$i];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }
        // Verify actual MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['images']['tmp_name'][$i]);
        if (!in_array($mime, $allowedMime, true)) {
            continue;
        }
        // 20 MB max per file
        if ($_FILES['images']['size'][$i] > 20 * 1024 * 1024) {
            continue;
        }
        $newName = bin2hex(random_bytes(12)) . '.' . $ext;
        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $newName)) {
            $savedFiles[] = 'uploads/reports/' . $centerId . '/' . $newName;
        }
    }
}

$imagesJson = $savedFiles ? json_encode($savedFiles) : null;

$stmt = $conn->prepare(
    'INSERT INTO meeting_reports
        (center_id, report_date, elderly_men, elderly_women, children_boys, children_girls, members_attended, images)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('isiiiiis', $centerId, $reportDate, $elderlyMen, $elderlyWomen, $childrenBoys, $childrenGirls, $membersAtt, $imagesJson);

if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'message' => 'Report submitted successfully.']);
} else {
    echo json_encode(['ok' => false, 'error' => 'Failed to save report. Please try again.']);
}
