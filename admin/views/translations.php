<?php
/**
 * admin/views/translations.php
 * Multi-language content manager — Products, Categories, UI Strings, FAQ.
 * Tabs = entity type, sub-tabs = language (hi/gu/mr — English is the base,
 * not editable here).
 */
ini_set('display_errors', 1); error_reporting(E_ALL);
// ── AJAX: save one entity_type + lang batch ─────────────────────────────────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'save_translations_batch') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('translations.manage');
    csrfVerify(true);

    $entityType = $_POST['entity_type'] ?? '';
    $lang       = $_POST['lang'] ?? '';
    $rowsJson   = $_POST['rows'] ?? '[]';
    $rows       = json_decode($rowsJson, true);

    if (!in_array($entityType, ['product','category','ui_string','faq'], true) || !is_array($rows)) {
        echo json_encode(['success' => false, 'error' => 'Invalid payload.']); exit;
    }

    $result = saveTranslations($entityType, $lang, $rows);
    echo json_encode($result);
    exit;
}

$adminTitle = 'Translations';
requireAdminPermission('translations.manage');
include __DIR__ . '/../_layout_top.php';

$langs = array_filter(SUPPORTED_LANGS, fn($l) => $l !== 'en'); // hi, gu, mr only

// ── Data loaders per tab ─────────────────────────────────────────────────────
$db = getDB();
$products = $db->query("SELECT id, name, description, category, subcategory, color_subcategory, finish, origin FROM products ORDER BY name ASC")->fetchAll();

require_once BASE_PATH . '/includes/categories.php';
$categoryEntities = [];
foreach (getCategoryNames() as $c) $categoryEntities[] = ['id' => 'cat_' . $c, 'label' => $c, 'group' => 'Stone Type'];
foreach (COLOR_SUBCATEGORIES as $c) $categoryEntities[] = ['id' => 'color_' . $c, 'label' => $c, 'group' => 'Color'];

