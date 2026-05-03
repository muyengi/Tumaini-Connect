<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

// ── Handle POST ───────────────────────────────────────────────────────────────
if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid form token.');
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim((string) ($_POST['name']  ?? ''));
        $email   = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone   = normalize_phone($_POST['phone'] ?? '');
        $newPass = (string) ($_POST['new_password']     ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($name === '') {
            set_flash('danger', 'Full name is required.');
            header('Location: index.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Please enter a valid email address.');
            header('Location: index.php');
            exit;
        }

        // Check email uniqueness (excluding self)
        $chk = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $chk->bind_param('si', $email, $userId);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            set_flash('danger', 'That email is already used by another account.');
            header('Location: index.php');
            exit;
        }

        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                set_flash('danger', 'New password must be at least 6 characters.');
                header('Location: index.php');
                exit;
            }
            if ($newPass !== $confirm) {
                set_flash('danger', 'Passwords do not match.');
                header('Location: index.php');
                exit;
            }
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $phoneVal = $phone ?: null;
            $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $name, $email, $phoneVal, $hash, $userId);
        } else {
            $phoneVal = $phone ?: null;
            $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
            $stmt->bind_param('sssi', $name, $email, $phoneVal, $userId);
        }

        if ($stmt->execute()) {
            // Refresh session
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone ?: null;
            set_flash('success', 'Profile updated successfully.');
        } else {
            set_flash('danger', 'Could not update profile. Please try again.');
        }

        header('Location: index.php');
        exit;
    }
}

// Ensure phone column exists (idempotent migration)
$conn->query('ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL DEFAULT NULL AFTER email');

// ── Load fresh user data ──────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT u.id, u.name, u.email, u.phone, u.role, u.union_id, u.conference_id, u.created_at,
            un.name union_name, c.name conference_name
     FROM users u
     LEFT JOIN unions un ON u.union_id = un.id
     LEFT JOIN conferences c  ON u.conference_id = c.id
     WHERE u.id = ? LIMIT 1'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

if (!$profile) {
    http_response_code(404);
    exit('User not found.');
}

