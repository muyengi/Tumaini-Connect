<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/functions.php";
$user = $_SESSION["user"] ?? null;
$reportVerified   = !empty($_SESSION['report_center_id']);
$reportCenterName = (string) ($_SESSION['report_center_name'] ?? '');

$stats = ["stations" => 0, "interests" => 0, "todayVisitors" => 0, "todayAttendance" => 0];
$r = $conn->query("SELECT COUNT(*) c FROM centers");      if ($r) $stats["stations"]        = (int)$r->fetch_assoc()["c"];
$r = $conn->query("SELECT COUNT(*) c FROM interests");    if ($r) $stats["interests"]       = (int)$r->fetch_assoc()["c"];
$r = $conn->query("SELECT COALESCE(SUM(elderly_men+elderly_women+children_boys+children_girls),0) t FROM meeting_reports WHERE report_date = CURDATE()");
if ($r) $stats["todayVisitors"]   = (int)$r->fetch_assoc()["t"];
$r = $conn->query("SELECT COALESCE(SUM(elderly_men+elderly_women+children_boys+children_girls+members_attended),0) t FROM meeting_reports WHERE report_date = CURDATE()");
if ($r) $stats["todayAttendance"] = (int)$r->fetch_assoc()["t"];

$regUnions      = $conn->query('SELECT id, name FROM unions ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$regConferences = $conn->query('SELECT id, union_id, name FROM conferences ORDER BY name')->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tumaini Connect &mdash; Seventh-day Adventist Church of Tanzania</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root {
  --sal-gold:   #f9bf3f;
  --sal-dark:   #1b1b2e;
  --sal-dark2:  #252540;
  --sal-accent: #e8a020;
  --sal-light:  #f8f7f4;
  --sal-text:   #6c757d;
}
* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: "Nunito", sans-serif; color: #444; margin: 0; }

/* ---- NAVBAR ---- */
#mainNav {
  position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
  background: transparent;
  transition: background .35s ease, box-shadow .35s ease;
  padding: .8rem 0;
}
#mainNav.scrolled {
  background: var(--sal-dark);
  box-shadow: 0 4px 20px rgba(0,0,0,.35);
  padding: .5rem 0;
}
#mainNav .navbar-brand {
  font-size: 1.45rem; font-weight: 900; color: #fff; letter-spacing: .5px;
}
#mainNav .navbar-brand span { color: var(--sal-gold); }
#mainNav .nav-link {
  color: rgba(255,255,255,.85) !important;
  font-weight: 700; font-size: .92rem; letter-spacing: .6px;
  text-transform: uppercase; padding: .4rem .9rem !important;
  transition: color .2s;
}
#mainNav .nav-link:hover { color: var(--sal-gold) !important; }
#mainNav .btn-nav {
  background: var(--sal-gold); color: var(--sal-dark) !important;
  border-radius: 30px; padding: .4rem 1.3rem !important;
  font-weight: 800; transition: background .2s, transform .2s;
}
#mainNav .btn-nav:hover { background: var(--sal-accent); transform: translateY(-1px); }
.navbar-toggler { border: 2px solid rgba(255,255,255,.5); }
.navbar-toggler-icon { filter: invert(1); }

/* ---- HERO ---- */
#hero {
  position: relative;
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center; text-align: center;
  color: #fff;
  overflow: hidden;
}
#hero-bg, #hero-bg-next {
  position: absolute; inset: 0;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  transition: opacity 1.2s ease;
}
#hero-bg-next { opacity: 0; }
#hero-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(to bottom, rgba(15,12,35,.75) 0%, rgba(27,27,46,.88) 100%);
  z-index: 1;
}
#hero::after {
  content: "";
  position: absolute; inset: 0;
  background: radial-gradient(ellipse at 60% 40%, rgba(249,191,63,.08), transparent 60%);
  pointer-events: none;
  z-index: 2;
}
.hero-sup {
  display: inline-block;
  font-size: .85rem; font-weight: 800; letter-spacing: 4px;
  text-transform: uppercase; color: var(--sal-gold);
  margin-bottom: 1.2rem;
}
.hero-title {
  font-size: clamp(2.2rem, 6vw, 4.2rem);
  font-weight: 900; line-height: 1.1;
  margin-bottom: 1.4rem;
}
.hero-title span { color: var(--sal-gold); }
.hero-desc {
  font-size: 1.1rem; color: rgba(255,255,255,.82);
  max-width: 580px; margin: 0 auto 2.2rem;
  line-height: 1.7;
}
.btn-hero-primary {
  background: var(--sal-gold); color: var(--sal-dark);
  font-weight: 800; font-size: 1rem; letter-spacing: .5px;
  border-radius: 30px; padding: .75rem 2.2rem; border: none;
  text-decoration: none; display: inline-block;
  transition: background .2s, transform .2s;
}
.btn-hero-primary:hover { background: var(--sal-accent); color: #fff; transform: translateY(-2px); }
.btn-hero-outline {
  background: transparent; color: #fff;
  font-weight: 800; font-size: 1rem; letter-spacing: .5px;
  border-radius: 30px; padding: .75rem 2.2rem;
  border: 2px solid rgba(255,255,255,.55);
  text-decoration: none; display: inline-block;
  transition: border-color .2s, color .2s, transform .2s;
}
.btn-hero-outline:hover { border-color: var(--sal-gold); color: var(--sal-gold); transform: translateY(-2px); }
.hero-scroll {
  position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%);
  color: rgba(255,255,255,.5); font-size: .8rem; text-align: center; animation: bounce 2s infinite;
}
.hero-scroll i { display: block; font-size: 1.6rem; }
@keyframes bounce {
  0%,100% { transform: translateX(-50%) translateY(0); }
  50%      { transform: translateX(-50%) translateY(8px); }
}

