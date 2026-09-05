-- Minister's Weekly Meeting Scheduler — database schema
-- Run automatically by install.php, or import manually via phpMyAdmin.

CREATE DATABASE IF NOT EXISTS honsam_meetings CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE honsam_meetings;

-- A ministry office (tenant). Each office's meetings/staff/users are isolated
-- to its own ministry_id — see includes/auth.php's resolve_ministry_id().
CREATE TABLE IF NOT EXISTS ministries (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(200) NOT NULL,             -- e.g. "Office of the Minister for Relief, Disaster Preparedness and Refugees"
  minister_name  VARCHAR(150) NOT NULL,             -- e.g. "Hon. Sam Engola"
  minister_photo VARCHAR(255) NULL,                 -- path relative to BASE_URL, e.g. "assets/img/min1.jpg"
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  role          ENUM('super_admin','office_admin') NOT NULL DEFAULT 'office_admin',
  ministry_id   INT NULL,                           -- NULL only for super_admin (platform-wide, not tied to one office)
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ministry_id) REFERENCES ministries(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS meetings (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  ministry_id            INT NOT NULL,
  title                  VARCHAR(200) NOT NULL,
  event_type             ENUM('inhouse','trip') NOT NULL DEFAULT 'inhouse',
  meeting_date           DATE NOT NULL,
  end_date               DATE NULL,          -- same as meeting_date for in-house; return date for trips
  start_time             TIME NULL,          -- required for in-house; optional departure time for trips
  end_time               TIME NULL,          -- required for in-house; optional return time for trips
  venue                  VARCHAR(200) NOT NULL,   -- venue for in-house, destination for trips
  agenda                 TEXT NULL,
  attendees              TEXT NULL,
  contact                VARCHAR(150) NULL,       -- contact person for this meeting/trip, e.g. "0775004767 - Charlot"
  notes                  TEXT NULL,               -- important details / things to check, separate from the agenda
  reminder_sent          TINYINT(1) NOT NULL DEFAULT 0,
  reminder_hours_before  INT NOT NULL DEFAULT 24,
  created_by             INT NULL,
  created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ministry_id (ministry_id),
  INDEX idx_meeting_date (meeting_date),
  INDEX idx_end_date (end_date),
  FOREIGN KEY (ministry_id) REFERENCES ministries(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Staff who can be picked as the accompanying team on a meeting/trip.
-- Managed via staff.php, scoped per ministry.
CREATE TABLE IF NOT EXISTS staff (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  ministry_id INT NOT NULL,
  name        VARCHAR(150) NOT NULL,
  active      TINYINT(1) NOT NULL DEFAULT 1,   -- inactive staff are hidden from the picker but kept for history
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ministry_id (ministry_id),
  FOREIGN KEY (ministry_id) REFERENCES ministries(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Many-to-many: which staff are accompanying a given meeting/trip.
CREATE TABLE IF NOT EXISTS meeting_staff (
  meeting_id INT NOT NULL,
  staff_id   INT NOT NULL,
  PRIMARY KEY (meeting_id, staff_id),
  FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB;
