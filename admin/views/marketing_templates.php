<?php

require_once BASE_PATH . '/includes/marketing_templates.php';

// ── AJAX: template rows per channel tab 
if (!empty($_GET['ajax_templates'])) {
    requireAdminPermissionJson('marketing.templates.manage');
      $channel = in_array($_GET['channel'] ?? '', ['whatsapp','email'], true) ? $_GET['channel'] : 'whatsapp';
    $result = getMarketingTemplates(['channel' => $channel, 'search' => trim($_GET['q'] ?? ''), 'limit' => 100, 'offset' => 0]);
    header('Content-Type: application/json');
    echo json_encode(['rows' => $result['rows'], 'total' => $result['total']]);
    exit;
}

// ── AJAX: single template fetch (for edit modal) 
if (!empty($_GET['ajax_template_get'])) {
    requireAdminPermissionJson('marketing.templates.manage');
  
    $tpl = getMarketingTemplate((int)($_GET['id'] ?? 0));
    header('Content-Type: application/json');
    echo json_encode(['template' => $tpl]);
    exit;
}
// ── AJAX: image upload (TinyMCE) ───────────────────────────────────────────
if (!empty($_GET['ajax_upload_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.templates.manage');
    csrfVerify(true);

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded, or the upload failed.']);
        exit;
    }

    $maxBytes = 5 * 1024 * 1024; // 5MB
    if ($_FILES['file']['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['error' => 'Image is too large — max 5MB.']);
        exit;
    }

    // Validate the file is a REAL image by reading its actual header bytes,
    // not just trusting the uploaded filename's extension (extension
    // spoofing — a .jpg that's actually a PHP script is a classic vector).
    $tmpPath = $_FILES['file']['tmp_name'];
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        http_response_code(400);
        echo json_encode(['error' => 'File is not a valid image.']);
        exit;
    }
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = $imageInfo['mime'] ?? '';
    if (!isset($allowedMimes[$mime])) {
        http_response_code(400);
        echo json_encode(['error' => 'Unsupported image type. Use JPG, PNG, GIF, or WebP.']);
        exit;
    }
    $ext = $allowedMimes[$mime];

    // New, dedicated public directory for this feature specifically —
    // deliberately NOT reusing the still-unconfirmed product-image path,
    // so no guessing about an existing directory's permissions is needed.
    $uploadDir = BASE_PATH . '/uploads/marketing_templates/';
    $isNewDir = !is_dir($uploadDir);
    if ($isNewDir && !@mkdir($uploadDir, 0755, true)) {
        error_log('marketing image upload: failed to create ' . $uploadDir);
        http_response_code(500);
        echo json_encode(['error' => 'Server could not create the upload directory.']);
        exit;
    }
    if ($isNewDir) {
        // Defense in depth: even though getimagesize() above already blocks
        // non-image uploads, this disables script execution in the
        // directory in case of a future validation bypass. Apache/
        // LiteSpeed-specific (.htaccess) — if this server runs nginx
        // instead, this file is silently ignored and offers no protection;
        // flagging that rather than assuming it works.
        @file_put_contents($uploadDir . '.htaccess',
            "php_flag engine off\n<FilesMatch \"\\.(php|phtml|php[0-9]|phar)\$\">\nRequire all denied\n</FilesMatch>\n");
    }

    // Random filename — never trust or reuse the original name (path
    // traversal, accidental overwrite, and info-disclosure prevention).
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        error_log('marketing image upload: move_uploaded_file failed for ' . $destPath);
        http_response_code(500);
        echo json_encode(['error' => 'Could not save the uploaded file.']);
        exit;
    }
    @chmod($destPath, 0644);

    // TinyMCE's images_upload_handler expects { "location": "<absolute URL>" }.
    // Must be absolute, not relative — email clients fetching this image
    // later have no concept of "relative to the app," only a bare URL.
    echo json_encode(['location' => rtrim(BASE_URL, '/') . '/uploads/marketing_templates/' . $filename]);
    exit;
}

