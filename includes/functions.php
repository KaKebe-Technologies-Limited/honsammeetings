<?php
require_once __DIR__ . '/db.php';

/**
 * Monday..Friday work-week date range (Y-m-d) containing $reference
 * (default: today), optionally shifted by $weekOffset whole weeks.
 */
function week_range(string $reference = 'today', int $weekOffset = 0): array
{
    $ref = new DateTime($reference);
    $ref->modify($weekOffset . ' week');
    $dow = (int) $ref->format('N'); // 1 (Mon) .. 7 (Sun)
    $monday = (clone $ref)->modify('-' . ($dow - 1) . ' days');
    $friday = (clone $monday)->modify('+4 days');
    return [$monday->format('Y-m-d'), $friday->format('Y-m-d')];
}

function fmt_date_long(string $ymd): string
{
    return (new DateTime($ymd))->format('l, jS F Y');
}

function fmt_date_short(string $ymd): string
{
    return (new DateTime($ymd))->format('D, j M Y');
}

function fmt_date_medium(string $ymd): string
{
    return (new DateTime($ymd))->format('jS M Y');
}

function fmt_time(?string $hms): string
{
    if (!$hms) return '';
    return (new DateTime($hms))->format('g:i A');
}

/** In-house meeting duration, e.g. "1h 30m". */
function duration_label(?string $start, ?string $end): string
{
    if (!$start || !$end) return '';
    $s = new DateTime($start);
    $e = new DateTime($end);
    $diff = $s->diff($e);
    $parts = [];
    if ($diff->h > 0) $parts[] = $diff->h . 'h';
    if ($diff->i > 0) $parts[] = $diff->i . 'm';
    return $parts ? implode(' ', $parts) : '0m';
}

/** Trip length in whole days, e.g. "5 days". */
function trip_length_label(array $m): string
{
    $from = new DateTime($m['meeting_date']);
    $to = new DateTime($m['end_date'] ?: $m['meeting_date']);
    $days = $from->diff($to)->days + 1;
    return $days . ' day' . ($days === 1 ? '' : 's');
}

/** Duration/length label appropriate to the event type. */
function event_span_label(array $m): string
{
    return $m['event_type'] === 'trip' ? trip_length_label($m) : duration_label($m['start_time'], $m['end_time']);
}

/** Human date range, e.g. "Mon, 7 Sep 2026 – Fri, 11 Sep 2026" (or a single date). */
function fmt_date_range(string $from, string $to): string
{
    return $from === $to ? fmt_date_short($from) : fmt_date_short($from) . ' – ' . fmt_date_short($to);
}

/** Whether a meeting/trip occupies the given calendar day. */
function meeting_covers_day(array $m, string $ymd): bool
{
    $to = $m['end_date'] ?: $m['meeting_date'];
    return $ymd >= $m['meeting_date'] && $ymd <= $to;
}

/** For a trip, "Day 2 of 5" for the given day; null for in-house or out-of-range days. */
function trip_day_badge(array $m, string $ymd): ?string
{
    if ($m['event_type'] !== 'trip') return null;
    $from = new DateTime($m['meeting_date']);
    $to = new DateTime($m['end_date'] ?: $m['meeting_date']);
    $cur = new DateTime($ymd);
    if ($cur < $from || $cur > $to) return null;
    $dayNum = $from->diff($cur)->days + 1;
    $totalDays = $from->diff($to)->days + 1;
    return "Day {$dayNum} of {$totalDays}";
}

/**
 * Meetings/trips whose span overlaps [$from, $to] inclusive, ordered
 * chronologically. A multi-day trip is returned once even if it spans the
 * whole range — callers that render per-day (schedule/print) should use
 * meeting_covers_day() to place it on each applicable day.
 */
