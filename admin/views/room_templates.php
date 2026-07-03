<?php
$adminTitle = 'Room Visualizer Templates';
requireAdminPermission('settings.logo'); // reuse an existing settings permission, or add 'room_templates.manage' to RBAC
include __DIR__ . '/../_layout_top.php';

$db = getDB();
$templates = $db->query("SELECT * FROM room_templates ORDER BY room_type, sort_order")->fetchAll();
?>
<style>
.rt-grid{display:grid;grid-template-columns:1fr;gap:16px;}
@media(min-width:900px){.rt-grid{grid-template-columns:1fr 1fr;}}
.rt-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--card-radius);padding:16px;}
.rt-thumb{width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:8px;background:var(--surface2);margin-bottom:10px;}
#rtCanvasWrap{position:relative;border:1px dashed var(--border);border-radius:8px;overflow:hidden;max-width:520px;}
#rtCanvas{width:100%;display:block;cursor:crosshair;}
.rt-point-list{font-size:11px;color:var(--text3);margin-top:6px;font-family:monospace;}
</style>

<div style="margin-bottom:16px;">
  <button class="btn-admin-primary" onclick="document.getElementById('rtCreateForm').style.display='block'">
    <?= icon('plus',14) ?> Add Room Template
  </button>
</div>

<!-- Create/Edit form -->
<div id="rtCreateForm" class="admin-form-section" style="display:none;">
  <p class="admin-form-section-title">New Room Template</p>
  <form method="POST" action="index.php" enctype="multipart/form-data" id="rtForm">
    <input type="hidden" name="action" value="save_room_template"/>
    <input type="hidden" name="mask_points" id="rtMaskPoints" value=""/>
    <?= csrfField() ?>
    <div class="admin-form-grid">
      <div>
        <label class="admin-label">Room Type</label>
        <select name="room_type" class="admin-input admin-select">
          <?php foreach (ROOM_TYPES as $k=>$v): ?>
          <option value="<?= h($k) ?>"><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="admin-label">Label</label>
        <input type="text" name="label" class="admin-input" placeholder="e.g. Modern Kitchen Floor" required/>
      </div>
      <div>
        <label class="admin-label">Base Room Photo *</label>
        <input type="file" name="base_image" class="admin-input" accept="image/*" id="rtBaseInput" required/>
      </div>
      <div>
        <label class="admin-label">Shadow/Lighting Layer (optional, grayscale)</label>
        <input type="file" name="shadow_layer" class="admin-input" accept="image/*"/>
        <p style="font-size:11px;color:var(--text3);margin-top:4px;">A grayscale photo of just the surface's shadows — multiplied over the texture for realism. Leave blank to skip.</p>
      </div>
    </div>

    <div style="margin-top:14px;">
  <label class="admin-label">Step 1: Click 4 corners for perspective (TL → TR → BR → BL)</label>
  <div id="rtCanvasWrap">
    <canvas id="rtCanvas"></canvas>
  </div>
  <p class="rt-point-list" id="rtPointList">No points yet.</p>
  <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="rtResetPoints()">Reset Corners</button>
</div>

<div style="margin-top:18px;" id="rtClipSection" style="display:none;">
  <label class="admin-label">
    Step 2 (optional): Click outline of exact visible shape
    <span style="font-weight:400;color:var(--text3);">— for irregular surfaces (L-shape, curved edge). Skip to use the 4-corner quad as-is.</span>
  </label>
  <p class="rt-point-list" id="rtClipPointList">No clip points yet.</p>
  <div style="display:flex;gap:8px;margin-top:8px;">
    <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="rtStartClipMode()">Start Drawing Outline</button>
    <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="rtFinishClip()">Finish Outline</button>
    <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="rtResetClip()">Clear Outline</button>
  </div>
</div>

<input type="hidden" name="clip_points" id="rtClipPoints" value=""/>
    <div style="margin-top:16px;display:flex;gap:10px;">
      <button type="submit" class="btn-admin-primary"><?= icon('check',14) ?> Save Template</button>
      <button type="button" class="btn-admin-secondary" onclick="document.getElementById('rtCreateForm').style.display='none'">Cancel</button>
    </div>
  </form>
</div>

<!-- Existing templates -->
<div class="rt-grid">
  <?php foreach ($templates as $t): ?>
  <div class="rt-card">
    <?php if ($t['base_image']): ?>
    <img class="rt-thumb" src="<?= h(ROOM_TEMPLATES_URL.'/'.$t['base_image']) ?>" alt=""/>
    <?php endif; ?>
    <p style="font-weight:700;font-size:14px;"><?= h($t['label']) ?></p>
    <p style="font-size:12px;color:var(--text3);margin-bottom:8px;">
      <?= h(ROOM_TYPES[$t['room_type']] ?? $t['room_type']) ?> ·
      <?= $t['is_active'] ? '<span style="color:var(--success);">Active</span>' : '<span style="color:var(--danger);">Inactive (needs setup)</span>' ?>
    </p>
    <div style="display:flex;gap:8px;">
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="toggle_room_template"/>
        <input type="hidden" name="template_id" value="<?= $t['id'] ?>"/>
        <input type="hidden" name="is_active" value="<?= $t['is_active'] ? 0 : 1 ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= $t['is_active'] ? 'Deactivate' : 'Activate' ?></button>
      </form>
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="delete_room_template"/>
        <input type="hidden" name="template_id" value="<?= $t['id'] ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete this template?"><?= icon('trash',13) ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
