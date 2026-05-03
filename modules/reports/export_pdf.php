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
    ORDER BY mr.report_date DESC, c.name ASC
    LIMIT 1200';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalAttendance = 0;
foreach ($rows as $row) {
    $totalAttendance += (int) $row['total_attendance'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance Report PDF</title>
<style>
body {
    font-family: Arial, sans-serif;
    color: #1b1b2e;
    margin: 22px;
}
.header {
    border-bottom: 2px solid #f9bf3f;
    margin-bottom: 12px;
    padding-bottom: 8px;
}
.header h1 {
    margin: 0;
    font-size: 20px;
}
.meta {
    font-size: 12px;
    color: #444;
}
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}
th, td {
    border: 1px solid #d8d8d8;
    padding: 6px;
    text-align: left;
}
th {
    background: #fff6de;
}
.summary {
    margin: 10px 0;
    font-size: 12px;
}
@media print {
    .no-print {
        display: none;
    }
}
</style>
</head>
<body>
    <div class="no-print" style="margin-bottom:10px;">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="header">
        <h1>Tumaini Connect - Attendance Report</h1>
        <div class="meta">Generated: <?= htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="meta">Date Range: <?= htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <div class="summary">
        Rows: <strong><?= count($rows) ?></strong> |
        Total Attendance: <strong><?= $totalAttendance ?></strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Union</th>
                <th>Conference</th>
                <th>Church/Station</th>
                <th>Men</th>
                <th>Women</th>
                <th>Boys</th>
                <th>Girls</th>
                <th>Members</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= htmlspecialchars((string) $r['report_date'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['union_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['conference_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $r['center_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $r['elderly_men'] ?></td>
                <td><?= (int) $r['elderly_women'] ?></td>
                <td><?= (int) $r['children_boys'] ?></td>
                <td><?= (int) $r['children_girls'] ?></td>
                <td><?= (int) $r['members_attended'] ?></td>
                <td><strong><?= (int) $r['total_attendance'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
            <tr><td colspan="10">No records found for selected filters.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
