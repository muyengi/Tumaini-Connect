<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_role(['tanzania_admin', 'union_admin', 'conference_admin']);

$user = current_user();
$role = (string) $user['role'];

$creatableRoles = match ($role) {
    'tanzania_admin' => ['union_admin', 'conference_admin', 'followup', 'coordinator'],
    'union_admin' => ['conference_admin', 'followup', 'coordinator'],
    default => ['followup', 'coordinator'],
};

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $createdRole = (string) ($_POST['user_role'] ?? 'followup');
        $assignmentScope = (string) ($_POST['assignment_scope'] ?? 'conference');
        $unionId = (int) ($_POST['union_id'] ?? 0);
        $conferenceId = (int) ($_POST['conference_id'] ?? 0);

        if (!in_array($createdRole, $creatableRoles, true)) {
            set_flash('danger', 'You cannot create that role from this account.');
            header('Location: index.php');
            exit;
        }

        if ($role === 'union_admin') {
            $unionId = (int) $user['union_id'];
        }
        if ($role === 'conference_admin') {
            $conferenceId = (int) $user['conference_id'];
        }

        if ($role === 'conference_admin' && $unionId <= 0) {
            $unionStmt = $conn->prepare('SELECT union_id FROM conferences WHERE id = ? LIMIT 1');
            $unionStmt->bind_param('i', $conferenceId);
            $unionStmt->execute();
            $unionId = (int) (($unionStmt->get_result()->fetch_assoc()['union_id'] ?? 0));
        }

        if ($createdRole === 'union_admin') {
            $assignmentScope = 'union';
            $conferenceId = 0;
        } elseif (in_array($createdRole, ['conference_admin', 'coordinator'], true)) {
            $assignmentScope = 'conference';
        }

        if ($role === 'tanzania_admin') {
            if (!in_array($assignmentScope, ['union', 'conference'], true)) {
                $assignmentScope = 'conference';
            }

            if ($assignmentScope === 'union') {
                $conferenceId = 0;
            }
        }

        if ($name === '' || $email === '' || $password === '') {
            set_flash('danger', 'Name, email and password are required.');
            header('Location: index.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please provide a valid email address.');
            header('Location: index.php');
            exit;
        }

        if (strlen($password) < 6) {
            set_flash('danger', 'Password must be at least 6 characters.');
            header('Location: index.php');
            exit;
        }

        if ($unionId <= 0) {
            set_flash('danger', 'Please choose a valid union.');
            header('Location: index.php');
            exit;
        }

        if ($assignmentScope === 'conference') {
            if ($conferenceId <= 0) {
                set_flash('danger', 'Please choose a conference/field for this user.');
                header('Location: index.php');
                exit;
            }
        }

        $userPhone = normalize_phone($_POST['phone'] ?? '');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (name, email, phone, password, role, union_id, conference_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssii', $name, $email, $userPhone ?: null, $hash, $createdRole, $unionId, $conferenceId);

        if ($stmt->execute()) {
            set_flash('success', role_label($createdRole) . ' created successfully.');
        } else {
            set_flash('danger', 'Failed to create user. Email may already exist.');
        }
    }

    if ($action === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $deleteSql = 'DELETE FROM users WHERE id = ? AND role IN ("followup", "conference_admin", "union_admin", "coordinator")';

        if ($role === 'union_admin') {
            $deleteSql .= ' AND union_id = ' . (int) $user['union_id'];
        } elseif ($role === 'conference_admin') {
            $deleteSql .= ' AND conference_id = ' . (int) $user['conference_id'];
        }

        $stmt = $conn->prepare($deleteSql);
        $stmt->bind_param('i', $id);
        $stmt->execute();

        set_flash('warning', 'User deleted.');
    }

    if ($action === 'edit_user') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $updatedRole = (string) ($_POST['user_role'] ?? 'followup');
        $assignmentScope = (string) ($_POST['assignment_scope'] ?? 'conference');
        $unionId = (int) ($_POST['union_id'] ?? 0);
        $conferenceId = (int) ($_POST['conference_id'] ?? 0);

        $targetSql = 'SELECT id, role FROM users WHERE id = ? AND role IN ("followup", "conference_admin", "union_admin", "coordinator")';
        if ($role === 'union_admin') {
            $targetSql .= ' AND union_id = ' . (int) $user['union_id'];
        } elseif ($role === 'conference_admin') {
            $targetSql .= ' AND conference_id = ' . (int) $user['conference_id'];
        }
        $targetStmt = $conn->prepare($targetSql);
        $targetStmt->bind_param('i', $id);
        $targetStmt->execute();
        $target = $targetStmt->get_result()->fetch_assoc();

        if (!$target) {
            set_flash('danger', 'User not found in your scope.');
            header('Location: index.php');
            exit;
        }

        if (!in_array($updatedRole, $creatableRoles, true)) {
            set_flash('danger', 'You cannot assign that role from this account.');
            header('Location: index.php');
            exit;
        }

        if ($role === 'union_admin') {
            $unionId = (int) $user['union_id'];
        }
        if ($role === 'conference_admin') {
            $conferenceId = (int) $user['conference_id'];
        }

        if ($role === 'conference_admin' && $unionId <= 0) {
            $unionStmt = $conn->prepare('SELECT union_id FROM conferences WHERE id = ? LIMIT 1');
            $unionStmt->bind_param('i', $conferenceId);
            $unionStmt->execute();
            $unionId = (int) (($unionStmt->get_result()->fetch_assoc()['union_id'] ?? 0));
        }

        if ($updatedRole === 'union_admin') {
            $assignmentScope = 'union';
            $conferenceId = 0;
        } elseif (in_array($updatedRole, ['conference_admin', 'coordinator'], true)) {
            $assignmentScope = 'conference';
        }

        if ($role === 'tanzania_admin') {
            if (!in_array($assignmentScope, ['union', 'conference'], true)) {
                $assignmentScope = 'conference';
            }
            if ($assignmentScope === 'union') {
                $conferenceId = 0;
            }
        }

        if ($name === '' || $email === '') {
            set_flash('danger', 'Name and email are required.');
            header('Location: index.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please provide a valid email address.');
            header('Location: index.php');
            exit;
        }

        if ($unionId <= 0) {
            set_flash('danger', 'Please choose a valid union.');
            header('Location: index.php');
            exit;
        }

        if ($assignmentScope === 'conference' && $conferenceId <= 0) {
            set_flash('danger', 'Please choose a conference/field for this user.');
            header('Location: index.php');
            exit;
        }

        if ($password !== '' && strlen($password) < 6) {
            set_flash('danger', 'New password must be at least 6 characters.');
            header('Location: index.php');
            exit;
        }

        $editPhone = normalize_phone($_POST['phone'] ?? '');
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ?, password = ?, role = ?, union_id = ?, conference_id = ? WHERE id = ?');
            $stmt->bind_param('sssssiii', $name, $email, $editPhone ?: null, $hash, $updatedRole, $unionId, $conferenceId, $id);
        } else {
            $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ?, role = ?, union_id = ?, conference_id = ? WHERE id = ?');
            $stmt->bind_param('ssssiii', $name, $email, $editPhone ?: null, $updatedRole, $unionId, $conferenceId, $id);
        }

        if ($stmt->execute()) {
            set_flash('success', 'User updated successfully.');
        } else {
            set_flash('danger', 'Failed to update user. Email may already exist.');
        }
    }

    header('Location: index.php');
    exit;
}

