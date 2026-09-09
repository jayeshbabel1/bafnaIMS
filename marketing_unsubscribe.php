<?php
/**
 * marketing_unsubscribe.php
 * Fire 9 — public unsubscribe landing page.
 * URL: marketing_unsubscribe.php?c={contact_id}&ch={channel}&sig=...
 * (matches includes/marketing_email.php::marketingUnsubscribeUrl())
 *
 * Requires a confirming POST rather than unsubscribing on bare GET — mail
 * security scanners are known to auto-prefetch inbox links, which would
 * otherwise silently unsubscribe people who never clicked anything.
 */
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/marketing_email.php';
require_once BASE_PATH . '/includes/marketing_contacts.php';

$contactId = (int)($_GET['c'] ?? $_POST['c'] ?? 0);
$channelIn = $_GET['ch'] ?? $_POST['ch'] ?? '';
$channel   = in_array($channelIn, ['whatsapp', 'email'], true) ? $channelIn : '';
$sig       = $_GET['sig'] ?? $_POST['sig'] ?? '';

$validSig = $contactId && $channel && verifyMarketingUnsubscribeSignature($contactId, $channel, $sig);
$companyName = h(getSetting('company_name', APP_NAME));
$done = false; $error = '';

if ($validSig && $_SERVER['REQUEST_METHOD'] === 'POST') {
    addMarketingSuppression($contactId, $channel, 'user_requested', 'unsubscribe_link');
    $col = $channel === 'whatsapp' ? 'whatsapp_opt_in' : 'email_opt_in';
    getDB()->prepare("UPDATE marketing_contacts SET {$col}=0, updated_at=? WHERE id=?")->execute([time(), $contactId]);
    logMarketingAudit('contact_unsubscribed', 'marketing_contacts', $contactId, "channel={$channel} source=link");
    $done = true;
} elseif (!$validSig) {
    $error = 'This unsubscribe link is invalid or has expired.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Unsubscribe — <?= $companyName ?></title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f1ec;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
.card{background:#fff;border-radius:14px;padding:32px;max-width:420px;width:100%;box-shadow:0 8px 28px rgba(0,0,0,.08);text-align:center;}
h1{font-size:18px;margin:0 0 12px;}
p{font-size:14px;color:#555;line-height:1.6;}
button{background:#1c1c1c;color:#fff;border:none;border-radius:8px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;margin-top:14px;}
.success{color:#3D8B6E;}
.error{color:#E84040;}
</style>
</head>
<body>
<div class="card">
<?php if ($done): ?>
  <h1 class="success">You're unsubscribed</h1>
  <p>You will no longer receive <?= $channel === 'whatsapp' ? 'WhatsApp' : 'email' ?> marketing messages from <?= $companyName ?>. Any transactional messages related to your account or orders are unaffected.</p>
<?php elseif ($error): ?>
  <h1 class="error">Link invalid</h1>
  <p><?= h($error) ?></p>
<?php else: ?>
  <h1>Unsubscribe from <?= $companyName ?> marketing?</h1>
  <p>You're about to stop receiving <?= $channel === 'whatsapp' ? 'WhatsApp' : 'email' ?> marketing messages. This won't affect any transactional communication about your orders or account.</p>
  <form method="POST">
    <input type="hidden" name="c" value="<?= (int)$contactId ?>"/>
    <input type="hidden" name="ch" value="<?= h($channel) ?>"/>
    <input type="hidden" name="sig" value="<?= h($sig) ?>"/>
    <button type="submit">Confirm Unsubscribe</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>