(function () {
  'use strict';

  var ROOM_LABELS = {
    hall: 'Hall', dining: 'Dining Room', drawing: 'Drawing Room',
    bedroom: 'Bedroom', kitchen: 'Kitchen',
  };

  function mat(color, rough, metal) {
    return new THREE.MeshStandardMaterial({ color: color, roughness: rough != null ? rough : 0.8, metalness: metal || 0 });
  }
  // Polished-stone material: clearcoat sells the "sealed slab" sheen real
  // photography can't fake with a flat StandardMaterial.
  function surfaceMat() {
    return new THREE.MeshPhysicalMaterial({
      color: 0xffffff, roughness: 0.32, metalness: 0.02,
      clearcoat: 0.45, clearcoatRoughness: 0.18, reflectivity: 0.4,
      envMapIntensity: 0.9,
    });
  }
  function contactShadow(radius, opacity) {
    var c = document.createElement('canvas'); c.width = c.height = 128;
    var ctx = c.getContext('2d');
    var g = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
    g.addColorStop(0, 'rgba(0,0,0,' + opacity + ')');
    g.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = g; ctx.fillRect(0, 0, 128, 128);
    var tex = new THREE.CanvasTexture(c);
   var m = new THREE.Mesh(new THREE.PlaneGeometry(radius, radius),
      new THREE.MeshBasicMaterial({
        map: tex, transparent: true, depthWrite: false,
        polygonOffset: true, polygonOffsetFactor: -4, polygonOffsetUnits: -4,
      }));
    m.rotation.x = -Math.PI / 2;
    m.renderOrder = 1;
    return m;
  }
  function pottedPlant(x, z) {
    var g = new THREE.Group();
    var pot = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.12, 0.28, 16), mat(0x8a4a3a, 0.8));
    pot.position.y = 0.14; pot.castShadow = pot.receiveShadow = true;
    g.add(pot);
    var leafMat = mat(0x3f6b46, 0.85);
    for (var i = 0; i < 6; i++) {
      var leaf = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.55, 6), leafMat);
      var a = (i / 6) * Math.PI * 2;
      leaf.position.set(Math.cos(a) * 0.08, 0.55, Math.sin(a) * 0.08);
      leaf.rotation.z = Math.cos(a) * 0.35;
      leaf.rotation.x = Math.sin(a) * 0.35;
      leaf.castShadow = true;
      g.add(leaf);
    }
    g.add(contactShadow(0.7, 0.35));
    g.position.set(x, 0, z);
    return g;
  }
  function wallArt(x, y, z, ry) {
    var g = new THREE.Group();
    var frame = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.75, 0.03), mat(0x2a2420, 0.6));
    var art = new THREE.Mesh(new THREE.PlaneGeometry(0.46, 0.66),
      new THREE.MeshStandardMaterial({ color: 0xC7B79A, roughness: 1 }));
    art.position.z = 0.016;
    g.add(frame); g.add(art);
    g.position.set(x, y, z); g.rotation.y = ry;
    return g;
  }
  function curtainPair(x, y, z) {
    var g = new THREE.Group();
    var cMat = new THREE.MeshStandardMaterial({ color: 0xD9CBB0, roughness: 0.95, side: THREE.DoubleSide });
    var rod = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.015, 1.5, 8), mat(0x3a3a3a, 0.4, 0.6));
    rod.rotation.z = Math.PI / 2; rod.position.set(x, y + 0.75, z);
    g.add(rod);
    [-0.62, 0.62].forEach(function (off) {
      var curve = new THREE.CylinderGeometry(0.18, 0.18, 1.5, 8, 1, true, 0, Math.PI);
      var panel = new THREE.Mesh(curve, cMat);
      panel.rotation.z = Math.PI / 2;
      panel.rotation.y = off < 0 ? 0 : Math.PI;
      panel.position.set(x + off, y, z);
      panel.castShadow = true;
      g.add(panel);
    });
    return g;
  }
  function floorLamp(x, z) {
    var g = new THREE.Group();
    var base = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.16, 0.03, 20), mat(0x2a2420, 0.4, 0.5));
    base.position.y = 0.015; g.add(base);
    var pole = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.02, 1.3, 8), mat(0x2a2420, 0.4, 0.5));
    pole.position.y = 0.68; g.add(pole);
    var shade = new THREE.Mesh(new THREE.ConeGeometry(0.22, 0.32, 20, 1, true),
      new THREE.MeshStandardMaterial({ color: 0xF3E6C8, roughness: 0.9, side: THREE.DoubleSide, emissive: 0xF3E6C8, emissiveIntensity: 0.25 }));
    shade.position.y = 1.5; g.add(shade);
    var bulb = new THREE.PointLight(0xffdca8, 0.6, 3.2);
    bulb.position.y = 1.4; g.add(bulb);
    g.add(contactShadow(0.7, 0.3));
    g.position.set(x, 0, z);
    return g;
  }
  function pendantLight(x, z, roomH) {
    var g = new THREE.Group();
    var cord = new THREE.Mesh(new THREE.CylinderGeometry(0.007, 0.007, 0.5, 6), mat(0x1a1a1a, 0.5));
    cord.position.y = roomH - 0.25; g.add(cord);
    var shade = new THREE.Mesh(new THREE.ConeGeometry(0.16, 0.18, 20, 1, true),
      new THREE.MeshStandardMaterial({ color: 0x2a2420, roughness: 0.5, side: THREE.DoubleSide }));
    shade.rotation.x = Math.PI; shade.position.y = roomH - 0.52; g.add(shade);
   var bulb = new THREE.PointLight(0xfff0d2, 0.85, 5, 2);
    bulb.position.y = roomH - 0.58;
    bulb.castShadow = false;
    g.add(bulb);
    g.position.set(x, 0, z);
    return g;
  }

  function buildShell(group, w, h, d, wallColor) {
    var floor = new THREE.Mesh(new THREE.PlaneGeometry(w, d), surfaceMat());
    floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true;
    group.add(floor);

    var ceiling = new THREE.Mesh(new THREE.PlaneGeometry(w, d), mat(0xFAF8F4, 0.95));
    ceiling.rotation.x = Math.PI / 2; ceiling.position.y = h;
    group.add(ceiling);

    var backWall = new THREE.Mesh(new THREE.PlaneGeometry(w, h), mat(wallColor, 0.92));
    backWall.position.set(0, h / 2, -d / 2); backWall.receiveShadow = true;
    group.add(backWall);

    var sideWall = new THREE.Mesh(new THREE.PlaneGeometry(d, h), mat(wallColor, 0.92));
    sideWall.rotation.y = Math.PI / 2; sideWall.position.set(-w / 2, h / 2, 0); sideWall.receiveShadow = true;
    group.add(sideWall);

    // Crown molding line + baseboards for a finished, professional edge
    var trimMat = mat(0xffffff, 0.55);
    var crown = new THREE.Mesh(new THREE.BoxGeometry(w, 0.06, 0.06), trimMat);
    crown.position.set(0, h - 0.03, -d / 2 + 0.03); group.add(crown);
    var crownSide = new THREE.Mesh(new THREE.BoxGeometry(d, 0.06, 0.06), trimMat);
    crownSide.rotation.y = Math.PI / 2; crownSide.position.set(-w / 2 + 0.03, h - 0.03, 0); group.add(crownSide);

    var bb1 = new THREE.Mesh(new THREE.BoxGeometry(w, 0.09, 0.02), trimMat);
    bb1.position.set(0, 0.045, -d / 2 + 0.01); group.add(bb1);
    var bb2 = new THREE.Mesh(new THREE.BoxGeometry(d, 0.09, 0.02), trimMat);
    bb2.rotation.y = Math.PI / 2; bb2.position.set(-w / 2 + 0.01, 0.045, 0); group.add(bb2);

    // Window + soft daylight glow plane + curtains
    var wx = w * 0.28, wy = h * 0.58;
    var frameMat = mat(0xffffff, 0.7);
    var wf = new THREE.Mesh(new THREE.BoxGeometry(1.1, 1.3, 0.06), frameMat);
    wf.position.set(wx, wy, -d / 2 + 0.03); group.add(wf);
    var pane = new THREE.Mesh(new THREE.PlaneGeometry(0.9, 1.1),
      new THREE.MeshBasicMaterial({ color: 0xfff8e6 }));
    pane.position.set(wx, wy, -d / 2 + 0.061); group.add(pane);
    var mv = new THREE.Mesh(new THREE.BoxGeometry(0.03, 1.1, 0.03), frameMat);
    mv.position.copy(pane.position); group.add(mv);
    var mh = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.03, 0.03), frameMat);
    mh.position.copy(pane.position); group.add(mh);
    group.add(curtainPair(wx, wy, -d / 2 + 0.12));

    // Simple door cut-out suggestion on side wall (frame only, closed look)
    var doorFrame = new THREE.Mesh(new THREE.BoxGeometry(0.06, 1.9, 0.85), frameMat);
    doorFrame.position.set(-w / 2 + 0.03, 0.95, d * 0.28); group.add(doorFrame);

    group.add(wallArt(0.9, h * 0.55, -d / 2 + 0.035, 0));
    group.add(pottedPlant(w / 2 - 0.4, d / 2 - 0.4));
    group.add(pendantLight(0, -0.3, h));

    return { floor: floor, wall: backWall, sidewall: sideWall };
  }

  function addRug(group, x, z, radius, color) {
    var rug = new THREE.Mesh(new THREE.CircleGeometry(radius, 48), mat(color, 1));
   rug.material.polygonOffset = true;
    rug.material.polygonOffsetFactor = -2;
    rug.material.polygonOffsetUnits = -2;
    rug.rotation.x = -Math.PI / 2; rug.position.set(x, 0.01, z); rug.receiveShadow = true;
    rug.renderOrder = 1;
    group.add(rug);
    return rug;
  }

  var ROOM_BUILDERS = {
    kitchen: function (group) {
      var s = buildShell(group, 4, 2.7, 4, 0xEFEAE0);
      var cg = new THREE.Group();
      var top = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.04, 0.65), surfaceMat());
      top.position.y = 0.92; top.castShadow = top.receiveShadow = true;
      cg.add(top);
      var cab = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.88, 0.6), mat(0xF4F1EA, 0.75));
      cab.position.y = 0.46; cab.castShadow = cab.receiveShadow = true;
      cg.add(cab);
      // Cabinet handles for detail
      for (var i = -1; i <= 1; i++) {
        var h = new THREE.Mesh(new THREE.CylinderGeometry(0.01, 0.01, 0.14, 6), mat(0x2a2420, 0.4, 0.7));
        h.rotation.z = Math.PI / 2; h.position.set(i * 0.65, 0.75, 0.31);
        cg.add(h);
      }
      cg.position.set(0, 0, -1.55);
      group.add(cg);
      var kshadow = contactShadow(2.6, 0.28);
      kshadow.position.set(0, 0.001, -1.55);
      group.add(kshadow);
      return { surfaces: s, camPos: [0, 1.5, 4.2], camTarget: [0, 1.1, 0] };
    },

    bedroom: function (group) {
      var s = buildShell(group, 4.2, 2.7, 4.2, 0xF2ECE3);
      var bg = new THREE.Group();
      var frame = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.35, 2.1), mat(0x7a5f45, 0.65));
      frame.position.y = 0.175; frame.castShadow = frame.receiveShadow = true; bg.add(frame);
      var mattress = new THREE.Mesh(new THREE.BoxGeometry(1.7, 0.25, 2.0), mat(0xffffff, 0.9));
      mattress.position.y = 0.475; mattress.castShadow = mattress.receiveShadow = true; bg.add(mattress);
      var quilt = new THREE.Mesh(new THREE.BoxGeometry(1.72, 0.06, 1.3), mat(0xB6C2B0, 0.85));
      quilt.position.set(0, 0.62, 0.3); bg.add(quilt);
      [-0.4, 0.4].forEach(function (px) {
        var p = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.12, 0.4), mat(0xf5f0e6, 0.9));
        p.position.set(px, 0.66, -0.75); bg.add(p);
      });
      var headboard = new THREE.Mesh(new THREE.BoxGeometry(1.9, 0.9, 0.1), mat(0x7a5f45, 0.65));
      headboard.position.set(0, 0.75, -1.1); headboard.castShadow = true; bg.add(headboard);
      bg.position.set(0.3, 0, -0.5);
     group.add(bg);
      var bshadow = contactShadow(2.4, 0.25);
      bshadow.position.set(0.3, 0.001, -0.5);
      group.add(bshadow);
      addRug(group, -0.6, 1.0, 0.9, 0xC9BBA6);
      group.add(floorLamp(-1.6, -1.4));
      return { surfaces: s, camPos: [0.5, 1.5, 4.4], camTarget: [0.3, 1.0, -0.3] };
    },

    drawing: function (group) {
      var s = buildShell(group, 4.4, 2.7, 4.4, 0xEDE7DC);
      var sg = new THREE.Group();
      var seat = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.4, 0.85), mat(0x5f6f5b, 0.8));
      seat.position.y = 0.35; seat.castShadow = seat.receiveShadow = true; sg.add(seat);
      var back = new THREE.Mesh(new THREE.BoxGeometry(2.0, 0.55, 0.22), mat(0x5f6f5b, 0.8));
      back.position.set(0, 0.68, -0.32); back.castShadow = true; sg.add(back);
      var armL = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.55, 0.85), mat(0x5f6f5b, 0.8));
      armL.position.set(-1.0, 0.55, 0); armL.castShadow = true; sg.add(armL);
      var armR = armL.clone(); armR.position.x = 1.0; sg.add(armR);
      // Cushions
      [-0.55, 0.55].forEach(function (cx) {
        var c = new THREE.Mesh(new THREE.BoxGeometry(0.75, 0.15, 0.75), mat(0x8a9a7f, 0.9));
        c.position.set(cx, 0.58, 0.02); sg.add(c);
      });
      sg.position.set(-0.6, 0, 1.1);
     group.add(sg);
      var sshadow = contactShadow(2.6, 0.3);
      sshadow.position.set(-0.6, 0.001, 1.1);
      group.add(sshadow);

      var table = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.08, 0.5), surfaceMat());
      table.position.set(-0.6, 0.28, 0.15); table.castShadow = table.receiveShadow = true;
      group.add(table);
      var tLeg = new THREE.CylinderGeometry(0.03, 0.03, 0.28, 8);
      [[-0.35, -0.15], [0.35, -0.15], [-0.35, 0.45], [0.35, 0.45]].forEach(function (p) {
        var leg = new THREE.Mesh(tLeg, mat(0x3a2c1e, 0.5));
        leg.position.set(-0.6 + p[0], 0.14, 0.15 + (p[1] - 0.15)); group.add(leg);
      });
      addRug(group, -0.6, 1.0, 1.1, 0xB9A98E);
      group.add(floorLamp(1.6, -1.5));
      group.add(wallArt(-1.6, s.sidewall.position.y, -0.4, Math.PI / 2));
      return { surfaces: s, camPos: [0.6, 1.5, 4.6], camTarget: [-0.3, 1.0, 0.3] };
    },

    dining: function (group) {
      var s = buildShell(group, 4.2, 2.7, 4.2, 0xE9E2D4);
      var top = new THREE.Mesh(new THREE.CylinderGeometry(0.85, 0.85, 0.06, 32), surfaceMat());
      top.position.set(0, 0.75, -0.2); top.castShadow = top.receiveShadow = true; group.add(top);
      var pedestal = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.14, 0.7, 16), mat(0x3a2c1e, 0.5));
     pedestal.position.set(0, 0.35, -0.2); pedestal.castShadow = true; group.add(pedestal);
      var dshadow = contactShadow(2.2, 0.28);
      dshadow.position.set(0, 0.001, -0.2);
      group.add(dshadow);

      var chairGeo = new THREE.BoxGeometry(0.4, 0.08, 0.4);
      var chairBackGeo = new THREE.BoxGeometry(0.4, 0.5, 0.06);
      var chairMat = mat(0x6b5844, 0.7);
      [[0, -1.15, 0], [0, 0.75, Math.PI], [-1.15, -0.2, Math.PI / 2], [1.15, -0.2, -Math.PI / 2]]
        .forEach(function (c) {
          var seat = new THREE.Mesh(chairGeo, chairMat);
          seat.position.set(c[0], 0.45, c[1]); seat.castShadow = seat.receiveShadow = true; group.add(seat);
          var back = new THREE.Mesh(chairBackGeo, chairMat);
          back.position.set(c[0], 0.74, c[1]); back.rotation.y = c[2]; back.translateZ(0.17);
          group.add(back);
        });
      group.add(pendantLight(0, -0.2, 2.7));
      return { surfaces: s, camPos: [0.4, 1.6, 4.6], camTarget: [0, 0.9, -0.2] };
    },

    hall: function (group) {
      var s = buildShell(group, 4.6, 2.9, 4.6, 0xF0EDE6);
      var cons = new THREE.Mesh(new THREE.BoxGeometry(1.3, 0.75, 0.35), mat(0x6b5844, 0.6));
      cons.position.set(1.2, 0.375, -1.9); cons.castShadow = cons.receiveShadow = true; group.add(cons);
      var mirror = new THREE.Mesh(new THREE.PlaneGeometry(0.7, 0.9),
        new THREE.MeshPhysicalMaterial({ color: 0xcfd8dc, roughness: 0.05, metalness: 0.9, clearcoat: 1 }));
     mirror.position.set(1.2, 1.5, -2.24); group.add(mirror);
      var hshadow = contactShadow(1.8, 0.25);
      hshadow.position.set(1.2, 0.001, -1.9);
      group.add(hshadow);
      addRug(group, 0, 0.8, 1.0, 0xC2B49A);
      group.add(pottedPlant(-1.8, -1.8));
      return { surfaces: s, camPos: [0, 1.6, 4.8], camTarget: [0, 1.1, -0.4] };
    },
  };

  function loadCroppedTexture(renderer, url, cropPct, cb) {
    var img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function () {
      var sx = img.width * cropPct, sy = img.height * cropPct;
      var sw = img.width - sx * 2, sh = img.height - sy * 2;
      var canvas = document.createElement('canvas');
      canvas.width = sw; canvas.height = sh;
     var ctx2d = canvas.getContext('2d');
      ctx2d.filter = 'contrast(1.22) brightness(0.82) saturate(1.05)';
      ctx2d.drawImage(img, sx, sy, sw, sh, 0, 0, sw, sh);
      ctx2d.filter = 'none';
      var tex = new THREE.CanvasTexture(canvas);
      tex.encoding = THREE.sRGBEncoding;
      tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
      tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
      cb(tex);
    };
    img.onerror = function () {};
    img.src = url;
  }
  function repeatForKey(tex, key) {
    if (key === 'floor') tex.repeat.set(3, 3);
    else if (key === 'counter') tex.repeat.set(1.4, 0.6);
    else tex.repeat.set(2, 1.6);
  }

  window.RoomVisualizer3D = function (containerId, opts) {
    opts = opts || {};
    var container = document.getElementById(containerId);
    if (!container || !window.THREE) return;

    var textureUrl = opts.textureUrl;
    var width = container.clientWidth || 600;
    var height = opts.height || 420;

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0xC9C2B4);
    scene.fog = new THREE.Fog(0xC9C2B4, 7, 15);

    var camera = new THREE.PerspectiveCamera(42, width / height, 0.1, 100);

 var renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 0.95;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    container.innerHTML = '';
    container.appendChild(renderer.domElement);

    var controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.minDistance = 1.8;
    controls.maxDistance = 8;
    controls.maxPolarAngle = Math.PI * 0.495;
    controls.autoRotate = false;
    controls.autoRotateSpeed = 0.8;

    // ── Three-point + practical lighting ──────────────────────────────────
    scene.add(new THREE.HemisphereLight(0xfff4e6, 0x33302c, 0.5));
    var keyLight = new THREE.DirectionalLight(0xfff1dc, 1.0);
    keyLight.castShadow = true;
    keyLight.shadow.mapSize.set(1536, 1536);
    keyLight.shadow.camera.near = 0.5; keyLight.shadow.camera.far = 14;
    keyLight.shadow.camera.left = -4; keyLight.shadow.camera.right = 4;
    keyLight.shadow.camera.top = 4; keyLight.shadow.camera.bottom = -4;
   keyLight.shadow.bias = -0.0006;
    keyLight.shadow.normalBias = 0.02;
    keyLight.shadow.radius = 2;
    scene.add(keyLight);
    var fillLight = new THREE.PointLight(0xffffff, 0.2, 10);
    scene.add(fillLight);
    var rimLight = new THREE.DirectionalLight(0xcfe0ff, 0.25);
    scene.add(rimLight);
    var windowGlow = new THREE.PointLight(0xfff2cc, 0.45, 6);
    scene.add(windowGlow);

    var roomGroup = null, surfaces = {}, activeKey = null, currentTexture = null;

    function applyTexture() {
      if (!currentTexture) return;
      Object.keys(surfaces).forEach(function (k) {
        var m = surfaces[k].material;
        if (k === activeKey) { m.map = currentTexture; m.color.set(0xffffff); }
        else { m.map = null; m.color.set(k === 'counter' || k === 'floor' ? 0xffffff : 0xEFEAE0); }
        m.needsUpdate = true;
      });
    }

    function buildRoom(roomKey) {
      if (!ROOM_BUILDERS[roomKey]) roomKey = 'kitchen';
      if (roomGroup) scene.remove(roomGroup);
      roomGroup = new THREE.Group();
      var built = ROOM_BUILDERS[roomKey](roomGroup);
      scene.add(roomGroup);
      surfaces = built.surfaces;

      keyLight.position.set(built.camPos[0] + 1.5, built.camPos[1] + 2.8, built.camPos[2] - 0.5);
      keyLight.target.position.set(built.camTarget[0], 0, built.camTarget[2]);
      scene.add(keyLight.target);
      rimLight.position.set(built.camTarget[0] - 2, 2.2, built.camTarget[2] - 3);
      fillLight.position.set(built.camTarget[0] - 2, 1.4, built.camTarget[2] + 2);
      windowGlow.position.set(1.6, 1.6, -1.9);

      camera.position.set(built.camPos[0], built.camPos[1], built.camPos[2]);
      controls.target.set(built.camTarget[0], built.camTarget[1], built.camTarget[2]);
      controls.update();

      if (!surfaces[activeKey]) activeKey = Object.keys(surfaces)[0];
      applyTexture();
    }

    buildRoom(opts.room || 'kitchen');

    loadCroppedTexture(renderer, textureUrl, 0.10, function (tex) {
      currentTexture = tex;
      repeatForKey(tex, activeKey);
      applyTexture();
    });

    window['rv3d_setRoom_' + containerId] = function (roomKey) {
      buildRoom(roomKey);
      if (currentTexture) { repeatForKey(currentTexture, activeKey); applyTexture(); }
    };
    window['rv3d_setSurface_' + containerId] = function (surfKey) {
      if (!surfaces[surfKey]) return;
      activeKey = surfKey;
      if (currentTexture) { repeatForKey(currentTexture, surfKey); applyTexture(); }
    };
    window['rv3d_getSurfaces_' + containerId] = function () { return Object.keys(surfaces); };
    window['rv3d_getRoomLabel'] = function (k) { return ROOM_LABELS[k] || k; };
    window['rv3d_toggleAutoRotate_' + containerId] = function () {
      controls.autoRotate = !controls.autoRotate;
      return controls.autoRotate;
    };
    window['rv3d_snapshot_' + containerId] = function () {
      renderer.render(scene, camera);
      return renderer.domElement.toDataURL('image/jpeg', 0.94);
    };

    (function animate() {
      requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    })();

    window.addEventListener('resize', function () {
      var w = container.clientWidth || width;
      camera.aspect = w / height;
      camera.updateProjectionMatrix();
      renderer.setSize(w, height);
    });
  };

  window.RV3D_ROOM_LABELS = ROOM_LABELS;
})();