<?php
$pageTitle = 'Inquiries — ' . APP_NAME;
$showNav   = true;

$db        = getDB();
$perPage   = 10;
$curPage   = max(1,(int)($_GET['p']??1));

// Count total
$cntSt = $db->prepare("SELECT COUNT(*) FROM inquiries WHERE user_id=?");
$cntSt->execute([$_SESSION['user_id']]);
$totalCount = (int)$cntSt->fetchColumn();
$totalPages = max(1,(int)ceil($totalCount/$perPage));
$curPage    = min($curPage,$totalPages);
$offset     = ($curPage-1)*$perPage;

$st = $db->prepare("
    SELECT i.*, p.name as product_name, p.quarry_number, p.category, p.palette,
    (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
    FROM inquiries i JOIN products p ON i.product_id=p.id
    WHERE i.user_id=?
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?");
$st->execute([$_SESSION['user_id'], $perPage, $offset]);
$inquiries = $st->fetchAll();

$statusMap = [
    'pending' => ['badge-gray',  'Pending'],
    'sent'    => ['badge-blue',  'Sent'],
    'replied' => ['badge-green', 'Replied'],
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
      <span class="badge badge-dark" style="font-size:12px;padding:5px 10px;"><?=$totalCount?> sent</span>
    </div>
  </div>

  <?php if(empty($inquiries) && $curPage===1): ?>
  <div class="empty-state" style="padding-top:72px;">
    <div class="empty-icon"><?= icon('msg',28) ?></div>
    <p class="empty-title">No inquiries yet</p>
    <p class="empty-sub">Send inquiries from product pages to connect with the Bafna team.</p>
    <a href="index.php?page=catalog"
       class="btn-primary btn-gold"
       style="margin-top:28px;width:auto;padding:13px 32px;text-decoration:none;">
      <?= icon('grid',15) ?>&nbsp; Browse Catalog
    </a>
  </div>

  <?php else: ?>
  <div class="inq-cards-wrapper">
    <div class="inq-cards-grid">
      <?php foreach($inquiries as $inq):
        $pal = json_decode($inq['palette']??'[]',true)?:['F2F0EC','D8CFC4','BFB0A0'];
        [$statusClass,$statusLabel] = $statusMap[$inq['status']]??['badge-gray','Unknown'];
      ?>
      <div class="card inq-card">
        <!-- Header -->
        <div class="inq-header">
          <div style="display:flex;gap:11px;align-items:flex-start;flex:1;min-width:0;">
            <div style="width:50px;height:50px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--surface2);">
              <?php if($inq['primary_photo']&&file_exists(PHOTOS_DIR.'/'.$inq['primary_photo'])): ?>
              <img src="assets/uploads/photos/<?=h($inq['primary_photo'])?>" alt=""
                   style="width:100%;height:100%;object-fit:cover;"/>
              <?php else: ?><?=marbleSVG($pal,50,50,'iq'.$inq['id'])?><?php endif; ?>
            </div>
            <div style="min-width:0;">
              <p class="inq-name"><?=h($inq['product_name'])?></p>
              <p class="inq-sub">Lot <?=h($inq['quarry_number'])?> · <?=timeAgo($inq['created_at'])?></p>
            </div>
          </div>
          <span class="badge <?=$statusClass?>" style="flex-shrink:0;"><?=$statusLabel?></span>
        </div>

        <!-- Message -->
        <div class="inq-msg">
          <p style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Your Message</p>
          <?=h($inq['message'])?>
          <?php if($inq['qty_required']): ?>
          <p style="margin-top:6px;font-size:11px;color:var(--gold);font-weight:600;">
            <?=icon('info',11)?> Qty: <?=h($inq['qty_required'])?> sq.ft.
          </p>
          <?php endif; ?>
        </div>

        <!-- Reply -->
        <?php if($inq['admin_reply']): ?>
        <div class="inq-reply">
          <p class="inq-reply-label"><?=icon('check',10)?>&nbsp; Bafna Reply</p>
          <?=h($inq['admin_reply'])?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── PAGINATION ────────────────────────────────────────────────── -->
    <?php if($totalPages>1): ?>
    <div class="pagination">
      <!-- Prev -->
      <?php if($curPage>1): ?>
      <a href="index.php?page=inquiries&p=<?=$curPage-1?>" class="pag-btn">&lsaquo;</a>
      <?php else: ?>
      <span class="pag-btn disabled">&lsaquo;</span>
      <?php endif; ?>

      <?php
      $range=2; $start=max(1,$curPage-$range); $end=min($totalPages,$curPage+$range);
      if($start>1): ?>
        <a href="index.php?page=inquiries&p=1" class="pag-btn">1</a>
        <?php if($start>2): ?><span class="pag-ellipsis">…</span><?php endif; ?>
      <?php endif; ?>

      <?php for($pi=$start;$pi<=$end;$pi++): ?>
      <a href="index.php?page=inquiries&p=<?=$pi?>"
         class="pag-btn <?=$pi===$curPage?'active':''?>"><?=$pi?></a>
      <?php endfor; ?>

      <?php if($end<$totalPages): ?>
        <?php if($end<$totalPages-1): ?><span class="pag-ellipsis">…</span><?php endif; ?>
        <a href="index.php?page=inquiries&p=<?=$totalPages?>" class="pag-btn"><?=$totalPages?></a>
      <?php endif; ?>

      <!-- Next -->
      <?php if($curPage<$totalPages): ?>
      <a href="index.php?page=inquiries&p=<?=$curPage+1?>" class="pag-btn">&rsaquo;</a>
      <?php else: ?>
      <span class="pag-btn disabled">&rsaquo;</span>
      <?php endif; ?>
    </div>

    <p style="text-align:center;font-size:12px;color:var(--text3);margin-top:-12px;margin-bottom:20px;">
      Showing <?=($offset+1)?>–<?=min($offset+$perPage,$totalCount)?> of <?=$totalCount?> inquiries
    </p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>