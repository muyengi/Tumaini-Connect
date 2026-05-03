<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['tanzania_admin']);

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            set_flash('danger', 'Union name is required.');
        } else {
            $stmt = $conn->prepare('INSERT INTO unions (name) VALUES (?)');
            $stmt->bind_param('s', $name);
            if ($stmt->execute()) {
                set_flash('success', 'Union created successfully.');
            } else {
                set_flash('danger', 'Failed to create union. Ensure the name is unique.');
            }
        }
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $stmt = $conn->prepare('UPDATE unions SET name = ? WHERE id = ?');
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        set_flash('success', 'Union updated.');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM unions WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        set_flash('warning', 'Union deleted.');
    }

    header('Location: index.php');
    exit;
}

$rows = $conn->query('SELECT id, name, created_at FROM unions ORDER BY id DESC')->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Union Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="row g-3">
    <div class="col-12">
        <div class="card tc-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">All Unions</h5>
                    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createUnionModal">Add Union</button>
                </div>
                <div class="table-responsive">
                    <table class="table datatable">
                        <thead><tr><th>ID</th><th>Name</th><th>Created</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= e($row['name']) ?></td>
                                <td><?= e($row['created_at']) ?></td>
                                <td>
                                    <form method="post" class="d-inline-flex gap-1">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <input type="text" class="form-control form-control-sm" name="name" value="<?= e($row['name']) ?>" required>
                                        <button class="btn btn-sm btn-outline-primary">Update</button>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this union?')">
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
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createUnionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Union</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Union Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Save Union</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