// UI strings — key => [English fallback, section]
$uiStrings = [
    'btn_cancel' => ['Cancel', 'Common / Shared'],
    'btn_save' => ['Save', 'Common / Shared'],
    'btn_send' => ['Send', 'Common / Shared'],
    'btn_continue' => ['Continue', 'Common / Shared'],
    'btn_clear' => ['Clear', 'Common / Shared'],
    'btn_remove' => ['Remove', 'Common / Shared'],
    'btn_rename' => ['Rename', 'Common / Shared'],
    'btn_view' => ['View', 'Common / Shared'],
    'btn_download' => ['Download', 'Common / Shared'],
    'btn_email' => ['Email', 'Common / Shared'],
    'btn_go_back' => ['Go Back', 'Common / Shared'],
    'btn_edit' => ['Edit', 'Common / Shared'],
    'title_delete' => ['Delete', 'Common / Shared'],
    'btn_yes' => ['Yes', 'Common / Shared'],
    'btn_no' => ['No', 'Common / Shared'],
    'form_email_address' => ['Email Address', 'Common / Shared'],
    'form_city' => ['City', 'Common / Shared'],
    'form_notes' => ['Notes', 'Common / Shared'],
    'form_mobile' => ['Mobile', 'Common / Shared'],
    'form_full_name' => ['Full Name', 'Common / Shared'],
    'form_firm_studio' => ['Firm / Studio', 'Common / Shared'],
    'form_email_placeholder' => ['client@example.com', 'Common / Shared'],
    'form_city_placeholder' => ['e.g. Mumbai', 'Common / Shared'],
    'form_mobile_placeholder' => ['98765 43210', 'Common / Shared'],
    'form_studio_email_placeholder' => ['you@studio.com', 'Common / Shared'],
    'hint_min_8_chars' => ['Min. 8 characters', 'Common / Shared'],
    'label_thickness' => ['Thickness', 'Common / Shared'],
    'label_useable_size' => ['Useable Size', 'Common / Shared'],
    'label_italian_size' => ['Italian Size', 'Common / Shared'],
    'label_in_stock' => ['In Stock', 'Common / Shared'],
    'label_out_of_stock' => ['Out of Stock', 'Common / Shared'],
    'filter_color' => ['Color', 'Common / Shared'],
    'filter_stone_type' => ['Stone Type', 'Common / Shared'],
    'unit_sqft' => ['sqft', 'Common / Shared'],
    'empty_no_clients' => ['No clients yet', 'Common / Shared'],
    'nav_catalog' => ['Catalog', 'Common / Shared'],
    'nav_shortlist' => ['Shortlist', 'Common / Shared'],
    'nav_clients' => ['Clients', 'Common / Shared'],
    'nav_updates' => ['Updates', 'Common / Shared'],
    'nav_support' => ['Support', 'Common / Shared'],
    'nav_profile' => ['Profile', 'Common / Shared'],
    'form_area_room_placeholder' => ['e.g. Master Bedroom', 'Common / Shared'],
    'form_new_password' => ['New Password', 'Common / Shared'],
    'form_current_password' => ['Current Password', 'Common / Shared'],
    'form_password' => ['Password', 'Common / Shared'],
    'form_confirm_password' => ['Confirm Password', 'Common / Shared'],
    'btn_back' => ['Back', 'Common / Shared'],
    'label_lot' => ['Lot', 'Common / Shared'],
    'btn_add_one' => ['Add one', 'Common / Shared'],
    'label_or' => ['OR', 'Common / Shared'],
    'btn_sign_in' => ['Sign In', 'Common / Shared'],
    'btn_sign_out' => ['Sign Out', 'Common / Shared'],
    'label_step' => ['Step', 'Register Page'],
    'label_of_2_steps' => ['of 2', 'Register Page'],
    'title_create_account' => ['Create Account', 'Register Page'],
    'subtitle_signup_trade_access' => ['Sign up for exclusive trade access.', 'Register Page'],
    'form_full_name_placeholder' => ['Rahul Sharma', 'Register Page'],
    'form_reenter_password_placeholder' => ['Re-enter password', 'Register Page'],
    'form_mobile_number' => ['Mobile Number', 'Register Page'],
    'form_mobile_number_placeholder' => ['Mobile number', 'Register Page'],
    'btn_complete_registration' => ['Complete Registration', 'Register Page'],
    'title_your_profession' => ['Your Profession', 'Register Page'],
    'subtitle_personalise_experience' => ['Help us personalise your experience.', 'Register Page'],
    'form_firm_placeholder' => ['RS Architecture Studio', 'Register Page'],
    'form_professional_role' => ['Professional Role', 'Register Page'],
    'form_years_of_experience' => ['Years of Experience', 'Register Page'],
    'text_already_registered' => ['Already registered?', 'Register Page'],
    'role_architect' => ['Architect', 'Register Page'],
    'role_interior_designer' => ['Interior Designer', 'Register Page'],
    'role_contractor' => ['Contractor', 'Register Page'],
    'role_developer' => ['Developer / Builder', 'Register Page'],
    'role_retailer' => ['Stone Retailer', 'Register Page'],
    'role_other' => ['Other Professional', 'Register Page'],
    'exp_0_2' => ['0–2 years', 'Register Page'],
    'exp_3_5' => ['3–5 years', 'Register Page'],
    'exp_6_10' => ['6–10 years', 'Register Page'],
    'exp_10_plus' => ['10+ years', 'Register Page'],
    'title_welcome_back' => ['Welcome back', 'Login Page'],
    'subtitle_signin_inventory' => ['Sign in to access the inventory.', 'Login Page'],
    'link_forgot_password' => ['Forgot password?', 'Login Page'],
    'text_new_user' => ['New user?', 'Login Page'],
    'link_create_account' => ['Create an account', 'Login Page'],
    'title_secure_account_recovery' => ['Secure Account Recovery', 'Forgot / Reset Password Pages'],
    'title_email_sent' => ['Email Sent!', 'Forgot / Reset Password Pages'],
    'msg_reset_link_sent' => ['If that email is registered, a reset link has been sent. Check your inbox. Link expires in 1 hour.', 'Forgot / Reset Password Pages'],
    'btn_back_to_login' => ['Back to Login', 'Forgot / Reset Password Pages'],
    'title_forgot_password' => ['Forgot Password?', 'Forgot / Reset Password Pages'],
    'subtitle_forgot_password' => ['Enter your email and we\\\'ll send a reset link.', 'Forgot / Reset Password Pages'],
    'btn_send_reset_link' => ['Send Reset Link', 'Forgot / Reset Password Pages'],
    'title_secure_password_reset' => ['Secure Password Reset', 'Forgot / Reset Password Pages'],
    'title_password_updated' => ['Password Updated!', 'Forgot / Reset Password Pages'],
    'msg_password_changed' => ['Your password has been changed. You can now sign in.', 'Forgot / Reset Password Pages'],
    'btn_sign_in_now' => ['Sign In Now', 'Forgot / Reset Password Pages'],
    'title_link_expired' => ['Link Expired', 'Forgot / Reset Password Pages'],
    'msg_link_expired' => ['This reset link is invalid or expired. Reset links are valid for 1 hour.', 'Forgot / Reset Password Pages'],
    'btn_request_new_link' => ['Request New Link', 'Forgot / Reset Password Pages'],
    'title_set_new_password' => ['Set New Password', 'Forgot / Reset Password Pages'],
    'subtitle_choose_strong_password' => ['Choose a strong password with at least 8 characters.', 'Forgot / Reset Password Pages'],
    'form_reenter_new_password_placeholder' => ['Re-enter your new password', 'Forgot / Reset Password Pages'],
    'btn_update_password' => ['Update Password', 'Forgot / Reset Password Pages'],
    'title_product_activation' => ['Product Activation', 'Activation Page'],
    'msg_license_not_activated' => ['This project requires a valid activation key before it can be used.', 'Activation Page'],
    'msg_license_invalid' => ['Invalid activation key. Please contact the administrator.', 'Activation Page'],
    'msg_license_revoked' => ['This license has been revoked. Please contact the administrator.', 'Activation Page'],
    'msg_license_domain_mismatch' => ['This license is bound to a different domain. Please contact the administrator.', 'Activation Page'],
    'msg_license_expired_dated' => ['Your license expired on %s. Please contact the administrator to renew your license.', 'Activation Page'],
    'msg_license_expired_generic' => ['Your license has expired. Please contact the administrator to renew your license.', 'Activation Page'],
    'msg_license_lifetime' => ['Lifetime License Activated.', 'Activation Page'],
    'msg_license_active' => ['License is active.', 'Activation Page'],
    'form_activation_key' => ['Activation Key', 'Activation Page'],
    'btn_activate' => ['Activate', 'Activation Page'],
    'label_status_active' => ['Active', 'Activation Page'],
    'label_status_expired' => ['Expired', 'Activation Page'],
    'label_status_revoked' => ['Revoked', 'Activation Page'],
    'label_status_domain_mismatch' => ['Domain mismatch', 'Activation Page'],
    'label_status_lifetime' => ['Lifetime', 'Activation Page'],
    'label_status_prefix' => ['Status:', 'Activation Page'],
    'msg_project_not_activated' => ['Project is not activated.', 'Activation Page'],
    'title_premium_stone_catalog' => ['Premium Stone Catalog Platform', 'Waiting Approval Page'],
    'title_access_pending_verification' => ['Access Pending Verification', 'Waiting Approval Page'],
    'msg_access_request_received' => ['Your request to access the %s has been received.', 'Waiting Approval Page'],
    'label_bafna_marble_catalog_platform' => ['Bafna Marble Catalog Platform', 'Waiting Approval Page'],
    'msg_access_verified_by' => ['You will be able to access the catalog once it is verified by the %s.', 'Waiting Approval Page'],
    'label_bafna_marble_team' => ['Bafna Marble Team', 'Waiting Approval Page'],
    'msg_access_email_notification' => ['You will receive an %s when your account is approved.', 'Waiting Approval Page'],
    'label_email_notification' => ['email notification', 'Waiting Approval Page'],
    'step_registered' => ['Registered', 'Waiting Approval Page'],
    'step_under_review' => ['Under Review', 'Waiting Approval Page'],
    'step_access_granted' => ['Access Granted', 'Waiting Approval Page'],
    'title_need_help' => ['Need Help?', 'Waiting Approval Page'],
    'label_business_hours' => ['Mon–Sat 9AM–6PM', 'Waiting Approval Page'],
    'filter_filters' => ['Filters', 'Catalog Page'],
    'filter_all' => ['All', 'Catalog Page'],
    'filter_clear_all' => ['Clear All', 'Catalog Page'],
    'filter_apply_filters' => ['Apply Filters', 'Catalog Page'],
    'filter_min' => ['Min', 'Catalog Page'],
    'filter_max' => ['Max', 'Catalog Page'],
    'filter_available_sqft' => ['Available Sqft', 'Catalog Page'],
    'filter_useable_length' => ['Useable Length (L)', 'Catalog Page'],
    'filter_useable_height' => ['Useable Height (H)', 'Catalog Page'],
    'filter_sort_latest' => ['Latest', 'Catalog Page'],
    'filter_sort_qty_desc' => ['Qty: High→Low', 'Catalog Page'],
    'filter_sort_qty_asc' => ['Qty: Low→High', 'Catalog Page'],
    'filter_sort_name_az' => ['Name A→Z', 'Catalog Page'],
    'filter_view_grid' => ['Grid', 'Catalog Page'],
    'filter_view_list' => ['List', 'Catalog Page'],
    'filter_view_table' => ['Table', 'Catalog Page'],
    'label_products' => ['products', 'Catalog Page'],
    'search_catalog_placeholder' => ['Search by name or lot number…', 'Catalog Page'],
    'spec_subcategory' => ['Subcategory', 'Product Page'],
    'spec_quarry_no' => ['Quarry No.', 'Product Page'],
    'spec_total_pieces' => ['Total Pieces', 'Product Page'],
    'unit_slabs' => ['slabs', 'Product Page'],
    'spec_origin' => ['Origin', 'Product Page'],
    'spec_finish' => ['Finish', 'Product Page'],
    'title_download_image' => ['Download image', 'Product Page'],
    'title_zoom_in' => ['Zoom in', 'Product Page'],
    'title_zoom_out' => ['Zoom out', 'Product Page'],
    'badge_featured' => ['Featured', 'Product Page'],
    'qty_tile_total' => ['Total', 'Product Page'],
    'qty_tile_on_hold' => ['On Hold', 'Product Page'],
    'qty_tile_available' => ['Available', 'Product Page'],
    'section_full_specifications' => ['Full Specifications', 'Product Page'],
    'section_gallery' => ['Gallery', 'Product Page'],
    'section_video' => ['Video', 'Product Page'],
    'btn_share_video_whatsapp' => ['Share Video on WhatsApp', 'Product Page'],
    'section_documents' => ['Documents', 'Product Page'],
    'doc_product_pdf' => ['Product PDF', 'Product Page'],
    'doc_product_detail_pdf' => ['Product Detail PDF', 'Product Page'],
    'doc_measurement_sheet' => ['Measurement Sheet', 'Product Page'],
    'doc_dna_report' => ['DNA Report', 'Product Page'],
    'btn_saved' => ['Saved', 'Product Page'],
    'btn_save_to_shortlist' => ['Save to Shortlist', 'Product Page'],
    'btn_whatsapp_share' => ['WhatsApp Share', 'Product Page'],
    'btn_add_to_client_selection' => ['Add to Client Selection', 'Product Page'],
    'btn_slab_calculator' => ['Slab Calculator', 'Product Page'],
    'btn_visualize_in_room' => ['Visualize in a Room', 'Product Page'],
    'btn_3d_room_preview' => ['3D Room Preview', 'Product Page'],
    'link_add_a_client' => ['add a client', 'Product Page'],
    'msg_to_use_selections' => ['to use selections.', 'Product Page'],
    'title_share_product' => ['Share Product', 'Product Page'],
    'btn_share_whatsapp' => ['Share via WhatsApp', 'Product Page'],
    'btn_share_email' => ['Share via Email', 'Product Page'],
    'btn_copy_link' => ['Copy Link', 'Product Page'],
    'unit_sqft_avail' => ['sqft avail.', 'Product Page'],
    'label_size' => ['Size:', 'Product Page'],
    'msg_add_client_first' => ['Add a client first to save product selections for them.', 'Product Page'],
    'title_share_product_pdf' => ['Share Product PDF', 'Product Page'],
    'form_recipient_mobile' => ['Recipient Mobile Number', 'Product Page'],
    'error_invalid_mobile' => ['Please enter a valid mobile number.', 'Product Page'],
    'btn_generate_share' => ['Generate &amp; Share', 'Product Page'],
    'msg_generating_pdf' => ['Generating PDF…', 'Product Page'],
    'title_whatsapp_opened' => ['WhatsApp Opened!', 'Product Page'],
    'msg_send_to_complete' => ['Send the message to complete sharing.', 'Product Page'],
    'btn_download_pdf' => ['Download PDF', 'Product Page'],
    'title_generation_failed' => ['Generation Failed', 'Product Page'],
    'btn_try_again' => ['Try Again', 'Product Page'],
    'msg_wa_pdf_title' => ['Product Details PDF', 'Product Page'],
    'msg_wa_pdf_instructions' => ['Tap the link above to view or download the full product PDF.', 'Product Page'],
    'label_regards' => ['Regards,', 'Product Page'],
    'form_client' => ['Client', 'Product Page'],
    'form_client_placeholder' => ['Type to search client…', 'Product Page'],
    'form_area_room' => ['Area / Room', 'Product Page'],
    'form_qty_required' => ['Qty Required (sqft)', 'Product Page'],
    'form_notes_placeholder_product' => ['Special requirements, finish preferences…', 'Product Page'],
    'btn_save_to_selection' => ['Save to Selection', 'Product Page'],
    'title_qty_exceeds_availability' => ['Quantity Exceeds Availability', 'Product Page'],
    'msg_qty_exceeds_availability' => ['Selected quantity is lower than available quantity. Do you still want to add this product?', 'Product Page'],
    'btn_add_client' => ['Add Client', 'Clients Page'],
    'eyebrow_my_contacts' => ['My Contacts', 'Clients Page'],
    'search_clients_placeholder' => ['Search by name, mobile or mason name…', 'Clients Page'],
    'msg_no_clients_match_search' => ['No clients match your search.', 'Clients Page'],
    'msg_add_first_client_prompt' => ['Add your first client to start managing product selections.', 'Clients Page'],
    'btn_add_first_client' => ['Add First Client', 'Clients Page'],
    'label_mason' => ['Mason', 'Clients Page'],
    'unit_items' => ['items', 'Clients Page'],
    'btn_selections' => ['Selections', 'Clients Page'],
    'title_delete_client' => ['Delete Client?', 'Clients Page'],
    'msg_delete_client_confirm' => ['This will also delete all product selections for this client.', 'Clients Page'],
    'msg_delete_confirm_prefix' => ['Delete', 'Clients Page'],
    'msg_delete_confirm_suffix' => ['This will also remove all their product selections.', 'Clients Page'],
    'msg_showing_range' => ['Showing %1$d–%2$d of %3$d', 'Clients Page'],
    'section_client_details' => ['Client Details', 'Client Form Page'],
    'section_mason_contractor' => ['Mason / Contractor (Optional)', 'Client Form Page'],
    'form_client_name' => ['Client Name', 'Client Form Page'],
    'form_client_mobile' => ['Client Mobile', 'Client Form Page'],
    'form_mason_name' => ['Mason Name', 'Client Form Page'],
    'form_mason_mobile' => ['Mason Mobile', 'Client Form Page'],
    'form_site_address' => ['Site Address', 'Client Form Page'],
    'form_client_name_placeholder' => ['e.g. Ramesh Patel', 'Client Form Page'],
    'form_mason_name_placeholder' => ['e.g. Suresh Kumar', 'Client Form Page'],
    'form_site_address_placeholder' => ['Plot 12, Sector 5, New Mumbai — 400001', 'Client Form Page'],
    'hint_mobile_10_digit' => ['10-digit Indian mobile number', 'Client Form Page'],
    'hint_email_optional' => ['Optional — used to send catalogs directly.', 'Client Form Page'],
    'label_characters' => ['characters', 'Client Form Page'],
    'heading_add_client' => ['Add Client', 'Client Form Page'],
    'heading_edit_client' => ['Edit Client', 'Client Form Page'],
    'btn_update_client' => ['Update Client', 'Client Form Page'],
    'btn_save_client' => ['Save Client', 'Client Form Page'],
    'eyebrow_client_selections' => ['Client Selections', 'Client Selections Page'],
    'btn_history' => ['History', 'Client Selections Page'],
    'search_selection_placeholder' => ['Search product name or lot number…', 'Client Selections Page'],
    'btn_add_products' => ['Add Products', 'Client Selections Page'],
    'btn_generate_pdf' => ['Generate PDF', 'Client Selections Page'],
    'title_edit_selection' => ['Edit Selection', 'Client Selections Page'],
    'form_selection_area_room' => ['Selection Area / Room', 'Client Selections Page'],
    'form_quantity_required' => ['Quantity Required (sqft)', 'Client Selections Page'],
    'title_email_selection_pdf' => ['Email Selection PDF', 'Client Selections Page'],
    'form_to' => ['To', 'Client Selections Page'],
    'form_cc' => ['CC', 'Client Selections Page'],
    'form_bcc' => ['BCC', 'Client Selections Page'],
    'form_subject' => ['Subject', 'Client Selections Page'],
    'form_message' => ['Message', 'Client Selections Page'],
    'form_cc_bcc_placeholder' => ['optional, comma-separated', 'Client Selections Page'],
    'empty_no_selections' => ['No products selected', 'Client Selections Page'],
    'msg_no_products_match_search' => ['No products match your search.', 'Client Selections Page'],
    'msg_browse_catalog_prompt' => ['Browse the catalog and click "Add to Selection" on any product.', 'Client Selections Page'],
    'th_product' => ['Product', 'Client Selections Page'],
    'th_avail_qty' => ['Avail. Qty', 'Client Selections Page'],
    'th_req_qty' => ['Req. Qty', 'Client Selections Page'],
    'th_actions' => ['Actions', 'Client Selections Page'],
    'msg_qty_exceeds_tooltip' => ['You have selected lower quantity product than its available quantity.', 'Client Selections Page'],
    'form_notes_placeholder' => ['Any special requirements…', 'Client Selections Page'],
    'eyebrow_account_security' => ['Account Security', 'Devices Page'],
    'heading_trusted_devices' => ['Trusted Devices', 'Devices Page'],
    'form_device_name' => ['Device Name', 'Devices Page'],
    'form_device_name_placeholder' => ['Device name (e.g. My Laptop)', 'Devices Page'],
    'btn_trust_this_device' => ['Trust This Device', 'Devices Page'],
    'btn_forced_logout' => ['Forced Logout', 'Devices Page'],
    'btn_yes_forced_logout' => ['Yes, Forced Logout', 'Devices Page'],
    'link_manage_devices' => ['Manage all trusted devices →', 'Profile Page'],
    'btn_save_changes' => ['Save Changes', 'Profile Page'],
    'btn_browse_catalog' => ['Browse Catalog', 'Shortlist Page'],
    'eyebrow_my_collection' => ['My Collection', 'Shortlist Page'],
    'empty_no_saved' => ['Nothing saved yet', 'Shortlist Page'],
    'heading_notifications' => ['Notifications', 'Notifications Page'],
    'eyebrow_help' => ['Help', 'Support Page'],
    'eyebrow_room_visualizer' => ['Room Visualizer', 'Room Visualizer Page'],
    'btn_back_to_product' => ['Back to Product', 'Room Visualizer Page'],
    'btn_go_to_catalog' => ['Go to Catalog', 'Not Found (404) Page'],
    'btn_go_to_login' => ['Go to Login', 'Not Found (404) Page'],
    'empty_no_products' => ['No products found', 'Empty States'],
    'empty_no_clients_found' => ['No clients found.', 'Empty States'],
    'label_available_qty' => ['Available Qty', 'Product Labels (cards)'],
];

