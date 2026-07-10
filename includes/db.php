<?php
declare(strict_types=1);

$DB_HOST = getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1';
$DB_USER = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$DB_NAME = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'tumaini_connect_db';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed.');
}

$conn->set_charset('utf8mb4');