$unionSql = 'SELECT id, name FROM unions';
if ($role === 'union_admin') {
    $unionSql .= ' WHERE id = ' . (int) $user['union_id'];
} elseif ($role === 'conference_admin') {
    $unionSql .= ' WHERE id = (SELECT union_id FROM conferences WHERE id = ' . (int) $user['conference_id'] . ')';
}
$unionSql .= ' ORDER BY name';
$unions = $conn->query($unionSql)->fetch_all(MYSQLI_ASSOC);

$conferenceSql = 'SELECT id, union_id, name FROM conferences';
if ($role === 'union_admin') {
    $conferenceSql .= ' WHERE union_id = ' . (int) $user['union_id'];
} elseif ($role === 'conference_admin') {
    $conferenceSql .= ' WHERE id = ' . (int) $user['conference_id'];
}
$conferenceSql .= ' ORDER BY name';
$conferences = $conn->query($conferenceSql)->fetch_all(MYSQLI_ASSOC);

$userListSql = 'SELECT u.id, u.name, u.email, u.phone, u.role, u.union_id, u.conference_id, u.created_at, un.name union_name, c.name conference_name
                FROM users u
                LEFT JOIN unions un ON u.union_id = un.id
                LEFT JOIN conferences c ON u.conference_id = c.id
                WHERE u.role IN ("followup", "conference_admin", "union_admin", "coordinator")';