/* ---- FEATURES STRIP ---- */
.features-strip {
  background: #fff;
  position: relative; z-index: 10;
  margin-top: -3px;
}
.feature-box {
  text-align: center; padding: 3rem 1.5rem;
  border-bottom: 4px solid transparent;
  transition: border-color .2s, background .2s;
}
.feature-box:hover { border-color: var(--sal-gold); background: #fffdf4; }
.feature-icon {
  width: 70px; height: 70px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.8rem; color: #fff; margin-bottom: 1.1rem;
  background: var(--sal-dark);
}
.feature-box h4 {
  font-weight: 800; font-size: 1rem; letter-spacing: 2px;
  text-transform: uppercase; margin-bottom: .5rem; color: var(--sal-dark);
}
.feature-box p { color: var(--sal-text); font-size: .93rem; margin: 0; }
.feature-box a {
  display: inline-block; margin-top: .9rem;
  font-size: .82rem; font-weight: 800; letter-spacing: 1.5px;
  text-transform: uppercase; color: var(--sal-gold); text-decoration: none;
  border-bottom: 2px solid var(--sal-gold);
}
.features-strip .col-md-4:not(:last-child) .feature-box {
  border-right: 1px solid #efefef;
}

/* ---- SECTION HEADING ---- */
.sec-sup {
  display: block; font-size: .8rem; font-weight: 800; letter-spacing: 4px;
  text-transform: uppercase; color: var(--sal-gold); margin-bottom: .6rem;
}
.sec-title {
  font-size: clamp(1.6rem, 3.5vw, 2.4rem);
  font-weight: 900; color: var(--sal-dark); line-height: 1.2;
}
.sec-divider {
  width: 50px; height: 3px; background: var(--sal-gold);
  margin: .9rem 0 1.4rem;
}
.sec-divider.mx-auto { margin-left: auto; margin-right: auto; }

/* ---- ABOUT ---- */
#about { background: var(--sal-light); }
.about-img-wrap { position: relative; }
.about-img-wrap img {
  width: 100%; border-radius: 4px;
  box-shadow: 12px 12px 0 var(--sal-gold);
}
.about-badge {
  position: absolute; bottom: -20px; right: -10px;
  background: var(--sal-dark); color: #fff;
  padding: 1.2rem 1.5rem; border-radius: 4px;
  font-size: .85rem; font-weight: 700; text-align: center; min-width: 130px;
}
.about-badge span { display: block; font-size: 2rem; font-weight: 900; color: var(--sal-gold); line-height: 1; }
.check-list { list-style: none; padding: 0; margin: 0 0 1.6rem; }
.check-list li { padding: .35rem 0; color: #555; font-size: .96rem; }
.check-list li i { color: var(--sal-gold); margin-right: .5rem; }

/* ---- COUNTER ---- */
#counter { background: var(--sal-dark); color: #fff; padding: 5rem 0; }
#counter .sec-sup { color: var(--sal-gold); }
#counter .sec-title { color: #fff; }
.counter-box { text-align: center; padding: 2rem 1rem; }
.counter-num {
  font-size: 3.5rem; font-weight: 900; color: var(--sal-gold); line-height: 1;
  display: block; margin-bottom: .3rem;
}
.counter-label {
  font-size: .82rem; font-weight: 800; letter-spacing: 3px;
  text-transform: uppercase; color: rgba(255,255,255,.7);
}
.counter-icon { font-size: 2rem; color: rgba(255,255,255,.2); display: block; margin-bottom: .7rem; }

/* ---- ACTIONS ---- */
#actions { background: #fff; padding: 5.5rem 0; }
.action-card {
  background: #fff; border: 1px solid #eee; border-radius: 6px; padding: 2.4rem 1.8rem;
  transition: transform .2s, box-shadow .2s;
  text-decoration: none; color: inherit; display: block;
  border-top: 4px solid var(--sal-gold); height: 100%;
}
.action-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(0,0,0,.1); color: inherit; }
.action-card .ac-icon {
  width: 60px; height: 60px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.6rem; color: var(--sal-dark);
  background: rgba(249,191,63,.15); margin-bottom: 1.2rem;
}
.action-card h5 { font-weight: 900; text-transform: uppercase; letter-spacing: 1px; color: var(--sal-dark); margin-bottom: .5rem; }
.action-card p { color: var(--sal-text); font-size: .93rem; margin: 0; }
.action-card .ac-link {
  display: inline-block; margin-top: 1rem;
  font-size: .83rem; font-weight: 800; letter-spacing: 1.5px;
  text-transform: uppercase; color: var(--sal-gold);
  border-bottom: 2px solid var(--sal-gold); text-decoration: none;
}

/* ---- EVENTS ---- */
#events { background: var(--sal-light); padding: 5.5rem 0; }
.event-item {
  background: #fff; border-radius: 6px;
  display: flex; align-items: stretch;
  box-shadow: 0 4px 20px rgba(0,0,0,.07);
  margin-bottom: 1.2rem; overflow: hidden;
}
.event-date {
  background: var(--sal-dark); color: #fff; text-align: center;
  padding: 1.4rem 1.2rem; min-width: 80px; flex-shrink: 0;
  display: flex; flex-direction: column; justify-content: center;
}
.event-date .day { font-size: 2rem; font-weight: 900; line-height: 1; color: var(--sal-gold); }
.event-date .month { font-size: .75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
.event-body { padding: 1.2rem 1.5rem; flex: 1; }
.event-body h5 { font-weight: 800; color: var(--sal-dark); margin: 0 0 .3rem; }
.event-meta { font-size: .82rem; color: var(--sal-text); }
.event-meta i { color: var(--sal-gold); margin-right: .3rem; }

/* ---- FOOTER ---- */
#footer { background: var(--sal-dark2); color: rgba(255,255,255,.7); padding: 5rem 0 0; }
#footer .brand { font-size: 1.5rem; font-weight: 900; color: #fff; }
#footer .brand span { color: var(--sal-gold); }
#footer p { font-size: .93rem; line-height: 1.7; }
#footer h5 {
  font-size: .85rem; font-weight: 800; letter-spacing: 3px;
  text-transform: uppercase; color: #fff; margin-bottom: 1.2rem;
  padding-bottom: .5rem; border-bottom: 2px solid var(--sal-gold); display: inline-block;
}
#footer a { color: rgba(255,255,255,.65); text-decoration: none; font-size: .93rem; transition: color .2s; }
#footer a:hover { color: var(--sal-gold); }
#footer ul { list-style: none; padding: 0; margin: 0; }
#footer ul li { padding: .3rem 0; }
#footer ul li i { color: var(--sal-gold); margin-right: .5rem; font-size: .85rem; }
.footer-bottom {
  background: rgba(0,0,0,.25); margin-top: 3rem; padding: 1.2rem 0;
  font-size: .85rem; text-align: center; color: rgba(255,255,255,.45);
}
.footer-bottom a { color: var(--sal-gold); }

/* ---- RESPONSIVE ---- */
@media (max-width: 768px) {
  .features-strip .col-md-4:not(:last-child) .feature-box { border-right: none; border-bottom: 1px solid #efefef; }
  .about-badge { display: none; }
}
</style>
</head>
<body>

<!-- ======= NAVBAR ======= -->
<nav id="mainNav" class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="/Tumaini-Connect/">Tumaini<span>Connect</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-center gap-1">
        <li class="nav-item"><a class="nav-link" href="#about" data-i18n="nav_about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#actions" data-i18n="nav_get_started">Get Started</a></li>
        <li class="nav-item"><a class="nav-link" href="#events" data-i18n="nav_events">Events</a></li>
        <li class="nav-item"><a class="nav-link" href="#footer" data-i18n="nav_contact">Contact</a></li>
        <!-- Language switcher -->
        <li class="nav-item">
          <button id="lang-toggle" class="btn btn-sm ms-1"
                  style="background:transparent;border:1.5px solid rgba(255,255,255,.45);color:rgba(255,255,255,.85);border-radius:20px;font-weight:800;font-size:.78rem;letter-spacing:1.5px;padding:.28rem .8rem;transition:border-color .2s,color .2s;">
            SW
          </button>
        </li>
        <?php if ($user && is_admin_role((string) ($user['role'] ?? ''))): ?>
        <li class="nav-item"><a class="nav-link btn-nav ms-2" href="/Tumaini-Connect/admin/dashboard.php"><i class="bi bi-grid-3x3-gap-fill me-1"></i><span data-i18n="nav_dashboard">Dashboard</span></a></li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link btn-nav ms-2" href="/Tumaini-Connect/auth/login.php"><i class="bi bi-box-arrow-in-right me-1"></i><span data-i18n="nav_login">Admin Login</span></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- ======= HERO ======= -->
<section id="hero">
  <div id="hero-bg"></div>
  <div id="hero-bg-next"></div>
  <div id="hero-overlay"></div>
  <div class="container" style="position:relative;z-index:3;">
    <span class="hero-sup" data-i18n="hero_sup">Seventh-day Adventist Church &mdash; Tanzania</span>
    <h1 class="hero-title" data-i18n="hero_title">Hope, Community<br>&amp; <span>Outreach</span></h1>
    <p class="hero-desc" data-i18n="hero_desc">
      Tumaini Connect unites Seventh-day Adventist outreach stations across Tanzania
      to collect interests, coordinate follow-up care, and spread hope to every community.
    </p>
    <div class="d-flex flex-wrap gap-3 justify-content-center">
      <button type="button" class="btn-hero-primary" data-bs-toggle="modal" data-bs-target="#reportModal" data-i18n="hero_btn_report">Submit Report</button>
      <button type="button" class="btn-hero-outline" data-bs-toggle="modal" data-bs-target="#interestModal" data-i18n="hero_btn_interest">Submit Interest</button>
    </div>
  </div>
  <div class="hero-scroll">
    <i class="bi bi-chevron-double-down"></i>
    Scroll
  </div>
</section>

<!-- ======= FEATURES STRIP ======= -->
<div class="features-strip">
  <div class="container-fluid px-0">
    <div class="row g-0">
      <div class="col-md-4">
        <div class="feature-box">
          <div class="feature-icon"><i class="bi bi-house-heart"></i></div>
          <h4 data-i18n="feat_stations_title">Stations</h4>
          <p data-i18n="feat_stations_desc">Register and manage outreach stations across conferences and unions throughout Tanzania.</p>
          <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" data-i18n="feat_stations_link">Register Now</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-box">
          <div class="feature-icon"><i class="bi bi-person-lines-fill"></i></div>
          <h4 data-i18n="feat_report_title">Attendance Report</h4>
          <p data-i18n="feat_report_desc">Submit daily meeting attendance: elderly, children and church members &mdash; with photos or video.</p>
          <a href="#" data-bs-toggle="modal" data-bs-target="#reportModal" data-i18n="feat_report_link">Submit Report</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-box">
          <div class="feature-icon"><i class="bi bi-heart-pulse"></i></div>
          <h4 data-i18n="feat_interest_title">Submit Interest</h4>
          <p data-i18n="feat_interest_desc">Share your interest in baptism, Bible study, prayer or a church visit &mdash; we will follow up with care.</p>
          <a href="#" data-bs-toggle="modal" data-bs-target="#interestModal" data-i18n="feat_interest_link">Submit Interest</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ======= ABOUT ======= -->
<section id="about" style="padding:6rem 0;">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5">
        <div class="about-img-wrap">
          <img src="/Tumaini-Connect/assets/images/home2.jpg"
               onerror="this.src='https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=700&q=80'"
               alt="Tumaini Lenye Baraka – Njombe Net-Event 2026" loading="lazy">
        </div>
        <!-- Upcoming event badge -->
        <div style="background:linear-gradient(135deg,#1b1b2e,#252540);border-radius:12px;padding:1.1rem 1.4rem;margin-top:1.4rem;display:flex;align-items:center;gap:1rem;">
          <div style="background:var(--sal-gold);border-radius:10px;padding:.6rem .8rem;flex-shrink:0;">
            <i class="bi bi-calendar-event-fill" style="font-size:1.5rem;color:var(--sal-dark);"></i>
          </div>
          <div>
            <div style="font-size:.72rem;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:var(--sal-gold);margin-bottom:.15rem;" data-i18n="about_event_badge">Upcoming Event</div>
            <div style="font-size:.97rem;font-weight:900;color:#fff;">Tumaini Lenye Baraka</div>
            <div style="font-size:.8rem;color:rgba(255,255,255,.65);">Njombe Net-Event &bull; 9 – 30 May 2026</div>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <span class="sec-sup" data-i18n="about_sup">Welcome to Tumaini Connect</span>
        <h2 class="sec-title" data-i18n="about_title">Capturing Every Soul,<br>Leaving <span style="color:var(--sal-gold);">No One Behind</span></h2>
        <div class="sec-divider"></div>
        <p style="color:#666;line-height:1.8;margin-bottom:1.2rem;" data-i18n="about_p1">
          Tumaini Connect is an outreach coordination platform built for the
          Seventh-day Adventist Church of Tanzania to manage stations, collect interest reports,
          and coordinate compassionate follow-up care across every union and conference.
        </p>
        <p style="color:#666;line-height:1.8;margin-bottom:1.8rem;" data-i18n="about_p2">
          Register your station today to track attendance, capture visitors and manage
          every spiritual interest raised during outreach events &mdash; including the
          <strong>Tumaini Lenye Baraka</strong> Njombe Net-Event running 9&ndash;30 May 2026.
        </p>
        <ul class="check-list">
          <li><i class="bi bi-check-circle-fill"></i><span data-i18n="about_li1">Manage unions, conferences and local stations</span></li>
          <li><i class="bi bi-check-circle-fill"></i><span data-i18n="about_li2">Collect and review interest requests in real time</span></li>
          <li><i class="bi bi-check-circle-fill"></i><span data-i18n="about_li3">Assign and track follow-up visits</span></li>
          <li><i class="bi bi-check-circle-fill"></i><span data-i18n="about_li4">Generate reports for leadership and planning</span></li>
        </ul>
        <div class="d-flex flex-wrap gap-3 mt-2">
          <button type="button" class="btn-hero-primary" data-bs-toggle="modal" data-bs-target="#registerModal" data-i18n="about_cta">Register Your Station</button>
          <a href="#events" class="btn-hero-outline" data-i18n="about_events_btn">View Upcoming Events</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ======= COUNTER ======= -->
<section id="counter">
  <div class="container">
    <div class="text-center mb-5">
      <span class="sec-sup" data-i18n="counter_sup">Our Impact</span>
      <h2 class="sec-title" style="color:#fff;" data-i18n="counter_title">We Are on a Mission<br>to Reach Every Individual</h2>
    </div>
    <div class="row">
      <div class="col-6 col-md-3">
        <div class="counter-box">
          <i class="bi bi-building counter-icon"></i>
          <span class="counter-num" data-target="<?= $stats["stations"] ?>"><?= $stats["stations"] ?></span>
          <span class="counter-label" data-i18n="counter_stations">Stations</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="counter-box">
          <i class="bi bi-person-check counter-icon"></i>
          <span class="counter-num" data-target="<?= $stats["interests"] ?>"><?= $stats["interests"] ?></span>
          <span class="counter-label" data-i18n="counter_interests">Interests Received</span>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="counter-box">
          <i class="bi bi-person-walking counter-icon"></i>
          <span class="counter-num" id="today-visitors-num" data-target="<?= $stats["todayVisitors"] ?>"><?= $stats["todayVisitors"] ?></span>
          <span class="counter-label">Today's Visitors</span>
 
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="counter-box">
          <i class="bi bi-people-fill counter-icon"></i>
          <span class="counter-num" id="today-attendance-num" data-target="<?= $stats["todayAttendance"] ?>"><?= $stats["todayAttendance"] ?></span>
          <span class="counter-label" data-i18n="counter_today_attendance">Today's Attendance</span>
          
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ======= EVENTS ======= -->
<section id="events" style="padding:5rem 0;background:#fff;">
  <div class="container">
  <div style="display:flex;flex-wrap:wrap;border-radius:20px;overflow:hidden;box-shadow:0 16px 60px rgba(17,20,31,.11);">

    <!-- LEFT: Njombe image (fixed height, not full-screen) -->
    <div style="flex:0 0 50%;min-width:280px;height:520px;position:relative;overflow:hidden;">
      <img src="/Tumaini-Connect/assets/images/njombe.jpeg"
           alt="Njombe – Tumaini Lenye Baraka 2026"
           style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;display:block;">
      <!-- dark overlay for readability -->
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(10,12,24,.35) 0%,rgba(10,12,24,.15) 60%,rgba(10,12,24,.55) 100%);pointer-events:none;"></div>
      <!-- floating event title on image -->
      <div style="position:absolute;bottom:2rem;left:2rem;right:2rem;">
        <div style="display:inline-block;background:var(--sal-gold);color:var(--sal-dark);font-size:.7rem;font-weight:900;letter-spacing:2.5px;text-transform:uppercase;padding:.35rem .95rem;border-radius:999px;margin-bottom:.9rem;">Net-Event 2026</div>
        <h2 style="font-size:2.6rem;font-weight:900;color:#fff;line-height:1.15;margin:0;text-shadow:0 2px 16px rgba(0,0,0,.5);">Tumaini<br><span style="color:var(--sal-gold);">Lenye Baraka</span></h2>
        <p style="color:rgba(255,255,255,.75);font-size:.9rem;margin-top:.55rem;font-weight:600;text-shadow:0 1px 6px rgba(0,0,0,.5);">Njombe Mjini SDA Church &bull; 9 – 30 May 2026</p>
      </div>
    </div>

    <!-- RIGHT: white content panel -->
    <div style="flex:1;min-width:280px;display:flex;align-items:center;background:#fff;border-left:1px solid #f0ede8;">
      <div style="padding:2.8rem 2.5rem;width:100%;">

        <span class="sec-sup"><i class="bi bi-calendar2-star me-1"></i>Upcoming Events</span>
        <h3 style="font-size:1.75rem;font-weight:900;color:var(--sal-dark);margin-bottom:.25rem;">Event Details</h3>
        <div class="sec-divider" style="margin-bottom:1rem;"></div>
        <p style="color:#777;font-size:.85rem;margin-bottom:1.2rem;line-height:1.6;">
          Kanisa la Waadventista wa Sabato Tanzania &bull; ATAPE
        </p>

        <ul style="list-style:none;padding:0;margin:0 0 1.4rem;display:flex;flex-direction:column;gap:.6rem;">
          <li style="display:flex;align-items:center;gap:.9rem;font-size:.93rem;color:#444;">
            <span style="background:#fff8e6;border-radius:10px;padding:.5rem .6rem;color:var(--sal-gold);font-size:1.05rem;flex-shrink:0;line-height:1;"><i class="bi bi-geo-alt-fill"></i></span>
            <span><strong style="color:var(--sal-dark);">Njombe Mjini SDA Church</strong>, Njombe</span>
          </li>
          <li style="display:flex;align-items:center;gap:.9rem;font-size:.93rem;color:#444;">
            <span style="background:#fff8e6;border-radius:10px;padding:.5rem .6rem;color:var(--sal-gold);font-size:1.05rem;flex-shrink:0;line-height:1;"><i class="bi bi-calendar-range-fill"></i></span>
            <span><strong style="color:var(--sal-dark);">9 – 30 May 2026</strong></span>
          </li>
          <li style="display:flex;align-items:center;gap:.9rem;font-size:.93rem;color:#444;">
            <span style="background:#fff8e6;border-radius:10px;padding:.5rem .6rem;color:var(--sal-gold);font-size:1.05rem;flex-shrink:0;line-height:1;"><i class="bi bi-clock-fill"></i></span>
            <span>11 Jioni &ndash; 02 Usiku</span>
          </li>
          <li style="display:flex;align-items:center;gap:.9rem;font-size:.93rem;color:#444;">
            <span style="background:#fff8e6;border-radius:10px;padding:.5rem .6rem;color:var(--sal-gold);font-size:1.05rem;flex-shrink:0;line-height:1;"><i class="bi bi-telephone-fill"></i></span>
            <span>+255 677 090 741</span>
          </li>
        </ul>

        <!-- Watch & Follow -->
        <div style="font-size:.7rem;font-weight:900;letter-spacing:2px;text-transform:uppercase;color:#bbb;margin-bottom:.85rem;">Watch &amp; Follow</div>
        <div style="display:flex;flex-wrap:wrap;gap:.45rem;margin-bottom:1.3rem;">
          <a href="https://www.youtube.com/@HopeChannelTZ" target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:.35rem;background:#ff0000;color:#fff;text-decoration:none;font-size:.78rem;font-weight:800;padding:.38rem .85rem;border-radius:999px;">
            <i class="bi bi-youtube"></i> YouTube
          </a>
          <a href="https://www.instagram.com/hopechanneltanzania" target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:.35rem;background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);color:#fff;text-decoration:none;font-size:.78rem;font-weight:800;padding:.38rem .85rem;border-radius:999px;">
            <i class="bi bi-instagram"></i> Instagram
          </a>
          <a href="https://www.facebook.com/HopeChannelTanzania" target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:.35rem;background:#1877f2;color:#fff;text-decoration:none;font-size:.78rem;font-weight:800;padding:.38rem .85rem;border-radius:999px;">
            <i class="bi bi-facebook"></i> Facebook
          </a>
          <a href="https://www.tiktok.com/@hopechanneltanzania" target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:.35rem;background:#010101;color:#fff;text-decoration:none;font-size:.78rem;font-weight:800;padding:.38rem .85rem;border-radius:999px;">
            <i class="bi bi-tiktok"></i> TikTok
          </a>
          <a href="https://x.com/HopeChannelTZ" target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:.35rem;background:#111;color:#fff;text-decoration:none;font-size:.78rem;font-weight:800;padding:.38rem .85rem;border-radius:999px;">
            <i class="bi bi-twitter-x"></i> X
          </a>
        </div>

        <div style="padding-top:1.3rem;border-top:1px solid #f0ede8;font-size:.78rem;color:#aaa;line-height:1.9;">
          Also live on
          <strong style="color:var(--sal-dark);">Azam TV Ch.466</strong> &bull;
          <strong style="color:var(--sal-dark);">Zuku Ch.870</strong> &bull;
          <strong style="color:var(--sal-dark);">Hope Channel TZ</strong> &bull;
          <strong style="color:var(--sal-dark);">AWR Tanzania</strong> &bull;
          <strong style="color:var(--sal-dark);">Rock FM Mbeya</strong>
        </div>

      </div>
    </div>
  </div><!-- /flex card -->
  </div><!-- /container -->