// ── AJAX: live preview render ─────────────────────────────────────────────
// ── AJAX: live preview render 
if (!empty($_GET['ajax_preview']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminPermissionJson('marketing.templates.manage');
    csrfVerify(true);
    $channel = in_array($_POST['channel'] ?? '', ['whatsapp','email'], true) ? $_POST['channel'] : 'email';
    $context = getMarketingSampleContext($channel);
    header('Content-Type: application/json');
    echo json_encode([
        'subject' => renderMarketingTemplate(trim($_POST['subject'] ?? ''), $context, $channel),
        'body'    => renderMarketingTemplate(trim($_POST['body'] ?? ''), $context, $channel),
        'footer'  => renderMarketingTemplate(trim($_POST['footer'] ?? ''), $context, $channel),
    ]);
    exit;
}

$adminTitle = 'Marketing Templates';
requireAdminPermission('marketing.templates.manage');
include __DIR__ . '/../_layout_top.php';

$variableRegistry = marketingVariableRegistry();
?>
<style>
.mkt-tabs{display:flex;gap:0;border-bottom:2px solid var(--admin-table-border,var(--border));margin-bottom:18px;}
.mkt-tab{padding:9px 18px;font-size:13px;font-weight:600;color:var(--admin-text3,var(--text3));border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;}
.mkt-tab.active{color:var(--admin-accent,var(--accent));border-bottom-color:var(--admin-accent,var(--accent));}
.mkt-panel{display:none;}
.mkt-panel.active{display:block;}
.mkt-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
.mkt-modal.open{display:flex;}
.mkt-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:900px;max-height:92vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.2);}
.mkt-editor-layout{display:grid;grid-template-columns:1fr 280px;gap:18px;}
@media(max-width:760px){.mkt-editor-layout{grid-template-columns:1fr;}}
.mkt-var-list{display:flex;flex-direction:column;gap:4px;max-height:220px;overflow-y:auto;}
.mkt-var-btn{text-align:left;padding:6px 10px;border-radius:6px;border:1px solid var(--admin-table-border,var(--border));background:var(--admin-surface,var(--surface));font-size:11.5px;cursor:pointer;font-family:monospace;}
.mkt-var-btn:hover{border-color:var(--admin-accent,var(--accent));background:var(--admin-accent-light,var(--accent-light));}
.mkt-preview-box{background:#f4f1ec;border-radius:10px;padding:14px;margin-top:12px;}
.mkt-wa-bubble{background:#dcf8c6;border-radius:8px;padding:10px 12px;font-size:13px;line-height:1.5;max-width:280px;white-space:pre-wrap;word-break:break-word;}
.mkt-btn-row{display:grid;grid-template-columns:110px 1fr 1fr auto;gap:6px;margin-bottom:6px;align-items:center;}
.mkt-tinymce-wrap .tox-tinymce{border-radius:8px;border-color:var(--admin-table-border,var(--border)) !important;}
  .tox-tinymce-aux {
    z-index: 999999 !important;
}

.tox-menu {
    z-index: 999999 !important;
}

.tox-dialog-wrap,
.tox-dialog {
    z-index: 1000000 !important;
}

/* Prevent parent containers from clipping TinyMCE menus */
.tox {
    overflow: visible !important;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js" referrerpolicy="origin"></script>

<div class="mkt-tabs">
  <button class="mkt-tab active" onclick="mktSwitchTab('whatsapp')"><?= icon('whatsapp',13) ?> WhatsApp Templates</button>
  <button class="mkt-tab" onclick="mktSwitchTab('email')"><?= icon('mail',13) ?> Email Templates</button>
</div>

<!-- ══ WhatsApp panel ══ -->
<div class="mkt-panel active" id="mktPanel-whatsapp">
  <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    <button type="button" class="btn-admin-primary" onclick="mktOpenCreate('whatsapp')"><?= icon('plus',14) ?> New WhatsApp Template</button>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="marketing_sync_whatsapp_templates"/>
      <?= csrfField() ?>
      <button type="submit" class="btn-admin-secondary"><?= icon('refresh',14) ?> Sync Approved Templates from Meta</button>
    </form>
    <p style="font-size:11px;color:var(--admin-text3,var(--text3));align-self:center;">
      <?= icon('info',11) ?> Templates are authored &amp; submitted in Meta Business Manager. This only pulls their current approval status.
    </p>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Category</th><th>Language</th><th>Status</th><th>Updated</th><th style="width:110px;">Actions</th></tr></thead>
      <tbody id="mktTbody-whatsapp"><tr><td colspan="6" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- ══ Email panel ══ -->
<div class="mkt-panel" id="mktPanel-email">
  <div style="margin-bottom:14px;">
    <button type="button" class="btn-admin-primary" onclick="mktOpenCreate('email')"><?= icon('plus',14) ?> New Email Template</button>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Name</th><th>Subject</th><th>Language</th><th>Updated</th><th style="width:110px;">Actions</th></tr></thead>
      <tbody id="mktTbody-email"><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">Loading…</td></tr></tbody>
    </table>
  </div>
</div>

<!-- ══ Editor modal ══ -->
<div id="mktEditorModal" class="mkt-modal">
  <div class="mkt-modal-card">
    <div style="display:flex;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--admin-table-border,var(--border));">
      <p id="mktEditorTitle" style="font-weight:700;font-size:16px;">New Template</p>
      <button type="button" onclick="mktCloseEditor()" style="background:none;border:none;color:var(--admin-text3,var(--text3));cursor:pointer;"><?= icon('close',18) ?></button>
    </div>
    <div style="padding:20px;">
      <form method="POST" action="index.php" id="mktEditorForm">
        <input type="hidden" name="action" id="mktFormAction" value="marketing_create_template"/>
        <input type="hidden" name="template_id" id="mktTemplateId" value=""/>
        <input type="hidden" name="channel" id="mktChannel" value="whatsapp"/>
        
        <?= csrfField() ?>

        <div class="mkt-editor-layout">
          <div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
              <div><label class="admin-label">Template Name *</label><input type="text" name="name" id="mktName" class="admin-input" required/></div>
              <div><label class="admin-label">Language Code</label><input type="text" name="language" id="mktLanguage" class="admin-input" placeholder="en_US"/></div>
            </div>

            <!-- WhatsApp-only fields -->
            <div id="mktWaFields">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                  <label class="admin-label">Category</label>
                  <select name="category" id="mktCategory" class="admin-input admin-select">
                    <option value="MARKETING">Marketing</option><option value="UTILITY">Utility</option><option value="AUTHENTICATION">Authentication</option>
                  </select>
                </div>
                <div>
                  <label class="admin-label">Approval Status</label>
                  <select name="approval_status" id="mktApprovalStatus" class="admin-input admin-select">
                    <option value="draft">Draft</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option>
                  </select>
                </div>
              </div>
              <div style="margin-bottom:14px;">
                <label class="admin-label">Header Type</label>
               <select name="header_type" id="mktHeaderType" class="admin-input admin-select" onchange="mktToggleHeaderText()" style="max-width:160px;margin-bottom:8px;">
                  <option value="none">None</option><option value="text">Text</option><option value="image">Image</option>
                  <option value="document">Document (PDF attachment)</option>
                </select>
                <p id="mktHeaderDocNote" style="display:none;font-size:11px;color:var(--admin-text3,var(--text3));margin-top:4px;">
                  The actual file is provided per-send by the campaign (a fixed catalog PDF, or each recipient's own selection PDF) — nothing to configure here.
                </p>
                <input type="text" name="header_text" id="mktHeaderText" class="admin-input" placeholder="Header text" style="display:none;"/>
              </div>
             <div style="margin-bottom:14px;">
                <label class="admin-label">Provider Template Name (Meta)</label>
                <input type="text" name="provider_template_name" id="mktProviderName" class="admin-input" placeholder="Exact name registered in Meta Business Manager" oninput="mktRenderVarMap()"/>
              </div>
              <div id="mktVarMapWrap" style="display:none;margin-bottom:14px;background:var(--admin-surface2,var(--surface2));border-radius:8px;padding:12px;">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:8px;">
                  Map Meta's Positional Variables
                </p>
                <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-bottom:10px;">
                  Meta templates use <code>{{1}}</code>, <code>{{2}}</code>… in the body text (visible above). Map each position to one of our named variables so campaigns can fill it in per-recipient.
                </p>
                <div id="mktVarMapList"></div>
              </div>
            </div>
           

            <!-- Email-only field -->
            <div id="mktEmailFields" style="display:none;margin-bottom:14px;">
              <label class="admin-label">Subject *</label>
              <input type="text" name="subject" id="mktSubject" class="admin-input"/>
            </div>

            <div style="margin-bottom:14px;">
              <label class="admin-label">Body *</label>
              <textarea name="body" id="mktBody" class="admin-input" rows="8" oninput="mktRunPreview()"></textarea>
              <div id="mktBodyRich" class="mkt-tinymce-wrap" style="display:none;">
                <textarea id="mktBodyRichArea"></textarea>
              </div>
              <p id="mktBodyRichError" style="display:none;font-size:11px;color:var(--danger,#E84040);margin-top:6px;">
                <?= icon('info',11) ?> Rich text editor failed to load (check your connection) — falling back to plain HTML editing below.
              </p>
            </div>

            <div id="mktFooterWrap" style="margin-bottom:14px;">
              <label class="admin-label">Footer (WhatsApp only, short text)</label>
              <input type="text" name="footer" id="mktFooter" class="admin-input" maxlength="60"/>
            </div>

            <!-- WhatsApp buttons -->
            <div id="mktButtonsWrap" style="margin-bottom:14px;">
              <label class="admin-label">Buttons</label>
              <div id="mktButtonsList"></div>
              <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktAddButtonRow()"><?= icon('plus',12) ?> Add Button</button>
            </div>

            <div class="mkt-preview-box">
              <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:8px;">Preview (sample data)</p>
              <div id="mktPreviewOutput"></div>
            </div>

            <button type="submit" class="btn-admin-primary" style="margin-top:16px;width:100%;justify-content:center;"><?= icon('check',15) ?> Save Template</button>
          </div>

          <div>
            <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:8px;">Insert Variable</p>
            <div class="mkt-var-list" id="mktVarList"></div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete confirm -->
<div id="mktDeleteTplModal" class="mkt-modal">
  <div class="mkt-modal-card" style="max-width:400px;">
    <div style="padding:20px;text-align:center;">
      <p style="font-weight:700;margin-bottom:8px;">Delete Template?</p>
      <p id="mktDeleteTplMsg" style="font-size:13px;color:var(--admin-text3,var(--text3));margin-bottom:18px;"></p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn-admin-secondary" style="flex:1;" onclick="document.getElementById('mktDeleteTplModal').classList.remove('open')">Cancel</button>
        <form method="POST" action="index.php" style="flex:1;">
          <input type="hidden" name="action" value="marketing_delete_template"/>
          <input type="hidden" name="template_id" id="mktDeleteTplId" value=""/>
          <?= csrfField() ?>
          <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;"><?= icon('trash',14) ?> Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
var MKT_VARIABLES = <?= json_encode($variableRegistry) ?>;
var mktActiveTab = 'whatsapp';
var mktPreviewTimer = null;

function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }

function mktSwitchTab(channel) {
  mktActiveTab = channel;
  document.querySelectorAll('.mkt-tab').forEach(function (t) { t.classList.toggle('active', t.getAttribute('onclick').includes("'"+channel+"'")); });
  document.querySelectorAll('.mkt-panel').forEach(function (p) { p.classList.toggle('active', p.id === 'mktPanel-'+channel); });
  mktLoadRows(channel);
}

function mktLoadRows(channel) {
  fetch('index.php?page=marketing_templates&ajax_templates=1&channel=' + channel)
    .then(function (r) { return r.json(); })
    .then(function (d) { mktRenderRows(channel, d.rows); });
}

function mktRenderRows(channel, rows) {
  var tbody = document.getElementById('mktTbody-' + channel);
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">No templates yet.</td></tr>';
    return;
  }
  var statusBadge = { draft: 'badge-gray', pending: 'badge-gold', approved: 'badge-green', rejected: 'badge-red' };
  tbody.innerHTML = rows.map(function (t) {
    var updated = new Date(t.updated_at * 1000).toLocaleDateString();
    if (channel === 'whatsapp') {
      return '<tr><td style="font-weight:600;">' + esc(t.name) + '</td><td>' + esc(t.category || '—') + '</td><td>' + esc(t.language) + '</td>' +
        '<td><span class="badge ' + (statusBadge[t.approval_status] || 'badge-gray') + '">' + esc(t.approval_status) + '</span></td>' +
        '<td style="font-size:11px;color:var(--admin-text3,var(--text3));">' + updated + '</td>' +
        '<td>' + mktRowActions(t.id, t.name) + '</td></tr>';
    }
    return '<tr><td style="font-weight:600;">' + esc(t.name) + '</td><td>' + esc(t.subject || '—') + '</td><td>' + esc(t.language) + '</td>' +
      '<td style="font-size:11px;color:var(--admin-text3,var(--text3));">' + updated + '</td>' +
      '<td>' + mktRowActions(t.id, t.name) + '</td></tr>';
  }).join('');
}
function mktRowActions(id, name) {
  return '<div style="display:flex;gap:5px;">' +
    '<button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktOpenEdit(' + id + ')" title="Edit"><?= icon('edit',13) ?></button>' +
    '<button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktDuplicate(' + id + ')" title="Duplicate"><?= icon('copy',13) ?></button>' +
    '<button type="button" class="btn-admin-danger btn-admin-sm" onclick="mktConfirmDelete(' + id + ',\'' + esc(name).replace(/'/g,"\\'") + '\')" title="Delete"><?= icon('trash',13) ?></button>' +
  '</div>';
}
function mktConfirmDelete(id, name) {
  document.getElementById('mktDeleteTplId').value = id;
  document.getElementById('mktDeleteTplMsg').textContent = 'Delete "' + name + '"? This cannot be undone.';
  document.getElementById('mktDeleteTplModal').classList.add('open');
}
function mktDuplicate(id) {
  var f = document.createElement('form');
  f.method = 'POST'; f.action = 'index.php';
  f.innerHTML = '<input type="hidden" name="action" value="marketing_duplicate_template"/>' +
    '<input type="hidden" name="template_id" value="' + id + '"/>' +
    '<?= csrfField() ?>';
  document.body.appendChild(f); f.submit();
}

function mktRenderVarList(channel) {
  var list = document.getElementById('mktVarList');
  list.innerHTML = '';
  Object.keys(MKT_VARIABLES).forEach(function (key) {
    var v = MKT_VARIABLES[key];
    if (v.channels.indexOf(channel) === -1) return;
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'mkt-var-btn';
    btn.title = v.label;
    btn.textContent = '{{' + key + '}}';
    btn.addEventListener('click', function () { mktInsertAtCursor('mktBody', '{{' + key + '}}'); });
    list.appendChild(btn);
  });
}
var mktBodyEditorInstance = null;
var mktBodyEditorReady = null;

// "Basic" toolbar per request — bold/italic/lists/link only, no menubar,
// no image upload (that needs a server-side upload endpoint — separate
// feature, not built here).
function mktEnsureBodyEditor() {
  if (mktBodyEditorInstance) return Promise.resolve(mktBodyEditorInstance);
  if (mktBodyEditorReady) return mktBodyEditorReady;
  if (typeof tinymce === 'undefined') {
    document.getElementById('mktBodyRichError').style.display = 'block';
    document.getElementById('mktBody').style.display = '';
    document.getElementById('mktBodyRich').style.display = 'none';
    return Promise.resolve(null);
  }
mktBodyEditorReady = tinymce.init({
    selector: '#mktBodyRichArea',
    height: 260,
    menubar: true,
    branding: false,
    statusbar: true,
    plugins: 'lists link image code',
    toolbar: 'undo redo | bold italic | bullist numlist | link image | removeformat',
    content_style: 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;font-size:13px;} img{max-width:100%;height:auto;}',
    automatic_uploads: true,
    file_picker_types: 'image',
    // Handles the Image dialog's Upload tab, plus paste and drag-and-drop —
    // all three route through this same function in TinyMCE 6.
    images_upload_handler: function (blobInfo, progress) {
      return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.withCredentials = true;
        xhr.open('POST', 'index.php?page=marketing_templates&ajax_upload_image=1');
        xhr.upload.onprogress = function (e) { if (e.lengthComputable) progress((e.loaded / e.total) * 100); };
        xhr.onload = function () {
          var json = null;
          try { json = JSON.parse(xhr.responseText); } catch (e) {}
          if (xhr.status !== 200 || !json || !json.location) {
            reject((json && json.error) || ('Upload failed (HTTP ' + xhr.status + ').'));
            return;
          }
          resolve(json.location);
        };
        xhr.onerror = function () { reject('Image upload failed — network error.'); };
        var formData = new FormData();
        formData.append('file', blobInfo.blob(), blobInfo.filename());
        formData.append('csrf_token', <?= json_encode(csrfToken()) ?>);
        xhr.send(formData);
      });
    },
    setup: function (editor) {
      editor.on('input change keyup undo redo', function () {
        document.getElementById('mktBody').value = editor.getContent();
        mktRunPreview();
      });
    }
  }).then(function (editors) {
    mktBodyEditorInstance = editors && editors[0] ? editors[0] : null;
    if (!mktBodyEditorInstance) throw new Error('TinyMCE init returned no editor instance.');
    return mktBodyEditorInstance;
  }).catch(function (err) {
    console.error('TinyMCE failed to load:', err);
    document.getElementById('mktBodyRichError').style.display = 'block';
    document.getElementById('mktBody').style.display = '';
    document.getElementById('mktBodyRich').style.display = 'none';
    mktBodyEditorReady = null;
    return null;
  });
  return mktBodyEditorReady;
}

