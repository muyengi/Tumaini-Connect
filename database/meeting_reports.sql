-- Meeting Attendance Reports table
-- Run this against tumaini_connect_db to add the new table.

USE tumaini_connect_db;

CREATE TABLE IF NOT EXISTS meeting_reports (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center_id    INT UNSIGNED NOT NULL,
    report_date  DATE         NOT NULL,
    elderly_men  INT UNSIGNED NOT NULL DEFAULT 0,
    elderly_women INT UNSIGNED NOT NULL DEFAULT 0,
    children_boys  INT UNSIGNED NOT NULL DEFAULT 0,
    children_girls INT UNSIGNED NOT NULL DEFAULT 0,
    members_attended INT UNSIGNED NOT NULL DEFAULT 0,
    images       TEXT         NULL COMMENT 'JSON array of relative file paths',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mr_center (center_id),
    KEY idx_mr_date   (report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
