<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$r = $conn->query(
    "SELECT
       COALESCE(SUM(elderly_men+elderly_women+children_boys+children_girls+members_attended),0) total,
       COALESCE(SUM(elderly_men+elderly_women+children_boys+children_girls),0) visitors
     FROM meeting_reports
     WHERE report_date = CURDATE()"
);
$row      = $r ? $r->fetch_assoc() : ['total'=>0,'visitors'=>0];

echo json_encode([
    'ok'       => true,
    'total'    => (int)$row['total'],
    'visitors' => (int)$row['visitors'],
    'date'     => date('d M Y'),
    'ymd'      => date('Y-m-d'),
]);