function mktDestroyBodyEditor() {
  if (mktBodyEditorInstance) {
    try { mktBodyEditorInstance.remove(); } catch (e) {}
    mktBodyEditorInstance = null;
  }
  mktBodyEditorReady = null;
}

function mktInsertAtCursor(fieldId, text) {
  if (fieldId === 'mktBody' && mktBodyEditorInstance) {
    mktBodyEditorInstance.execCommand('mceInsertContent', false, text);
    document.getElementById('mktBody').value = mktBodyEditorInstance.getContent();
    mktBodyEditorInstance.focus();
    mktRunPreview();
    return;
  }
  var el = document.getElementById(fieldId);
  var start = el.selectionStart, end = el.selectionEnd;
  el.value = el.value.slice(0, start) + text + el.value.slice(end);
  el.focus(); el.selectionStart = el.selectionEnd = start + text.length;
  mktRunPreview();
}

function mktToggleHeaderText() {
  var val = document.getElementById('mktHeaderType').value;
  document.getElementById('mktHeaderText').style.display = val === 'text' ? '' : 'none';
  document.getElementById('mktHeaderDocNote').style.display = val === 'document' ? '' : 'none';
}

function mktAddButtonRow(existing) {
  existing = existing || { type: 'quick_reply', text: '', value: '' };
  var wrap = document.createElement('div');
  wrap.className = 'mkt-btn-row';
  wrap.innerHTML =
    '<select class="admin-input admin-select" name="button_type[]">' +
      '<option value="quick_reply"' + (existing.type==='quick_reply'?' selected':'') + '>Quick Reply</option>' +
      '<option value="url"' + (existing.type==='url'?' selected':'') + '>URL</option>' +
      '<option value="phone_number"' + (existing.type==='phone_number'?' selected':'') + '>Call Phone</option>' +
    '</select>' +
    '<input type="text" class="admin-input" name="button_text[]" placeholder="Button label" value="' + esc(existing.text) + '"/>' +
    '<input type="text" class="admin-input" name="button_value[]" placeholder="URL / phone (supports {{vars}})" value="' + esc(existing.value) + '"/>' +
    '<button type="button" class="btn-admin-danger btn-admin-sm" onclick="this.parentElement.remove()">×</button>';
  document.getElementById('mktButtonsList').appendChild(wrap);
}

