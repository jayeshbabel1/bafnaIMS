/**
 * assets/js/room_visualizer_three.js
 * ─────────────────────────────────────────────────────────────────────────
 * 3D Room Visualizer — modular engine, v2.
 *
 * PUBLIC API (unchanged from v1 — nothing that calls this file needs to change):
 *   window.RoomVisualizer3D(containerId, opts)
 *   window.RV3D_mount(containerId, controlsWrapId, opts)
 *   window.RV3D_ROOM_LABELS
 *   window['rv3d_setRoom_'+id](roomKey)
 *   window['rv3d_setSurface_'+id](surfaceKey)
 *   window['rv3d_getSurfaces_'+id]()
 *   window['rv3d_getRoomLabel'](key)            ← global, no id suffix
 *   window['rv3d_getSurfaceLabel'](key)         ← global, no id suffix
 *   window['rv3d_setQuality_'+id](level)
 *   window['rv3d_toggleDayNight_'+id]()
 *   window['rv3d_toggleBeforeAfter_'+id]()
 *   window['rv3d_toggleAutoRotate_'+id]()
 *   window['rv3d_resetCamera_'+id]()
 *   window['rv3d_zoom_'+id](steps)
 *   window['rv3d_fullscreen_'+id]()
 *   window['rv3d_setThickness_'+id](mm)
 *   window['rv3d_setEdgeProfile_'+id](profile)
 *   window['rv3d_setSlabRotation_'+id](deg)
 *   window['rv3d_setIsland_'+id](bool)
 *   window['rv3d_supportsCountertopControls_'+id]()
 *   window['rv3d_snapshot_'+id]()
 *   window['rv3d_highResSnapshot_'+id](scale)
 *
 * ARCHITECTURE (internal — not part of the public contract, may evolve):
 *
 *   MaterialManager  — one instance per viewer. Caches materials by key so
 *                       every cabinet door across every room shares ONE
 *                       material instance, every "counter"-tagged mesh
 *                       (kitchen run A + run B + island) shares ONE
 *                       MeshPhysicalMaterial so texture application is a
 *                       single assignment instead of a per-mesh loop.
 *
 *   GeometryCache    — same idea for repeated primitives (handles, burner
 *                       rings, balusters…) keyed by their dimensions.
 *
 *   LayoutEngine      — created per room from {width, height, depth,
 *                       wallThickness}. Every placement in every room goes
 *                       through it (alongBackWall / alongSideWall / lShape /
 *                       atOffset / roomCenter) instead of hand-picked
 *                       coordinates, so wall thickness, cabinet depth and
 *                       countertop thickness genuinely drive the geometry.
 *                       Objects placed this way must follow one convention:
 *                       their *attachment* face is local +Z; the engine
 *                       works out the correct position + rotationY so that
 *                       face sits flush against the target wall.
 *
 *   centerBottom(group) — recenters a group's contents around its own
 *                       bounding box (X/Z centered, Y bottom-anchored) so
 *                       callers position composite furniture with a single
 *                       `.position.set()` / `.rotation.y=` — no internal
 *                       magic numbers leak out of a builder.
 *
 *   Builders.*        — RoomShell, CabinetRun, Countertop, Island, Vanity,
 *                       ReceptionDesk, Staircase, appliances.sink/.faucet/
 *                       .stove, plus small decor helpers (plant, wallArt,
 *                       curtains, floorLamp, pendantLight, rug). CabinetRun/
 *                       Island/Vanity/ReceptionDesk attach their Countertop
 *                       as a scenegraph CHILD (`cabinetGroup.add(countertop)`)
 *                       and any requested appliance is attached as a CHILD
 *                       of that countertop mesh (`countertop.add(sink)`) —
 *                       real parent/child nesting, not just visual stacking.
 *
 *   ROOM_BUILDERS[key] — one function per room; every one of them starts by
 *                       creating a LayoutEngine for its own dimensions and
 *                       calling Builders.RoomShell with it, so all 8 rooms
 *                       (kitchen, bathroom, living_room, bedroom, staircase,
 *                       reception, hall, dining) are constructed through the
 *                       identical shell + layout machinery.
 *
 * Known simplifications (kept from v1, still true here): the "ogee" edge
 * profile is a stylised bezier approximation, not a true compound ogee; and
 * ambient occlusion is a single baked AO map derived from the slab photo's
 * own contrast, not real-time SSAO. Both are fast, dependency-free and read
 * correctly at countertop viewing distance.
 * ─────────────────────────────────────────────────────────────────────────
 */
