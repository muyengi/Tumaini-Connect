<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['tanzania_admin', 'union_admin', 'conference_admin']);

$user = current_user();
$role = (string) ($user['role'] ?? '');

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

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-30 days'));

$fromDate = (string) ($_GET['from_date'] ?? $defaultFrom);
$toDate = (string) ($_GET['to_date'] ?? $today);
$groupBy = ($_GET['group_by'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';

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
$whereTypes = 'ss';
$whereParams = [$fromDate, $toDate];

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

$whereSql = implode(' AND ', $conditions);

$summarySql = 'SELECT
        COUNT(*) reports_count,
        COALESCE(SUM(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended), 0) total_attendance,
        COALESCE(AVG(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended), 0) avg_attendance
    FROM meeting_reports mr
    JOIN centers c ON c.id = mr.center_id
    WHERE ' . $whereSql;
$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->bind_param($whereTypes, ...$whereParams);
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc() ?: [];

$periodExpr = $groupBy === 'daily'
    ? 'DATE_FORMAT(mr.report_date, "%Y-%m-%d")'
    : 'DATE_FORMAT(DATE_SUB(mr.report_date, INTERVAL WEEKDAY(mr.report_date) DAY), "%Y-%m-%d")';

$trendSql = 'SELECT
        ' . $periodExpr . ' period_label,
        SUM(mr.elderly_men + mr.elderly_women + mr.children_boys + mr.children_girls + mr.members_attended) total_attendance
    FROM meeting_reports mr
    JOIN centers c ON c.id = mr.center_id
    WHERE ' . $whereSql . '
    GROUP BY period_label
    ORDER BY period_label ASC';
$trendStmt = $conn->prepare($trendSql);
$trendStmt->bind_param($whereTypes, ...$whereParams);
$trendStmt->execute();
$trendRows = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$trendLabels = [];
$trendValues = [];
foreach ($trendRows as $row) {
    $trendLabels[] = (string) $row['period_label'];
    $trendValues[] = (int) $row['total_attendance'];
}

$detailSql = 'SELECT
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
    LIMIT 1000';
$detailStmt = $conn->prepare($detailSql);
$detailStmt->bind_param($whereTypes, ...$whereParams);
$detailStmt->execute();
$rows = $detailStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$filteredTotals = [
    'elderly_men' => 0,
    'elderly_women' => 0,
    'children_boys' => 0,
    'children_girls' => 0,
    'members_attended' => 0,
    'total_attendance' => 0,
    'reports_count' => count($rows),
    'stations_count' => 0,
];
$filteredStations = [];
foreach ($rows as $r) {
    $filteredTotals['elderly_men'] += (int) $r['elderly_men'];
    $filteredTotals['elderly_women'] += (int) $r['elderly_women'];
    $filteredTotals['children_boys'] += (int) $r['children_boys'];
    $filteredTotals['children_girls'] += (int) $r['children_girls'];
    $filteredTotals['members_attended'] += (int) $r['members_attended'];
    $filteredTotals['total_attendance'] += (int) $r['total_attendance'];
    $filteredStations[(string) $r['center_name']] = true;
}
$filteredTotals['stations_count'] = count($filteredStations);

$scopeForConfs = [];
$scopeConfTypes = '';
$scopeConfParams = [];
$addConf = static function (string $sql, string $type = '', $value = null) use (&$scopeForConfs, &$scopeConfTypes, &$scopeConfParams): void {
    $scopeForConfs[] = $sql;
    if ($type !== '') {
        $scopeConfTypes .= $type;
        $scopeConfParams[] = $value;
    }
};

if ($role === 'union_admin') {
    $addConf('union_id = ?', 'i', (int) $user['union_id']);
} elseif ($role === 'conference_admin') {
    $addConf('id = ?', 'i', (int) $user['conference_id']);
}
if ($role === 'tanzania_admin' && $unionFilter > 0) {
    $addConf('union_id = ?', 'i', $unionFilter);
}

$confSql = 'SELECT id, union_id, name FROM conferences';
if ($scopeForConfs) {
    $confSql .= ' WHERE ' . implode(' AND ', $scopeForConfs);
}
$confSql .= ' ORDER BY name';
$confStmt = $conn->prepare($confSql);
if ($scopeConfTypes !== '') {
    $confStmt->bind_param($scopeConfTypes, ...$scopeConfParams);
}
$confStmt->execute();
$conferences = $confStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$scopeForCenters = [];
$scopeCenterTypes = '';
$scopeCenterParams = [];
$addCenter = static function (string $sql, string $type = '', $value = null) use (&$scopeForCenters, &$scopeCenterTypes, &$scopeCenterParams): void {
    $scopeForCenters[] = $sql;
    if ($type !== '') {
        $scopeCenterTypes .= $type;
        $scopeCenterParams[] = $value;
    }
};

if ($role === 'union_admin') {
    $addCenter('union_id = ?', 'i', (int) $user['union_id']);
} elseif ($role === 'conference_admin') {
    $addCenter('conference_id = ?', 'i', (int) $user['conference_id']);
}
if ($role === 'tanzania_admin' && $unionFilter > 0) {
    $addCenter('union_id = ?', 'i', $unionFilter);
}
if (($role === 'tanzania_admin' || $role === 'union_admin') && $conferenceFilter > 0) {
    $addCenter('conference_id = ?', 'i', $conferenceFilter);
}

$centerSql = 'SELECT id, name, union_id, conference_id FROM centers';
if ($scopeForCenters) {
    $centerSql .= ' WHERE ' . implode(' AND ', $scopeForCenters);
}
$centerSql .= ' ORDER BY name';
$centerStmt = $conn->prepare($centerSql);
if ($scopeCenterTypes !== '') {
    $centerStmt->bind_param($scopeCenterTypes, ...$scopeCenterParams);
}
$centerStmt->execute();
$centers = $centerStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unions = [];
if ($role === 'tanzania_admin') {
    $unions = $conn->query('SELECT id, name FROM unions ORDER BY name')->fetch_all(MYSQLI_ASSOC);
}

$queryWithoutAction = $_GET;
$scopeLabel = $role === 'tanzania_admin'
    ? 'Tanzania Wide View'
    : ($role === 'union_admin' ? 'Union View' : 'Conference View');

$pageTitle = 'Attendance Reports';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">Mission Analytics</p>
                <h3 class="mb-2">Attendance Reports</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e($scopeLabel) ?></span>
                    <span class="badge tc-badge-gold"><?= e($fromDate) ?> to <?= e($toDate) ?></span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/admin/dashboard.php">Dashboard</a>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/reports/export_pdf.php?<?= e(http_build_query($queryWithoutAction)) ?>" target="_blank">Quick PDF</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card tc-kpi-card tc-kpi-midnight h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-file-chart"></i></div>
            <p class="tc-stat-label mb-1">Reports</p>
            <h3 class="mb-1 js-count" data-target="<?= (int) ($summary['reports_count'] ?? 0) ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-account-group"></i></div>
            <p class="tc-stat-label mb-1">Total Attendance</p>
            <h3 class="mb-1 js-count" data-target="<?= (int) ($summary['total_attendance'] ?? 0) ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-12 col-xl-4">
        <div class="card tc-kpi-card tc-kpi-paper h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-chart-areaspline"></i></div>
            <p class="tc-stat-label mb-1">Average per Report</p>
            <h3 class="mb-1"><?= number_format((float) ($summary['avg_attendance'] ?? 0), 1) ?></h3>
        </div></div>
    </div>
</div>

<div class="card tc-card tc-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end tc-report-filters">
            <?php if ($role === 'tanzania_admin'): ?>
            <div class="col-md-2">
                <label class="form-label">Union</label>
                <select class="form-select" name="union_id" id="unionFilter">
                    <option value="0">All</option>
                    <?php foreach ($unions as $u): ?>
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
                    <?php foreach ($conferences as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>" <?= $conferenceFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label class="form-label">Church / Station</label>
                <select class="form-select" name="center_id" id="centerFilter">
                    <option value="0">All</option>
                    <?php foreach ($centers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>" data-conference="<?= (int) $c['conference_id'] ?>" <?= $centerFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Search Church</label>
                <input type="text" class="form-control" name="center_search" value="<?= e($centerSearch) ?>" placeholder="Type church name">
            </div>
            <div class="col-md-2">
                <label class="form-label">Group</label>
                <select class="form-select" name="group_by">
                    <option value="weekly" <?= $groupBy === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                    <option value="daily" <?= $groupBy === 'daily' ? 'selected' : '' ?>>Daily</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" class="form-control" name="from_date" value="<?= e($fromDate) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" class="form-control" name="to_date" value="<?= e($toDate) ?>">
            </div>
            <div class="col-md-12 d-grid d-md-flex gap-2 mt-2">
                <button class="btn btn-primary">Apply Filters</button>
                <a class="btn btn-outline-secondary" href="/Tumaini-Connect/modules/reports/index.php">Reset</a>
                <a class="btn btn-outline-success" href="/Tumaini-Connect/modules/reports/export_csv.php?<?= e(http_build_query($queryWithoutAction)) ?>">Export CSV</a>
                <a class="btn btn-outline-dark" href="/Tumaini-Connect/modules/reports/export_pdf.php?<?= e(http_build_query($queryWithoutAction)) ?>" target="_blank">Export PDF</a>
            </div>
        </form>
    </div>
</div>

<div class="card tc-card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0">Filter Summary Totals</h5>
            <span class="badge tc-badge-dark">Reports: <?= (int) $filteredTotals['reports_count'] ?> &middot; Stations: <?= (int) $filteredTotals['stations_count'] ?></span>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-4 col-xl-2"><div class="tc-mini-stat"><span>Elderly Men</span><strong><?= number_format((int) $filteredTotals['elderly_men']) ?></strong></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="tc-mini-stat"><span>Elderly Women</span><strong><?= number_format((int) $filteredTotals['elderly_women']) ?></strong></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="tc-mini-stat"><span>Children Boys</span><strong><?= number_format((int) $filteredTotals['children_boys']) ?></strong></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="tc-mini-stat"><span>Children Girls</span><strong><?= number_format((int) $filteredTotals['children_girls']) ?></strong></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="tc-mini-stat"><span>Members</span><strong><?= number_format((int) $filteredTotals['members_attended']) ?></strong></div></div>
            <div class="col-6 col-md-4 col-xl-2"><div class="tc-mini-stat"><span>Total Attendance</span><strong><?= number_format((int) $filteredTotals['total_attendance']) ?></strong></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card tc-card">
            <div class="card-body">
                <h5 class="mb-3">Attendance Trend</h5>
                <canvas id="attendanceTrend" height="95"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card tc-card">
    <div class="card-body">
        <h5 class="mb-3">Attendance Detail</h5>
        <div class="table-responsive">
            <table class="table datatable tc-interest-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Union</th>
                    <th>Conference</th>
                    <th>Church / Station</th>
                    <th>Elderly Men</th>
                    <th>Elderly Women</th>
                    <th>Children Boys</th>
                    <th>Children Girls</th>
                    <th>Members</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e((string) $r['report_date']) ?></td>
                        <td><?= e((string) $r['union_name']) ?></td>
                        <td><?= e((string) $r['conference_name']) ?></td>
                        <td><?= e((string) $r['center_name']) ?></td>
                        <td><?= (int) $r['elderly_men'] ?></td>
                        <td><?= (int) $r['elderly_women'] ?></td>
                        <td><?= (int) $r['children_boys'] ?></td>
                        <td><?= (int) $r['children_girls'] ?></td>
                        <td><?= (int) $r['members_attended'] ?></td>
                        <td><strong><?= (int) $r['total_attendance'] ?></strong></td>
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
            backgroundColor: 'rgba(249, 191, 63, 0.16)',
            borderColor: '#e8a020',
            borderWidth: 2,
            fill: true,
            tension: 0.33,
            pointRadius: 2.8,
            pointHoverRadius: 4.5
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 } },
            x: { grid: { display: false } }
        }
    }
});

