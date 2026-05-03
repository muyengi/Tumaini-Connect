<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['tanzania_admin', 'union_admin']);

$user = current_user();
$isUnionAdmin = $user['role'] === 'union_admin';

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $unionId = (int) ($_POST['union_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? 'Conference';

        if ($isUnionAdmin) {
            $unionId = (int) $user['union_id'];
        }

        $stmt = $conn->prepare('INSERT INTO conferences (union_id, name, type) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $unionId, $name, $type);
        if ($stmt->execute()) {
            set_flash('success', 'Conference/Field created.');
        } else {
            set_flash('danger', 'Failed to create Conference/Field.');
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($isUnionAdmin) {
            $stmt = $conn->prepare('DELETE c FROM conferences c WHERE c.id = ? AND c.union_id = ?');
            $stmt->bind_param('ii', $id, $user['union_id']);
        } else {
            $stmt = $conn->prepare('DELETE FROM conferences WHERE id = ?');
            $stmt->bind_param('i', $id);
        }
        $stmt->execute();
        set_flash('warning', 'Conference/Field deleted.');
    }

    header('Location: index.php');
    exit;
}

$unionsSql = $isUnionAdmin
    ? 'SELECT id, name FROM unions WHERE id = ' . (int) $user['union_id']
    : 'SELECT id, name FROM unions ORDER BY name';
$unions = $conn->query($unionsSql)->fetch_all(MYSQLI_ASSOC);

$listSql = 'SELECT c.id, c.name, c.type, c.created_at, u.name union_name FROM conferences c JOIN unions u ON c.union_id = u.id';
if ($isUnionAdmin) {
    $listSql .= ' WHERE c.union_id = ' . (int) $user['union_id'];
}
$listSql .= ' ORDER BY c.id DESC';
$conferences = $conn->query($listSql)->fetch_all(MYSQLI_ASSOC);

$totalConferences = count($conferences);
$fieldCount = 0;
$conferenceCount = 0;
$unionNames = [];
foreach ($conferences as $conference) {
    $type = strtolower((string) ($conference['type'] ?? ''));
    if ($type === 'field') {
        $fieldCount++;
    } else {
        $conferenceCount++;
    }
    $unionNames[(string) $conference['union_name']] = true;
}
$scopeLabel = $isUnionAdmin ? 'Union View' : 'Tanzania Wide View';
$unionCount = count($unionNames);

$pageTitle = 'Conference/Field Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">Territory Structure</p>
                <h3 class="mb-2">Conference / Field Management</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e($scopeLabel) ?></span>
                    <span class="badge tc-badge-gold">Territory Setup</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createConferenceModal">Add Conference / Field</button>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/admin/dashboard.php">Dashboard</a>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/centers/index.php">Stations</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-midnight h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-bank"></i></div>
            <p class="tc-stat-label mb-1">In Scope</p>
            <h3 class="mb-1 js-count" data-target="<?= $totalConferences ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-teal h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-domain"></i></div>
            <p class="tc-stat-label mb-1">Conferences</p>
            <h3 class="mb-1 js-count" data-target="<?= $conferenceCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-paper h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-map-marker-radius"></i></div>
            <p class="tc-stat-label mb-1">Fields</p>
            <h3 class="mb-1 js-count" data-target="<?= $fieldCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-source-branch"></i></div>
            <p class="tc-stat-label mb-1">Unions Covered</p>
            <h3 class="mb-1 js-count" data-target="<?= $unionCount ?>">0</h3>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card tc-card"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Conferences / Fields</h5>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createConferenceModal">Add Conference / Field</button>
            </div>
            <div class="table-responsive">
                <table class="table datatable">
                    <thead><tr><th>ID</th><th>Union</th><th>Name</th><th>Type</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($conferences as $row): ?>
                        <tr>
                            <td><?= (int) $row['id'] ?></td>
                            <td><?= e($row['union_name']) ?></td>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['type']) ?></td>
                            <td><?= e($row['created_at']) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this item?')">
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

<div class="modal fade" id="createConferenceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Conference / Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Union</label>
                        <select name="union_id" class="form-select" required>
                            <?php foreach ($unions as $union): ?>
                                <option value="<?= (int) $union['id'] ?>"><?= e($union['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="Conference">Conference</option>
                            <option value="Field">Field</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
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
