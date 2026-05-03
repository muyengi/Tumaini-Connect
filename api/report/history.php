<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$centerId = (int) ($_SESSION['report_center_id'] ?? 0);
if ($centerId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

// Ensure table exists (idempotent)
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

$stmt = $conn->prepare(
    'SELECT id, report_date, elderly_men, elderly_women, children_boys, children_girls,
            members_attended, images, created_at
     FROM meeting_reports
     WHERE center_id = ?
     ORDER BY report_date DESC
     LIMIT 50'
);
$stmt->bind_param('i', $centerId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$row) {
    $row['images'] = $row['images'] ? json_decode($row['images'], true) : [];
    // cast numbers
    foreach (['elderly_men','elderly_women','children_boys','children_girls','members_attended'] as $col) {
        $row[$col] = (int) $row[$col];
    }
}
unset($row);

$followupStmt = $conn->prepare(
    'SELECT
        i.full_name,
        i.phone,
        i.request_type,
        i.status interest_status,
        f.status followup_status,
        f.followup_date,
        f.remarks,
        f.created_at,
        u.name assigned_name,
        u.role assigned_role
     FROM interests i
     JOIN followups f ON f.interest_id = i.id
     JOIN users u ON u.id = f.assigned_to
     WHERE i.center_id = ?
     ORDER BY COALESCE(f.followup_date, DATE(f.created_at)) DESC, f.id DESC
     LIMIT 50'
);
$followupStmt->bind_param('i', $centerId);
$followupStmt->execute();
$followupRows = $followupStmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($followupRows as &$followup) {
    $followup['whatsapp_url'] = wa_link((string) ($followup['phone'] ?? ''));
}
unset($followup);

echo json_encode(['ok' => true, 'reports' => $rows, 'followups' => $followupRows]);