if ($role === 'union_admin') {
    $userListSql .= ' AND u.union_id = ' . (int) $user['union_id'];
} elseif ($role === 'conference_admin') {
    $userListSql .= ' AND u.conference_id = ' . (int) $user['conference_id'];
}
$userListSql .= ' ORDER BY u.id DESC';
$users = $conn->query($userListSql)->fetch_all(MYSQLI_ASSOC);

$totalUsers = count($users);
$followupUsers = 0;
$coordinatorUsers = 0;
$adminUsers = 0;
foreach ($users as $row) {
    if ($row['role'] === 'followup') {
        $followupUsers++;
    } elseif ($row['role'] === 'coordinator') {
        $coordinatorUsers++;
    } else {
        $adminUsers++;
    }
}

$scopeLabel = $role === 'tanzania_admin'
    ? 'Tanzania Wide View'
    : ($role === 'union_admin' ? 'Union View' : 'Conference View');

$pageTitle = 'User Management';
include __DIR__ . '/../../includes/header.php';
?>
<div class="card tc-dash-hero mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="tc-dash-hero-kicker mb-1">People & Access</p>
                <h3 class="mb-2">User Management</h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge tc-badge-dark"><?= e($scopeLabel) ?></span>
                    <span class="badge tc-badge-gold">Role-based Access Control</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createUserModal">Add User</button>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/admin/dashboard.php">Dashboard</a>
                <a class="btn btn-outline-dark btn-sm" href="/Tumaini-Connect/modules/followups/index.php">Follow-up Board</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-midnight h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-account-multiple"></i></div>
                <p class="tc-stat-label mb-1">Users in Scope</p>
                <h3 class="mb-1 js-count" data-target="<?= $totalUsers ?>">0</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-teal h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-account-check"></i></div>
                <p class="tc-stat-label mb-1">Follow-up Officers</p>
                <h3 class="mb-1 js-count" data-target="<?= $followupUsers ?>">0</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-paper h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-account-star"></i></div>
                <p class="tc-stat-label mb-1">Coordinators</p>
                <h3 class="mb-1 js-count" data-target="<?= $coordinatorUsers ?>">0</h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card tc-kpi-card tc-kpi-sunrise h-100">
            <div class="card-body">
                <div class="tc-kpi-icon"><i class="mdi mdi-shield-account"></i></div>
                <p class="tc-stat-label mb-1">Admin Roles</p>
                <h3 class="mb-1 js-count" data-target="<?= $adminUsers ?>">0</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="card tc-card"><div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0">Users in Scope</h5>
                <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createUserModal">Add User</button>
            </div>
            <div class="table-responsive">
                <table class="table datatable">
                    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Role</th><th>Union</th><th>Conference</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e($u['name']) ?></td>
                            <td><?= e((string) ($u['phone'] ?? '—')) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><?= e(role_label((string) $u['role'])) ?></td>
                            <td><?= e((string) $u['union_name']) ?></td>
                            <td><?= e((string) $u['conference_name']) ?></td>
                            <td><?= e((string) $u['created_at']) ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary js-edit-user"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-id="<?= (int) $u['id'] ?>"
                                        data-name="<?= e($u['name']) ?>"
                                        data-email="<?= e($u['email']) ?>"
                                        data-phone="<?= e((string) ($u['phone'] ?? '')) ?>"
                                        data-role="<?= e((string) $u['role']) ?>"
                                        data-union-id="<?= (int) ($u['union_id'] ?? 0) ?>"
                                        data-conference-id="<?= (int) ($u['conference_id'] ?? 0) ?>"
                                    >Edit</button>
                                    <form method="post" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id" id="editUserId" value="0">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" id="editUserName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <small class="text-muted">(for SMS notifications)</small></label>
                        <input type="tel" class="form-control" name="phone" id="editUserPhone" placeholder="e.g. 0712345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="user_role" id="editUserRole" required>
                            <?php foreach ($creatableRoles as $creatableRole): ?>
                                <option value="<?= e($creatableRole) ?>"><?= e(role_label($creatableRole)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editUserEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (optional)</label>
                        <input type="password" class="form-control" name="password" id="editUserPassword" minlength="6" placeholder="Leave blank to keep current password">
                    </div>
                    <?php if ($role === 'tanzania_admin'): ?>
                    <div class="mb-3">
                        <label class="form-label">Assignment Level</label>
                        <select class="form-select" name="assignment_scope" id="editAssignmentScope">
                            <option value="union">Union Only</option>
                            <option value="conference" selected>Conference / Field</option>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="assignment_scope" value="conference" id="editAssignmentScope">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Union</label>
                        <select class="form-select" name="union_id" id="editUserUnion" <?= $role !== 'tanzania_admin' ? 'disabled' : '' ?> required>
                            <?php foreach ($unions as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($role !== 'tanzania_admin' && isset($unions[0])): ?>
                            <input type="hidden" name="union_id" value="<?= (int) $unions[0]['id'] ?>">
                        <?php endif; ?>
                    </div>
                    <div class="mb-3" id="editConferenceGroup">
                        <label class="form-label">Conference / Field</label>
                        <select class="form-select" name="conference_id" id="editUserConference" <?= $role === 'conference_admin' ? 'disabled' : '' ?> required>
                            <?php foreach ($conferences as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($role === 'conference_admin' && isset($conferences[0])): ?>
                            <input type="hidden" name="conference_id" value="<?= (int) $conferences[0]['id'] ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_user">

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone <small class="text-muted">(for SMS notifications)</small></label>
                        <input type="tel" class="form-control" name="phone" placeholder="e.g. 0712345678">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="user_role" id="userRole" required>
                            <?php foreach ($creatableRoles as $creatableRole): ?>
                                <option value="<?= e($creatableRole) ?>"><?= e(role_label($creatableRole)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" minlength="6" required>
                    </div>
                    <?php if ($role === 'tanzania_admin'): ?>
                    <div class="mb-3">
                        <label class="form-label">Assignment Level</label>
                        <select class="form-select" name="assignment_scope" id="assignmentScope">
                            <option value="union">Union Only</option>
                            <option value="conference" selected>Conference / Field</option>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="assignment_scope" value="conference">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Union</label>
                        <select class="form-select" name="union_id" id="userUnion" <?= $role !== 'tanzania_admin' ? 'disabled' : '' ?> required>
                            <?php foreach ($unions as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($role !== 'tanzania_admin' && isset($unions[0])): ?>
                            <input type="hidden" name="union_id" value="<?= (int) $unions[0]['id'] ?>">
                        <?php endif; ?>
                    </div>
                    <div class="mb-3" id="conferenceGroup">
                        <label class="form-label">Conference / Field</label>
                        <select class="form-select" name="conference_id" id="userConference" <?= $role === 'conference_admin' ? 'disabled' : '' ?> required>
                            <?php foreach ($conferences as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($role === 'conference_admin' && isset($conferences[0])): ?>
                            <input type="hidden" name="conference_id" value="<?= (int) $conferences[0]['id'] ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const scopeSelect = document.getElementById('assignmentScope');
    const roleSelect = document.getElementById('userRole');
    const unionSelect = document.getElementById('userUnion');
    const confSelect = document.getElementById('userConference');
    const conferenceGroup = document.getElementById('conferenceGroup');
    if (!unionSelect || !confSelect) return;

    const options = Array.from(confSelect.options).map((opt) => ({
        value: opt.value,
        text: opt.textContent,
        union: opt.getAttribute('data-union') || ''
    }));

    const renderConferences = () => {
        const selectedUnion = unionSelect.value;
        const current = confSelect.value;
        confSelect.innerHTML = '';

        options.forEach((opt) => {
            if (selectedUnion !== '' && opt.union !== selectedUnion) return;
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.text;
            option.setAttribute('data-union', opt.union);
            if (opt.value === current) option.selected = true;
            confSelect.appendChild(option);
        });
    };

    const syncAssignmentMode = () => {
        if (!conferenceGroup) return;
        const selectedRole = roleSelect ? roleSelect.value : 'followup';
        let isConferenceScope = true;

        if (selectedRole === 'union_admin') {
            isConferenceScope = false;
            if (scopeSelect) scopeSelect.value = 'union';
        } else if (selectedRole === 'conference_admin' || selectedRole === 'coordinator') {
            isConferenceScope = true;
            if (scopeSelect) scopeSelect.value = 'conference';
        } else if (scopeSelect) {
            isConferenceScope = scopeSelect.value === 'conference';
        }

        if (scopeSelect) {
            const showScopeChoice = selectedRole === 'followup' && <?= $role === 'tanzania_admin' ? 'true' : 'false' ?>;
            const scopeWrapper = scopeSelect.closest('.mb-3');
            if (scopeWrapper) {
                scopeWrapper.style.display = showScopeChoice ? '' : 'none';
            }
            scopeSelect.disabled = !showScopeChoice;
        }

        conferenceGroup.style.display = isConferenceScope ? '' : 'none';
        confSelect.required = isConferenceScope;
        confSelect.disabled = !isConferenceScope;
        if (!isConferenceScope) {
            confSelect.value = '';
        } else {
            renderConferences();
        }
    };

    unionSelect.addEventListener('change', renderConferences);
    renderConferences();

    if (scopeSelect) {
        scopeSelect.addEventListener('change', syncAssignmentMode);
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', syncAssignmentMode);
    }

    syncAssignmentMode();

    const editRoleSelect = document.getElementById('editUserRole');
    const editUnionSelect = document.getElementById('editUserUnion');
    const editConfSelect = document.getElementById('editUserConference');
    const editScopeSelect = document.getElementById('editAssignmentScope');
    const editConferenceGroup = document.getElementById('editConferenceGroup');
    const editButtons = document.querySelectorAll('.js-edit-user');

    if (editUnionSelect && editConfSelect) {
        const editOptions = Array.from(editConfSelect.options).map((opt) => ({
            value: opt.value,
            text: opt.textContent,
            union: opt.getAttribute('data-union') || ''
        }));

        const renderEditConferences = () => {
            const selectedUnion = editUnionSelect.value;
            const current = editConfSelect.getAttribute('data-current') || editConfSelect.value;
            editConfSelect.innerHTML = '';

            editOptions.forEach((opt) => {
                if (selectedUnion !== '' && opt.union !== selectedUnion) return;
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.text;
                option.setAttribute('data-union', opt.union);
                if (opt.value === current) option.selected = true;
                editConfSelect.appendChild(option);
            });
        };

        const syncEditAssignmentMode = () => {
            if (!editConferenceGroup || !editRoleSelect) return;
            const selectedRole = editRoleSelect.value;
            let isConferenceScope = true;

            if (selectedRole === 'union_admin') {
                isConferenceScope = false;
                if (editScopeSelect) editScopeSelect.value = 'union';
            } else if (selectedRole === 'conference_admin' || selectedRole === 'coordinator') {
                isConferenceScope = true;
                if (editScopeSelect) editScopeSelect.value = 'conference';
            } else if (editScopeSelect && editScopeSelect.tagName === 'SELECT') {
                isConferenceScope = editScopeSelect.value === 'conference';
            }

            if (editScopeSelect && editScopeSelect.tagName === 'SELECT') {
                const showScopeChoice = selectedRole === 'followup' && <?= $role === 'tanzania_admin' ? 'true' : 'false' ?>;
                const scopeWrapper = editScopeSelect.closest('.mb-3');
                if (scopeWrapper) {
                    scopeWrapper.style.display = showScopeChoice ? '' : 'none';
                }
                editScopeSelect.disabled = !showScopeChoice;
            }

            editConferenceGroup.style.display = isConferenceScope ? '' : 'none';
            editConfSelect.required = isConferenceScope;
            editConfSelect.disabled = !isConferenceScope;

            if (!isConferenceScope) {
                editConfSelect.value = '';
            } else {
                renderEditConferences();
            }
        };

        editButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id') || '0';
                const name = btn.getAttribute('data-name') || '';
                const email = btn.getAttribute('data-email') || '';
                const phone = btn.getAttribute('data-phone') || '';
                const roleVal = btn.getAttribute('data-role') || 'followup';
                const unionId = btn.getAttribute('data-union-id') || '';
                const conferenceId = btn.getAttribute('data-conference-id') || '';

                document.getElementById('editUserId').value = id;
                document.getElementById('editUserName').value = name;
                document.getElementById('editUserEmail').value = email;
                const editPhoneEl = document.getElementById('editUserPhone');
                if (editPhoneEl) editPhoneEl.value = phone;
                document.getElementById('editUserPassword').value = '';
                editRoleSelect.value = roleVal;
                editUnionSelect.value = unionId;
                editConfSelect.setAttribute('data-current', conferenceId);

                if (editScopeSelect && editScopeSelect.tagName === 'SELECT') {
                    editScopeSelect.value = conferenceId ? 'conference' : 'union';
                }

                syncEditAssignmentMode();
            });
        });

        editUnionSelect.addEventListener('change', renderEditConferences);
        if (editScopeSelect && editScopeSelect.tagName === 'SELECT') {
            editScopeSelect.addEventListener('change', syncEditAssignmentMode);
        }
        if (editRoleSelect) {
            editRoleSelect.addEventListener('change', syncEditAssignmentMode);
        }
    }

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
