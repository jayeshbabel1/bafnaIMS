<?php
$adminTitle = 'Sync Product Data';
include __DIR__ . '/../_layout_top.php';

// Describe what each directory holds
$stepMeta = [
    1 => [
        'label'   => 'Photos / Images',
        'icon'    => 'image',
        'color'   => 'var(--accent)',
        'bg'      => 'var(--accent-light)',
        'dir'     => PHOTOS_DIR,
        'desc'    => 'Scans <code>assets/uploads/photos/</code> and links image files to products by quarry number.',
        'format'  => 'Expected filename: <strong>Q23048-IMG.jpg</strong> or <strong>Q23048-IMG-1.jpg</strong>',
    ],
    2 => [
        'label'   => 'Measurement Sheets',
        'icon'    => 'file',
        'color'   => 'var(--success)',
        'bg'      => 'var(--success-bg)',
        'dir'     => MEASUREMENT_DIR,
        'desc'    => 'Scans <code>assets/uploads/measurement_sheets/</code> and links PDFs to products.',
        'format'  => 'Expected filename: <strong>Q23048-MS.pdf</strong> or <strong>Q23048-MS-1.pdf</strong>',
    ],
    3 => [
        'label'   => 'DNA / Lot Reports',
        'icon'    => 'pdf',
        'color'   => 'var(--danger)',
        'bg'      => 'var(--danger-bg)',
        'dir'     => DNA_DIR,
        'desc'    => 'Scans <code>assets/uploads/dna_reports/</code> and links PDF lot reports to products.',
        'format'  => 'Expected filename: <strong>Q23048-DNA.pdf</strong> or <strong>Q23048-DNA-1.pdf</strong>',
    ],
];

// Count files in each dir for overview
function countDir(string $dir, array $exts): int {
    if (!is_dir($dir)) return 0;
    $c = 0;
    foreach (array_diff(scandir($dir), ['.','..']) as $f) {
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $exts)) $c++;
    }
    return $c;
}
$imgCount = countDir(PHOTOS_DIR,      ['jpg','jpeg','png','webp']);
$msCount  = countDir(MEASUREMENT_DIR, ['pdf']);
$dnaCount = countDir(DNA_DIR,         ['pdf']);
$counts   = [1 => $imgCount, 2 => $msCount, 3 => $dnaCount];
?>

<style>
.sync-card {
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:var(--card-radius);
    padding:20px 24px;
    margin-bottom:16px;
    display:flex;
    align-items:flex-start;
    gap:18px;
    transition:box-shadow .2s;
}
.sync-card.running { box-shadow:0 0 0 2px var(--accent); }
.sync-card.done    { box-shadow:0 0 0 2px var(--success); }
.sync-card.errored { box-shadow:0 0 0 2px var(--gold); }
.sync-step-icon {
    width:48px;height:48px;border-radius:14px;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;font-size:20px;
}
.sync-step-body { flex:1; }
.sync-step-title { font-size:15px;font-weight:700;color:var(--text);margin-bottom:3px; }
.sync-step-desc  { font-size:12px;color:var(--text3);margin-bottom:4px;line-height:1.5; }
.sync-step-format{ font-size:11px;color:var(--text3);margin-bottom:10px; }
.sync-status-row { display:flex;align-items:center;gap:14px;flex-wrap:wrap; }
.sync-stat { font-size:12px;font-weight:600; }
.sync-stat span  { font-size:18px;font-weight:700;font-family:'Cormorant Garamond',serif; }

/* Progress bar */
.progress-wrap  { background:var(--border);border-radius:6px;height:8px;overflow:hidden;width:100%;margin:10px 0 6px; }
.progress-fill  { height:100%;border-radius:6px;transition:width .4s ease;width:0%; }
.progress-label { font-size:11px;color:var(--text3);text-align:right; }

/* Overall bar */
.overall-wrap { background:var(--surface);border:1px solid var(--border);border-radius:var(--card-radius);padding:20px 24px;margin-bottom:24px; }
.overall-bar  { background:var(--border);border-radius:8px;height:14px;overflow:hidden;margin:14px 0 8px; }
.overall-fill { height:100%;border-radius:8px;background:linear-gradient(90deg,var(--accent),var(--accent-mid));transition:width .5s ease;width:0%; }

/* Error log */
.error-log  { margin-top:10px;max-height:140px;overflow-y:auto;background:var(--surface2);border-radius:8px;padding:8px 12px; }
.error-item { font-size:11px;color:var(--text3);padding:2px 0;border-bottom:1px solid var(--border);line-height:1.4; }
.error-item:last-child{border-bottom:none;}