function meetings_between(string $from, string $to): array
{
    $stmt = db()->prepare(
        'SELECT * FROM meetings
         WHERE IFNULL(end_date, meeting_date) >= ? AND meeting_date <= ?
         ORDER BY meeting_date ASC, start_time ASC'
    );
    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

/** Active staff, for the "Accompanying Team" picker, alphabetical. */
function all_staff(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM staff' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY name ASC';
    return db()->query($sql)->fetchAll();
}

/** Staff accompanying a given meeting/trip, alphabetical. */
function meeting_team(int $meetingId): array
{
    $stmt = db()->prepare(
        'SELECT s.* FROM staff s
         JOIN meeting_staff ms ON ms.staff_id = s.id
         WHERE ms.meeting_id = ?
         ORDER BY s.name ASC'
    );
    $stmt->execute([$meetingId]);
    return $stmt->fetchAll();
}

/** Replace a meeting's accompanying team with exactly this set of staff IDs. */
function save_meeting_team(int $meetingId, array $staffIds): void
{
    $db = db();
    $db->prepare('DELETE FROM meeting_staff WHERE meeting_id = ?')->execute([$meetingId]);
    if (!$staffIds) {
        return;
    }
    $stmt = $db->prepare('INSERT IGNORE INTO meeting_staff (meeting_id, staff_id) VALUES (?, ?)');
    foreach (array_unique(array_map('intval', $staffIds)) as $staffId) {
        $stmt->execute([$meetingId, $staffId]);
    }
}

/** "A, B and C" — natural-language join for a team/attendee list. */
function join_names(array $names): string
{
    if (count($names) <= 1) {
        return $names[0] ?? '';
    }
    $last = array_pop($names);
    return implode(', ', $names) . ' and ' . $last;
}

/**
 * Meetings currently inside their own configured reminder window
 * (NOW() is between "start minus reminder_hours_before" and "start").
 * Pass $unsentOnly = true (used by the cron sender) to exclude meetings
 * that have already been emailed.
 */
function meetings_in_reminder_window(bool $unsentOnly = false): array
{
    $sql = "SELECT * FROM meetings
            WHERE TIMESTAMP(meeting_date, COALESCE(start_time,'00:00:00')) >= NOW()
              AND TIMESTAMP(meeting_date, COALESCE(start_time,'00:00:00')) <= DATE_ADD(NOW(), INTERVAL reminder_hours_before HOUR)";
    if ($unsentOnly) {
        $sql .= " AND reminder_sent = 0";
    }
    $sql .= " ORDER BY meeting_date ASC, start_time ASC";
    return db()->query($sql)->fetchAll();
}

/**
 * Look for an exact duplicate and/or time-clashing engagements against the
 * given (not-yet-saved) meeting/trip. A trip is treated as blocking every
 * day it covers; two in-house meetings only clash if their times actually
 * overlap on the same day.
 *
 * @return array{duplicate: array|null, overlaps: array[]}
 */
function find_scheduling_conflicts(
    string $title,
    string $type,
    string $dateFrom,
    string $dateTo,
    ?string $startTime,
    ?string $endTime,
    ?int $excludeId = null
): array {
    $sql = 'SELECT * FROM meetings WHERE IFNULL(end_date, meeting_date) >= ? AND meeting_date <= ?';
    $params = [$dateFrom, $dateTo];
    if ($excludeId) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $normTitle = mb_strtolower(trim($title));
    $duplicate = null;
    $overlaps = [];

    foreach ($rows as $r) {
        $rFrom = $r['meeting_date'];
        $rTo = $r['end_date'] ?: $r['meeting_date'];
        $fullDayClash = ($type === 'trip' || $r['event_type'] === 'trip');

        $timeOverlap = true;
        if (!$fullDayClash) {
            // Both in-house — the query above only intersects them when
            // they fall on the same single day, so compare actual times.
            $s1 = $startTime ?: '00:00:00';
            $e1 = $endTime ?: '23:59:59';
            $s2 = $r['start_time'] ?: '00:00:00';
            $e2 = $r['end_time'] ?: '23:59:59';
            $timeOverlap = ($s1 < $e2) && ($s2 < $e1);
        }
        if (!$timeOverlap) {
            continue;
        }

        $isDuplicate = $normTitle !== ''
            && mb_strtolower(trim($r['title'])) === $normTitle
            && $r['event_type'] === $type
            && $rFrom === $dateFrom && $rTo === $dateTo
            && ($type !== 'inhouse' || ($r['start_time'] === $startTime && $r['end_time'] === $endTime));

        if ($isDuplicate && !$duplicate) {
            $duplicate = $r;
        } else {
            $overlaps[] = $r;
        }
    }

    return ['duplicate' => $duplicate, 'overlaps' => $overlaps];
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Build the subject/body for a meeting/trip reminder email.
 * @return array{0: string, 1: string} [$subject, $body]
 */
function build_reminder_email(array $m): array
{
    $isTrip = $m['event_type'] === 'trip';

    if ($isTrip) {
        $subject = 'Reminder: Trip — ' . $m['title'] . ' (' . fmt_date_short($m['meeting_date']) . ')';
        $body = "TRIP REMINDER\n\n"
            . "Title:        {$m['title']}\n"
            . "Departure:    " . fmt_date_long($m['meeting_date']) . ($m['start_time'] ? ' at ' . fmt_time($m['start_time']) : '') . "\n"
            . "Return:       " . fmt_date_long($m['end_date'] ?: $m['meeting_date']) . ($m['end_time'] ? ' at ' . fmt_time($m['end_time']) : '') . "\n"
            . "Length:       " . trip_length_label($m) . "\n"
            . "Venue:        {$m['venue']}\n"
            . ($m['attendees'] ? "Attendees:    {$m['attendees']}\n" : '')
            . (!empty($m['contact']) ? "Contact:      {$m['contact']}\n" : '')
            . ($m['agenda'] ? "\nPurpose:\n{$m['agenda']}\n" : '')
            . (!empty($m['notes']) ? "\nNotes:\n{$m['notes']}\n" : '');
    } else {
        $subject = 'Reminder: ' . $m['title'] . ' — ' . fmt_date_short($m['meeting_date']);
        $body = "MEETING REMINDER\n\n"
            . "Title:  {$m['title']}\n"
            . "Date:   " . fmt_date_long($m['meeting_date']) . "\n"
            . "Time:   " . fmt_time($m['start_time']) . " - " . fmt_time($m['end_time']) . "\n"
            . "Venue:  {$m['venue']}\n"
            . ($m['attendees'] ? "Attendees: {$m['attendees']}\n" : '')
            . (!empty($m['contact']) ? "Contact: {$m['contact']}\n" : '')
            . ($m['agenda'] ? "\nAgenda:\n{$m['agenda']}\n" : '')
            . (!empty($m['notes']) ? "\nNotes:\n{$m['notes']}\n" : '');
    }
    $body .= "\n-- " . SMTP_FROM_NAME;

    return [$subject, $body];
}

/**
 * Branded HTML email chrome (header, accent stripe, card, button styles)
 * shared by every outgoing email so the whole inbox history looks like one
 * consistent, polished product. Colors are hardcoded hex, not CSS custom
 * properties — email HTML is an isolated document with no access to
 * assets/css/style.css, so a var() with no fallback renders as nothing in
 * every mail client.
 *
 * @return array{style: string, headerHtml: string}
 */
function email_chrome(string $subtitle = ''): array
{
    $subtitleHtml = $subtitle !== '' ? "<p>" . e($subtitle) . "</p>" : '';
    return [
        'style' => "
            body { font-family: Arial, Helvetica, sans-serif; color: #202124; margin: 0; background: #eef0f3; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #101112, #26282b); padding: 26px 24px; border-radius: 14px 14px 0 0; }
            .header-row { display: table; width: 100%; }
            .logo-cell { display: table-cell; vertical-align: middle; width: 48px; }
            .logo-box { width: 44px; height: 44px; background: #f4c20d; border-radius: 50%; color: #101112; font-weight: 800; font-size: 15px; text-align: center; line-height: 44px; }
            .brand-cell { display: table-cell; vertical-align: middle; padding-left: 14px; }
            .brand-cell h1 { margin: 0; color: #fff; font-size: 18px; }
            .brand-cell p { margin: 2px 0 0; color: #e7c568; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; }
            .accent { height: 5px; background: #f4c20d; }
            .content { background: #fff; padding: 30px 28px; border: 1px solid #e4e6ea; border-top: none; border-radius: 0 0 14px 14px; }
            .kicker { margin: 0 0 14px; font-weight: 800; color: #101112; font-size: 15px; }
            .detail { margin: 18px 0; padding: 16px 18px; background: #f8f8f9; border-radius: 10px; border-left: 4px solid #f4c20d; }
            .detail p { margin: 0 0 8px; }
            .detail p:last-child { margin-bottom: 0; }
            .label { font-weight: bold; color: #101112; }
            .btn { display: inline-block; padding: 12px 26px; background: #f4c20d; color: #101112 !important; text-decoration: none; border-radius: 8px; font-weight: 700; }
            .footer { text-align: center; padding: 20px; color: #9aa0ab; font-size: 12px; }
            .pill { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; background: #101112; color: #f4c20d; }
        ",
        'headerHtml' => "
            <div class='header'>
                <div class='header-row'>
                    <div class='logo-cell'><div class='logo-box'>SE</div></div>
                    <div class='brand-cell'><h1>" . e(APP_NAME) . "</h1>$subtitleHtml</div>
                </div>
            </div>
            <div class='accent'></div>
        ",
    ];
}

/** Build the styled HTML body for a meeting/trip reminder email. */
function build_reminder_email_html(array $m): string
{
    $isTrip = $m['event_type'] === 'trip';
    $chrome = email_chrome($isTrip ? 'Trip Reminder' : 'Meeting Reminder');
    $link   = SITE_URL . '/print_meeting.php?id=' . (int) $m['id'];

    $rows = [];
    $rows[] = "<p><span class='label'>📌 Title:</span> " . e($m['title']) . "</p>";

    if ($isTrip) {
        $rows[] = "<p><span class='label'>📤 Departure:</span> " . e(fmt_date_long($m['meeting_date'])) . ($m['start_time'] ? ' at ' . e(fmt_time($m['start_time'])) : '') . "</p>";
        $rows[] = "<p><span class='label'>📥 Return:</span> " . e(fmt_date_long($m['end_date'] ?: $m['meeting_date'])) . ($m['end_time'] ? ' at ' . e(fmt_time($m['end_time'])) : '') . "</p>";
        $rows[] = "<p><span class='label'>⏱ Length:</span> " . e(trip_length_label($m)) . "</p>";
        $rows[] = "<p><span class='label'>📍 Venue:</span> " . e($m['venue']) . "</p>";
    } else {
        $rows[] = "<p><span class='label'>🗓 Date:</span> " . e(fmt_date_long($m['meeting_date'])) . "</p>";
        $rows[] = "<p><span class='label'>🕐 Time:</span> " . e(fmt_time($m['start_time'])) . " – " . e(fmt_time($m['end_time'])) . "</p>";
        $rows[] = "<p><span class='label'>📍 Venue:</span> " . e($m['venue']) . "</p>";
    }
    if ($m['attendees']) {
        $rows[] = "<p><span class='label'>👥 Attendees:</span> " . e($m['attendees']) . "</p>";
    }
    if (!empty($m['contact'])) {
        $rows[] = "<p><span class='label'>☎ Contact:</span> " . e($m['contact']) . "</p>";
    }
    $detailHtml = "<div class='detail'>" . implode('', $rows) . "</div>";

    $agendaHtml = '';
    if ($m['agenda']) {
        $agendaHtml = "<p style='margin:18px 0 0;'><span class='label'>" . ($isTrip ? '📝 Purpose' : '📝 Agenda') . ":</span></p>"
            . "<div class='detail' style='margin-top:8px;'><p style='white-space:pre-wrap;margin:0;'>" . nl2br(e($m['agenda'])) . "</p></div>";
    }

    $notesHtml = '';
    if (!empty($m['notes'])) {
        $notesHtml = "<p style='margin:18px 0 0;'><span class='label'>⚠ Notes — things to keep in check:</span></p>"
            . "<div class='detail' style='margin-top:8px;border-left-color:#101112;background:#fff8e1;'><p style='white-space:pre-wrap;margin:0;'>" . nl2br(e($m['notes'])) . "</p></div>";
    }

    return "
    <html>
    <head><style>{$chrome['style']}</style></head>
    <body>
        <div class='container'>
            {$chrome['headerHtml']}
            <div class='content'>
                <p class='kicker'>" . ($isTrip ? '💼 Upcoming trip' : '🔔 Upcoming meeting') . "</p>
                <p>This is a reminder for the following engagement on the Minister's calendar:</p>
                {$detailHtml}
                {$agendaHtml}
                {$notesHtml}
                <p style='text-align:center;margin-top:26px;'><a href='{$link}' class='btn'>🔍 View Full Details</a></p>
            </div>
            <div class='footer'>
                <p>" . e(MINISTRY_NAME) . "<br>&copy; " . date('Y') . " " . e(APP_NAME) . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/** Every registered user's email + the fixed CC list, for reminder sends. */
function reminder_recipients(): array
{
    $to = array_values(array_filter(array_column(db()->query('SELECT email FROM users')->fetchAll(), 'email')));
    $cc = defined('MAIL_CC_LIST') ? MAIL_CC_LIST : [];
    return [$to, $cc];
}
