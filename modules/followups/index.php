<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$user = current_user();
$role = $user['role'];

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'assign' && in_array($role, ['tanzania_admin', 'union_admin', 'conference_admin', 'coordinator'], true)) {
        $interestId = (int) ($_POST['interest_id'] ?? 0);
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
        $status = trim($_POST['status'] ?? 'Pending');
        $remarks = trim($_POST['remarks'] ?? '');
        $followupDate = trim($_POST['followup_date'] ?? '');

        $stmt = $conn->prepare('INSERT INTO followups (interest_id, assigned_to, status, remarks, followup_date) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE assigned_to = VALUES(assigned_to), status = VALUES(status), remarks = VALUES(remarks), followup_date = VALUES(followup_date)');
        $stmt->bind_param('iisss', $interestId, $assignedTo, $status, $remarks, $followupDate);
        if ($stmt->execute()) {
            $conn->query('UPDATE interests SET status = "assigned" WHERE id = ' . $interestId);
            set_flash('success', 'Follow-up assignment saved.');
        } else {
            set_flash('danger', 'Could not save follow-up assignment.');
        }
    }

    if ($action === 'update' && in_array($role, ['followup', 'coordinator', 'conference_admin', 'union_admin', 'tanzania_admin'], true)) {
        $id = (int) ($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Pending');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($role === 'followup') {
            $stmt = $conn->prepare('UPDATE followups SET status = ?, remarks = ? WHERE id = ? AND assigned_to = ?');
            $stmt->bind_param('ssii', $status, $remarks, $id, $user['id']);
        } else {
            $stmt = $conn->prepare('UPDATE followups SET status = ?, remarks = ? WHERE id = ?');
            $stmt->bind_param('ssi', $status, $remarks, $id);
        }
        $stmt->execute();

        if (strtolower($status) === 'completed') {
            $conn->query('UPDATE interests i JOIN followups f ON i.id = f.interest_id SET i.status = "completed" WHERE f.id = ' . $id);
        }

        set_flash('success', 'Follow-up updated.');
    }

    header('Location: index.php');
    exit;
}

$usersSql = "SELECT id, name, role FROM users WHERE role IN ('followup', 'coordinator') ORDER BY name";
$assignableUsers = $conn->query($usersSql)->fetch_all(MYSQLI_ASSOC);

$pendingSql = 'SELECT i.id, i.full_name, i.phone, i.request_type, c.name center_name
               FROM interests i
               JOIN centers c ON i.center_id = c.id
               WHERE i.status IN ("pending", "assigned")';
if ($role === 'union_admin') {
    $pendingSql .= ' AND c.union_id = ' . (int) $user['union_id'];
} elseif ($role === 'conference_admin') {
    $pendingSql .= ' AND c.conference_id = ' . (int) $user['conference_id'];
} elseif ($role === 'coordinator') {
    $email = $conn->real_escape_string($user['email']);
    $name = $conn->real_escape_string($user['name']);
    $pendingSql .= " AND (c.coordinator_email = '$email' OR c.coordinator_name = '$name')";
} elseif ($role === 'followup') {
    $pendingSql .= ' AND i.id IN (SELECT interest_id FROM followups WHERE assigned_to = ' . (int) $user['id'] . ')';
}
$pendingSql .= ' ORDER BY i.id DESC';
$pendingInterests = $conn->query($pendingSql)->fetch_all(MYSQLI_ASSOC);

$listSql = 'SELECT f.*, i.full_name, i.phone, i.request_type, c.name center_name, u.name assigned_name
            FROM followups f
            JOIN interests i ON f.interest_id = i.id
            JOIN centers c ON i.center_id = c.id
            JOIN users u ON f.assigned_to = u.id';
if ($role === 'union_admin') {
    $listSql .= ' WHERE c.union_id = ' . (int) $user['union_id'];
} elseif ($role === 'conference_admin') {
    $listSql .= ' WHERE c.conference_id = ' . (int) $user['conference_id'];
} elseif ($role === 'coordinator') {
    $email = $conn->real_escape_string($user['email']);
    $name = $conn->real_escape_string($user['name']);
    $listSql .= " WHERE (c.coordinator_email = '$email' OR c.coordinator_name = '$name')";
} elseif ($role === 'followup') {
    $listSql .= ' WHERE f.assigned_to = ' . (int) $user['id'];
}
$listSql .= ' ORDER BY f.id DESC';
$followups = $conn->query($listSql)->fetch_all(MYSQLI_ASSOC);

