# Minister's Weekly Meeting Scheduler

A simple, clean PHP + MySQL platform for managing and displaying the
Minister's weekly meeting schedule — built for
**Office of the Minister for Relief, Disaster Preparedness and Refugees**.

## Features

- Login-protected dashboard (only authorized staff can create/edit/delete)
- Create / edit / delete meetings (date, time, venue, agenda, attendees)
- Duration auto-calculated from start/end time
- Weekly (Mon–Sun) schedule view with week navigation, color-coded for
  meetings coming up in the next 24 hours
- Dashboard notification badge for meetings due soon
- Printer-friendly weekly schedule and single-meeting print views
- Email reminders sent ~24 hours before each meeting (via a small script
  you run on a schedule)

## Requirements

- XAMPP (Apache + PHP 8+ + MySQL/MariaDB) — you already have this, since the
  project lives in `C:\xampp\htdocs\honsammeetings`.

## Setup

1. **Start Apache and MySQL** in the XAMPP Control Panel.
2. Add the Minister's photo: save it as
   `assets/img/min1.jpg` (see `assets/img/README.txt`). It appears circular
   in the header/login page, and top-right on printed sheets. The filename
   is set once in `config.php` (`MINISTER_PHOTO`) — change both together if
   you swap the photo for a different file.
3. In your browser go to:
   `http://localhost/honsammeetings/install.php`
   This creates the `honsam_meetings` database, its tables, and lets you
   create the first admin account (full name, username, email, password).
4. After the admin account is created, **delete or rename `install.php`**
   (leaving it live is a security risk).
5. Log in at `http://localhost/honsammeetings/login.php`.

If your XAMPP MySQL uses a different user/password, edit `config.php`
(`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) before running the installer.

## Email reminders

`cron_reminders.php` finds meetings starting within the next 24 hours that
haven't been notified yet, emails every registered user, and marks them as
sent. It uses PHP's built-in `mail()`, so on local XAMPP you'll need to
configure `php.ini` / `sendmail.ini` to point at a real SMTP server (e.g.
Gmail SMTP) for mail to actually leave the machine — otherwise the script
still runs and tracks reminders, it just won't deliver mail.

Schedule it to run every 15–30 minutes with **Windows Task Scheduler**:

- Program/script: `C:\xampp\php\php.exe`
- Arguments: `C:\xampp\htdocs\honsammeetings\cron_reminders.php`

You can also run it manually any time from a terminal:

```bash
C:/xampp/php/php.exe C:/xampp/htdocs/honsammeetings/cron_reminders.php
```

## Project structure

```
config.php            Database + app settings
includes/              Shared PHP (db, auth, helpers, header/footer)
install.php            One-time setup wizard (delete after use)
schema.sql              Raw SQL schema (for reference / manual import)
login.php / logout.php  Authentication
index.php               Dashboard
schedule.php             Weekly Mon–Sun schedule view
meetings.php              All meetings list + search
meeting_edit.php           Add / edit meeting form
meeting_delete.php          Delete a meeting
print_weekly.php             Printer-friendly weekly schedule
print_meeting.php             Printer-friendly single meeting
cron_reminders.php            Sends 24h-before email reminders
assets/css/style.css           Main stylesheet
assets/css/print.css            Print stylesheet
assets/img/min1.jpg               Minister's photo
```
