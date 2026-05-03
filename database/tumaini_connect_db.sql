-- Tumaini Connect Database Schema
-- MySQL 8+

CREATE DATABASE IF NOT EXISTS tumaini_connect_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tumaini_connect_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS followups;
DROP TABLE IF EXISTS interests;
DROP TABLE IF EXISTS centers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS conferences;
DROP TABLE IF EXISTS unions;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE unions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE conferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    union_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('Conference', 'Field') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conferences_union FOREIGN KEY (union_id) REFERENCES unions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_conference_name_per_union (union_id, name),
    KEY idx_conferences_union (union_id)
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('tanzania_admin', 'union_admin', 'conference_admin', 'coordinator', 'followup') NOT NULL,
    union_id INT UNSIGNED NULL,
    conference_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_union FOREIGN KEY (union_id) REFERENCES unions(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_conference FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE SET NULL,
    KEY idx_users_role (role),
    KEY idx_users_union (union_id),
    KEY idx_users_conference (conference_id)
) ENGINE=InnoDB;

CREATE TABLE centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    union_id INT UNSIGNED NOT NULL,
    conference_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    type ENUM('Church', 'Home', 'School', 'Group', 'Institution') NOT NULL,
    region VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    ward VARCHAR(100) NOT NULL,
    coordinator_name VARCHAR(120) NOT NULL,
    coordinator_phone VARCHAR(30) NULL,
    coordinator_email VARCHAR(120) NULL,
    meeting_time VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_centers_union FOREIGN KEY (union_id) REFERENCES unions(id) ON DELETE CASCADE,
    CONSTRAINT fk_centers_conference FOREIGN KEY (conference_id) REFERENCES conferences(id) ON DELETE CASCADE,
    KEY idx_centers_union (union_id),
    KEY idx_centers_conference (conference_id),
    KEY idx_centers_region (region)
) ENGINE=InnoDB;

CREATE TABLE interests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    gender VARCHAR(20) NOT NULL,
    age INT UNSIGNED NULL,
    phone VARCHAR(30) NOT NULL,
    request_type ENUM('baptism', 'bible_study', 'prayer', 'visit', 'church_connection') NOT NULL,
    additional_notes TEXT NULL,
    status ENUM('pending', 'assigned', 'completed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_interests_center FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE CASCADE,
    KEY idx_interests_center (center_id),
    KEY idx_interests_status (status),
    KEY idx_interests_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE followups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interest_id INT UNSIGNED NOT NULL,
    assigned_to INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Pending',
    remarks TEXT NULL,
    followup_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_followups_interest FOREIGN KEY (interest_id) REFERENCES interests(id) ON DELETE CASCADE,
    CONSTRAINT fk_followups_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_followup_interest (interest_id),
    KEY idx_followups_assigned_to (assigned_to),
    KEY idx_followups_status (status)
) ENGINE=InnoDB;

INSERT INTO users (name, email, password, role)
VALUES ('Tanzania Admin', 'admin@tumaini.or.tz', '$2y$10$.wuNGTtvuK1AgH0TXFEbReiC01bnY.8HLMgKeela4OGOUEcYnxqOu', 'tanzania_admin');