</section>
<!-- responsive -->
<style>
@media (max-width:991px) {
  #events .container > div { flex-direction: column; }
  #events .container > div > div:first-child { flex:unset;height:55vw;min-height:200px;width:100%; }
  #events .container > div > div:last-child  { flex:unset;width:100%;border-left:none; }
  #events .container > div > div:last-child > div { padding:2rem 1.5rem; }
  #events .container > div > div:first-child > div:last-child { left:1.2rem;right:1.2rem;bottom:1.2rem; }
}
</style>

<!-- ======= ACTION CARDS ======= -->
<section id="actions">
  <div class="container">
    <div class="text-center mb-5">
      <span class="sec-sup" data-i18n="actions_sup">Get Started</span>
      <h2 class="sec-title" data-i18n="actions_title">Where Would You Like to Begin?</h2>
      <div class="sec-divider mx-auto"></div>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <a href="/Tumaini-Connect/auth/login.php" class="action-card">
          <div class="ac-icon"><i class="bi bi-shield-lock-fill"></i></div>
          <h5 data-i18n="ac_admin_title">Admin Access</h5>
          <p data-i18n="ac_admin_desc">Sign in as Tanzania admin, union admin, conference admin, coordinator or follow-up officer.</p>
          <span class="ac-link" data-i18n="ac_admin_link">Login Now</span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="#" class="action-card" data-bs-toggle="modal" data-bs-target="#registerModal">
          <div class="ac-icon"><i class="bi bi-building-add"></i></div>
          <h5 data-i18n="ac_register_title">Register Station</h5>
          <p data-i18n="ac_register_desc">Create a new station and set up a coordinator account &mdash; no admin login required.</p>
          <span class="ac-link" data-i18n="ac_register_link">Register Now</span>
        </a>
      </div>
      <div class="col-md-4">
        <a href="#" class="action-card" data-bs-toggle="modal" data-bs-target="#interestModal">
          <div class="ac-icon"><i class="bi bi-heart-pulse"></i></div>
          <h5 data-i18n="ac_interest_title">Submit Interest</h5>
          <p data-i18n="ac_interest_desc">Share your interest in baptism, Bible study, prayer or a church visit &mdash; we will follow up with you.</p>
          <span class="ac-link" data-i18n="ac_interest_link">Open Form</span>
        </a>
      </div>
    </div>
  </div>
</section>



<!-- ======= FOOTER ======= -->
<footer id="footer">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="brand mb-3">Tumaini<span>Connect</span></div>
        <p data-i18n="footer_tagline">
          The official outreach coordination platform of the Seventh-day Adventist Church of Tanzania.
          Collecting interests, coordinating follow-ups, and spreading hope &mdash; one community at a time.
        </p>
        <div class="d-flex gap-3 mt-3">
          <a href="https://www.facebook.com/HopeChannelTanzania" target="_blank" rel="noopener" title="Facebook" style="font-size:1.2rem;"><i class="bi bi-facebook"></i></a>
          <a href="https://x.com/HopeChannelTZ" target="_blank" rel="noopener" title="X / Twitter" style="font-size:1.2rem;"><i class="bi bi-twitter-x"></i></a>
          <a href="https://www.instagram.com/hopechanneltanzania" target="_blank" rel="noopener" title="Instagram" style="font-size:1.2rem;"><i class="bi bi-instagram"></i></a>
          <a href="https://www.tiktok.com/@hopechanneltanzania" target="_blank" rel="noopener" title="TikTok" style="font-size:1.2rem;"><i class="bi bi-tiktok"></i></a>
          <a href="https://www.youtube.com/@HopeChannelTZ" target="_blank" rel="noopener" title="YouTube" style="font-size:1.2rem;"><i class="bi bi-youtube"></i></a>
        </div>
      </div>
      <div class="col-lg-2 col-6">
        <h5>Quick Links</h5>
        <ul>
          <li><i class="bi bi-chevron-right"></i><a href="/Tumaini-Connect/">Home</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="#about">About</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="#actions">Get Started</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="#events">Events</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="/Tumaini-Connect/auth/login.php">Admin Login</a></li>
        </ul>
      </div>
      <div class="col-lg-3 col-6">
        <h5>Services</h5>
        <ul>
          <li><i class="bi bi-chevron-right"></i><a href="#" data-bs-toggle="modal" data-bs-target="#registerModal">Register Station</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="#" data-bs-toggle="modal" data-bs-target="#reportModal">Submit Report</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="#" data-bs-toggle="modal" data-bs-target="#interestModal">Submit Interest</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="/Tumaini-Connect/modules/interests/public_form.php">Station Report Form</a></li>
          <li><i class="bi bi-chevron-right"></i><a href="/Tumaini-Connect/admin/dashboard.php">Admin Dashboard</a></li>
        </ul>
      </div>
      <div class="col-lg-3">
        <h5>Have a Question?</h5>
        <ul>
          <li><i class="bi bi-geo-alt-fill"></i>Seventh-day Adventist Church of Tanzania</li>
          <li><i class="bi bi-telephone-fill"></i>+255 756 505 435</li>
          <li><i class="bi bi-envelope-fill"></i>info@tumaini.or.tz</li>
        </ul>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">
      &copy; <?= date("Y") ?> Tumaini Connect &mdash; Seventh-day Adventist Church of Tanzania
    </div>
  </div>
</footer>

