<?php
/**
 * admin/views/marketing_analytics.php — Fire 10
 */
require_once BASE_PATH . '/includes/marketing_analytics.php';

if (!empty($_GET['ajax_campaign_detail'])) {
    requireAdminPermissionJson('marketing.reports.view');
    $detail = getMarketingCampaignAnalytics((int)($_GET['campaign_id'] ?? 0));
    header('Content-Type: application/json');
    echo json_encode($detail ?: ['error' => 'Campaign not found.']);
    exit;
}

$adminTitle = 'Marketing Analytics';
requireAdminPermission('marketing.reports.view');
include __DIR__ . '/../_layout_top.php';

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 0], true)) $days = 30;

$summary = getMarketingDashboardSummary($days);
$volumeSeries = getMarketingSendVolumeTimeSeries($days > 0 ? min($days, 30) : 14);
$campaigns = getMarketingCampaignsWithMetrics(['limit' => 100, 'offset' => 0])['rows'];

$maxVol = 1;
foreach ($volumeSeries as $d) $maxVol = max($maxVol, $d['whatsapp'], $d['email']);
?>
<style>
.mkta-cards{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;}
@media(max-width:700px){.mkta-cards{grid-template-columns:1fr;}}
.mkta-card{background:var(--admin-card-bg,var(--surface));border:1px solid var(--admin-table-border,var(--border));border-radius:12px;padding:18px;}
.mkta-card-title{font-size:13px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.mkta-metric-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.mkta-metric{text-align:center;}
.mkta-metric-val{font-size:20px;font-weight:700;color:var(--admin-accent,var(--accent));}
.mkta-metric-label{font-size:10.5px;color:var(--admin-text3,var(--text3));text-transform:uppercase;margin-top:2px;}
.mkta-chart{display:flex;align-items:flex-end;gap:3px;height:90px;margin-bottom:8px;}
.mkta-bar-pair{flex:1;display:flex;gap:2px;align-items:flex-end;height:100%;}
.mkta-bar{flex:1;border-radius:2px 2px 0 0;min-height:2px;}
.mkta-bar.wa{background:#4A9B6E;} .mkta-bar.em{background:#C9A24B;}
.mkta-legend{display:flex;gap:14px;font-size:11px;color:var(--admin-text3,var(--text3));}
.mkta-legend span{display:flex;align-items:center;gap:4px;}
.mkta-dot{width:8px;height:8px;border-radius:2px;display:inline-block;}
.mkt-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
.mkt-modal.open{display:flex;}
.mkt-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.2);}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
  <div style="display:flex;gap:6px;">
    <?php foreach (['7'=>'7 Days','30'=>'30 Days','90'=>'90 Days','0'=>'All Time'] as $val=>$label): ?>
    <a href="index.php?page=marketing_analytics&days=<?= $val ?>"
       class="btn-admin-secondary btn-admin-sm" style="<?= $days == (int)$val ? 'background:var(--admin-accent,var(--accent));color:#fff;' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <div style="font-size:12px;color:var(--admin-text3,var(--text3));">
    <?= $summary['active_campaigns'] ?> active campaign(s) · <?= $summary['total_contacts'] ?> active contacts
  </div>
</div>

<div class="mkta-cards">
  <?php foreach (['whatsapp'=>'WhatsApp','email'=>'Email'] as $ch => $chLabel): $m = $summary['channels'][$ch]; ?>
  <div class="mkta-card">
    <p class="mkta-card-title"><?= icon($ch === 'whatsapp' ? 'whatsapp' : 'mail', 15) ?> <?= $chLabel ?></p>
    <div class="mkta-metric-grid" style="margin-bottom:12px;">
      <div class="mkta-metric"><p class="mkta-metric-val"><?= $m['dispatched'] ?></p><p class="mkta-metric-label">Dispatched</p></div>
      <div class="mkta-metric"><p class="mkta-metric-val"><?= $m['delivery_rate'] !== null ? $m['delivery_rate'].'%' : '—' ?></p><p class="mkta-metric-label"><?= $ch==='email' ? 'Engaged Rate' : 'Delivery Rate' ?></p></div>
      <div class="mkta-metric"><p class="mkta-metric-val" style="color:var(--danger,#E84040);"><?= $m['failure_rate'] !== null ? $m['failure_rate'].'%' : '—' ?></p><p class="mkta-metric-label">Failure Rate</p></div>
    </div>
    <?php if ($ch === 'whatsapp'): ?>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));">Read Rate: <strong><?= $m['read_rate'] !== null ? $m['read_rate'].'%' : '—' ?></strong> (<?= $m['read'] ?> read)</p>
    <?php else: ?>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));">Open Rate: <strong><?= $m['open_rate'] !== null ? $m['open_rate'].'%' : '—' ?></strong> (<?= $m['opened'] ?>) · Click Rate: <strong><?= $m['click_rate'] !== null ? $m['click_rate'].'%' : '—' ?></strong> (<?= $m['clicked'] ?>)</p>
    <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:6px;">
      <?= icon('info',10) ?> "Engaged Rate" for email means at least one open or click was recorded — SMTP gives no true
      delivery confirmation, so this is the closest available proxy, not a delivery receipt.
    </p>
    <?php endif; ?>
    <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:10px;"><?= $summary['suppressed'][$ch] ?> contact(s) suppressed on this channel (all-time).</p>
  </div>
  <?php endforeach; ?>
