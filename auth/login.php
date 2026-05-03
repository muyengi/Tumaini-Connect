<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (isset($_SESSION['user'])) {
  $role = (string) ($_SESSION['user']['role'] ?? '');
  header('Location: ' . role_home($role));
    exit;
}

if (is_post()) {
    if (!verify_csrf()) {
        set_flash('danger', 'Invalid CSRF token.');
        header('Location: login.php');
        exit;
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (login_user($conn, $email, $password)) {
        set_flash('success', 'Welcome back to Tumaini Connect.');
      $role = (string) ($_SESSION['user']['role'] ?? '');
      header('Location: ' . role_home($role));
        exit;
    }

    set_flash('danger', 'Invalid login credentials.');
    header('Location: login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Tumaini Connect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="/Tumaini-Connect/assets/vendors/mdi/css/materialdesignicons.min.css" rel="stylesheet">
    <link href="/Tumaini-Connect/assets/vendors/css/vendor.bundle.base.css" rel="stylesheet">
    <link href="/Tumaini-Connect/assets/css/style.css" rel="stylesheet">
    <style>
      body {
        font-family: "Nunito", sans-serif;
        min-height: 100vh;
        background:
          radial-gradient(circle at top left, rgba(98, 52, 183, 0.16), transparent 34%),
          radial-gradient(circle at right center, rgba(47, 122, 99, 0.18), transparent 36%),
          linear-gradient(135deg, #17172a 0%, #22223b 52%, #202f37 100%);
      }

      .tc-login-shell {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
      }

      .tc-login-grid {
        width: min(1080px, 100%);
        display: grid;
        grid-template-columns: 1.1fr minmax(320px, 430px);
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 28px 80px rgba(7, 11, 24, 0.35);
        background: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(10px);
      }

      .tc-login-aside {
        padding: 3rem;
        color: #fff;
        background:
          linear-gradient(145deg, rgba(18, 20, 38, 0.9), rgba(34, 34, 59, 0.72)),
          linear-gradient(120deg, rgba(249, 191, 63, 0.12), rgba(47, 122, 99, 0.12));
      }

      .tc-login-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: #f9bf3f;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 0.75rem;
        font-weight: 800;
        margin-bottom: 1rem;
      }

      .tc-login-title {
        font-size: clamp(2rem, 5vw, 3.3rem);
        font-weight: 900;
        line-height: 1.05;
        margin-bottom: 1rem;
      }

      .tc-login-copy {
        max-width: 33rem;
        color: rgba(255, 255, 255, 0.8);
        font-size: 1rem;
        line-height: 1.7;
        margin-bottom: 1.8rem;
      }

      .tc-login-points {
        display: grid;
        gap: 0.9rem;
        margin: 0;
        padding: 0;
        list-style: none;
      }

      .tc-login-points li {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        color: rgba(255, 255, 255, 0.88);
      }

      .tc-login-points i {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(249, 191, 63, 0.14);
        color: #f9bf3f;
        font-size: 1rem;
        flex: 0 0 auto;
      }

      .tc-login-card {
        background: #fff;
        padding: 2.5rem 2rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .tc-login-brand {
        margin-bottom: 1.25rem;
      }

      .tc-login-brand h3 {
        font-weight: 900;
        margin-bottom: 0.2rem;
        color: #17172a;
      }

      .tc-login-brand p,
      .tc-login-meta,
      .tc-login-footer {
        color: #6c7286;
      }

      .tc-login-form .form-control {
        min-height: 52px;
        border-radius: 14px;
        border-color: #dde1ea;
        padding-inline: 1rem;
      }

      .tc-login-form .form-control:focus {
        border-color: #2f7a63;
        box-shadow: 0 0 0 0.18rem rgba(47, 122, 99, 0.16);
      }

      .tc-login-btn {
        min-height: 52px;
        border-radius: 14px;
        border: 0;
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.6px;
        background: linear-gradient(135deg, #2f7a63, #225a49);
      }

      .tc-login-btn:hover,
      .tc-login-btn:focus {
        background: linear-gradient(135deg, #3a8f74, #2a6f5a);
      }

      .tc-login-links {
        display: flex;
        flex-wrap: wrap;
        gap: 0.85rem 1.2rem;
        font-size: 0.86rem;
      }

      .tc-login-links a {
        color: #2f7a63;
        font-weight: 800;
        text-decoration: none;
      }

      .tc-login-links a:hover {
        color: #225a49;
      }

      @media (max-width: 991.98px) {
        .tc-login-grid {
          grid-template-columns: 1fr;
        }

        .tc-login-aside {
          padding: 2.2rem 1.6rem;
        }

        .tc-login-card {
          padding: 2rem 1.3rem;
        }
      }
    </style>
</head>
<body>
  <div class="tc-login-shell">
    <div class="tc-login-grid">
      <section class="tc-login-aside">
        <span class="tc-login-kicker"><i class="mdi mdi-crosshairs-gps"></i> Tumaini Connect</span>
        <h1 class="tc-login-title">Mission outreach coordination for every level.</h1>
        <p class="tc-login-copy">
          Sign in with your account to manage unions, conferences, stations, interests, reports and follow-up work across the Tumaini Connect network.
        </p>
        <ul class="tc-login-points">
          <li><i class="mdi mdi-shield-check"></i><span>Admin dashboards are reserved for Tanzania, Union and Conference administrators only.</span></li>
          <li><i class="mdi mdi-account-group"></i><span>Coordinators and follow-up officers are redirected to their working modules, not the dashboard.</span></li>
          <li><i class="mdi mdi-chart-line"></i><span>Track attendance, people, reports and outreach performance from one platform.</span></li>
        </ul>
      </section>

      <section class="tc-login-card">
        <div class="tc-login-brand">
          <h3>Tumaini Connect</h3>
          <p class="mb-0">Tanzania Outreach Platform</p>
        </div>

          <?php
          $flash = get_flash();
          if ($flash):
          ?>
          <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
              <?= e($flash['message']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
          <?php endif; ?>

          <h5 class="fw-bold mb-2 text-dark">Sign in</h5>
          <p class="tc-login-meta mb-4">Use your email and password to continue.</p>

            <form method="post" class="tc-login-form" novalidate>
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <div class="form-group mb-3">
                <input type="email" class="form-control form-control-lg" name="email"
                       placeholder="Email address" required autocomplete="username">
              </div>
              <div class="form-group mb-3">
                <input type="password" class="form-control form-control-lg" name="password"
                       placeholder="Password" required autocomplete="current-password">
              </div>
              <div class="mt-3 d-grid">
                <button type="submit" class="btn btn-block btn-lg tc-login-btn text-white">
                    SIGN IN
                </button>
              </div>
            </form>

            <div class="tc-login-footer mt-4 small">
                Default: admin@tumaini.or.tz / Admin@123
            </div>
            <div class="tc-login-links mt-4">
                <a href="/Tumaini-Connect/">Back to Home</a>
                <a href="/Tumaini-Connect/modules/interests/public_form.php">Public Interest Form</a>
            </div>
        </section>
    </div>
</div>
<script src="/Tumaini-Connect/assets/vendors/js/vendor.bundle.base.js"></script>
</body>
</html>
