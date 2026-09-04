-- Minister's Weekly Meeting Scheduler — database schema
-- Run automatically by install.php, or import manually via phpMyAdmin.

CREATE DATABASE IF NOT EXISTS honsam_meetings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE honsam_meetings;

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS meetings (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  title                  VARCHAR(200) NOT NULL,
  event_type             ENUM('inhouse','trip') NOT NULL DEFAULT 'inhouse',
  meeting_date           DATE NOT NULL,
  end_date               DATE NULL,          -- same as meeting_date for in-house; return date for trips
  start_time             TIME NULL,          -- required for in-house; optional departure time for trips
  end_time               TIME NULL,          -- required for in-house; optional return time for trips
  venue                  VARCHAR(200) NOT NULL,   -- venue for in-house, destination for trips
  agenda                 TEXT NULL,
  attendees              TEXT NULL,
  reminder_sent          TINYINT(1) NOT NULL DEFAULT 0,
  reminder_hours_before  INT NOT NULL DEFAULT 24,
  created_by             INT NULL,
  created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_meeting_date (meeting_date),
  INDEX idx_end_date (end_date),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