<!-- ======= MEETING ATTENDANCE REPORT MODAL ======= -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">

      <!-- Modal Header -->
      <div class="modal-header" style="background:var(--sal-dark);border:none;padding:1.2rem 1.6rem;">
        <div>
          <h5 class="modal-title" id="reportModalLabel" style="color:#fff;font-weight:900;margin:0;">
            <i class="bi bi-clipboard2-pulse me-2" style="color:var(--sal-gold);"></i>Meeting Attendance Report
          </h5>
          <div id="rm-badge" class="<?= $reportVerified ? '' : 'd-none' ?> mt-1" style="font-size:.82rem;color:rgba(255,255,255,.6);">
            <i class="bi bi-building me-1"></i><span id="rm-badge-name"><?= htmlspecialchars($reportCenterName, ENT_QUOTES) ?></span>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-0">

        <!-- STEP 1: Verify -->
        <div id="rm-step-verify" <?= $reportVerified ? 'style="display:none;"' : '' ?> style="padding:2rem 1.8rem;">
          <p style="color:#555;margin-bottom:1.4rem;">Enter your station credentials to access the report form.</p>
          <div id="rm-verify-alert" class="alert d-none mb-3"></div>
          <div class="mb-3">
            <label style="font-weight:700;font-size:.9rem;color:var(--sal-dark);">Station Name</label>
            <input id="rm-sname" type="text" class="form-control form-control-lg mt-1"
                   placeholder="e.g. Busegeja SDA" autocomplete="organization">
          </div>
          <div class="mb-4">
            <label style="font-weight:700;font-size:.9rem;color:var(--sal-dark);">Coordinator Mobile Number</label>
            <input id="rm-phone" type="tel" class="form-control form-control-lg mt-1"
                   placeholder="e.g. 0712345678" autocomplete="tel">
          </div>
          <button id="rm-verify-btn" class="btn btn-lg w-100"
                  style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;border-radius:8px;">
            <span id="rm-vbtn-txt">Verify Station</span>
            <span id="rm-vbtn-spin" class="spinner-border spinner-border-sm ms-2 d-none"></span>
          </button>
          <div class="text-center mt-3" style="font-size:.85rem;color:#999;">
            New station? <a href="#" id="rm-goto-register" style="color:var(--sal-gold);">Register here</a>
          </div>
        </div>

        <!-- STEP 2: Report form + History -->
        <div id="rm-step-main" <?= $reportVerified ? '' : 'class="d-none"' ?>>

          <!-- Tabs -->
          <ul class="nav nav-tabs border-0 px-3 pt-3" style="gap:.3rem;">
            <li class="nav-item">
              <button id="tab-form-btn" class="nav-link active rm-tab" data-target="rm-tab-form"
                      style="font-weight:800;font-size:.88rem;border-radius:6px 6px 0 0;">
                <i class="bi bi-pencil-square me-1"></i>Submit Report
              </button>
            </li>
            <li class="nav-item">
              <button id="tab-hist-btn" class="nav-link rm-tab" data-target="rm-tab-history"
                      style="font-weight:800;font-size:.88rem;border-radius:6px 6px 0 0;">
                <i class="bi bi-clock-history me-1"></i>Previous Reports
              </button>
            </li>
            <li class="nav-item">
              <button id="tab-fu-btn" class="nav-link rm-tab" data-target="rm-tab-followups"
                      style="font-weight:800;font-size:.88rem;border-radius:6px 6px 0 0;">
                <i class="bi bi-whatsapp me-1"></i>Follow-up Contacts
              </button>
            </li>
            <li class="ms-auto nav-item d-flex align-items-center pe-1">
              <button id="rm-logout-btn" class="btn btn-sm btn-outline-secondary"
                      style="font-size:.78rem;" title="Switch Station">
                <i class="bi bi-arrow-left-right"></i> Switch
              </button>
            </li>
          </ul>

          <!-- Tab: Submit Form -->
          <div id="rm-tab-form" style="padding:1.5rem 1.8rem;">
            <div id="rm-submit-alert" class="alert d-none mb-3"></div>
            <form id="rm-form" enctype="multipart/form-data" novalidate>

              <div class="mb-3">
                <label style="font-weight:700;font-size:.9rem;">Report Date</label>
                <input id="rm-date" type="date" name="report_date" class="form-control mt-1">
              </div>

              <!-- Elderly Visitors -->
              <div class="mb-3">
                <div style="background:#f8f7f4;border-radius:8px;padding:1rem 1.2rem;">
                  <div class="rm-group-label">
                    <i class="bi bi-person-standing" style="color:var(--sal-gold);"></i>&nbsp;Elderly Visitors
                  </div>
                  <div class="row g-2 mt-1">
                    <div class="col-6">
                      <label class="rm-sub-label">Men</label>
                      <input type="number" name="elderly_men" min="0" value="0" class="form-control">
                    </div>
                    <div class="col-6">
                      <label class="rm-sub-label">Women</label>
                      <input type="number" name="elderly_women" min="0" value="0" class="form-control">
                    </div>
                  </div>
                </div>
              </div>

              <!-- Children -->
              <div class="mb-3">
                <div style="background:#f8f7f4;border-radius:8px;padding:1rem 1.2rem;">
                  <div class="rm-group-label">
                    <i class="bi bi-people" style="color:var(--sal-gold);"></i>&nbsp;Children
                  </div>
                  <div class="row g-2 mt-1">
                    <div class="col-6">
                      <label class="rm-sub-label">Boys</label>
                      <input type="number" name="children_boys" min="0" value="0" class="form-control">
                    </div>
                    <div class="col-6">
                      <label class="rm-sub-label">Girls</label>
                      <input type="number" name="children_girls" min="0" value="0" class="form-control">
                    </div>
                  </div>
                </div>
              </div>

              <!-- Church Members -->
              <div class="mb-3">
                <div style="background:#f8f7f4;border-radius:8px;padding:1rem 1.2rem;">
                  <div class="rm-group-label">
                    <i class="bi bi-building" style="color:var(--sal-gold);"></i>&nbsp;Church Members Attended
                  </div>
                  <input type="number" name="members_attended" min="0" value="0" class="form-control mt-2">
                </div>
              </div>

              <!-- Photos / Video -->
              <div class="mb-4">
                <label style="font-weight:700;font-size:.9rem;display:block;margin-bottom:.5rem;">
                  <i class="bi bi-camera" style="color:var(--sal-gold);"></i>
                  Station Photos / Short Video
                  <span style="font-weight:400;font-size:.78rem;color:#999;"> &mdash; multiple allowed</span>
                </label>
                <div id="rm-drop-area"
                     onclick="document.getElementById('rm-files').click()"
                     style="border:2px dashed #ddd;border-radius:8px;padding:1.6rem;text-align:center;
                            cursor:pointer;transition:border-color .2s;">
                  <i class="bi bi-cloud-upload" style="font-size:2.2rem;color:#ccc;"></i>
                  <div style="font-size:.88rem;color:#aaa;margin-top:.3rem;">Tap to capture or choose files</div>
                  <div style="font-size:.73rem;color:#ccc;">JPG &bull; PNG &bull; GIF &bull; WEBP &bull; MP4 &mdash; max 20 MB each</div>
                </div>
                <input type="file" id="rm-files" name="images[]" multiple
                       accept="image/*,video/mp4,video/quicktime" style="display:none;">
                <div id="rm-preview" class="mt-2 d-flex flex-wrap gap-2"></div>
              </div>

              <button type="submit" id="rm-submit-btn" class="btn btn-lg w-100"
                      style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;border-radius:8px;">
                <span id="rm-sbtn-txt">Submit Report</span>
                <span id="rm-sbtn-spin" class="spinner-border spinner-border-sm ms-2 d-none"></span>
              </button>
            </form>
          </div>

          <!-- Tab: History -->
          <div id="rm-tab-history" class="d-none" style="padding:1.5rem 1.8rem;">
            <div id="rm-hist-loader" class="text-center py-4">
              <div class="spinner-border" style="color:var(--sal-gold);"></div>
              <div style="font-size:.88rem;color:#aaa;margin-top:.5rem;">Loading reports&hellip;</div>
            </div>
            <div id="rm-hist-body"></div>
          </div>

          <!-- Tab: Follow-up Contacts -->
          <div id="rm-tab-followups" class="d-none" style="padding:1.5rem 1.8rem;">
            <div id="rm-fu-loader" class="text-center py-4">
              <div class="spinner-border" style="color:var(--sal-gold);"></div>
              <div style="font-size:.88rem;color:#aaa;margin-top:.5rem;">Loading contacts&hellip;</div>
            </div>
            <div id="rm-fu-body"></div>
          </div>

        </div><!-- /rm-step-main -->
      </div><!-- /modal-body -->
    </div>
  </div>
</div>
<!-- ======= END REPORT MODAL ======= -->

