/**
 * assets/js/room_templates.vue.js
 * Vue 3 island — CSP-safe (no runtime template compiler, no eval).
 * Uses render()/h() instead of a template string, so it works under
 * script-src CSP without 'unsafe-eval'. Requires vue.runtime.global.prod.js
 * (NOT vue.global.prod.js) to be loaded first.
 */
(function () {
  'use strict';
  const { createApp, ref, reactive, computed, nextTick, watch, h, Transition } = Vue;

  createApp({
    setup() {
      const cfg = window.RT_CONFIG || {};

      const showForm      = ref(false);
      const imgEl         = ref(null);
      const canvasEl      = ref(null);
      const wrapEl        = ref(null);
      const loupeCanvasEl = ref(null);
      const imgLoaded     = ref(false);
      const imgNatural    = reactive({ w: 0, h: 0 });

      const points     = reactive([]);
      const clipPoints = reactive([]);
      const clipMode   = ref(false);
      const dragIndex  = ref(-1);
      const dragTarget = ref(null);

      const view = reactive({ scale: 1, tx: 0, ty: 0 });
      const MIN_SCALE = 1, MAX_SCALE = 4;
      let pinchStartDist = 0, pinchStartScale = 1, panStart = null;

      const loupeVisible = ref(false);
      const loupePos = reactive({ x: 0, y: 0 });

      const maskPointsJson = computed(() => JSON.stringify(points));
      const clipPointsJson = computed(() =>
        clipMode.value || clipPoints.length < 3 ? '' : JSON.stringify(clipPoints)
      );

      function onFileChange(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
          const testImg = new Image();
          testImg.onload = async () => {
            imgNatural.w = testImg.naturalWidth;
            imgNatural.h = testImg.naturalHeight;
            points.splice(0);
            clipPoints.splice(0);
            view.scale = 1; view.tx = 0; view.ty = 0;

            // Flip this FIRST so Vue renders the <img>/<canvas> elements,
            // THEN wait a tick before touching their refs.
            imgLoaded.value = true;
            await nextTick();

            imgEl.value.src = ev.target.result;
            redraw();
          };
          testImg.src = ev.target.result;
        };
        reader.readAsDataURL(file);
      }

      function clientToCanvasCoords(clientX, clientY) {
        const canvas = canvasEl.value;
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return [
          Math.round((clientX - rect.left) * scaleX),
          Math.round((clientY - rect.top) * scaleY),
        ];
      }

      function hitTestPoint(list, x, y, radius) {
        const r = radius / view.scale;
        for (let i = 0; i < list.length; i++) {
          const dx = list[i][0] - x, dy = list[i][1] - y;
          if (Math.sqrt(dx * dx + dy * dy) <= r) return i;
        }
        return -1;
      }

      // ── Mouse ──────────────────────────────────────────────────────────
      function onCanvasMouseDown(e) {
        const [x, y] = clientToCanvasCoords(e.clientX, e.clientY);
        const clipHit = hitTestPoint(clipPoints, x, y, 14);
        if (clipHit !== -1) { dragIndex.value = clipHit; dragTarget.value = 'clip'; return; }
        const quadHit = hitTestPoint(points, x, y, 14);
        if (quadHit !== -1) { dragIndex.value = quadHit; dragTarget.value = 'quad'; return; }

        if (clipMode.value) clipPoints.push([x, y]);
        else if (points.length < 4) points.push([x, y]);
        redraw();
      }
      function onCanvasMouseMove(e) {
        if (dragIndex.value === -1) return;
        const [x, y] = clientToCanvasCoords(e.clientX, e.clientY);
        const list = dragTarget.value === 'clip' ? clipPoints : points;
        list[dragIndex.value] = [x, y];
        redraw();
      }
      function onCanvasMouseUp() {
        dragIndex.value = -1;
        dragTarget.value = null;
      }

      // ── Touch ──────────────────────────────────────────────────────────
      function touchDist(t0, t1) {
        const dx = t0.clientX - t1.clientX, dy = t0.clientY - t1.clientY;
        return Math.sqrt(dx * dx + dy * dy);
      }

      function onTouchStart(e) {
        if (e.touches.length === 2) {
          dragIndex.value = -1;
          loupeVisible.value = false;
          pinchStartDist = touchDist(e.touches[0], e.touches[1]);
          pinchStartScale = view.scale;
          e.preventDefault();
          return;
        }
        if (e.touches.length === 1) {
          const t = e.touches[0];
          const [x, y] = clientToCanvasCoords(t.clientX, t.clientY);
          const clipHit = hitTestPoint(clipPoints, x, y, 22);
          const quadHit = clipHit === -1 ? hitTestPoint(points, x, y, 22) : -1;

          if (clipHit !== -1) {
            dragIndex.value = clipHit; dragTarget.value = 'clip';
            showLoupe(t); e.preventDefault(); return;
          }
          if (quadHit !== -1) {
            dragIndex.value = quadHit; dragTarget.value = 'quad';
            showLoupe(t); e.preventDefault(); return;
          }
          if (view.scale > 1.02) {
            panStart = { x: t.clientX, y: t.clientY, tx: view.tx, ty: view.ty };
            return;
          }
          if (clipMode.value) clipPoints.push([x, y]);
          else if (points.length < 4) points.push([x, y]);
          redraw();
        }
      }

      function onTouchMove(e) {
        if (e.touches.length === 2) {
          const dist = touchDist(e.touches[0], e.touches[1]);
          view.scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, pinchStartScale * (dist / pinchStartDist)));
          e.preventDefault();
          return;
        }
        if (e.touches.length === 1) {
          const t = e.touches[0];
          if (dragIndex.value !== -1) {
            const [x, y] = clientToCanvasCoords(t.clientX, t.clientY);
            const list = dragTarget.value === 'clip' ? clipPoints : points;
            list[dragIndex.value] = [x, y];
            redraw();
            showLoupe(t);
            e.preventDefault();
            return;
          }
          if (panStart) {
            view.tx = panStart.tx + (t.clientX - panStart.x);
            view.ty = panStart.ty + (t.clientY - panStart.y);
            e.preventDefault();
          }
        }
      }

      function onTouchEnd(e) {
        if (e.touches.length === 0) {
          dragIndex.value = -1;
          dragTarget.value = null;
          panStart = null;
          loupeVisible.value = false;
        }
      }

      function showLoupe(touch) {
        loupeVisible.value = true;
        const wrapRect = wrapEl.value.getBoundingClientRect();
        loupePos.x = touch.clientX - wrapRect.left;
        loupePos.y = touch.clientY - wrapRect.top;

        nextTick(() => {
          const loupe = loupeCanvasEl.value;
          if (!loupe) return;
          const lctx = loupe.getContext('2d');
          const [cx, cy] = clientToCanvasCoords(touch.clientX, touch.clientY);
          const cropSize = 120, zoomFactor = 2.4;

          lctx.clearRect(0, 0, loupe.width, loupe.height);
          lctx.imageSmoothingEnabled = true;
          lctx.drawImage(
            canvasEl.value,
            cx - cropSize / 2, cy - cropSize / 2, cropSize, cropSize,
            0, 0, loupe.width, loupe.height
          );
          lctx.strokeStyle = '#B8975A';
          lctx.lineWidth = 2;
          const c = loupe.width / 2;
          lctx.beginPath();
          lctx.moveTo(c - 10, c); lctx.lineTo(c + 10, c);
          lctx.moveTo(c, c - 10); lctx.lineTo(c, c + 10);
          lctx.stroke();
          lctx.beginPath();
          lctx.arc(c, c, 3, 0, Math.PI * 2);
          lctx.fillStyle = '#B8975A';
          lctx.fill();
        });
      }

      function resetZoom() { view.scale = 1; view.tx = 0; view.ty = 0; }
      function resetQuad() { points.splice(0); redraw(); }
      function resetClip() { clipPoints.splice(0); clipMode.value = false; redraw(); }
      function startClipMode() {
        if (points.length !== 4) { alert('Place all 4 perspective corners first.'); return; }
        clipMode.value = true;
        clipPoints.splice(0);
      }
      function finishClip() {
        if (clipPoints.length < 3) { alert('Need at least 3 points for an outline.'); return; }
        clipMode.value = false;
        redraw();
      }

      function redraw() {
        const canvas = canvasEl.value;
        if (!canvas || !imgLoaded.value) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(imgEl.value, 0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#B8975A'; ctx.strokeStyle = '#B8975A'; ctx.lineWidth = 3;
        points.forEach((p, i) => {
          ctx.beginPath(); ctx.arc(p[0], p[1], 9, 0, Math.PI * 2); ctx.fill();
          ctx.fillStyle = '#fff'; ctx.font = 'bold 11px sans-serif';
          ctx.fillText(String(i + 1), p[0] - 3, p[1] + 4);
          ctx.fillStyle = '#B8975A';
        });
        if (points.length > 1) {
          ctx.beginPath(); ctx.moveTo(points[0][0], points[0][1]);
          for (let i = 1; i < points.length; i++) ctx.lineTo(points[i][0], points[i][1]);
          if (points.length === 4) ctx.closePath();
          ctx.stroke();
        }

        if (clipPoints.length) {
          ctx.fillStyle = '#2C6E8A'; ctx.strokeStyle = '#2C6E8A'; ctx.lineWidth = 2;
          ctx.setLineDash([6, 4]);
          clipPoints.forEach((p) => { ctx.beginPath(); ctx.arc(p[0], p[1], 7, 0, Math.PI * 2); ctx.fill(); });
          ctx.beginPath(); ctx.moveTo(clipPoints[0][0], clipPoints[0][1]);
          for (let i = 1; i < clipPoints.length; i++) ctx.lineTo(clipPoints[i][0], clipPoints[i][1]);
          if (!clipMode.value) ctx.closePath();
          ctx.stroke();
          ctx.setLineDash([]);
        }
      }

      watch(imgLoaded, async (loaded) => {
        if (!loaded) return;
        await nextTick();
        canvasEl.value.width = imgNatural.w;
        canvasEl.value.height = imgNatural.h;
        redraw();
      });

      function validateBeforeSubmit(e) {
        if (points.length !== 4) {
          e.preventDefault();
          alert('Please place exactly 4 perspective corner points.');
        }
      }

      const roomTypes = cfg.roomTypes || {};
      const csrfToken = cfg.csrfToken || '';

      // ══════════════════════════════════════════════════════════════════
      // RENDER — plain h() calls, no template compiler, no eval, CSP-safe
      // ══════════════════════════════════════════════════════════════════
      function render() {
        const canvasStyle = {
          width: '100%', display: 'block', cursor: 'crosshair',
          transform: `translate(${view.tx}px, ${view.ty}px) scale(${view.scale})`,
          transformOrigin: '0 0',
          touchAction: 'none',
        };

        const formPanel = showForm.value
          ? h('div', { class: 'admin-form-section' }, [
              h('p', { class: 'admin-form-section-title' }, 'New Room Template'),
              h('form', {
                method: 'POST', action: 'index.php', enctype: 'multipart/form-data',
                onSubmit: validateBeforeSubmit,
              }, [
                h('input', { type: 'hidden', name: 'action', value: 'save_room_template' }),
                h('input', { type: 'hidden', name: 'mask_points', value: maskPointsJson.value }),
                h('input', { type: 'hidden', name: 'clip_points', value: clipPointsJson.value }),
                h('input', { type: 'hidden', name: 'csrf_token', value: csrfToken }),

                h('div', { class: 'admin-form-grid' }, [
                  h('div', [
                    h('label', { class: 'admin-label' }, 'Room Type'),
                    h('select', { name: 'room_type', class: 'admin-input admin-select' },
                      Object.keys(roomTypes).map((key) =>
                        h('option', { value: key, key }, roomTypes[key])
                      )
                    ),
                  ]),
                  h('div', [
                    h('label', { class: 'admin-label' }, 'Label'),
                    h('input', {
                      type: 'text', name: 'label', class: 'admin-input',
                      placeholder: 'e.g. Modern Kitchen Floor', required: true,
                    }),
                  ]),
                  h('div', [
                    h('label', { class: 'admin-label' }, 'Base Room Photo *'),
                    h('input', {
                      type: 'file', name: 'base_image', class: 'admin-input',
                      accept: 'image/*', required: true, onChange: onFileChange,
                    }),
                  ]),
                  h('div', [
                    h('label', { class: 'admin-label' }, 'Shadow/Lighting Layer (optional)'),
                    h('input', { type: 'file', name: 'shadow_layer', class: 'admin-input', accept: 'image/*' }),
                  ]),
                ]),

                imgLoaded.value ? h('div', { style: 'margin-top:14px;' }, [
                  h('div', { style: 'display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;' }, [
                    h('label', { class: 'admin-label', style: 'margin:0;' },
                      clipMode.value ? 'Drawing outline — tap to add points' : 'Step 1: Tap 4 corners (drag to fine-tune)'
                    ),
                    h('div', { style: 'display:flex;gap:6px;align-items:center;' }, [
                      h('span', { style: 'font-size:11px;color:var(--text3);' }, 'Pinch to zoom · drag to pan'),
                      view.scale > 1.02
                        ? h('button', {
                            type: 'button', class: 'btn-admin-secondary btn-admin-sm', onClick: resetZoom,
                          }, 'Reset Zoom')
                        : null,
                    ]),
                  ]),

                  h('div', {
                    ref: wrapEl,
                    style: 'position:relative;border:1px dashed var(--border);border-radius:8px;overflow:hidden;max-width:560px;touch-action:none;background:#111;',
                  }, [
                    h('img', { ref: imgEl, style: 'display:none;' }),
                    h('canvas', {
                      ref: canvasEl,
                      style: canvasStyle,
                      onMousedown: onCanvasMouseDown,
                      onMousemove: onCanvasMouseMove,
                      onMouseup: onCanvasMouseUp,
                      onMouseleave: onCanvasMouseUp,
                      onTouchstart: onTouchStart,
                      onTouchmove: onTouchMove,
                      onTouchend: onTouchEnd,
                      onTouchcancel: onTouchEnd,
                    }),
                    loupeVisible.value
                      ? h('div', {
                          class: 'rt-loupe',
                          style: `left:${loupePos.x - 55}px;top:${loupePos.y - 150}px;`,
                        }, [
                          h('canvas', { ref: loupeCanvasEl, width: 110, height: 110 }),
                        ])
                      : null,
                  ]),

                  h('p', { style: 'font-size:11px;color:var(--text3);margin-top:6px;font-family:monospace;' }, [
                    `Quad: ${points.length}/4 points`,
                    clipPoints.length ? ` · Outline: ${clipPoints.length} points` : '',
                    view.scale > 1.02 ? ` · Zoom: ${view.scale.toFixed(1)}×` : '',
                  ].join('')),

                  h('div', { style: 'display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;' }, [
                    h('button', { type: 'button', class: 'btn-admin-secondary btn-admin-sm', onClick: resetQuad }, 'Reset Corners'),
                    (points.length === 4 && !clipMode.value)
                      ? h('button', { type: 'button', class: 'btn-admin-secondary btn-admin-sm', onClick: startClipMode }, 'Draw Irregular Outline')
                      : null,
                    clipMode.value
                      ? h('button', { type: 'button', class: 'btn-admin-secondary btn-admin-sm', onClick: finishClip }, 'Finish Outline')
                      : null,
                    clipPoints.length
                      ? h('button', { type: 'button', class: 'btn-admin-secondary btn-admin-sm', onClick: resetClip }, 'Clear Outline')
                      : null,
                  ]),
                ]) : null,

                h('div', { style: 'margin-top:16px;display:flex;gap:10px;' }, [
                  h('button', { type: 'submit', class: 'btn-admin-primary' }, 'Save Template'),
                  h('button', {
                    type: 'button', class: 'btn-admin-secondary',
                    onClick: () => { showForm.value = false; },
                  }, 'Cancel'),
                ]),
              ]),
            ])
          : null;

        return h('div', [
          h('button', {
            type: 'button', class: 'btn-admin-primary', style: 'margin-bottom:16px;',
            onClick: () => { showForm.value = !showForm.value; },
          }, showForm.value ? 'Close Form' : '+ Add Room Template'),

          h(Transition, { name: 'rv-fade' }, () => formPanel),
        ]);
      }

       return render; // setup() returning a function = this IS the render function
    },
  }).mount('#rtVueApp');
})();