<?php
/**
 * includes/marketing/SmtpEmailProvider.php
 * Fire 5 — SMTP/PHPMailer implementation of MarketingChannelProvider,
 * thinly wrapping the existing includes/mailer.php::sendMail(). Never
 * reimplements SMTP transport logic.
 *
 * Interface-shape note: email doesn't have WhatsApp's "approved template
 * name + language code" concept — templates here live in our own
 * marketing_templates table (Fire 6), not a provider-side registry. So for
 * this provider, sendTemplate()'s $templateName is used only as a fallback
 * subject/logging label, $language is ignored, and the actual rendered
 * content is carried in $components as:
 *   ['subject'=>string,'html'=>string,'text'=>string,'to_name'=>string,
 *    'attachments'=>string[] (local file paths),'cc'=>string,'bcc'=>string]
 */
require_once __DIR__ . '/ProviderInterface.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../marketing_email.php';

class SmtpEmailProvider implements MarketingChannelProvider {

    public function validateCredentials(): array {
        $smtp = getSmtpSettings();
        if (!$smtp['smtp_enabled']) {
            return ['valid' => false, 'error' => 'SMTP is not enabled. Configure it in Settings → Mail Settings first.'];
        }
        if (trim($smtp['smtp_host']) === '') {
            return ['valid' => false, 'error' => 'SMTP host is not configured.'];
        }
        return [
            'valid'      => true,
            'smtp_host'  => $smtp['smtp_host'],
            'from_email' => $smtp['smtp_from_email'],
            'from_name'  => $smtp['smtp_from_name'],
        ];
    }

    public function sendTemplate(string $to, string $templateName, string $language, array $components = []): array {
        $subject = $components['subject'] ?? $templateName;
        $html    = $components['html']    ?? '';
        $text    = $components['text']    ?? '';
        $toName  = $components['to_name'] ?? '';
        $attachments = $components['attachments'] ?? [];
        $cc = $components['cc'] ?? '';
        $bcc = $components['bcc'] ?? '';

        if (trim($html) === '') {
            return ['success' => false, 'error' => 'Email body is empty — nothing to send.'];
        }

        $emailSettings = getMarketingEmailSettings();
        $result = sendMail(
            $to, $subject, $html, $text, $toName, $attachments, $cc, $bcc,
            $emailSettings['from_name_override'] ?: null,
            $emailSettings['from_email_override'] ?: null,
            $emailSettings['reply_to'] ?: null
        );

        // SMTP has no provider-assigned message ID the way Meta's Graph API
        // does — honestly reflect that rather than fabricating one.
        return $result['success']
            ? ['success' => true, 'provider_message_id' => null]
            : ['success' => false, 'error' => $result['error']];
    }

    public function sendMedia(string $to, string $mediaUrl, string $mediaType, string $caption = ''): array {
        $isRemote = (bool)preg_match('#^https?://#i', $mediaUrl);
        $tmpFile  = null;
        $localPath = $mediaUrl;

        if ($isRemote) {
            $data = @file_get_contents($mediaUrl);
            if ($data === false) {
                return ['success' => false, 'error' => 'Could not download media from URL: ' . $mediaUrl];
            }
            $tmpFile = sys_get_temp_dir() . '/mkt_email_media_' . uniqid('', true);
            file_put_contents($tmpFile, $data);
            $localPath = $tmpFile;
        }
        if (!file_exists($localPath)) {
            return ['success' => false, 'error' => 'Media file not found: ' . $localPath];
        }

        $emailSettings = getMarketingEmailSettings();
        $subject = $caption !== '' ? $caption : 'Attachment';
        $html    = '<p>' . nl2br(h($caption ?: 'Please find the attached file.')) . '</p>';

        $result = sendMail(
            $to, $subject, $html, $caption, '', [$localPath], '', '',
            $emailSettings['from_name_override'] ?: null,
            $emailSettings['from_email_override'] ?: null,
            $emailSettings['reply_to'] ?: null
        );

        if ($tmpFile && file_exists($tmpFile)) @unlink($tmpFile);

        return $result['success']
            ? ['success' => true, 'provider_message_id' => null]
            : ['success' => false, 'error' => $result['error']];
    }

    public function getMessageStatus(string $providerMessageId): array {
        // SMTP has no polling API. Delivered/opened/clicked/bounced state
        // arrives via tracking pixel + click-redirect + bounce webhook,
        // all of which ship in Fire 9 — same "not supported here" honesty
        // as WhatsAppCloudProvider::getMessageStatus().
        return ['success' => false, 'error' => 'Status polling is not supported for SMTP email; rely on tracking pixel/webhook events.'];
    }
}