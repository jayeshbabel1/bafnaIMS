<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * pages/inquiries.php  — with archive section (Task 1)
 */
$pageTitle = 'Inquiries — ' . APP_NAME;
$showNav   = true;

// ── Run lightweight auto-archive on every page load ──────────────────────────
// (Cheap: only fires the UPDATE when rows actually qualify)
require_once BASE_PATH . '/includes/notifications.php';
autoArchiveInquiries();

$db      = getDB();
$perPage = 6;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$tab     = ($_GET['tab'] ?? 'active'); // active | archive

// ── Counts ───────────────────────────────────────────────────────────────────
$cntActive = (int)$db->prepare("SELECT COUNT(*) FROM inquiries WHERE user_id=? AND status != 'closed'")
    ->execute([$_SESSION['user_id']]) ?
    (function() use ($db) {
        $s = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE user_id=? AND status != 'closed'");
        $s->execute([$_SESSION['user_id']]);
        return $s->fetchColumn();
    })() : 0;

$cntArchive = (function() use ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE user_id=? AND status='closed'");
    $s->execute([$_SESSION['user_id']]);
    return (int)$s->fetchColumn();
})();

$activeCount  = (function() use ($db) {
    $s = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE user_id=? AND status != 'closed'");
    $s->execute([$_SESSION['user_id']]);
    return (int)$s->fetchColumn();
})();

$archiveCount = $cntArchive;
$totalCount   = ($tab === 'archive') ? $archiveCount : $activeCount;
$totalPages   = max(1, (int)ceil($totalCount / $perPage));
$currentPage      = min($currentPage, $totalPages);
$offset       = ($currentPage - 1) * $perPage;

