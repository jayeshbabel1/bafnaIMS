<?php
/**
 * includes/marketing/WhatsAppCloudProvider.php
 * Fire 4 — Meta WhatsApp Cloud API implementation of MarketingChannelProvider.
 * cURL usage mirrors includes/cloudinary.php's idiom (curl_init/setopt_array/
 * exec/getinfo/error/close) rather than introducing a new HTTP client library.
 */
require_once __DIR__ . '/ProviderInterface.php';

class WhatsAppCloudProvider implements MarketingChannelProvider {

    private string $phoneNumberId;
    private string $businessAccountId;
    private string $accessToken;
    private string $apiVersion;

    public function __construct(array $settings) {
        $this->phoneNumberId     = $settings['phone_number_id']      ?? '';
        $this->businessAccountId = $settings['business_account_id'] ?? '';
        $this->accessToken       = $settings['access_token']         ?? '';
        $this->apiVersion        = $settings['api_version']          ?: 'v21.0';
    }

    public static function fromStoredSettings(): self {
        return new self(getMarketingWhatsAppSettings());
    }

    private function baseUrl(string $path): string {
        return "https://graph.facebook.com/{$this->apiVersion}/{$path}";
    }

    /** Shared low-level request helper — GET or POST JSON to the Graph API. */
    private function request(string $method, string $url, array $body = null): array {
        if ($this->accessToken === '') {
            return ['success' => false, 'error' => 'WhatsApp access token is not configured.'];
        }
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . $this->accessToken];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['success' => false, 'error' => 'Connection error: ' . $curlErr];
        }
        $decoded = json_decode((string)$response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            $code = $decoded['error']['code'] ?? null;
            return ['success' => false, 'error' => $msg, 'error_code' => $code, 'raw' => $decoded];
        }
        return ['success' => true, 'data' => $decoded];
    }

    public function validateCredentials(): array {
        if ($this->phoneNumberId === '') {
            return ['valid' => false, 'error' => 'Phone Number ID is not configured.'];
        }

        $phoneCheck = $this->request('GET', $this->baseUrl(
            $this->phoneNumberId . '?fields=verified_name,display_phone_number,quality_rating,code_verification_status'
        ));
        if (!$phoneCheck['success']) {
            return ['valid' => false, 'error' => 'Phone number check failed: ' . $phoneCheck['error']];
        }

        $wabaInfo = null;
        if ($this->businessAccountId !== '') {
            $wabaCheck = $this->request('GET', $this->baseUrl($this->businessAccountId . '?fields=name,id'));
            if (!$wabaCheck['success']) {
                return ['valid' => false, 'error' => 'Business Account check failed: ' . $wabaCheck['error']];
            }
            $wabaInfo = $wabaCheck['data'];
        }

        return [
            'valid'   => true,
            'phone'   => $phoneCheck['data'],
            'business_account' => $wabaInfo,
        ];
    }

    public function sendTemplate(string $to, string $templateName, string $language, array $components = []): array {
        if ($this->phoneNumberId === '') {
            return ['success' => false, 'error' => 'Phone Number ID is not configured.'];
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $language],
            ],
        ];
        if (!empty($components)) $payload['template']['components'] = $components;

        $result = $this->request('POST', $this->baseUrl($this->phoneNumberId . '/messages'), $payload);
        if (!$result['success']) return $result;

        $providerMessageId = $result['data']['messages'][0]['id'] ?? null;
        return ['success' => true, 'provider_message_id' => $providerMessageId, 'raw' => $result['data']];
    }

    public function sendMedia(string $to, string $mediaUrl, string $mediaType, string $caption = ''): array {
        if ($this->phoneNumberId === '') {
            return ['success' => false, 'error' => 'Phone Number ID is not configured.'];
        }
        $mediaType = in_array($mediaType, ['image', 'document', 'video'], true) ? $mediaType : 'document';
        $mediaObj  = ['link' => $mediaUrl];
        if ($caption !== '') $mediaObj['caption'] = $caption;

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => $mediaType,
            $mediaType           => $mediaObj,
        ];

        $result = $this->request('POST', $this->baseUrl($this->phoneNumberId . '/messages'), $payload);
        if (!$result['success']) return $result;

        $providerMessageId = $result['data']['messages'][0]['id'] ?? null;
        return ['success' => true, 'provider_message_id' => $providerMessageId, 'raw' => $result['data']];
    }

    public function getMessageStatus(string $providerMessageId): array {
        // Meta's Cloud API does not expose a direct "get status by message ID"
        // read endpoint — delivery/read state arrives exclusively via webhook
        // (Fire 9). This method exists to satisfy the interface contract for
        // future providers that DO support polling, and returns a clear
        // not-supported result rather than pretending to have data.
        return ['success' => false, 'error' => 'Status polling is not supported by WhatsApp Cloud API; rely on webhook events.'];
    }
  
  /**
     * Fire 6 — read-only fetch of this account's approved/pending/rejected
     * message templates. NOT part of MarketingChannelProvider — this is a
     * WhatsApp-specific capability (no submission/creation here, Meta's own
     * Business Manager UI remains the place templates are authored/submitted).
     */
    public function listTemplates(): array {
        if ($this->businessAccountId === '') {
            return ['success' => false, 'error' => 'WhatsApp Business Account ID is not configured.'];
        }
        $result = $this->request('GET', $this->baseUrl(
            $this->businessAccountId . '/message_templates?fields=name,status,category,language,components&limit=200'
        ));
        if (!$result['success']) return $result;

        return ['success' => true, 'templates' => $result['data']['data'] ?? []];
    }
  }