function mktRunPreview() {
  clearTimeout(mktPreviewTimer);
  mktPreviewTimer = setTimeout(function () {
    var body = new URLSearchParams();
    body.set('channel', mktActiveTab);
    body.set('subject', document.getElementById('mktSubject').value);
    body.set('body', document.getElementById('mktBody').value);
    body.set('footer', document.getElementById('mktFooter').value);
    body.set('csrf_token', <?= json_encode(csrfToken()) ?>);
    fetch('index.php?page=marketing_templates&ajax_preview=1', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var out = document.getElementById('mktPreviewOutput');
        if (mktActiveTab === 'whatsapp') {
          out.innerHTML = '<div class="mkt-wa-bubble">' + esc(d.body) + (d.footer ? '<br/><span style="font-size:11px;color:#667781;">' + esc(d.footer) + '</span>' : '') + '</div>';
        } else {
          out.innerHTML = '<p style="font-weight:700;margin-bottom:8px;">' + esc(d.subject) + '</p><div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:14px;">' + d.body + '</div>';
        }
      });
  }, 250);
}

function mktOpenCreate(channel) {
  document.getElementById('mktEditorTitle').textContent = 'New ' + (channel === 'whatsapp' ? 'WhatsApp' : 'Email') + ' Template';
  document.getElementById('mktFormAction').value = 'marketing_create_template';
  document.getElementById('mktTemplateId').value = '';
  document.getElementById('mktChannel').value = channel;
  document.getElementById('mktName').value = '';
  document.getElementById('mktLanguage').value = channel === 'whatsapp' ? 'en_US' : 'en';
  document.getElementById('mktSubject').value = '';
  document.getElementById('mktBody').value = '';
  document.getElementById('mktFooter').value = '';
  document.getElementById('mktHeaderType').value = 'none';
  document.getElementById('mktHeaderText').value = '';
  document.getElementById('mktProviderName').value = '';
  document.getElementById('mktCategory').value = 'MARKETING';
  document.getElementById('mktApprovalStatus').value = 'draft';
 document.getElementById('mktButtonsList').innerHTML = '';
  document.getElementById('mktVarMapList').innerHTML = '';
  document.getElementById('mktVarMapWrap').style.display = 'none';
  mktToggleHeaderText();
  mktApplyChannelVisibility(channel);
  mktRenderVarList(channel);
  document.getElementById('mktEditorModal').classList.add('open');
  mktRunPreview();
}