// ── Fetch rows ────────────────────────────────────────────────────────────────
$whereStatus = ($tab === 'archive') ? "COALESCE(i.status,'')='closed'" : "COALESCE(i.status,'')!='closed'";
//$whereStatus = ($tab === 'archive') ? "i.status = 'closed'" : "i.status != 'closed'";
$st = $db->prepare("
    SELECT i.*, p.name as product_name, p.quarry_number, p.category, p.palette,
    (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
    FROM inquiries i JOIN products p ON i.product_id=p.id
    WHERE i.user_id=? AND $whereStatus
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?");
$st->execute([$_SESSION['user_id'], $perPage, $offset]);
$inquiries = $st->fetchAll();

$statusMap = [
    'pending' => ['badge-gray',  'Pending'],
    'sent'    => ['badge-blue',  'Sent'],
    'replied' => ['badge-green', 'Replied'],
    'closed'  => ['badge-gray',  'Archived'],
];
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-brand">
      <div>
        <p class="topbar-eyebrow">My Activity</p>
        <p class="topbar-title">Inquiries</p>
      </div>
    </div>
    <div class="topbar-actions">
      <span class="badge badge-dark" style="font-size:12px;padding:5px 10px;"><?= $activeCount + $archiveCount ?> total</span>
    </div>
  </div>

  <!-- Tab bar -->
  <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin:0 16px;">
    <a href="index.php?page=inquiries&tab=active"
       style="padding:11px 18px;font-size:13px;font-weight:600;border-bottom:2px solid <?= $tab==='active'?'var(--accent)':'transparent' ?>;margin-bottom:-2px;color:<?= $tab==='active'?'var(--accent)':'var(--text3)' ?>;display:flex;align-items:center;gap:6px;">
      Active
      <?php if ($activeCount > 0): ?><span class="badge badge-blue" style="font-size:10px;"><?= $activeCount ?></span><?php endif; ?>
    </a>
    <a href="index.php?page=inquiries&tab=archive"
       style="padding:11px 18px;font-size:13px;font-weight:600;border-bottom:2px solid <?= $tab==='archive'?'var(--accent)':'transparent' ?>;margin-bottom:-2px;color:<?= $tab==='archive'?'var(--accent)':'var(--text3)' ?>;display:flex;align-items:center;gap:6px;">
      Archive
      <?php if ($archiveCount > 0): ?><span class="badge badge-gray" style="font-size:10px;"><?= $archiveCount ?></span><?php endif; ?>
    </a>
  </div>

  <?php if ($tab === 'archive' && $archiveCount > 0): ?>
  <div style="padding:10px 16px 0;">
    <div class="alert" style="background:var(--surface2);color:var(--text3);font-size:12px;display:flex;align-items:center;gap:8px;padding:10px 14px;">
      <?= icon('info', 14) ?>
      Inquiries older than 25 days are automatically archived.
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($inquiries) && $currentPage === 1): ?>
  <div class="empty-state" style="padding-top:60px;">
    <div class="empty-icon"><?= icon($tab === 'archive' ? 'file' : 'msg', 28) ?></div>
    <p class="empty-title"><?= $tab === 'archive' ? 'No archived inquiries' : 'No inquiries yet' ?></p>
    <p class="empty-sub">
      <?= $tab === 'archive'
        ? 'Inquiries older than 25 days will appear here.'
        : 'Send inquiries from product pages to connect with the Bafna team.' ?>
    </p>
    <?php if ($tab === 'active'): ?>
    <a href="index.php?page=catalog" class="btn-primary btn-gold"
       style="margin-top:28px;width:auto;padding:13px 32px;text-decoration:none;">
      <?= icon('grid', 15) ?>&nbsp; Browse Catalog
    </a>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <div class="inq-cards-wrapper">
    <div class="inq-cards-grid">
      <?php foreach ($inquiries as $inq):
        $pal = json_decode($inq['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
        [$statusClass, $statusLabel] = $statusMap[$inq['status']] ?? ['badge-gray', 'Unknown'];
      ?>
      <div class="card inq-card <?= $inq['status'] === 'closed' ? 'inq-card--archived' : '' ?>">
        <!-- Header -->
        <div class="inq-header">
          <div style="display:flex;gap:11px;align-items:flex-start;flex:1;min-width:0;">
            <div style="width:50px;height:50px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--surface2);">
              <?php if ($inq['primary_photo'] && file_exists(PHOTOS_DIR . '/' . $inq['primary_photo'])): ?>
              <img src="assets/uploads/photos/<?= h($inq['primary_photo']) ?>" alt=""
                   style="width:100%;height:100%;object-fit:cover;<?= $inq['status']==='closed'?'filter:grayscale(.5);opacity:.8;':'' ?>"/>
              <?php else: ?><?= marbleSVG($pal, 50, 50, 'iq' . $inq['id']) ?><?php endif; ?>
            </div>
            <div style="min-width:0;">
              <p class="inq-name"><?= h($inq['product_name']) ?></p>
              <p class="inq-sub">Lot <?= h($inq['quarry_number']) ?> · <?= timeAgo($inq['created_at']) ?></p>
            </div>
          </div>
          <span class="badge <?= $statusClass ?>" style="flex-shrink:0;"><?= $statusLabel ?></span>
        </div>

        <!-- Message -->
        <div class="inq-msg">
          <p style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Your Message</p>
          <?= h($inq['message']) ?>
          <?php if ($inq['qty_required']): ?>
          <p style="margin-top:6px;font-size:11px;color:var(--gold);font-weight:600;">
            <?= icon('info', 11) ?> Qty: <?= h($inq['qty_required']) ?> sq.ft.
          </p>
          <?php endif; ?>
        </div>

        <!-- Reply -->
        <?php if ($inq['admin_reply']): ?>
        <div class="inq-reply">
          <p class="inq-reply-label"><?= icon('check', 10) ?>&nbsp; Bafna Reply</p>
          <?= h($inq['admin_reply']) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($currentPage > 1): ?>
      <a href="index.php?page=inquiries&tab=<?= $tab ?>&p=<?= $currentPage - 1 ?>" class="pag-btn">&lsaquo;</a>
      <?php else: ?><span class="pag-btn disabled">&lsaquo;</span><?php endif; ?>

      <?php $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range);
      if ($s > 1): ?>
        <a href="index.php?page=inquiries&tab=<?= $tab ?>&p=1" class="pag-btn">1</a>
        <?php if ($s > 2): ?><span class="pag-ellipsis">…</span><?php endif; ?>
      <?php endif; ?>
      <?php for ($pi = $s; $pi <= $e; $pi++): ?>
      <a href="index.php?page=inquiries&tab=<?= $tab ?>&p=<?= $pi ?>"
         class="pag-btn <?= $pi === $currentPage ? 'active' : '' ?>"><?= $pi ?></a>
      <?php endfor; ?>
      <?php if ($e < $totalPages): ?>
        <?php if ($e < $totalPages - 1): ?><span class="pag-ellipsis">…</span><?php endif; ?>
        <a href="index.php?page=inquiries&tab=<?= $tab ?>&p=<?= $totalPages ?>" class="pag-btn"><?= $totalPages ?></a>
      <?php endif; ?>

      <?php if ($currentPage < $totalPages): ?>
      <a href="index.php?page=inquiries&tab=<?= $tab ?>&p=<?= $currentPage + 1 ?>" class="pag-btn">&rsaquo;</a>
      <?php else: ?><span class="pag-btn disabled">&rsaquo;</span><?php endif; ?>
    </div>
    <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:-12px;margin-bottom:20px;">
      Showing <?= ($offset + 1) ?>–<?= min($offset + $perPage, $totalCount) ?> of <?= $totalCount ?> inquiries
    </p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<style>
.inq-card--archived { opacity: .85; }
</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>