<style>
.rm-group-label { font-weight:800; font-size:.82rem; letter-spacing:1.5px; text-transform:uppercase; color:var(--sal-dark); }
.rm-sub-label   { font-size:.82rem; font-weight:700; display:block; margin-bottom:.25rem; }
#rm-drop-area:hover { border-color:var(--sal-gold); background:#fffdf4; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ---- Report Modal JS ---- */
(function () {
  var BASE = '/Tumaini-Connect';
  var verified = <?= json_encode($reportVerified) ?>;

  /* Set today's date */
  var dateIn = document.getElementById('rm-date');
  if (dateIn) dateIn.value = new Date().toISOString().split('T')[0];

  /* --- Tab switching --- */
  document.querySelectorAll('.rm-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.rm-tab').forEach(function (t) { t.classList.remove('active'); });
      btn.classList.add('active');
      var target = btn.getAttribute('data-target');
      ['rm-tab-form', 'rm-tab-history', 'rm-tab-followups'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.classList.toggle('d-none', id !== target);
      });
      if (target === 'rm-tab-history') loadHistory();
      if (target === 'rm-tab-followups') loadFollowups();
    });
  });

  /* --- Verify --- */
  document.getElementById('rm-verify-btn').addEventListener('click', function () {
    var name  = (document.getElementById('rm-sname').value || '').trim();
    var phone = (document.getElementById('rm-phone').value || '').trim();
    if (!name || !phone) {
      showAlert('rm-verify-alert', 'danger', 'Please enter both station name and mobile number.');
      return;
    }
    setBusy('rm-verify-btn', 'rm-vbtn-txt', 'rm-vbtn-spin', 'Verifying…', true);
    clearAlert('rm-verify-alert');
    var fd = new FormData();
    fd.append('station_name', name);
    fd.append('phone', phone);
    fetch(BASE + '/api/report/verify.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        setBusy('rm-verify-btn', 'rm-vbtn-txt', 'rm-vbtn-spin', 'Verify Station', false);
        if (d.ok) {
          document.getElementById('rm-badge-name').textContent = d.center_name;
          document.getElementById('rm-badge').classList.remove('d-none');
          document.getElementById('rm-step-verify').style.display = 'none';
          document.getElementById('rm-step-main').classList.remove('d-none');
          verified = true;
          histLoaded = false;
          fuLoaded = false;
        } else {
          showAlert('rm-verify-alert', 'danger', d.error || 'Verification failed.');
        }
      })
      .catch(function () {
        setBusy('rm-verify-btn', 'rm-vbtn-txt', 'rm-vbtn-spin', 'Verify Station', false);
        showAlert('rm-verify-alert', 'danger', 'Network error. Please try again.');
      });
  });

  /* --- Switch Station --- */
  document.getElementById('rm-logout-btn').addEventListener('click', function () {
    fetch(BASE + '/api/report/logout.php', { method: 'POST' })
      .catch(function () {})
      .finally(function () {
        verified = false;
        histLoaded = false;
        fuLoaded = false;
        _apiData = null;
        document.getElementById('rm-step-verify').style.display = '';
        document.getElementById('rm-step-main').classList.add('d-none');
        document.getElementById('rm-badge').classList.add('d-none');
        document.getElementById('rm-sname').value = '';
        document.getElementById('rm-phone').value = '';
        clearAlert('rm-verify-alert');
        /* reset to first tab */
        document.querySelectorAll('.rm-tab').forEach(function (t) { t.classList.remove('active'); });
        document.getElementById('tab-form-btn').classList.add('active');
        ['rm-tab-form','rm-tab-history','rm-tab-followups'].forEach(function (id) {
          var el = document.getElementById(id);
          if (el) el.classList.toggle('d-none', id !== 'rm-tab-form');
        });
      });
  });

  /* --- File Preview --- */
  document.getElementById('rm-files').addEventListener('change', function () {
    var preview = document.getElementById('rm-preview');
    preview.innerHTML = '';
    Array.from(this.files).forEach(function (file) {
      var wrap = document.createElement('div');
      wrap.style.cssText = 'position:relative;width:80px;height:80px;border-radius:6px;overflow:hidden;border:2px solid #eee;flex-shrink:0;';
      if (file.type.startsWith('video/')) {
        wrap.style.background = '#1b1b2e';
        wrap.innerHTML = '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="bi bi-play-circle-fill" style="font-size:2rem;color:var(--sal-gold);"></i></div>';
      } else {
        var img = document.createElement('img');
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        img.src = URL.createObjectURL(file);
        wrap.appendChild(img);
      }
      var lbl = document.createElement('div');
      lbl.style.cssText = 'position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);color:#fff;font-size:.58rem;text-align:center;padding:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
      lbl.textContent = file.name;
      wrap.appendChild(lbl);
      preview.appendChild(wrap);
    });
  });

  /* --- Submit Report --- */
  document.getElementById('rm-form').addEventListener('submit', function (e) {
    e.preventDefault();
    setBusy('rm-submit-btn', 'rm-sbtn-txt', 'rm-sbtn-spin', 'Submitting…', true);
    clearAlert('rm-submit-alert');
    fetch(BASE + '/api/report/submit.php', { method: 'POST', body: new FormData(this) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        setBusy('rm-submit-btn', 'rm-sbtn-txt', 'rm-sbtn-spin', 'Submit Report', false);
        if (d.ok) {
          showAlert('rm-submit-alert', 'success', '✅ Report submitted successfully!');
          document.getElementById('rm-form').reset();
          document.getElementById('rm-preview').innerHTML = '';
          document.getElementById('rm-date').value = new Date().toISOString().split('T')[0];
          document.querySelectorAll('#rm-form input[type="number"]').forEach(function (el) { el.value = 0; });
        } else {
          showAlert('rm-submit-alert', 'danger', d.error || 'Submission failed.');
        }
      })
      .catch(function () {
        setBusy('rm-submit-btn', 'rm-sbtn-txt', 'rm-sbtn-spin', 'Submit Report', false);
        showAlert('rm-submit-alert', 'danger', 'Network error. Please try again.');
      });
  });

  /* shared API cache */
  var _apiData = null;
  function fetchApiData(cb) {
    if (_apiData) { cb(_apiData); return; }
    fetch(BASE + '/api/report/history.php')
      .then(function (r) { return r.json(); })
      .then(function (d) { _apiData = d; cb(d); })
      .catch(function () { cb(null); });
  }

  /* --- Load History --- */
  var histLoaded = false;
  function loadHistory() {
    if (histLoaded) return;
    histLoaded = true;
    document.getElementById('rm-hist-loader').style.display = 'block';
    document.getElementById('rm-hist-body').innerHTML = '';
    fetchApiData(function (d) {
      document.getElementById('rm-hist-loader').style.display = 'none';
      if (!d || !d.ok) {
        document.getElementById('rm-hist-body').innerHTML = '<p style="text-align:center;color:#aaa;padding:2rem 0;">Unable to load reports.</p>';
        return;
      }
      var html = '';
      if (d.reports && d.reports.length) {
        function buildReportCard(r) {
          var card = '';
          var totalV = (r.elderly_men + r.elderly_women + r.children_boys + r.children_girls);
          var grand  = totalV + r.members_attended;
          card += '<div style="background:#f8f7f4;border-radius:8px;padding:1rem 1.2rem;margin-bottom:1rem;">';
          card += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;gap:.8rem;">';
          card += '<strong style="color:var(--sal-dark);font-size:.95rem;">' + r.report_date + '</strong>';
          card += '<span style="background:var(--sal-gold);color:var(--sal-dark);font-size:.75rem;font-weight:800;padding:.2rem .65rem;border-radius:20px;">Total ' + grand + '</span>';
          card += '</div>';
          card += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;">';
          card += sc('Elderly Men',   r.elderly_men);
          card += sc('Elderly Women', r.elderly_women);
          card += sc('Boys',          r.children_boys);
          card += sc('Girls',         r.children_girls);
          card += sc('Members',       r.members_attended);
          card += sc('Visitors',      totalV);
          card += '</div>';
          if (r.images && r.images.length) {
            card += '<div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.7rem;">';
            r.images.forEach(function (p) {
              if (/\.(mp4|mov)$/i.test(p)) {
                card += '<div style="width:58px;height:58px;border-radius:5px;background:#1b1b2e;display:flex;align-items:center;justify-content:center;"><i class="bi bi-play-circle-fill" style="color:var(--sal-gold);font-size:1.4rem;"></i></div>';
              } else {
                card += '<img src="' + BASE + '/' + p + '" style="width:58px;height:58px;object-fit:cover;border-radius:5px;" loading="lazy">';
              }
            });
            card += '</div>';
          }
          card += '</div>';
          return card;
        }

        var latest = d.reports.slice(0, 3);
        latest.forEach(function (r) {
          html += buildReportCard(r);
        });

        var remaining = d.reports.slice(3);
        if (remaining.length) {
          html += '<div id="rm-hist-more" class="d-none">';
          remaining.forEach(function (r) {
            html += buildReportCard(r);
          });
          html += '</div>';
          html += '<div style="text-align:center;margin-top:.35rem;">';
          html += '<button id="rm-hist-more-btn" type="button" class="btn btn-sm btn-outline-secondary" style="font-weight:700;">Read More (' + remaining.length + ')</button>';
          html += '</div>';
        }
      } else {
        html = '<p style="text-align:center;color:#aaa;padding:2rem 0;">No previous reports submitted yet.</p>';
      }
      document.getElementById('rm-hist-body').innerHTML = html;

      var moreBtn = document.getElementById('rm-hist-more-btn');
      var moreWrap = document.getElementById('rm-hist-more');
      if (moreBtn && moreWrap) {
        moreBtn.addEventListener('click', function () {
          var hidden = moreWrap.classList.contains('d-none');
          moreWrap.classList.toggle('d-none', !hidden);
          moreBtn.textContent = hidden ? 'Show Less' : 'Read More (' + (d.reports.length - 3) + ')';
        });
      }
    });
  }

  /* --- Load Follow-up Contacts --- */
  var fuLoaded = false;
  function loadFollowups() {
    if (fuLoaded) return;
    fuLoaded = true;
    document.getElementById('rm-fu-loader').style.display = 'block';
    document.getElementById('rm-fu-body').innerHTML = '';
    fetchApiData(function (d) {
      document.getElementById('rm-fu-loader').style.display = 'none';
      if (!d || !d.ok) {
        document.getElementById('rm-fu-body').innerHTML = '<p style="text-align:center;color:#aaa;padding:2rem 0;">Unable to load contacts.</p>';
        return;
      }
      var html = '';
      if (d.followups && d.followups.length) {
        d.followups.forEach(function (f) {
          html += '<div style="background:#ffffff;border:1px solid #ece6d6;border-radius:10px;padding:1rem;margin-bottom:.85rem;box-shadow:0 6px 18px rgba(17,20,31,.04);">';
          html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.8rem;flex-wrap:wrap;">';
          html += '<div>';
          html += '<div style="font-size:.96rem;font-weight:800;color:var(--sal-dark);">' + escapeHtml(f.full_name || 'Contact') + '</div>';
          html += '<div style="font-size:.83rem;color:#687083;margin-top:.2rem;">' + escapeHtml(prettyRequest(f.request_type)) + ' &middot; Assigned to ' + escapeHtml(f.assigned_name || 'Officer') + '</div>';
          html += '</div>';
          html += '<div style="display:flex;gap:.45rem;align-items:center;flex-wrap:wrap;">';
          html += '<span style="background:#eef6f3;color:#225a49;font-size:.74rem;font-weight:800;padding:.28rem .6rem;border-radius:999px;">' + escapeHtml(f.followup_status || f.interest_status || 'Pending') + '</span>';
          html += '<a href="' + escapeHtml(f.whatsapp_url || '#') + '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:.4rem;background:#2f7a63;color:#fff;text-decoration:none;font-size:.78rem;font-weight:800;padding:.42rem .72rem;border-radius:999px;">';
          html += '<i class="bi bi-whatsapp"></i> ' + escapeHtml(f.phone || 'Open Chat') + '</a>';
          html += '</div>';
          html += '</div>';
          if (f.followup_date || f.remarks) {
            html += '<div style="margin-top:.7rem;font-size:.8rem;color:#6b7280;">';
            if (f.followup_date) html += '<strong style="color:#364152;">Follow-up Date:</strong> ' + escapeHtml(f.followup_date) + '<br>';
            if (f.remarks) html += '<strong style="color:#364152;">Remarks:</strong> ' + escapeHtml(f.remarks);
            html += '</div>';
          }
          html += '</div>';
        });
      } else {
        html = '<p style="text-align:center;color:#aaa;padding:2rem 0;">No tagged follow-up contacts for this church yet.</p>';
      }
      document.getElementById('rm-fu-body').innerHTML = html;
    });
  }

  function sc(label, val) {
    return '<div style="background:#fff;border-radius:5px;padding:.35rem .5rem;text-align:center;">' +
           '<div style="font-size:.68rem;font-weight:700;color:#999;">' + label + '</div>' +
           '<div style="font-size:1.05rem;font-weight:900;color:var(--sal-dark);">' + (val || 0) + '</div></div>';
  }
  function prettyRequest(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function showAlert(id, type, msg) {
    var el = document.getElementById(id);
    if (!el) return;
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.classList.remove('d-none');
  }
  function clearAlert(id) { var el = document.getElementById(id); if (el) el.classList.add('d-none'); }
  function setBusy(btnId, txtId, spinId, label, busy) {
    var btn = document.getElementById(btnId);
    var txt = document.getElementById(txtId);
    var spn = document.getElementById(spinId);
    if (btn) btn.disabled = busy;
    if (txt) txt.textContent = label;
    if (spn) spn.classList.toggle('d-none', !busy);
  }

  /* Re-allow history reload on next modal open */
  document.getElementById('reportModal').addEventListener('show.bs.modal', function () {
    histLoaded = false;
  });

})();

/* Navbar scroll effect */
window.addEventListener("scroll", function () {
  document.getElementById("mainNav").classList.toggle("scrolled", window.scrollY > 60);
});