</div>

<div class="admin-form-section">
  <p class="admin-form-section-title">Send Volume (last <?= count($volumeSeries) ?> days)</p>
  <div class="mkta-chart">
    <?php foreach ($volumeSeries as $d): ?>
    <div class="mkta-bar-pair" title="<?= h($d['date']) ?>: WA <?= $d['whatsapp'] ?>, Email <?= $d['email'] ?>">
      <div class="mkta-bar wa" style="height:<?= max(2, round($d['whatsapp']/$maxVol*100)) ?>%;"></div>
      <div class="mkta-bar em" style="height:<?= max(2, round($d['email']/$maxVol*100)) ?>%;"></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mkta-legend"><span><span class="mkta-dot" style="background:#4A9B6E;"></span> WhatsApp</span><span><span class="mkta-dot" style="background:#C9A24B;"></span> Email</span></div>
</div>

<div class="admin-form-section">
  <p class="admin-form-section-title">Campaigns</p>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Channel</th><th>Status</th><th>Recipients</th><th>Dispatched</th><th>Delivery/Engaged</th><th>Failed</th><th style="width:60px;"></th></tr></thead>
      <tbody>
      <?php if (empty($campaigns)): ?>
      <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">No campaigns yet.</td></tr>
      <?php else: foreach ($campaigns as $c): $m = $c['metrics']; ?>
      <tr>
        <td style="font-weight:600;"><?= h($c['name']) ?></td>
        <td><?= h($c['channel']) ?></td>
        <td><span class="badge badge-gray"><?= h(str_replace('_',' ',$c['status'])) ?></span></td>
        <td><?= $m['total_recipients'] ?></td>
        <td><?= $m['dispatched'] ?></td>
        <td><?= $m['delivery_rate'] !== null ? $m['delivery_rate'].'%' : '—' ?></td>
        <td style="<?= $m['failed'] > 0 ? 'color:var(--danger,#E84040);' : '' ?>"><?= $m['failed'] ?></td>
        <td><button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktaOpenDetail(<?= $c['id'] ?>)" title="View Details"><?= icon('eye',13) ?></button></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="mktaDetailModal" class="mkt-modal">
  <div class="mkt-modal-card">
    <div style="display:flex;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--admin-table-border,var(--border));">
      <p id="mktaDetailTitle" style="font-weight:700;font-size:16px;">Campaign Details</p>
      <button type="button" onclick="document.getElementById('mktaDetailModal').classList.remove('open')" style="background:none;border:none;color:var(--admin-text3,var(--text3));cursor:pointer;"><?= icon('close',18) ?></button>
    </div>
    <div style="padding:20px;" id="mktaDetailBody">Loading…</div>
  </div>
</div>

<script>
function esc(s){var d=document.createElement('div');d.textContent=String(s==null?'':s);return d.innerHTML;}
function mktaOpenDetail(id) {
  document.getElementById('mktaDetailModal').classList.add('open');
  document.getElementById('mktaDetailBody').innerHTML = 'Loading…';
  fetch('index.php?page=marketing_analytics&ajax_campaign_detail=1&campaign_id=' + id)
    .then(function(r){return r.json();}).then(function(d) {
      if (d.error) { document.getElementById('mktaDetailBody').textContent = d.error; return; }
      document.getElementById('mktaDetailTitle').textContent = d.campaign.name;
      var r = d.rates;
      var html = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">';
      html += '<div style="text-align:center;"><p style="font-size:20px;font-weight:700;">' + r.dispatched + '</p><p style="font-size:10px;color:var(--admin-text3,var(--text3));text-transform:uppercase;">Dispatched</p></div>';
      html += '<div style="text-align:center;"><p style="font-size:20px;font-weight:700;">' + (r.delivery_rate!=null?r.delivery_rate+'%':'—') + '</p><p style="font-size:10px;color:var(--admin-text3,var(--text3));text-transform:uppercase;">Delivery/Engaged</p></div>';
      html += '<div style="text-align:center;"><p style="font-size:20px;font-weight:700;color:var(--danger,#E84040);">' + (r.failure_rate!=null?r.failure_rate+'%':'—') + '</p><p style="font-size:10px;color:var(--admin-text3,var(--text3));text-transform:uppercase;">Failed</p></div>';
      html += '</div>';

      html += '<p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:8px;">Recipient Status</p>';
      html += '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">';
      Object.keys(d.recipients).forEach(function(k){ if (d.recipients[k] > 0) html += '<span class="badge badge-gray">' + esc(k) + ': ' + d.recipients[k] + '</span>'; });
      html += '</div>';

      if (d.top_errors && d.top_errors.length) {
        html += '<p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:8px;">Top Errors</p>';
        html += '<ul style="font-size:12px;margin:0 0 16px 18px;padding:0;">';
        d.top_errors.forEach(function(e){ html += '<li>' + esc(e.error_message) + ' (' + e.c + ')</li>'; });
        html += '</ul>';
      }

      document.getElementById('mktaDetailBody').innerHTML = html;
    });
}
document.getElementById('mktaDetailModal').addEventListener('click', function(e){ if (e.target === this) this.classList.remove('open'); });
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>