# OPM Schedules

A multi-tenant PHP + MySQL platform for Ministry offices across the
Government of Uganda to manage and display their weekly meeting schedule.
Each ministry's meetings, staff, and users are completely isolated from
every other office (see **Multi-tenancy & roles** below). Office of the
Minister for Relief, Disaster Preparedness and Refugees / Hon. Sam Engola is
the original, reference office the app was built around.

## Features

- Login-protected dashboard, scoped to the signed-in user's own ministry
- Create / edit / delete meetings and trips (date, time, venue, agenda,
  attendees, contact person, notes, accompanying staff team)
- Manage a Staff list (`staff.php`) so a meeting/trip's accompanying team can
  be picked from a checklist instead of typed freehand
- Duration auto-calculated from start/end time
- Weekly (Mon–Fri) schedule view with week navigation, color-coded for
  meetings coming up soon; scheduling clashes are allowed but flagged
  (⚠ Clash), never silently blocked
- Dashboard notification badge for meetings due soon
- Printer-friendly weekly schedule and single-meeting print views
- Branded HTML email reminders sent ahead of each meeting (per-item lead
  time), plus a one-click "Remind now" button on every meeting card
- One-click "Email Weekly Program (PDF)" — sends the current week's full
  schedule as a formatted, branded PDF to every registered user in that
  ministry
- A platform-wide **Super Admin** area (`admin_dashboard.php`) — Uganda
  flag–themed, separate from the per-ministry app — to onboard new
  ministries, manage every user across every office, and reset passwords
  (the one thing no Office Admin can do)

## Multi-tenancy & roles

Every ministry office is a row in the `ministries` table (name, minister
name, minister photo). `meetings` and `staff` each carry a `ministry_id`,
and every query that touches them is scoped by it — see
`includes/auth.php`'s `resolve_ministry_id()` and `includes/functions.php`'s
`ministry_by_id()`/`current_ministry()`.

Two fixed roles (`users.role`):

- **`office_admin`** — everything this README describes above: full CRUD on
  their own ministry's meetings, staff, and schedule. Cannot see other
  offices, cannot manage users, cannot reset passwords.
- **`super_admin`** — not tied to any one ministry (`ministry_id` is
  `NULL`). Gets the separate admin area (`admin_dashboard.php`,
  `admin_ministries.php`, `admin_users.php`) to onboard ministries, manage
  every user platform-wide, and is the only role that can reset a
  password. A super_admin can also open any specific ministry's normal
  schedule/meetings/staff pages by adding `?ministry_id=<id>` — the app's
  nav links carry that through automatically once you're viewing one.

## Requirements

- XAMPP (Apache + PHP 8+ + MySQL/MariaDB) — you already have this, since the
  project lives in `C:\xampp\htdocs\honsammeetings`.

## Setup (local development)

1. **Start Apache and MySQL** in the XAMPP Control Panel.
2. Import `schema.sql` into MySQL (via phpMyAdmin, or
   `mysql -u root < schema.sql`) — creates the `honsam_meetings` database
   and all its tables (`ministries`, `users`, `meetings`, `staff`,
   `meeting_staff`), and seeds the standing Staff roles for ministry #1.
3. Create your first ministry row in the `ministries` table (name, minister
   name, and — optionally — a photo path such as `assets/img/min1.jpg`,
   with the actual file placed there; see `assets/img/README.txt`).
4. Create your first users directly in the `users` table: at least one
   `super_admin` (leave `ministry_id` NULL) to reach the admin area, and/or
   an `office_admin` tied to the ministry from step 3 (`password_hash` via
   PHP's `password_hash()`).
5. Copy `mail_secret.sample.php` to `mail_secret.php` and fill in real SMTP
   credentials (see that file for instructions).
6. Log in at `http://localhost/honsammeetings/login.php` — a `super_admin`
   lands on the admin dashboard, an `office_admin` lands on their ministry's
   own dashboard.

Locally, `config.php` uses XAMPP defaults (`root` / no password / database
`honsam_meetings`) automatically — no edits needed unless your local MySQL
setup differs.

### Upgrading an existing single-tenant install

If your database predates the `ministries` table, run a one-off migration
script that adds `ministries` + `ministry_id` to `meetings`/`staff`,
backfills all existing data to ministry #1, and creates a first
`super_admin` user (credentials printed once — save them immediately).
Ask for that script's latest copy rather than hand-rolling the ALTER
statements — it's idempotent, so it's safe to re-run.

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
- Import `schema.sql` into the live database once, the same way as locally
  (or, if the live database already has an older single-tenant version of
  this schema, run the one-off multi-tenant migration script instead — see
  **Upgrading an existing single-tenant install** above — then delete it
  from the server).

So a normal deploy after that first-time setup is: `git push` to update the
code, then nothing else — `db_secret.php` and `mail_secret.php` already
live on the server and are untouched by the push.

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
config.php                     Environment (DB, SMTP) + app settings
db_secret.sample.php            Template for live-server DB/URL overrides
mail_secret.sample.php          Template for live SMTP credentials
schema.sql                      Raw SQL schema (ministries, users, meetings, staff, meeting_staff)
includes/
  db.php / auth.php / functions.php   Connection, session/roles/tenant-scoping, everything else
  mailer.php                    Self-contained SMTP client (no external deps)
  pdf.php                       Weekly program PDF builder (FPDF, includes/vendor/fpdf)
  header.php / footer.php       Per-ministry app shell (black/gold branding)
  admin_header.php / admin_footer.php   Super Admin shell (Uganda flag colors)
  meeting_card.php              Shared meeting/trip card partial

Per-ministry app (scoped to the signed-in office_admin's own ministry):
  home.php / login.php / logout.php   Public landing + authentication
  index.php                     Dashboard
  schedule.php                   Weekly Mon–Fri schedule view
  meetings.php                    All meetings list + search
  meeting_edit.php / meeting_delete.php / meeting_remind.php
  staff.php / staff_edit.php / staff_delete.php
  print_weekly.php / print_meeting.php   Printer-friendly views
  send_weekly_program.php         Emails the week's PDF program on demand
  cron_reminders.php               Sends reminders due across every ministry

Super Admin platform area (super_admin role only):
  admin_dashboard.php             Platform-wide overview
  admin_ministries.php / admin_ministry_edit.php   Ministry CRUD
  admin_users.php / admin_user_edit.php / admin_user_delete.php   User CRUD + password resets

assets/css/style.css            Per-ministry app stylesheet
assets/css/admin.css             Super Admin stylesheet
assets/css/print.css              Print stylesheet
assets/img/                        Ministry photos (min1.jpg, ministries/*)
```
