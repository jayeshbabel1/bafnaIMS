<?php
$pid = (int)($_GET['id'] ?? 0);
$db  = getDB();
$p   = null;
if ($pid) {
    $st = $db->prepare("SELECT * FROM products WHERE id=?"); $st->execute([$pid]);
    $p  = $st->fetch() ?: null;
    $ps = $db->prepare("SELECT * FROM product_photos WHERE product_id=? ORDER BY sort_order"); $ps->execute([$pid]);
    $existingPhotos = $ps->fetchAll();
} else {
    $existingPhotos = [];
}
 
$adminTitle = $p ? 'Edit: ' . $p['name'] : 'Add Product';
include __DIR__ . '/../_layout_top.php';
 
$pal = $p ? (json_decode($p['palette']??'[]',true) ?: ['F2F0EC','D8CFC4','BFB0A0']) : ['F2F0EC','D8CFC4','BFB0A0'];
$g   = fn($k) => h($p[$k] ?? '');
?>
 
<style>
/* ── Product Edit Responsive Layout ─────────────────────────── */
 
/* Mobile first: single column */
.pe-layout {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.pe-main  { width: 100%; min-width: 0; }
.pe-side  { width: 100%; min-width: 0; }
 
/* Tablet (≥ 768px): still single column but wider padding */
@media (min-width: 768px) {
    .pe-layout { gap: 20px; }
}
 
/* Desktop (≥ 1100px): two-column side by side */
@media (min-width: 1100px) {
    .pe-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        align-items: start;
    }
    .pe-main { width: auto; }
    .pe-side  { width: auto; }
}
 
/* Form grid inside sections */
.pe-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}
@media (min-width: 480px) {
    .pe-form-grid { grid-template-columns: 1fr 1fr; }
}
 
/* Dim preview bar */
#dimPreviewBar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 14px;
    background: var(--surface2);
    border-radius: 8px;
    margin-top: 8px;
    font-size: 12px;
    color: var(--text2);
}
 
/* Action bar at top */
.pe-action-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
 
/* Photo grid */
.photo-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.photo-grid-item {
    width: 68px;
    height: 68px;
}
@media (min-width: 480px) {
    .photo-grid-item { width: 76px; height: 76px; }
}
 
/* Palette inputs */
.pe-palette-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.pe-palette-swatch {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid var(--border);
    flex-shrink: 0;
}
 
/* Submit bar sticky on mobile */
.pe-submit-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}
.pe-submit-bar .btn-admin-primary { flex: 1; justify-content: center; min-width: 140px; }
@media (min-width: 640px) {
    .pe-submit-bar .btn-admin-primary { flex: none; }
}
 
/* Quantities grid */
.pe-qty-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
@media (min-width: 480px) {
    .pe-qty-grid { grid-template-columns: repeat(4, 1fr); }
}
 
/* Documents grid */
.pe-doc-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}
@media (min-width: 640px) {
    .pe-doc-grid { grid-template-columns: 1fr 1fr; }
}
</style>
 
<!-- Action bar -->
<div class="pe-action-bar">
    <a href="index.php?page=products" class="btn-admin-secondary btn-admin-sm">
        <?= icon('back',14) ?> Back
    </a>
    <?php if ($p): ?>
    <a href="../index.php?page=product&id=<?= $pid ?>" target="_blank"
       class="btn-admin-secondary btn-admin-sm">
        <?= icon('eye',14) ?> Preview
    </a>
    <?php
    $waThumb = '';
    if (!empty($existingPhotos)) {
        $firstPhoto = $existingPhotos[0]['filename'] ?? '';
        if ($firstPhoto && file_exists(PHOTOS_DIR.'/'.$firstPhoto)) {
            $waThumb = '../assets/uploads/photos/' . $firstPhoto;
        }
    }
    ?>
    <button type="button"
            onclick="openWaShare(<?= $pid ?>, <?= h(json_encode($p['name'])) ?>, <?= h(json_encode($p['quarry_number'])) ?>, <?= h(json_encode($waThumb)) ?>)"
            class="btn-admin-secondary btn-admin-sm"
            style="color:#25D366;border-color:#25D366;">
        <?= icon('whatsapp',14) ?> Share
    </button>
    <button type="button" id="pdfDownloadBtn"
            onclick="downloadProductPdf(<?= $pid ?>, <?= h(json_encode($p['name'])) ?>)"
            class="btn-admin-secondary btn-admin-sm"
            style="color:var(--danger);border-color:var(--danger);">
        <?= icon('pdf',14) ?> PDF
    </button>
    <?php endif; ?>