// FAQ — mirrors pages/support.php $faqs array, indexed 0..n
$faqs = [
    ['How do I save a product?',        'Tap the heart icon on any product card in the catalog to add it to your Shortlist for quick access later.'],
    ['How do I contact about a product?','Use the Share button on any product page to send the details via WhatsApp or email to coordinate directly.'],
    ['Can I save multiple products?',   'Yes — there is no limit. Your entire Shortlist is available under the Shortlist tab in the navigation.'],
    ['How long do notifications last?', 'Notifications are automatically removed after 20 days to keep your feed clean and relevant.'],
    ['How do I reset my password?',     'On the login page, tap "Forgot password?" and follow the instructions sent to your registered email address.'],
    ['How do I update my profile?',     'Go to your Profile page — the edit form is always visible so you can update your details at any time.'],
];

$existing = []; // [entityType][lang] => [entity_id => [field_key => value]]
foreach (['product','category','ui_string','faq'] as $et) {
    foreach ($langs as $l) $existing[$et][$l] = getTranslationsFor($et, $l);
}
?>
<style>
.tr-tabs{display:flex;gap:0;border-bottom:2px solid var(--admin-table-border,var(--border));margin-bottom:20px;overflow-x:auto;}
.tr-tab{padding:10px 20px;font-size:13px;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;white-space:nowrap;}
.tr-tab.active{border-bottom-color:var(--admin-accent,var(--accent));color:var(--admin-accent,var(--accent));}
.tr-panel{display:none;}
.tr-panel.active{display:block;}
.tr-lang-tabs{display:flex;gap:8px;margin-bottom:16px;}
.tr-lang-tab{padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;background:var(--admin-surface,var(--surface));border:1.5px solid var(--admin-table-border,var(--border));color:var(--admin-text3,var(--text3));cursor:pointer;font-family:inherit;}
.tr-lang-tab.active{background:var(--admin-accent,var(--accent));border-color:var(--admin-accent,var(--accent));color:#fff;}
.tr-lang-panel{display:none;}
.tr-lang-panel.active{display:block;}
.tr-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px 0;border-bottom:1px solid var(--admin-table-border,var(--border));align-items:start;}
.tr-row-en{font-size:13px;color:var(--admin-text2,var(--text2));padding-top:9px;}
.tr-row-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:4px;display:block;}
.tr-group-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text2,var(--text2));margin:18px 0 8px;padding-bottom:6px;border-bottom:1px solid var(--admin-table-border,var(--border));}
.tr-group-title:first-child{margin-top:0;}
.tr-search{margin-bottom:14px;max-width:340px;}
.tr-item-hidden{display:none !important;}
.tr-save-bar{position:sticky;bottom:0;background:var(--admin-bg,var(--bg));padding:14px 0;border-top:1px solid var(--admin-table-border,var(--border));margin-top:16px;display:flex;align-items:center;gap:12px;}
</style>