(function () {
  'use strict';

  // ═══════════════════════════════════════════════════════════════════════
  // Registries
  // ═══════════════════════════════════════════════════════════════════════
  var ROOM_LABELS = {
    kitchen: 'Kitchen', bathroom: 'Bathroom', living_room: 'Living Room',
    bedroom: 'Bedroom', staircase: 'Staircase', reception: 'Reception',
    hall: 'Hall', dining: 'Dining Room', drawing: 'Living Room',
  };
  var SURFACE_LABELS = {
    floor: 'Floor', wall: 'Back Wall', sidewall: 'Side Wall',
    counter: 'Countertop', backsplash: 'Backsplash',
    desk: 'Reception Desk', tread: 'Stair Treads', vanity: 'Vanity Top',
  };
  var ROOM_ORDER = ['kitchen', 'bathroom', 'living_room', 'bedroom', 'staircase', 'reception'];

  var QUALITY = {
    low:    { shadowMap: 512,  pixelRatio: 1.0, envRes: 32,  texSize: 256, aa: false },
    medium: { shadowMap: 1024, pixelRatio: 1.5, envRes: 64,  texSize: 384, aa: true },
    high:   { shadowMap: 2048, pixelRatio: 2.0, envRes: 128, texSize: 512, aa: true },
    ultra:  { shadowMap: 4096, pixelRatio: 2.0, envRes: 256, texSize: 768, aa: true },
  };

  // ═══════════════════════════════════════════════════════════════════════
  // Color science — dynamic wall / ceiling / floor harmony from the slab
  // palette that is already stored per-product (no pixel sampling needed).
  // ═══════════════════════════════════════════════════════════════════════
  function hexToRgb01(hex) {
    hex = String(hex || 'F2F0EC').replace('#', '');
    if (hex.length === 3) hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    var n = parseInt(hex, 16); if (isNaN(n)) n = 0xF2F0EC;
    return { r: ((n >> 16) & 255) / 255, g: ((n >> 8) & 255) / 255, b: (n & 255) / 255 };
  }
  function rgbToHsl(r, g, b) {
    var max = Math.max(r, g, b), min = Math.min(r, g, b);
    var h = 0, s = 0, l = (max + min) / 2;
    if (max !== min) {
      var d = max - min;
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
      else if (max === g) h = (b - r) / d + 2;
      else h = (r - g) / d + 4;
      h /= 6;
    }
    return { h: h, s: s, l: l };
  }
  function pickRoomPalette(paletteHexArr) {
    var base = (paletteHexArr && paletteHexArr[0]) ? paletteHexArr[0] : 'F2F0EC';
    var c = hexToRgb01(base);
    var hsl = rgbToHsl(c.r, c.g, c.b);
    var L = hsl.l, S = hsl.s;
    var warmHue = (hsl.h < 0.14 || hsl.h > 0.85) || (hsl.h > 0.05 && hsl.h < 0.20);
    var wall, floorBase;
    if (S < 0.07) { wall = '#DCD8D0'; floorBase = L > 0.5 ? '#EDE9E1' : '#D9D4C9'; }
    else if (L < 0.32) { wall = '#F3ECDD'; floorBase = '#E6DECE'; }
    else if (L >= 0.32 && L < 0.66 && warmHue) { wall = '#F5EEDA'; floorBase = '#EAE1CB'; }
    else if (L >= 0.66) { wall = '#DAD5CB'; floorBase = '#EDE9E1'; }
    else { wall = '#EDE8DD'; floorBase = '#E4DFD2'; }
    return { wall: wall, ceiling: '#FAF8F4', floorBase: floorBase };
  }

  // ═══════════════════════════════════════════════════════════════════════
  // Material Manager — every material used anywhere is created at most once
  // per viewer instance and looked up by key thereafter.
  // ═══════════════════════════════════════════════════════════════════════
  function createMaterialManager() {
    var cache = {};
    return {
      standard: function (key, color, rough, metal) {
        if (!cache[key]) cache[key] = new THREE.MeshStandardMaterial({ color: color, roughness: rough != null ? rough : 0.8, metalness: metal || 0 });
        return cache[key];
      },
      physical: function (key, params) {
        if (!cache[key]) cache[key] = new THREE.MeshPhysicalMaterial(params);
        return cache[key];
      },
      basic: function (key, params) {
        if (!cache[key]) cache[key] = new THREE.MeshBasicMaterial(params);
        return cache[key];
      },
      // One shared, texturable PBR material per surface key (floor/counter/
      // vanity/desk/tread/backsplash/wall…). All meshes tagged with the same
      // surface key point at the SAME material object, so applying/removing
      // the slab texture is a single assignment, not a per-mesh loop.
      surface: function (key) {
        var ck = 'surface:' + key;
        if (!cache[ck]) {
          cache[ck] = new THREE.MeshPhysicalMaterial({
            color: 0xffffff, roughness: 0.34, metalness: 0.02,
            clearcoat: 0.5, clearcoatRoughness: 0.16, reflectivity: 0.45,
            envMapIntensity: 1.0,
          });
        }
        return cache[ck];
      },
      get: function (key, factory) { if (!cache[key]) cache[key] = factory(); return cache[key]; },
      dispose: function () {
        Object.keys(cache).forEach(function (k) { if (cache[k].dispose) cache[k].dispose(); });
        cache = {};
      },
    };
  }

  function createGeometryCache() {
    var cache = {};
    return {
      get: function (key, factory) { if (!cache[key]) cache[key] = factory(); return cache[key]; },
      dispose: function () {
        Object.keys(cache).forEach(function (k) { cache[k].dispose(); });
        cache = {};
      },
    };
  }

  function ensureUv2(geometry) {
    if (geometry.attributes.uv && !geometry.attributes.uv2) {
      geometry.setAttribute('uv2', new THREE.BufferAttribute(geometry.attributes.uv.array, 2));
    }
  }

  // Recenters a group's CURRENT children around their own bounding box
  // (X/Z centered, Y bottom-anchored) by wrapping them in an inner group and
  // offsetting that inner group — never the individual meshes. This is the
  // single mechanism composite builders use instead of hand-picked
  // translation constants; callers of a builder only ever set `.position`
  // and `.rotation.y` on the group it returns.
  function centerBottom(group) {
    var box = new THREE.Box3().setFromObject(group);
    if (!isFinite(box.min.x) || group.children.length === 0) return group;
    var center = box.getCenter(new THREE.Vector3());
    var inner = new THREE.Group();
    var kids = group.children.slice();
    kids.forEach(function (c) { inner.add(c); });
    inner.position.set(-center.x, -box.min.y, -center.z);
    group.add(inner);
    return group;
  }

  // ═══════════════════════════════════════════════════════════════════════
  // Layout Engine — every position in every room is derived from these
  // methods; nothing downstream hand-writes `-d/2 + 0.3` style constants.
  //
  // Convention: any object placed via alongBackWall/alongSideWall must have
  // its "attachment face" (the face that should touch the wall) at local
  // +Z, and be symmetric about local X=0. The engine works out the correct
  // rotationY (Math.PI for the back wall, -Math.PI/2 for the side wall) so
  // that face lands flush against the wall's inner surface, and the correct
  // position so the requested center-line lands where asked.
  // ═══════════════════════════════════════════════════════════════════════
  function createLayoutEngine(dims) {
    var width = dims.width, height = dims.height, depth = dims.depth;
    var wt = dims.wallThickness != null ? dims.wallThickness : 0.1;

    return {
      width: width, height: height, depth: depth, wallThickness: wt,

      innerBackZ: function () { return -depth / 2; },
      innerSideX: function () { return -width / 2; },

      wallBoxes: function () {
        return {
          back: { size: [width, height, wt], position: [0, height / 2, -depth / 2 - wt / 2] },
          side: { size: [wt, height, depth], position: [-width / 2 - wt / 2, height / 2, 0] },
        };
      },

      // centerX = world X of the placed object's own centerline.
      alongBackWall: function (objDepth, centerX) {
        return {
          position: new THREE.Vector3(centerX, 0, -depth / 2 + wt + objDepth / 2),
          rotationY: Math.PI,
        };
      },
      // centerZ = world Z of the placed object's own centerline.
      alongSideWall: function (objDepth, centerZ) {
        return {
          position: new THREE.Vector3(-width / 2 + wt + objDepth / 2, 0, centerZ),
          rotationY: -Math.PI / 2,
        };
      },
      // L-shaped run: A hugs the back wall starting at the corner, B hugs
      // the side wall starting where A's footprint ends.
      lShape: function (runDepth, runALen, runBLen) {
        var a = this.alongBackWall(runDepth, -width / 2 + wt + runALen / 2);
        var b = this.alongSideWall(runDepth, -depth / 2 + wt + runDepth + runBLen / 2);
        return {
          runA: { position: a.position, rotationY: a.rotationY, length: runALen, depth: runDepth },
          runB: { position: b.position, rotationY: b.rotationY, length: runBLen, depth: runDepth },
        };
      },
      // Freestanding furniture — no wall attachment, arbitrary facing.
      atOffset: function (x, z, rotationY) {
        return { position: new THREE.Vector3(x, 0, z), rotationY: rotationY || 0 };
      },
      roomCenter: function () { return new THREE.Vector3(0, 0, 0); },
    };
  }

  function applyTransform(group, transform) {
    group.position.copy(transform.position);
    group.rotation.y = transform.rotationY || 0;
    return group;
  }

  // ═══════════════════════════════════════════════════════════════════════
  // Countertop geometry factory — real extruded cross-section, so thickness
  // and edge profile are actual geometry, not a texture trick. Local frame:
  // X = length (centered), Y = 0 at slab BOTTOM rising to +thickness at the
  // TOP surface, Z = depth with the wall-attached edge at +depth/2 and the
  // profiled front edge at -depth/2 (matches the LayoutEngine convention
  // above, since CabinetRun/Island/etc. reuse the same "+Z = attachment
  // face" rule for whatever they're built into).
  // ═══════════════════════════════════════════════════════════════════════
  function buildCountertopGeometry(length, depth, thickness, edgeProfile) {
    var t = Math.max(0.015, thickness || 0.035);
    var e = Math.min(t * 0.8, 0.02);
    var shape = new THREE.Shape();
    shape.moveTo(0, 0);
    if (edgeProfile === 'bullnose') {
      shape.lineTo(depth - e, 0);
      shape.absarc(depth - e, t / 2, t / 2, -Math.PI / 2, Math.PI / 2, false);
    } else if (edgeProfile === 'beveled') {
      shape.lineTo(depth - e, 0);
      shape.lineTo(depth, e);
      shape.lineTo(depth, t - e);
      shape.lineTo(depth - e, t);
    } else if (edgeProfile === 'ogee') {
      shape.lineTo(depth - e, 0);
      shape.bezierCurveTo(depth - e * 0.4, 0, depth, e * 0.5, depth, e);
      shape.bezierCurveTo(depth, t * 0.45, depth - e * 0.6, t * 0.55, depth - e * 0.2, t * 0.7);
      shape.bezierCurveTo(depth, t * 0.85, depth, t - e * 0.2, depth - e, t);
    } else {
      shape.lineTo(depth, 0);
      shape.lineTo(depth, t);
    }
    shape.lineTo(0, t);
    shape.lineTo(0, 0);

    var geo = new THREE.ExtrudeGeometry(shape, { depth: length, bevelEnabled: false, curveSegments: 12 });
    geo.rotateY(Math.PI / 2);                    // rawZ(length)->X, rawX(depth)->-Z
    geo.translate(-length / 2, 0, depth / 2);     // center length; wall-side edge -> Z=+depth/2

    var pos = geo.attributes.position, uv = new Float32Array(pos.count * 2);
    for (var i = 0; i < pos.count; i++) {
      uv[i * 2] = (pos.getX(i) + length / 2) / length;
      uv[i * 2 + 1] = (pos.getZ(i) + depth / 2) / depth;
    }
    geo.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
    ensureUv2(geo);
    return geo;
  }

  // ═══════════════════════════════════════════════════════════════════════
  // Small decorative builders (unchanged in spirit from v1, now sourced
  // from the shared MaterialManager/GeometryCache and bounding-box centered)
  // ═══════════════════════════════════════════════════════════════════════
  var Builders = {};
  Builders.decor = {};

  Builders.decor.contactShadow = function (mm, gc, radius, opacity) {
    radius = radius * 0.6;
    var key = 'contact-shadow-tex:' + opacity;
    var tex = mm.get(key, function () {
      var c = document.createElement('canvas'); c.width = c.height = 128;
      var ctx = c.getContext('2d');
      var g = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
      g.addColorStop(0, 'rgba(0,0,0,' + opacity + ')');
      g.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = g; ctx.fillRect(0, 0, 128, 128);
      return new THREE.CanvasTexture(c);
    });
    var mat = mm.get('contact-shadow-mat:' + opacity, function () {
      return new THREE.MeshBasicMaterial({ map: tex, transparent: true, depthWrite: false, polygonOffset: true, polygonOffsetFactor: -4, polygonOffsetUnits: -4 });
    });
    var geo = gc.get('contact-shadow-geo:' + radius.toFixed(2), function () { return new THREE.PlaneGeometry(radius, radius); });
    var m = new THREE.Mesh(geo, mat);
    m.rotation.x = -Math.PI / 2;
    m.renderOrder = 1;
    return m;
  };

  Builders.decor.pottedPlant = function (mm, gc) {
    var g = new THREE.Group();
    var pot = new THREE.Mesh(gc.get('plant-pot', function () { return new THREE.CylinderGeometry(0.16, 0.12, 0.28, 16); }), mm.standard('plant-pot', 0x8a4a3a, 0.8));
    pot.position.y = 0.14; pot.castShadow = pot.receiveShadow = true; g.add(pot);
    var leafMat = mm.standard('plant-leaf', 0x3f6b46, 0.85);
    var leafGeo = gc.get('plant-leaf', function () { return new THREE.ConeGeometry(0.08, 0.55, 6); });
    for (var i = 0; i < 6; i++) {
      var leaf = new THREE.Mesh(leafGeo, leafMat);
      var a = (i / 6) * Math.PI * 2;
      leaf.position.set(Math.cos(a) * 0.08, 0.55, Math.sin(a) * 0.08);
      leaf.rotation.z = Math.cos(a) * 0.35; leaf.rotation.x = Math.sin(a) * 0.35;
      leaf.castShadow = true; g.add(leaf);
    }
    g.add(Builders.decor.contactShadow(mm, gc, 0.7, 0.35));
    return centerBottom(g);
  };

  Builders.decor.wallArt = function (mm, gc) {
    var g = new THREE.Group();
    var frame = new THREE.Mesh(gc.get('art-frame', function () { return new THREE.BoxGeometry(0.55, 0.75, 0.03); }), mm.standard('art-frame', 0x2a2420, 0.6));
    var art = new THREE.Mesh(gc.get('art-plane', function () { return new THREE.PlaneGeometry(0.46, 0.66); }), mm.standard('art-plane', 0xC7B79A, 1));
    art.position.z = 0.016;
    g.add(frame); g.add(art);
    return g;
  };

  Builders.decor.curtainPair = function (mm, gc) {
    var g = new THREE.Group();
    var cMat = mm.standard('curtain', 0xD9CBB0, 0.95);
    cMat.side = THREE.DoubleSide;
    var rod = new THREE.Mesh(gc.get('curtain-rod', function () { return new THREE.CylinderGeometry(0.015, 0.015, 1.5, 8); }), mm.standard('curtain-rod', 0x3a3a3a, 0.4, 0.6));
    rod.rotation.z = Math.PI / 2; rod.position.y = 0.75; g.add(rod);
    var panelGeo = gc.get('curtain-panel', function () { return new THREE.CylinderGeometry(0.18, 0.18, 1.5, 8, 1, true, 0, Math.PI); });
    [-0.62, 0.62].forEach(function (off) {
      var panel = new THREE.Mesh(panelGeo, cMat);
      panel.rotation.z = Math.PI / 2; panel.rotation.y = off < 0 ? 0 : Math.PI;
      panel.position.x = off; panel.castShadow = true; g.add(panel);
    });
    return g;
  };

  Builders.decor.floorLamp = function (mm, gc) {
    var g = new THREE.Group();
    var base = new THREE.Mesh(gc.get('lamp-base', function () { return new THREE.CylinderGeometry(0.14, 0.16, 0.03, 20); }), mm.standard('lamp-base', 0x2a2420, 0.4, 0.5));
    base.position.y = 0.015; g.add(base);
    var pole = new THREE.Mesh(gc.get('lamp-pole', function () { return new THREE.CylinderGeometry(0.02, 0.02, 1.3, 8); }), mm.standard('lamp-base', 0x2a2420, 0.4, 0.5));
    pole.position.y = 0.68; g.add(pole);
    var shade = new THREE.Mesh(gc.get('lamp-shade', function () { return new THREE.ConeGeometry(0.22, 0.32, 20, 1, true); }),
      mm.physical('lamp-shade', { color: 0xF3E6C8, roughness: 0.9, side: THREE.DoubleSide, emissive: 0xF3E6C8, emissiveIntensity: 0.25 }));
    shade.position.y = 1.5; g.add(shade);
    var bulb = new THREE.PointLight(0xffdca8, 0.6, 3.2); bulb.name = 'nightLight'; bulb.position.y = 1.4; g.add(bulb);
    g.add(Builders.decor.contactShadow(mm, gc, 0.7, 0.3));
    return centerBottom(g);
  };

  Builders.decor.pendantLight = function (mm, gc, roomH) {
    var g = new THREE.Group();
    var cord = new THREE.Mesh(gc.get('pendant-cord', function () { return new THREE.CylinderGeometry(0.007, 0.007, 0.5, 6); }), mm.standard('pendant-cord', 0x1a1a1a, 0.5));
    cord.position.y = roomH - 0.25; g.add(cord);
    var shade = new THREE.Mesh(gc.get('pendant-shade', function () { return new THREE.ConeGeometry(0.16, 0.18, 20, 1, true); }),
      mm.standard('pendant-shade', 0x2a2420, 0.5, 0).clone ? mm.get('pendant-shade-mat', function () { var m = new THREE.MeshStandardMaterial({ color: 0x2a2420, roughness: 0.5, side: THREE.DoubleSide }); return m; }) : null);
    shade.rotation.x = Math.PI; shade.position.y = roomH - 0.52; g.add(shade);
    var bulb = new THREE.PointLight(0xfff0d2, 0.85, 5, 2); bulb.name = 'nightLight'; bulb.position.y = roomH - 0.58; g.add(bulb);
    return g;
  };

  Builders.decor.rug = function (mm, gc, radius, color) {
    var mat = mm.get('rug:' + color, function () {
      var m = new THREE.MeshStandardMaterial({ color: color, roughness: 1 });
      m.polygonOffset = true; m.polygonOffsetFactor = -3; m.polygonOffsetUnits = -3;
      return m;
    });
    var rug = new THREE.Mesh(gc.get('rug-geo:' + radius.toFixed(2), function () { return new THREE.CircleGeometry(radius * 0.55, 48); }), mat);
    rug.rotation.x = -Math.PI / 2; rug.position.y = 0.012; rug.receiveShadow = true; rug.renderOrder = 2;
    return rug;
  };

  Builders.decor.tileTexture = function (mm) {
    return mm.get('tile-texture', function () {
      var c = document.createElement('canvas'); c.width = c.height = 256;
      var ctx = c.getContext('2d');
      ctx.fillStyle = '#F4F1EA'; ctx.fillRect(0, 0, 256, 256);
      ctx.strokeStyle = 'rgba(0,0,0,0.12)'; ctx.lineWidth = 3;
      var tile = 64;
      for (var y = 0; y <= 256; y += tile) {
        var offset = (y / tile) % 2 === 0 ? 0 : tile / 2;
        for (var x = -tile; x <= 256 + tile; x += tile) ctx.strokeRect(x + offset, y, tile, tile);
      }
      var tex = new THREE.CanvasTexture(c);
      tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
      tex.repeat.set(3, 1.2);
      return tex;
    });
  };

  // ═══════════════════════════════════════════════════════════════════════
  // RoomShell — floor / ceiling / walls (real BoxGeometry thickness) / trim /
  // window / door. Every room builder starts here.
  // ═══════════════════════════════════════════════════════════════════════
  Builders.RoomShell = function (mm, gc, layout, opts) {
    opts = opts || {};
    var w = layout.width, h = layout.height, d = layout.depth, wt = layout.wallThickness;
    var group = new THREE.Group();

    var floorMat = mm.surface('floor');
    floorMat.color.set(opts.floorBaseColor || '#EDE9E1');
    var floorGeo = gc.get('floor-geo:' + w.toFixed(2) + ':' + d.toFixed(2), function () { return new THREE.PlaneGeometry(w, d); });
    ensureUv2(floorGeo);
    var floor = new THREE.Mesh(floorGeo, floorMat);
    floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true;
    group.add(floor);

    var ceiling = new THREE.Mesh(gc.get('ceiling-geo:' + w.toFixed(2) + ':' + d.toFixed(2), function () { return new THREE.PlaneGeometry(w, d); }),
      mm.standard('ceiling', opts.ceilingColor || 0xFAF8F4, 0.95));
    ceiling.rotation.x = Math.PI / 2; ceiling.position.y = h;
    group.add(ceiling);

    var wallMat = mm.standard('wall:' + (opts.wallColor || '#EDE8DD'), opts.wallColor || 0xEDE8DD, 0.92);
    var boxes = layout.wallBoxes();
    var backWall = new THREE.Mesh(new THREE.BoxGeometry(boxes.back.size[0], boxes.back.size[1], boxes.back.size[2]), wallMat);
    backWall.position.set(boxes.back.position[0], boxes.back.position[1], boxes.back.position[2]);
    backWall.receiveShadow = true;
    group.add(backWall);

    var sideWall = new THREE.Mesh(new THREE.BoxGeometry(boxes.side.size[0], boxes.side.size[1], boxes.side.size[2]), wallMat);
    sideWall.position.set(boxes.side.position[0], boxes.side.position[1], boxes.side.position[2]);
    sideWall.receiveShadow = true;
    group.add(sideWall);

    if (opts.trim !== false) {
      var trimMat = mm.standard('trim', 0xffffff, 0.55);
      var crown = new THREE.Mesh(new THREE.BoxGeometry(w, 0.06, 0.06), trimMat);
      crown.position.set(0, h - 0.03, -d / 2 + 0.03); group.add(crown);
      var crownSide = new THREE.Mesh(new THREE.BoxGeometry(d, 0.06, 0.06), trimMat);
      crownSide.rotation.y = Math.PI / 2; crownSide.position.set(-w / 2 + 0.03, h - 0.03, 0); group.add(crownSide);
      var bb1 = new THREE.Mesh(new THREE.BoxGeometry(w, 0.09, 0.02), trimMat);
      bb1.position.set(0, 0.045, -d / 2 + 0.01); group.add(bb1);
      var bb2 = new THREE.Mesh(new THREE.BoxGeometry(d, 0.09, 0.02), trimMat);
      bb2.rotation.y = Math.PI / 2; bb2.position.set(-w / 2 + 0.01, 0.045, 0); group.add(bb2);
    }

    if (opts.windowWall) {
      var frameMat = mm.standard('trim', 0xffffff, 0.7);
      var wy = h * 0.58;
      if (opts.windowWall === 'side') {
        var wf = new THREE.Mesh(new THREE.BoxGeometry(0.06, 1.3, 1.1), frameMat);
        wf.position.set(-w / 2 + 0.03, wy, 0); group.add(wf);
        var pane = new THREE.Mesh(new THREE.PlaneGeometry(1.1, 1.1), mm.basic('window-pane', { color: 0xfff8e6 }));
        pane.rotation.y = Math.PI / 2; pane.position.set(-w / 2 + 0.061, wy, 0); group.add(pane);
        var curtSide = Builders.decor.curtainPair(mm, gc);
        curtSide.rotation.y = Math.PI / 2; curtSide.position.set(-w / 2 + 0.12, wy, 0);
        group.add(curtSide);
      } else {
        var wx = w * 0.28;
        var wf2 = new THREE.Mesh(new THREE.BoxGeometry(1.1, 1.3, 0.06), frameMat);
        wf2.position.set(wx, wy, -d / 2 + 0.03); group.add(wf2);
        var pane2 = new THREE.Mesh(new THREE.PlaneGeometry(0.9, 1.1), mm.basic('window-pane', { color: 0xfff8e6 }));
        pane2.position.set(wx, wy, -d / 2 + 0.061); group.add(pane2);
        var mv = new THREE.Mesh(new THREE.BoxGeometry(0.03, 1.1, 0.03), frameMat); mv.position.copy(pane2.position); group.add(mv);
        var mh = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.03, 0.03), frameMat); mh.position.copy(pane2.position); group.add(mh);
        var curtBack = Builders.decor.curtainPair(mm, gc);
        curtBack.position.set(wx, wy, -d / 2 + 0.12);
        group.add(curtBack);
      }
    }

    if (opts.doorWall) {
      var doorFrameMat = mm.standard('trim', 0xffffff, 0.7);
      var doorFrame = new THREE.Mesh(new THREE.BoxGeometry(0.06, 1.9, 0.85), doorFrameMat);
      if (opts.doorWall === 'back') doorFrame.position.set(0, 0.95, -d / 2 + 0.03);
      else doorFrame.position.set(-w / 2 + 0.03, 0.95, d * 0.28);
      group.add(doorFrame);
    }

    if (opts.wallArtAt) {
      var art = Builders.decor.wallArt(mm, gc);
      applyTransform(art, layout.atOffset(opts.wallArtAt[0], opts.wallArtAt[1], opts.wallArtAt[2] || 0));
      art.position.y = opts.wallArtAt[3] != null ? opts.wallArtAt[3] : h * 0.55;
      group.add(art);
    }
    if (opts.plantAt) {
      opts.plantAt.forEach(function (p) {
        var plant = Builders.decor.pottedPlant(mm, gc);
        applyTransform(plant, layout.atOffset(p[0], p[1]));
        group.add(plant);
      });
    }
    if (opts.pendantAt) {
      opts.pendantAt.forEach(function (p) {
        var pendant = Builders.decor.pendantLight(mm, gc, h);
        applyTransform(pendant, layout.atOffset(p[0], p[1]));
        group.add(pendant);
      });
    }

    return { group: group, floor: floor, wall: backWall, sidewall: sideWall };
  };

  // ═══════════════════════════════════════════════════════════════════════
  // Countertop — standalone factory reused by every cabinet-bearing builder.
  // Returns the mesh plus an `update()` closure so thickness/edge-profile
  // controls can rebuild geometry in place without touching the material.
  // ═══════════════════════════════════════════════════════════════════════
  Builders.Countertop = function (mm, opts) {
    var length = opts.length, depth = opts.depth;
    var thicknessM = opts.thicknessM, edgeProfile = opts.edgeProfile;
    var geo = buildCountertopGeometry(length, depth, thicknessM, edgeProfile);
    var mesh = new THREE.Mesh(geo, mm.surface(opts.surfaceKey || 'counter'));
    mesh.castShadow = mesh.receiveShadow = true;
    return {
      mesh: mesh,
      update: function (newThicknessM, newEdgeProfile) {
        var newGeo = buildCountertopGeometry(length, depth, newThicknessM, newEdgeProfile);
        mesh.geometry.dispose();
        mesh.geometry = newGeo;
      },
    };
  };

  // ═══════════════════════════════════════════════════════════════════════
  // Appliances — always attached as children of a Countertop mesh, so their
  // own local coordinates are relative to the countertops local frame
  // (topY = countertop thickness = the finished top surface).
  // ═══════════════════════════════════════════════════════════════════════
  Builders.appliances = {
    faucet: function (mm, gc, opts) {
      var localX = opts.offsetX || 0, depth = opts.depth || 0.6, topY = opts.topY || 0.035;
      var g = new THREE.Group();
      var faucetMat = mm.standard('faucet-metal', 0xB9BEC4, 0.2, 0.95);
      var fz = -depth * 0.32; // toward the front (profiled) edge
      var base = new THREE.Mesh(gc.get('faucet-base', function () { return new THREE.CylinderGeometry(0.014, 0.016, 0.05, 12); }), faucetMat);
      base.position.set(localX, topY + 0.025, fz); g.add(base);
      var riser = new THREE.Mesh(gc.get('faucet-riser', function () { return new THREE.CylinderGeometry(0.011, 0.011, 0.22, 10); }), faucetMat);
      riser.position.set(localX, topY + 0.13, fz); g.add(riser);
      var arc = new THREE.Mesh(gc.get('faucet-arc', function () { return new THREE.TorusGeometry(0.09, 0.011, 8, 16, Math.PI); }), faucetMat);
      arc.rotation.z = Math.PI; arc.position.set(localX, topY + 0.24, fz + 0.09); g.add(arc);
      var spout = new THREE.Mesh(gc.get('faucet-spout', function () { return new THREE.CylinderGeometry(0.011, 0.011, 0.12, 10); }), faucetMat);
      spout.position.set(localX, topY + 0.19, fz + 0.18); g.add(spout);
      return g;
    },
    sink: function (mm, gc, opts) {
      var localX = opts.offsetX || 0, depth = opts.depth || 0.6, topY = opts.thicknessM || 0.035;
      var g = new THREE.Group();
      var basin = new THREE.Mesh(gc.get('sink-basin', function () { return new THREE.BoxGeometry(0.5, 0.18, depth * 0.55); }), mm.standard('sink-basin', 0xC9CDD1, 0.3, 0.85));
      basin.position.set(localX, topY - 0.095, 0); g.add(basin);
      var rim = new THREE.Mesh(gc.get('sink-rim', function () { return new THREE.BoxGeometry(0.54, 0.015, depth * 0.6); }), mm.standard('sink-rim', 0xD7DADD, 0.25, 0.9));
      rim.position.set(localX, topY + 0.005, 0); g.add(rim);
      g.add(Builders.appliances.faucet(mm, gc, { offsetX: localX, depth: depth, topY: topY }));
      return g; // NOT bounding-box centered — positions are meaningful relative to the parent countertop's own local frame.
    },
    stove: function (mm, gc, opts) {
      var localX = opts.offsetX || 0, depth = opts.depth || 0.6, cabinetHeight = opts.cabinetHeight || 0.85, topY = opts.thicknessM || 0.035;
      var g = new THREE.Group();
      var top = new THREE.Mesh(gc.get('stove-top', function () { return new THREE.BoxGeometry(0.58, 0.012, depth * 0.62); }), mm.standard('stove-glass', 0x101010, 0.15, 0.3));
      top.position.set(localX, topY + 0.007, 0); g.add(top);
      var burnerMat = mm.standard('stove-burner', 0x2a2a2a, 0.4, 0.6);
      var ringGeo = gc.get('burner-ring', function () { return new THREE.TorusGeometry(0.055, 0.007, 8, 20); });
      [[-0.16, -0.09], [0.16, -0.09], [-0.16, 0.09], [0.16, 0.09]].forEach(function (o) {
        var ring = new THREE.Mesh(ringGeo, burnerMat);
        ring.rotation.x = -Math.PI / 2;
        ring.position.set(localX + o[0], topY + 0.014, o[1] * (depth / 0.6));
        g.add(ring);
      });
      // Oven body hangs down from the countertops bottom (local Y=0),
      // occupying the cabinet volume below.
      var oven = new THREE.Mesh(gc.get('stove-oven:' + cabinetHeight.toFixed(2) + ':' + depth.toFixed(2), function () { return new THREE.BoxGeometry(0.58, cabinetHeight - 0.05, depth - 0.02); }), mm.standard('stove-oven', 0xCCCCCC, 0.35, 0.7));
      oven.position.set(localX, -cabinetHeight / 2, 0); g.add(oven);
      var win = new THREE.Mesh(gc.get('stove-window:' + cabinetHeight.toFixed(2), function () { return new THREE.BoxGeometry(0.44, cabinetHeight * 0.4, 0.02); }), mm.standard('stove-window', 0x161616, 0.2, 0.4));
      win.position.set(localX, -cabinetHeight * 0.22, -depth / 2 + 0.011); g.add(win);
      var handle = new THREE.Mesh(gc.get('stove-handle', function () { return new THREE.CylinderGeometry(0.008, 0.008, 0.5, 8); }), mm.standard('cabinet-handle', 0x2a2420, 0.35, 0.65));
      handle.rotation.z = Math.PI / 2; handle.position.set(localX, -cabinetHeight * 0.05, -depth / 2 + 0.02); g.add(handle);
      return g;
    },
  };

  // ═══════════════════════════════════════════════════════════════════════
  // CabinetRun — a run of base cabinets that owns its own Countertop (added
  // as a scenegraph child) and, in turn, any requested appliances (added as
  // children of that countertop). Never receives the slab material itself.
  // ═══════════════════════════════════════════════════════════════════════
  Builders.CabinetRun = function (mm, gc, opts) {
    var length = opts.length, height = opts.height || 0.85, depth = opts.depth || 0.62;
    var doorsPerMeter = opts.doorsPerMeter != null ? opts.doorsPerMeter : 1.3;
    var drawerRows = opts.drawerRows || 0;
    var group = new THREE.Group();

    var doorMat = mm.standard('cabinet-door', 0xF4F1EA, 0.55);
    var frameMat = mm.standard('cabinet-frame', 0xEDE7D9, 0.6);
    var handleMat = mm.standard('cabinet-handle', 0x2a2420, 0.35, 0.65);

    var carcass = new THREE.Mesh(gc.get('carcass:' + length.toFixed(2) + ':' + height.toFixed(2) + ':' + depth.toFixed(2), function () { return new THREE.BoxGeometry(length, height, depth); }), frameMat);
    carcass.position.y = height / 2; carcass.castShadow = carcass.receiveShadow = true;
    group.add(carcass);

    var doorCount = Math.max(1, Math.round(length * doorsPerMeter));
    var doorW = (length / doorCount) - 0.01;
    var frontZ = -depth / 2 - 0.011; // local -Z = front, matches the countertop's "profiled/front" edge

    for (var i = 0; i < doorCount; i++) {
      var cx = -length / 2 + doorW / 2 + 0.005 + i * (length / doorCount);
      if (drawerRows > 0) {
        var rowH = (height - 0.04) / drawerRows;
        for (var r = 0; r < drawerRows; r++) {
          var dFront = new THREE.Mesh(gc.get('drawer:' + doorW.toFixed(2) + ':' + rowH.toFixed(2), function () { return new THREE.BoxGeometry(doorW, rowH - 0.01, 0.02); }), doorMat);
          dFront.position.set(cx, 0.02 + rowH * r + rowH / 2, frontZ);
          group.add(dFront);
          var handle1 = new THREE.Mesh(gc.get('handle-drawer:' + doorW.toFixed(2), function () { return new THREE.CylinderGeometry(0.006, 0.006, doorW * 0.35, 6); }), handleMat);
          handle1.rotation.z = Math.PI / 2;
          handle1.position.set(cx, 0.02 + rowH * r + rowH - 0.05, frontZ - 0.02);
          group.add(handle1);
        }
      } else {
        var door = new THREE.Mesh(gc.get('door:' + doorW.toFixed(2) + ':' + height.toFixed(2), function () { return new THREE.BoxGeometry(doorW, height - 0.04, 0.02); }), doorMat);
        door.position.set(cx, height / 2, frontZ);
        group.add(door);
        var hnd = new THREE.Mesh(gc.get('handle-door', function () { return new THREE.CylinderGeometry(0.006, 0.006, 0.14, 6); }), handleMat);
        hnd.position.set(cx + (i % 2 === 0 ? doorW * 0.32 : -doorW * 0.32), height / 2, frontZ - 0.02);
        group.add(hnd);
      }
    }

    var countertop = null;
    if (opts.countertop) {
      var co = opts.countertop;
      var overhangLen = co.overhang != null ? co.overhang : 0.04;
      var overhangDep = co.overhangDepth != null ? co.overhangDepth : 0.02;
      countertop = Builders.Countertop(mm, {
        length: length + overhangLen, depth: depth + overhangDep,
        thicknessM: co.thicknessM, edgeProfile: co.edgeProfile, surfaceKey: co.surfaceKey || 'counter',
      });
      countertop.mesh.position.y = height;
      group.add(countertop.mesh); // countertop is a CHILD of the cabinet run

      (opts.appliances || []).forEach(function (a) {
        var applianceGroup = null;
        if (a.type === 'sink') applianceGroup = Builders.appliances.sink(mm, gc, { offsetX: a.offsetX || 0, depth: depth, thicknessM: co.thicknessM });
        else if (a.type === 'stove') applianceGroup = Builders.appliances.stove(mm, gc, { offsetX: a.offsetX || 0, depth: depth, cabinetHeight: height, thicknessM: co.thicknessM });
        if (applianceGroup) countertop.mesh.add(applianceGroup); // appliances are CHILDREN of the countertop
      });
    }

    centerBottom(group);
    return {
      group: group, length: length, height: height, depth: depth, countertop: countertop,
      updateCountertop: countertop ? countertop.update : function () {},
    };
  };

  // ── Island: freestanding cabinet body + countertop child, same rules ────
  Builders.Island = function (mm, gc, opts) {
    var length = opts.length || 1.6, depth = opts.depth || 0.9, height = opts.height || 0.85;
    var group = new THREE.Group();
    var body = new THREE.Mesh(gc.get('island-body:' + length.toFixed(2) + ':' + depth.toFixed(2) + ':' + height.toFixed(2), function () { return new THREE.BoxGeometry(length, height, depth); }), mm.standard('cabinet-frame', 0xEDE7D9, 0.6));
    body.position.y = height / 2; body.castShadow = body.receiveShadow = true;
    group.add(body);
    var co = opts.countertop || {};
    var countertop = Builders.Countertop(mm, {
      length: length + (co.overhang != null ? co.overhang : 0.06),
      depth: depth + (co.overhangDepth != null ? co.overhangDepth : 0.06),
      thicknessM: co.thicknessM, edgeProfile: co.edgeProfile, surfaceKey: co.surfaceKey || 'counter',
    });
    countertop.mesh.position.y = height;
    group.add(countertop.mesh);
    centerBottom(group);
    return { group: group, countertop: countertop, updateCountertop: countertop.update };
  };

  // ── Vanity: bathroom cabinet + countertop + sink/faucet children ────────
  Builders.Vanity = function (mm, gc, opts) {
    var length = opts.length || 1.3, depth = opts.depth || 0.5, height = opts.height || 0.82;
    var run = Builders.CabinetRun(mm, gc, {
      length: length, height: height, depth: depth, doorsPerMeter: 0, drawerRows: 2,
      countertop: {
        thicknessM: opts.thicknessM, edgeProfile: opts.edgeProfile || 'bullnose',
        surfaceKey: opts.surfaceKey || 'vanity', overhang: 0.04, overhangDepth: 0.02,
      },
      appliances: [{ type: 'sink', offsetX: 0 }],
    });
    return run;
  };

  // ── ReceptionDesk: desk body + countertop child ─────────────────────────
  Builders.ReceptionDesk = function (mm, gc, opts) {
    var length = opts.length || 2.4, depth = opts.depth || 0.7, height = opts.height || 1.1;
    var group = new THREE.Group();
    var body = new THREE.Mesh(gc.get('desk-body:' + length.toFixed(2) + ':' + depth.toFixed(2) + ':' + height.toFixed(2), function () { return new THREE.BoxGeometry(length, height, depth); }), mm.standard('desk-body', 0x2a2420, 0.5));
    body.position.y = height / 2; body.castShadow = body.receiveShadow = true;
    group.add(body);
    var co = opts.countertop || {};
    var countertop = Builders.Countertop(mm, {
      length: length + (co.overhang != null ? co.overhang : 0.08),
      depth: depth + (co.overhangDepth != null ? co.overhangDepth : 0.08),
      thicknessM: co.thicknessM, edgeProfile: co.edgeProfile || 'beveled', surfaceKey: co.surfaceKey || 'desk',
    });
    countertop.mesh.position.y = height;
    group.add(countertop.mesh);
    centerBottom(group);
    return { group: group, countertop: countertop, updateCountertop: countertop.update };
  };

  // ── Staircase: a flight of treads, each built from the shared countertop
  //    geometry factory (no cabinet parent — nested under its own riser) ──
  Builders.Staircase = function (mm, gc, opts) {
    var steps = opts.steps || 9, treadH = opts.treadH || 0.18, treadDepth = opts.treadDepth || 0.30, treadW = opts.treadW || 1.2;
    var thicknessM = opts.thicknessM, edgeProfile = opts.edgeProfile || 'bullnose';
    var group = new THREE.Group();
    var treads = [];
    var updaters = [];
    var riserMat = mm.standard('cabinet-frame', 0xEDE7D9, 0.6);
    var railMat = mm.standard('stair-rail', 0x2a2420, 0.4, 0.5);
    var balusterGeo = gc.get('baluster', function () { return new THREE.CylinderGeometry(0.012, 0.012, 1, 6); });

    for (var i = 0; i < steps; i++) {
      var stepGroup = new THREE.Group();
      var riser = new THREE.Mesh(gc.get('riser:' + treadW.toFixed(2) + ':' + treadH.toFixed(2), function () { return new THREE.BoxGeometry(treadW, treadH, 0.02); }), riserMat);
      riser.position.y = treadH / 2;
      riser.castShadow = riser.receiveShadow = true;
      stepGroup.add(riser);

      var treadHandle = Builders.Countertop(mm, { length: treadW, depth: treadDepth, thicknessM: thicknessM, edgeProfile: edgeProfile, surfaceKey: 'tread' });
      treadHandle.mesh.position.y = treadH;
      stepGroup.add(treadHandle.mesh);
      treads.push(treadHandle.mesh);
      updaters.push(treadHandle.update);

      var baluster = new THREE.Mesh(balusterGeo, railMat);
      baluster.scale.y = treadH * (i + 1) + 0.75;
      baluster.position.set(treadW / 2 - 0.03, (treadH * (i + 1) + 0.75) / 2, 0);
      stepGroup.add(baluster);

      stepGroup.position.set(0, treadH * i, -i * treadDepth);
      group.add(stepGroup);
    }

    var railLen = Math.sqrt(Math.pow(steps * treadDepth, 2) + Math.pow(steps * treadH, 2));
    var railCurve = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, railLen, 8), railMat);
    railCurve.position.set(treadW / 2 - 0.03, treadH * steps / 2 + 0.9, -(steps - 1) * treadDepth / 2);
    railCurve.rotation.x = Math.atan2(steps * treadH, steps * treadDepth);
    group.add(railCurve);

    return {
      group: group, treads: treads,
      updateCountertop: function (newThicknessM, newEdgeProfile) { updaters.forEach(function (fn) { fn(newThicknessM, newEdgeProfile); }); },
    };
  };

  // ═══════════════════════════════════════════════════════════════════════
  // Room builders — every one starts with a LayoutEngine + RoomShell, so all
  // 8 rooms share the identical placement machinery.
  // ═══════════════════════════════════════════════════════════════════════
  var ROOM_DIMS = {
    kitchen:     { width: 4.2, height: 2.7, depth: 4.0 },
    bathroom:    { width: 3.0, height: 2.6, depth: 3.2 },
    living_room: { width: 4.4, height: 2.7, depth: 4.4 },
    bedroom:     { width: 4.2, height: 2.7, depth: 4.2 },
    staircase:   { width: 3.4, height: 3.4, depth: 4.0 },
    reception:   { width: 5.0, height: 3.0, depth: 5.0 },
    hall:        { width: 4.6, height: 2.9, depth: 4.6 },
    dining:      { width: 4.2, height: 2.7, depth: 4.2 },
  };

  var ROOM_BUILDERS = {};

  ROOM_BUILDERS.kitchen = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.kitchen;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, {
      wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, windowWall: 'side',
      pendantAt: [[0.4, -0.6]], plantAt: [[-dims.width / 2 + 0.4, dims.depth / 2 - 0.4]],
    });
    group.add(shell.group);

    var counterDepth = 0.62, cabinetHeight = 0.85;
    var runALen = 2.6, runBLen = 1.8;
    var lshape = layout.lShape(counterDepth, runALen, runBLen);

    var runA = Builders.CabinetRun(mm, gc, {
      length: runALen, height: cabinetHeight, depth: counterDepth, doorsPerMeter: 1.3, drawerRows: 0,
      countertop: { thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile },
      appliances: [{ type: 'sink', offsetX: 0.2 }],
    });
    applyTransform(runA.group, lshape.runA);
    group.add(runA.group);

    var runB = Builders.CabinetRun(mm, gc, {
      length: runBLen, height: cabinetHeight, depth: counterDepth, doorsPerMeter: 0, drawerRows: 3,
      countertop: { thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile },
      appliances: [{ type: 'stove', offsetX: runBLen / 2 - 0.4 }],
    });
    applyTransform(runB.group, lshape.runB);
    group.add(runB.group);

    // Wall cabinets + backsplash above run A only.
    var wallCabH = 0.7, wallCabDepth = 0.34;
    var wallRun = Builders.CabinetRun(mm, gc, { length: runALen, height: wallCabH, depth: wallCabDepth, doorsPerMeter: 1.3, drawerRows: 0 });
    var wallTransform = layout.alongBackWall(wallCabDepth, lshape.runA.position.x);
    applyTransform(wallRun.group, wallTransform);
    wallRun.group.position.y = cabinetHeight + 0.55;
    group.add(wallRun.group);

    var splashH = 0.55;
    var backsplash = new THREE.Mesh(new THREE.PlaneGeometry(runALen, splashH), mm.surface('backsplash'));
    mm.surface('backsplash').map = null; // texture applied later via applyTexture(); base tile look meanwhile:
    var tileTex = Builders.decor.tileTexture(mm);
    backsplash.material = mm.get('backsplash-base', function () { return new THREE.MeshStandardMaterial({ map: tileTex, roughness: 0.5 }); });
    ensureUv2(backsplash.geometry);
    applyTransform(backsplash, layout.alongBackWall(0.02, lshape.runA.position.x));
    backsplash.position.y = cabinetHeight + splashH / 2;
    group.add(backsplash);

    var islandHandle = null;
    if (ctx.showIsland) {
      islandHandle = Builders.Island(mm, gc, {
        length: 1.6, depth: 0.9, height: cabinetHeight,
        countertop: { thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile },
      });
      applyTransform(islandHandle.group, layout.atOffset(0.6, 0.9));
      group.add(islandHandle.group);
    }

    var counterMeshes = [runA.countertop.mesh, runB.countertop.mesh];
    if (islandHandle) counterMeshes.push(islandHandle.countertop.mesh);

    return {
      surfaces: { floor: [shell.floor], counter: counterMeshes, backsplash: [backsplash] },
      camPos: [0.6, 1.6, 4.6], camTarget: [-0.2, 1.0, -0.2],
      rebuildSurface: function (t, p) {
        runA.updateCountertop(t, p);
        runB.updateCountertop(t, p);
        if (islandHandle) islandHandle.updateCountertop(t, p);
      },
    };
  };

  ROOM_BUILDERS.bathroom = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.bathroom;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, { wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, pendantAt: [[0, -0.4]] });
    group.add(shell.group);

    var vanityLen = 1.3, vanityDepth = 0.5, vanityH = 0.82;
    var vanity = Builders.Vanity(mm, gc, { length: vanityLen, depth: vanityDepth, height: vanityH, thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile });
    applyTransform(vanity.group, layout.alongSideWall(vanityDepth, -dims.depth / 2 + vanityDepth + 0.7));
    group.add(vanity.group);

    var mirror = new THREE.Mesh(new THREE.PlaneGeometry(0.7, 0.9), mm.physical('mirror', { color: 0xcfd8dc, roughness: 0.05, metalness: 0.9, clearcoat: 1 }));
    applyTransform(mirror, layout.alongSideWall(0.001, -dims.depth / 2 + vanityDepth + 0.7));
    mirror.position.y = 1.5;
    group.add(mirror);

    var accent = new THREE.Mesh(new THREE.PlaneGeometry(1.4, 1.8), mm.surface('wall'));
    ensureUv2(accent.geometry);
    applyTransform(accent, layout.alongSideWall(0.02, dims.depth / 2 - 0.9));
    accent.position.y = 0.9;
    group.add(accent);

    var tub = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.4, 0.55, 24), mm.standard('tub', 0xF6F4EF, 0.3));
    tub.scale.set(1.6, 1, 0.9);
    applyTransform(tub, layout.atOffset(-dims.width / 2 + 0.75, dims.depth / 2 - 0.9));
    tub.position.y = 0.28;
    tub.castShadow = tub.receiveShadow = true;
    group.add(tub);
    var tubShadow = Builders.decor.contactShadow(mm, gc, 1.3, 0.25);
    tubShadow.position.set(-dims.width / 2 + 0.75, 0.001, dims.depth / 2 - 0.9);
    group.add(tubShadow);

    return {
      surfaces: { floor: [shell.floor], vanity: [vanity.countertop.mesh], wall: [accent] },
      camPos: [0.8, 1.5, 3.6], camTarget: [0, 1.0, 0],
      rebuildSurface: function (t, p) { vanity.updateCountertop(t, p); },
    };
  };

  function livingRoomLike(group, mm, gc, ctx, dims) {
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, {
      wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, windowWall: 'back', doorWall: 'side',
      wallArtAt: [-1.6, -0.4], pendantAt: [[0, -0.3]], plantAt: [[dims.width / 2 - 0.4, dims.depth / 2 - 0.4]],
    });
    group.add(shell.group);

    var sofa = new THREE.Group();
    var seat = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.4, 0.85), mm.standard('sofa', 0x5f6f5b, 0.8));
    seat.position.y = 0.35; seat.castShadow = seat.receiveShadow = true; sofa.add(seat);
    var back = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.55, 0.22), mm.standard('sofa', 0x5f6f5b, 0.8));
    back.position.set(0, 0.68, -0.32); back.castShadow = true; sofa.add(back);
    var armGeo = new THREE.BoxGeometry(0.22, 0.55, 0.85);
    var armL = new THREE.Mesh(armGeo, mm.standard('sofa', 0x5f6f5b, 0.8));
    armL.position.set(-1.0, 0.55, 0); armL.castShadow = true; sofa.add(armL);
    var armR = new THREE.Mesh(armGeo, mm.standard('sofa', 0x5f6f5b, 0.8));
    armR.position.set(1.0, 0.55, 0); armR.castShadow = true; sofa.add(armR);
    var cushionGeo = new THREE.BoxGeometry(0.75, 0.15, 0.75);
    [-0.55, 0.55].forEach(function (cx) {
      var c = new THREE.Mesh(cushionGeo, mm.standard('sofa-cushion', 0x8a9a7f, 0.9));
      c.position.set(cx, 0.58, 0.02); sofa.add(c);
    });
    centerBottom(sofa);
    applyTransform(sofa, layout.atOffset(-0.6, 1.1));
    group.add(sofa);
    var sofaShadow = Builders.decor.contactShadow(mm, gc, 2.6, 0.3);
    sofaShadow.position.set(-0.6, 0.001, 1.1);
    group.add(sofaShadow);

    var coffeeTable = Builders.ReceptionDesk(mm, gc, {
      length: 0.9, depth: 0.5, height: 0.28,
      countertop: { thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile, surfaceKey: 'counter', overhang: 0, overhangDepth: 0 },
    });
    applyTransform(coffeeTable.group, layout.atOffset(-0.6, 0.15));
    group.add(coffeeTable.group);

    group.add((function () { var r = Builders.decor.rug(mm, gc, 1.1, 0xB9A98E); applyTransform(r, layout.atOffset(-0.6, 1.0)); return r; })());
    var lamp = Builders.decor.floorLamp(mm, gc);
    applyTransform(lamp, layout.atOffset(1.6, -1.5));
    group.add(lamp);

    return {
      surfaces: { floor: [shell.floor], wall: [shell.wall], sidewall: [shell.sidewall], counter: [coffeeTable.countertop.mesh] },
      camPos: [0.6, 1.5, 4.6], camTarget: [-0.3, 1.0, 0.3],
      rebuildSurface: function (t, p) { coffeeTable.updateCountertop(t, p); },
    };
  }
  ROOM_BUILDERS.living_room = function (group, mm, gc, ctx) { return livingRoomLike(group, mm, gc, ctx, ROOM_DIMS.living_room); };
  ROOM_BUILDERS.drawing = ROOM_BUILDERS.living_room;

  ROOM_BUILDERS.bedroom = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.bedroom;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, { wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, windowWall: 'back', doorWall: 'side' });
    group.add(shell.group);

    var bed = new THREE.Group();
    var frame = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.35, 2.1), mm.standard('bed-frame', 0x7a5f45, 0.65));
    frame.position.y = 0.175; frame.castShadow = frame.receiveShadow = true; bed.add(frame);
    var mattress = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.25, 2.0), mm.standard('mattress', 0xffffff, 0.9));
    mattress.position.y = 0.475; mattress.castShadow = mattress.receiveShadow = true; bed.add(mattress);
    var quilt = new THREE.Mesh(new THREE.BoxGeometry(1.72, 0.06, 1.3), mm.standard('quilt', 0xB6C2B0, 0.85));
    quilt.position.set(0, 0.62, 0.3); bed.add(quilt);
    var pillowGeo = new THREE.BoxGeometry(0.55, 0.12, 0.4);
    [-0.4, 0.4].forEach(function (px) {
      var p = new THREE.Mesh(pillowGeo, mm.standard('pillow', 0xf5f0e6, 0.9));
      p.position.set(px, 0.66, -0.75); bed.add(p);
    });
    var headboard = new THREE.Mesh(new THREE.BoxGeometry(1.9, 0.9, 0.1), mm.standard('bed-frame', 0x7a5f45, 0.65));
    headboard.position.set(0, 0.75, -1.1); headboard.castShadow = true; bed.add(headboard);
    centerBottom(bed);
    applyTransform(bed, layout.atOffset(0.3, -0.5));
    group.add(bed);
    var bedShadow = Builders.decor.contactShadow(mm, gc, 2.4, 0.25);
    bedShadow.position.set(0.3, 0.001, -0.5);
    group.add(bedShadow);

    group.add((function () { var r = Builders.decor.rug(mm, gc, 0.9, 0xC9BBA6); applyTransform(r, layout.atOffset(-0.6, 1.0)); return r; })());
    var lamp = Builders.decor.floorLamp(mm, gc);
    applyTransform(lamp, layout.atOffset(-1.6, -1.4));
    group.add(lamp);

    return { surfaces: { floor: [shell.floor], wall: [shell.wall] }, camPos: [0.5, 1.5, 4.4], camTarget: [0.3, 1.0, -0.3] };
  };

  ROOM_BUILDERS.staircase = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.staircase;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, {
      wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, pendantAt: [[0, 0.6]], wallArtAt: [-dims.width / 2 + 0.03, 0, Math.PI / 2, 1.7],
    });
    group.add(shell.group);

    var stair = Builders.Staircase(mm, gc, { steps: 9, treadH: 0.18, treadDepth: 0.30, treadW: 1.2, thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile || 'bullnose' });
    applyTransform(stair.group, layout.atOffset(0, dims.depth / 2 - 0.6));
    group.add(stair.group);

    return {
      surfaces: { floor: [shell.floor], tread: stair.treads },
      camPos: [2.4, 2.0, 3.2], camTarget: [0, 1.2, -0.4],
      rebuildSurface: function (t, p) { stair.updateCountertop(t, p); },
    };
  };

  ROOM_BUILDERS.reception = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.reception;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, {
      wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase,
      pendantAt: [[0, -0.6], [-1.4, -0.6], [1.4, -0.6]],
      wallArtAt: [1.4, -dims.depth / 2 + 0.035, 0], plantAt: [[dims.width / 2 - 0.5, dims.depth / 2 - 0.5], [-dims.width / 2 + 0.5, dims.depth / 2 - 0.5]],
    });
    group.add(shell.group);

    var desk = Builders.ReceptionDesk(mm, gc, { length: 2.4, depth: 0.7, height: 1.1, thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile });
    applyTransform(desk.group, layout.alongBackWall(0.7, 0));
    group.add(desk.group);

    var panel = new THREE.Mesh(new THREE.PlaneGeometry(2.0, 1.0), mm.standard('desk-panel', 0xF4F1EA, 0.7));
    applyTransform(panel, layout.alongBackWall(0.001, 0));
    panel.position.y = 1.5;
    group.add(panel);

    var bench = new THREE.Group();
    var benchBody = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.42, 0.55), mm.standard('bench', 0x6b5844, 0.7));
    benchBody.position.y = 0.21; benchBody.castShadow = benchBody.receiveShadow = true; bench.add(benchBody);
    var cushion = new THREE.Mesh(new THREE.BoxGeometry(1.55, 0.12, 0.5), mm.standard('sofa-cushion', 0x8a9a7f, 0.9));
    cushion.position.y = 0.48; bench.add(cushion);
    centerBottom(bench);
    applyTransform(bench, layout.atOffset(1.6, dims.depth / 2 - 1.0));
    group.add(bench);

    group.add((function () { var r = Builders.decor.rug(mm, gc, 1.6, 0xC2B49A); applyTransform(r, layout.atOffset(0, 0.5)); return r; })());

    return {
      surfaces: { floor: [shell.floor], desk: [desk.countertop.mesh] },
      camPos: [0, 1.7, 4.8], camTarget: [0, 1.1, -0.3],
      rebuildSurface: function (t, p) { desk.updateCountertop(t, p); },
    };
  };

  ROOM_BUILDERS.hall = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.hall;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, { wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, windowWall: 'back', doorWall: 'side', plantAt: [[-1.8, -1.8]] });
    group.add(shell.group);

    var console_ = Builders.ReceptionDesk(mm, gc, {
      length: 1.3, depth: 0.35, height: 0.75,
      countertop: { thicknessM: ctx.thicknessM, edgeProfile: ctx.edgeProfile, surfaceKey: 'counter' },
    });
    applyTransform(console_.group, layout.alongSideWall(0.35, -1.9));
    group.add(console_.group);

    var mirror = new THREE.Mesh(new THREE.PlaneGeometry(0.7, 0.9), mm.physical('mirror', { color: 0xcfd8dc, roughness: 0.05, metalness: 0.9, clearcoat: 1 }));
    applyTransform(mirror, layout.alongSideWall(0.001, -1.9));
    mirror.position.y = 1.5;
    group.add(mirror);

    var consoleShadow = Builders.decor.contactShadow(mm, gc, 1.8, 0.25);
    consoleShadow.position.set(-dims.width / 2 + 0.18, 0.001, -1.9);
    group.add(consoleShadow);
    group.add((function () { var r = Builders.decor.rug(mm, gc, 1.0, 0xC2B49A); applyTransform(r, layout.atOffset(0, 0.8)); return r; })());

    return {
      surfaces: { floor: [shell.floor], wall: [shell.wall], counter: [console_.countertop.mesh] },
      camPos: [0, 1.6, 4.8], camTarget: [0, 1.1, -0.4],
      rebuildSurface: function (t, p) { console_.updateCountertop(t, p); },
    };
  };

  ROOM_BUILDERS.dining = function (group, mm, gc, ctx) {
    var dims = ROOM_DIMS.dining;
    var layout = createLayoutEngine({ width: dims.width, height: dims.height, depth: dims.depth, wallThickness: ctx.wallThickness });
    var shell = Builders.RoomShell(mm, gc, layout, { wallColor: ctx.wallColor, floorBaseColor: ctx.floorBase, pendantAt: [[0, -0.2]] });
    group.add(shell.group);

    var tableGroup = new THREE.Group();
    var topGeo = buildCountertopGeometry(1.7, 1.7, ctx.thicknessM, ctx.edgeProfile); // circular look approximated with a square-plan extrude kept small; visually reads fine at table scale
    var tableTopMat = mm.surface('counter');
    var pedestal = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.14, 0.7, 16), mm.standard('table-pedestal', 0x3a2c1e, 0.5));
    pedestal.position.y = 0.35; pedestal.castShadow = true; tableGroup.add(pedestal);
    var top = new THREE.Mesh(new THREE.CylinderGeometry(0.85, 0.85, ctx.thicknessM || 0.035, 32), tableTopMat);
    top.position.y = 0.7 + (ctx.thicknessM || 0.035) / 2;
    top.castShadow = top.receiveShadow = true; tableGroup.add(top);
    centerBottom(tableGroup);
    applyTransform(tableGroup, layout.atOffset(0, -0.2));
    group.add(tableGroup);
    var tableShadow = Builders.decor.contactShadow(mm, gc, 2.2, 0.28);
    tableShadow.position.set(0, 0.001, -0.2);
    group.add(tableShadow);

    var chairSeatGeo = new THREE.BoxGeometry(0.4, 0.08, 0.4);
    var chairBackGeo = new THREE.BoxGeometry(0.4, 0.5, 0.06);
    var chairMat = mm.standard('chair', 0x6b5844, 0.7);
    [[0, -1.15, 0], [0, 0.75, Math.PI], [-1.15, -0.2, Math.PI / 2], [1.15, -0.2, -Math.PI / 2]].forEach(function (c) {
      var seat = new THREE.Mesh(chairSeatGeo, chairMat);
      applyTransform(seat, layout.atOffset(c[0], c[1]));
      seat.position.y = 0.45; seat.castShadow = seat.receiveShadow = true; group.add(seat);
      var back = new THREE.Mesh(chairBackGeo, chairMat);
      back.position.set(c[0], 0.74, c[1]); back.rotation.y = c[2]; back.translateZ(0.17);
      group.add(back);
    });

    return {
      surfaces: { floor: [shell.floor], counter: [top] },
      camPos: [0.4, 1.6, 4.6], camTarget: [0, 0.9, -0.2],
      rebuildSurface: function (t) {
        // Round table — thickness only (edge profile doesn't apply to a lathe-round top).
        top.geometry.dispose();
        top.geometry = new THREE.CylinderGeometry(0.85, 0.85, t, 32);
        top.position.y = 0.7 + t / 2;
      },
    };
  };

  // ═══════════════════════════════════════════════════════════════════════
  // Slab texture + PBR map generation (unchanged approach from v1)
  // ═══════════════════════════════════════════════════════════════════════
  function generatePBRMaps(img, size) {
    var work = document.createElement('canvas'); work.width = work.height = size;
    var wctx = work.getContext('2d');
    var cropPct = 0.10;
    var sx = img.width * cropPct, sy = img.height * cropPct;
    var sw = img.width - sx * 2, sh = img.height - sy * 2;
    wctx.filter = 'contrast(1.22) brightness(0.86) saturate(1.05)';
    wctx.drawImage(img, sx, sy, sw, sh, 0, 0, size, size);
    wctx.filter = 'none';
    var colorData = wctx.getImageData(0, 0, size, size);

    var lum = new Float32Array(size * size);
    for (var i = 0; i < size * size; i++) {
      var r = colorData.data[i * 4], g = colorData.data[i * 4 + 1], b = colorData.data[i * 4 + 2];
      lum[i] = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    }

    var normalCanvas = document.createElement('canvas'); normalCanvas.width = normalCanvas.height = size;
    var nctx = normalCanvas.getContext('2d'); var nImg = nctx.createImageData(size, size);
    var aoCanvas = document.createElement('canvas'); aoCanvas.width = aoCanvas.height = size;
    var actx = aoCanvas.getContext('2d'); var aImg = actx.createImageData(size, size);
    var roughCanvas = document.createElement('canvas'); roughCanvas.width = roughCanvas.height = size;
    var rctx = roughCanvas.getContext('2d'); var rImg = rctx.createImageData(size, size);

    var strength = 2.2;
    for (var y = 0; y < size; y++) {
      for (var x = 0; x < size; x++) {
        var xm = Math.max(0, x - 1), xp = Math.min(size - 1, x + 1);
        var ym = Math.max(0, y - 1), yp = Math.min(size - 1, y + 1);
        var l = lum[y * size + x];
        var dx = (lum[y * size + xp] - lum[y * size + xm]) * strength;
        var dy = (lum[yp * size + x] - lum[ym * size + x]) * strength;
        var nx = -dx, ny = -dy, nz = 1.0;
        var len = Math.sqrt(nx * nx + ny * ny + nz * nz);
        nx /= len; ny /= len; nz /= len;
        var idx = (y * size + x) * 4;
        nImg.data[idx] = (nx * 0.5 + 0.5) * 255; nImg.data[idx + 1] = (ny * 0.5 + 0.5) * 255; nImg.data[idx + 2] = (nz * 0.5 + 0.5) * 255; nImg.data[idx + 3] = 255;
        var edge = Math.min(1, Math.abs(dx) + Math.abs(dy));
        var ao = Math.max(0.55, Math.min(1, 1 - edge * 0.35 - (1 - l) * 0.12));
        aImg.data[idx] = aImg.data[idx + 1] = aImg.data[idx + 2] = ao * 255; aImg.data[idx + 3] = 255;
        var rough = Math.max(0.14, Math.min(0.55, 0.5 - (l - 0.5) * 0.35 - edge * 0.1));
        rImg.data[idx] = rImg.data[idx + 1] = rImg.data[idx + 2] = rough * 255; rImg.data[idx + 3] = 255;
      }
    }
    nctx.putImageData(nImg, 0, 0); actx.putImageData(aImg, 0, 0); rctx.putImageData(rImg, 0, 0);

    var colorTex = new THREE.CanvasTexture(work);
    var normalTex = new THREE.CanvasTexture(normalCanvas);
    var aoTex = new THREE.CanvasTexture(aoCanvas);
    var roughTex = new THREE.CanvasTexture(roughCanvas);
    colorTex.encoding = THREE.sRGBEncoding;
    normalTex.encoding = aoTex.encoding = roughTex.encoding = THREE.LinearEncoding;
    [colorTex, normalTex, aoTex, roughTex].forEach(function (t) { t.wrapS = t.wrapT = THREE.RepeatWrapping; t.center.set(0.5, 0.5); });
    return { colorTex: colorTex, normalTex: normalTex, aoTex: aoTex, roughTex: roughTex };
  }

  function loadSlabMaps(renderer, url, size, cb) {
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () {
      var maps = generatePBRMaps(img, size);
      var anisotropy = renderer.capabilities.getMaxAnisotropy();
      [maps.colorTex, maps.normalTex, maps.aoTex, maps.roughTex].forEach(function (t) { t.anisotropy = anisotropy; });
      cb(maps);
    };
    img.onerror = function () { cb(null); };
    img.src = url;
  }

  function repeatForKey(tex, key) {
    if (key === 'floor') tex.repeat.set(3, 3);
    else if (key === 'counter' || key === 'vanity' || key === 'desk') tex.repeat.set(1.6, 0.7);
    else if (key === 'tread') tex.repeat.set(1, 0.6);
    else if (key === 'backsplash') tex.repeat.set(2, 1);
    else tex.repeat.set(2, 1.6);
  }

  function buildProceduralEnvironment(renderer) {
    if (typeof THREE.PMREMGenerator !== 'function') return null;
    try {
      var envScene = new THREE.Scene();
      var geo = new THREE.BoxGeometry(6, 6, 6);
      var mats = [0xfff3df, 0xfff3df, 0xffffff, 0x9a8f7e, 0xe8ddc8, 0xe8ddc8].map(function (c) { return new THREE.MeshBasicMaterial({ color: c, side: THREE.BackSide }); });
      envScene.add(new THREE.Mesh(geo, mats));
      var panel1 = new THREE.Mesh(new THREE.PlaneGeometry(2, 2), new THREE.MeshBasicMaterial({ color: 0xffffff }));
      panel1.position.set(0, 2.9, 0); panel1.rotation.x = Math.PI / 2; envScene.add(panel1);
      var panel2 = new THREE.Mesh(new THREE.PlaneGeometry(1.5, 3), new THREE.MeshBasicMaterial({ color: 0xfff8ea }));
      panel2.position.set(-2.9, 0, 0); panel2.rotation.y = Math.PI / 2; envScene.add(panel2);
      var pmrem = new THREE.PMREMGenerator(renderer);
      pmrem.compileEquirectangularShader();
      var target = pmrem.fromScene(envScene, 0.02);
      pmrem.dispose();
      return target.texture;
    } catch (e) { return null; }
  }

  // ═══════════════════════════════════════════════════════════════════════
  // Main entry — unchanged public signature.
  // ═══════════════════════════════════════════════════════════════════════
  window.RoomVisualizer3D = function (containerId, opts) {
    opts = opts || {};
    var container = document.getElementById(containerId);
    if (!container || !window.THREE) return null;

    var qualityKey = QUALITY[opts.quality] ? opts.quality : 'high';
    var q = QUALITY[qualityKey];
    var width = container.clientWidth || 600;
    var height = opts.height || 420;
    var roomPalette = pickRoomPalette(opts.palette || []);

    var mm = createMaterialManager();
    var gc = createGeometryCache();

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0xC9C2B4);
    scene.fog = new THREE.Fog(0xC9C2B4, 7, 15);
    var camera = new THREE.PerspectiveCamera(42, width / height, 0.1, 100);

    var renderer = new THREE.WebGLRenderer({ antialias: q.aa, preserveDrawingBuffer: true, powerPreference: 'high-performance' });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, q.pixelRatio));
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 0.95;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    container.innerHTML = '';
    container.appendChild(renderer.domElement);

    var controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true; controls.dampingFactor = 0.08; controls.enablePan = true;
    controls.minDistance = 1.2; controls.maxDistance = 9; controls.maxPolarAngle = Math.PI * 0.495;
    controls.autoRotate = false; controls.autoRotateSpeed = 0.8;

    var hemi = new THREE.HemisphereLight(0xfff4e6, 0x33302c, 0.5); scene.add(hemi);
    var keyLight = new THREE.DirectionalLight(0xfff1dc, 1.0);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(q.shadowMap, q.shadowMap);
    keyLight.shadow.camera.near = 0.5; keyLight.shadow.camera.far = 14;
    keyLight.shadow.camera.left = -4; keyLight.shadow.camera.right = 4;
    keyLight.shadow.camera.top = 4; keyLight.shadow.camera.bottom = -4;
    keyLight.shadow.bias = -0.0006; keyLight.shadow.normalBias = 0.02; keyLight.shadow.radius = 2;
    scene.add(keyLight);
    var fillLight = new THREE.PointLight(0xffffff, 0.2, 10); scene.add(fillLight);
    var rimLight = new THREE.DirectionalLight(0xcfe0ff, 0.25); scene.add(rimLight);
    var windowGlow = new THREE.PointLight(0xfff2cc, 0.45, 6); scene.add(windowGlow);

    if (qualityKey !== 'low') {
      var envTex = buildProceduralEnvironment(renderer);
      if (envTex) scene.environment = envTex;
    }

    var roomGroup = null, surfaces = {}, activeKey = null, currentTexture = null, currentMaps = null, currentBuilt = null;
    var dayMode = true, beforeAfter = true, slabRotationDeg = 0;
    var thicknessM = (opts.thicknessMm ? opts.thicknessMm : 35) / 1000;
    var edgeProfile = opts.edgeProfile || 'straight';
    var showIsland = !!opts.showIsland;
    var wallThickness = opts.wallThicknessM != null ? opts.wallThicknessM : 0.1;

    function applyTexture() {
      Object.keys(surfaces).forEach(function (k) {
        var m = mm.surface(k);
        if (k === activeKey && currentMaps && beforeAfter) {
          m.map = currentMaps.colorTex; m.normalMap = currentMaps.normalTex; m.normalScale = new THREE.Vector2(0.35, 0.35);
          m.roughnessMap = currentMaps.roughTex; m.aoMap = currentMaps.aoTex; m.aoMapIntensity = 0.6; m.color.set(0xffffff);
        } else if (k === activeKey && currentMaps && !beforeAfter) {
          m.map = null; m.normalMap = null; m.roughnessMap = null; m.aoMap = null; m.color.set(0xE7E2D8);
        } else if (k !== 'floor') {
          m.map = null; m.normalMap = null; m.roughnessMap = null; m.aoMap = null; m.color.set(0xEFEAE0);
        }
        m.needsUpdate = true;
      });
    }
    function applyRotation() {
      if (!currentMaps) return;
      var rad = (slabRotationDeg * Math.PI) / 180;
      [currentMaps.colorTex, currentMaps.normalTex, currentMaps.aoTex, currentMaps.roughTex].forEach(function (t) { t.rotation = rad; });
    }
    function applyDayNight() {
      if (dayMode) {
        scene.background.set(0xC9C2B4); if (scene.fog) scene.fog.color.set(0xC9C2B4);
        hemi.intensity = 0.5; keyLight.intensity = 1.0; fillLight.intensity = 0.2; rimLight.intensity = 0.25; windowGlow.intensity = 0.45;
        renderer.toneMappingExposure = 0.95;
      } else {
        scene.background.set(0x0d0f16); if (scene.fog) scene.fog.color.set(0x0d0f16);
        hemi.intensity = 0.12; keyLight.intensity = 0.15; fillLight.intensity = 0.08; rimLight.intensity = 0.05; windowGlow.intensity = 0.05;
        renderer.toneMappingExposure = 1.15;
      }
      if (roomGroup) {
        roomGroup.traverse(function (obj) {
          if (obj.isPointLight && obj.name === 'nightLight') {
            if (obj.userData._dayI == null) obj.userData._dayI = obj.intensity;
            obj.intensity = dayMode ? obj.userData._dayI : obj.userData._dayI * 2.4;
          }
        });
      }
    }

    function buildRoom(roomKey, keepSurface) {
      if (!ROOM_BUILDERS[roomKey]) roomKey = 'kitchen';
      if (roomGroup) scene.remove(roomGroup);
      roomGroup = new THREE.Group();
      var ctx = { wallColor: roomPalette.wall, floorBase: roomPalette.floorBase, thicknessM: thicknessM, edgeProfile: edgeProfile, showIsland: showIsland, wallThickness: wallThickness };
      currentBuilt = ROOM_BUILDERS[roomKey](roomGroup, mm, gc, ctx);
      scene.add(roomGroup);
      surfaces = currentBuilt.surfaces;

      keyLight.position.set(currentBuilt.camPos[0] + 1.5, currentBuilt.camPos[1] + 2.8, currentBuilt.camPos[2] - 0.5);
      keyLight.target.position.set(currentBuilt.camTarget[0], 0, currentBuilt.camTarget[2]);
      scene.add(keyLight.target);
      rimLight.position.set(currentBuilt.camTarget[0] - 2, 2.2, currentBuilt.camTarget[2] - 3);
      fillLight.position.set(currentBuilt.camTarget[0] - 2, 1.4, currentBuilt.camTarget[2] + 2);
      windowGlow.position.set(1.6, 1.6, -1.9);
      camera.position.set(currentBuilt.camPos[0], currentBuilt.camPos[1], currentBuilt.camPos[2]);
      controls.target.set(currentBuilt.camTarget[0], currentBuilt.camTarget[1], currentBuilt.camTarget[2]);
      controls.update();

      var keys = Object.keys(surfaces);
      if (!keepSurface || !surfaces[activeKey]) {
        activeKey = keys.indexOf('counter') !== -1 ? 'counter'
          : keys.indexOf('vanity') !== -1 ? 'vanity'
          : keys.indexOf('desk') !== -1 ? 'desk'
          : keys.indexOf('tread') !== -1 ? 'tread'
          : keys[0];
      }
      applyTexture(); applyRotation(); applyDayNight();
    }

    buildRoom(opts.room || 'kitchen');

    if (opts.textureUrl) {
      loadSlabMaps(renderer, opts.textureUrl, q.texSize, function (maps) {
        if (!maps) return;
        currentMaps = maps;
        [currentMaps.colorTex, currentMaps.normalTex, currentMaps.aoTex, currentMaps.roughTex].forEach(function (t) { repeatForKey(t, activeKey); });
        applyTexture(); applyRotation();
        if (typeof opts.onReady === 'function') opts.onReady();
      });
    }

    // ── Public per-instance API (unchanged names/signatures) ──────────────
    window['rv3d_setRoom_' + containerId] = function (roomKey) {
      buildRoom(roomKey, false);
      if (currentMaps) { [currentMaps.colorTex, currentMaps.normalTex, currentMaps.aoTex, currentMaps.roughTex].forEach(function (t) { repeatForKey(t, activeKey); }); applyTexture(); applyRotation(); }
    };
    window['rv3d_setSurface_' + containerId] = function (surfKey) {
      if (!surfaces[surfKey]) return;
      activeKey = surfKey;
      if (currentMaps) [currentMaps.colorTex, currentMaps.normalTex, currentMaps.aoTex, currentMaps.roughTex].forEach(function (t) { repeatForKey(t, surfKey); });
      applyTexture();
    };
    window['rv3d_getSurfaces_' + containerId] = function () { return Object.keys(surfaces); };
    window['rv3d_getRoomLabel'] = function (k) { return ROOM_LABELS[k] || k; };
    window['rv3d_getSurfaceLabel'] = function (k) { return SURFACE_LABELS[k] || k; };

    window['rv3d_setQuality_' + containerId] = function (level) {
      if (!QUALITY[level]) return;
      qualityKey = level; q = QUALITY[level];
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, q.pixelRatio));
      keyLight.shadow.mapSize.set(q.shadowMap, q.shadowMap);
      if (keyLight.shadow.map) { keyLight.shadow.map.dispose(); keyLight.shadow.map = null; }
      if (qualityKey !== 'low' && !scene.environment) { var e2 = buildProceduralEnvironment(renderer); if (e2) scene.environment = e2; }
      else if (qualityKey === 'low') { scene.environment = null; }
    };
    window['rv3d_toggleDayNight_' + containerId] = function () { dayMode = !dayMode; applyDayNight(); return dayMode; };
    window['rv3d_toggleBeforeAfter_' + containerId] = function () { beforeAfter = !beforeAfter; applyTexture(); return beforeAfter; };
    window['rv3d_toggleAutoRotate_' + containerId] = function () { controls.autoRotate = !controls.autoRotate; return controls.autoRotate; };
    window['rv3d_resetCamera_' + containerId] = function () {
      if (!currentBuilt) return;
      camera.position.set(currentBuilt.camPos[0], currentBuilt.camPos[1], currentBuilt.camPos[2]);
      controls.target.set(currentBuilt.camTarget[0], currentBuilt.camTarget[1], currentBuilt.camTarget[2]);
      controls.update();
    };
    window['rv3d_zoom_' + containerId] = function (steps) {
      var dir = new THREE.Vector3().subVectors(camera.position, controls.target);
      var dist = Math.max(controls.minDistance, Math.min(controls.maxDistance, dir.length() * (1 - steps * 0.15)));
      dir.setLength(dist);
      camera.position.copy(controls.target).add(dir);
      controls.update();
    };
    window['rv3d_fullscreen_' + containerId] = function () {
      var req = container.requestFullscreen || container.webkitRequestFullscreen || container.msRequestFullscreen;
      if (req) req.call(container);
    };
    window['rv3d_setThickness_' + containerId] = function (mmVal) {
      thicknessM = Math.max(10, Math.min(60, mmVal)) / 1000;
      if (currentBuilt && currentBuilt.rebuildSurface) { currentBuilt.rebuildSurface(thicknessM, edgeProfile); applyTexture(); applyRotation(); }
    };
    window['rv3d_setEdgeProfile_' + containerId] = function (profile) {
      edgeProfile = profile;
      if (currentBuilt && currentBuilt.rebuildSurface) { currentBuilt.rebuildSurface(thicknessM, edgeProfile); applyTexture(); applyRotation(); }
    };
    window['rv3d_setSlabRotation_' + containerId] = function (deg) { slabRotationDeg = deg; applyRotation(); };
    window['rv3d_setIsland_' + containerId] = function (on) {
      showIsland = !!on;
      buildRoom('kitchen', true);
      if (currentMaps) { [currentMaps.colorTex, currentMaps.normalTex, currentMaps.aoTex, currentMaps.roughTex].forEach(function (t) { repeatForKey(t, activeKey); }); applyTexture(); applyRotation(); }
    };
    window['rv3d_supportsCountertopControls_' + containerId] = function () { return !!(currentBuilt && currentBuilt.rebuildSurface); };

    window['rv3d_snapshot_' + containerId] = function () { renderer.render(scene, camera); return renderer.domElement.toDataURL('image/jpeg', 0.94); };
    window['rv3d_highResSnapshot_' + containerId] = function (scale) {
      scale = Math.max(1, Math.min(4, scale || 3));
      var targetW = Math.min(4096, width * scale), targetH = Math.min(4096, height * scale);
      renderer.setSize(targetW, targetH, false);
      camera.aspect = targetW / targetH; camera.updateProjectionMatrix();
      renderer.render(scene, camera);
      var data = renderer.domElement.toDataURL('image/png');
      renderer.setSize(width, height, false);
      camera.aspect = width / height; camera.updateProjectionMatrix();
      renderer.render(scene, camera);
      return data;
    };

    (function animate() { requestAnimationFrame(animate); controls.update(); renderer.render(scene, camera); })();

    function doResize() {
      var w = container.clientWidth || width;
      var hh = document.fullscreenElement === container ? window.innerHeight : height;
      camera.aspect = w / hh; camera.updateProjectionMatrix();
      renderer.setSize(w, hh);
    }
    window.addEventListener('resize', doResize);
    document.addEventListener('fullscreenchange', doResize);

    return { containerId: containerId, getSurfaces: function () { return Object.keys(surfaces); } };
  };

  // ═══════════════════════════════════════════════════════════════════════
  // Shared control-bar builder — professional light UI, one implementation
  // reused by both the user panel and the admin panel.
  // ═══════════════════════════════════════════════════════════════════════
  var _rv3dStyleInjected = false;
  function injectStyles() {
    if (_rv3dStyleInjected) return;
    _rv3dStyleInjected = true;
    var css = '' +
      '.rv3d-bar{--rv3d-accent:#B8975A;--rv3d-ink:#1A2837;--rv3d-sub:#6B7684;--rv3d-line:#E7E2D8;' +
        'background:#FBFAF8;border-top:1px solid var(--rv3d-line);font-family:inherit;}' +
      '.rv3d-section{padding:10px 16px;border-bottom:1px solid var(--rv3d-line);}' +
      '.rv3d-section:last-child{border-bottom:none;}' +
      '.rv3d-caption{font-size:9.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--rv3d-sub);margin-bottom:7px;}' +
      '.rv3d-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}' +
      '.rv3d-tab{padding:6px 13px;border-radius:20px;font-size:12px;font-weight:600;background:#fff;color:var(--rv3d-sub);' +
        'border:1px solid var(--rv3d-line);cursor:pointer;white-space:nowrap;font-family:inherit;transition:all .15s;}' +
      '.rv3d-tab:hover{border-color:var(--rv3d-accent);color:var(--rv3d-ink);}' +
      '.rv3d-tab.active{background:var(--rv3d-ink);border-color:var(--rv3d-ink);color:#fff;}' +
      '.rv3d-tab.active::after{content:"";}' +
      '.rv3d-divider{width:1px;align-self:stretch;background:var(--rv3d-line);margin:0 4px;}' +
      '.rv3d-btn{width:32px;height:32px;border-radius:8px;background:#fff;color:var(--rv3d-ink);' +
        'border:1px solid var(--rv3d-line);cursor:pointer;display:flex;align-items:center;justify-content:center;' +
        'font-size:13px;transition:all .15s;flex-shrink:0;}' +
      '.rv3d-btn:hover{border-color:var(--rv3d-accent);background:#FBF5EB;}' +
      '.rv3d-btn.on{background:var(--rv3d-accent);border-color:var(--rv3d-accent);color:#fff;}' +
      '.rv3d-select{height:32px;border-radius:8px;background:#fff;color:var(--rv3d-ink);border:1px solid var(--rv3d-line);' +
        'font-size:12px;padding:0 8px;font-family:inherit;cursor:pointer;}' +
      '.rv3d-label{font-size:11px;color:var(--rv3d-sub);font-weight:600;white-space:nowrap;margin-right:2px;}' +
      '.rv3d-slider{width:90px;accent-color:var(--rv3d-accent);}' +
      '.rv3d-check{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--rv3d-ink);font-weight:600;cursor:pointer;padding:0 4px;}' +
      '.rv3d-check input{accent-color:var(--rv3d-accent);width:15px;height:15px;cursor:pointer;}';
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
  }

  window.RV3D_mount = function (containerId, controlsWrapId, opts) {
    injectStyles();
    var handle = window.RoomVisualizer3D(containerId, opts);
    var wrap = document.getElementById(controlsWrapId);
    if (!wrap || !handle) return handle;

    function call(name) {
      var args = Array.prototype.slice.call(arguments, 1);
      var fn = window['rv3d_' + name + '_' + containerId];
      return fn ? fn.apply(null, args) : undefined;
    }

    var bar = document.createElement('div'); bar.className = 'rv3d-bar';

    // Room section
    var roomSection = document.createElement('div'); roomSection.className = 'rv3d-section';
    var roomCaption = document.createElement('div'); roomCaption.className = 'rv3d-caption'; roomCaption.textContent = 'Room';
    var roomRow = document.createElement('div'); roomRow.className = 'rv3d-row';
    ROOM_ORDER.forEach(function (key) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'rv3d-tab' + (key === (opts.room || 'kitchen') ? ' active' : '');
      b.textContent = call('getRoomLabel', key) || key;
      b.addEventListener('click', function () {
        roomRow.querySelectorAll('.rv3d-tab').forEach(function (t) { t.classList.remove('active'); });
        b.classList.add('active');
        call('setRoom', key);
        renderSurfaceRow();
      });
      roomRow.appendChild(b);
    });
    roomSection.appendChild(roomCaption); roomSection.appendChild(roomRow);

    // Surface section
    var surfaceSection = document.createElement('div'); surfaceSection.className = 'rv3d-section';
    var surfaceCaption = document.createElement('div'); surfaceCaption.className = 'rv3d-caption'; surfaceCaption.textContent = 'Slab Applied To';
    var surfaceRow = document.createElement('div'); surfaceRow.className = 'rv3d-row';
    surfaceSection.appendChild(surfaceCaption); surfaceSection.appendChild(surfaceRow);

    function renderSurfaceRow() {
      surfaceRow.innerHTML = '';
      var keys = call('getSurfaces') || [];
      keys.forEach(function (k, i) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'rv3d-tab' + (i === 0 ? ' active' : '');
        b.textContent = window.rv3d_getSurfaceLabel ? window.rv3d_getSurfaceLabel(k) : k;
        b.addEventListener('click', function () {
          surfaceRow.querySelectorAll('.rv3d-tab').forEach(function (t) { t.classList.remove('active'); });
          b.classList.add('active');
          call('setSurface', k);
        });
        surfaceRow.appendChild(b);
      });
      slabSection.style.display = call('supportsCountertopControls') ? 'block' : 'none';
    }

    // View tools section
    var viewSection = document.createElement('div'); viewSection.className = 'rv3d-section';
    var viewCaption = document.createElement('div'); viewCaption.className = 'rv3d-caption'; viewCaption.textContent = 'View';
    var viewRow = document.createElement('div'); viewRow.className = 'rv3d-row';

    function iconBtn(label, title, onClick) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'rv3d-btn'; b.title = title; b.textContent = label;
      b.addEventListener('click', onClick);
      return b;
    }
    function divider() { var d = document.createElement('div'); d.className = 'rv3d-divider'; return d; }

    var qualitySel = document.createElement('select'); qualitySel.className = 'rv3d-select';
    ['low', 'medium', 'high', 'ultra'].forEach(function (lvl) {
      var o = document.createElement('option'); o.value = lvl; o.textContent = lvl.charAt(0).toUpperCase() + lvl.slice(1);
      if (lvl === (opts.quality || 'high')) o.selected = true;
      qualitySel.appendChild(o);
    });
    qualitySel.addEventListener('change', function () { call('setQuality', qualitySel.value); });
    viewRow.appendChild(qualitySel);
    viewRow.appendChild(divider());

    var dayBtn = iconBtn('☀', 'Day / Night lighting', function () {
      var isDay = call('toggleDayNight');
      dayBtn.classList.toggle('on', !isDay);
      dayBtn.textContent = isDay ? '☀' : '☾';
    });
    viewRow.appendChild(dayBtn);
    var beforeBtn = iconBtn('B/A', 'Before / After comparison', function () {
      var after = call('toggleBeforeAfter');
      beforeBtn.classList.toggle('on', !after);
    });
    viewRow.appendChild(beforeBtn);
    var rotateBtn = iconBtn('⟳', 'Auto-rotate', function () { rotateBtn.classList.toggle('on', call('toggleAutoRotate')); });
    viewRow.appendChild(rotateBtn);
    viewRow.appendChild(divider());
    viewRow.appendChild(iconBtn('−', 'Zoom out', function () { call('zoom', -1); }));
    viewRow.appendChild(iconBtn('+', 'Zoom in', function () { call('zoom', 1); }));
    viewRow.appendChild(iconBtn('⌂', 'Reset camera', function () { call('resetCamera'); }));
    viewRow.appendChild(iconBtn('⛶', 'Fullscreen', function () { call('fullscreen'); }));
    viewRow.appendChild(divider());
    viewRow.appendChild(iconBtn('📷', 'Save snapshot', function () { var d = call('snapshot'); if (d) downloadDataUrl(d, 'room-preview.jpg'); }));
    viewRow.appendChild(iconBtn('HD', 'High-resolution snapshot', function () { var d = call('highResSnapshot', 3); if (d) downloadDataUrl(d, 'room-preview-hd.png'); }));

    viewSection.appendChild(viewCaption); viewSection.appendChild(viewRow);

    // Slab tools section (thickness / edge profile / rotation / island)
    var slabSection = document.createElement('div'); slabSection.className = 'rv3d-section';
    var slabCaption = document.createElement('div'); slabCaption.className = 'rv3d-caption'; slabCaption.textContent = 'Slab';
    var slabRow = document.createElement('div'); slabRow.className = 'rv3d-row';

    var thickLabel = document.createElement('span'); thickLabel.className = 'rv3d-label'; thickLabel.textContent = 'Thickness';
    var thickInput = document.createElement('input');
    thickInput.type = 'range'; thickInput.className = 'rv3d-slider'; thickInput.min = 10; thickInput.max = 60; thickInput.value = opts.thicknessMm || 35;
    thickInput.addEventListener('input', function () { call('setThickness', parseInt(thickInput.value, 10)); });

    var edgeLabel = document.createElement('span'); edgeLabel.className = 'rv3d-label'; edgeLabel.textContent = 'Edge';
    var edgeSel = document.createElement('select'); edgeSel.className = 'rv3d-select';
    ['straight', 'bullnose', 'beveled', 'ogee'].forEach(function (p) {
      var o = document.createElement('option'); o.value = p; o.textContent = p.charAt(0).toUpperCase() + p.slice(1);
      if (p === (opts.edgeProfile || 'straight')) o.selected = true;
      edgeSel.appendChild(o);
    });
    edgeSel.addEventListener('change', function () { call('setEdgeProfile', edgeSel.value); });

    var rotLabel = document.createElement('span'); rotLabel.className = 'rv3d-label'; rotLabel.textContent = 'Rotation';
    var rotInput = document.createElement('input');
    rotInput.type = 'range'; rotInput.className = 'rv3d-slider'; rotInput.min = 0; rotInput.max = 359; rotInput.value = 0;
    rotInput.addEventListener('input', function () { call('setSlabRotation', parseInt(rotInput.value, 10)); });

    slabRow.appendChild(thickLabel); slabRow.appendChild(thickInput);
    slabRow.appendChild(edgeLabel); slabRow.appendChild(edgeSel);
    slabRow.appendChild(rotLabel); slabRow.appendChild(rotInput);

    if (opts.allowIsland) {
      var islLabel = document.createElement('label'); islLabel.className = 'rv3d-check';
      var islCheck = document.createElement('input'); islCheck.type = 'checkbox';
      islCheck.addEventListener('change', function () { call('setIsland', islCheck.checked); renderSurfaceRow(); });
      islLabel.appendChild(islCheck);
      islLabel.appendChild(document.createTextNode('Island'));
      slabRow.appendChild(islLabel);
    }
    slabSection.appendChild(slabCaption); slabSection.appendChild(slabRow);

    bar.appendChild(roomSection);
    bar.appendChild(surfaceSection);
    bar.appendChild(viewSection);
    bar.appendChild(slabSection);
    wrap.innerHTML = '';
    wrap.appendChild(bar);

    renderSurfaceRow();
    return handle;
  };

  function downloadDataUrl(dataUrl, filename) {
    var a = document.createElement('a');
    a.href = dataUrl; a.download = filename;
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  }

  window.RV3D_ROOM_LABELS = ROOM_LABELS;
})();