function mktOpenEdit(id) {
  fetch('index.php?page=marketing_templates&ajax_template_get=1&id=' + id)
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var t = d.template;
      if (!t) { alert('Template not found.'); return; }
      document.getElementById('mktEditorTitle').textContent = 'Edit Template';
      document.getElementById('mktFormAction').value = 'marketing_update_template';
      document.getElementById('mktTemplateId').value = t.id;
      document.getElementById('mktChannel').value = t.channel;
      document.getElementById('mktName').value = t.name;
      document.getElementById('mktLanguage').value = t.language;
      document.getElementById('mktSubject').value = t.subject || '';
      document.getElementById('mktBody').value = t.body;
      document.getElementById('mktFooter').value = t.footer || '';
      document.getElementById('mktHeaderType').value = (t.header && t.header.type) || 'none';
      document.getElementById('mktHeaderText').value = (t.header && t.header.text) || '';
      document.getElementById('mktProviderName').value = t.provider_template_name || '';
      document.getElementById('mktCategory').value = t.category || 'MARKETING';
      document.getElementById('mktApprovalStatus').value = t.approval_status || 'draft';
      document.getElementById('mktButtonsList').innerHTML = '';
     (t.buttons || []).forEach(function (b) { mktAddButtonRow(b); });
      window.mktCurrentMapping = t.variable_mapping || [];
      mktToggleHeaderText();
      mktApplyChannelVisibility(t.channel);
      mktRenderVarList(t.channel);
      mktRenderVarMap();
      document.getElementById('mktEditorModal').classList.add('open');
      mktRunPreview();
    });
}