<div class="tr-tabs">
  <button class="tr-tab active" onclick="trSwitchTab('product')">Products</button>
  <button class="tr-tab" onclick="trSwitchTab('category')">Categories</button>
  <button class="tr-tab" onclick="trSwitchTab('ui_string')">UI Strings</button>
  <button class="tr-tab" onclick="trSwitchTab('faq')">FAQ</button>
</div>

<?php
// ── Renders one entity_type's full tab: lang sub-tabs + field rows ─────────
function trRenderPanel(string $entityType, array $langs, array $existing): void {
?>
<div class="tr-panel <?= $entityType==='product'?'active':'' ?>" id="tr-panel-<?= h($entityType) ?>">
  <div class="tr-lang-tabs">
    <?php foreach ($langs as $i => $l): ?>
    <button type="button" class="tr-lang-tab <?= $i===0?'active':'' ?>"
            onclick="trSwitchLang('<?= h($entityType) ?>','<?= h($l) ?>')"
            data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>"><?= h(LANG_LABELS[$l]) ?></button>
    <?php endforeach; ?>
  </div>

  <?php if ($entityType === 'product'): global $products; ?>
  <input type="text" class="admin-input tr-search" placeholder="Search product name…" oninput="trFilterRows(this,'tr-panel-product')"/>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php foreach ($products as $p):
        $tid = (string)$p['id'];
        foreach (['name'=>'Name','description'=>'Description','category'=>'Category','subcategory'=>'Subcategory','color_subcategory'=>'Color','finish'=>'Finish','origin'=>'Origin'] as $fk => $flabel):
          $en = trim((string)($p[$fk] ?? ''));
          if ($en === '') continue;
          $val = $existing[$entityType][$l][$tid][$fk] ?? '';
      ?>
      <div class="tr-row" data-search="<?= h(mb_strtolower($p['name'])) ?>">
        <div class="tr-row-en"><span class="tr-row-label"><?= h($p['name']) ?> — <?= h($flabel) ?></span><?= h($en) ?></div>
        <div>
          <textarea class="admin-input tr-field" rows="<?= $fk==='description'?3:1 ?>"
                    data-entity-id="<?= h($tid) ?>" data-field-key="<?= h($fk) ?>"
                    placeholder="Leave blank to use English"><?= h($val) ?></textarea>
        </div>
      </div>
      <?php endforeach; endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>

  <?php elseif ($entityType === 'category'): global $categoryEntities; ?>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php $lastGroup = null; foreach ($categoryEntities as $c):
        if ($c['group'] !== $lastGroup) { echo '<p class="tr-group-title">'.h($c['group']).'</p>'; $lastGroup = $c['group']; }
        $val = $existing[$entityType][$l][$c['id']]['label'] ?? '';
      ?>
      <div class="tr-row">
        <div class="tr-row-en"><?= h($c['label']) ?></div>
        <div>
          <input type="text" class="admin-input tr-field"
                 data-entity-id="<?= h($c['id']) ?>" data-field-key="label"
                 value="<?= h($val) ?>" placeholder="Leave blank to use English"/>
        </div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>

  <?php elseif ($entityType === 'ui_string'): global $uiStrings; ?>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php $lastSection = null; foreach ($uiStrings as $key => [$en, $section]):
        if ($section !== $lastSection) { echo '<p class="tr-group-title">'.h($section).'</p>'; $lastSection = $section; }
        $val = $existing[$entityType][$l][$key]['value'] ?? '';
      ?>
      <div class="tr-row">
        <div class="tr-row-en"><span class="tr-row-label"><?= h($key) ?></span><?= h($en) ?></div>
        <div>
          <input type="text" class="admin-input tr-field"
                 data-entity-id="<?= h($key) ?>" data-field-key="value"
                 value="<?= h($val) ?>" placeholder="Leave blank to use English"/>
        </div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>

  <?php elseif ($entityType === 'faq'): global $faqs; ?>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php foreach ($faqs as $idx => [$q, $a]):
        $qVal = $existing[$entityType][$l][(string)$idx]['question'] ?? '';
        $aVal = $existing[$entityType][$l][(string)$idx]['answer']   ?? '';
      ?>
      <p class="tr-group-title">FAQ #<?= $idx + 1 ?></p>
      <div class="tr-row">
        <div class="tr-row-en"><span class="tr-row-label">Question</span><?= h($q) ?></div>
        <div><input type="text" class="admin-input tr-field" data-entity-id="<?= $idx ?>" data-field-key="question" value="<?= h($qVal) ?>" placeholder="Leave blank to use English"/></div>
      </div>
      <div class="tr-row">
        <div class="tr-row-en"><span class="tr-row-label">Answer</span><?= h($a) ?></div>
        <div><textarea class="admin-input tr-field" rows="2" data-entity-id="<?= $idx ?>" data-field-key="answer" placeholder="Leave blank to use English"><?= h($aVal) ?></textarea></div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="tr-save-bar">
    <button type="button" class="btn-admin-primary" onclick="trSaveCurrent('<?= h($entityType) ?>')">
      <?= icon('check', 15) ?> Save Changes
    </button>
    <span class="tr-status" data-entity="<?= h($entityType) ?>" style="font-size:12px;"></span>
  </div>
</div>
<?php
}
foreach (['product','category','ui_string','faq'] as $et) {
    trRenderPanel($et, $langs, $existing);
}
?>

