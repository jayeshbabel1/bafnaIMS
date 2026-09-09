<?php
/**
 * admin/views/_marketing_history_widget.php — Fire 10
 * Drop-in "Marketing History" card for any admin page with a users.id or
 * clients.id in scope. Usage from any admin/views/*.php file:
 *
 *   <?php
 *     $mktWidgetSourceType = 'client';   // or 'user'
 *     $mktWidgetSourceId   = $client['id'];
 *     include __DIR__ . '/_marketing_history_widget.php';
 *   ?>
 *
 * includes/marketing_analytics.php is required globally from admin/index.php
 * alongside the other marketing_* includes, so no extra require_once is
 * needed at the include site.
 */
if (!function_exists('getMarketingHistoryForSourceRecord')) {
    echo '<!-- marketing history widget: includes/marketing_analytics.php not loaded -->';
    return;
}
$mktWidgetData = getMarketingHistoryForSourceRecord($mktWidgetSourceType ?? '', (int)($mktWidgetSourceId ?? 0));
?>
<div class="admin-form-section" style="margin-top:16px;">
  <p class="admin-form-section-title">Marketing History</p>
  <?php if (!$mktWidgetData): ?>
  <p style="font-size:13px;color:var(--admin-text3,var(--text3));">
    Not yet tracked as a marketing contact. Use "Sync from <?= h(ucfirst($mktWidgetSourceType ?? 'Users')) ?>" on the
    <a href="index.php?page=marketing_contacts">Marketing → Contacts</a> page to create one.
  </p>
  <?php else: $c = $mktWidgetData['contact']; ?>
  <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-bottom:12px;font-size:12px;">
    <span>WhatsApp: <span class="badge <?= $c['whatsapp_opt_in']?'badge-green':'badge-gray' ?>"><?= $c['whatsapp_opt_in']?'Opted In':'Opted Out' ?></span></span>
    <span>Email: <span class="badge <?= $c['email_opt_in']?'badge-green':'badge-gray' ?>"><?= $c['email_opt_in']?'Opted In':'Opted Out' ?></span></span>
    <?php if ($mktWidgetData['pending_in_queue'] > 0): ?><span class="badge badge-gold"><?= $mktWidgetData['pending_in_queue'] ?> queued</span><?php endif; ?>
    <a href="index.php?page=marketing_contact_profile&id=<?= $c['id'] ?>" style="margin-left:auto;font-size:12px;">View Full Profile →</a>
  </div>
  <?php if (empty($mktWidgetData['recent'])): ?>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));">No marketing messages sent yet.</p>
  <?php else: ?>
  <table class="admin-table" style="font-size:12px;">
    <thead><tr><th>Date</th><th>Channel</th><th>Campaign</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($mktWidgetData['recent'] as $m): ?>
    <tr>
      <td><?= date('d M Y', $m['created_at']) ?></td>
      <td><?= h($m['channel']) ?></td>
      <td><?= h($m['campaign_name'] ?: '—') ?></td>
      <td><span class="badge badge-gray"><?= h($m['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; endif; ?>
</div>