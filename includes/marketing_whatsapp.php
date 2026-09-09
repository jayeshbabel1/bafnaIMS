<?php
/**
 * includes/marketing_whatsapp.php
 * Fire 4 — WhatsApp Business API settings CRUD (encrypted at rest via
 * marketing.php's setMarketingProviderSetting()/getMarketingProviderSetting(),
 * which already encrypt whatsapp_access_token and whatsapp_webhook_verify_token
 * per MARKETING_ENCRYPTED_SETTING_KEYS defined in Fire 2).
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing/WhatsAppCloudProvider.php';

const MARKETING_WHATSAPP_DEFAULTS = [
    'whatsapp_enabled'              => '0',
    'whatsapp_phone_number_id'      => '',
    'whatsapp_business_account_id'  => '',
    'whatsapp_access_token'         => '',
    'whatsapp_app_secret'           => '',
    'whatsapp_api_version'          => 'v21.0',
    'whatsapp_webhook_verify_token' => '',
    'whatsapp_default_language'     => 'en_US',
    'whatsapp_timezone'             => 'Asia/Kolkata',
    'whatsapp_sender_label'         => '',
];

/**
 * Returns all WhatsApp settings, decrypted, merged with defaults.
 * Shape expected by WhatsAppCloudProvider::__construct() plus a couple
 * of display-only fields for the settings screen.
 */
function getMarketingWhatsAppSettings(): array {
    ensureMarketingTables();
    $out = [];
    foreach (MARKETING_WHATSAPP_DEFAULTS as $key => $default) {
        $out[$key] = getMarketingProviderSetting($key, $default);
    }
    // Re-key to the plain names WhatsAppCloudProvider expects.
    return [
        'enabled'              => $out['whatsapp_enabled'] === '1',
        'phone_number_id'      => $out['whatsapp_phone_number_id'],
        'business_account_id'  => $out['whatsapp_business_account_id'],
        'access_token'         => $out['whatsapp_access_token'],
        'app_secret'           => $out['whatsapp_app_secret'],
        'api_version'          => $out['whatsapp_api_version'],
        'webhook_verify_token' => $out['whatsapp_webhook_verify_token'],
        'default_language'     => $out['whatsapp_default_language'],
        'timezone'             => $out['whatsapp_timezone'],
        'sender_label'         => $out['whatsapp_sender_label'],
    ];
}

/**
 * Saves WhatsApp settings from a form submission. Access token is only
 * overwritten if a new non-empty value was submitted (mirrors the SMTP
 * settings page's password-field convention — never force re-entry of a
 * secret just to change an unrelated field).
 */
function saveMarketingWhatsAppSettings(array $data): array {
    ensureMarketingTables();

    setMarketingProviderSetting('whatsapp_enabled', !empty($data['enabled']) ? '1' : '0');
    setMarketingProviderSetting('whatsapp_phone_number_id', trim($data['phone_number_id'] ?? ''));
    setMarketingProviderSetting('whatsapp_business_account_id', trim($data['business_account_id'] ?? ''));
    setMarketingProviderSetting('whatsapp_api_version', trim($data['api_version'] ?? '') ?: 'v21.0');
    setMarketingProviderSetting('whatsapp_default_language', trim($data['default_language'] ?? '') ?: 'en_US');
    setMarketingProviderSetting('whatsapp_timezone', trim($data['timezone'] ?? '') ?: 'Asia/Kolkata');
    setMarketingProviderSetting('whatsapp_sender_label', trim($data['sender_label'] ?? ''));

   if (!empty($data['access_token'])) {
        setMarketingProviderSetting('whatsapp_access_token', trim($data['access_token']));
    }
    if (!empty($data['app_secret'])) {
        setMarketingProviderSetting('whatsapp_app_secret', trim($data['app_secret']));
    }
    if (!empty($data['webhook_verify_token'])) {
        setMarketingProviderSetting('whatsapp_webhook_verify_token', trim($data['webhook_verify_token']));
    }

    return ['success' => true];
}

/** The URL to paste into Meta App Dashboard → WhatsApp → Configuration → Webhook. Live from Fire 9. */
function getMarketingWhatsAppWebhookUrl(): string {
    return rtrim(BASE_URL, '/') . '/marketing_webhook.php?provider=whatsapp';
}


/** Runs the live Test Connection check against Meta's Graph API. */
function testMarketingWhatsAppConnection(): array {
    $provider = WhatsAppCloudProvider::fromStoredSettings();
    return $provider->validateCredentials();
}


function verifyMarketingWhatsAppWebhookSignature(string $rawBody, string $signatureHeader): bool {
    $appSecret = getMarketingProviderSetting('whatsapp_app_secret', '');
    if ($appSecret === '') {
        error_log('WhatsApp webhook: whatsapp_app_secret is not configured — signature verification SKIPPED (insecure). Set it in Marketing → WhatsApp API.');
        return true;
    }
    if (!str_starts_with($signatureHeader, 'sha256=')) return false;
    $expected = hash_hmac('sha256', $rawBody, $appSecret);
    return hash_equals($expected, substr($signatureHeader, 7));
}