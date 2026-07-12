/**
 * assets/js/room_visualizer.vue.js
 * Vue 3 island — CSP-safe (render()/h(), no template compiler, no eval).
 * Requires vue.runtime.global.prod.js loaded first.
 */
(function () {
  'use strict';
  const { createApp, ref, reactive, computed, h, Transition, TransitionGroup } = Vue;

  createApp({
    setup() {
      const cfg = window.RV_CONFIG || {};
      const templatesByType = cfg.templatesByType || {};
      const roomTypes       = Object.keys(templatesByType);
      const roomTypeLabels  = cfg.roomTypeLabels || {};

      const activeType       = ref(roomTypes[0] || '');
      const selectedTemplate = ref(null);
      const generating       = ref(false);
      const resultUrl        = ref('');
      const resultVisible    = ref(false);
      const errorMsg         = ref('');
      const history          = reactive(cfg.history || []);
      const lightbox         = ref(false);

      const scenesForActiveType = computed(() => templatesByType[activeType.value] || []);

      function pickType(type) {
        activeType.value = type;
        selectedTemplate.value = null;
      }
      function pickScene(tpl) {
        selectedTemplate.value = tpl;
        errorMsg.value = '';
      }

      async function generate() {
        if (!selectedTemplate.value) return;
        generating.value = true;
        errorMsg.value = '';
        resultVisible.value = true;

        try {
          const body = new URLSearchParams({
            action: 'generate_room_preview',
            product_id: cfg.productId,
            template_id: selectedTemplate.value.id,
            csrf_token: cfg.csrfToken,
          });
          const r = await fetch('index.php', { method: 'POST', body });
          const d = await r.json();
          if (!d.success) throw new Error(d.error || 'Generation failed.');

          resultUrl.value = d.url + '?t=' + Date.now();
          history.unshift({
            id: d.id,
            result_image: d.url,
            storage_driver: 'local',
            room_label: selectedTemplate.value.label,
          });
        } catch (e) {
          errorMsg.value = e.message || 'Something went wrong generating the preview.';
          resultVisible.value = false;
        } finally {
          generating.value = false;
        }
      }

      async function deleteHistoryItem(id) {
        if (!confirm('Delete this render?')) return;
        const body = new URLSearchParams({
          action: 'delete_room_visualization',
          id,
          csrf_token: cfg.csrfToken,
        });
        await fetch('index.php', { method: 'POST', body });
        const idx = history.findIndex((hh) => hh.id === id);
        if (idx !== -1) history.splice(idx, 1);
      }

      function render() {
        const slabCard = h('div', { class: 'rv-slab-card' }, [
          h('p', { class: 'rv-section-label' }, 'Selected Slab'),
          h('div', { class: 'rv-slab-thumb' }, [
            cfg.productPhoto ? h('img', { src: cfg.productPhoto, alt: '' }) : null,
          ]),
          h('p', { class: 'rv-slab-name' }, cfg.productName || ''),
          h('p', { class: 'rv-slab-meta' }, `Lot ${cfg.productQuarry || ''}`),
        ]);

        const roomTabs = h('div', { class: 'rv-room-tabs' },
          roomTypes.map((type) =>
            h('button', {
              type: 'button', key: type,
              class: ['rv-room-tab', { active: activeType.value === type }],
              onClick: () => pickType(type),
            }, roomTypeLabels[type] || type)
          )
        );

        const sceneGrid = h(TransitionGroup, { name: 'rv-fade', tag: 'div', class: 'rv-scene-grid' }, () =>
          scenesForActiveType.value.map((tpl) =>
            h('div', {
              key: tpl.id,
              class: ['rv-scene-item', { selected: selectedTemplate.value && selectedTemplate.value.id === tpl.id }],
              onClick: () => pickScene(tpl),
            }, [
              h('img', { src: tpl.base_url, alt: tpl.label }),
              h('p', {}, tpl.label),
            ])
          )
        );

        const genBtn = h('button', {
          type: 'button',
          class: 'btn btn-gold btn-block btn-lg rv-generate-btn',
          disabled: !selectedTemplate.value || generating.value,
          onClick: generate,
        }, [
          generating.value ? h('span', { class: 'rv-spin' }) : null,
          generating.value ? 'Generating…' : (selectedTemplate.value ? 'Generate Preview' : 'Select a scene to generate'),
        ]);

        const errorBlock = errorMsg.value ? h('p', { class: 'rv-error' }, errorMsg.value) : null;

        const resultBlock = h(Transition, { name: 'rv-fade' }, () =>
          resultVisible.value ? h('div', { class: 'rv-result-wrap' }, [
            h('p', { class: 'rv-section-label' }, 'Preview'),
            h('div', {
              class: 'rv-result-frame',
              onClick: () => { if (resultUrl.value && !generating.value) lightbox.value = true; },
            }, [
              resultUrl.value ? h('img', {
                src: resultUrl.value, alt: 'Room preview',
                class: { 'rv-loading-img': generating.value },
              }) : null,
              generating.value ? h('div', { class: 'rv-result-loader' }, [
                h('div', { class: 'loader-spinner' }),
              ]) : null,
            ]),
            h('div', { style: 'display:flex;gap:10px;margin-top:14px;' }, [
              h('button', {
                type: 'button', class: 'btn btn-secondary', style: 'flex:1;',
                disabled: generating.value, onClick: generate,
              }, 'Regenerate'),
              h('a', {
                href: resultUrl.value, download: true,
                class: 'btn btn-primary', style: 'flex:1;text-decoration:none;text-align:center;',
              }, 'Download'),
            ]),
          ]) : null
        );

        const mainCard = h('div', { class: 'rv-main-card' }, [
          h('p', { class: 'rv-section-label' }, '1. Choose a Room Type'),
          roomTabs,
          h('p', { class: 'rv-section-label', style: 'margin-top:18px;' }, '2. Choose a Scene'),
          sceneGrid,
          genBtn,
          errorBlock,
          resultBlock,
        ]);

        const historyBlock = history.length ? h('div', { style: 'margin-top:32px;' }, [
          h('p', { class: 'rv-section-label' }, 'Previous Renders'),
          h(TransitionGroup, { name: 'rv-fade', tag: 'div', class: 'rv-history-grid' }, () =>
            history.map((hItem) =>
              h('div', { class: 'rv-history-item', key: hItem.id }, [
                h('img', {
                  src: hItem.result_image.startsWith('http')
                    ? hItem.result_image
                    : ('storage/room_previews/' + hItem.result_image),
                  alt: '',
                }),
                h('p', {}, hItem.room_label),
                h('button', {
                  type: 'button', class: 'rv-history-delete',
                  onClick: () => deleteHistoryItem(hItem.id),
                }, '×'),
              ])
            )
          ),
        ]) : null;

        const lightboxBlock = h(Transition, { name: 'rv-fade' }, () =>
          lightbox.value ? h('div', {
            class: 'rv-lightbox',
            onClick: () => { lightbox.value = false; },
          }, [
            h('img', { src: resultUrl.value, alt: '' }),
          ]) : null
        );

        return h('div', [
          h('div', { class: 'rv-vue-layout' }, [slabCard, mainCard]),
          historyBlock,
          lightboxBlock,
        ]);
      }

      return render;
    },
  }).mount('#rvApp');
})();