<script>
var trActiveLang = { product: '<?= h($langs[array_key_first($langs)]) ?>', category: '<?= h($langs[array_key_first($langs)]) ?>', ui_string: '<?= h($langs[array_key_first($langs)]) ?>', faq: '<?= h($langs[array_key_first($langs)]) ?>' };

function trSwitchTab(entity) {
  document.querySelectorAll('.tr-tab').forEach(function(t){ t.classList.toggle('active', t.getAttribute('onclick').includes("'"+entity+"'")); });
  document.querySelectorAll('.tr-panel').forEach(function(p){ p.classList.toggle('active', p.id === 'tr-panel-'+entity); });
}
function trSwitchLang(entity, lang) {
  trActiveLang[entity] = lang;
  document.querySelectorAll('.tr-lang-tab[data-entity="'+entity+'"]').forEach(function(t){
    t.classList.toggle('active', t.dataset.lang === lang);
  });
  document.querySelectorAll('#tr-panel-'+entity+' .tr-lang-panel').forEach(function(p){
    p.classList.toggle('active', p.id === 'tr-'+entity+'-'+lang);
  });
}
function trFilterRows(input, panelId) {
  var q = input.value.trim().toLowerCase();
  document.querySelectorAll('#'+panelId+' .tr-row[data-search]').forEach(function(row) {
    row.classList.toggle('tr-item-hidden', q !== '' && row.dataset.search.indexOf(q) === -1);
  });
}
function trSaveCurrent(entity) {
  var lang = trActiveLang[entity];
  var form = document.querySelector('.tr-form[data-entity="'+entity+'"][data-lang="'+lang+'"]');
  var statusEl = document.querySelector('.tr-status[data-entity="'+entity+'"]');
  var csrf = form.querySelector('input[name="csrf_token"]').value;

  var rows = [];
  form.querySelectorAll('.tr-field').forEach(function(f) {
    rows.push({ entity_id: f.dataset.entityId, field_key: f.dataset.fieldKey, value: f.value });
  });

  var body = new URLSearchParams();
  body.set('action', 'save_translations_batch');
  body.set('entity_type', entity);
  body.set('lang', lang);
  body.set('rows', JSON.stringify(rows));
  body.set('csrf_token', csrf);

  statusEl.textContent = 'Saving…';
  statusEl.style.color = 'var(--admin-text3,var(--text3))';

  fetch('index.php?page=translations', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.success) {
        statusEl.textContent = 'Saved ✓ (' + rows.length + ' fields)';
        statusEl.style.color = 'var(--success,#3D8B6E)';
      } else {
        statusEl.textContent = 'Error: ' + (d.error || 'save failed');
        statusEl.style.color = 'var(--danger,#E84040)';
      }
      setTimeout(function(){ statusEl.textContent = ''; }, 3500);
    })
    .catch(function(e) {
      statusEl.textContent = 'Request failed: ' + e.message;
      statusEl.style.color = 'var(--danger,#E84040)';
    });
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>