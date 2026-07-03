<?php
/**
 * includes/cloudinary.php
 * Thin cURL wrapper for Cloudinary's unsigned/signed upload API.
 * Free tier: 25GB storage / 25GB bandwidth per month — plenty for previews.
 */

function cloudinaryConfigured(): bool {
    return getenv('CLOUDINARY_CLOUD_NAME') && getenv('CLOUDINARY_API_KEY') && getenv('CLOUDINARY_API_SECRET');
}

/**
 * Upload a local file to Cloudinary using a signed request.
 * Returns ['success'=>bool, 'url'=>string, 'public_id'=>string, 'error'=>string]
 */
function cloudinaryUpload(string $localPath, string $folder = 'room_previews'): array {
    if (!cloudinaryConfigured()) {
        return ['success' => false, 'error' => 'Cloudinary not configured.'];
    }
    if (!file_exists($localPath)) {
        return ['success' => false, 'error' => 'File not found: ' . $localPath];
    }

    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey    = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');

    $timestamp = time();
    $publicId  = $folder . '/' . uniqid('rv_', true);

    // Signature = sha1("folder=..&public_id=..&timestamp=.." + api_secret)
    $paramsToSign = [
        'folder'    => $folder,
        'public_id' => basename($publicId),
        'timestamp' => $timestamp,
    ];
    ksort($paramsToSign);
    $signStr = '';
    foreach ($paramsToSign as $k => $v) $signStr .= "{$k}={$v}&";
    $signStr = rtrim($signStr, '&') . $apiSecret;
    $signature = sha1($signStr);

    $url = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

    $post = [
        'file'       => new CURLFile($localPath),
        'api_key'    => $apiKey,
        'timestamp'  => $timestamp,
        'folder'     => $folder,
        'public_id'  => basename($publicId),
        'signature'  => $signature,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => 'cURL error: ' . $err];
    }

    $data = json_decode($response, true);
    if (!isset($data['secure_url'])) {
        return ['success' => false, 'error' => $data['error']['message'] ?? 'Unknown Cloudinary error'];
    }

    return [
        'success'   => true,
        'url'       => $data['secure_url'],
        'public_id' => $data['public_id'],
    ];
}

/**
 * Delete an asset from Cloudinary (best-effort, ignores failures).
 */
function cloudinaryDelete(string $publicId): void {
    if (!cloudinaryConfigured()) return;
    try {
        $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey    = getenv('CLOUDINARY_API_KEY');
        $apiSecret = getenv('CLOUDINARY_API_SECRET');
        $timestamp = time();
        $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

        $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Throwable $e) {
        error_log('cloudinaryDelete: ' . $e->getMessage());
    }
}