/* Counter animation */
function animateCounters() {
  document.querySelectorAll(".counter-num").forEach(function (el) {
    var target = parseInt(el.getAttribute("data-target")) || 0;
    if (target === 0) { el.textContent = "0"; return; }
    var start = 0, duration = 1800, startTime = null;
    function step(ts) {
      if (!startTime) startTime = ts;
      var progress = Math.min((ts - startTime) / duration, 1);
      var ease = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.floor(ease * target);
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  });
}
var counterObserver = new IntersectionObserver(function (entries) {
  entries.forEach(function (e) {
    if (e.isIntersecting) { animateCounters(); counterObserver.disconnect(); }
  });
}, { threshold: 0.3 });
var counterSection = document.getElementById("counter");
if (counterSection) counterObserver.observe(counterSection);

/* Today's Attendance — refresh every minute; full page reload at midnight */
(function () {
  var lastDate = '<?= date('Y-m-d') ?>'; /* ISO date for midnight rollover detection */

  function fetchTodayAttendance() {
    fetch(BASE + '/api/stats/today_attendance.php?t=' + Date.now())
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || typeof d.total === 'undefined') return;
        var newDate = d.date || '';
        var ymd     = d.ymd  || '';

        function setCounter(id, val) {
          var el = document.getElementById(id);
          if (el) { el.setAttribute('data-target', val); el.textContent = val; }
        }
        function setDateLabel(id, label) {
          var el = document.getElementById(id);
          if (el && label) el.textContent = label;
        }

        setCounter('today-attendance-num', parseInt(d.total)  || 0);
        setCounter('today-visitors-num',   parseInt(d.visitors) || 0);
        setDateLabel('today-attendance-date', newDate);
        setDateLabel('today-visitors-date',   newDate);

        /* If the date rolled over, reload the full page */
        if (ymd && ymd !== lastDate) {
          lastDate = ymd;
          location.reload();
        }
      })
      .catch(function () {});
  }

  /* Poll every 60 seconds */
  setInterval(fetchTodayAttendance, 60000);
})();
</script>
<!-- ======= REGISTER STATION MODAL ======= -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">

      <div class="modal-header" style="background:var(--sal-dark);border:none;padding:1.2rem 1.6rem;">
        <h5 class="modal-title" id="registerModalLabel" style="color:#fff;font-weight:900;margin:0;">
          <i class="bi bi-building-add me-2" style="color:var(--sal-gold);"></i>Register Your Station
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-0">

        <!-- FORM SCREEN -->
        <div id="reg-screen-form" style="padding:1.8rem;">
          <p style="color:#555;margin-bottom:1.2rem;">Fill in your station details. Your coordinator mobile number will be your password for submitting meeting reports.</p>
          <div id="reg-alert" class="alert d-none mb-3"></div>
          <form id="reg-form" novalidate>
            <div class="row g-3">

              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Union <span style="color:#dc3545;">*</span></label>
                <select id="reg-union" name="union_id" class="form-select mt-1" required>
                  <option value="">Select union</option>
                  <?php foreach ($regUnions as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Conference / Field <span style="color:#dc3545;">*</span></label>
                <select id="reg-conference" name="conference_id" class="form-select mt-1" required>
                  <option value="">Select conference</option>
                  <?php foreach ($regConferences as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" data-union="<?= (int)$c['union_id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-8">
                <label style="font-weight:700;font-size:.9rem;">Station Name <span style="color:#dc3545;">*</span></label>
                <input type="text" name="name" class="form-control mt-1" placeholder="e.g. Kikwakwaru B" required>
              </div>

              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">Type <span style="color:#dc3545;">*</span></label>
                <select name="type" class="form-select mt-1" required>
                  <option>Church</option>
                  <option>Home</option>
                  <option>School</option>
                  <option>Group</option>
                  <option>Institution</option>
                </select>
              </div>

              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">Region <span style="color:#dc3545;">*</span></label>
                <input type="text" name="region" class="form-control mt-1" placeholder="e.g. Shinyanga" required>
              </div>
              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">District <span style="color:#dc3545;">*</span></label>
                <input type="text" name="district" class="form-control mt-1" placeholder="e.g. Kahama" required>
              </div>
              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">Ward <span style="color:#dc3545;">*</span></label>
                <input type="text" name="ward" class="form-control mt-1" placeholder="e.g. Busoka" required>
              </div>

              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Your Name (Coordinator) <span style="color:#dc3545;">*</span></label>
                <input type="text" name="coordinator_name" class="form-control mt-1" placeholder="Full name" required>
              </div>
              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Your Mobile <span style="font-weight:400;font-size:.82rem;color:#999;">(becomes password)</span> <span style="color:#dc3545;">*</span></label>
                <input type="tel" name="coordinator_phone" class="form-control mt-1" placeholder="e.g. 0712345678" required>
              </div>

              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Email <span style="font-weight:400;color:#aaa;">(optional)</span></label>
                <input type="email" name="coordinator_email" class="form-control mt-1" placeholder="coordinator@example.com">
              </div>
              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Place / Address <span style="font-weight:400;color:#aaa;">(optional)</span></label>
                <input type="text" id="reg-place" name="place" class="form-control mt-1" placeholder="Describe the exact location">
              </div>

              <!-- Map picker -->
              <div class="col-12">
                <label style="font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:.4rem;">
                  <i class="bi bi-geo-alt-fill" style="color:var(--sal-gold);"></i>
                  Pin Station Location on Map
                  <span style="font-weight:400;font-size:.78rem;color:#aaa;">&mdash; tap or search to place marker</span>
                </label>
                <!-- Search box with autocomplete -->
                <div style="position:relative;margin-top:.5rem;margin-bottom:.5rem;">
                  <input type="text" id="reg-map-search" class="form-control pe-5"
                         placeholder="Search a place (e.g. Nkome,Geita)"
                         autocomplete="off">
                  <button type="button" id="reg-map-search-btn"
                          style="position:absolute;right:0;top:0;height:100%;background:var(--sal-gold);border:none;border-radius:0 6px 6px 0;padding:0 .9rem;">
                    <span id="reg-search-icon"><i class="bi bi-search" style="color:var(--sal-dark);"></i></span>
                    <span id="reg-search-spin" class="spinner-border spinner-border-sm d-none" style="color:var(--sal-dark);width:1rem;height:1rem;"></span>
                  </button>
                  <ul id="reg-search-dropdown"
                      style="display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;
                             background:#fff;border:1.5px solid #ddd;border-top:none;
                             border-radius:0 0 8px 8px;max-height:220px;overflow-y:auto;
                             box-shadow:0 6px 20px rgba(0,0,0,.12);padding:0;margin:0;list-style:none;"></ul>
                </div>
                <div id="reg-map" style="height:280px;border-radius:8px;border:1.5px solid #ddd;overflow:hidden;"></div>
                <div id="reg-latlng-display" class="mt-1" style="font-size:.78rem;color:#aaa;text-align:right;"></div>
                <input type="hidden" id="reg-lat" name="latitude" value="">
                <input type="hidden" id="reg-lng" name="longitude" value="">
              </div>

              <div class="col-12 mt-1">
                <button type="submit" id="reg-submit-btn" class="btn btn-lg w-100"
                        style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;border-radius:8px;">
                  <span id="reg-sbtn-txt">Register Station</span>
                  <span id="reg-sbtn-spin" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
              </div>

            </div>
          </form>
        </div>

        <!-- SUCCESS SCREEN -->
        <div id="reg-screen-success" class="d-none" style="padding:2.5rem 1.8rem;text-align:center;">
          <div style="width:72px;height:72px;border-radius:50%;background:var(--sal-gold);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;">
            <i class="bi bi-check-lg" style="font-size:2.2rem;color:var(--sal-dark);"></i>
          </div>
          <h4 style="color:var(--sal-dark);font-weight:900;margin-bottom:.5rem;">Station Registered!</h4>
          <p style="color:#666;margin-bottom:1.6rem;">Your station has been created. Save these credentials &mdash; you will use them to submit meeting reports from this page.</p>

          <div style="background:var(--sal-dark);border-radius:10px;padding:1.4rem;text-align:left;margin-bottom:1.6rem;">
            <div style="font-size:.75rem;font-weight:800;color:rgba(255,255,255,.45);letter-spacing:1.5px;text-transform:uppercase;margin-bottom:.9rem;">Your Login Credentials</div>
            <div style="display:flex;gap:.7rem;align-items:flex-start;margin-bottom:.9rem;">
              <i class="bi bi-building" style="color:var(--sal-gold);font-size:1.1rem;margin-top:.15rem;"></i>
              <div>
                <div style="color:rgba(255,255,255,.45);font-size:.75rem;margin-bottom:.15rem;">Station Name (Login ID)</div>
                <div id="reg-success-name" style="color:#fff;font-weight:800;font-size:1rem;word-break:break-word;"></div>
              </div>
            </div>
            <div style="display:flex;gap:.7rem;align-items:flex-start;">
              <i class="bi bi-phone" style="color:var(--sal-gold);font-size:1.1rem;margin-top:.15rem;"></i>
              <div>
                <div style="color:rgba(255,255,255,.45);font-size:.75rem;margin-bottom:.15rem;">Password</div>
                <div id="reg-success-phone" style="color:#fff;font-weight:800;font-size:1rem;"></div>
              </div>
            </div>
          </div>

          <button type="button" id="reg-open-report" class="btn btn-lg w-100"
                  style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;border-radius:8px;margin-bottom:.7rem;">
            <i class="bi bi-clipboard2-pulse me-2"></i>Submit First Report
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>
</div>
<!-- ======= END REGISTER MODAL ======= -->

<script>
(function () {
  var BASE = '/Tumaini-Connect';

  /* --- Map picker --- */
  var regMap = null, regMarker = null;

  document.getElementById('registerModal').addEventListener('shown.bs.modal', function () {
    if (regMap) { regMap.invalidateSize(); return; }
    // Default centre: Tanzania
    regMap = L.map('reg-map').setView([-6.369028, 34.888822], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(regMap);

    regMap.on('click', function (e) { placeMarker(e.latlng.lat, e.latlng.lng, true); });
  });

  function placeMarker(lat, lng, reverseGeocode) {
    var latlng = L.latLng(lat, lng);
    if (regMarker) { regMarker.setLatLng(latlng); } else {
      regMarker = L.marker(latlng, { draggable: true }).addTo(regMap);
      regMarker.on('dragend', function () {
        var p = regMarker.getLatLng();
        setCoords(p.lat, p.lng);
        doReverseGeocode(p.lat, p.lng);
      });
    }
    setCoords(lat, lng);
    if (reverseGeocode) doReverseGeocode(lat, lng);
  }

  function setCoords(lat, lng) {
    document.getElementById('reg-lat').value = lat.toFixed(7);
    document.getElementById('reg-lng').value = lng.toFixed(7);
    document.getElementById('reg-latlng-display').textContent =
      '\u{1F4CD} ' + lat.toFixed(6) + ', ' + lng.toFixed(6);
  }

  function doReverseGeocode(lat, lng) {
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng, {
      headers: { 'Accept-Language': 'en' }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var addr = d.display_name || '';
        if (addr && !document.getElementById('reg-place').value) {
          document.getElementById('reg-place').value = addr;
        }
      })
      .catch(function () {});
  }

  /* Map search with live autocomplete */
  var searchInput   = document.getElementById('reg-map-search');
  var searchBtn     = document.getElementById('reg-map-search-btn');
  var searchDrop    = document.getElementById('reg-search-dropdown');
  var searchIcon    = document.getElementById('reg-search-icon');
  var searchSpin    = document.getElementById('reg-search-spin');
  var searchTimer   = null;
  var lastQuery     = '';

  function setSearchBusy(busy) {
    searchIcon.classList.toggle('d-none', busy);
    searchSpin.classList.toggle('d-none', !busy);
    searchBtn.disabled = busy;
  }

  function closeDropdown() {
    searchDrop.style.display = 'none';
    searchDrop.innerHTML = '';
  }

  function pickResult(r) {
    var lat = parseFloat(r.lat), lng = parseFloat(r.lon);
    searchInput.value = r.display_name || '';
    closeDropdown();
    if (regMap) {
      regMap.setView([lat, lng], 15);
      placeMarker(lat, lng, false);
      document.getElementById('reg-place').value = r.display_name || '';
      setCoords(lat, lng);
    }
  }

  function runSearch(q) {
    if (q.length < 3) { closeDropdown(); return; }
    setSearchBusy(true);
    fetch('https://nominatim.openstreetmap.org/search?format=json&q='
          + encodeURIComponent(q) + '&limit=6&addressdetails=0', {
      headers: { 'Accept-Language': 'en' }
    })
      .then(function (r) { return r.json(); })
      .then(function (results) {
        setSearchBusy(false);
        if ((searchInput.value || '').trim() !== q) return; // stale
        if (!results.length) {
          searchDrop.innerHTML = '<li style="padding:.65rem 1rem;color:#aaa;font-size:.85rem;">No results found</li>';
          searchDrop.style.display = 'block';
          return;
        }
        searchDrop.innerHTML = '';
        results.forEach(function (r) {
          var li = document.createElement('li');
          li.style.cssText = 'padding:.6rem 1rem;cursor:pointer;font-size:.85rem;border-bottom:1px solid #f0f0f0;line-height:1.35;';
          li.textContent = r.display_name || '';
          li.addEventListener('mouseenter', function () { li.style.background = '#fff8e6'; });
          li.addEventListener('mouseleave', function () { li.style.background = ''; });
          li.addEventListener('mousedown', function (e) { e.preventDefault(); pickResult(r); });
          searchDrop.appendChild(li);
        });
        searchDrop.style.display = 'block';
      })
      .catch(function () { setSearchBusy(false); closeDropdown(); });
  }

  searchInput.addEventListener('input', function () {
    var q = (this.value || '').trim();
    clearTimeout(searchTimer);
    if (q.length < 3) { closeDropdown(); return; }
    searchTimer = setTimeout(function () { runSearch(q); }, 350);
  });

  searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); clearTimeout(searchTimer); runSearch((this.value || '').trim()); }
    if (e.key === 'Escape') closeDropdown();
  });

  searchBtn.addEventListener('click', function () {
    clearTimeout(searchTimer);
    runSearch((searchInput.value || '').trim());
  });

  document.addEventListener('click', function (e) {
    if (!searchInput.contains(e.target) && !searchDrop.contains(e.target)) closeDropdown();
  });

  /* --- Union → Conference filter --- */
  var unionSel = document.getElementById('reg-union');
  var confSel  = document.getElementById('reg-conference');
  var allConfOpts = Array.from(confSel.options).map(function (o) {
    return { value: o.value, text: o.text, union: o.getAttribute('data-union') };
  });

  function filterConferences() {
    var selected = unionSel.value;
    confSel.innerHTML = '<option value="">Select conference</option>';
    allConfOpts.filter(function (o) {
      return o.value !== '' && (selected === '' || o.union === selected);
    }).forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o.value;
      opt.textContent = o.text;
      opt.setAttribute('data-union', o.union || '');
      confSel.appendChild(opt);
    });
  }
  unionSel.addEventListener('change', filterConferences);

  /* --- Form submit --- */
  document.getElementById('reg-form').addEventListener('submit', function (e) {
    e.preventDefault();
    setBusy(true, 'Registering\u2026');
    clearAlert();
    fetch(BASE + '/api/register/station.php', { method: 'POST', body: new FormData(this) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        setBusy(false, 'Register Station');
        if (d.ok) {
          document.getElementById('reg-success-name').textContent  = d.station_name;
          document.getElementById('reg-success-phone').textContent = d.coordinator_phone;
          document.getElementById('reg-screen-form').classList.add('d-none');
          document.getElementById('reg-screen-success').classList.remove('d-none');
        } else {
          showAlert('danger', d.error || 'Registration failed. Please try again.');
        }
      })
      .catch(function () {
        setBusy(false, 'Register Station');
        showAlert('danger', 'Network error. Please try again.');
      });
  });

  /* --- Submit First Report button --- */
  document.getElementById('reg-open-report').addEventListener('click', function () {
    bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
    document.getElementById('registerModal').addEventListener('hidden.bs.modal', function onHide() {
      document.getElementById('registerModal').removeEventListener('hidden.bs.modal', onHide);
      new bootstrap.Modal(document.getElementById('reportModal')).show();
    });
  });

  /* --- "Register here" link inside report modal --- */
  var gotoReg = document.getElementById('rm-goto-register');
  if (gotoReg) {
    gotoReg.addEventListener('click', function (e) {
      e.preventDefault();
      bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
      document.getElementById('reportModal').addEventListener('hidden.bs.modal', function onHide() {
        document.getElementById('reportModal').removeEventListener('hidden.bs.modal', onHide);
        new bootstrap.Modal(document.getElementById('registerModal')).show();
      });
    });
  }

  /* --- Reset on close --- */
  document.getElementById('registerModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('reg-screen-form').classList.remove('d-none');
    document.getElementById('reg-screen-success').classList.add('d-none');
    document.getElementById('reg-form').reset();
    document.getElementById('reg-lat').value = '';
    document.getElementById('reg-lng').value = '';
    document.getElementById('reg-latlng-display').textContent = '';
    document.getElementById('reg-map-search').value = '';
    closeDropdown();
    if (regMarker) { regMap.removeLayer(regMarker); regMarker = null; }
    filterConferences();
    clearAlert();
  });

  function setBusy(busy, label) {
    document.getElementById('reg-submit-btn').disabled = busy;
    document.getElementById('reg-sbtn-txt').textContent = label;
    document.getElementById('reg-sbtn-spin').classList.toggle('d-none', !busy);
  }
  function showAlert(type, msg) {
    var el = document.getElementById('reg-alert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.classList.remove('d-none');
  }
  function clearAlert() { document.getElementById('reg-alert').classList.add('d-none'); }
})();
</script>
<!-- ======= INTEREST FORM MODAL ======= -->
<div class="modal fade" id="interestModal" tabindex="-1" aria-labelledby="interestModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">

      <div class="modal-header" style="background:var(--sal-dark);border:none;padding:1.2rem 1.6rem;">
        <div>
          <h5 class="modal-title" id="interestModalLabel" style="color:#fff;font-weight:900;margin:0;">
            <i class="bi bi-heart-pulse me-2" style="color:var(--sal-gold);"></i>Submit Your Interest
          </h5>
          <div style="font-size:.82rem;color:rgba(255,255,255,.5);margin-top:.2rem;">We will follow up with care and support</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-0">

        <!-- FORM SCREEN -->
        <div id="int-screen-form" style="padding:1.8rem;">
          <div id="int-alert" class="alert d-none mb-3"></div>

          <!-- Station verify strip -->
          <div id="int-station-strip" style="background:#f8f7f4;border-radius:8px;padding:.9rem 1.1rem;margin-bottom:1.4rem;">
            <div id="int-strip-unverified" <?= $reportVerified ? 'class="d-none"' : '' ?>>
              <div style="font-size:.82rem;color:#888;margin-bottom:.6rem;">
                <i class="bi bi-info-circle me-1"></i>
                Are you a station coordinator? Verify your station to link this interest to your station.
              </div>
              <div class="row g-2">
                <div class="col-sm-5">
                  <input type="text" id="int-sname" class="form-control form-control-sm"
                         placeholder="Station Name" autocomplete="organization">
                </div>
                <div class="col-sm-5">
                  <input type="tel" id="int-phone" class="form-control form-control-sm"
                         placeholder="Coordinator Mobile" autocomplete="tel">
                </div>
                <div class="col-sm-2">
                  <button type="button" id="int-verify-btn" class="btn btn-sm w-100"
                          style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;">
                    <span id="int-vbtn-txt">Verify</span>
                    <span id="int-vbtn-spin" class="spinner-border spinner-border-sm d-none"></span>
                  </button>
                </div>
              </div>
            </div>
            <div id="int-strip-verified" <?= $reportVerified ? '' : 'class="d-none"' ?>
                 style="display:flex;align-items:center;gap:.7rem;">
              <i class="bi bi-check-circle-fill" style="color:#28a745;font-size:1.1rem;"></i>
              <div>
                <div style="font-size:.78rem;color:#888;">Submitting as coordinator of</div>
                <div id="int-station-name" style="font-weight:800;color:var(--sal-dark);font-size:.92rem;"><?= htmlspecialchars($reportCenterName, ENT_QUOTES) ?></div>
              </div>
              <button type="button" id="int-clear-station" class="btn btn-sm btn-outline-secondary ms-auto" style="font-size:.75rem;">Switch</button>
            </div>
          </div>

          <form id="int-form" novalidate>
            <input type="hidden" id="int-center-id" name="center_id" value="">
            <div class="row g-3">

              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Full Name <span style="color:#dc3545;">*</span></label>
                <input type="text" name="full_name" class="form-control mt-1" placeholder="Enter your full name" required>
              </div>
              <div class="col-md-3">
                <label style="font-weight:700;font-size:.9rem;">Gender</label>
                <select name="gender" class="form-select mt-1">
                  <option value="">Select</option>
                  <option>Male</option>
                  <option>Female</option>
                  <option>Other</option>
                </select>
              </div>
              <div class="col-md-3">
                <label style="font-weight:700;font-size:.9rem;">Age</label>
                <input type="number" name="age" min="1" max="120" class="form-control mt-1" placeholder="e.g. 28">
              </div>

              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Mobile Number <span style="color:#dc3545;">*</span></label>
                <input type="tel" name="phone" class="form-control mt-1" placeholder="e.g. 0712345678" required>
              </div>
              <div class="col-md-6">
                <label style="font-weight:700;font-size:.9rem;">Request Type <span style="color:#dc3545;">*</span></label>
                <select name="request_type" class="form-select mt-1" required>
                  <option value="">Select your interest</option>
                  <option value="baptism">Baptism</option>
                  <option value="bible_study">Bible Study</option>
                  <option value="prayer">Prayer Request</option>
                  <option value="visit">Church Visit</option>
                  <option value="church_connection">Church Connection</option>
                </select>
              </div>

              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">Region</label>
                <input type="text" name="region" class="form-control mt-1" placeholder="e.g. Arusha">
              </div>
              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">District</label>
                <input type="text" name="district" class="form-control mt-1" placeholder="e.g. Arusha">
              </div>
              <div class="col-md-4">
                <label style="font-weight:700;font-size:.9rem;">Ward</label>
                <input type="text" name="ward" class="form-control mt-1" placeholder="e.g. Njiro">
              </div>

              <div class="col-12">
                <label style="font-weight:700;font-size:.9rem;">Additional Notes <span style="font-weight:400;color:#aaa;">(optional)</span></label>
                <textarea name="notes" class="form-control mt-1" rows="3" placeholder="Any other information you would like to share..."></textarea>
              </div>

              <div class="col-12">
                <button type="submit" id="int-submit-btn" class="btn btn-lg w-100"
                        style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;border-radius:8px;">
                  <span id="int-sbtn-txt">Submit Interest</span>
                  <span id="int-sbtn-spin" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
              </div>

            </div>
          </form>
        </div>

        <!-- SUCCESS SCREEN -->
        <div id="int-screen-success" class="d-none" style="padding:3rem 1.8rem;text-align:center;">
          <div style="width:72px;height:72px;border-radius:50%;background:var(--sal-gold);display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;">
            <i class="bi bi-check-lg" style="font-size:2.2rem;color:var(--sal-dark);"></i>
          </div>
          <h4 style="color:var(--sal-dark);font-weight:900;margin-bottom:.5rem;">Request Received!</h4>
          <p style="color:#666;margin-bottom:1.8rem;">Thank you for reaching out. Our team will follow up with you shortly with care and support.</p>
          <button type="button" id="int-submit-another" class="btn btn-lg w-100"
                  style="background:var(--sal-gold);color:var(--sal-dark);font-weight:800;border:none;border-radius:8px;margin-bottom:.7rem;">
            <i class="bi bi-plus-circle me-2"></i>Submit Another
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>
</div>
<!-- ======= END INTEREST MODAL ======= -->

