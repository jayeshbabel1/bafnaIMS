<?php
/**
 * marketing_webhook.php
 * Fire 9 — public entrypoint for Meta WhatsApp Cloud API webhooks.
 * URL: BASE_URL/marketing_webhook.php?provider=whatsapp
 * (matches includes/marketing_whatsapp.php::getMarketingWhatsAppWebhookUrl())
 *
 * BOOTSTRAP NOTE: index.php's contents weren't in my context (came through
 * empty), so this bootstrap mirrors the BASE_PATH/config/db pattern already
 * confirmed working in Fire 8's cron scripts. If your real bootstrap wires
 * config/sessions differently, tell me and I'll align this exactly.
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing.php';
require_once BASE_PATH . '/includes/marketing_whatsapp.php';
require_once BASE_PATH . '/includes/marketing_webhook_handler.php';

$provider = $_GET['provider'] ?? 'whatsapp';
if ($provider !== 'whatsapp') { http_response_code(404); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = handleWhatsAppWebhookVerification($_GET);
    if ($challenge !== null) { echo $challenge; exit; }
    http_response_code(403);
    echo 'Verification failed.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $result = handleWhatsAppWebhookPayload($rawBody, $signature);
    http_response_code($result['success'] ? 200 : 401);
    echo json_encode($result);
    exit;
}

http_response_code(405);