$totalAssignments = count($followups);
$pendingCount = 0;
$contactedCount = 0;
$completedCount = 0;
foreach ($followups as $followup) {
    $status = strtolower((string) ($followup['status'] ?? 'pending'));
    if ($status === 'completed') {
        $completedCount++;
    } elseif ($status === 'contacted') {
        $contactedCount++;
    } else {
        $pendingCount++;
    }
}
$scopeLabel = $role === 'tanzania_admin'
    ? 'Tanzania Wide View'
    : ($role === 'union_admin'
        ? 'Union View'
        : ($role === 'conference_admin' ? 'Conference View' : ($role === 'coordinator' ? 'Station View' : 'Personal Queue')));

$pageTitle = 'Follow-up Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">Care Pipeline</p>
                <h3 class="mb-2">Follow-up Management</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e($scopeLabel) ?></span>
                    <span class="badge tc-badge-gold">Response Workflow</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if (in_array($role, ['tanzania_admin', 'union_admin', 'conference_admin', 'coordinator'], true)): ?>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#assignFollowupModal">Assign Follow-up</button>
                <?php endif; ?>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/admin/dashboard.php">Dashboard</a>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/interests/index.php">Interests</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-midnight h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-format-list-bulleted"></i></div>
            <p class="tc-stat-label mb-1">Assignments</p>
            <h3 class="mb-1 js-count" data-target="<?= $totalAssignments ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-teal h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-timer-sand"></i></div>
            <p class="tc-stat-label mb-1">Pending</p>
            <h3 class="mb-1 js-count" data-target="<?= $pendingCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-paper h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-phone-check"></i></div>
            <p class="tc-stat-label mb-1">Contacted</p>
            <h3 class="mb-1 js-count" data-target="<?= $contactedCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-check-decagram"></i></div>
            <p class="tc-stat-label mb-1">Completed</p>
            <h3 class="mb-1 js-count" data-target="<?= $completedCount ?>">0</h3>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card tc-card"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Follow-up List</h5>
                <?php if (in_array($role, ['tanzania_admin', 'union_admin', 'conference_admin', 'coordinator'], true)): ?>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#assignFollowupModal">Assign Follow-up</button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table datatable">
                    <thead><tr><th>Person</th><th>Request</th><th>Assigned</th><th>Status</th><th>Date</th><th>Remarks</th></tr></thead>
                    <tbody>
                    <?php foreach ($followups as $f): ?>
                        <tr>
                            <td><?= e($f['full_name']) ?><br><small><?= e($f['phone']) ?></small></td>
                            <td><?= e(ucwords(str_replace('_', ' ', $f['request_type']))) ?><br><small><?= e($f['center_name']) ?></small></td>
                            <td><?= e($f['assigned_name']) ?></td>
                            <td>
                                <form method="post" class="d-flex gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                    <select class="form-select form-select-sm" name="status">
                                        <option <?= $f['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option <?= $f['status'] === 'Contacted' ? 'selected' : '' ?>>Contacted</option>
                                        <option <?= $f['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                            </td>
                            <td><?= e((string) $f['followup_date']) ?></td>
                            <td>
                                    <input class="form-control form-control-sm mb-1" name="remarks" value="<?= e((string) $f['remarks']) ?>">
                                    <button class="btn btn-sm btn-outline-primary">Update</button>
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

<?php if (in_array($role, ['tanzania_admin', 'union_admin', 'conference_admin', 'coordinator'], true)): ?>
<div class="modal fade" id="assignFollowupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="assign">
                    <div class="mb-3">
                        <label class="form-label">Interest</label>
                        <select class="form-select" name="interest_id" required>
                            <option value="">Select request</option>
                            <?php foreach ($pendingInterests as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['full_name']) ?> - <?= e($p['request_type']) ?> (<?= e($p['center_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign To</label>
                        <select class="form-select" name="assigned_to" required>
                            <option value="">Select officer</option>
                            <?php foreach ($assignableUsers as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> (<?= e(role_label($u['role'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Status</label><input class="form-control" name="status" value="Pending"></div>
                    <div class="mb-3"><label class="form-label">Follow-up Date</label><input type="date" class="form-control" name="followup_date"></div>
                    <div class="mb-3"><label class="form-label">Remarks</label><textarea class="form-control" rows="3" name="remarks"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
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