$pageTitle = 'My Profile';
$initials  = strtoupper(mb_substr(trim((string) $profile['name']), 0, 1));
$memberSince = date('d M Y', strtotime((string) $profile['created_at']));
include __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Profile page-scoped styles ─────────────────────────────── */
.pf-hero {
    border: 0;
    border-radius: 20px;
    background: linear-gradient(120deg, #1b1b2e 0%, #252540 55%, #2f5f5c 100%);
    color: #fff;
    overflow: hidden;
    position: relative;
}
.pf-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.pf-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f9bf3f, #e8a020);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 800; color: #1b1b2e;
    flex-shrink: 0;
    box-shadow: 0 4px 16px rgba(0,0,0,.35);
    border: 3px solid rgba(255,255,255,.18);
}
.pf-stat-pill {
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 999px;
    padding: .3rem .85rem;
    font-size: .78rem;
    font-weight: 700;
    backdrop-filter: blur(6px);
    display: inline-flex; align-items: center; gap: .4rem;
}
.pf-stat-pill.gold { background: rgba(249,191,63,.18); border-color: rgba(249,191,63,.4); color: #f9bf3f; }
.pf-stat-pill.sms-on  { background: rgba(40,167,69,.18); border-color: rgba(40,167,69,.4); color: #6fe092; }
.pf-stat-pill.sms-off { background: rgba(255,255,255,.07); border-color: rgba(255,255,255,.12); color: rgba(255,255,255,.6); }

/* Section divider with label */
.pf-section-label {
    display: flex; align-items: center; gap: .75rem;
    margin: 1.5rem 0 1rem;
}
.pf-section-label span {
    font-size: .68rem; text-transform: uppercase; letter-spacing: 1.2px;
    font-weight: 800; color: #9191a8; white-space: nowrap;
}
.pf-section-label::after {
    content: ''; flex: 1; height: 1px; background: #eceaf5;
}

/* Input icon prefix */
.pf-input-icon { position: relative; }
.pf-input-icon .mdi {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: #9191a8; font-size: 1.05rem; pointer-events: none; z-index: 5;
}
.pf-input-icon input { padding-left: 2.4rem; }

/* Info list */
.pf-info-list { list-style: none; padding: 0; margin: 0; }
.pf-info-list li {
    display: flex; justify-content: space-between; align-items: center;
    padding: .7rem 0;
    border-bottom: 1px solid #f0eefa;
    font-size: .88rem;
    gap: .5rem;
}
.pf-info-list li:last-child { border-bottom: 0; }
.pf-info-list .pf-info-label { color: #9191a8; font-weight: 700; font-size: .76rem; text-transform: uppercase; letter-spacing: .5px; }
.pf-info-list .pf-info-value { font-weight: 600; color: #1b1b2e; text-align: right; }

/* Security tips */
.pf-tip {
    display: flex; align-items: flex-start; gap: .65rem;
    padding: .55rem 0; border-bottom: 1px solid #f0eefa; font-size: .85rem; color: #5d5d70;
}
.pf-tip:last-child { border-bottom: 0; }
.pf-tip .mdi { color: #2f7a63; font-size: 1rem; margin-top: .1rem; flex-shrink: 0; }

/* Save button glow */
.pf-save-btn {
    background: linear-gradient(135deg, #2f7a63, #225a49);
    border: 0; color: #fff; padding: .65rem 2rem;
    border-radius: 12px; font-weight: 700; font-size: .95rem;
    box-shadow: 0 4px 14px rgba(47,122,99,.35);
    transition: box-shadow .2s, transform .15s;
}
.pf-save-btn:hover { box-shadow: 0 6px 20px rgba(47,122,99,.5); transform: translateY(-1px); color:#fff; }
.pf-save-btn:disabled { opacity: .55; pointer-events: none; }

/* Flash banner */
.pf-flash {
    border-radius: 12px; font-weight: 600; font-size: .9rem;
    border: 0; display: flex; align-items: center; gap: .6rem;
}
</style>

<?php $flash = get_flash(); ?>

<!-- ═══════════════════════════ HERO ═══════════════════════════ -->
<div class="card pf-hero mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center gap-4">

            <!-- Avatar -->
            <div class="pf-avatar"><?= e($initials) ?></div>

            <!-- Name + meta -->
            <div class="flex-grow-1">
                <p class="tc-dash-hero-kicker mb-1" style="color:rgba(255,255,255,.65);">Account Settings</p>
                <h3 class="mb-2 fw-800" style="font-size:1.55rem;"><?= e((string) $profile['name']) ?></h3>
                <div class="d-flex flex-wrap gap-2">
                    <span class="pf-stat-pill"><i class="mdi mdi-shield-account"></i><?= e(role_label((string) $profile['role'])) ?></span>
                    <?php if ($profile['conference_name']): ?>
                        <span class="pf-stat-pill gold"><i class="mdi mdi-map-marker"></i><?= e((string) $profile['conference_name']) ?></span>
                    <?php elseif ($profile['union_name']): ?>
                        <span class="pf-stat-pill gold"><i class="mdi mdi-domain"></i><?= e((string) $profile['union_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($profile['phone'])): ?>
                        <span class="pf-stat-pill sms-on"><i class="mdi mdi-bell-ring"></i>SMS Active</span>
                    <?php else: ?>
                        <span class="pf-stat-pill sms-off"><i class="mdi mdi-bell-off"></i>No SMS</span>
                    <?php endif; ?>
                    <span class="pf-stat-pill" style="color:rgba(255,255,255,.7);">
                        <i class="mdi mdi-calendar-check"></i>Since <?= e($memberSince) ?>
                    </span>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════════════════════ BODY ═══════════════════════════ -->
<div class="row g-4 align-items-start">

    <!-- ── LEFT: Update Form ──────────────────────────────────── -->
    <div class="col-xl-7 col-lg-7">
        <div class="card tc-card">
            <div class="card-body p-4">

                <!-- Flash -->
                <?php if ($flash): ?>
                    <div class="alert pf-flash alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'warning' ? 'warning' : 'danger') ?> mb-4">
                        <i class="mdi mdi-<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?> fs-5"></i>
                        <?= e($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate id="profileForm">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action"     value="update_profile">

                    <!-- Personal Info section -->
                    <div class="pf-section-label"><span>Personal Information</span></div>

                    <div class="row g-3 mb-1">
                        <div class="col-sm-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="pf-input-icon">
                                <i class="mdi mdi-account-outline"></i>
                                <input type="text" class="form-control" name="name"
                                       value="<?= e((string) $profile['name']) ?>" required
                                       placeholder="Your full name">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <div class="pf-input-icon">
                                <i class="mdi mdi-email-outline"></i>
                                <input type="email" class="form-control" name="email"
                                       value="<?= e((string) $profile['email']) ?>" required
                                       placeholder="you@example.com">
                            </div>
                        </div>
                    </div>

                    <div class="mb-1 mt-3">
                        <label class="form-label">
                            Phone Number
                            <span class="text-muted fw-normal" style="text-transform:none;letter-spacing:0;">— for SMS notifications</span>
                        </label>
                        <div class="pf-input-icon">
                            <i class="mdi mdi-phone-outline"></i>
                            <input type="tel" class="form-control" name="phone"
                                   value="<?= e((string) ($profile['phone'] ?? '')) ?>"
                                   placeholder="e.g. 0712 345 678">
                        </div>
                        <div class="form-text">
                            <i class="mdi mdi-information-outline me-1"></i>
                            Add your number to receive attendance reports and interest alerts via SMS.
                        </div>
                    </div>

                    <!-- Password section -->
                    <div class="pf-section-label mt-1"><span>Change Password</span></div>
                    <p class="text-muted mb-3" style="font-size:.84rem;">Leave both fields blank to keep your current password.</p>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-right:0;background:#fff;border-color:#dbd8ea;">
                                    <i class="mdi mdi-lock-outline text-muted"></i>
                                </span>
                                <input type="password" class="form-control" name="new_password"
                                       id="newPassInput" minlength="6"
                                       placeholder="Min 6 characters"
                                       style="border-left:0;border-right:0;">
                                <button type="button" class="btn btn-outline-secondary"
                                        style="border-color:#dbd8ea;border-left:0;" id="toggleNewPass"
                                        tabindex="-1">
                                    <i class="mdi mdi-eye" id="toggleNewPassIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text" style="border-right:0;background:#fff;border-color:#dbd8ea;">
                                    <i class="mdi mdi-lock-check-outline text-muted"></i>
                                </span>
                                <input type="password" class="form-control" name="confirm_password"
                                       id="confirmPassInput" minlength="6"
                                       placeholder="Repeat password"
                                       style="border-left:0;border-right:0;">
                                <button type="button" class="btn btn-outline-secondary"
                                        style="border-color:#dbd8ea;border-left:0;" id="toggleConfirmPass"
                                        tabindex="-1">
                                    <i class="mdi mdi-eye" id="toggleConfirmPassIcon"></i>
                                </button>
                            </div>
                            <div id="passMismatch" class="text-danger small mt-1 d-none">
                                <i class="mdi mdi-alert-circle-outline me-1"></i>Passwords do not match.
                            </div>
                            <div id="passMatch" class="text-success small mt-1 d-none">
                                <i class="mdi mdi-check-circle-outline me-1"></i>Passwords match.
                            </div>
                        </div>
                    </div>

                    <!-- Password strength bar -->
                    <div id="strengthWrap" class="mt-2 d-none">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div style="flex:1;height:5px;border-radius:99px;background:#eee;overflow:hidden;">
                                <div id="strengthBar" style="height:100%;width:0;border-radius:99px;transition:width .3s,background .3s;"></div>
                            </div>
                            <span id="strengthLabel" class="fw-700" style="font-size:.72rem;min-width:44px;"></span>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="mt-4 d-flex align-items-center gap-3 flex-wrap">
                        <button type="submit" class="btn pf-save-btn" id="saveBtn">
                            <i class="mdi mdi-content-save me-2"></i>Save Changes
                        </button>
                        <a href="javascript:history.back()" class="btn btn-link text-muted" style="font-size:.88rem;">
                            Cancel
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Info + Security ─────────────────────────────── -->
    <div class="col-xl-5 col-lg-5">

        <!-- Account Info card -->
        <div class="card tc-card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                          style="width:30px;height:30px;background:rgba(47,122,99,.12);">
                        <i class="mdi mdi-information text-success" style="font-size:.95rem;"></i>
                    </span>
                    Account Details
                </h5>
                <div class="pf-section-label mt-3"><span>Overview</span></div>
                <ul class="pf-info-list">
                    <li>
                        <span class="pf-info-label"><i class="mdi mdi-shield-account me-1"></i>Role</span>
                        <span class="pf-info-value">
                            <span class="badge" style="background:rgba(47,122,99,.12);color:#2f7a63;font-size:.8rem;font-weight:700;">
                                <?= e(role_label((string) $profile['role'])) ?>
                            </span>
                        </span>
                    </li>
                    <?php if ($profile['union_name']): ?>
                    <li>
                        <span class="pf-info-label"><i class="mdi mdi-domain me-1"></i>Union</span>
                        <span class="pf-info-value"><?= e((string) $profile['union_name']) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($profile['conference_name']): ?>
                    <li>
                        <span class="pf-info-label"><i class="mdi mdi-map-marker me-1"></i>Conference</span>
                        <span class="pf-info-value"><?= e((string) $profile['conference_name']) ?></span>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="pf-info-label"><i class="mdi mdi-email me-1"></i>Email</span>
                        <span class="pf-info-value" style="word-break:break-all;"><?= e((string) $profile['email']) ?></span>
                    </li>
                    <li>
                        <span class="pf-info-label"><i class="mdi mdi-phone me-1"></i>Phone / SMS</span>
                        <span class="pf-info-value">
                            <?php if (!empty($profile['phone'])): ?>
                                <span class="badge bg-success bg-opacity-10 text-success fw-700" style="font-size:.8rem;">
                                    <i class="mdi mdi-check-circle me-1"></i><?= e((string) $profile['phone']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary fw-700" style="font-size:.8rem;">
                                    <i class="mdi mdi-bell-off me-1"></i>Not set
                                </span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li>
                        <span class="pf-info-label"><i class="mdi mdi-calendar me-1"></i>Member Since</span>
                        <span class="pf-info-value"><?= e($memberSince) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Security Tips card -->
        <div class="card tc-card">
            <div class="card-body p-4">
                <h5 class="mb-0 d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle"
                          style="width:30px;height:30px;background:rgba(249,191,63,.15);">
                        <i class="mdi mdi-shield-lock" style="color:#e8a020;font-size:.95rem;"></i>
                    </span>
                    Security Tips
                </h5>
                <div class="pf-section-label mt-3"><span>Best Practices</span></div>
                <div class="pf-tip"><i class="mdi mdi-key-variant"></i>Use a strong password with letters, numbers &amp; symbols.</div>
                <div class="pf-tip"><i class="mdi mdi-account-lock"></i>Never share your login credentials with anyone.</div>
                <div class="pf-tip"><i class="mdi mdi-cellphone-message"></i>Add your phone number to receive SMS alerts.</div>
                <div class="pf-tip"><i class="mdi mdi-logout-variant"></i>Log out when finished on shared or public devices.</div>
                <div class="pf-tip"><i class="mdi mdi-update"></i>Change your password regularly for better security.</div>
            </div>
        </div>

    </div><!-- /right col -->
</div>

<script>
(function () {
    /* ── Password show/hide ─────────────────────────────────── */
    function initToggle(inputId, btnId, iconId) {
        var inp  = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        document.getElementById(btnId).addEventListener('click', function () {
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            icon.className = show ? 'mdi mdi-eye-off' : 'mdi mdi-eye';
        });
    }
    initToggle('newPassInput',     'toggleNewPass',     'toggleNewPassIcon');
    initToggle('confirmPassInput', 'toggleConfirmPass', 'toggleConfirmPassIcon');

    /* ── Password strength ──────────────────────────────────── */
    var newPass     = document.getElementById('newPassInput');
    var confirmPass = document.getElementById('confirmPassInput');
    var mismatch    = document.getElementById('passMismatch');
    var matchOk     = document.getElementById('passMatch');
    var saveBtn     = document.getElementById('saveBtn');
    var strengthWrap = document.getElementById('strengthWrap');
    var strengthBar  = document.getElementById('strengthBar');
    var strengthLabel= document.getElementById('strengthLabel');

    function calcStrength(pw) {
        var s = 0;
        if (pw.length >= 6)  s++;
        if (pw.length >= 10) s++;
        if (/[A-Z]/.test(pw)) s++;
        if (/[0-9]/.test(pw)) s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        return s;
    }

    var levels = [
        { w: '20%', bg: '#dc3545', label: 'Weak'    },
        { w: '40%', bg: '#fd7e14', label: 'Fair'    },
        { w: '60%', bg: '#ffc107', label: 'Good'    },
        { w: '80%', bg: '#20c997', label: 'Strong'  },
        { w:'100%', bg: '#198754', label: 'Great!'  },
    ];

    newPass.addEventListener('input', function () {
        var val = this.value;
        if (val === '') {
            strengthWrap.classList.add('d-none');
        } else {
            strengthWrap.classList.remove('d-none');
            var idx = Math.max(0, Math.min(calcStrength(val) - 1, 4));
            strengthBar.style.width      = levels[idx].w;
            strengthBar.style.background = levels[idx].bg;
            strengthLabel.textContent    = levels[idx].label;
            strengthLabel.style.color    = levels[idx].bg;
        }
        checkMatch();
    });

    /* ── Match validation ───────────────────────────────────── */
    function checkMatch() {
        var nv = newPass.value, cv = confirmPass.value;
        if (cv === '') {
            mismatch.classList.add('d-none');
            matchOk.classList.add('d-none');
            saveBtn.disabled = false;
            return;
        }
        var bad = nv !== cv;
        mismatch.classList.toggle('d-none', !bad);
        matchOk.classList.toggle('d-none',  bad);
        saveBtn.disabled = bad;
    }

    confirmPass.addEventListener('input', checkMatch);
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