.badge-count { display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600; }
</style>

<!-- ── Overall progress ───────────────────────────────────────────────────── -->
<div class="overall-wrap" id="overallCard">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <p style="font-size:17px;font-weight:700;">Data Sync</p>
            <p style="font-size:13px;color:var(--text3);margin-top:2px;">
                Scans upload folders and links files to products by quarry number.
            </p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <span class="badge-count" style="background:var(--accent-light);color:var(--accent);">
                <?= icon('image',12) ?> <?= $imgCount ?> photos
            </span>
            <span class="badge-count" style="background:var(--success-bg);color:var(--success);">
                <?= icon('file',12) ?> <?= $msCount ?> sheets
            </span>
            <span class="badge-count" style="background:var(--danger-bg);color:var(--danger);">
                <?= icon('pdf',12) ?> <?= $dnaCount ?> reports
            </span>
            <button id="startSyncBtn" onclick="startSync()" class="btn-admin-primary" style="padding:10px 24px;">
                <?= icon('refresh',16) ?> Start Sync
            </button>
        </div>
    </div>
    <div class="overall-bar"><div class="overall-fill" id="overallFill"></div></div>
    <p class="progress-label" id="overallLabel" style="font-size:12px;color:var(--text3);">Ready — click Start Sync to begin</p>
</div>

<!-- ── Step cards ─────────────────────────────────────────────────────────── -->
<?php foreach ($stepMeta as $stepNum => $meta): ?>
<div class="sync-card" id="stepCard<?= $stepNum ?>">
    <div class="sync-step-icon" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>;">
        <?= icon($meta['icon'], 22) ?>
    </div>
    <div class="sync-step-body">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
            <p class="sync-step-title">Step <?= $stepNum ?> — <?= h($meta['label']) ?></p>
            <span id="stepBadge<?= $stepNum ?>" class="badge badge-gray">Pending</span>
            <span class="badge-count" style="background:var(--surface2);color:var(--text3);">
                <?= $counts[$stepNum] ?> file<?= $counts[$stepNum] !== 1 ? 's' : '' ?> found
            </span>
        </div>
        <p class="sync-step-desc"><?= $meta['desc'] ?></p>
        <p class="sync-step-format"><?= $meta['format'] ?></p>

        <!-- Progress bar -->
        <div class="progress-wrap">
            <div class="progress-fill" id="stepBar<?= $stepNum ?>" style="background:<?= $meta['color'] ?>;"></div>
        </div>
        <p class="progress-label" id="stepLabel<?= $stepNum ?>">—</p>

        <!-- Stats row (hidden until run) -->
        <div class="sync-status-row" id="stepStats<?= $stepNum ?>" style="display:none;margin-top:8px;">
            <div class="sync-stat" style="color:var(--success);">
                <span id="statSynced<?= $stepNum ?>">0</span> synced
            </div>
            <div class="sync-stat" style="color:var(--text3);">
                <span id="statSkipped<?= $stepNum ?>">0</span> skipped
            </div>
            <div class="sync-stat" style="color:var(--text2);">
                <span id="statFound<?= $stepNum ?>">0</span> total
            </div>
        </div>

        <!-- Error log (hidden until errors exist) -->
        <div id="stepErrors<?= $stepNum ?>" style="display:none;">
            <p style="font-size:11px;font-weight:600;color:var(--text3);margin-top:10px;">
                <?= icon('info',12) ?> Skipped / Notes
            </p>
            <div class="error-log" id="stepErrorList<?= $stepNum ?>"></div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ── Done banner (hidden until all steps finish) ────────────────────────── -->
<div id="doneBanner" style="display:none;background:var(--success-bg);border:1px solid var(--success);
     border-radius:var(--card-radius);padding:20px 24px;display:none;align-items:center;gap:14px;">
    <div style="width:44px;height:44px;border-radius:50%;background:var(--success);
         display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;">
        <?= icon('check',20) ?>
    </div>
    <div>
        <p style="font-size:16px;font-weight:700;color:var(--success);">All steps complete!</p>
        <p style="font-size:13px;color:var(--text2);margin-top:2px;" id="doneSummary"></p>
    </div>
    <a href="index.php?page=products" class="btn-admin-secondary" style="margin-left:auto;">
        <?= icon('grid',14) ?> View Products
    </a>
</div>

<script>
const TOTAL_STEPS = 3;
let totals = { synced: 0, skipped: 0, found: 0 };