document.querySelectorAll('.js-count').forEach((el) => {
    const target = Number(el.getAttribute('data-target') || 0);
    const duration = 650;
    const start = performance.now();
    const run = (t) => {
        const progress = Math.min((t - start) / duration, 1);
        el.textContent = Math.round(target * progress).toLocaleString();
        if (progress < 1) requestAnimationFrame(run);
    };
    requestAnimationFrame(run);
});

(() => {
    const union = document.getElementById('unionFilter');
    const conf = document.getElementById('conferenceFilter');
    const center = document.getElementById('centerFilter');

    if (!center) {
        return;
    }

    const confOptions = conf
        ? Array.from(conf.options).map((o) => ({
            value: o.value,
            text: o.text,
            union: o.getAttribute('data-union') || ''
        }))
        : [];

    const centerOptions = Array.from(center.options).map((o) => ({
        value: o.value,
        text: o.text,
        union: o.getAttribute('data-union') || '',
        conference: o.getAttribute('data-conference') || ''
    }));

    const renderConferences = () => {
        if (!union || !conf) {
            return;
        }

        const selectedUnion = union.value;
        const current = conf.value;
        conf.innerHTML = '<option value="0">All</option>';

        confOptions.forEach((o) => {
            if (o.value === '0') {
                return;
            }
            if (selectedUnion !== '0' && o.union !== selectedUnion) {
                return;
            }
            const op = document.createElement('option');
            op.value = o.value;
            op.textContent = o.text;
            op.setAttribute('data-union', o.union);
            if (o.value === current) {
                op.selected = true;
            }
            conf.appendChild(op);
        });
    };

    const renderCenters = () => {
        const selectedUnion = union ? union.value : '0';
        const selectedConf = conf ? conf.value : '0';
        const current = center.value;
        center.innerHTML = '<option value="0">All</option>';

        centerOptions.forEach((o) => {
            if (o.value === '0') {
                return;
            }
            if (selectedUnion !== '0' && o.union !== selectedUnion) {
                return;
            }
            if (selectedConf !== '0' && o.conference !== selectedConf) {
                return;
            }
            const op = document.createElement('option');
            op.value = o.value;
            op.textContent = o.text;
            op.setAttribute('data-union', o.union);
            op.setAttribute('data-conference', o.conference);
            if (o.value === current) {
                op.selected = true;
            }
            center.appendChild(op);
        });
    };

    if (union) {
        union.addEventListener('change', () => {
            renderConferences();
            renderCenters();
        });
    }
    if (conf) {
        conf.addEventListener('change', renderCenters);
    }
})();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
