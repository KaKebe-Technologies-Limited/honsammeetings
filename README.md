# Minister's Weekly Meeting Scheduler

A simple, clean PHP + MySQL platform for managing and displaying the
Minister's weekly meeting schedule — built for
**Office of the Minister for Relief, Disaster Preparedness and Refugees**.

## Features

- Login-protected dashboard (only authorized staff can create/edit/delete)
- Create / edit / delete meetings and trips (date, time, venue, agenda,
  attendees, contact person, notes, accompanying staff team)
- Manage a Staff list (`staff.php`) so a meeting/trip's accompanying team can
  be picked from a checklist instead of typed freehand
- Duration auto-calculated from start/end time
- Weekly (Mon–Fri) schedule view with week navigation, color-coded for
  meetings coming up soon
- Dashboard notification badge for meetings due soon
- Printer-friendly weekly schedule and single-meeting print views
- Branded HTML email reminders sent ahead of each meeting (per-item lead
  time), plus a one-click "Remind now" button on every meeting card
- One-click "Email Weekly Program (PDF)" — sends the current week's full
  schedule as a formatted PDF to every registered user

## Requirements

- XAMPP (Apache + PHP 8+ + MySQL/MariaDB) — you already have this, since the
  project lives in `C:\xampp\htdocs\honsammeetings`.

## Setup (local development)

1. **Start Apache and MySQL** in the XAMPP Control Panel.
2. Add the Minister's photo: save it as
   `assets/img/min1.jpg` (see `assets/img/README.txt`). It appears circular
   in the header/login page, and top-right on printed sheets. The filename
   is set once in `config.php` (`MINISTER_PHOTO`) — change both together if
   you swap the photo for a different file.
3. Import `schema.sql` into MySQL (via phpMyAdmin, or
   `mysql -u root < schema.sql`) — creates the `honsam_meetings` database
   and all its tables, and seeds the standing Staff roles.
4. Create your first admin user directly in the `users` table (username,
   `password_hash` via PHP's `password_hash()`, full name, email).
5. Copy `mail_secret.sample.php` to `mail_secret.php` and fill in real SMTP
   credentials (see that file for instructions).
6. Log in at `http://localhost/honsammeetings/login.php`.

Locally, `config.php` uses XAMPP defaults (`root` / no password / database
`honsam_meetings`) automatically — no edits needed unless your local MySQL
setup differs.

## Deploying to production (opmschedules.site)

The whole project — including `config.php` — is pushed via git, but the
live database password and the live `SITE_URL`/`BASE_URL` must **never** be
committed. `config.php` handles this the same way it already does for SMTP:

- Locally, no extra file is needed — `config.php` falls back to the XAMPP
  defaults shown above.
- On the live server, copy `db_secret.sample.php` to `db_secret.php` (same
  folder as `config.php`) and fill in the real live database host/name/user/
  password, plus `BASE_URL` and `SITE_URL` for that domain. This file is
  gitignored — it only ever exists on the server it's meant for, never in
  the repo, and never on a local dev machine (its mere presence is what
  switches `config.php` from local defaults to production).
- Likewise copy `mail_secret.sample.php` to `mail_secret.php` on the live
  server with real SMTP credentials.
- Import `schema.sql` into the live database once, the same way as locally.

So a normal deploy is: `git push` to update the code, then nothing else —
`db_secret.php` and `mail_secret.php` already live on the server and are
untouched by the push.

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
