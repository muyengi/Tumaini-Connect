<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');

unset($_SESSION['report_center_id'], $_SESSION['report_center_name']);

echo json_encode(['ok' => true]);