(function(){
  var canvas = document.getElementById('rtCanvas');
  var ctx    = canvas.getContext('2d');
  var img    = new Image();
  var points = [];

  document.getElementById('rtBaseInput').addEventListener('change', function(e){
    var file = e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(ev){
      img.onload = function(){
        canvas.width  = img.naturalWidth;
        canvas.height = img.naturalHeight;
        redraw();
      };
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  });

  canvas.addEventListener('click', function(e){
    if (points.length >= 4) return;
    var rect  = canvas.getBoundingClientRect();
    var scaleX = canvas.width / rect.width;
    var scaleY = canvas.height / rect.height;
    var x = Math.round((e.clientX - rect.left) * scaleX);
    var y = Math.round((e.clientY - rect.top)  * scaleY);
    points.push([x, y]);
    redraw();
    updatePointList();
  });

  function redraw(){
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#B8975A';
    ctx.strokeStyle = '#B8975A';
    ctx.lineWidth = 3;
    points.forEach(function(p, i){
      ctx.beginPath();
      ctx.arc(p[0], p[1], 8, 0, Math.PI*2);
      ctx.fill();
      ctx.fillText((i+1).toString(), p[0]-3, p[1]-12);
    });
    if (points.length > 1) {
      ctx.beginPath();
      ctx.moveTo(points[0][0], points[0][1]);
      for (var i=1;i<points.length;i++) ctx.lineTo(points[i][0], points[i][1]);
      if (points.length === 4) ctx.closePath();
      ctx.stroke();
    }
    document.getElementById('rtMaskPoints').value = JSON.stringify(points);
  }

  function updatePointList(){
    document.getElementById('rtPointList').textContent = points.length
      ? points.map(function(p,i){ return (i+1)+': ('+p[0]+','+p[1]+')'; }).join('  ')
      : 'No points yet.';
  }

  window.rtResetPoints = function(){
    points = [];
    redraw();
    updatePointList();
  };

  document.getElementById('rtForm').addEventListener('submit', function(e){
    if (points.length !== 4) {
      e.preventDefault();
      alert('Please click exactly 4 corner points on the image.');
    }
  });
  var clipPoints = [];
var clipMode   = false;

window.rtStartClipMode = function () {
  if (points.length !== 4) { alert('Place all 4 perspective corners first.'); return; }
  clipMode = true;
  clipPoints = [];
  document.getElementById('rtClipSection').style.display = 'block';
  updateClipList();
};

window.rtFinishClip = function () {
  if (clipPoints.length < 3) { alert('Need at least 3 points for an outline.'); return; }
  clipMode = false;
  document.getElementById('rtClipPoints').value = JSON.stringify(clipPoints);
  redraw();
};

window.rtResetClip = function () {
  clipPoints = [];
  document.getElementById('rtClipPoints').value = '';
  clipMode = false;
  redraw();
  updateClipList();
};

// Extend existing canvas click handler
canvas.addEventListener('click', function (e) {
  var rect = canvas.getBoundingClientRect();
  var scaleX = canvas.width / rect.width;
  var scaleY = canvas.height / rect.height;
  var x = Math.round((e.clientX - rect.left) * scaleX);
  var y = Math.round((e.clientY - rect.top) * scaleY);

  if (clipMode) {
    clipPoints.push([x, y]);
    updateClipList();
    redraw();
    return;
  }
  if (points.length >= 4) return;
  points.push([x, y]);
  redraw();
  updatePointList();
  if (points.length === 4) document.getElementById('rtClipSection').style.display = 'block';
});

function updateClipList() {
  document.getElementById('rtClipPointList').textContent = clipPoints.length
    ? clipPoints.map(function (p, i) { return (i + 1) + ':(' + p[0] + ',' + p[1] + ')'; }).join('  ')
    : 'No clip points yet.';
}

// Extend redraw() to also draw clip polygon in a different color
function redraw() {
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

  // Perspective quad — gold
  ctx.fillStyle = '#B8975A'; ctx.strokeStyle = '#B8975A'; ctx.lineWidth = 3;
  points.forEach(function (p, i) {
    ctx.beginPath(); ctx.arc(p[0], p[1], 8, 0, Math.PI * 2); ctx.fill();
    ctx.fillText((i + 1).toString(), p[0] - 3, p[1] - 12);
  });
  if (points.length > 1) {
    ctx.beginPath();
    ctx.moveTo(points[0][0], points[0][1]);
    for (var i = 1; i < points.length; i++) ctx.lineTo(points[i][0], points[i][1]);
    if (points.length === 4) ctx.closePath();
    ctx.stroke();
  }

  // Clip polygon — blue, dashed
  if (clipPoints.length) {
    ctx.fillStyle = '#2C6E8A'; ctx.strokeStyle = '#2C6E8A'; ctx.lineWidth = 2;
    ctx.setLineDash([6, 4]);
    clipPoints.forEach(function (p, i) {
      ctx.beginPath(); ctx.arc(p[0], p[1], 6, 0, Math.PI * 2); ctx.fill();
    });
    ctx.beginPath();
    ctx.moveTo(clipPoints[0][0], clipPoints[0][1]);
    for (var j = 1; j < clipPoints.length; j++) ctx.lineTo(clipPoints[j][0], clipPoints[j][1]);
    if (!clipMode) ctx.closePath();
    ctx.stroke();
    ctx.setLineDash([]);
  }

  document.getElementById('rtMaskPoints').value = JSON.stringify(points);
}
})();
  
  
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>