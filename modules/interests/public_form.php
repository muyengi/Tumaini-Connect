<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms.php';

$stationSession = $_SESSION['station_report_auth'] ?? null;

if (is_post() && (($_POST['action'] ?? '') === 'station_login')) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid form token.');
        header('Location: public_form.php');
        exit;
    }

    $stationName   = trim($_POST['station_name'] ?? '');
    $phonePassword = $_POST['phone_password'] ?? '';

    if (login_station_coordinator($conn, $stationName, $phonePassword)) {
        $stmt = $conn->prepare('SELECT id, name, coordinator_phone FROM centers WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->bind_param('s', $stationName);
        $stmt->execute();
        $center = $stmt->get_result()->fetch_assoc();

        if ($center) {
            $_SESSION['station_report_auth'] = [
                'center_id' => (int) $center['id'],
                'center_name' => (string) $center['name'],
                'phone' => normalize_phone((string) $center['coordinator_phone']),
            ];
            set_flash('success', 'Station verified. You can now submit and track reports.');
            header('Location: public_form.php');
            exit;
        }
    }

    set_flash('danger', 'Invalid station number or phone password.');
    header('Location: public_form.php');
    exit;
}

if (is_post() && (($_POST['action'] ?? '') === 'station_logout')) {
    if (verify_csrf()) {
        unset($_SESSION['station_report_auth']);
        set_flash('warning', 'Station session closed.');
    }
    header('Location: public_form.php');
    exit;
}

