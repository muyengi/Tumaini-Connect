<?php
declare(strict_types=1);

$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'tumaini_connect_db';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed.');
}

$conn->set_charset('utf8mb4');