<script>
(function () {
  var BASE = '/Tumaini-Connect';
  var intVerified = <?= json_encode($reportVerified) ?>;
  var intCenterId = <?= json_encode($reportVerified ? (int)$_SESSION['report_center_id'] : 0) ?>;

  function setIntCenter(id, name) {
    intVerified = true;
    intCenterId = id;
    document.getElementById('int-center-id').value = id;
    document.getElementById('int-station-name').textContent = name;
    document.getElementById('int-strip-unverified').classList.add('d-none');
    document.getElementById('int-strip-verified').classList.remove('d-none');
  }

  /* Sync with report session on open */
  document.getElementById('interestModal').addEventListener('show.bs.modal', function () {
    if (intVerified && intCenterId) {
      document.getElementById('int-center-id').value = intCenterId;
    }
  });

  /* --- Verify station --- */
  document.getElementById('int-verify-btn').addEventListener('click', function () {
    var name  = (document.getElementById('int-sname').value  || '').trim();
    var phone = (document.getElementById('int-phone').value || '').trim();
    if (!name || !phone) {
      intShowAlert('danger', 'Enter both station name and mobile number.');
      return;
    }
    intSetBusy('int-verify-btn', 'int-vbtn-txt', 'int-vbtn-spin', 'Verifying…', true);
    var fd = new FormData();
    fd.append('station_name', name);
    fd.append('phone', phone);
    fetch(BASE + '/api/report/verify.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        intSetBusy('int-verify-btn', 'int-vbtn-txt', 'int-vbtn-spin', 'Verify', false);
        if (d.ok) {
          setIntCenter(d.center_id, d.center_name);
          intClearAlert();
        } else {
          intShowAlert('danger', d.error || 'Verification failed.');
        }
      })
      .catch(function () {
        intSetBusy('int-verify-btn', 'int-vbtn-txt', 'int-vbtn-spin', 'Verify', false);
        intShowAlert('danger', 'Network error. Please try again.');
      });
  });

  /* --- Switch / clear station --- */
  document.getElementById('int-clear-station').addEventListener('click', function () {
    intVerified = false;
    intCenterId = 0;
    document.getElementById('int-center-id').value = '';
    document.getElementById('int-sname').value = '';
    document.getElementById('int-phone').value = '';
    document.getElementById('int-strip-verified').classList.add('d-none');
    document.getElementById('int-strip-unverified').classList.remove('d-none');
  });

  /* --- Form submit --- */
  document.getElementById('int-form').addEventListener('submit', function (e) {
    e.preventDefault();
    intClearAlert();
    /* Validate required fields */
    var name  = this.querySelector('[name="full_name"]').value.trim();
    var phone = this.querySelector('[name="phone"]').value.trim();
    var type  = this.querySelector('[name="request_type"]').value;
    if (!name || !phone || !type) {
      intShowAlert('danger', 'Please fill in Full Name, Mobile Number and Request Type.');
      return;
    }
    intSetBusy('int-submit-btn', 'int-sbtn-txt', 'int-sbtn-spin', 'Submitting…', true);
    fetch(BASE + '/api/interest/submit.php', { method: 'POST', body: new FormData(this) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        intSetBusy('int-submit-btn', 'int-sbtn-txt', 'int-sbtn-spin', 'Submit Interest', false);
        if (d.ok) {
          document.getElementById('int-screen-form').classList.add('d-none');
          document.getElementById('int-screen-success').classList.remove('d-none');
        } else {
          intShowAlert('danger', d.error || 'Submission failed.');
        }
      })
      .catch(function () {
        intSetBusy('int-submit-btn', 'int-sbtn-txt', 'int-sbtn-spin', 'Submit Interest', false);
        intShowAlert('danger', 'Network error. Please try again.');
      });
  });

  /* --- Submit another --- */
  document.getElementById('int-submit-another').addEventListener('click', function () {
    document.getElementById('int-form').reset();
    document.getElementById('int-center-id').value = intCenterId || '';
    document.getElementById('int-screen-success').classList.add('d-none');
    document.getElementById('int-screen-form').classList.remove('d-none');
    intClearAlert();
  });

  /* --- Reset on close --- */
  document.getElementById('interestModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('int-form').reset();
    document.getElementById('int-center-id').value = intCenterId || '';
    document.getElementById('int-screen-success').classList.add('d-none');
    document.getElementById('int-screen-form').classList.remove('d-none');
    intClearAlert();
  });

  function intShowAlert(type, msg) {
    var el = document.getElementById('int-alert');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.classList.remove('d-none');
  }
  function intClearAlert() { document.getElementById('int-alert').classList.add('d-none'); }
  function intSetBusy(btnId, txtId, spinId, label, busy) {
    var btn = document.getElementById(btnId);
    var txt = document.getElementById(txtId);
    var spn = document.getElementById(spinId);
    if (btn) btn.disabled = busy;
    if (txt) txt.textContent = label;
    if (spn) spn.classList.toggle('d-none', !busy);
  }
})();
</script>
<!-- ======= LANGUAGE SWITCHER JS ======= -->
<script>
(function () {
  var TRANSLATIONS = {
    en: {
      /* Navbar */
      nav_about:       'About',
      nav_get_started: 'Get Started',
      nav_events:      'Events',
      nav_contact:     'Contact',
      nav_dashboard:   'Dashboard',
      nav_login:       'Admin Login',
      /* Hero */
      hero_sup:        'Seventh-day Adventist Church \u2014 Tanzania',
      hero_title:      'Hope, Community<br>&amp; <span>Outreach</span>',
      hero_desc:       'Tumaini Connect unites Seventh-day Adventist outreach stations across Tanzania to collect interests, coordinate follow-up care, and spread hope to every community.',
      hero_btn_report: 'Submit Report',
      hero_btn_interest: 'Submit Interest',
      /* Features strip */
      feat_stations_title:  'Stations',
      feat_stations_desc:   'Register and manage outreach stations across conferences and unions throughout Tanzania.',
      feat_stations_link:   'Register Now',
      feat_report_title:    'Attendance Report',
      feat_report_desc:     'Submit daily meeting attendance: elderly, children and church members \u2014 with photos or video.',
      feat_report_link:     'Submit Report',
      feat_interest_title:  'Submit Interest',
      feat_interest_desc:   'Share your interest in baptism, Bible study, prayer or a church visit \u2014 we will follow up with care.',
      feat_interest_link:   'Submit Interest',
      /* About */
      about_sup:   'Welcome to Tumaini Connect',
      about_title: 'Connect, Grow and<br>Serve With Us',
      about_p1:    'Tumaini Connect is an outreach coordination platform built for the Seventh-day Adventist Church of Tanzania to manage stations, collect interest reports, and coordinate compassionate follow-up care across every union and conference.',
      about_p2:    'From union administrators to station coordinators, everyone has a role. Together we track every person who reaches out \u2014 ensuring no one is left behind.',
      about_li1:   'Manage unions, conferences and local stations',
      about_li2:   'Collect and review interest requests in real time',
      about_li3:   'Assign and track follow-up visits',
      about_li4:   'Generate reports for leadership and planning',
      about_cta:        'Register Your Station',
      about_events_btn: 'View Upcoming Events',
      about_event_badge:'Upcoming Event',
      /* Counter */
      counter_sup:         'Our Impact',
      counter_title:       'We Are on a Mission<br>to Reach Every Individual',
      counter_stations:    'Stations',
      counter_interests:   'Interests Received',
      counter_conferences: 'Conferences',
      counter_followups:   'Follow-ups Done',
      /* Actions */
      actions_sup:   'Get Started',
      actions_title: 'Where Would You Like to Begin?',
      ac_admin_title:    'Admin Access',
      ac_admin_desc:     'Sign in as Tanzania admin, union admin, conference admin, coordinator or follow-up officer.',
      ac_admin_link:     'Login Now',
      ac_register_title: 'Register Station',
      ac_register_desc:  'Create a new station and set up a coordinator account \u2014 no admin login required.',
      ac_register_link:  'Register Now',
      ac_interest_title: 'Submit Interest',
      ac_interest_desc:  'Share your interest in baptism, Bible study, prayer or a church visit \u2014 we will follow up with you.',
      ac_interest_link:  'Open Form',
      /* Events */
      events_sup:   'Upcoming Gatherings',
      events_title: 'Latest Events',
      ev1_title: 'Saturday Fellowship &amp; Prayer',
      ev1_loc:   'All Regional Stations',
      ev1_desc:  'Join station groups for combined fellowship, prayer, and outreach planning.',
      ev2_title: 'Conference Leaders Meeting',
      ev2_loc:   'Union Headquarters',
      ev2_desc:  'Monthly review of interest requests, follow-up reports and station growth.',
      ev3_title: 'Outreach Coordinator Training',
      ev3_loc:   'Tanzania \u2014 All Regions',
      ev3_desc:  'Training for new station coordinators on using the platform and outreach best practices.',
      /* Mission */
      mission_sup:   'Seventh-day Adventist Church',
      mission_title: 'For the Glory of God<br>&amp; Wellbeing of Others',
      mission_p:     'Our outreach stations are the frontlines of hope. Each coordinator carries the responsibility of connecting people with care, prayer and follow-up support \u2014 making sure every interest request becomes a personal encounter.',
      mission_cta:   'Become a Coordinator',
      /* Values */
      val_community:     'Community',
      val_community_sub: 'United in purpose',
      val_care:          'Care',
      val_care_sub:      'Personal follow-up',
      val_reach:         'Reach',
      val_reach_sub:     'Across Tanzania',
      val_growth:        'Growth',
      val_growth_sub:    'Measured impact',
      /* Footer */
      footer_tagline: 'The official outreach coordination platform of the Seventh-day Adventist Church of Tanzania. Collecting interests, coordinating follow-ups, and spreading hope \u2014 one community at a time.',
    },
    sw: {
      /* Navbar */
      nav_about:       'Kuhusu',
      nav_get_started: 'Anza Hapa',
      nav_events:      'Matukio',
      nav_contact:     'Wasiliana',
      nav_dashboard:   'Dashibodi',
      nav_login:       'Ingia (Msimamizi)',
      /* Hero */
      hero_sup:        'Kanisa la Waadventista wa Sabato \u2014 Tanzania',
      hero_title:      'Tumaini, Ushirika<br>&amp; <span>Uinjilisti</span>',
      hero_desc:       'Tumaini Connect inaunganisha vituo vya utume vya Waadventista wa Sabato Tanzania ili kukusanya maombi, kuratibu ufuatiliaji, na kusambaza tumaini katika kila ushirika.',
      hero_btn_report:   'Wasilisha Ripoti',
      hero_btn_interest: 'Toa Nia',
      /* Features strip */
      feat_stations_title:  'Vituo',
      feat_stations_desc:   'Sajili na simamia vituo vya utume katika konferensi na muungano wote wa Kanisa la Waadventista wa Sabato Tanzania.',
      feat_stations_link:   'Sajili Sasa',
      feat_report_title:    'Ripoti ya Mahudhurio',
      feat_report_desc:     'Wasilisha mahudhurio ya mkutano wa kila siku: wazee, watoto na wanakanisa \u2014 na picha au video.',
      feat_report_link:     'Wasilisha Ripoti',
      feat_interest_title:  'Toa Nia',
      feat_interest_desc:   'Shiriki nia yako ya ubatizo, masomo ya Biblia, sala au kuhudhuria kanisa \u2014 tutafuatilia kwa upendo.',
      feat_interest_link:   'Toa Nia',
      /* About */
      about_sup:   'Karibu Tumaini Connect',
      about_title: 'Unganika, Kukua na<br>Kutumika Pamoja',
      about_p1:    'Tumaini Connect ni jukwaa la uratibu wa utume lililoundwa na Kanisa la Waadventista wa Sabato Tanzania kusimamia vituo, kukusanya ripoti za nia, na kuratibu ufuatiliaji wa huruma katika kila muungano na konferensi.',
      about_p2:    'Kuanzia wasimamizi wa Union hadi waratibu wa vituo, kila mtu ana jukumu. Pamoja tunafuatilia kila mtu anayewasiliana \u2014 kuhakikisha hakuna anayeachwa nyuma.',
      about_li1:   'Simamia Union, konferensi na vituo vya ndani',
      about_li2:   'Kusanya na kukagua maombi ya nia kwa wakati halisi',
      about_li3:   'Gawanya na fuatilia ziara za ufuatiliaji',
      about_li4:   'Tengeneza ripoti kwa uongozi na mipango',
      about_cta:        'Sajili Kituo Chako',
      about_events_btn: 'Tazama Matukio Yajayo',
      about_event_badge:'Tukio Lijalo',
      /* Counter */
      counter_sup:         'Athari Yetu',
      counter_title:       'Tuko katika Utume<br>wa Kufikia Kila Mtu',
      counter_stations:    'Vituo',
      counter_interests:   'Nia Zilizopokelewa',
      counter_conferences: 'Konferensi',
      counter_followups:   'Ufuatiliaji Uliokamilika',
      /* Actions */
      actions_sup:   'Anza Hapa',
      actions_title: 'Ungependa Kuanza Wapi?',
      ac_admin_title:    'Ufikiaji wa Msimamizi',
      ac_admin_desc:     'Ingia kama msimamizi wa Tanzania, Unioni, konferensi, mratibu au afisa ufuatiliaji.',
      ac_admin_link:     'Ingia Sasa',
      ac_register_title: 'Sajili Kituo',
      ac_register_desc:  'Unda kituo kipya na usanidi akaunti ya mratibu \u2014 hakuna kuingia kama msimamizi.',
      ac_register_link:  'Sajili Sasa',
      ac_interest_title: 'Toa Nia',
      ac_interest_desc:  'Shiriki nia yako ya ubatizo, masomo ya Biblia, sala au ziara ya kanisa \u2014 tutafuatilia nawe.',
      ac_interest_link:  'Fungua Fomu',
      /* Events */
      events_sup:   'Makusanyiko Yajayo',
      events_title: 'Matukio ya Hivi Karibuni',
      ev1_title: 'Ushirika wa Jumamosi &amp; Sala',
      ev1_loc:   'Vituo Vyote vya Kikanda',
      ev1_desc:  'Jiunge na makundi ya vituo kwa ushirika wa pamoja, sala, na mipango ya uinjilisti.',
      ev2_title: 'Mkutano wa Viongozi wa Konferensi',
      ev2_loc:   'Makao Makuu ya Muungano',
      ev2_desc:  'Ukaguzi wa kila mwezi wa maombi ya nia, ripoti za ufuatiliaji na ukuaji wa vituo.',
      ev3_title: 'Mafunzo ya Waratibu wa Uinjilisti',
      ev3_loc:   'Tanzania \u2014 Mikoa Yote',
      ev3_desc:  'Mafunzo kwa waratibu wapya wa vituo juu ya kutumia jukwaa na mbinu bora za uinjilisti.',
      /* Mission */
      mission_sup:   'Kanisa la Waadventista wa Sabato',
      mission_title: 'Kwa Utukufu wa Mungu<br>&amp; Ustawi wa Wengine',
      mission_p:     'Vituo vyetu vya utume ni mstari wa mbele wa tumaini. Kila mratibu anabeba jukumu la kuunganisha watu na huduma, sala na msaada wa ufuatiliaji \u2014 kuhakikisha kila ombi la nia linakuwa mkutano wa kibinafsi.',
      mission_cta:   'Kuwa Mratibu',
      /* Values */
      val_community:     'Ushirika',
      val_community_sub: 'Umoja wa lengo',
      val_care:          'Huduma',
      val_care_sub:      'Ufuatiliaji wa kibinafsi',
      val_reach:         'Ufikio',
      val_reach_sub:     'Kote Tanzania',
      val_growth:        'Ukuaji',
      val_growth_sub:    'Athari inayopimika',
      /* Footer */
      footer_tagline: 'Jukwaa rasmi la uratibu wa utume la Kanisa la Waadventista wa Sabato Tanzania. Kukusanya nia, kuratibu ufuatiliaji, na kusambaza tumaini \u2014 ushirika mmoja kwa wakati mmoja.',
    }
  };

  var currentLang = localStorage.getItem('tc_lang') || 'en';
  var btn = document.getElementById('lang-toggle');

  function applyLang(lang) {
    currentLang = lang;
    localStorage.setItem('tc_lang', lang);
    var t = TRANSLATIONS[lang] || TRANSLATIONS['en'];
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      var key = el.getAttribute('data-i18n');
      if (t[key] !== undefined) {
        el.innerHTML = t[key];
      }
    });
    document.documentElement.lang = lang === 'sw' ? 'sw' : 'en';
    btn.textContent = lang === 'sw' ? 'EN' : 'SW';
  }

  btn.addEventListener('click', function () {
    applyLang(currentLang === 'en' ? 'sw' : 'en');
  });

  /* Apply on load */
  applyLang(currentLang);
})();
</script>

<script>
/* ---- Hero background slideshow ---- */
(function () {
  var images = [
    '/Tumaini-Connect/assets/images/home.jpg',
    '/Tumaini-Connect/assets/images/home1.jpg',
    '/Tumaini-Connect/assets/images/home3.jpg'
  ];
  var current = 0;
  var hero = document.getElementById('hero');
  var bg     = document.getElementById('hero-bg');
  var bgNext = document.getElementById('hero-bg-next');

  if (!bg || !bgNext) return;

  /* Set initial image */
  bg.style.backgroundImage = 'url("' + images[0] + '")';

  /* Preload all images */
  images.forEach(function (src) {
    var img = new Image(); img.src = src;
  });

  setInterval(function () {
    var next = (current + 1) % images.length;
    bgNext.style.backgroundImage = 'url("' + images[next] + '")';
    bgNext.style.opacity = '1';

    setTimeout(function () {
      bg.style.backgroundImage = bgNext.style.backgroundImage;
      bgNext.style.opacity = '0';
      current = next;
    }, 1300);
  }, 10000);
})();
</script>
</body>
</html>