if (is_post()) {
    if (($_POST['action'] ?? '') !== 'submit_report') {
        header('Location: public_form.php');
        exit;
    }

    if (!verify_csrf()) {
        set_flash('danger', 'Invalid form token.');
        header('Location: public_form.php');
        exit;
    }

    $stationSession = $_SESSION['station_report_auth'] ?? null;
    if (!$stationSession || empty($stationSession['center_id'])) {
        set_flash('danger', 'Please verify station number and phone before submitting report.');
        header('Location: public_form.php');
        exit;
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $phone = normalize_phone($_POST['phone'] ?? '');
    $centerId = (int) $stationSession['center_id'];
    $requestType = trim($_POST['request_type'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $ward = trim($_POST['ward'] ?? '');
    $notes = trim($_POST['additional_notes'] ?? '');

    $allowedRequest = ['baptism', 'bible_study', 'prayer', 'visit', 'church_connection'];
    if (!in_array($requestType, $allowedRequest, true) || $fullName === '' || $phone === '' || $centerId <= 0) {
        set_flash('danger', 'Please fill all required fields correctly.');
        header('Location: public_form.php');
        exit;
    }

    $locationText = "Location: $region / $district / $ward";
    $allNotes = trim($locationText . "\n" . $notes);

    $stmt = $conn->prepare('INSERT INTO interests (center_id, full_name, gender, age, phone, request_type, additional_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, "pending")');
    $stmt->bind_param('ississs', $centerId, $fullName, $gender, $age, $phone, $requestType, $allNotes);

    if ($stmt->execute()) {
        // Notify coordinator by SMS
        $coordRow = $conn->prepare('SELECT name, coordinator_phone FROM centers WHERE id = ? LIMIT 1');
        $coordRow->bind_param('i', $centerId);
        $coordRow->execute();
        $coordData = $coordRow->get_result()->fetch_assoc();
        if ($coordData) {
            sms_notify_coordinator_new_interest([
                'full_name'    => $fullName,
                'phone'        => $phone,
                'request_type' => $requestType,
            ], $coordData);
        }
        set_flash('success', 'Asante! Your request was submitted successfully.');
    } else {
        set_flash('danger', 'Could not submit your request. Please try again.');
    }

    header('Location: public_form.php');
    exit;
}

$stationCenter = null;
$reports = [];
$followupQueue = [];
if ($stationSession && !empty($stationSession['center_id'])) {
    $centerId = (int) $stationSession['center_id'];

    $centerStmt = $conn->prepare('SELECT id, name, region, district, ward, meeting_time FROM centers WHERE id = ? LIMIT 1');
    $centerStmt->bind_param('i', $centerId);
    $centerStmt->execute();
    $stationCenter = $centerStmt->get_result()->fetch_assoc();

    $reportsStmt = $conn->prepare('SELECT id, full_name, phone, request_type, status, created_at FROM interests WHERE center_id = ? ORDER BY id DESC LIMIT 30');
    $reportsStmt->bind_param('i', $centerId);
    $reportsStmt->execute();
    $reports = $reportsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $followupStmt = $conn->prepare('SELECT i.full_name, i.phone, i.request_type, i.status interest_status, f.status followup_status, f.followup_date, f.remarks, u.name assigned_name FROM interests i JOIN followups f ON f.interest_id = i.id JOIN users u ON u.id = f.assigned_to WHERE i.center_id = ? ORDER BY COALESCE(f.followup_date, DATE(f.created_at)) DESC, f.id DESC LIMIT 30');
    $followupStmt->bind_param('i', $centerId);
    $followupStmt->execute();
    $followupQueue = $followupStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Station Report Form &mdash; Tumaini Connect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--sal-gold:#f9bf3f;--sal-dark:#1b1b2e;--sal-dark2:#252540;--sal-accent:#e8a020;--sal-light:#f8f7f4;--sal-text:#6c757d;}
*{box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{font-family:"Nunito",sans-serif;color:#444;margin:0;background:var(--sal-light);}

/* NAV */
#pfNav{
  position:fixed;top:0;left:0;right:0;z-index:9999;
  background:var(--sal-dark);
  box-shadow:0 4px 20px rgba(0,0,0,.35);
  padding:.6rem 0;
}
#pfNav .navbar-brand{font-size:1.35rem;font-weight:900;color:#fff;text-decoration:none;}
#pfNav .navbar-brand span{color:var(--sal-gold);}
#pfNav .nav-back{
  display:inline-flex;align-items:center;gap:.4rem;
  color:rgba(255,255,255,.75);font-size:.87rem;font-weight:700;
  text-decoration:none;letter-spacing:.4px;
  transition:color .2s;
}
#pfNav .nav-back:hover{color:var(--sal-gold);}

/* PAGE HERO */
.pf-hero{
  background:linear-gradient(135deg,var(--sal-dark) 0%,var(--sal-dark2) 100%);
  padding:7.5rem 0 3.5rem;
  text-align:center;
  color:#fff;
  position:relative;
  overflow:hidden;
}
.pf-hero::before{
  content:"";position:absolute;inset:0;
  background:radial-gradient(ellipse at 65% 45%,rgba(249,191,63,.12),transparent 65%);
  pointer-events:none;
}
.pf-hero-sup{
  display:inline-block;
  font-size:.78rem;font-weight:900;letter-spacing:4px;
  text-transform:uppercase;color:var(--sal-gold);
  margin-bottom:.9rem;
}
.pf-hero h1{font-size:clamp(1.8rem,5vw,2.8rem);font-weight:900;margin-bottom:.8rem;}
.pf-hero p{font-size:1rem;color:rgba(255,255,255,.72);max-width:520px;margin:0 auto;line-height:1.7;}

/* CARD SHELL */
.pf-card{
  background:#fff;
  border-radius:16px;
  box-shadow:0 12px 40px rgba(17,20,31,.10);
  overflow:hidden;
}

/* STATION BADGE */
.pf-station-badge{
  display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;
  background:linear-gradient(90deg,var(--sal-dark),var(--sal-dark2));
  color:#fff;
  padding:1rem 1.5rem;
}
.pf-station-badge .name{font-size:1.05rem;font-weight:900;margin-bottom:.15rem;}
.pf-station-badge .meta{font-size:.8rem;color:rgba(255,255,255,.65);}
.pf-switch-btn{
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);
  color:#fff;font-size:.8rem;font-weight:800;padding:.35rem .85rem;border-radius:999px;
  cursor:pointer;transition:background .2s;white-space:nowrap;
}
.pf-switch-btn:hover{background:rgba(249,191,63,.25);border-color:var(--sal-gold);color:var(--sal-gold);}

/* TABS */
.pf-tabs{
  display:flex;gap:0;
  border-bottom:2px solid #f0ede8;
  padding:0 1.5rem;
  background:#fafaf8;
}
.pf-tab-btn{
  background:none;border:none;
  font-size:.87rem;font-weight:800;color:#9a9da6;
  padding:.9rem 1.1rem .75rem;
  cursor:pointer;position:relative;
  white-space:nowrap;
  transition:color .2s;
}
.pf-tab-btn::after{
  content:"";position:absolute;bottom:-2px;left:0;right:0;height:2px;
  background:var(--sal-gold);border-radius:2px;
  transform:scaleX(0);transition:transform .2s;
}
.pf-tab-btn.active{color:var(--sal-dark);}
.pf-tab-btn.active::after{transform:scaleX(1);}
.pf-tab-btn:hover{color:var(--sal-dark);}

/* TAB PANES */
.pf-pane{display:none;padding:1.8rem 1.5rem;}
.pf-pane.active{display:block;}

/* LOGIN FORM */
.pf-login-wrap{padding:2rem 1.5rem;}
.pf-login-wrap .field-label{
  font-size:.82rem;font-weight:800;letter-spacing:.6px;
  text-transform:uppercase;color:#6b7280;margin-bottom:.35rem;display:block;
}
.pf-login-wrap .form-control{
  border:2px solid #e5e3dc;border-radius:8px;
  padding:.65rem .85rem;font-size:.95rem;font-weight:600;
  transition:border-color .2s,box-shadow .2s;background:#fafaf8;
}
.pf-login-wrap .form-control:focus{
  border-color:var(--sal-gold);box-shadow:0 0 0 3px rgba(249,191,63,.15);background:#fff;
}
.btn-pf-primary{
  background:var(--sal-gold);color:var(--sal-dark);
  font-weight:900;border:none;border-radius:10px;
  padding:.7rem 1.5rem;font-size:.97rem;
  width:100%;transition:background .2s,transform .2s;
}
.btn-pf-primary:hover{background:var(--sal-accent);color:#fff;transform:translateY(-1px);}

/* REPORT FORM */
.pf-section-label{
  font-size:.78rem;font-weight:900;letter-spacing:2px;
  text-transform:uppercase;color:var(--sal-gold);
  background:var(--sal-dark);border-radius:6px;
  padding:.3rem .75rem;display:inline-block;margin-bottom:.9rem;
}
.pf-group{background:#f8f7f4;border-radius:10px;padding:1.1rem 1.2rem;margin-bottom:1.1rem;}
.pf-group-title{font-size:.83rem;font-weight:900;color:#364152;margin-bottom:.7rem;display:flex;align-items:center;gap:.4rem;}
.pf-pane .form-label{font-size:.83rem;font-weight:800;color:#364152;margin-bottom:.3rem;}
.pf-pane .form-control,.pf-pane .form-select{
  border:2px solid #e5e3dc;border-radius:8px;
  padding:.6rem .8rem;font-size:.92rem;font-weight:600;background:#fff;
  transition:border-color .2s;
}
.pf-pane .form-control:focus,.pf-pane .form-select:focus{
  border-color:var(--sal-gold);box-shadow:0 0 0 3px rgba(249,191,63,.15);
}

/* REPORT CARDS */
.report-card{
  background:#f8f7f4;border-radius:10px;
  padding:1rem 1.15rem;margin-bottom:.9rem;
}
.report-card-header{
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:.75rem;gap:.8rem;
}
.report-card-header strong{font-size:.95rem;color:var(--sal-dark);}
.report-badge{
  background:var(--sal-gold);color:var(--sal-dark);
  font-size:.73rem;font-weight:900;padding:.2rem .65rem;border-radius:20px;
}
.report-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;}
.report-stat{background:#fff;border-radius:6px;padding:.35rem .5rem;text-align:center;}
.report-stat .lbl{font-size:.66rem;font-weight:800;color:#9a9da6;text-transform:uppercase;}
.report-stat .val{font-size:1rem;font-weight:900;color:var(--sal-dark);}

/* CONTACT CARDS */
.contact-card{
  background:#fff;border:1px solid #ece6d6;border-radius:12px;
  padding:1rem 1.15rem;margin-bottom:.85rem;
  box-shadow:0 4px 14px rgba(17,20,31,.05);
}
.contact-card-row{
  display:flex;justify-content:space-between;align-items:flex-start;
  gap:.8rem;flex-wrap:wrap;
}
.contact-name{font-size:.95rem;font-weight:900;color:var(--sal-dark);}
.contact-meta{font-size:.8rem;color:#687083;margin-top:.2rem;}
.contact-pills{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;margin-top:.3rem;}
.pill-status{
  background:#eef6f3;color:#225a49;
  font-size:.73rem;font-weight:800;padding:.26rem .6rem;border-radius:999px;
}
.btn-wa{
  display:inline-flex;align-items:center;gap:.4rem;
  background:#2f7a63;color:#fff;text-decoration:none;
  font-size:.78rem;font-weight:900;padding:.38rem .75rem;border-radius:999px;
  transition:background .2s,transform .2s;white-space:nowrap;
}
.btn-wa:hover{background:#1f5c49;color:#fff;transform:translateY(-1px);}
.contact-footer{margin-top:.65rem;font-size:.79rem;color:#6b7280;line-height:1.6;}
.contact-footer strong{color:#364152;}

/* EMPTY STATE */
.pf-empty{text-align:center;padding:2.5rem 0;color:#aaa;font-size:.92rem;}
.pf-empty i{font-size:2.5rem;display:block;margin-bottom:.6rem;opacity:.4;}

/* FLASH */
.pf-flash{
  border-radius:10px;padding:.85rem 1.1rem;margin-bottom:1.2rem;
  font-size:.9rem;font-weight:700;
}
.pf-flash.success{background:#d1fae5;color:#065f46;border-left:4px solid #10b981;}
.pf-flash.danger {background:#fee2e2;color:#991b1b;border-left:4px solid #ef4444;}
.pf-flash.warning{background:#fef3c7;color:#92400e;border-left:4px solid #f59e0b;}

/* FOOTER */
.pf-footer{
  text-align:center;padding:2rem 1rem;
  font-size:.82rem;color:#9a9da6;
  border-top:1px solid #e8e4da;
  margin-top:3rem;
}
.pf-footer a{color:var(--sal-dark);font-weight:700;text-decoration:none;}
.pf-footer a:hover{color:var(--sal-gold);}
</style>
</head>
<body>

<!-- NAV -->
<nav id="pfNav">
  <div class="container d-flex align-items-center justify-content-between">
    <a class="navbar-brand" href="/Tumaini-Connect/">Tumaini <span>Connect</span></a>
    <a class="nav-back" href="/Tumaini-Connect/"><i class="bi bi-arrow-left"></i> Back to Home</a>
  </div>
</nav>

<!-- HERO -->
<div class="pf-hero">
  <div class="container position-relative">
    <div class="pf-hero-sup"><i class="bi bi-building-check me-1"></i> Station Portal</div>
    <h1>Station Report Form</h1>
    <p>Verify your station to submit reports, track interest submissions, and view church follow-up contacts.</p>
  </div>
</div>

<!-- MAIN -->
<div class="container" style="max-width:720px;padding-top:2.5rem;padding-bottom:3rem;">

  <?php if ($flash): ?>
    <div class="pf-flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>

  <?php if (!$stationCenter): ?>
    <!-- ========== LOGIN CARD ========== -->
    <div class="pf-card">
      <div style="background:linear-gradient(135deg,var(--sal-dark),var(--sal-dark2));padding:1.5rem;color:#fff;">
        <h5 style="margin:0;font-weight:900;font-size:1.1rem;"><i class="bi bi-shield-lock-fill me-2" style="color:var(--sal-gold);"></i>Verify Your Station</h5>
        <p style="margin:.4rem 0 0;font-size:.83rem;color:rgba(255,255,255,.65);">Enter your station name and coordinator phone number to continue.</p>
      </div>
      <div class="pf-login-wrap">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="station_login">

          <div class="mb-3">
            <label class="field-label">Station Name</label>
            <input type="text" class="form-control" name="station_name" required
                   placeholder="e.g. Mabamba SDA" autocomplete="off">
          </div>
          <div class="mb-4">
            <label class="field-label">Coordinator Phone (password)</label>
            <input type="password" class="form-control" name="phone_password" required
                   placeholder="Enter coordinator phone number">
          </div>
          <button type="submit" class="btn-pf-primary">
            <i class="bi bi-shield-check me-1"></i> Verify Station
          </button>
        </form>
        <div style="margin-top:1.2rem;text-align:center;font-size:.83rem;color:#9a9da6;">
          Not registered yet? <a href="/Tumaini-Connect/" style="color:var(--sal-dark);font-weight:800;text-decoration:none;" class="tc-home-link">Register on the home page</a>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- ========== VERIFIED CARD ========== -->
    <div class="pf-card">

      <!-- Station Badge -->
      <div class="pf-station-badge">
        <div>
          <div class="name"><i class="bi bi-building-check me-1" style="color:var(--sal-gold);"></i><?= e($stationCenter['name']) ?></div>
          <div class="meta"><?= e((string)$stationCenter['region']) ?> &bull; <?= e((string)$stationCenter['district']) ?> &bull; <?= e((string)$stationCenter['ward']) ?></div>
        </div>
        <form method="post" class="m-0">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="station_logout">
          <button type="submit" class="pf-switch-btn"><i class="bi bi-arrow-left-right me-1"></i>Switch Station</button>
        </form>
      </div>

      <!-- Tabs -->
      <div class="pf-tabs" id="pfTabs">
        <button class="pf-tab-btn active" data-pane="pf-submit"><i class="bi bi-pencil-square me-1"></i>Submit Report</button>
        <button class="pf-tab-btn" data-pane="pf-history"><i class="bi bi-clock-history me-1"></i>Previous Reports</button>
        <button class="pf-tab-btn" data-pane="pf-followups"><i class="bi bi-whatsapp me-1"></i>Follow-up Contacts</button>
      </div>

      <!-- Pane: Submit -->
      <div id="pf-submit" class="pf-pane active">
        <form method="post" class="row g-3" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="submit_report">

          <div class="col-12">
            <div class="pf-section-label"><i class="bi bi-person me-1"></i>Person Info</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Full Name <span style="color:#ef4444;">*</span></label>
            <input type="text" class="form-control" name="full_name" required placeholder="Enter full name">
          </div>
          <div class="col-md-3">
            <label class="form-label">Gender <span style="color:#ef4444;">*</span></label>
            <select class="form-select" name="gender" required>
              <option value="">Select</option>
              <option>Male</option>
              <option>Female</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Age</label>
            <input type="number" min="1" max="120" class="form-control" name="age" placeholder="e.g. 35">
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone <span style="color:#ef4444;">*</span></label>
            <input type="text" class="form-control" name="phone" required placeholder="0712 345 678">
          </div>
          <div class="col-md-6">
            <label class="form-label">Request Type <span style="color:#ef4444;">*</span></label>
            <select class="form-select" name="request_type" required>
              <option value="">Select</option>
              <option value="baptism">Baptism</option>
              <option value="bible_study">Bible Study</option>
              <option value="prayer">Prayer</option>
              <option value="visit">Visit</option>
              <option value="church_connection">Church Connection</option>
            </select>
          </div>

          <div class="col-12">
            <div class="pf-section-label"><i class="bi bi-geo-alt me-1"></i>Location</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Region</label>
            <input type="text" class="form-control" name="region" value="<?= e((string)($stationCenter['region'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">District</label>
            <input type="text" class="form-control" name="district" value="<?= e((string)($stationCenter['district'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Ward</label>
            <input type="text" class="form-control" name="ward" value="<?= e((string)($stationCenter['ward'] ?? '')) ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Additional Notes</label>
            <textarea class="form-control" name="additional_notes" rows="3" placeholder="Any extra details about this person..."></textarea>
          </div>
          <div class="col-12">
            <button type="submit" class="btn-pf-primary">
              <i class="bi bi-send me-1"></i> Submit Report
            </button>
          </div>
        </form>
      </div>

      <!-- Pane: History -->
      <div id="pf-history" class="pf-pane">
        <?php if (count($reports) === 0): ?>
          <div class="pf-empty"><i class="bi bi-inbox"></i>No reports submitted yet.</div>
        <?php else: ?>
          <?php foreach ($reports as $row): ?>
            <div class="report-card">
              <div class="report-card-header">
                <strong><?= e($row['full_name']) ?></strong>
                <span class="report-badge"><?= e((string)ucwords(str_replace('_',' ',(string)$row['request_type']))) ?></span>
              </div>
              <div class="d-flex flex-wrap gap-2" style="font-size:.8rem;color:#687083;">
                <span><i class="bi bi-telephone me-1"></i><?= e($row['phone']) ?></span>
                <span><i class="bi bi-circle-fill me-1" style="font-size:.5rem;opacity:.5;"></i><?= e($row['status']) ?></span>
                <span><i class="bi bi-calendar3 me-1"></i><?= e($row['created_at']) ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Pane: Follow-up Contacts -->
      <div id="pf-followups" class="pf-pane">
        <?php if (count($followupQueue) === 0): ?>
          <div class="pf-empty"><i class="bi bi-people"></i>No tagged follow-up contacts for this church yet.</div>
        <?php else: ?>
          <?php foreach ($followupQueue as $row): ?>
            <div class="contact-card">
              <div class="contact-card-row">
                <div>
                  <div class="contact-name"><?= e((string)$row['full_name']) ?></div>
                  <div class="contact-meta">
                    <?= e((string)ucwords(str_replace('_',' ',(string)$row['request_type']))) ?>
                    &middot; Assigned to <strong><?= e((string)$row['assigned_name']) ?></strong>
                  </div>
                  <div class="contact-pills">
                    <span class="pill-status"><?= e((string)($row['followup_status'] ?: $row['interest_status'])) ?></span>
                  </div>
                </div>
                <a href="<?= e(wa_link((string)$row['phone'])) ?>" target="_blank" rel="noopener" class="btn-wa">
                  <i class="bi bi-whatsapp"></i> <?= e((string)$row['phone']) ?>
                </a>
              </div>
              <?php if ($row['followup_date'] || $row['remarks']): ?>
                <div class="contact-footer">
                  <?php if ($row['followup_date']): ?><strong>Follow-up Date:</strong> <?= e((string)$row['followup_date']) ?><br><?php endif; ?>
                  <?php if ($row['remarks']): ?><strong>Remarks:</strong> <?= e((string)$row['remarks']) ?><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div><!-- /pf-card -->
  <?php endif; ?>

</div><!-- /container -->

<div class="pf-footer">
  &copy; <?= date('Y') ?> <a href="/Tumaini-Connect/">Tumaini Connect</a> &mdash; Tanzania Mission Outreach
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  /* Tab switching */
  var btns = document.querySelectorAll('#pfTabs .pf-tab-btn');
  btns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      btns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var paneId = btn.getAttribute('data-pane');
      document.querySelectorAll('.pf-pane').forEach(function (p) {
        p.classList.toggle('active', p.id === paneId);
      });
    });
  });
})();
</script>
</body>
</html>
