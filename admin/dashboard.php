<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
require_login();

$user = current_user();
$role = (string) ($user['role'] ?? '');
require_role(['tanzania_admin', 'union_admin', 'conference_admin']);

$conn->query(
    'CREATE TABLE IF NOT EXISTS meeting_reports (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        center_id INT UNSIGNED NOT NULL,
        report_date DATE NOT NULL,
        elderly_men INT UNSIGNED NOT NULL DEFAULT 0,
        elderly_women INT UNSIGNED NOT NULL DEFAULT 0,
        children_boys INT UNSIGNED NOT NULL DEFAULT 0,
        children_girls INT UNSIGNED NOT NULL DEFAULT 0,
        members_attended INT UNSIGNED NOT NULL DEFAULT 0,
        images TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mr_center (center_id),
        KEY idx_mr_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// Add phone column to users if not present (one-time migration)
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL DEFAULT NULL AFTER email");

$attendanceView = ($_GET['attendance_view'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';
$windowDefault = $attendanceView === 'daily' ? 14 : 8;
$windowMax = $attendanceView === 'daily' ? 90 : 26;
$window = (int) ($_GET['window'] ?? $windowDefault);
$window = max(1, min($windowMax, $window));

$unionFilter = (int) ($_GET['union_id'] ?? 0);
$conferenceFilter = (int) ($_GET['conference_id'] ?? 0);
$centerFilter = (int) ($_GET['center_id'] ?? 0);
$centerSearch = trim((string) ($_GET['center_search'] ?? ''));

$conditions = [];
$whereTypes = '';
$whereParams = [];

$addWhere = static function (string $sql, string $type = '', $value = null) use (&$conditions, &$whereTypes, &$whereParams): void {
    $conditions[] = $sql;
    if ($type !== '') {
        $whereTypes .= $type;
        $whereParams[] = $value;
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

$whereSql = $conditions ? implode(' AND ', $conditions) : '1=1';

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: /Tumaini-Connect/admin/dashboard.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_interest_worked') {
        $interestId = (int) ($_POST['interest_id'] ?? 0);
        $workStatus = trim((string) ($_POST['work_status'] ?? 'Pending'));
        $workNotes = trim((string) ($_POST['work_notes'] ?? ''));
        $workDate = trim((string) ($_POST['followup_date'] ?? ''));

        $allowedWork = ['Pending', 'Contacted', 'Completed'];
        if (!in_array($workStatus, $allowedWork, true)) {
            set_flash('danger', 'Invalid work status selected.');
        } else {
            $checkSql = 'SELECT i.id
                         FROM interests i
                         JOIN centers c ON i.center_id = c.id
                         WHERE i.id = ? AND ' . $whereSql . '
                         LIMIT 1';
            $checkStmt = $conn->prepare($checkSql);
            $checkTypes = 'i' . $whereTypes;
            $checkParams = array_merge([$interestId], $whereParams);
            $checkStmt->bind_param($checkTypes, ...$checkParams);
            $checkStmt->execute();
            $allowed = $checkStmt->get_result()->fetch_assoc();

            if (!$allowed) {
                set_flash('danger', 'You are not allowed to update this interest.');
            } else {
                $upsert = $conn->prepare(
                    'INSERT INTO followups (interest_id, assigned_to, status, remarks, followup_date)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        assigned_to = VALUES(assigned_to),
                        status = VALUES(status),
                        remarks = VALUES(remarks),
                        followup_date = VALUES(followup_date)'
                );
                $assignedTo = (int) $user['id'];
                $normalizedDate = $workDate === '' ? null : $workDate;
                $upsert->bind_param('iisss', $interestId, $assignedTo, $workStatus, $workNotes, $normalizedDate);

                if ($upsert->execute()) {
                    $interestStatus = $workStatus === 'Completed' ? 'completed' : 'assigned';
                    $updateInterest = $conn->prepare('UPDATE interests SET status = ? WHERE id = ?');
                    $updateInterest->bind_param('si', $interestStatus, $interestId);
                    $updateInterest->execute();

                    // Notify the interest person by SMS when status changes to Contacted or Completed
                    if (in_array($workStatus, ['Contacted', 'Completed'], true)) {
                        $intRow = $conn->prepare('SELECT full_name, phone, request_type FROM interests WHERE id = ? LIMIT 1');
                        $intRow->bind_param('i', $interestId);
                        $intRow->execute();
                        $intData = $intRow->get_result()->fetch_assoc();
                        if ($intData) {
                            sms_notify_interest_followup($intData, (string) $user['name']);
                        }
                    }

                    set_flash('success', 'Interest work details saved.');
                } else {
                    set_flash('danger', 'Could not save interest work details.');
                }
            }
        }
    }

    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /Tumaini-Connect/admin/dashboard.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

$rangeSql = $attendanceView === 'daily'
    ? 'mr.report_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'
    : 'mr.report_date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)';

$attendanceSummarySql = 'SELECT
        COUNT(*) reports_count,
        COALESCE(SUM(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended), 0) total_attendance,
        COALESCE(AVG(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended), 0) avg_attendance
    FROM meeting_reports mr
    JOIN centers c ON mr.center_id = c.id
    WHERE ' . $whereSql . ' AND ' . $rangeSql;
$attendanceSummaryStmt = $conn->prepare($attendanceSummarySql);
$attendanceSummaryTypes = $whereTypes . 'i';
$attendanceSummaryParams = array_merge($whereParams, [$window]);
$attendanceSummaryStmt->bind_param($attendanceSummaryTypes, ...$attendanceSummaryParams);
$attendanceSummaryStmt->execute();
$attendanceSummary = $attendanceSummaryStmt->get_result()->fetch_assoc() ?: [];

$groupExpr = $attendanceView === 'daily'
    ? 'DATE_FORMAT(mr.report_date, "%Y-%m-%d")'
    : 'DATE_FORMAT(DATE_SUB(mr.report_date, INTERVAL WEEKDAY(mr.report_date) DAY), "%Y-%m-%d")';

$trendSql = 'SELECT
        ' . $groupExpr . ' period_label,
        SUM(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended) total_attendance
    FROM meeting_reports mr
    JOIN centers c ON mr.center_id = c.id
    WHERE ' . $whereSql . ' AND ' . $rangeSql . '
    GROUP BY period_label
    ORDER BY period_label ASC';
$trendStmt = $conn->prepare($trendSql);
$trendTypes = $whereTypes . 'i';
$trendParams = array_merge($whereParams, [$window]);
$trendStmt->bind_param($trendTypes, ...$trendParams);
$trendStmt->execute();
$trendRows = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$trendLabels = [];
$trendValues = [];
foreach ($trendRows as $row) {
    $trendLabels[] = (string) $row['period_label'];
    $trendValues[] = (int) $row['total_attendance'];
}

$performanceSql = 'SELECT
        c.name center_name,
        COUNT(*) reports_count,
        SUM(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended) attendance_total,
        ROUND(AVG(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended), 1) avg_attendance
    FROM meeting_reports mr
    JOIN centers c ON mr.center_id = c.id
    WHERE ' . $whereSql . ' AND ' . $rangeSql . '
    GROUP BY c.id, c.name
    ORDER BY avg_attendance DESC
    LIMIT 10';
$performanceStmt = $conn->prepare($performanceSql);
$performanceTypes = $whereTypes . 'i';
$performanceParams = array_merge($whereParams, [$window]);
$performanceStmt->bind_param($performanceTypes, ...$performanceParams);
$performanceStmt->execute();
$performanceRows = $performanceStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$interestSummarySql = 'SELECT
        COUNT(*) total_interests,
        SUM(CASE WHEN i.status = "pending" THEN 1 ELSE 0 END) pending_interests,
        SUM(CASE WHEN i.status = "assigned" THEN 1 ELSE 0 END) assigned_interests,
        SUM(CASE WHEN i.status = "completed" THEN 1 ELSE 0 END) completed_interests,
        SUM(CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END) worked_interests
    FROM interests i
    JOIN centers c ON i.center_id = c.id
    LEFT JOIN followups f ON f.interest_id = i.id
    WHERE ' . $whereSql;
$interestSummaryStmt = $conn->prepare($interestSummarySql);
if ($whereTypes !== '') {
    $interestSummaryStmt->bind_param($whereTypes, ...$whereParams);
}
$interestSummaryStmt->execute();
$interestSummary = $interestSummaryStmt->get_result()->fetch_assoc() ?: [];

$interestRowsSql = 'SELECT
        i.id,
        i.full_name,
        i.phone,
        i.request_type,
        i.additional_notes,
        i.status interest_status,
        i.created_at,
        c.name center_name,
        cf.name conference_name,
        u.name union_name,
        f.status followup_status,
        f.remarks followup_remarks,
        f.followup_date,
        fu.name worked_by
    FROM interests i
    JOIN centers c ON i.center_id = c.id
    JOIN conferences cf ON c.conference_id = cf.id
    JOIN unions u ON c.union_id = u.id
    LEFT JOIN followups f ON f.interest_id = i.id
    LEFT JOIN users fu ON fu.id = f.assigned_to
    WHERE ' . $whereSql . '
    ORDER BY i.created_at DESC
    LIMIT 20';
$interestRowsStmt = $conn->prepare($interestRowsSql);
if ($whereTypes !== '') {
    $interestRowsStmt->bind_param($whereTypes, ...$whereParams);
}
$interestRowsStmt->execute();
$interestRows = $interestRowsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unionOptions = [];
if ($role === 'tanzania_admin') {
    $unionOptions = $conn->query('SELECT id, name FROM unions ORDER BY name')->fetch_all(MYSQLI_ASSOC);
}

$conferenceScope = [];
$conferenceScopeTypes = '';
$conferenceScopeParams = [];
$addConfScope = static function (string $sql, string $type = '', $value = null) use (&$conferenceScope, &$conferenceScopeTypes, &$conferenceScopeParams): void {
    $conferenceScope[] = $sql;
    if ($type !== '') {
        $conferenceScopeTypes .= $type;
        $conferenceScopeParams[] = $value;
    }
};

if ($role === 'union_admin') {
    $addConfScope('union_id = ?', 'i', (int) $user['union_id']);
} elseif ($role === 'conference_admin') {
    $addConfScope('id = ?', 'i', (int) $user['conference_id']);
}
if ($role === 'tanzania_admin' && $unionFilter > 0) {
    $addConfScope('union_id = ?', 'i', $unionFilter);
}

$conferenceSql = 'SELECT id, union_id, name FROM conferences';
if ($conferenceScope) {
    $conferenceSql .= ' WHERE ' . implode(' AND ', $conferenceScope);
}
$conferenceSql .= ' ORDER BY name';
$conferenceStmt = $conn->prepare($conferenceSql);
if ($conferenceScopeTypes !== '') {
    $conferenceStmt->bind_param($conferenceScopeTypes, ...$conferenceScopeParams);
}
$conferenceStmt->execute();
$conferenceOptions = $conferenceStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$centerScope = [];
$centerScopeTypes = '';
$centerScopeParams = [];
$addCenterScope = static function (string $sql, string $type = '', $value = null) use (&$centerScope, &$centerScopeTypes, &$centerScopeParams): void {
    $centerScope[] = $sql;
    if ($type !== '') {
        $centerScopeTypes .= $type;
        $centerScopeParams[] = $value;
    }
};

if ($role === 'union_admin') {
    $addCenterScope('union_id = ?', 'i', (int) $user['union_id']);
} elseif ($role === 'conference_admin') {
    $addCenterScope('conference_id = ?', 'i', (int) $user['conference_id']);
}
if (($role === 'tanzania_admin' || $role === 'union_admin') && $conferenceFilter > 0) {
    $addCenterScope('conference_id = ?', 'i', $conferenceFilter);
}
if ($role === 'tanzania_admin' && $unionFilter > 0) {
    $addCenterScope('union_id = ?', 'i', $unionFilter);
}

$centerSql = 'SELECT id, name, conference_id, union_id FROM centers';
if ($centerScope) {
    $centerSql .= ' WHERE ' . implode(' AND ', $centerScope);
}
$centerSql .= ' ORDER BY name';
$centerStmt = $conn->prepare($centerSql);
if ($centerScopeTypes !== '') {
    $centerStmt->bind_param($centerScopeTypes, ...$centerScopeParams);
}
$centerStmt->execute();
$centerOptions = $centerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$scopeLabel = $role === 'tanzania_admin'
    ? 'Tanzania Wide View'
    : ($role === 'union_admin' ? 'Union View' : 'Conference View');
$totalInterests = (int) ($interestSummary['total_interests'] ?? 0);
$pendingInterests = (int) ($interestSummary['pending_interests'] ?? 0);
$assignedInterests = (int) ($interestSummary['assigned_interests'] ?? 0);
$completedInterests = (int) ($interestSummary['completed_interests'] ?? 0);
$workedInterests = (int) ($interestSummary['worked_interests'] ?? 0);
$completionRate = $totalInterests > 0 ? round(($completedInterests / $totalInterests) * 100, 1) : 0.0;
$workedRate = $totalInterests > 0 ? round(($workedInterests / $totalInterests) * 100, 1) : 0.0;

// --- Attendance by Category (stacked bar) ---
$categoryTrendSql = 'SELECT
        ' . $groupExpr . ' period_label,
        SUM(mr.elderly_men)        elderly_men,
        SUM(mr.elderly_women)      elderly_women,
        SUM(mr.children_boys)      children_boys,
        SUM(mr.children_girls)     children_girls,
        SUM(mr.members_attended)   members_attended
    FROM meeting_reports mr
    JOIN centers c ON mr.center_id = c.id
    WHERE ' . $whereSql . ' AND ' . $rangeSql . '
    GROUP BY period_label
    ORDER BY period_label ASC';
$catStmt = $conn->prepare($categoryTrendSql);
$catStmt->bind_param($trendTypes, ...$trendParams);
$catStmt->execute();
$catRows = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catLabels = $catElderlyMen = $catElderlyWomen = $catBoys = $catGirls = $catMembers = [];
foreach ($catRows as $r) {
    $catLabels[]       = (string) $r['period_label'];
    $catElderlyMen[]   = (int) $r['elderly_men'];
    $catElderlyWomen[] = (int) $r['elderly_women'];
    $catBoys[]         = (int) $r['children_boys'];
    $catGirls[]        = (int) $r['children_girls'];
    $catMembers[]      = (int) $r['members_attended'];
}

// --- Interest Request Types (horizontal bar) ---
$intTypesSql = 'SELECT i.request_type, COUNT(*) cnt
    FROM interests i
    JOIN centers c ON i.center_id = c.id
    WHERE ' . $whereSql . '
    GROUP BY i.request_type ORDER BY cnt DESC';
$intTypesStmt = $conn->prepare($intTypesSql);
if ($whereTypes !== '') {
    $intTypesStmt->bind_param($whereTypes, ...$whereParams);
}
$intTypesStmt->execute();
$intTypeLabels = [];
$intTypeValues = [];
foreach ($intTypesStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $intTypeLabels[] = ucwords(str_replace('_', ' ', (string) $r['request_type']));
    $intTypeValues[] = (int) $r['cnt'];
}

// --- Station Activity (active vs inactive) ---
$totalStationsSql = 'SELECT COUNT(DISTINCT c.id) cnt FROM centers c WHERE ' . $whereSql;
$tsStmt = $conn->prepare($totalStationsSql);
if ($whereTypes !== '') {
    $tsStmt->bind_param($whereTypes, ...$whereParams);
}
$tsStmt->execute();
$totalStationsCount = (int) ($tsStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

$activeStationsSql = 'SELECT COUNT(DISTINCT mr.center_id) cnt
    FROM meeting_reports mr
    JOIN centers c ON mr.center_id = c.id
    WHERE ' . $whereSql . ' AND ' . $rangeSql;
$asStmt = $conn->prepare($activeStationsSql);
$asStmt->bind_param($trendTypes, ...$trendParams);
$asStmt->execute();
$activeStationsCount = (int) ($asStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$inactiveStationsCount = max(0, $totalStationsCount - $activeStationsCount);

// Top stations for horizontal bar chart
$topStationNames = array_map(
    fn($r) => mb_strlen((string) $r['center_name']) > 26
        ? mb_substr((string) $r['center_name'], 0, 23) . '…'
        : (string) $r['center_name'],
    $performanceRows
);
$topStationTotals = array_map(fn($r) => (int) $r['attendance_total'], $performanceRows);

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">Mission Intelligence</p>
                <h3 class="mb-2">Welcome, <?= e((string) $user['name']) ?></h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e($scopeLabel) ?></span>
                    <span class="badge tc-badge-gold">Updated <?= e(date('M d, Y')) ?></span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/reports/index.php">Attendance Reports</a>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/users/index.php">Manage Users</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-midnight h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-file-document-multiple-outline"></i></div>
                <p class="tc-stat-label mb-1">Attendance Reports</p>
                <h3 class="mb-1 js-count" data-target="<?= (int) ($attendanceSummary['reports_count'] ?? 0) ?>">0</h3>
                <small>Last <?= $window ?> <?= e($attendanceView === 'daily' ? 'days' : 'weeks') ?></small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-account-group"></i></div>
                <p class="tc-stat-label mb-1">Total Attendance</p>
                <h3 class="mb-1 js-count" data-target="<?= (int) ($attendanceSummary['total_attendance'] ?? 0) ?>">0</h3>
                <small>People reached</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-paper h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-chart-line"></i></div>
                <p class="tc-stat-label mb-1">Average Attendance</p>
                <h3 class="mb-1"><?= number_format((float) ($attendanceSummary['avg_attendance'] ?? 0), 1) ?></h3>
                <small>Per submitted report</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-teal h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-account-check-outline"></i></div>
                <p class="tc-stat-label mb-1">Interest Requests</p>
                <h3 class="mb-1 js-count" data-target="<?= $totalInterests ?>">0</h3>
                <small>Worked: <?= $workedInterests ?> (<?= number_format($workedRate, 1) ?>%)</small>
            </div>
        </div>
    </div>
</div>

<div class="card tc-card tc-filter-card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Live Scope Filters</h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="true" aria-controls="filterPanel">
                Toggle Filters
            </button>
        </div>
        <div id="filterPanel" class="collapse show">
            <form method="get" class="row g-2 align-items-end tc-report-filters">
                <?php if ($role === 'tanzania_admin'): ?>
                <div class="col-md-2">
                    <label class="form-label">Union</label>
                    <select class="form-select" name="union_id" id="unionFilter">
                        <option value="0">All</option>
                        <?php foreach ($unionOptions as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= $unionFilter === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if ($role === 'tanzania_admin' || $role === 'union_admin'): ?>
                <div class="col-md-3">
                    <label class="form-label">Conference / Field</label>
                    <select class="form-select" name="conference_id" id="conferenceFilter">
                        <option value="0">All</option>
                        <?php foreach ($conferenceOptions as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>" <?= $conferenceFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">Church / Station</label>
                    <select class="form-select" name="center_id" id="centerFilter">
                        <option value="0">All</option>
                        <?php foreach ($centerOptions as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>" data-conference="<?= (int) $c['conference_id'] ?>" <?= $centerFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search Church</label>
                    <input type="text" class="form-control" name="center_search" value="<?= e($centerSearch) ?>" placeholder="Type church name">
                </div>
                <div class="col-md-1">
                    <label class="form-label">View</label>
                    <select class="form-select" name="attendance_view">
                        <option value="weekly" <?= $attendanceView === 'weekly' ? 'selected' : '' ?>>Week</option>
                        <option value="daily" <?= $attendanceView === 'daily' ? 'selected' : '' ?>>Day</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Window</label>
                    <input type="number" class="form-control" name="window" min="1" max="<?= $windowMax ?>" value="<?= $window ?>">
                </div>
                <div class="col-md-12 d-grid d-md-flex gap-2 mt-2">
                    <button class="btn btn-primary">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="/Tumaini-Connect/admin/dashboard.php">Reset</a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card tc-card h-100">
            <div class="card-body">
                <h5 class="mb-1">Attendance Trend</h5>
                <p class="text-muted small mb-3">Interactive view of attendance over selected time window.</p>
                <canvas id="attendanceTrend" height="115"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card tc-card h-100">
            <div class="card-body">
                <h5 class="mb-1">Interest Progress</h5>
                <p class="text-muted small mb-2">Completion and work tracking snapshot.</p>
                <canvas id="interestMix" height="180"></canvas>
                <div class="tc-meter mt-3">
                    <div class="d-flex justify-content-between small"><span>Completion Rate</span><strong><?= number_format($completionRate, 1) ?>%</strong></div>
                    <div class="progress"><div class="progress-bar bg-success" role="progressbar" style="width: <?= $completionRate ?>%;"></div></div>
                </div>
                <div class="tc-meter mt-2">
                    <div class="d-flex justify-content-between small"><span>Worked Coverage</span><strong><?= number_format($workedRate, 1) ?>%</strong></div>
                    <div class="progress"><div class="progress-bar bg-info" role="progressbar" style="width: <?= $workedRate ?>%;"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NEW: Attendance by Category + Interest Types -->
<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card tc-card h-100">
            <div class="card-body">
                <h5 class="mb-1">Attendance Breakdown by Category</h5>
                <p class="text-muted small mb-3">Who is being reached — elderly, children, and members per period.</p>
                <canvas id="categoryBreakdown" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card tc-card h-100">
            <div class="card-body">
                <h5 class="mb-1">Interest Request Types</h5>
                <p class="text-muted small mb-3">Distribution of what people are requesting.</p>
                <canvas id="interestTypes" height="210"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card tc-card h-100">
            <div class="card-body">
                <h5 class="mb-3">Top Performing Churches</h5>
                <div class="tc-ranking-list">
                    <?php foreach ($performanceRows as $index => $row): ?>
                        <div class="tc-rank-item">
                            <div class="tc-rank-left">
                                <span class="tc-rank-num"><?= (int) ($index + 1) ?></span>
                                <div>
                                    <div class="fw-semibold"><?= e($row['center_name']) ?></div>
                                    <small class="text-muted">Reports: <?= (int) $row['reports_count'] ?>, Total: <?= (int) $row['attendance_total'] ?></small>
                                </div>
                            </div>
                            <span class="badge bg-dark"><?= e((string) $row['avg_attendance']) ?> avg</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$performanceRows): ?>
                        <p class="text-muted mb-0">No attendance reports found for selected filters.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card tc-card h-100">
            <div class="card-body">
                <h5 class="mb-3">Interest Status Breakdown</h5>
                <div class="row g-2 mb-3">
                    <div class="col-sm-3"><div class="tc-mini-stat"><span>Pending</span><strong><?= $pendingInterests ?></strong></div></div>
                    <div class="col-sm-3"><div class="tc-mini-stat"><span>Assigned</span><strong><?= $assignedInterests ?></strong></div></div>
                    <div class="col-sm-3"><div class="tc-mini-stat"><span>Completed</span><strong><?= $completedInterests ?></strong></div></div>
                    <div class="col-sm-3"><div class="tc-mini-stat"><span>Active Stations</span><strong><?= $activeStationsCount ?>/<?= $totalStationsCount ?></strong></div></div>
                </div>
                <h6 class="text-muted small mb-2" style="font-weight:700;letter-spacing:.5px;text-transform:uppercase;">Station Activity (<?= $window ?> <?= $attendanceView === 'daily' ? 'days' : 'weeks' ?>)</h6>
                <canvas id="stationActivity" height="65"></canvas>
                <p class="text-muted small mt-3 mb-0">Active = stations that submitted at least one report in the selected window.</p>
            </div>
        </div>
    </div>
</div>

<div class="card tc-card">
    <div class="card-body">
        <h5 class="mb-3">Recent Interest Requests</h5>
        <div class="table-responsive">
            <table class="table datatable tc-interest-table">
                <thead>
                <tr>
                    <th>Person</th>
                    <th>Request</th>
                    <th>Church Scope</th>
                    <th>Contact</th>
                    <th>Worked Status</th>
                    <th>Work Details</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($interestRows as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($row['full_name']) ?></div>
                            <small class="text-muted"><?= e((string) $row['created_at']) ?></small>
                        </td>
                        <td>
                            <?= e(ucwords(str_replace('_', ' ', (string) $row['request_type']))) ?><br>
                            <small class="text-muted"><?= e((string) $row['additional_notes']) ?></small>
                        </td>
                        <td>
                            <?= e($row['center_name']) ?><br>
                            <small class="text-muted"><?= e($row['conference_name']) ?> / <?= e($row['union_name']) ?></small>
                        </td>
                        <td>
                            <?= e($row['phone']) ?><br>
                            <div class="d-flex gap-1 flex-wrap mt-1">
                                <a href="<?= e(wa_link((string) $row['phone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">WhatsApp</a>
                                <a href="sms:<?= e((string) $row['phone']) ?>" class="btn btn-sm btn-outline-primary">SMS</a>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($row['followup_status'])): ?>
                                <span class="badge bg-success"><?= e((string) $row['followup_status']) ?></span><br>
                                <small class="text-muted">By: <?= e((string) ($row['worked_by'] ?? 'N/A')) ?></small>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Worked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="d-grid gap-1 tc-work-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="mark_interest_worked">
                                <input type="hidden" name="interest_id" value="<?= (int) $row['id'] ?>">
                                <select class="form-select form-select-sm" name="work_status">
                                    <?php $currentWork = (string) ($row['followup_status'] ?: 'Pending'); ?>
                                    <option value="Pending" <?= $currentWork === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Contacted" <?= $currentWork === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                                    <option value="Completed" <?= $currentWork === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                                <input type="date" class="form-control form-control-sm" name="followup_date" value="<?= e((string) $row['followup_date']) ?>">
                                <textarea class="form-control form-control-sm" name="work_notes" rows="2" placeholder="Work details"><?= e((string) $row['followup_remarks']) ?></textarea>
                                <button class="btn btn-sm btn-outline-dark">Save</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('attendanceTrend').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels, JSON_UNESCAPED_SLASHES) ?>,
        datasets: [{
            label: 'Attendance',
            data: <?= json_encode($trendValues, JSON_UNESCAPED_SLASHES) ?>,
            borderColor: '#f9bf3f',
            backgroundColor: 'rgba(249, 191, 63, 0.17)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('interestMix').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Assigned', 'Completed'],
        datasets: [{
            data: [<?= $pendingInterests ?>, <?= $assignedInterests ?>, <?= $completedInterests ?>],
            backgroundColor: ['#f39c12', '#3498db', '#2ecc71'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        cutout: '68%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

(() => {
    const unionSelect = document.getElementById('unionFilter');
    const confSelect = document.getElementById('conferenceFilter');
    const centerSelect = document.getElementById('centerFilter');
    if (!centerSelect) return;

    const confOptions = confSelect
        ? Array.from(confSelect.options).map((o) => ({
            value: o.value,
            label: o.textContent,
            union: o.getAttribute('data-union') || ''
        }))
        : [];

    const centerOptions = Array.from(centerSelect.options).map((o) => ({
        value: o.value,
        label: o.textContent,
        conference: o.getAttribute('data-conference') || '',
        union: o.getAttribute('data-union') || ''
    }));

    const renderConferences = () => {
        if (!unionSelect || !confSelect) return;
        const selectedUnion = unionSelect.value;
        const selectedConf = confSelect.value;
        confSelect.innerHTML = '<option value="0">All</option>';

        confOptions.forEach((opt) => {
            if (opt.value === '0') return;
            if (selectedUnion !== '0' && opt.union !== selectedUnion) return;
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            option.setAttribute('data-union', opt.union);
            if (opt.value === selectedConf) option.selected = true;
            confSelect.appendChild(option);
        });
    };

    const renderCenters = () => {
        const selectedUnion = unionSelect ? unionSelect.value : '0';
        const selectedConf = confSelect ? confSelect.value : '0';
        const selectedCenter = centerSelect.value;

        centerSelect.innerHTML = '<option value="0">All</option>';
        centerOptions.forEach((opt) => {
            if (opt.value === '0') return;
            if (selectedUnion !== '0' && opt.union !== selectedUnion) return;
            if (selectedConf !== '0' && opt.conference !== selectedConf) return;
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            option.setAttribute('data-union', opt.union);
            option.setAttribute('data-conference', opt.conference);
            if (opt.value === selectedCenter) option.selected = true;
            centerSelect.appendChild(option);
        });
    };

    if (unionSelect) {
        unionSelect.addEventListener('change', () => {
            renderConferences();
            renderCenters();
        });
    }

    if (confSelect) {
        confSelect.addEventListener('change', renderCenters);
    }

    document.querySelectorAll('.js-count').forEach((el) => {
        const target = Number(el.getAttribute('data-target') || 0);
        const duration = 700;
        const start = performance.now();
        const run = (t) => {
            const progress = Math.min((t - start) / duration, 1);
            el.textContent = Math.round(target * progress).toLocaleString();
            if (progress < 1) requestAnimationFrame(run);
        };
        requestAnimationFrame(run);
    });
})();

// --- Attendance by Category (stacked bar) ---
new Chart(document.getElementById('categoryBreakdown').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($catLabels, JSON_UNESCAPED_SLASHES) ?>,
        datasets: [
            { label: 'Elderly Men',   data: <?= json_encode($catElderlyMen) ?>,   backgroundColor: '#3d5a80' },
            { label: 'Elderly Women', data: <?= json_encode($catElderlyWomen) ?>,  backgroundColor: '#e76f51' },
            { label: 'Boys',          data: <?= json_encode($catBoys) ?>,          backgroundColor: '#2a9d8f' },
            { label: 'Girls',         data: <?= json_encode($catGirls) ?>,         backgroundColor: '#e9c46a' },
            { label: 'Members',       data: <?= json_encode($catMembers) ?>,       backgroundColor: '#264653' }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } } },
        scales: {
            x: { stacked: true, grid: { display: false } },
            y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

// --- Interest Request Types (horizontal bar) ---
new Chart(document.getElementById('interestTypes').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($intTypeLabels, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Requests',
            data: <?= json_encode($intTypeValues) ?>,
            backgroundColor: ['#f9bf3f','#e76f51','#2a9d8f','#3d5a80','#264653','#e9c46a'],
            borderWidth: 0,
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { grid: { display: false } }
        }
    }
});

// --- Station Activity (active vs inactive) ---
new Chart(document.getElementById('stationActivity').getContext('2d'), {
    type: 'bar',
    data: {
        labels: ['Active', 'Inactive'],
        datasets: [{
            data: [<?= $activeStationsCount ?>, <?= $inactiveStationsCount ?>],
            backgroundColor: ['#2a9d8f', '#e5e3dc'],
            borderWidth: 0,
            borderRadius: 4
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false }, tooltip: {
            callbacks: { label: ctx => ' ' + ctx.raw + ' station' + (ctx.raw !== 1 ? 's' : '') }
        }},
        scales: {
            x: { beginAtZero: true, max: <?= max(1, $totalStationsCount) ?>, ticks: { precision: 0 } },
            y: { grid: { display: false } }
        }
    }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
