<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['tanzania_admin', 'union_admin', 'conference_admin']);

$user = current_user();
$role = $user['role'];

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $unionId = (int) ($_POST['union_id'] ?? 0);
        $conferenceId = (int) ($_POST['conference_id'] ?? 0);

        if ($role === 'union_admin') {
            $unionId = (int) $user['union_id'];
        }
        if ($role === 'conference_admin') {
            $conferenceId = (int) $user['conference_id'];
        }

        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? 'Church');
        $region = trim($_POST['region'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $ward = trim($_POST['ward'] ?? '');
        $coordinatorName = trim($_POST['coordinator_name'] ?? '');
        $coordinatorPhone = normalize_phone($_POST['coordinator_phone'] ?? '');
        $coordinatorEmail = trim($_POST['coordinator_email'] ?? '');
        $meetingTime = trim($_POST['meeting_time'] ?? '');

        if ($coordinatorPhone === '') {
            set_flash('danger', 'Coordinator phone is required to create station account login.');
            header('Location: index.php');
            exit;
        }

        $stmt = $conn->prepare('INSERT INTO centers (union_id, conference_id, name, type, region, district, ward, coordinator_name, coordinator_phone, coordinator_email, meeting_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iisssssssss', $unionId, $conferenceId, $name, $type, $region, $district, $ward, $coordinatorName, $coordinatorPhone, $coordinatorEmail, $meetingTime);
        if ($stmt->execute()) {
            $centerId = (int) $stmt->insert_id;
            $accountEmail = 'station' . $centerId . '@tumaini.local';
            $accountPasswordHash = password_hash($coordinatorPhone, PASSWORD_DEFAULT);

            $userStmt = $conn->prepare('INSERT INTO users (name, email, password, role, union_id, conference_id) VALUES (?, ?, ?, "coordinator", ?, ?)');
            $userStmt->bind_param('sssii', $coordinatorName, $accountEmail, $accountPasswordHash, $unionId, $conferenceId);
            $userStmt->execute();

            set_flash('success', 'Station created successfully. Station number: ' . $centerId . '.');
        } else {
            set_flash('danger', 'Failed to create station.');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($role === 'tanzania_admin') {
            $stmt = $conn->prepare('DELETE FROM centers WHERE id = ?');
            $stmt->bind_param('i', $id);
        } elseif ($role === 'union_admin') {
            $stmt = $conn->prepare('DELETE FROM centers WHERE id = ? AND union_id = ?');
            $stmt->bind_param('ii', $id, $user['union_id']);
        } else {
            $stmt = $conn->prepare('DELETE FROM centers WHERE id = ? AND conference_id = ?');
            $stmt->bind_param('ii', $id, $user['conference_id']);
        }
        $stmt->execute();
        set_flash('warning', 'Station deleted.');
    }

    header('Location: index.php');
    exit;
}

if ($role === 'tanzania_admin') {
    $unions = $conn->query('SELECT id, name FROM unions ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $conferences = $conn->query('SELECT id, union_id, name FROM conferences ORDER BY name')->fetch_all(MYSQLI_ASSOC);
    $listSql = 'SELECT c.*, u.name union_name, cf.name conference_name FROM centers c JOIN unions u ON c.union_id = u.id JOIN conferences cf ON c.conference_id = cf.id ORDER BY c.id DESC';
} elseif ($role === 'union_admin') {
    $stmtUnion = $conn->prepare('SELECT id, name FROM unions WHERE id = ?');
    $stmtUnion->bind_param('i', $user['union_id']);
    $stmtUnion->execute();
    $unions = $stmtUnion->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtConf = $conn->prepare('SELECT id, union_id, name FROM conferences WHERE union_id = ? ORDER BY name');
    $stmtConf->bind_param('i', $user['union_id']);
    $stmtConf->execute();
    $conferences = $stmtConf->get_result()->fetch_all(MYSQLI_ASSOC);

    $listSql = 'SELECT c.*, u.name union_name, cf.name conference_name FROM centers c JOIN unions u ON c.union_id = u.id JOIN conferences cf ON c.conference_id = cf.id WHERE c.union_id = ' . (int) $user['union_id'] . ' ORDER BY c.id DESC';
} else {
    $stmtConfOne = $conn->prepare('SELECT id, union_id, name FROM conferences WHERE id = ?');
    $stmtConfOne->bind_param('i', $user['conference_id']);
    $stmtConfOne->execute();
    $conf = $stmtConfOne->get_result()->fetch_assoc();

    $stmtUnion = $conn->prepare('SELECT id, name FROM unions WHERE id = ?');
    $stmtUnion->bind_param('i', $conf['union_id']);
    $stmtUnion->execute();
    $unions = $stmtUnion->get_result()->fetch_all(MYSQLI_ASSOC);
    $conferences = [$conf];

    $listSql = 'SELECT c.*, u.name union_name, cf.name conference_name FROM centers c JOIN unions u ON c.union_id = u.id JOIN conferences cf ON c.conference_id = cf.id WHERE c.conference_id = ' . (int) $user['conference_id'] . ' ORDER BY c.id DESC';
}

$centers = $conn->query($listSql)->fetch_all(MYSQLI_ASSOC);
$totalStations = count($centers);
$withWhatsApp = 0;
$uniqueRegions = [];
$churchCount = 0;
foreach ($centers as $center) {
    if (!empty($center['coordinator_phone'])) {
        $withWhatsApp++;
    }
    if (!empty($center['region'])) {
        $uniqueRegions[(string) $center['region']] = true;
    }
    if (strtolower((string) ($center['type'] ?? '')) === 'church') {
        $churchCount++;
    }
}
$scopeLabel = $role === 'tanzania_admin'
    ? 'Tanzania Wide View'
    : ($role === 'union_admin' ? 'Union View' : 'Conference View');
$regionCount = count($uniqueRegions);
$pageTitle = 'Station Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">Field Operations</p>
                <h3 class="mb-2">Station Management</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e($scopeLabel) ?></span>
                    <span class="badge tc-badge-gold">Coverage & Coordination</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createCenterModal">Add Station</button>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/admin/dashboard.php">Dashboard</a>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/reports/index.php">Reports</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-midnight h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-home-city"></i></div>
            <p class="tc-stat-label mb-1">Stations</p>
            <h3 class="mb-1 js-count" data-target="<?= $totalStations ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-teal h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-map-marker"></i></div>
            <p class="tc-stat-label mb-1">Regions Reached</p>
            <h3 class="mb-1 js-count" data-target="<?= $regionCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-paper h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-whatsapp"></i></div>
            <p class="tc-stat-label mb-1">WhatsApp Ready</p>
            <h3 class="mb-1 js-count" data-target="<?= $withWhatsApp ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-church"></i></div>
            <p class="tc-stat-label mb-1">Church Type</p>
            <h3 class="mb-1 js-count" data-target="<?= $churchCount ?>">0</h3>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card tc-card"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Stations</h5>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createCenterModal">Add Station</button>
            </div>
            <div class="table-responsive">
                <table class="table datatable">
                    <thead><tr><th>Name</th><th>Union</th><th>Conference</th><th>Location</th><th>Coordinator</th><th>Contact</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($centers as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?><br><small class="text-muted"><?= e($row['type']) ?></small></td>
                            <td><?= e($row['union_name']) ?></td>
                            <td><?= e($row['conference_name']) ?></td>
                            <td><?= e($row['region']) ?> / <?= e($row['district']) ?> / <?= e($row['ward']) ?></td>
                            <td><?= e($row['coordinator_name']) ?></td>
                            <td>
                                <?php if (!empty($row['coordinator_phone'])): ?>
                                    <a href="<?= e(wa_link($row['coordinator_phone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success">WhatsApp</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete station?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<div class="modal fade" id="createCenterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Register Station</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Union</label>
                            <select class="form-select" name="union_id" id="centerUnion" <?= $role !== 'tanzania_admin' ? 'disabled' : '' ?> required>
                                <?php foreach ($unions as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($role !== 'tanzania_admin' && isset($unions[0])): ?>
                                <input type="hidden" name="union_id" value="<?= (int) $unions[0]['id'] ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6"><label class="form-label">Conference / Field</label>
                            <select class="form-select" name="conference_id" id="centerConference" <?= $role === 'conference_admin' ? 'disabled' : '' ?> required>
                                <?php foreach ($conferences as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($role === 'conference_admin' && isset($conferences[0])): ?>
                                <input type="hidden" name="conference_id" value="<?= (int) $conferences[0]['id'] ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6"><label class="form-label">Station Name</label><input class="form-control" name="name" required></div>
                        <div class="col-md-6"><label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option>Church</option><option>Home</option><option>School</option><option>Group</option><option>Institution</option>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Region</label><input class="form-control" name="region" required></div>
                        <div class="col-md-4"><label class="form-label">District</label><input class="form-control" name="district" required></div>
                        <div class="col-md-4"><label class="form-label">Ward</label><input class="form-control" name="ward" required></div>
                        <div class="col-md-6"><label class="form-label">Coordinator Name</label><input class="form-control" name="coordinator_name" required></div>
                        <div class="col-md-6"><label class="form-label">Coordinator Phone</label><input class="form-control" name="coordinator_phone" required></div>
                        <div class="col-md-6"><label class="form-label">Coordinator Email</label><input type="email" class="form-control" name="coordinator_email"></div>
                        <div class="col-md-6"><label class="form-label">Meeting Time</label><input class="form-control" name="meeting_time" placeholder="e.g. Saturday 15:00"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save Station</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const unionSelect = document.getElementById('centerUnion');
    const confSelect = document.getElementById('centerConference');
    if (!unionSelect || !confSelect) return;

    const options = Array.from(confSelect.options).map(opt => ({
        value: opt.value,
        text: opt.text,
        union: opt.getAttribute('data-union')
    }));

    function refreshConferences() {
        const selectedUnion = unionSelect.value;
        confSelect.innerHTML = '';
        options.filter(o => o.union === selectedUnion).forEach(o => {
            const option = document.createElement('option');
            option.value = o.value;
            option.textContent = o.text;
            confSelect.appendChild(option);
        });
    }

    unionSelect.addEventListener('change', refreshConferences);
    refreshConferences();
})();
</script>
<script>
(() => {
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
})();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
