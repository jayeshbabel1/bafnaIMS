
// ── Toast dismiss ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const t = document.getElementById('admin-toast');
  if (t) setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity .4s'; }, 3500);

  // Confirm deletes
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  // Color preview sync
  document.querySelectorAll('.color-sync-input').forEach(inp => {
    const prev = document.getElementById('prev_' + inp.name.replace('--',''));
    if (prev) {
      prev.style.background = inp.value;
      inp.addEventListener('input', () => prev.style.background = inp.value);
    }
    const picker = inp.closest('.color-swatch-item')?.querySelector('.color-preview');
    if (picker) {
      picker.style.background = inp.value;
      inp.addEventListener('input', () => picker.style.background = inp.value);
      picker.addEventListener('click', () => {
        const ci = document.createElement('input');
        ci.type='color'; ci.value=inp.value.startsWith('#') ? inp.value : '#2c6e8a';
        ci.addEventListener('input', () => { inp.value=ci.value; picker.style.background=ci.value; });
        ci.click();
      });
    }
  });

  // Palette inputs
  document.querySelectorAll('.pal-input').forEach(inp => {
    inp.addEventListener('input', updatePalettePreview);
  });
  updatePalettePreview();

  // Photo upload preview
  const photoInput = document.getElementById('photoUpload');
  if (photoInput) {
    photoInput.addEventListener('change', function() {
      const grid = document.getElementById('newPhotoGrid');
      if (!grid) return;
      grid.innerHTML = '';
      Array.from(this.files).forEach(f => {
        const url = URL.createObjectURL(f);
        const d = document.createElement('div');
        d.className = 'photo-grid-item';
        d.innerHTML = `<img src="${url}" alt=""/>`;
        grid.appendChild(d);
      });
    });
  }

  // Drag-over on upload zones
  document.querySelectorAll('.upload-zone').forEach(zone => {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
      e.preventDefault(); zone.classList.remove('drag-over');
      const input = zone.querySelector('input[type=file]');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });
    zone.addEventListener('click', () => zone.querySelector('input[type=file]')?.click());
  });
});

function updatePalettePreview() {
  const inputs = document.querySelectorAll('.pal-input');
  const preview = document.getElementById('palettePreview');
  if (!preview || !inputs.length) return;
  preview.innerHTML = '';
  inputs.forEach(inp => {
    const d = document.createElement('div');
    d.className = 'pal-dot';
    d.style.background = '#' + (inp.value.replace('#','') || 'ccc');
    preview.appendChild(d);
  });
}

// Palette string helper for hidden input
function syncPalette() {
  const inputs = Array.from(document.querySelectorAll('.pal-input'));
  const val = JSON.stringify(inputs.map(i => i.value.replace('#','').toUpperCase()));
  const hidden = document.getElementById('paletteHidden');
  if (hidden) hidden.value = val;
}
