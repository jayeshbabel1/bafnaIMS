<?php
$adminTitle = 'Inquiries';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
$filter = $_GET['status'] ?? '';
$sql = "SELECT i.*,u.name as uname,u.email as uemail,u.firm,p.name as pname,p.quarry_number,p.category FROM inquiries i JOIN users u ON i.user_id=u.id JOIN products p ON i.product_id=p.id WHERE 1=1";
$params = [];
if ($filter) { $sql .= " AND i.status=?"; $params[] = $filter; }
$sql .= " ORDER BY i.created_at DESC";
$st = $db->prepare($sql); $st->execute($params);
$inquiries = $st->fetchAll();
$counts = $db->query("SELECT status, COUNT(*) as c FROM inquiries GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Status tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;">
  <a href="index.php?page=inquiries" class="tag-pill<?= !$filter?' active':'' ?>">All (<?= array_sum($counts) ?>)</a>
  <?php foreach (['pending'=>'badge-gray','replied'=>'badge-green'] as $st2 => $cls): ?>
  <a href="index.php?page=inquiries&status=<?= $st2 ?>" class="tag-pill<?= $filter===$st2?' active':'' ?>">
    <?= ucfirst($st2) ?> (<?= $counts[$st2] ?? 0 ?>)
  </a>
  <?php endforeach; ?>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr><th>User</th><th>Product</th><th>Message</th><th>Qty</th><th>Status</th><th>Date</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php if (empty($inquiries)): ?>
      <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text3);">No inquiries found.</td></tr>
      <?php else: foreach ($inquiries as $inq):
        $sc = ['pending'=>'badge-gray','replied'=>'badge-green'][$inq['status']] ?? 'badge-gray';
      ?>
      <tr>
        <td>
          <p style="font-weight:500;font-size:13px;"><?= h($inq['uname']) ?></p>
          <p style="font-size:11px;color:var(--text3);"><?= h($inq['uemail']) ?></p>
          <?php if ($inq['firm']): ?><p style="font-size:10px;color:var(--text3);"><?= h($inq['firm']) ?></p><?php endif; ?>
        </td>
        <td>
          <p style="font-weight:500;font-size:12px;"><?= h($inq['pname']) ?></p>
          <p style="font-size:11px;color:var(--text3);"><?= h($inq['quarry_number']) ?></p>
        </td>
        <td style="max-width:220px;">
          <p style="font-size:12px;color:var(--text2);white-space:normal;line-height:1.4;"><?= h(mb_strimwidth($inq['message'],0,100,'…')) ?></p>
          <?php if ($inq['admin_reply']): ?>
          <p style="font-size:11px;color:var(--success);margin-top:4px;"><?= icon('check',10) ?> Reply sent</p>
          <?php endif; ?>
        </td>
        <td style="font-size:12px;color:var(--text3);"><?= $inq['qty_required'] ? h($inq['qty_required']).' sq.ft.' : '—' ?></td>
        <td><span class="badge <?= $sc ?>"><?= ucfirst($inq['status']) ?></span></td>
        <td style="font-size:11px;color:var(--text3);white-space:nowrap;"><?= date('d M Y', $inq['created_at']) ?></td>
        <td>
          <!-- Reply form inline -->
          <button type="button" onclick="toggleReply(<?= $inq['id'] ?>)"
                  class="btn-admin-secondary btn-admin-sm"><?= icon('msg',13) ?> Reply</button>
        </td>
      </tr>
      <!-- Reply row -->
      <tr id="reply_<?= $inq['id'] ?>" style="display:none;background:var(--accent-light);">
        <td colspan="7" style="padding:12px 16px;">
          <?php if ($inq['admin_reply']): ?>
          <p style="font-size:12px;font-weight:600;color:var(--accent);margin-bottom:6px;">Previous reply:</p>
          <p style="font-size:12px;color:var(--text2);margin-bottom:10px;"><?= h($inq['admin_reply']) ?></p>
          <?php endif; ?>
          <form method="POST" action="index.php" style="display:flex;gap:8px;align-items:flex-end;">
            <input type="hidden" name="action"     value="reply_inquiry"/>
            <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>"/>
            <textarea name="reply" class="admin-input" rows="2" placeholder="Type your reply…" style="flex:1;resize:vertical;"><?= h($inq['admin_reply'] ?? '') ?></textarea>
            <button type="submit" class="btn-admin-primary" style="padding:9px 16px;"><?= icon('check',14) ?> Send</button>
            <button type="button" class="btn-admin-secondary" onclick="toggleReply(<?= $inq['id'] ?>)">Cancel</button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--text3);margin-top:10px;"><?= count($inquiries) ?> inquiries</p>

<script>
function toggleReply(id) {
  const row = document.getElementById('reply_' + id);
  row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>