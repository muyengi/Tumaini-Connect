<?php
declare(strict_types=1);

/**
 * Tumaini Connect — Daily Summary SMS Cron
 *
 * Schedule via Windows Task Scheduler or crontab:
 *   crontab:  0 20 * * * php /path/to/Tumaini-Connect/cron/daily_sms.php
 *   Windows:  schtasks /create /tn "TumainiDailySMS" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\Tumaini-Connect\cron\daily_sms.php" /sc daily /st 20:00
 *
 * Sends per-admin scoped attendance + interest summaries for today.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/sms.php';

// ── Fetch all admin users who have a phone number ─────────────────────────────
$admins = $conn->query(
    "SELECT id, name, phone, role, union_id, conference_id
     FROM users
     WHERE role IN ('tanzania_admin','union_admin','conference_admin')
       AND phone IS NOT NULL AND phone <> ''"
)->fetch_all(MYSQLI_ASSOC);

if (empty($admins)) {
    echo "No admin users with phone numbers found. Exiting.\n";
    exit;
}

$today = date('Y-m-d');

foreach ($admins as $admin) {
    $role         = (string) $admin['role'];
    $unionId      = (int)   ($admin['union_id']      ?? 0);
    $conferenceId = (int)   ($admin['conference_id'] ?? 0);

    // ── Build scope WHERE clause ──────────────────────────────────────────────
    $centerWhere  = '1=1';
    $centerTypes  = '';
    $centerParams = [];

    if ($role === 'union_admin' && $unionId > 0) {
        $centerWhere  = 'c.union_id = ?';
        $centerTypes  = 'i';
        $centerParams = [$unionId];
    } elseif ($role === 'conference_admin' && $conferenceId > 0) {
        $centerWhere  = 'c.conference_id = ?';
        $centerTypes  = 'i';
        $centerParams = [$conferenceId];
    }

    // ── Scope label ──────────────────────────────────────────────────────────
    $scopeName = 'Tanzania';
    if ($role === 'union_admin' && $unionId > 0) {
        $r = $conn->prepare('SELECT name FROM unions WHERE id = ? LIMIT 1');
        $r->bind_param('i', $unionId);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        if ($row) {
            $scopeName = (string) $row['name'];
        }
    } elseif ($role === 'conference_admin' && $conferenceId > 0) {
        $r = $conn->prepare('SELECT name FROM conferences WHERE id = ? LIMIT 1');
        $r->bind_param('i', $conferenceId);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        if ($row) {
            $scopeName = (string) $row['name'];
        }
    }

    // ── Attendance summary for today ──────────────────────────────────────────
    $attSql = "SELECT
            COUNT(*) reports_count,
            COALESCE(SUM(mr.elderly_men), 0) elderly_men,
            COALESCE(SUM(mr.elderly_women), 0) elderly_women,
            COALESCE(SUM(mr.children_boys), 0) children_boys,
            COALESCE(SUM(mr.children_girls), 0) children_girls,
            COALESCE(SUM(mr.members_attended), 0) members_attended,
            COALESCE(SUM(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended), 0) total_attendance,
            COUNT(DISTINCT mr.center_id) active_stations
        FROM meeting_reports mr
        JOIN centers c ON mr.center_id = c.id
        WHERE $centerWhere AND DATE(mr.created_at) = ?";

    $attParams  = array_merge($centerParams, [$today]);
    $attTypes   = $centerTypes . 's';
    $attStmt    = $conn->prepare($attSql);
    if ($attTypes !== '') {
        $attStmt->bind_param($attTypes, ...$attParams);
    }
    $attStmt->execute();
    $attData = $attStmt->get_result()->fetch_assoc() ?: [];

    // Total stations in scope
    $totalStSql  = "SELECT COUNT(*) cnt FROM centers c WHERE $centerWhere";
    $totalStStmt = $conn->prepare($totalStSql);
    if ($centerTypes !== '') {
        $totalStStmt->bind_param($centerTypes, ...$centerParams);
    }
    $totalStStmt->execute();
    $totalStations = (int) ($totalStStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    $attData['active_stations'] = (int) ($attData['active_stations'] ?? 0);
    $attData['total_stations']  = $totalStations;

    // ── Interest summary ──────────────────────────────────────────────────────
    $intSql = "SELECT
            SUM(CASE WHEN DATE(i.created_at) = ? THEN 1 ELSE 0 END) new_today,
            COUNT(*)                                                  total,
            SUM(CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END)        worked,
            SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END)  completed
        FROM interests i
        JOIN centers c ON i.center_id = c.id
        LEFT JOIN followups f ON f.interest_id = i.id
        WHERE $centerWhere";

    $intParams = array_merge([$today], $centerParams);
    $intTypes  = 's' . $centerTypes;
    $intStmt   = $conn->prepare($intSql);
    if ($intTypes !== '') {
        $intStmt->bind_param($intTypes, ...$intParams);
    }
    $intStmt->execute();
    $intData = $intStmt->get_result()->fetch_assoc() ?: [];

    // ── Send ──────────────────────────────────────────────────────────────────
    sms_send_daily_summary($admin, $attData, $intData, $scopeName);

    echo "Sent daily summary to {$admin['name']} ({$admin['phone']}) — $scopeName\n";
}

echo "Done.\n";
