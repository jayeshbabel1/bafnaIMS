<?php
/**
 * includes/marketing_variables.php
 * Fire 6 — Universal variable registry + {{var|fallback}} render engine.
 *
 * Design: variables are resolved from a flat "context" array built by
 * buildMarketingVariableContext(). Fire 7 (campaign engine) and Fire 11/12
 * (automation, catalog integration) populate the $extra parameter with
 * product/selection/salesperson data when that context is available;
 * until then those keys simply render their fallback (or empty string).
 *
 * KNOWN LIMITATION (flagged, not fixed here): catalog_url/selection_url/
 * product_url all point at pages gated by requireLogin(). A contact with
 * no `users` account will hit the login wall. Needs a magic-link or public
 * guest-view mechanism in a later fire — out of scope for template/variable
 * work specifically.
 */
require_once __DIR__ . '/marketing_email.php'; // for marketingUnsubscribeUrl()

/**
 * Registry: variable_key => [
 *   'label'    => human name shown in the "Insert Variable" picker,
 *   'channels' => which channels this is meaningful for (UI filter hint only,
 *                 never blocks rendering — an unused variable just resolves
 *                 to its fallback/empty string, never breaks the message),
 * ]
 */
function marketingVariableRegistry(): array {
    return [
        'customer_name'      => ['label' => 'Customer Name',          'channels' => ['whatsapp','email']],
        'company_name'       => ['label' => 'Company Name',           'channels' => ['whatsapp','email']],
        'mobile'             => ['label' => 'Contact Mobile',         'channels' => ['whatsapp','email']],
        'email'              => ['label' => 'Contact Email',          'channels' => ['whatsapp','email']],
        'city'               => ['label' => 'Contact City',           'channels' => ['whatsapp','email']],
        'catalog_url'        => ['label' => 'Catalog URL',            'channels' => ['whatsapp','email']],
        'selection_url'      => ['label' => 'Client Selection URL',   'channels' => ['whatsapp','email']],
        'selection_pdf'      => ['label' => 'Selection PDF Link',     'channels' => ['whatsapp','email']],
        'product_name'       => ['label' => 'Product Name',           'channels' => ['whatsapp','email']],
        'product_code'       => ['label' => 'Product Quarry Number',  'channels' => ['whatsapp','email']],
        'product_url'        => ['label' => 'Product URL',            'channels' => ['whatsapp','email']],
        'salesperson_name'   => ['label' => 'Salesperson Name',       'channels' => ['whatsapp','email']],
        'salesperson_mobile' => ['label' => 'Salesperson Mobile',     'channels' => ['whatsapp','email']],
        'unsubscribe_url'    => ['label' => 'Unsubscribe Link',       'channels' => ['email']],
    ];
}

/**
 * Builds the flat context array used to resolve {{var}} placeholders.
 * $contact = a row from marketing_contacts (or compatible array).
 * $extra   = optional overrides/additions (product_name, selection_url, etc.)
 *            supplied by the caller (campaign engine / automation).
 */
function buildMarketingVariableContext(array $contact, array $extra = [], string $channel = 'email'): array {
    $context = [
        'customer_name' => $contact['name']  ?? '',
        'mobile'        => $contact['mobile'] ?? '',
        'email'         => $contact['email']  ?? '',
        'city'          => $contact['city']   ?? '',
        'company_name'  => getSetting('company_name', APP_NAME),
        'catalog_url'   => rtrim(BASE_URL, '/') . '/index.php?page=catalog',
    ];

    if ($channel === 'email' && isset($contact['id'])) {
        $context['unsubscribe_url'] = marketingUnsubscribeUrl((int)$contact['id'], 'email');
    }

    // Extra context (product/selection/salesperson) always wins if supplied —
    // this is how Fire 7/11/12 inject real data without touching this file.
    return array_merge($context, array_filter($extra, fn($v) => $v !== null));
}

/**
 * Sample context for template preview only — clearly fake data, never
 * touches the database, so previewing a template never has side effects
 * or depends on real records existing.
 */
function getMarketingSampleContext(string $channel): array {
    return [
        'customer_name'      => 'Rahul Sharma',
        'mobile'             => '+91 98765 43210',
        'email'              => 'rahul@example.com',
        'city'               => 'Mumbai',
        'company_name'       => getSetting('company_name', APP_NAME),
        'catalog_url'        => rtrim(BASE_URL, '/') . '/index.php?page=catalog',
        'selection_url'      => rtrim(BASE_URL, '/') . '/index.php?page=client_selections&client_id=101',
        'selection_pdf'      => rtrim(BASE_URL, '/') . '/storage/catalogs/sample_selection.pdf',
        'product_name'       => 'Calacatta Oro Supremo',
        'product_code'       => 'QM-0421',
        'product_url'        => rtrim(BASE_URL, '/') . '/index.php?page=product&id=1',
        'salesperson_name'   => 'Bafna Sales Team',
        'salesperson_mobile' => '+91 98980 74441',
        'unsubscribe_url'    => rtrim(BASE_URL, '/') . '/marketing_unsubscribe.php?preview=1',
    ];
}

/**
 * Resolves {{var}} and {{var|fallback}} placeholders. Never throws, never
 * leaves a raw {{...}} in output — an unresolved variable with no fallback
 * simply becomes an empty string, guaranteeing the message is never
 * malformed because a field was missing.
 *
 * Email channel HTML-escapes resolved values (contact-supplied strings like
 * name/city could otherwise break markup or enable injection); WhatsApp is
 * plain text so no escaping is applied there.
 */
function renderMarketingTemplate(string $text, array $context, string $channel = 'email'): string {
    return preg_replace_callback(
        '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:\|([^{}]*))?\}\}/',
        function (array $m) use ($context, $channel) {
            $varName  = $m[1];
            $fallback = isset($m[2]) ? trim($m[2]) : '';
            $value    = $context[$varName] ?? '';
            $resolved = ($value === '' || $value === null) ? $fallback : (string)$value;
            return $channel === 'email' ? h($resolved) : $resolved;
        },
        $text
    );
}

/** Returns every {{var}} name referenced in a template body (for validation). */
function extractMarketingTemplateVariables(string $text): array {
    preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(?:\|[^{}]*)?\}\}/', $text, $matches);
    return array_values(array_unique($matches[1] ?? []));
}

/**
 * Soft validation — flags unknown variable names so a typo like
 * {{custmer_name}} is caught before send, but never blocks saving (a
 * template may legitimately be a work-in-progress).
 */
function validateMarketingTemplateVariables(string $text): array {
    $registry = marketingVariableRegistry();
    $used     = extractMarketingTemplateVariables($text);
    $unknown  = array_values(array_diff($used, array_keys($registry)));
    return [
        'valid'   => empty($unknown),
        'used'    => $used,
        'unknown' => $unknown,
    ];
}