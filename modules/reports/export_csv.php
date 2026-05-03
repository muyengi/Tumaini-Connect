<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['tanzania_admin', 'union_admin', 'conference_admin']);

$user = current_user();
$role = (string) ($user['role'] ?? '');

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-30 days'));

$fromDate = (string) ($_GET['from_date'] ?? $defaultFrom);
$toDate = (string) ($_GET['to_date'] ?? $today);
$unionFilter = (int) ($_GET['union_id'] ?? 0);
$conferenceFilter = (int) ($_GET['conference_id'] ?? 0);
$centerFilter = (int) ($_GET['center_id'] ?? 0);
$centerSearch = trim((string) ($_GET['center_search'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = $defaultFrom;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = $today;
}
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$conditions = ['mr.report_date BETWEEN ? AND ?'];
$types = 'ss';
$params = [$fromDate, $toDate];

$addWhere = static function (string $sql, string $type = '', $value = null) use (&$conditions, &$types, &$params): void {
    $conditions[] = $sql;
    if ($type !== '') {
        $types .= $type;
        $params[] = $value;
    }
};

if ($role === 'union_admin') {
    $addWhere('c.union_id = ?', 'i', (int) $user['union_id']);
} elseif ($role === 'conference_admin') {
    $addWhere('c.conference_id = ?', 'i', (int) $user['conference_id']);
}

if ($role === 'tanzania_admin' && $unionFilter > 0) {
    $addWhere('c.union_id = ?', 'i', $unionFilter);
}
if (($role === 'tanzania_admin' || $role === 'union_admin') && $conferenceFilter > 0) {
    $addWhere('c.conference_id = ?', 'i', $conferenceFilter);
}
if ($centerFilter > 0) {
    $addWhere('c.id = ?', 'i', $centerFilter);
}
if ($centerSearch !== '') {
    $addWhere('c.name LIKE ?', 's', '%' . $centerSearch . '%');
}

$whereSql = implode(' AND ', $conditions);

$sql = 'SELECT
        mr.report_date,
        u.name union_name,
        cf.name conference_name,
        c.name center_name,
        mr.elderly_men,
        mr.elderly_women,
        mr.children_boys,
        mr.children_girls,
        mr.members_attended,
        (mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended) total_attendance
    FROM meeting_reports mr
    JOIN centers c ON c.id = mr.center_id
    JOIN conferences cf ON cf.id = c.conference_id
    JOIN unions u ON u.id = c.union_id
    WHERE ' . $whereSql . '
    ORDER BY mr.report_date DESC, c.name ASC';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_reports_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Report Date', 'Union', 'Conference', 'Church/Station', 'Elderly Men', 'Elderly Women', 'Children Boys', 'Children Girls', 'Members', 'Total Attendance']);

while ($row = $res->fetch_assoc()) {
    fputcsv($output, [
        $row['report_date'],
        $row['union_name'],
        $row['conference_name'],
        $row['center_name'],
        $row['elderly_men'],
        $row['elderly_women'],
        $row['children_boys'],
        $row['children_girls'],
        $row['members_attended'],
        $row['total_attendance'],
    ]);
}

fclose($output);
exit;
