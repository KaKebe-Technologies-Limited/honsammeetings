<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$page_title = 'All Meetings';
$active     = 'meetings';

$q = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? '';

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(title LIKE ? OR venue LIKE ? OR agenda LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if (in_array($typeFilter, ['inhouse', 'trip'], true)) {
    $where[] = 'event_type = ?';
    $params[] = $typeFilter;
}
$sql = 'SELECT * FROM meetings';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY meeting_date DESC, start_time DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$meetings = $stmt->fetchAll();
$today = date('Y-m-d');

require __DIR__ . '/includes/header.php';
?>
<div class="page">
  <?php if ($flash = flash_get()): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['text']) ?></div>
  <?php endif; ?>
  <div class="page-head">
    <div>
      <h2>All Meetings &amp; Trips</h2>
      <div class="sub"><?= count($meetings) ?> record<?= count($meetings) === 1 ? '' : 's' ?></div>
    </div>
    <div class="btn-row">
      <form method="get" style="display:flex;gap:8px;">
        <input type="text" name="q" placeholder="Search title, venue, agenda…" value="<?= e($q) ?>">
        <select name="type" onchange="this.form.submit()">
          <option value="">All types</option>
          <option value="inhouse" <?= $typeFilter === 'inhouse' ? 'selected' : '' ?>>In-house only</option>
          <option value="trip" <?= $typeFilter === 'trip' ? 'selected' : '' ?>>Trips only</option>
        </select>
        <button class="btn btn-outline" type="submit">Search</button>
      </form>
      <a href="<?= BASE_URL ?>/meeting_edit.php" class="btn btn-gold">+ Add Meeting / Trip</a>
    </div>
  </div>

  <?php if (!$meetings): ?>
    <div class="panel empty">No meetings or trips found.</div>
  <?php else: ?>
    <?php foreach ($meetings as $m):
        $endsOn = $m['end_date'] ?: $m['meeting_date'];
        $past = $endsOn < $today;
        $soon = false;
        $dayContext = null;
    ?>
      <div style="<?= $past ? 'opacity:.6;' : '' ?>">
        <?php include __DIR__ . '/includes/meeting_card.php'; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
