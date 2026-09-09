<?php
/**
 * includes/marketing/ProviderInterface.php
 * Fire 4 — channel-provider abstraction. Campaign/queue logic (Fire 7/8)
 * talks only to this interface, never to a specific vendor's API directly,
 * so SMS/Telegram/other providers can be added later without touching the
 * campaign engine.
 *
 * All methods return a consistent array shape rather than throwing, mirroring
 * this codebase's existing convention (cloudinaryUpload(), sendMail(), etc.):
 *   ['success' => bool, 'error' => ?string, ...method-specific keys]
 */
interface MarketingChannelProvider {

    /** Verifies stored credentials are valid by calling the provider's API. */
    public function validateCredentials(): array;

    /**
     * Sends an approved template message.
     * @param array $components Provider-specific template component payload (header/body variables).
     */
    public function sendTemplate(string $to, string $templateName, string $language, array $components = []): array;

    /** Sends a single media attachment (image/document) with optional caption. */
    public function sendMedia(string $to, string $mediaUrl, string $mediaType, string $caption = ''): array;

    /** Polls current delivery status for a previously-sent message (fallback path if webhooks are delayed/down). */
    public function getMessageStatus(string $providerMessageId): array;
}