function startSync() {
    // Reset state
    totals = { synced: 0, skipped: 0, found: 0 };
    document.getElementById('doneBanner').style.display = 'none';
    document.getElementById('startSyncBtn').disabled = true;
    document.getElementById('startSyncBtn').innerHTML = '<?= icon('refresh',16) ?> Syncing…';
    document.getElementById('overallLabel').textContent = 'Running step 1 of <?= count($stepMeta) ?>…';

    for (let i = 1; i <= TOTAL_STEPS; i++) {
        setBadge(i, 'Pending', 'badge-gray');
        setBar(i, 0);
        document.getElementById('stepLabel'+i).textContent = '—';
        document.getElementById('stepStats'+i).style.display = 'none';
        document.getElementById('stepErrors'+i).style.display = 'none';
        document.getElementById('stepErrorList'+i).innerHTML = '';
        document.getElementById('stepCard'+i).className = 'sync-card';
    }

    runStep(1);
}

async function runStep(step) {
    const card = document.getElementById('stepCard'+step);
    card.className = 'sync-card running';
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    setBadge(step, 'Running…', 'badge-blue');
    document.getElementById('stepLabel'+step).textContent = 'Scanning files…';

    // Animate bar to ~40% while waiting
    animateBar(step, 40, 800);

    try {
        const resp = await fetch('index.php?ajax_sync='+step);
        if (!resp.ok) throw new Error('HTTP '+resp.status);
        const data = await resp.json();

        // Complete bar
        animateBar(step, 100, 300);

        totals.synced  += (data.synced  || 0);
        totals.skipped += (data.skipped || 0);
        totals.found   += (data.found   || 0);

        // Update stats
        document.getElementById('statSynced'+step).textContent  = data.synced  || 0;
        document.getElementById('statSkipped'+step).textContent = data.skipped || 0;
        document.getElementById('statFound'+step).textContent   = data.found   || 0;
        document.getElementById('stepStats'+step).style.display = 'flex';

        // Status label
        const hasErrors = data.errors && data.errors.length > 0;
        document.getElementById('stepLabel'+step).textContent =
            `${data.synced || 0} synced · ${data.skipped || 0} skipped · ${data.found || 0} total`;

        if (hasErrors) {
            document.getElementById('stepErrors'+step).style.display = 'block';
            data.errors.slice(0, 30).forEach(err => {
                const div = document.createElement('div');
                div.className = 'error-item';
                div.textContent = err;
                document.getElementById('stepErrorList'+step).appendChild(div);
            });
            if (data.errors.length > 30) {
                const div = document.createElement('div');
                div.className = 'error-item';
                div.textContent = `… and ${data.errors.length - 30} more`;
                document.getElementById('stepErrorList'+step).appendChild(div);
            }
            setBadge(step, 'Done (with notes)', 'badge-gold');
            card.className = 'sync-card errored';
        } else {
            setBadge(step, 'Done ✓', 'badge-green');
            card.className = 'sync-card done';
        }

        // Update overall progress bar
        const pct = Math.round((step / TOTAL_STEPS) * 100);
        document.getElementById('overallFill').style.width = pct + '%';

        if (step < TOTAL_STEPS) {
            document.getElementById('overallLabel').textContent = `Running step ${step+1} of ${TOTAL_STEPS}…`;
            setTimeout(() => runStep(step + 1), 400);
        } else {
            allDone();
        }

    } catch (err) {
        setBadge(step, 'Error', 'badge-red');
        card.className = 'sync-card errored';
        document.getElementById('stepLabel'+step).textContent = 'Error: ' + err.message;
        document.getElementById('overallLabel').textContent = 'Sync failed at step ' + step;
        resetBtn();
    }
}

function allDone() {
    document.getElementById('overallFill').style.width = '100%';
    document.getElementById('overallLabel').textContent = 'All steps completed!';
    const banner = document.getElementById('doneBanner');
    banner.style.display = 'flex';
    document.getElementById('doneSummary').textContent =
        `${totals.synced} file(s) synced · ${totals.skipped} skipped · ${totals.found} total scanned`;
    banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    resetBtn();
}

function resetBtn() {
    const btn = document.getElementById('startSyncBtn');
    btn.disabled = false;
    btn.innerHTML = '<?= icon('refresh',16) ?> Run Again';
}

function setBadge(step, text, cls) {
    const el = document.getElementById('stepBadge'+step);
    el.className = 'badge ' + cls;
    el.textContent = text;
}

function setBar(step, pct) {
    document.getElementById('stepBar'+step).style.width = pct + '%';
}

function animateBar(step, target, duration) {
    const bar = document.getElementById('stepBar'+step);
    const start = parseFloat(bar.style.width) || 0;
    const diff = target - start;
    const startTime = performance.now();
    function tick(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        bar.style.width = (start + diff * progress) + '%';
        if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>