</div>
 
<form method="POST" action="index.php" enctype="multipart/form-data" id="productForm">
    <input type="hidden" name="action"     value="save_product"/>
    <input type="hidden" name="product_id" value="<?= $pid ?>"/>
 
    <div class="pe-layout">
 
        <!-- ── LEFT / MAIN COLUMN ─────────────────────────────── -->
        <div class="pe-main">
 
            <!-- Basic Info -->
            <div class="admin-form-section">
                <p class="admin-form-section-title">Basic Information</p>
                <div class="pe-form-grid">
                    <div>
                        <label class="admin-label">Product Name *</label>
                        <input type="text" name="name" class="admin-input"
                               value="<?= $g('name') ?>" required/>
                    </div>
                    <div>
                        <label class="admin-label">Quarry Number *</label>
                        <input type="text" name="quarry_number" class="admin-input"
                               value="<?= $g('quarry_number') ?>" required/>
                    </div>
                    <div>
                        <label class="admin-label">Category</label>
                        <select name="category" class="admin-input admin-select">
                            <option value="">— Select —</option>
                            <?php foreach (CATEGORIES as $c): ?>
                            <option value="<?= h($c) ?>" <?= ($p['category']??'')===$c?'selected':'' ?>>
                                <?= h($c) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">Sub-Category</label>
                        <input type="text" name="subcategory" class="admin-input"
                               value="<?= $g('subcategory') ?>"/>
                    </div>
                    <div>
                        <label class="admin-label">Color Type</label>
                        <select name="color_subcategory" class="admin-input admin-select">
                            <option value="">— Select —</option>
                            <?php foreach (COLOR_SUBCATEGORIES as $c): ?>
                            <option value="<?= h($c) ?>" <?= ($p['color_subcategory']??'')===$c?'selected':'' ?>>
                                <?= h($c) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">Origin</label>
                        <input type="text" name="origin" class="admin-input"
                               value="<?= $g('origin') ?>"/>
                    </div>
                    <div>
                        <label class="admin-label">Finish</label>
                        <input type="text" name="finish" class="admin-input"
                               value="<?= $g('finish') ?>"/>
                    </div>
                    <div>
                        <label class="admin-label">Thickness (mm)</label>
                        <input type="text" name="thickness" class="admin-input"
                               value="<?= $g('thickness') ?>"/>
                    </div>
                    <!-- Slab sizes -->
                    <div>
                        <label class="admin-label">Useable Size — Length</label>
                        <input type="text" name="sizes_l" class="admin-input"
                               value="<?= $g('sizes_l') ?>" placeholder="e.g. 233"/>
                    </div>
                    <div>
                        <label class="admin-label">Useable Size — Height</label>
                        <input type="text" name="sizes_h" class="admin-input"
                               value="<?= $g('sizes_h') ?>" placeholder="e.g. 120"/>
                    </div>
                    <!-- Italian sizes -->
                    <div>
                        <label class="admin-label">Italian Size — Length</label>
                        <input type="text" name="cutter_size_l" class="admin-input"
                               value="<?= $g('cutter_size_l') ?>" placeholder="e.g. 104"/>
                    </div>
                    <div>
                        <label class="admin-label">Italian Size — Height</label>
                        <input type="text" name="cutter_size_h" class="admin-input"
                               value="<?= $g('cutter_size_h') ?>" placeholder="e.g. 34"/>
                    </div>
                </div>
 
                <!-- Dimension preview -->
                <div id="dimPreviewBar">
                    <span>Useable Size: <strong id="previewSizes">—</strong></span>
                    <span>Italian Size: <strong id="previewCutter">—</strong></span>
                </div>
 
                <div style="margin-top:14px;">
                    <label class="admin-label">Description</label>
                    <textarea name="description" class="admin-input" rows="3"
                              style="resize:vertical;"><?= $g('description') ?></textarea>
                </div>
            </div>
 
            <!-- Inventory -->
            <div class="admin-form-section">
                <p class="admin-form-section-title">Inventory Quantities</p>
                <div class="pe-qty-grid">
                    <div>
                        <label class="admin-label">Total (sqft)</label>
                        <input type="number" step="0.01" name="total_quantity"
                               class="admin-input" value="<?= $g('total_quantity') ?>" placeholder="0.00"/>
                    </div>
                    <div>
                        <label class="admin-label">Available (sqft)</label>
                        <input type="number" step="0.01" name="quantity_available"
                               class="admin-input" value="<?= $g('quantity_available') ?>" placeholder="0.00"/>
                    </div>
                    <div>
                        <label class="admin-label">On Hold (sqft)</label>
                        <input type="number" step="0.01" name="quantity_on_hold"
                               class="admin-input" value="<?= $g('quantity_on_hold') ?>" placeholder="0.00"/>
                    </div>
                    <div>
                        <label class="admin-label">Pieces</label>
                        <input type="number" name="pieces" class="admin-input"
                               value="<?= $g('pieces') ?>" placeholder="0"/>
                    </div>
                </div>
                <div style="display:flex;gap:20px;margin-top:14px;flex-wrap:wrap;">
                    <label class="admin-check-row">
                        <input type="checkbox" name="in_stock" value="1"
                               <?= ($p['in_stock']??1)?'checked':'' ?>/>
                        <span style="font-size:13px;font-weight:500;">In Stock</span>
                    </label>
                    <label class="admin-check-row">
                        <input type="checkbox" name="featured" value="1"
                               <?= ($p['featured']??0)?'checked':'' ?>/>
                        <span style="font-size:13px;font-weight:500;">✦ Featured</span>
                    </label>
                </div>
            </div>
 
            <!-- Documents -->
            <div class="admin-form-section">
                <p class="admin-form-section-title">Documents</p>
                <div class="pe-doc-grid">
                    <div>
                        <label class="admin-label">Measurement Sheet</label>
                        <?php if ($p && $p['measurement_sheet']): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;
                                    padding:8px;background:var(--surface2);border-radius:8px;">
                            <?= icon('file',14) ?>
                            <span style="font-size:12px;font-weight:500;flex:1;min-width:0;
                                         white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= h($p['measurement_sheet']) ?>
                            </span>
                            <a href="../assets/uploads/measurement_sheets/<?= h($p['measurement_sheet']) ?>"
                               target="_blank" style="color:var(--accent);font-size:11px;flex-shrink:0;">View</a>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="measurement_sheet" class="admin-input" style="padding:6px;"/>
                    </div>
                    <div>
                        <label class="admin-label">DNA / Lot Report (PDF)</label>
                        <?php if ($p && $p['dna_report']): ?>
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;
                                    padding:8px;background:var(--surface2);border-radius:8px;">
                            <?= icon('pdf',14) ?>
                            <span style="font-size:12px;font-weight:500;flex:1;min-width:0;
                                         white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?= h($p['dna_report']) ?>
                            </span>
                            <a href="../assets/uploads/dna_reports/<?= h($p['dna_report']) ?>"
                               target="_blank" style="color:var(--danger);font-size:11px;flex-shrink:0;">View</a>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="dna_report" accept=".pdf"
                               class="admin-input" style="padding:6px;"/>
                    </div>
                </div>
            </div>
 
        </div><!-- /.pe-main -->
 
        <!-- ── RIGHT / SIDE COLUMN ────────────────────────────── -->
        <div class="pe-side">
 
            <!-- Photos -->
            <div class="admin-form-section">
                <p class="admin-form-section-title">Product Photos</p>
                <?php if (!empty($existingPhotos)): ?>
                <div class="photo-grid" style="margin-bottom:12px;">
                    <?php foreach ($existingPhotos as $ph): ?>
                    <div class="photo-grid-item">
                        <?php if (file_exists(PHOTOS_DIR.'/'.$ph['filename'])): ?>
                        <img src="../assets/uploads/photos/<?= h($ph['filename']) ?>" alt=""/>
                        <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;
                                    justify-content:center;font-size:9px;color:var(--text3);">
                            No file
                        </div>
                        <?php endif; ?>
                        <form method="POST" action="index.php" style="display:contents;">
                            <input type="hidden" name="photo_id" value="<?= $ph['id'] ?>"/>
                            <button type="submit" name="action" value="delete_photo"
                                    formaction="index.php"
                                    class="photo-grid-del"
                                    data-confirm="Delete this photo?">×</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="upload-zone" style="padding:16px;">
                    <input type="file" name="photos[]" id="photoUpload" accept="image/*" multiple/>
                    <div style="pointer-events:none;">
                        <?= icon('image',24) ?>
                        <p style="font-size:12px;font-weight:600;margin-top:8px;">
                            Click to add photos
                        </p>
                        <p style="font-size:11px;color:var(--text3);">Multiple files supported</p>
                    </div>
                </div>
                <div class="photo-grid" id="newPhotoGrid" style="margin-top:8px;"></div>
            </div>
 
            <!-- Palette -->
            <div class="admin-form-section">
                <p class="admin-form-section-title">Stone Palette</p>
                <p style="font-size:11px;color:var(--text3);margin-bottom:10px;">
                    Used for SVG preview when no photos are uploaded.
                </p>
                <?php for ($i = 0; $i < 3; $i++): $c = $pal[$i] ?? 'F2F0EC'; ?>
                <div class="pe-palette-row">
                    <div class="pe-palette-swatch" style="background:#<?= h($c) ?>;"></div>
                    <input type="text" class="admin-input pal-input" value="<?= h($c) ?>"
                           placeholder="FFFFFF" maxlength="6"
                           style="font-family:monospace;flex:1;"
                           oninput="this.previousElementSibling.style.background='#'+this.value; syncPalette()"/>
                </div>
                <?php endfor; ?>
                <div class="palette-preview" id="palettePreview"></div>
                <input type="hidden" name="palette" id="paletteHidden"
                       value="<?= h($p['palette'] ?? '["F2F0EC","D8CFC4","BFB0A0"]') ?>"/>
            </div>
 
            <!-- Sort Order -->
            <div class="admin-form-section">
                <p class="admin-form-section-title">Display Order</p>
                <label class="admin-label">Sort Order (lower = first)</label>
                <input type="number" name="sort_order" class="admin-input"
                       value="<?= $g('sort_order') ?>" min="0"/>
            </div>
 
            <!-- Submit -->
            <div class="pe-submit-bar">
                <button type="submit" class="btn-admin-primary" onclick="syncPalette()">
                    <?= icon('check',16) ?>
                    <?= $pid ? 'Update Product' : 'Create Product' ?>
                </button>
                <a href="index.php?page=products" class="btn-admin-secondary">Cancel</a>
            </div>
 
        </div><!-- /.pe-side -->
 
    </div><!-- /.pe-layout -->
