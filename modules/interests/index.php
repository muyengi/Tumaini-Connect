<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$user = current_user();
$role = (string) $user['role'];

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: index.php');
        exit;
    }

    if (($_POST['action'] ?? '') === 'update_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';

        $allowed = ['pending', 'assigned', 'completed'];
        if (!in_array($status, $allowed, true)) {
            set_flash('danger', 'Invalid status.');
            header('Location: index.php');
            exit;
        }

        $sql = 'UPDATE interests i JOIN centers c ON i.center_id = c.id SET i.status = ? WHERE i.id = ?';
        if ($role === 'union_admin') {
            $sql .= ' AND c.union_id = ' . (int) $user['union_id'];
        } elseif ($role === 'conference_admin') {
            $sql .= ' AND c.conference_id = ' . (int) $user['conference_id'];
        } elseif ($role === 'coordinator') {
            $sql .= " AND (c.coordinator_email = '" . $conn->real_escape_string((string) $user['email']) . "' OR c.coordinator_name = '" . $conn->real_escape_string((string) $user['name']) . "')";
        } elseif ($role === 'followup') {
            $sql .= ' AND i.id IN (SELECT interest_id FROM followups WHERE assigned_to = ' . (int) $user['id'] . ')';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        set_flash('success', 'Interest status updated.');
    }

    header('Location: index.php');
    exit;
}

$sql = 'SELECT i.*, c.name center_name, c.region, c.district, c.ward, u.name union_name, cf.name conference_name
        FROM interests i
        JOIN centers c ON i.center_id = c.id
        JOIN unions u ON c.union_id = u.id
        JOIN conferences cf ON c.conference_id = cf.id';

if ($role === 'union_admin') {
    $sql .= ' WHERE c.union_id = ' . (int) $user['union_id'];
} elseif ($role === 'conference_admin') {
    $sql .= ' WHERE c.conference_id = ' . (int) $user['conference_id'];
} elseif ($role === 'coordinator') {
    $stationId = coordinator_station_id($user);
    if ($stationId !== null) {
        $sql .= ' WHERE c.id = ' . $stationId;
    } else {
        $email = $conn->real_escape_string((string) $user['email']);
        $name = $conn->real_escape_string((string) $user['name']);
        $sql .= " WHERE (c.coordinator_email = '$email' OR c.coordinator_name = '$name')";
    }
} elseif ($role === 'followup') {
    $sql .= ' WHERE i.id IN (SELECT interest_id FROM followups WHERE assigned_to = ' . (int) $user['id'] . ')';
}

$sql .= ' ORDER BY i.id DESC';
$interests = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$totalInterests = count($interests);
$pendingCount = 0;
$assignedCount = 0;
$completedCount = 0;
foreach ($interests as $row) {
    if ($row['status'] === 'pending') {
        $pendingCount++;
    } elseif ($row['status'] === 'assigned') {
        $assignedCount++;
    } elseif ($row['status'] === 'completed') {
        $completedCount++;
    }
}
$completionRate = $totalInterests > 0 ? round(($completedCount / $totalInterests) * 100, 1) : 0.0;

$pageTitle = 'Interest Requests';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">Care Pipeline</p>
                <h3 class="mb-2">Interest Requests</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e(role_label($role)) ?></span>
                    <span class="badge tc-badge-gold">Completion <?= number_format($completionRate, 1) ?>%</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/Tumaini-Connect/modules/interests/public_form.php" target="_blank" class="btn btn-outline-dark btn-sm">Open Public Form</a>
                <a href="/Tumaini-Connect/modules/followups/index.php" class="btn btn-outline-dark btn-sm">Follow-up Board</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-midnight h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-account-multiple"></i></div>
            <p class="tc-stat-label mb-1">Total Interests</p>
            <h3 class="mb-1 js-count" data-target="<?= $totalInterests ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-timer-sand"></i></div>
            <p class="tc-stat-label mb-1">Pending</p>
            <h3 class="mb-1 js-count" data-target="<?= $pendingCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-paper h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-account-arrow-right"></i></div>
            <p class="tc-stat-label mb-1">Assigned</p>
            <h3 class="mb-1 js-count" data-target="<?= $assignedCount ?>">0</h3>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-teal h-100"><div class="card-body">
            <div class="tc-kpi-icon"><i class="mdi mdi-check-circle-outline"></i></div>
            <p class="tc-stat-label mb-1">Completed</p>
            <h3 class="mb-1 js-count" data-target="<?= $completedCount ?>">0</h3>
        </div></div>
    </div>
</div>

<div class="card tc-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Submitted Interests</h5>
            <a href="/Tumaini-Connect/modules/interests/public_form.php" target="_blank" class="btn btn-primary btn-sm">Open Public Form</a>
        </div>

        <div class="table-responsive">
            <table class="table datatable tc-interest-table">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Request</th>
                    <th>Phone</th>
                    <th>Station</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($interests as $row): ?>
                    <tr>
                        <td><?= e($row['full_name']) ?><br><small class="text-muted"><?= e((string) $row['gender']) ?>, <?= (int) $row['age'] ?></small></td>
                        <td><?= e(ucwords(str_replace('_', ' ', (string) $row['request_type']))) ?><br><small><?= e((string) $row['additional_notes']) ?></small></td>
                        <td>
                            <?= e((string) $row['phone']) ?><br>
                            <a href="<?= e(wa_link((string) $row['phone'])) ?>" target="_blank" class="btn btn-sm btn-outline-success mt-1">WhatsApp</a>
                        </td>
                        <td><?= e((string) $row['center_name']) ?><br><small class="text-muted"><?= e((string) $row['union_name']) ?> / <?= e((string) $row['conference_name']) ?></small></td>
                        <td><?= e((string) $row['region']) ?> / <?= e((string) $row['district']) ?> / <?= e((string) $row['ward']) ?></td>
                        <td>
                            <form method="post" class="d-flex gap-1">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <select class="form-select form-select-sm" name="status">
                                    <option value="pending" <?= $row['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="assigned" <?= $row['status'] === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                                    <option value="completed" <?= $row['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                        </td>
                        <td><?= e((string) $row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
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
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
