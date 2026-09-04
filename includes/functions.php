<?php
require_once __DIR__ . '/db.php';

/**
 * Monday..Sunday date range (Y-m-d) containing $reference (default: today),
 * optionally shifted by $weekOffset whole weeks.
 */
function week_range(string $reference = 'today', int $weekOffset = 0): array
{
    $ref = new DateTime($reference);
    $ref->modify($weekOffset . ' week');
    $dow = (int) $ref->format('N'); // 1 (Mon) .. 7 (Sun)
    $monday = (clone $ref)->modify('-' . ($dow - 1) . ' days');
    $sunday = (clone $monday)->modify('+6 days');
    return [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
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

/**
 * Meetings/trips that have not yet finished, soonest first, limited.
 */
function upcoming_meetings(int $limit = 10): array
{
    $stmt = db()->prepare(
        "SELECT * FROM meetings
         WHERE IFNULL(end_date, meeting_date) > CURDATE()
            OR (IFNULL(end_date, meeting_date) = CURDATE() AND (end_time IS NULL OR end_time >= CURTIME()))
         ORDER BY meeting_date ASC, start_time ASC
         LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
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