</form>
 
<script>
/* Live dimension preview */
(function () {
    function update() {
        var slL = (document.querySelector('[name="sizes_l"]')?.value || '').trim();
        var slH = (document.querySelector('[name="sizes_h"]')?.value || '').trim();
        var ctL = (document.querySelector('[name="cutter_size_l"]')?.value || '').trim();
        var ctH = (document.querySelector('[name="cutter_size_h"]')?.value || '').trim();
        var ps = document.getElementById('previewSizes');
        var pc = document.getElementById('previewCutter');
        if (ps) ps.textContent = (slL && slH) ? slL + ' × ' + slH : (slL || slH || '—');
        if (pc) pc.textContent = (ctL && ctH) ? ctL + ' × ' + ctH : (ctL || ctH || '—');
    }
    ['sizes_l','sizes_h','cutter_size_l','cutter_size_h'].forEach(function(n) {
        var el = document.querySelector('[name="'+n+'"]');
        if (el) el.addEventListener('input', update);
    });
    update();
})();
 
/* PDF download */
function downloadProductPdf(productId, productName) {
    var btn = document.getElementById('pdfDownloadBtn');
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<?= icon('refresh',14) ?> Generating…';
    fetch('index.php?pdf_download=1&product_id=' + productId)
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.blob();
        })
        .then(function(blob) {
            var safe = (productName||'product').replace(/[^A-Za-z0-9 _\-]/g,'').trim().replace(/\s+/g,'_');
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = safe + '.pdf';
            document.body.appendChild(a); a.click();
            document.body.removeChild(a); URL.revokeObjectURL(url);
        })
        .catch(function(e) { alert('PDF generation failed: ' + e.message); })
        .finally(function() { btn.disabled = false; btn.innerHTML = orig; });
}
</script>
 
<?php include __DIR__ . '/_wa_share_modal.php'; ?>
<?php include __DIR__ . '/../_layout_bottom.php'; ?>