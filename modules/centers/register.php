<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: register.php');
        exit;
    }

    $unionId = (int) ($_POST['union_id'] ?? 0);
    $conferenceId = (int) ($_POST['conference_id'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['type'] ?? 'Church');
    $region = trim($_POST['region'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $coordinatorName = trim($_POST['coordinator_name'] ?? '');
    $coordinatorPhone = normalize_phone($_POST['coordinator_phone'] ?? '');
    $coordinatorEmail = trim($_POST['coordinator_email'] ?? '');
    $meetingTime = trim($_POST['meeting_time'] ?? '');

    $allowedTypes = ['Church', 'Home', 'School', 'Group', 'Institution'];

    if ($unionId <= 0 || $conferenceId <= 0 || $name === '' || $region === '' || $district === '' || $ward === '' || $coordinatorName === '' || $coordinatorPhone === '' || !in_array($type, $allowedTypes, true)) {
        set_flash('danger', 'Please fill all required fields correctly.');
        header('Location: register.php');
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

        set_flash('success', 'Station registered! Your login credentials — Station Name: "' . $name . '" | Password: your coordinator mobile number. Use these to submit meeting reports from the main page.');
        header('Location: /Tumaini-Connect/modules/interests/public_form.php');
        exit;
    }

    set_flash('danger', 'Could not register station. Please try again.');
    header('Location: register.php');
    exit;
}

$unions = $conn->query('SELECT id, name FROM unions ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$conferences = $conn->query('SELECT id, union_id, name FROM conferences ORDER BY name')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Register Station';
include __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card tc-card">
            <div class="card-body p-4">
                <h4 class="mb-2">Station Registration</h4>
                <p class="text-muted">Create a station account without admin login. Station number and coordinator phone will be used for reporting access.</p>

                <form method="post" class="row g-3" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="col-md-6">
                        <label class="form-label">Union</label>
                        <select class="form-select" name="union_id" id="union_id" required>
                            <option value="">Select union</option>
                            <?php foreach ($unions as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Conference / Field</label>
                        <select class="form-select" name="conference_id" id="conference_id" required>
                            <option value="">Select conference</option>
                            <?php foreach ($conferences as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" data-union="<?= (int) $c['union_id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Station Name</label>
                        <input class="form-control" name="name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" required>
                            <option>Church</option>
                            <option>Home</option>
                            <option>School</option>
                            <option>Group</option>
                            <option>Institution</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Region</label>
                        <input class="form-control" name="region" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">District</label>
                        <input class="form-control" name="district" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ward</label>
                        <input class="form-control" name="ward" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Coordinator Name</label>
                        <input class="form-control" name="coordinator_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Coordinator Phone (Used as Password)</label>
                        <input class="form-control" name="coordinator_phone" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Coordinator Email</label>
                        <input type="email" class="form-control" name="coordinator_email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Meeting Time</label>
                        <input class="form-control" name="meeting_time" placeholder="e.g. Saturday 15:00">
                    </div>

                    <div class="col-12 d-grid gap-2">
                        <button class="btn btn-primary">Register Station</button>
                        <a class="btn btn-outline-secondary" href="/Tumaini-Connect/modules/interests/public_form.php">Go to Station Report Form</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const unionSelect = document.getElementById('union_id');
    const confSelect = document.getElementById('conference_id');
    if (!unionSelect || !confSelect) return;

    const options = Array.from(confSelect.options).map(opt => ({
        value: opt.value,
        text: opt.text,
        union: opt.getAttribute('data-union')
    }));

    function renderConferences() {
        const selectedUnion = unionSelect.value;
        confSelect.innerHTML = '<option value="">Select conference</option>';
        options.filter(o => o.value !== '' && (selectedUnion === '' || o.union === selectedUnion)).forEach(o => {
            const option = document.createElement('option');
            option.value = o.value;
            option.textContent = o.text;
            option.setAttribute('data-union', o.union || '');
            confSelect.appendChild(option);
        });
    }

    unionSelect.addEventListener('change', renderConferences);
})();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
