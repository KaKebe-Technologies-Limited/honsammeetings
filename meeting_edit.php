<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id      = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;
$error   = null;

$today = date('Y-m-d');
$meeting = [
    'event_type'            => 'inhouse',
    'title'                 => '',
    'meeting_date'          => $_GET['date'] ?? $today,
    'end_date'              => $_GET['date'] ?? $today,
    'start_time'            => '10:00:00',
    'end_time'              => '11:00:00',
    'venue'                 => '',
    'agenda'                => '',
    'attendees'             => '',
    'contact'               => '',
    'notes'                 => '',
    'reminder_hours_before' => REMINDER_LEAD_HOURS,
];

$selectedTeam = [];
$ministryId   = null;

if ($editing) {
    $found = find_meeting($id);
    if (!$found) {
        header('Location: ' . BASE_URL . '/meetings.php');
        exit;
    }
    $meeting = $found;
    $ministryId = (int) $found['ministry_id'];
    $selectedTeam = array_column(meeting_team($id), 'id');
} else {
    $ministryId = resolve_ministry_id();
    if (!$ministryId) {
        redirect_no_ministry();
    }
}

$allStaff = all_staff($ministryId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $eventType = ($_POST['event_type'] ?? '') === 'trip' ? 'trip' : 'inhouse';
    $title     = trim($_POST['title'] ?? '');
    $venue     = trim($_POST['venue'] ?? '');
    $attendees = trim($_POST['attendees'] ?? '');
    $contact   = trim($_POST['contact'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $agenda    = trim($_POST['agenda'] ?? '');
    $selectedTeam = array_map('intval', $_POST['team'] ?? []);
    $reminderHours = (int) ($_POST['reminder_hours_before'] ?? REMINDER_LEAD_HOURS);
    if (!array_key_exists($reminderHours, REMINDER_OPTIONS)) {
        $reminderHours = REMINDER_LEAD_HOURS;
    }

    if ($eventType === 'inhouse') {
        $dateFrom  = $_POST['meeting_date'] ?? '';
        $dateTo    = $dateFrom;
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime   = trim($_POST['end_time'] ?? '');
    } else {
        $dateFrom  = $_POST['trip_start_date'] ?? '';
        $dateTo    = $_POST['trip_end_date'] ?? '';
        $startTime = trim($_POST['trip_start_time'] ?? '');
        $endTime   = trim($_POST['trip_end_time'] ?? '');
    }
    $startTime = $startTime !== '' ? $startTime . ':00' : null;
    $endTime   = $endTime !== '' ? $endTime . ':00' : null;

    // Repopulate the form with what was submitted in case of an error.
    $meeting = [
        'event_type' => $eventType,
        'title' => $title,
        'meeting_date' => $dateFrom ?: $today,
        'end_date' => $dateTo ?: $dateFrom ?: $today,
        'start_time' => $startTime ?? '',
        'end_time' => $endTime ?? '',
        'venue' => $venue,
        'agenda' => $agenda,
        'attendees' => $attendees,
        'contact' => $contact,
        'notes' => $notes,
        'reminder_hours_before' => $reminderHours,
    ];

    if ($title === '' || $venue === '') {
        $error = $eventType === 'trip'
            ? 'Title and destination are required.'
            : 'Title and venue are required.';
    } elseif (!$dateFrom || !$dateTo) {
        $error = $eventType === 'trip'
            ? 'Please provide both a departure and a return date.'
            : 'Please provide a date, start time and end time.';
    } elseif ($eventType === 'inhouse' && (!$startTime || !$endTime)) {
        $error = 'Please provide a start time and end time.';
    } elseif ($eventType === 'inhouse' && $endTime <= $startTime) {
        $error = 'End time must be after the start time.';
    } elseif ($dateTo < $dateFrom) {
        $error = 'Return date cannot be before the departure date.';
    } else {
        $conflict = find_scheduling_conflicts(
            $title, $eventType, $dateFrom, $dateTo, $startTime, $endTime,
            $ministryId, $editing ? $id : null
        );

        if ($conflict['duplicate']) {
            $d = $conflict['duplicate'];
            $error = 'This looks like a duplicate — an identical entry "' . $d['title'] . '" already exists on '
                . fmt_date_range($d['meeting_date'], $d['end_date'] ?: $d['meeting_date']) . '.';
        } else {
            // A time clash is allowed through — just warned about, not blocked.
            $clashWarning = null;
            if ($conflict['overlaps']) {
                $names = array_map(
                    fn($r) => '"' . $r['title'] . '" (' . fmt_date_range($r['meeting_date'], $r['end_date'] ?: $r['meeting_date']) . ')',
                    $conflict['overlaps']
                );
                $clashWarning = 'Saved, but this clashes with: ' . implode('; ', $names) . '.';
            }

            if ($editing) {
                $stmt = db()->prepare(
                    'UPDATE meetings SET event_type=?, title=?, meeting_date=?, end_date=?, start_time=?, end_time=?,
                     venue=?, agenda=?, attendees=?, contact=?, notes=?, reminder_hours_before=?, reminder_sent=0
                     WHERE id=?'
                );
                $stmt->execute([
                    $eventType, $title, $dateFrom, $dateTo, $startTime, $endTime,
                    $venue, $agenda, $attendees, $contact, $notes, $reminderHours,
                    $id,
                ]);
                $savedId = $id;
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO meetings (ministry_id, event_type, title, meeting_date, end_date, start_time, end_time,
                     venue, agenda, attendees, contact, notes, reminder_hours_before, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $ministryId, $eventType, $title, $dateFrom, $dateTo, $startTime, $endTime,
                    $venue, $agenda, $attendees, $contact, $notes, $reminderHours,
                    current_user()['id'],
                ]);
                $savedId = (int) db()->lastInsertId();
            }
            save_meeting_team($savedId, $selectedTeam);

            $params = $clashWarning ? [] : ['saved' => 1];
            if ((current_user()['role'] ?? '') === 'super_admin') {
                $params['ministry_id'] = $ministryId;
            }
            if ($clashWarning) {
                flash_set('warning', $clashWarning);
            }
            header('Location: ' . BASE_URL . '/schedule.php' . ($params ? '?' . http_build_query($params) : ''));
            exit;
        }
    }
}

$page_title = $editing ? 'Edit Meeting' : 'Add Meeting / Trip';
$active     = $editing ? '' : 'add';
$isTrip     = $meeting['event_type'] === 'trip';

require __DIR__ . '/includes/header.php';
?>
<div class="page">
  <div class="page-head">
    <div>
      <h2><?= $editing ? 'Edit' : 'Add' ?> Meeting / Trip</h2>
      <div class="sub"><?= $editing ? 'Update the details below.' : "Schedule a new item on the Minister's calendar." ?></div>
    </div>
  </div>

  <div class="panel panel-pad">
    <?php if ($error): ?>
      <div class="alert alert-error">⚠ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

      <div class="form-grid">
        <div class="field full">
          <label>Event Type *</label>
          <select name="event_type" id="eventType">
            <option value="inhouse" <?= !$isTrip ? 'selected' : '' ?>>In-house Meeting</option>
            <option value="trip" <?= $isTrip ? 'selected' : '' ?>>Trip (Outside Station / Country)</option>
          </select>
        </div>

        <div class="field full">
          <label>Purpose / Title *</label>
          <input type="text" name="title" required maxlength="200" value="<?= e($meeting['title']) ?>" placeholder="e.g. Cabinet Meeting">
        </div>

        <!-- In-house fields -->
        <div class="field" id="grpDate">
          <label>Date *</label>
          <input type="date" name="meeting_date" id="meetingDate" value="<?= e($isTrip ? $today : $meeting['meeting_date']) ?>">
        </div>
        <div class="field" id="grpVenue">
          <label id="venueLabel">Venue / Location *</label>
          <input type="text" name="venue" id="venueInput" required maxlength="200" value="<?= e($meeting['venue']) ?>" placeholder="e.g. State House">
        </div>
        <div class="field" id="grpStart">
          <label>Start time *</label>
          <input type="time" name="start_time" id="startTime" value="<?= e(!$isTrip ? substr($meeting['start_time'], 0, 5) : '') ?>">
        </div>
        <div class="field" id="grpEnd">
          <label>End time *</label>
          <input type="time" name="end_time" id="endTime" value="<?= e(!$isTrip ? substr($meeting['end_time'], 0, 5) : '') ?>">
          <span class="hint">Duration is calculated automatically.</span>
        </div>

        <!-- Trip fields -->
        <div class="field" id="grpTripFrom" hidden>
          <label>Departure Date *</label>
          <input type="date" name="trip_start_date" id="tripStartDate" value="<?= e($isTrip ? $meeting['meeting_date'] : $today) ?>">
        </div>
        <div class="field" id="grpTripTo" hidden>
          <label>Return Date *</label>
          <input type="date" name="trip_end_date" id="tripEndDate" value="<?= e($isTrip ? ($meeting['end_date'] ?: $meeting['meeting_date']) : $today) ?>">
          <span class="hint">Every day from departure to return is blocked on the calendar.</span>
        </div>
        <div class="field" id="grpTripStartTime" hidden>
          <label>Start time (optional)</label>
          <input type="time" name="trip_start_time" id="tripStartTime" value="<?= e($isTrip ? substr((string) $meeting['start_time'], 0, 5) : '') ?>">
        </div>
        <div class="field" id="grpTripEndTime" hidden>
          <label>End time (optional)</label>
          <input type="time" name="trip_end_time" id="tripEndTime" value="<?= e($isTrip ? substr((string) $meeting['end_time'], 0, 5) : '') ?>">
        </div>

        <div class="field full">
          <label>Attendees (optional)</label>
          <input type="text" name="attendees" maxlength="500" value="<?= e($meeting['attendees']) ?>" placeholder="e.g. PS, Commissioners, UNHCR Rep">
          <span class="hint">External people, by name/role — not tracked in the Staff list.</span>
        </div>

        <div class="field full">
          <label>Accompanying Team (optional)</label>
          <?php if (!$allStaff): ?>
            <div class="hint">No staff added yet. <a href="<?= BASE_URL ?>/staff_edit.php<?= ministry_qs($ministryId, '?') ?>" target="_blank">Add staff</a> to pick them here.</div>
          <?php else: ?>
            <div class="team-picker">
              <?php foreach ($allStaff as $s): ?>
                <label class="team-option">
                  <input type="checkbox" name="team[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $selectedTeam, true) ? 'checked' : '' ?>>
                  <?= e($s['name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <span class="hint">Staff accompanying the Minister — managed on the <a href="<?= BASE_URL ?>/staff.php<?= ministry_qs($ministryId, '?') ?>" target="_blank">Staff</a> page.</span>
        </div>

        <div class="field full">
          <label>Contact Person (optional)</label>
          <input type="text" name="contact" maxlength="150" value="<?= e($meeting['contact']) ?>" placeholder="e.g. 0775004767 - Charlot">
          <span class="hint">Who to reach about this engagement — shown on the schedule and included in the reminder email.</span>
        </div>

        <div class="field full">
          <label>Agenda / Purpose</label>
          <textarea name="agenda" placeholder="Brief description…"><?= e($meeting['agenda']) ?></textarea>
        </div>

        <div class="field full">
          <label>Notes (optional)</label>
          <textarea name="notes" placeholder="Important details or things to keep in check — documents to bring, prep needed, dress code, etc."><?= e($meeting['notes']) ?></textarea>
          <span class="hint">Shown on the schedule, print views, and included in the reminder email.</span>
        </div>

        <div class="field full">
          <label>Set Reminder *</label>
          <select name="reminder_hours_before">
            <?php foreach (REMINDER_OPTIONS as $hrs => $label): ?>
              <option value="<?= $hrs ?>" <?= (int) $meeting['reminder_hours_before'] === $hrs ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <span class="hint">An email reminder is sent automatically to registered staff (and CC'd contacts) when this window opens.</span>
        </div>
      </div>

      <div class="btn-row" style="margin-top:8px;">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Save' ?></button>
        <a href="<?= BASE_URL ?>/schedule.php<?= ministry_qs($ministryId, '?') ?>" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var typeSel = document.getElementById('eventType');
  var venueInput = document.getElementById('venueInput');
  var meetingDate = document.getElementById('meetingDate');
  var startTime = document.getElementById('startTime');
  var endTime = document.getElementById('endTime');
  var tripStartDate = document.getElementById('tripStartDate');
  var tripEndDate = document.getElementById('tripEndDate');

  function apply() {
    var isTrip = typeSel.value === 'trip';

    document.getElementById('grpDate').hidden = isTrip;
    document.getElementById('grpStart').hidden = isTrip;
    document.getElementById('grpEnd').hidden = isTrip;
    document.getElementById('grpTripFrom').hidden = !isTrip;
    document.getElementById('grpTripTo').hidden = !isTrip;
    document.getElementById('grpTripStartTime').hidden = !isTrip;
    document.getElementById('grpTripEndTime').hidden = !isTrip;

    meetingDate.required = !isTrip;
    startTime.required = !isTrip;
    endTime.required = !isTrip;
    tripStartDate.required = isTrip;
    tripEndDate.required = isTrip;

    venueInput.placeholder = isTrip ? 'e.g. Nairobi, Kenya' : 'e.g. State House';
  }

  typeSel.addEventListener('change', apply);
  apply();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
