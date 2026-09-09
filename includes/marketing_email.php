<?php
/**
 * includes/marketing_email.php
 * Fire 5 — Email marketing settings CRUD. Deliberately does NOT duplicate
 * SMTP host/port/credentials — those remain single-sourced in the
 * `settings` table via includes/mailer.php::getSmtpSettings(), edited on
 * the existing admin/views/smtp.php page. This file only adds the
 * marketing-specific layer on top: an optional From-identity override,
 * Reply-To, the compliance unsubscribe footer, and tracking toggles.
 *
 * None of these values are secrets, so unlike Fire 4's WhatsApp settings
 * they are stored in marketing_provider_settings with is_encrypted=0
 * (the default) via the existing setMarketingProviderSetting() helper.
 */
require_once __DIR__ . '/marketing.php';

const MARKETING_EMAIL_DEFAULTS = [
    'email_marketing_enabled'   => '0',
    'email_from_name_override'  => '',
    'email_from_email_override' => '',
    'email_reply_to'            => '',
    'email_unsubscribe_footer'  => "You're receiving this email because you're a registered contact of {{company_name}}. <a href=\"{{unsubscribe_url}}\">Unsubscribe</a> from marketing emails.",
    'email_track_opens'         => '1',
    'email_track_clicks'        => '1',
];

function getMarketingEmailSettings(): array {
    ensureMarketingTables();
    $out = [];
    foreach (MARKETING_EMAIL_DEFAULTS as $key => $default) {
        $out[$key] = getMarketingProviderSetting($key, $default);
    }
    return [
        'enabled'              => $out['email_marketing_enabled'] === '1',
        'from_name_override'   => $out['email_from_name_override'],
        'from_email_override'  => $out['email_from_email_override'],
        'reply_to'             => $out['email_reply_to'],
        'unsubscribe_footer'   => $out['email_unsubscribe_footer'],
        'track_opens'          => $out['email_track_opens'] === '1',
        'track_clicks'         => $out['email_track_clicks'] === '1',
    ];
}

function saveMarketingEmailSettings(array $data): array {
    ensureMarketingTables();

    $fromEmail = trim($data['from_email_override'] ?? '');
    if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'From Email override is not a valid email address.'];
    }
    $replyTo = trim($data['reply_to'] ?? '');
    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Reply-To is not a valid email address.'];
    }

    setMarketingProviderSetting('email_marketing_enabled', !empty($data['enabled']) ? '1' : '0');
    setMarketingProviderSetting('email_from_name_override', trim($data['from_name_override'] ?? ''));
    setMarketingProviderSetting('email_from_email_override', $fromEmail);
    setMarketingProviderSetting('email_reply_to', $replyTo);
    setMarketingProviderSetting('email_unsubscribe_footer', trim($data['unsubscribe_footer'] ?? '') ?: MARKETING_EMAIL_DEFAULTS['email_unsubscribe_footer']);
    setMarketingProviderSetting('email_track_opens', !empty($data['track_opens']) ? '1' : '0');
    setMarketingProviderSetting('email_track_clicks', !empty($data['track_clicks']) ? '1' : '0');

    return ['success' => true];
}

/**
 * Sends a live test email through the exact marketing from-identity/
 * reply-to configuration, so what the admin sees in their inbox matches
 * what a real campaign send will produce — not a generic SMTP ping.
 */
function testMarketingEmailConnection(string $testEmail): array {
    require_once __DIR__ . '/marketing/SmtpEmailProvider.php';

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Enter a valid test email address.'];
    }

    $provider = new SmtpEmailProvider();
    $cred = $provider->validateCredentials();
    if (!$cred['valid']) {
        return ['success' => false, 'error' => $cred['error']];
    }

    $settings = getMarketingEmailSettings();
    $footer = strtr($settings['unsubscribe_footer'], [
        '{{company_name}}'    => getSetting('company_name', APP_NAME),
        '{{unsubscribe_url}}' => '#preview-only',
    ]);

    $html = '<h2 style="font-family:sans-serif;">Marketing Email Test</h2>'
          . '<p style="font-family:sans-serif;">This is a test send from the Marketing module\'s email settings. '
          . 'If you received this, your marketing From-identity and SMTP connection are working correctly.</p>'
          . '<hr/><p style="font-size:12px;color:#888;font-family:sans-serif;">' . $footer . '</p>';

    $result = $provider->sendTemplate($testEmail, 'Marketing Settings Test', 'en', [
        'subject' => 'Test — ' . (APP_NAME) . ' Marketing Email',
        'html'    => $html,
    ]);

    return $result['success']
        ? ['success' => true]
        : ['success' => false, 'error' => $result['error']];
}

// ── Unsubscribe link signing ─────────────────────────────────────────────
// Groundwork only — the receiving landing page (marketing_unsubscribe.php)
// ships in Fire 9 alongside webhooks/tracking. Generating signed links now
// means Fire 6's template variable engine can reference {{unsubscribe_url}}
// immediately without waiting on Fire 9.
function _marketingUnsubscribeSecret(): string {
    static $key = null;
    if ($key !== null) return $key;
    $key = getSetting('marketing_unsubscribe_secret', '');
    if ($key === '') {
        $key = bin2hex(random_bytes(24));
        setSetting('marketing_unsubscribe_secret', $key);
    }
    return $key;
}

function marketingUnsubscribeUrl(int $contactId, string $channel): string {
    $sig = substr(hash_hmac('sha256', $contactId . ':' . $channel, _marketingUnsubscribeSecret()), 0, 32);
    return rtrim(BASE_URL, '/') . '/marketing_unsubscribe.php?c=' . $contactId . '&ch=' . urlencode($channel) . '&sig=' . $sig;
}

function verifyMarketingUnsubscribeSignature(int $contactId, string $channel, string $sig): bool {
    $expected = substr(hash_hmac('sha256', $contactId . ':' . $channel, _marketingUnsubscribeSecret()), 0, 32);
    return hash_equals($expected, $sig);
}