function mktRenderVarMap() {
  var isWa = document.getElementById('mktChannel').value === 'whatsapp';
  var providerName = document.getElementById('mktProviderName').value.trim();
  var wrap = document.getElementById('mktVarMapWrap');
  if (!isWa || providerName === '') { wrap.style.display = 'none'; return; }

  var body = document.getElementById('mktBody').value;
  var positions = [];
  var re = /\{\{\s*(\d+)\s*\}\}/g, m;
  while ((m = re.exec(body)) !== null) { var n = parseInt(m[1], 10); if (positions.indexOf(n) === -1) positions.push(n); }
  positions.sort(function (a, b) { return a - b; });

  if (!positions.length) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';

  var existing = window.mktCurrentMapping || [];
  var list = document.getElementById('mktVarMapList');
  list.innerHTML = '';
  positions.forEach(function (pos, idx) {
    var row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:6px;';
    var opts = '<option value="">— Select —</option>';
    Object.keys(MKT_VARIABLES).forEach(function (key) {
      var sel = existing[idx] === key ? ' selected' : '';
      opts += '<option value="' + key + '"' + sel + '>' + MKT_VARIABLES[key].label + '</option>';
    });
    row.innerHTML = '<span style="font-family:monospace;font-size:12px;min-width:50px;">{{' + pos + '}}</span>' +
      '<select name="var_map[]" class="admin-input admin-select" style="flex:1;">' + opts + '</select>';
    list.appendChild(row);
  });
}

function mktApplyChannelVisibility(channel) {
  var isWa = channel === 'whatsapp';
  document.getElementById('mktWaFields').style.display = isWa ? '' : 'none';
  document.getElementById('mktButtonsWrap').style.display = isWa ? '' : 'none';
  document.getElementById('mktFooterWrap').style.display = isWa ? '' : 'none';
  document.getElementById('mktEmailFields').style.display = isWa ? 'none' : '';
  document.getElementById('mktSubject').required = !isWa;

  document.getElementById('mktBodyRichError').style.display = 'none';
  if (isWa) {
    mktDestroyBodyEditor();
    document.getElementById('mktBodyRich').style.display = 'none';
    document.getElementById('mktBody').style.display = '';
  } else {
    var currentValue = document.getElementById('mktBody').value || '';
    document.getElementById('mktBody').style.display = 'none';
    document.getElementById('mktBodyRich').style.display = '';
   mktEnsureBodyEditor().then(function (editor) {
      if (editor) editor.setContent(currentValue);
    });
  }
}

document.getElementById('mktEditorForm').addEventListener('submit', function () {
  if (mktBodyEditorInstance) {
    document.getElementById('mktBody').value = mktBodyEditorInstance.getContent();
  }
});

function mktCloseEditor() { document.getElementById('mktEditorModal').classList.remove('open'); }
document.querySelectorAll('.mkt-modal').forEach(function (m) {
  m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('open'); });
});

mktLoadRows('whatsapp');
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>