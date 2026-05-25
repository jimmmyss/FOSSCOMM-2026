/* FOSSCOMM 2026 — front-end client islands.
 * Hand-rolled vanilla JS; no React. Tiny on purpose.
 * Each block self-mounts if its anchor exists; safe to load on any page.
 */
(function () {
    'use strict';

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- Countdown ticker in the status bar ----------
    (function countdown() {
        var startIso = (window.FC_DATA && window.FC_DATA.eventStart) || '2026-10-17T09:00:00+03:00';
        var target = new Date(startIso).getTime();
        var el = document.querySelector('[data-fc-countdown]');
        if (!el) return;
        function tick() {
            var diff = target - Date.now();
            if (diff < 0) { el.textContent = 'LIVE NOW'; return; }
            var s = Math.floor(diff / 1000);
            var d = Math.floor(s / 86400);
            var h = Math.floor((s % 86400) / 3600);
            var m = Math.floor((s % 3600) / 60);
            el.textContent = 'T-' + String(d).padStart(3, '0') + 'd ' + String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm';
        }
        tick();
        setInterval(tick, 30000);
    })();

    // ---------- Section nav active-link highlight ----------
    (function sectionNav() {
        var nav = document.querySelector('[data-fc-island="section-nav"]');
        if (!nav) return;
        var links = Array.from(nav.querySelectorAll('[data-fc-nav-target]'));
        var sections = links.map(function (a) {
            return document.getElementById(a.getAttribute('data-fc-nav-target'));
        }).filter(Boolean);
        if (!sections.length || !('IntersectionObserver' in window)) return;
        var io = new IntersectionObserver(function (entries) {
            var visible = entries.filter(function (e) { return e.isIntersecting; })
                .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });
            if (!visible[0]) return;
            var id = visible[0].target.id;
            links.forEach(function (a) {
                a.classList.toggle('text-accent', a.getAttribute('data-fc-nav-target') === id);
            });
        }, { rootMargin: '-30% 0px -60% 0px', threshold: [0, 0.25, 0.5, 1] });
        sections.forEach(function (el) { io.observe(el); });
    })();

    // ---------- Schedule filter bar: DAY // ROOM // CATEGORY (native selects) ----------
    (function scheduleFilter() {
        document.querySelectorAll('[data-fc-island="schedule-filter"]').forEach(function (root) {
            var daySel   = root.querySelector('[data-fc-filter="day"]');
            var roomSel  = root.querySelector('[data-fc-filter="room"]');
            var trackSel = root.querySelector('[data-fc-filter="track"]');
            function val(sel) { return sel ? sel.value : 'all'; }

            function applyFilters() {
                // Empty value = the placeholder label (DAY/ROOM/CATEGORY) = no filter.
                var day = val(daySel), room = val(roomSel), track = val(trackSel);
                // Day filters whole day-lists (no day picked → show both).
                root.querySelectorAll('[data-fc-day-list]').forEach(function (list) {
                    list.hidden = (!!day && list.getAttribute('data-fc-day-list') !== day);
                });
                // Room + category filter individual rows.
                root.querySelectorAll('[data-fc-session]').forEach(function (li) {
                    var liRoom = li.getAttribute('data-fc-room') || '';
                    var tracks = (li.getAttribute('data-fc-tracks') || '').split(/\s+/).filter(Boolean);
                    var roomOK  = !room  || liRoom === room;
                    var trackOK = !track || tracks.indexOf(track) !== -1;
                    li.hidden = !(roomOK && trackOK);
                });
            }

            // Size each <select> to its CURRENT option's text (not the widest option),
            // so the bar reads like the status bar — segments only as wide as their text.
            function fitWidth(sel) {
                var opt = sel.options[sel.selectedIndex];
                var cs  = getComputedStyle(sel);
                var span = document.createElement('span');
                span.style.position   = 'absolute';
                span.style.visibility = 'hidden';
                span.style.whiteSpace = 'pre';
                span.style.fontFamily    = cs.fontFamily;
                span.style.fontSize      = cs.fontSize;
                span.style.fontWeight    = cs.fontWeight;
                span.style.letterSpacing = cs.letterSpacing;
                span.style.textTransform = cs.textTransform;
                span.textContent = opt ? opt.text : '';
                document.body.appendChild(span);
                sel.style.width = Math.ceil(span.getBoundingClientRect().width + 2) + 'px';
                document.body.removeChild(span);
            }

            [daySel, roomSel, trackSel].forEach(function (sel) {
                if (!sel) return;
                fitWidth(sel);
                sel.addEventListener('change', function () {
                    fitWidth(sel);
                    applyFilters();
                });
            });
            applyFilters();
        });
    })();

    // ---------- FAQ accordion ----------
    (function faqAccordion() {
        document.querySelectorAll('[data-fc-island="faq-list"]').forEach(function (list) {
            list.querySelectorAll('[data-fc-faq-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var item = btn.closest('[data-fc-faq-item]');
                    var body = item && item.querySelector('[data-fc-faq-body]');
                    var marker = btn.querySelector('[data-fc-faq-marker]');
                    if (!body) return;
                    var open = !body.classList.contains('hidden');
                    body.classList.toggle('hidden', open);
                    if (marker) marker.textContent = open ? '[+]' : '[−]';
                });
            });
        });
    })();

    // ---------- Glyph scramble ----------
    (function scramble() {
        if (reducedMotion) return;
        var glyphs = 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ░▒▓0123456789@#';
        document.querySelectorAll('[data-fc-island="scramble"]').forEach(function (el) {
            var target = el.textContent || '';
            var payload = el.getAttribute('data-fc-payload');
            var delay = 0;
            if (payload) {
                try { delay = (JSON.parse(payload).delay) || 0; } catch (e) {}
            }
            var duration = 700 + target.length * 25;
            setTimeout(function () { runScramble(el, target, duration); }, delay);
        });

        function runScramble(el, target, duration) {
            var start = performance.now();
            var len = target.length;
            function frame(t) {
                var progress = Math.min(1, (t - start) / duration);
                var lockedChars = Math.floor(progress * len);
                var out = '';
                for (var i = 0; i < len; i++) {
                    if (i < lockedChars) {
                        out += target[i];
                    } else if (target[i] === ' ' || target[i] === '\n') {
                        out += target[i];
                    } else {
                        out += glyphs[Math.floor(Math.random() * glyphs.length)];
                    }
                }
                el.textContent = out;
                if (progress < 1) {
                    requestAnimationFrame(frame);
                } else {
                    el.textContent = target;
                }
            }
            requestAnimationFrame(frame);
        }
    })();

    // (Hero background grain is now pure CSS — see .fc-hero-dots in assets/site.css.)

    // ---------- ASCII globe (venue section) ----------
    // Strategy B: real Natural Earth 110m land polygons rasterized once at init
    // into a 720×360 Uint8Array mask via scanline algorithm. Runtime queries are
    // O(1) mask lookups — fast on any device. Drag to rotate, wheel/pinch to zoom
    // continuously, pin overlays read from data-fc-pins JSON.
    (function asciiGlobe() {
        var mounts = document.querySelectorAll('[data-fc-island="ascii-globe"]');
        if (!mounts.length) return;

        var COASTLINES = (typeof window !== 'undefined' && window.FC_COASTLINES) || [];
        if (!COASTLINES.length) return;

        // --- One-time: precompute cartesian unit-sphere coords for every coastline vertex.
        // Each polygon gets a Float32Array [x0,y0,z0, x1,y1,z1, ...] so per-frame rendering
        // is just N matrix-multiplies (cheap) instead of N trig calls. Also caches a centroid
        // direction + geodesic radius for fast back-hemisphere culling.
        var DEG_RAD = Math.PI / 180;
        var POLY_XYZ = new Array(COASTLINES.length);
        var POLY_CENTROID = new Float32Array(COASTLINES.length * 3); // cx, cy, cz per poly
        var POLY_COSR = new Float32Array(COASTLINES.length);          // cos(geodesic radius)
        // Precomputed `-sin(radius)` per polygon — the actual back-cull threshold.
        // Storing it saves a Math.sqrt per polygon per frame in render() (~hundreds
        // of square roots per render → zero). Done once here.
        var POLY_BACKCULL = new Float32Array(COASTLINES.length);
        (function precomputeCartesian() {
            for (var pi = 0; pi < COASTLINES.length; pi++) {
                var src = COASTLINES[pi];
                var n = src.length / 2;
                var dst = new Float32Array(n * 3);
                var sx = 0, sy = 0, sz = 0;
                for (var i = 0, j = 0; i < n; i++, j += 3) {
                    var lat = src[i * 2] * DEG_RAD;
                    var lon = src[i * 2 + 1] * DEG_RAD;
                    var cosLat = Math.cos(lat);
                    var x = cosLat * Math.sin(lon);
                    var y = Math.sin(lat);
                    var z = cosLat * Math.cos(lon);
                    dst[j] = x; dst[j + 1] = y; dst[j + 2] = z;
                    sx += x; sy += y; sz += z;
                }
                POLY_XYZ[pi] = dst;
                // Normalize the centroid direction.
                var cmag = Math.sqrt(sx * sx + sy * sy + sz * sz) || 1;
                var cx = sx / cmag, cy = sy / cmag, cz = sz / cmag;
                POLY_CENTROID[pi * 3]     = cx;
                POLY_CENTROID[pi * 3 + 1] = cy;
                POLY_CENTROID[pi * 3 + 2] = cz;
                // Geodesic radius = max angle from centroid to any vertex.
                // cos(radius) = min(dot(centroid, vertex)). We'll cache this min dot.
                var minDot = 1;
                for (var k = 0; k < n; k++) {
                    var d = dst[k * 3] * cx + dst[k * 3 + 1] * cy + dst[k * 3 + 2] * cz;
                    if (d < minDot) minDot = d;
                }
                POLY_COSR[pi] = minDot;
                POLY_BACKCULL[pi] = -Math.sqrt(1 - minDot * minDot);
            }
        })();

        // ---- Per-mount setup ----
        // Mobile = viewport narrower than the lg breakpoint (1024px), matching the
        // venue template's `lg:hidden` / `hidden lg:block` split. We deliberately do
        // NOT use `(hover: none)` here — some hybrid desktops (touchscreen laptops,
        // Windows in tablet mode) report no hover even when a mouse is attached,
        // which would silently disable hover/select on the globe for those users.
        // The check is live so rotating a tablet flips the mode without reload.
        var mqMobile = window.matchMedia && window.matchMedia('(max-width: 1023.98px)');
        var isTouchDevice = mqMobile ? mqMobile.matches : false;
        if (mqMobile && mqMobile.addEventListener) {
            mqMobile.addEventListener('change', function (e) { isTouchDevice = e.matches; });
        } else if (mqMobile && mqMobile.addListener) {
            mqMobile.addListener(function (e) { isTouchDevice = e.matches; }); // Safari < 14
        }

        Array.prototype.forEach.call(mounts, function (mount) {
            // Editions = both globe pins AND year browser data.
            var editionsAttr = mount.getAttribute('data-fc-editions');
            var editions = [];
            if (editionsAttr) {
                try { editions = JSON.parse(editionsAttr) || []; } catch (e) { editions = []; }
            }
            var pins = editions.filter(function (ed) {
                var la = parseFloat(ed.lat), lo = parseFloat(ed.lon);
                return !isNaN(la) && !isNaN(lo);
            }).map(function (ed) {
                return {
                    label:      String(ed.year || ''),
                    city:       String(ed.city || ''),
                    lat:        parseFloat(ed.lat),
                    lon:        parseFloat(ed.lon),
                    isCurrent:  !!ed.current,
                    year:       parseInt(ed.year, 10) || 0,
                    url:        String(ed.url || '')
                };
            });

            // Co-located editions (same lat/lon — e.g. multiple Athens years)
            // would otherwise paint a single dot with one label on top, hiding
            // the rest. Group by rounded lat/lon and assign each pin an index
            // inside its group so drawPin() can fan the leader+label out at
            // an angle (see stackAngle()).
            var stackByYear = {};
            (function () {
                var stacks = {};
                pins.forEach(function (p) {
                    var k = p.lat.toFixed(6) + ',' + p.lon.toFixed(6);
                    (stacks[k] = stacks[k] || []).push(p);
                });
                Object.keys(stacks).forEach(function (k) {
                    var grp = stacks[k];
                    grp.sort(function (a, b) { return a.year - b.year; });
                    grp.forEach(function (p, idx) {
                        p.stackIndex = idx;
                        p.stackTotal = grp.length;
                        stackByYear[p.year] = { idx: idx, total: grp.length };
                    });
                });
            })();
            // Fan-out angle in degrees for pin `idx` of `total` co-located pins.
            // 45° per slot, capped at 160° total so the outermost leader doesn't
            // dip below horizontal. Single pin → 0° (straight up, unchanged).
            function stackAngle(idx, total) {
                if (!total || total <= 1) return 0;
                var spread = Math.min(160, 45 * (total - 1));
                return -spread / 2 + spread * (idx / (total - 1));
            }
            var clusterLabel = mount.getAttribute('data-fc-cluster-label') || '';

            // Cluster (zoomed-out) pin position = centroid of pins.
            var clusterLat = 0, clusterLon = 0;
            if (pins.length) {
                for (var pIdx = 0; pIdx < pins.length; pIdx++) {
                    clusterLat += pins[pIdx].lat;
                    clusterLon += pins[pIdx].lon;
                }
                clusterLat /= pins.length;
                clusterLon /= pins.length;
            }

            // Find a sensible "current" pin to orient on; fallback to first pin or cluster centroid.
            var primaryPin = null;
            for (var pi3 = 0; pi3 < pins.length; pi3++) {
                if (pins[pi3].isCurrent) { primaryPin = pins[pi3]; break; }
            }
            if (!primaryPin && pins.length) primaryPin = pins[0];
            var defaultLat = primaryPin ? primaryPin.lat : 20;
            var defaultLon = primaryPin ? primaryPin.lon : 0;

            // The disc is drawn with its centre at y = SURFACE_H × 5/7 ≈ 71% of
            // the box height (cyPx in render()), so the bottom ~30% of the disc
            // extends past the section's bottom border and is clipped. That puts
            // the visible centre of the disc at y = SURFACE_H / 2, not at cyPx.
            // Without a pitch offset, looking at `defaultLat` head-on projects
            // the pin to the disc's geometric centre (cyPx) — which on screen
            // looks "below the middle of the visible globe". This offset rotates
            // the view down by enough to push the pin up to the visible centre.
            //
            //   0.3 = (cyPx − SURFACE_H/2) / rPx-at-zoom-1
            //       = (325 − 227.5) / 325
            //
            // Divided by zoom so the pixel shift stays constant at every zoom
            // level: ry·rPx = sin(asin(0.3/z))·(cxPx·z) = 0.3·cxPx ≈ 97.5 px.
            var PIN_CENTER_PITCH_NORM = 0.3;
            function pitchOffsetForZoom(z) {
                return Math.asin(Math.min(1, PIN_CENTER_PITCH_NORM / z));
            }

            // Render state vs animation targets. Easing brings the rendered values
            // toward the targets each frame, giving smooth zoom/reset/rotation transitions.
            var yaw   = defaultLon * Math.PI / 180;
            var pitch = defaultLat * Math.PI / 180 - pitchOffsetForZoom(1);
            var zoom  = 1;
            var targetYaw   = yaw;
            var targetPitch = pitch;
            var targetZoom  = zoom;

            // Interaction & idle tracking for auto-rotation.
            var lastInteraction = performance.now();
            var IDLE_MS = 2000;
            var AUTO_ROT_RAD_PER_FRAME = reducedMotion ? 0 : 0.0018;
            var EASE_ROT   = reducedMotion ? 1 : 0.14;
            var EASE_ZOOM  = reducedMotion ? 1 : 0.18;

            // Mouse-inside tracking: suppress auto-rotation when zoomed in.
            var mouseInside = false;
            var mouseLeaveTime = 0;
            var RESET_AFTER_LEAVE_MS = 10000;

            // Year browser: currently selected edition and hover state.
            var selectedEdition = null;   // { year, city, lat, lon, url }
            var hoveredEdition = null;    // { year, city, lat, lon }

            // Cluster ↔ individual pin: binary switch (0 or 1), no gradient fade.
            var CLUSTER_ZOOM_THRESHOLD = 2.5;

            mount.innerHTML = '';
            mount.style.position = 'relative';

            // 70% visible layout: box aspect 10/7 means the disc (whose diameter equals
            // box width) extends 30% past the bottom edge of the box, where the page's
            // natural sub-section divider line meets it. overflow:hidden clips SVG and
            // pins past that line.
            var box = document.createElement('div');
            box.className = 'select-none';
            box.style.position = 'relative';
            box.style.aspectRatio = '10 / 7';
            box.style.overflow = 'hidden';
            box.style.touchAction = 'none';
            box.style.cursor = 'grab';
            box.tabIndex = 0;
            box.style.outline = 'none';
            mount.appendChild(box);

            // Cost cap: render the globe into a fixed-pixel surface, then CSS-scale
            // it to fill the box. SVG path rasterization is ~O(width²); capping the
            // rasterized width caps the per-frame cost across all viewports. The
            // browser composites the scaled surface on the GPU (cheap). Controls +
            // flipBtn stay in `box`, NOT in `surface`, so their UI stays crisp.
            var SURFACE_W = 650;
            var SURFACE_H = SURFACE_W * 7 / 10;
            var surface = document.createElement('div');
            surface.style.position = 'absolute';
            surface.style.top = '0';
            surface.style.left = '0';
            surface.style.width  = SURFACE_W + 'px';
            surface.style.height = SURFACE_H + 'px';
            surface.style.transformOrigin = 'top left';
            surface.style.willChange = 'transform';
            box.appendChild(surface);

            function fitSurface() {
                var r = box.getBoundingClientRect();
                if (r.width <= 0) return;
                surface.style.transform = 'scale(' + (r.width / SURFACE_W) + ')';
            }
            fitSurface();
            window.addEventListener('resize', fitSurface, { passive: true });
            if ('ResizeObserver' in window) new ResizeObserver(fitSurface).observe(box);

            // (No canvas — rendering is done via SVG paths layered inside the SVG below.)

            // SVG layer: full disc outline (circle) + equator + prime meridian arcs.
            // All updated per frame to track yaw/pitch/zoom.
            var SVG_NS = 'http://www.w3.org/2000/svg';
            var svg = document.createElementNS(SVG_NS, 'svg');
            svg.setAttribute('width', '100%');
            svg.setAttribute('height', '100%');
            svg.style.position = 'absolute';
            svg.style.inset = '0';
            svg.style.pointerEvents = 'none';
            svg.style.overflow = 'hidden';
            // Ocean disc (paper-white circle behind the land).
            var oceanCircle = document.createElementNS(SVG_NS, 'circle');
            oceanCircle.setAttribute('fill', '#FAFAF7');
            oceanCircle.setAttribute('stroke', 'none');
            svg.appendChild(oceanCircle);

            // Land — a single path rebuilt per frame from projected coastline polygons.
            // clip-path keeps the fill bounded to the disc even if a stray sliver of the
            // rotated polygon math wanders past the rim.
            var clipPathId = 'fc-globe-clip-' + Math.random().toString(36).slice(2, 8);
            var defs = document.createElementNS(SVG_NS, 'defs');
            var clipPath = document.createElementNS(SVG_NS, 'clipPath');
            clipPath.setAttribute('id', clipPathId);
            var clipCircle = document.createElementNS(SVG_NS, 'circle');
            clipPath.appendChild(clipCircle);
            defs.appendChild(clipPath);
            svg.appendChild(defs);

            var landPath = document.createElementNS(SVG_NS, 'path');
            // Same color/alpha as the outline + section divider line.
            landPath.setAttribute('fill', 'rgba(10, 10, 10, 0.12)');
            landPath.setAttribute('stroke', 'none');
            landPath.setAttribute('fill-rule', 'evenodd');
            landPath.setAttribute('clip-path', 'url(#' + clipPathId + ')');
            svg.appendChild(landPath);

            var outlineCircle = document.createElementNS(SVG_NS, 'circle');
            outlineCircle.setAttribute('fill', 'none');
            // Same color + width as the section divider (border-border = ink @ 12% alpha).
            outlineCircle.setAttribute('stroke', 'rgba(10, 10, 10, 0.12)');
            outlineCircle.setAttribute('stroke-width', '1');
            outlineCircle.setAttribute('vector-effect', 'non-scaling-stroke');
            svg.appendChild(outlineCircle);
            var equatorPath = document.createElementNS(SVG_NS, 'path');
            equatorPath.setAttribute('fill', 'none');
            equatorPath.setAttribute('stroke', 'var(--ink-muted)');
            equatorPath.setAttribute('stroke-width', '0.75');
            equatorPath.setAttribute('stroke-dasharray', '2 3');
            equatorPath.setAttribute('vector-effect', 'non-scaling-stroke');
            svg.appendChild(equatorPath);
            var meridianPath = document.createElementNS(SVG_NS, 'path');
            meridianPath.setAttribute('fill', 'none');
            meridianPath.setAttribute('stroke', 'var(--ink-muted)');
            meridianPath.setAttribute('stroke-width', '0.75');
            meridianPath.setAttribute('stroke-dasharray', '2 3');
            meridianPath.setAttribute('vector-effect', 'non-scaling-stroke');
            svg.appendChild(meridianPath);
            surface.appendChild(svg);

            var pinLayer = document.createElement('div');
            pinLayer.style.position = 'absolute';
            pinLayer.style.inset = '0';
            pinLayer.style.pointerEvents = 'none';
            pinLayer.style.fontFamily = 'JetBrains Mono, ui-monospace, monospace';
            surface.appendChild(pinLayer);

            // Controls — transparent container, individual buttons styled like the navbar.
            var ctrlGap = 'clamp(8px, 1.5vw, 16px)';
            var controls = document.createElement('div');
            controls.style.position = 'absolute';
            controls.style.right = ctrlGap;
            controls.style.bottom = ctrlGap;
            controls.style.display = 'flex';
            controls.style.flexDirection = 'row';
            controls.style.gap = '4px';
            controls.style.alignItems = 'center';
            controls.style.fontFamily = 'JetBrains Mono, ui-monospace, monospace';
            controls.style.fontSize = '14px';
            controls.style.background = 'none';
            var btnStyle = 'border:1px solid var(--ink-faint);background:rgba(250,250,247,0.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);color:var(--ink);width:28px;height:28px;line-height:1;cursor:pointer;padding:0;font-family:inherit;font-size:inherit;';
            var btnZin  = document.createElement('button'); btnZin.type = 'button'; btnZin.setAttribute('data-zin',''); btnZin.setAttribute('style', btnStyle); btnZin.setAttribute('aria-label','Zoom in'); btnZin.textContent = '+';
            var btnZout = document.createElement('button'); btnZout.type = 'button'; btnZout.setAttribute('data-zout',''); btnZout.setAttribute('style', btnStyle); btnZout.setAttribute('aria-label','Zoom out'); btnZout.textContent = '\u2212';
            var btnZrst = document.createElement('button'); btnZrst.type = 'button'; btnZrst.setAttribute('data-zrst',''); btnZrst.setAttribute('style', btnStyle); btnZrst.setAttribute('aria-label','Reset view'); btnZrst.textContent = '\u2302';
            controls.appendChild(btnZin);
            controls.appendChild(btnZout);
            controls.appendChild(btnZrst);
            box.appendChild(controls);

            // The editions UI is the sticky bar rendered server-side in
            // template-parts/sections/venue.php (shown on mobile AND desktop). There is
            // no in-globe panel and no ED button anymore — pins drive that same bar.





            function poke() {
                lastInteraction = performance.now();
                mouseLeaveTime = 0;
                markDirty();
            }

            // Far-side flip button — shown when the primary pin is on the back of the globe.
            // Clicking it eases the rotation to bring the pin to the front.
            var flipBtn = document.createElement('button');
            flipBtn.type = 'button';
            flipBtn.style.position = 'absolute';
            flipBtn.style.left = '50%';
            flipBtn.style.bottom = '12px';
            flipBtn.style.transform = 'translateX(-50%)';
            flipBtn.style.display = 'none';
            flipBtn.style.fontFamily = 'JetBrains Mono, ui-monospace, monospace';
            flipBtn.style.fontSize = '10px';
            flipBtn.style.textTransform = 'uppercase';
            flipBtn.style.letterSpacing = '0.08em';
            flipBtn.style.padding = '4px 10px';
            flipBtn.style.border = '1px solid var(--ink-faint)';
            flipBtn.style.background = 'var(--paper)';
            flipBtn.style.color = 'var(--ink-muted)';
            flipBtn.style.cursor = 'pointer';
            flipBtn.style.whiteSpace = 'nowrap';
            flipBtn.addEventListener('mouseenter', function () { flipBtn.style.color = 'var(--accent)'; flipBtn.style.borderColor = 'var(--accent)'; });
            flipBtn.addEventListener('mouseleave', function () { flipBtn.style.color = 'var(--ink-muted)'; flipBtn.style.borderColor = 'var(--ink-faint)'; });
            flipBtn.addEventListener('click', function () {
                if (!primaryPin) return;
                targetYaw   = primaryPin.lon * Math.PI / 180;
                targetPitch = primaryPin.lat * Math.PI / 180 - pitchOffsetForZoom(targetZoom);
                poke();
            });
            box.appendChild(flipBtn);

            controls.querySelector('[data-zin]').addEventListener('click', function () {
                targetZoom = Math.min(32, targetZoom * 1.5); poke();
            });
            controls.querySelector('[data-zout]').addEventListener('click', function () {
                targetZoom = Math.max(0.5, targetZoom / 1.5); poke();
            });
            // Reset zoom + viewpoint to the default. Reused by the home (⌂) button AND
            // fired automatically when the venue section leaves the sidebar threshold.
            function resetView() {
                // Kill any drag momentum immediately — otherwise the inertia loop keeps
                // overwriting targetYaw/targetPitch and the reset only "takes" once the
                // spin decays. Zeroing velocity makes the reset land any time.
                vyaw = 0; vpitch = 0;
                targetZoom  = 1;
                targetYaw   = defaultLon * Math.PI / 180;
                targetPitch = defaultLat * Math.PI / 180 - pitchOffsetForZoom(1);
                selectedEdition = null;
                hoveredEdition = null;
                resetYearButtons();   // clear bar selection + "(click me again)" labels
                poke();
            }
            controls.querySelector('[data-zrst]').addEventListener('click', resetView);

            // Drag + pinch — direct (1:1) for responsiveness. Drag tracks one
            // pointer, accumulating velocity for inertia after release. Pinch
            // takes over the moment a second pointer goes down: drag is paused
            // (so we don't accumulate the cross-finger delta into yaw/pitch —
            // that was the "spins wildly when you pinch" bug) and the zoom is
            // driven directly off the inter-finger distance ratio so the
            // gesture feels glued to the fingers without easing lag.
            var activePointers = {};
            var pinchActive = false;
            var pinchStartDist = 0, pinchStartZoom = 1;
            var dragging = false, lastX = 0, lastY = 0, lastMoveT = 0;
            var vyaw = 0, vpitch = 0;

            box.addEventListener('pointerdown', function (e) {
                if (e.target.tagName === 'BUTTON') return;
                // Don't start a drag/pinch when tapping a pin — let its click through.
                if (e.target.closest && e.target.closest('[data-fc-pin]')) return;

                activePointers[e.pointerId] = { x: e.clientX, y: e.clientY };
                var ids = Object.keys(activePointers);

                if (ids.length === 1) {
                    // Single pointer down → start drag.
                    dragging = true; pinchActive = false;
                    lastX = e.clientX; lastY = e.clientY;
                    lastMoveT = performance.now();
                    vyaw = 0; vpitch = 0;
                    try { box.setPointerCapture(e.pointerId); } catch (_) {}
                    box.style.cursor = 'grabbing';
                } else if (ids.length === 2) {
                    // Second pointer down → switch into pinch mode. Kill drag
                    // state immediately so the next pointermove can't tack the
                    // inter-finger jump onto yaw/pitch.
                    dragging = false;
                    pinchActive = true;
                    vyaw = 0; vpitch = 0;
                    var a = activePointers[ids[0]], b = activePointers[ids[1]];
                    pinchStartDist = Math.hypot(a.x - b.x, a.y - b.y) || 1;
                    pinchStartZoom = targetZoom;
                }
                poke();
            });

            box.addEventListener('pointermove', function (e) {
                if (!activePointers[e.pointerId]) return;
                activePointers[e.pointerId] = { x: e.clientX, y: e.clientY };

                if (pinchActive) {
                    var ids = Object.keys(activePointers);
                    if (ids.length >= 2) {
                        var a = activePointers[ids[0]], b = activePointers[ids[1]];
                        var d = Math.hypot(a.x - b.x, a.y - b.y);
                        var z = Math.max(0.5, Math.min(32, pinchStartZoom * d / pinchStartDist));
                        // Direct-set BOTH zoom and targetZoom so the gesture is
                        // glued to the fingers (no easing lag during pinch).
                        targetZoom = z;
                        zoom = z;
                        poke();
                    }
                    return;
                }

                if (!dragging) return;
                var now = performance.now();
                var dt = Math.max(1, now - lastMoveT);
                var dx = e.clientX - lastX, dy = e.clientY - lastY;
                var speed = 0.005 / Math.max(0.5, zoom * 0.5);
                var dyaw   = -dx * speed;
                var dpitch =  dy * speed;
                yaw   += dyaw;
                pitch += dpitch;
                if (pitch >  Math.PI / 2 - 0.05) pitch =  Math.PI / 2 - 0.05;
                if (pitch < -Math.PI / 2 + 0.05) pitch = -Math.PI / 2 + 0.05;
                targetYaw = yaw; targetPitch = pitch;
                // Velocity normalized to per-frame units (~16ms per frame).
                vyaw   = dyaw   * (16 / dt);
                vpitch = dpitch * (16 / dt);
                lastX = e.clientX; lastY = e.clientY; lastMoveT = now;
                poke();
            });

            function endPointer(e) {
                // Untracked pointer (e.g. a pin tap that bailed at pointerdown)
                // — don't disturb the active drag/pinch.
                if (!activePointers[e.pointerId]) return;
                delete activePointers[e.pointerId];
                var n = Object.keys(activePointers).length;
                if (n === 0) {
                    if (dragging) {
                        dragging = false;
                        try { box.releasePointerCapture(e.pointerId); } catch (_) {}
                        box.style.cursor = 'grab';
                    }
                    pinchActive = false;
                    pinchStartDist = 0;
                } else if (n === 1 && pinchActive) {
                    // Lifted one finger of a pinch — end the pinch but don't
                    // auto-resume drag from the cached delta (would feel like
                    // the globe yanks the moment the second finger leaves).
                    pinchActive = false;
                    pinchStartDist = 0;
                }
                poke();
            }
            box.addEventListener('pointerup', endPointer);
            box.addEventListener('pointercancel', endPointer);
            box.addEventListener('pointerleave', endPointer);

            // Track whether the mouse is inside the globe container.
            box.addEventListener('mouseenter', function () {
                mouseInside = true;
                mouseLeaveTime = 0;
                markDirty();
            });
            box.addEventListener('mouseleave', function () {
                mouseInside = false;
                mouseLeaveTime = performance.now();
                // Cursor left the globe — drop any lingering pin-hover state. Without
                // this, a re-render that destroys the dot under a stationary cursor
                // could leave hoveredEdition stuck (the dot's own mouseleave doesn't
                // always fire for synchronously-removed elements).
                if (hoveredEdition) {
                    hoveredEdition = null;
                    document.querySelectorAll('[data-fc-edition-year]').forEach(function (b) {
                        b.classList.remove('is-hovered');
                    });
                }
                markDirty();
            });

            // Year browser: also treat year-list area as "inside".
            var yearList = document.querySelector('[data-fc-year-list]');
            if (yearList) {
                yearList.addEventListener('mouseenter', function () {
                    mouseInside = true;
                    mouseLeaveTime = 0;
                    markDirty();
                });
                yearList.addEventListener('mouseleave', function () {
                    mouseInside = false;
                    mouseLeaveTime = performance.now();
                    markDirty();
                });
            }

            // Keyboard navigation. Box has tabIndex=0, focus to use.
            box.addEventListener('keydown', function (e) {
                var step = 12 * Math.PI / 180;
                if (e.key === 'ArrowLeft')       { targetYaw   -= step; }
                else if (e.key === 'ArrowRight') { targetYaw   += step; }
                else if (e.key === 'ArrowUp')    { targetPitch += step; }
                else if (e.key === 'ArrowDown')  { targetPitch -= step; }
                else if (e.key === '+' || e.key === '=') { targetZoom = Math.min(32, targetZoom * 1.3); }
                else if (e.key === '-' || e.key === '_') { targetZoom = Math.max(0.5, targetZoom / 1.3); }
                else if (e.key === '0' || e.key === 'Home') {
                    targetYaw = defaultLon * Math.PI / 180;
                    targetPitch = defaultLat * Math.PI / 180 - pitchOffsetForZoom(1);
                    targetZoom = 1;
                    selectedEdition = null;
                    hoveredEdition = null;
                } else { return; }
                if (targetPitch >  Math.PI / 2 - 0.05) targetPitch =  Math.PI / 2 - 0.05;
                if (targetPitch < -Math.PI / 2 + 0.05) targetPitch = -Math.PI / 2 + 0.05;
                e.preventDefault();
                poke();
            });

            box.addEventListener('wheel', function (e) {
                e.preventDefault();
                var factor = e.deltaY > 0 ? 1 / 1.18 : 1.18;
                targetZoom = Math.max(0.5, Math.min(32, targetZoom * factor));
                poke();
            }, { passive: false });

            // (Pinch is unified into the drag handler above — see the
            // `activePointers` / `pinchActive` block.)

            // ---- Render ----
            var dirty = true;
            function markDirty() { dirty = true; }

            // Re-render on size change.
            if (window.ResizeObserver) {
                new ResizeObserver(markDirty).observe(box);
            } else {
                window.addEventListener('resize', markDirty);
            }

            // Scratch buffers reused across frames — sized to the largest polygon once.
            var _maxV = 0;
            for (var _pi = 0; _pi < POLY_XYZ.length; _pi++) {
                var _n = POLY_XYZ[_pi].length / 3;
                if (_n > _maxV) _maxV = _n;
            }
            var pXBuf = new Float32Array(_maxV);
            var pYBuf = new Float32Array(_maxV);
            var pZBuf = new Float32Array(_maxV);

            // Last-applied SVG attribute values — used to skip redundant
            // setAttribute calls when nothing changed (mostly during pure drag).
            var _lastCxStr = '', _lastCyStr = '', _lastRStr = '';


            // Generates an SVG path for the front-side arc of a great circle on the
            // unit sphere. Each frame previously did 121 iterations × 2 trig calls
            // for both equator and meridian. Now: the unit-sphere base coordinates
            // are precomputed once (no trig per frame), the step is 5° instead of
            // 3° (still smooth at 650px wide), and pts is an array + join.
            var GC_STEPS = 73;   // 360/5 + 1
            var GC_SIN = new Float32Array(GC_STEPS);
            var GC_COS = new Float32Array(GC_STEPS);
            for (var _gci = 0; _gci < GC_STEPS; _gci++) {
                var _a = (_gci * 360 / (GC_STEPS - 1)) * Math.PI / 180;
                GC_SIN[_gci] = Math.sin(_a);
                GC_COS[_gci] = Math.cos(_a);
            }
            var _gcBuf = [];
            function greatCirclePath(kindEq, cxPx, cyPx, rPx, cosY, sinY, cosP, sinP) {
                _gcBuf.length = 0;
                var inSeg = false;
                for (var i = 0; i < GC_STEPS; i++) {
                    var x, y, z;
                    if (kindEq) { x = GC_SIN[i]; y = 0;          z = GC_COS[i]; }
                    else        { x = 0;          y = GC_SIN[i]; z = GC_COS[i]; }
                    var rx = x * cosY - z * sinY;
                    var rz = x * sinY + z * cosY;
                    var ry = y * cosP - rz * sinP;
                    var rz2 = y * sinP + rz * cosP;
                    if (rz2 > 0.04) {
                        var px = cxPx + rx * rPx;
                        var py = cyPx - ry * rPx;
                        _gcBuf.push(inSeg ? 'L' : 'M', px.toFixed(1), ' ', py.toFixed(1), ' ');
                        inSeg = true;
                    } else {
                        inSeg = false;
                    }
                }
                return _gcBuf.join('');
            }

            function render() {
                // Render coordinates live in the fixed surface space (NOT the box).
                // The surface's CSS transform scales the finished rasterization up
                // to the box's actual on-screen size — see `fitSurface()` above.
                var w = SURFACE_W;
                var h = SURFACE_H;

                // Disc center sits at 5/7 of box height — top of disc at y=0, bottom 30%
                // of the disc clipped by the box overflow.
                var cxPx = w / 2;
                var cyPx = h * 5 / 7;
                var rPx  = Math.min(cxPx, cyPx) * zoom;
                var rPxI = rPx | 0; // integer for path strings

                var cosY = Math.cos(yaw),  sinY = Math.sin(yaw);
                var cosP = Math.cos(pitch), sinP = Math.sin(pitch);

                // Build the land path. For each polygon: forward-project verts; emit a
                // closed subpath made of straight edges between visible vertices, plus
                // SVG arc commands along the disc rim wherever the polygon crosses the
                // horizon. clip-path catches any stray sliver from numerical drift.
                //
                // Hot path: hoist rimSegments out of the polygon loop so it isn't
                // re-allocated per-polygon each frame. It closes over cxPx/cyPx/rPx
                // from the render() scope. Also build the path as a String[] and
                // join at the end — repeated `+=` on a growing string forces
                // engines into the slow path on long buffers.
                function rimSegments(a1, a2) {
                    var diff = a2 - a1;
                    while (diff > Math.PI) diff -= 2 * Math.PI;
                    while (diff < -Math.PI) diff += 2 * Math.PI;
                    var steps = Math.max(2, Math.ceil(Math.abs(diff) / 0.15));
                    var seg = '';
                    for (var s = 1; s <= steps; s++) {
                        var t = a1 + diff * (s / steps);
                        var sx = (cxPx + Math.cos(t) * rPx) | 0;
                        var sy = (cyPx + Math.sin(t) * rPx) | 0;
                        seg += 'L' + sx + ',' + sy;
                    }
                    return seg;
                }
                var pathParts = [];
                var nPolys = POLY_XYZ.length;

                for (var pi = 0; pi < nPolys; pi++) {
                    // Quick cull: if the polygon's centroid dotted with view direction (rotated)
                    // is less than -sin(geodesic_radius), every vertex is on the back side.
                    // The threshold (`-sin(r)`) is precomputed in POLY_BACKCULL — saves a
                    // Math.sqrt per polygon per frame.
                    var cX = POLY_CENTROID[pi * 3];
                    var cY = POLY_CENTROID[pi * 3 + 1];
                    var cZ = POLY_CENTROID[pi * 3 + 2];
                    var crx_z = cX * sinY + cZ * cosY;
                    var crz   = cY * sinP + crx_z * cosP;
                    if (crz < POLY_BACKCULL[pi]) continue;

                    var xyz = POLY_XYZ[pi];
                    var nV = xyz.length / 3;
                    if (nV < 3) continue;

                    // Forward-project every vertex of this polygon.
                    var anyVisible = false;
                    for (var i = 0; i < nV; i++) {
                        var x = xyz[i * 3];
                        var y = xyz[i * 3 + 1];
                        var z = xyz[i * 3 + 2];
                        var rx = x * cosY - z * sinY;
                        var rz = x * sinY + z * cosY;
                        var ry = y * cosP - rz * sinP;
                        var rz2 = y * sinP + rz * cosP;
                        pXBuf[i] = rx;
                        pYBuf[i] = ry;
                        pZBuf[i] = rz2;
                        if (rz2 > 0) anyVisible = true;
                    }
                    if (!anyVisible) continue;

                    // Walk edges. Build subpaths with horizon-clip points.
                    var subStart = pathParts.length;   // for rollback if subpath ends empty
                    var inSub = false;
                    var firstSX = 0, firstSY = 0;
                    var hasPendingExit = false;
                    var lastExitAngle = 0;

                    for (var k = 0; k < nV; k++) {
                        var a = k;
                        var b = (k + 1) % nV;
                        var aVis = pZBuf[a] > 0;
                        var bVis = pZBuf[b] > 0;

                        if (aVis && bVis) {
                            if (!inSub) {
                                var asx = (cxPx + pXBuf[a] * rPx) | 0;
                                var asy = (cyPx - pYBuf[a] * rPx) | 0;
                                pathParts.push('M', asx, ',', asy);
                                firstSX = asx; firstSY = asy;
                                inSub = true;
                            }
                            var bsx = (cxPx + pXBuf[b] * rPx) | 0;
                            var bsy = (cyPx - pYBuf[b] * rPx) | 0;
                            pathParts.push('L', bsx, ',', bsy);
                        } else if (aVis && !bVis) {
                            // Exit horizon at z=0 interpolation.
                            var tE = pZBuf[a] / (pZBuf[a] - pZBuf[b]);
                            var ixE = pXBuf[a] + tE * (pXBuf[b] - pXBuf[a]);
                            var iyE = pYBuf[a] + tE * (pYBuf[b] - pYBuf[a]);
                            var magE = Math.sqrt(ixE * ixE + iyE * iyE) || 1;
                            ixE /= magE; iyE /= magE;
                            var esx = (cxPx + ixE * rPx) | 0;
                            var esy = (cyPx - iyE * rPx) | 0;
                            if (!inSub) {
                                var ax2 = (cxPx + pXBuf[a] * rPx) | 0;
                                var ay2 = (cyPx - pYBuf[a] * rPx) | 0;
                                pathParts.push('M', ax2, ',', ay2);
                                firstSX = ax2; firstSY = ay2;
                                inSub = true;
                            }
                            pathParts.push('L', esx, ',', esy);
                            hasPendingExit = true;
                            // Store exit angle on the rim (atan2 using screen coords relative to center).
                            lastExitAngle = Math.atan2(esy - cyPx, esx - cxPx);
                        } else if (!aVis && bVis) {
                            // Enter horizon.
                            var tN = pZBuf[a] / (pZBuf[a] - pZBuf[b]);
                            var ixN = pXBuf[a] + tN * (pXBuf[b] - pXBuf[a]);
                            var iyN = pYBuf[a] + tN * (pYBuf[b] - pYBuf[a]);
                            var magN = Math.sqrt(ixN * ixN + iyN * iyN) || 1;
                            ixN /= magN; iyN /= magN;
                            var nsx = (cxPx + ixN * rPx) | 0;
                            var nsy = (cyPx - iyN * rPx) | 0;
                            var entryAngle = Math.atan2(nsy - cyPx, nsx - cxPx);

                            if (hasPendingExit) {
                                // Walk along the rim from exit to entry with line segments.
                                pathParts.push(rimSegments(lastExitAngle, entryAngle));
                                hasPendingExit = false;
                            } else {
                                pathParts.push('M', nsx, ',', nsy);
                                firstSX = nsx; firstSY = nsy;
                                inSub = true;
                            }
                            var bsx2 = (cxPx + pXBuf[b] * rPx) | 0;
                            var bsy2 = (cyPx - pYBuf[b] * rPx) | 0;
                            pathParts.push('L', bsx2, ',', bsy2);
                        }
                        // both invisible → skip
                    }

                    if (inSub) {
                        if (hasPendingExit) {
                            var closingAngle = Math.atan2(firstSY - cyPx, firstSX - cxPx);
                            pathParts.push(rimSegments(lastExitAngle, closingAngle));
                        }
                        pathParts.push('Z');
                    } else if (pathParts.length !== subStart) {
                        // We pushed parts but never closed a subpath — defensive trim.
                        pathParts.length = subStart;
                    }
                }

                landPath.setAttribute('d', pathParts.join(''));

                // Ocean / clip / outline circle attrs only change when cxPx, cyPx,
                // or rPx change. cx/cy are derived from SURFACE_W/H (constants);
                // r changes only with zoom. So during pure drag (zoom unchanged)
                // these 9 setAttribute calls per frame are no-ops. Cache the last
                // applied value and skip the round-trip into SVG-attribute parsing.
                var cxStr = cxPx.toFixed(1), cyStr = cyPx.toFixed(1), rStr = rPx.toFixed(1);
                if (cxStr !== _lastCxStr || cyStr !== _lastCyStr || rStr !== _lastRStr) {
                    oceanCircle.setAttribute('cx', cxStr);
                    oceanCircle.setAttribute('cy', cyStr);
                    oceanCircle.setAttribute('r',  rStr);
                    clipCircle.setAttribute('cx', cxStr);
                    clipCircle.setAttribute('cy', cyStr);
                    clipCircle.setAttribute('r',  rStr);
                    outlineCircle.setAttribute('cx', cxStr);
                    outlineCircle.setAttribute('cy', cyStr);
                    outlineCircle.setAttribute('r',  rStr);
                    _lastCxStr = cxStr; _lastCyStr = cyStr; _lastRStr = rStr;
                }
                equatorPath.setAttribute('d', greatCirclePath(true,  cxPx, cyPx, rPx, cosY, sinY, cosP, sinP));
                meridianPath.setAttribute('d', greatCirclePath(false, cxPx, cyPx, rPx, cosY, sinY, cosP, sinP));

                // Pin overlay. Binary cluster/individual switch based on zoom threshold.
                // Build all pins into a DocumentFragment first, then swap into pinLayer
                // in one go — N appendChild calls into the live DOM become 1.
                var pinFragment = document.createDocumentFragment();
                var clusterAlpha = zoom <= CLUSTER_ZOOM_THRESHOLD ? 1 : 0;
                var indivAlpha   = zoom > CLUSTER_ZOOM_THRESHOLD ? 1 : 0;
                var primaryVisible = false;

                function drawPin(lat, lon, label, color, alpha, bigDot, isPrimary, edYear, stackIdx, stackTotal) {
                    if (alpha <= 0.01) return;
                    var pLat = lat * Math.PI / 180, pLon = lon * Math.PI / 180;
                    var pX = Math.cos(pLat) * Math.sin(pLon);
                    var pY = Math.sin(pLat);
                    var pZ = Math.cos(pLat) * Math.cos(pLon);
                    var rx =  pX * cosY - pZ * sinY;
                    var rz =  pX * sinY + pZ * cosY;
                    var ry =  pY * cosP - rz * sinP;
                    var rz2 = pY * sinP + rz * cosP;
                    if (rz2 < 0) return;       // back side of globe
                    if (isPrimary) primaryVisible = true;

                    var leftPx = cxPx + rx * rPx;
                    var topPx  = cyPx - ry * rPx;
                    // On touch viewports the WHOLE pin (dot + leader + label) is
                    // uniformly scaled up — original mobile dot was a hair too small
                    // for thumb taps and the leader/label felt thin against it. All
                    // distances and font sizes inherit `pinScale` so the pin keeps
                    // its proportions.
                    var pinScale = isTouchDevice ? 1.75 : 1;
                    var dotSize = isTouchDevice ? (bigDot ? 20 : 14) * pinScale : (bigDot ? 12 : 8);
                    var leaderH = 18 * pinScale;
                    var leaderW = 1 * pinScale;
                    var labelTopOffset = 30 * pinScale;
                    var labelFontPx = (bigDot ? 11 : 10) * pinScale;
                    var labelPadV = 2 * pinScale;
                    var labelPadH = 6 * pinScale;

                    // Group = single hit-target for the WHOLE pin (dot + line + label).
                    // Hovering or clicking any visible part triggers the same handler,
                    // and mouseenter/mouseleave on the group only fire at the outer
                    // boundary — moving the cursor between dot↔line↔label stays "inside"
                    // the group, so the hover state doesn't flicker.
                    // Appended to `pinFragment` instead of straight into pinLayer so all
                    // pins land in one DOM swap at the end of render() — N invalidations
                    // collapse to one.
                    var group = document.createElement('div');
                    group.style.position = 'absolute';
                    group.style.left = '0';
                    group.style.top  = '0';
                    group.style.width  = '0';
                    group.style.height = '0';
                    pinFragment.appendChild(group);

                    var dot = document.createElement('div');
                    dot.style.position = 'absolute';
                    dot.style.left = (leftPx - dotSize / 2) + 'px';
                    dot.style.top  = (topPx  - dotSize / 2) + 'px';
                    dot.style.width  = dotSize + 'px';
                    dot.style.height = dotSize + 'px';
                    dot.style.borderRadius = '50%';
                    dot.style.background = color;
                    dot.style.boxShadow = '0 0 0 2px var(--paper)';
                    dot.style.opacity = alpha;
                    group.appendChild(dot);

                    if (label) {
                        // Leader + label live inside a "stalk" anchored at the dot's
                        // top-center. For stacked (co-located) pins, the stalk is
                        // rotated around that anchor so each pin's label fans out in
                        // its own direction — preventing all labels from rendering on
                        // top of each other. Single pins use rotation 0 (straight up,
                        // identical to the pre-stack layout). All distances scaled
                        // by `pinScale` so mobile gets a uniformly larger pin.
                        var angleDeg = stackAngle(stackIdx || 0, stackTotal || 1);
                        var stalk = document.createElement('div');
                        stalk.style.position = 'absolute';
                        stalk.style.left = leftPx + 'px';
                        stalk.style.top  = (topPx - dotSize / 2) + 'px';
                        stalk.style.width  = '0';
                        stalk.style.height = '0';
                        if (angleDeg !== 0) {
                            stalk.style.transform = 'rotate(' + angleDeg + 'deg)';
                            stalk.style.transformOrigin = '0 0';
                        }
                        group.appendChild(stalk);

                        var leader = document.createElement('div');
                        leader.style.position = 'absolute';
                        leader.style.left = (-leaderW / 2) + 'px';
                        leader.style.top  = (-leaderH) + 'px';
                        leader.style.width  = leaderW + 'px';
                        leader.style.height = leaderH + 'px';
                        leader.style.background = color;
                        leader.style.opacity = alpha;
                        stalk.appendChild(leader);

                        var lbl = document.createElement('div');
                        lbl.textContent = label;
                        lbl.style.position = 'absolute';
                        lbl.style.left = '0';
                        lbl.style.top  = (-labelTopOffset) + 'px';
                        lbl.style.transform = 'translateX(-50%)';
                        lbl.style.fontSize = labelFontPx + 'px';
                        lbl.style.textTransform = 'uppercase';
                        lbl.style.letterSpacing = '0.06em';
                        lbl.style.background = color;
                        lbl.style.color = 'var(--paper)';
                        lbl.style.padding = labelPadV + 'px ' + labelPadH + 'px';
                        lbl.style.whiteSpace = 'nowrap';
                        lbl.style.opacity = alpha;
                        stalk.appendChild(lbl);
                    }

                    // Cluster pin (no year) is decorative — no click/hover, and it
                    // should be transparent to the mouse so clicks fall through to the
                    // globe's drag layer rather than blocking it.
                    if (edYear == null) {
                        group.style.pointerEvents = 'none';
                        dot.style.pointerEvents  = 'none';
                        if (label) {
                            leader.style.pointerEvents = 'none';
                            lbl.style.pointerEvents    = 'none';
                        }
                    } else {
                        group.setAttribute('data-fc-pin', '');
                        group.setAttribute('data-fc-pin-year', String(edYear));
                        group.style.cursor = 'pointer';
                        group.setAttribute('title',
                            (selectedEdition && selectedEdition.year === edYear)
                                ? 'Click to open archive'
                                : 'Click to select');

                        // First click: select (sidebar + pin both show their click-me-again
                        // prompt). Second click while already selected: open the archive URL.
                        // If there is no archive URL, cycle through the SASS_MESSAGES pool
                        // so the user gets a different reply each tap (in random order).
                        group.addEventListener('click', function (e) {
                            e.stopPropagation();
                            if (selectedEdition && selectedEdition.year === edYear) {
                                if (selectedEdition.url) { window.open(selectedEdition.url, '_blank'); return; }
                                var msg = nextSassMessage(selectedEdition.sass);
                                selectedEdition.sass = msg;
                                applySassToSidebar(edYear, msg);
                                markDirty();
                                return;
                            }
                            selectEditionButton(document.querySelector('[data-fc-edition-year="' + edYear + '"]'));
                        });

                        // Hover is mouse-only — on touch, taps fire a phantom mouseenter
                        // that we skip so mobile stays click-only. The check runs at
                        // fire time so a viewport resize across the lg breakpoint
                        // toggles hover without re-binding.
                        group.addEventListener('mouseenter', function () {
                            if (isTouchDevice) return;
                            hoveredEdition = { year: edYear, city: '', lat: lat, lon: lon };
                            document.querySelectorAll('[data-fc-edition-year="' + edYear + '"]').forEach(function (hb) {
                                hb.classList.add('is-hovered');
                            });
                            markDirty();
                        });
                        group.addEventListener('mouseleave', function () {
                            if (isTouchDevice) return;
                            if (hoveredEdition && hoveredEdition.year === edYear) {
                                hoveredEdition = null;
                            }
                            document.querySelectorAll('[data-fc-edition-year="' + edYear + '"]').forEach(function (hb) {
                                hb.classList.remove('is-hovered');
                            });
                            markDirty();
                        });
                    }
                }

                // Cluster pin: show hoveredEdition year if hovering, else default label.
                if (pins.length && clusterAlpha > 0.01) {
                    var displayLabel = (hoveredEdition && clusterAlpha > 0.5)
                        ? String(hoveredEdition.year)
                        : clusterLabel;
                    drawPin(clusterLat, clusterLon, displayLabel, 'var(--accent)', clusterAlpha, true, false, null, 0, 1);
                }
                // Individual pins. Layered: normal pins first, hovered on top,
                // selected on top of everything (with the "CLICK ME AGAIN" label so
                // the pin mirrors the sidebar's "(click me again)" prompt).
                if (indivAlpha > 0.01) {
                    var hoveredPin  = null;
                    var selectedPin = null;
                    for (var i = 0; i < pins.length; i++) {
                        var p = pins[i];
                        if (selectedEdition && p.year === selectedEdition.year) { selectedPin = p; continue; }
                        if (hoveredEdition  && p.year === hoveredEdition.year)  { hoveredPin  = p; continue; }
                        var col = p.isCurrent ? 'var(--accent)' : 'var(--ink-muted)';
                        drawPin(p.lat, p.lon, p.label, col, indivAlpha, false, p === primaryPin, p.year, p.stackIndex, p.stackTotal);
                    }
                    if (hoveredPin) {
                        drawPin(hoveredPin.lat, hoveredPin.lon, hoveredPin.label, 'var(--accent)', indivAlpha, true, hoveredPin === primaryPin, hoveredPin.year, hoveredPin.stackIndex, hoveredPin.stackTotal);
                    }
                    if (selectedPin) {
                        var selLbl = (selectedEdition && selectedEdition.sass) || 'CLICK ME AGAIN';
                        drawPin(selectedPin.lat, selectedPin.lon, selLbl, 'var(--accent)', indivAlpha, true, selectedPin === primaryPin, selectedPin.year, selectedPin.stackIndex, selectedPin.stackTotal);
                    }
                } else if (selectedEdition
                           && typeof selectedEdition.lat === 'number'
                           && typeof selectedEdition.lon === 'number') {
                    // Zoomed-out: still keep the selected pin visible with its prompt.
                    // Look up stack info from the matching pin so the selected label
                    // fans out at the same angle as the underlying co-located pin.
                    // Skipped when the selected edition has no coords (e.g. "Online"
                    // years) — those rows live in the sidebar only, no globe pin.
                    var selLbl2 = selectedEdition.sass || 'CLICK ME AGAIN';
                    var selStack = stackByYear[selectedEdition.year] || { idx: 0, total: 1 };
                    drawPin(selectedEdition.lat, selectedEdition.lon, selLbl2, 'var(--accent)', 1, true, false, selectedEdition.year, selStack.idx, selStack.total);
                }

                // Single DOM swap for the entire pin layer. replaceChildren
                // accepts a DocumentFragment, so the old pin nodes are dropped
                // and the new set is inserted in one operation — much cheaper
                // than `innerHTML = ''` followed by N appendChild calls.
                if (pinLayer.replaceChildren) {
                    pinLayer.replaceChildren(pinFragment);
                } else {
                    pinLayer.innerHTML = '';
                    pinLayer.appendChild(pinFragment);
                }

                // Flip-to-primary button: shown when the primary pin is hidden (back of
                // globe) AND the user is in zoomed-in pin mode (cluster mostly faded).
                if (primaryPin && !primaryVisible && indivAlpha > 0.3) {
                    flipBtn.textContent = '↻ ' + (primaryPin.label || 'home') + ' (other side)';
                    flipBtn.style.display = '';
                } else {
                    flipBtn.style.display = 'none';
                }
            }

            function clamp(v, lo, hi) { return v < lo ? lo : (v > hi ? hi : v); }

            // rAF loop. Order of work each frame:
            //   1. Drag inertia (momentum after the user releases)
            //   2. Idle auto-rotation (after IDLE_MS of inactivity)
            //   3. Ease rendered values toward targets (zoom button, reset, keyboard)
            //   4. Render if dirty, throttled to 30 fps
            var lastTick = 0;
            var visible = true;
            function tick(t) {
                if (!visible) { requestAnimationFrame(tick); return; }

                // Inertia from drag release
                if (!dragging && (Math.abs(vyaw) > 0.0002 || Math.abs(vpitch) > 0.0002)) {
                    yaw   += vyaw;
                    pitch += vpitch;
                    if (pitch >  Math.PI / 2 - 0.05) pitch =  Math.PI / 2 - 0.05;
                    if (pitch < -Math.PI / 2 + 0.05) pitch = -Math.PI / 2 + 0.05;
                    targetYaw = yaw; targetPitch = pitch;
                    vyaw   *= 0.92;
                    vpitch *= 0.92;
                    markDirty();
                }

                // Idle auto-rotation — ONLY at the default (reset) zoom level. If the
                // user has zoomed in OR out past that point, the globe does not rotate.
                // (The view is auto-reset when venue leaves the sidebar threshold; see
                // the scroll handler below.)
                var atDefaultZoom = Math.abs(zoom - 1) < 0.05;
                if (!dragging && AUTO_ROT_RAD_PER_FRAME > 0 && (t - lastInteraction) > IDLE_MS
                    && Math.abs(vyaw) < 0.0002 && Math.abs(vpitch) < 0.0002
                    && atDefaultZoom) {
                    yaw       += AUTO_ROT_RAD_PER_FRAME;
                    targetYaw += AUTO_ROT_RAD_PER_FRAME;
                    markDirty();
                }

                // Ease toward targets (from buttons, reset, keyboard, pinch)
                var dy = targetYaw   - yaw;
                var dp = targetPitch - pitch;
                var dz = targetZoom  - zoom;
                if (Math.abs(dy) > 0.00015) { yaw   += dy * EASE_ROT;  markDirty(); }
                else if (yaw !== targetYaw)   { yaw   = targetYaw; }
                if (Math.abs(dp) > 0.00015) { pitch += dp * EASE_ROT;  markDirty(); }
                else if (pitch !== targetPitch) { pitch = targetPitch; }
                if (Math.abs(dz) > 0.001)   { zoom  += dz * EASE_ZOOM; markDirty(); }
                else if (zoom !== targetZoom) { zoom  = targetZoom; }

                if (dirty && t - lastTick > 33) {
                    watchZoomThreshold();
                    dirty = false;
                    lastTick = t;
                    render();
                }
                requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);

            // Pause when off-screen to save CPU
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (entries) {
                    visible = entries[0].isIntersecting;
                    if (visible) markDirty();
                }, { rootMargin: '100px' }).observe(box);
            }

            // Auto-reset the view (as if the home button was pressed) when the venue
            // section leaves the active-section threshold the sidebar uses (0.35 of the
            // viewport — matches assets/section-nav.js).
            (function autoResetOnSectionLeave() {
                var sectionEl = box.closest('section');
                if (!sectionEl) return;
                var SIDEBAR_THRESHOLD = 0.35;
                var wasActive = null;
                function venueIsActive() {
                    var secs = document.querySelectorAll('section');
                    if (!secs.length) return false;
                    var trigger = window.scrollY + window.innerHeight * SIDEBAR_THRESHOLD;
                    var activeId = null;
                    secs.forEach(function (s) {
                        var top = s.getBoundingClientRect().top + window.scrollY;
                        if (top <= trigger) activeId = s.id;
                    });
                    return activeId === sectionEl.id;
                }
                function check() {
                    var active = venueIsActive();
                    if (wasActive === null) { wasActive = active; return; }
                    if (wasActive && !active) resetView();   // venue just scrolled away
                    wasActive = active;
                }
                window.addEventListener('scroll', check, { passive: true });
                window.addEventListener('resize', check);
                check();
            })();

            // ---- Cross-cutting deselect rules ----

            // Zooming out far enough to bring the FOSSCOMM cluster pin back wipes the
            // editions state — both selection and hover. Detect the downward crossing
            // (was above the cluster threshold last frame, at-or-below this frame) so
            // we only fire it once per zoom-out, not every frame at low zoom.
            //
            // Called from the rAF tick instead of a setInterval — that timer ran
            // forever even when the section was off-screen, burning a tick every
            // 80ms for nothing. Now it piggybacks the loop and naturally pauses
            // with the rest of the globe when `visible` is false.
            var prevAboveCluster = zoom > CLUSTER_ZOOM_THRESHOLD;
            function watchZoomThreshold() {
                var nowAbove = zoom > CLUSTER_ZOOM_THRESHOLD;
                if (prevAboveCluster && !nowAbove) {
                    if (selectedEdition || hoveredEdition) {
                        selectedEdition = null;
                        hoveredEdition = null;
                        resetYearButtons();
                        markDirty();
                    }
                }
                prevAboveCluster = nowAbove;
            }

            // Clicking anything that is NOT a pin and NOT a sidebar edition button
            // while in the "click me again" state drops the selection. Sidebar/pin
            // clicks pre-empt this (pin.click stops propagation; sidebar.click runs
            // first and the closest-check below short-circuits the deselect).
            document.addEventListener('click', function (e) {
                if (!selectedEdition) return;
                var t = e.target;
                if (t && t.closest && (t.closest('[data-fc-pin]') || t.closest('[data-fc-edition-year]'))) return;
                selectedEdition = null;
                resetYearButtons();
                markDirty();
            });

            // Stuck-hover safety net: if the cursor is anywhere that ISN'T a pin or
            // sidebar item but hoveredEdition is still set, clear it. Handles the
            // re-render-under-stationary-cursor edge case where the dot's own
            // mouseleave never fires. Inert on mobile (no hover to recover from).
            document.addEventListener('mousemove', function (e) {
                if (isTouchDevice) return;
                if (!hoveredEdition) return;
                var t = e.target;
                if (t && t.closest && (t.closest('[data-fc-pin]') || t.closest('[data-fc-edition-year]'))) return;
                hoveredEdition = null;
                document.querySelectorAll('[data-fc-edition-year]').forEach(function (b) {
                    b.classList.remove('is-hovered');
                });
                markDirty();
            }, { passive: true });

            // ---- Editions bar: click, hover, link ----
            // Pool of sass replies shown when "click me again" is tapped on an
            // edition that has no archive URL. Picked at random (never the same
            // one twice in a row, so it always feels like something changed).
            var SASS_MESSAGES = ['(Nope)', '(Boop)', '(So?)', '(Again)', '(Crickets)', '(Missed)'];
            function nextSassMessage(current) {
                var pool = SASS_MESSAGES;
                if (current) {
                    pool = SASS_MESSAGES.filter(function (m) { return m !== current; });
                }
                return pool[Math.floor(Math.random() * pool.length)];
            }
            function applySassToSidebar(edYear, msg) {
                document.querySelectorAll('[data-fc-edition-year="' + edYear + '"] .fc-edition-arrow-sel').forEach(function (s) {
                    s.textContent = msg;
                });
            }

            // Deselect every bar item. The label swap (year → "(click me again)") is
            // pure CSS keyed off [data-fc-edition-selected]; JS only toggles the flag.
            function resetYearButtons() {
                document.querySelectorAll('[data-fc-edition-year]').forEach(function (b) {
                    b.removeAttribute('data-fc-edition-selected');
                    b.classList.remove('is-hovered');
                    if (b.hasAttribute('data-fc-edition-current')) {
                        b.classList.add('text-accent');
                        b.classList.remove('text-ink-muted');
                    } else {
                        b.classList.remove('text-accent');
                        b.classList.add('text-ink-muted');
                    }
                });
                // Restore the canonical "(click me again)" copy in case a previous
                // selection had replaced it with a sass message.
                document.querySelectorAll('.fc-edition-arrow-sel').forEach(function (s) {
                    s.textContent = '(click me again)';
                });
            }

            // Pan + zoom to an edition. Does NOT touch selectedEdition — that's a
            // click-only state. Called both by click (via selectEditionButton) and by
            // the mobile bar's scroll-snap handler.
            function focusGlobeOnEdition(btn) {
                if (!btn) return;
                var edLat = parseFloat(btn.getAttribute('data-fc-edition-lat'));
                var edLon = parseFloat(btn.getAttribute('data-fc-edition-lon'));
                if (isNaN(edLat) || isNaN(edLon)) return;

                // Kill drag momentum so the pan lands immediately (see home button).
                vyaw = 0; vpitch = 0;
                // Pan globe to that city and zoom to max. Same pitch offset as the
                // default/reset view so the pin lands in the visible centre of the
                // box regardless of the disc's clipped bottom (see pitchOffsetForZoom).
                targetYaw   = edLon * Math.PI / 180;
                targetZoom  = 20;
                targetPitch = edLat * Math.PI / 180 - pitchOffsetForZoom(targetZoom);
                poke();
            }

            // Click-select an edition. Sets selectedEdition (so the pin draws with
            // "CLICK ME AGAIN") and flags BOTH matching bar buttons (mobile + desktop)
            // so each shows "(click me again)" via the CSS rule keyed off
            // [data-fc-edition-selected]. Pans/zooms only when the row has coords —
            // coordless editions (e.g. the "Online" years) still get the selected
            // state and the second-click-opens-URL behaviour, but the globe stays
            // put because there's nothing to pan to.
            function selectEditionButton(btn) {
                if (!btn) return;
                var edLat  = parseFloat(btn.getAttribute('data-fc-edition-lat'));
                var edLon  = parseFloat(btn.getAttribute('data-fc-edition-lon'));
                var edCity = btn.getAttribute('data-fc-edition-city') || '';
                var edYear = parseInt(btn.getAttribute('data-fc-edition-year'), 10);
                var edUrl  = btn.getAttribute('data-fc-edition-url') || '';
                if (!edYear) return;   // year is the only truly required field
                var hasCoords = !isNaN(edLat) && !isNaN(edLon);
                resetYearButtons();
                document.querySelectorAll('[data-fc-edition-year="' + edYear + '"]').forEach(function (b) {
                    b.classList.add('text-accent');
                    b.classList.remove('text-ink-muted');
                    b.setAttribute('data-fc-edition-selected', '');
                });
                selectedEdition = {
                    year: edYear, city: edCity,
                    lat: hasCoords ? edLat : null,
                    lon: hasCoords ? edLon : null,
                    url: edUrl
                };
                if (hasCoords) {
                    focusGlobeOnEdition(btn);
                } else {
                    // No globe pan possible — just trigger a redraw so any
                    // previously-selected pin clears off the globe.
                    markDirty();
                }
            }

            document.querySelectorAll('[data-fc-edition-year]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    // Coords are NOT required to interact with the sidebar — an
                    // edition with only a URL (the "Online" years) should still
                    // toggle into the selected state on first click and follow
                    // its URL on the second.

                    // Second click on an already-selected year: open its archive URL,
                    // or — if no URL is set — cycle through the SASS_MESSAGES pool so
                    // each tap produces a different reply in place of "(click me again)".
                    if (btn.hasAttribute('data-fc-edition-selected')) {
                        var edUrl = btn.getAttribute('data-fc-edition-url') || '';
                        if (edUrl) { window.open(edUrl, '_blank'); return; }
                        var edYear = parseInt(btn.getAttribute('data-fc-edition-year'), 10);
                        var msg = nextSassMessage(selectedEdition && selectedEdition.sass);
                        if (selectedEdition) selectedEdition.sass = msg;
                        applySassToSidebar(edYear, msg);
                        markDirty();
                        return;
                    }

                    selectEditionButton(btn);
                });

                // Hover a bar item = preview: pan/zoom the globe + highlight the
                // matching pin. Inert in mobile-mode (tap-only spec). The check
                // runs at fire time, not attach time, so the behaviour adapts when
                // the viewport crosses the lg breakpoint without a reload.
                btn.addEventListener('mouseenter', function () {
                    if (isTouchDevice) return;
                    var edLat  = parseFloat(btn.getAttribute('data-fc-edition-lat'));
                    var edLon  = parseFloat(btn.getAttribute('data-fc-edition-lon'));
                    var edCity = btn.getAttribute('data-fc-edition-city') || '';
                    var edYear = parseInt(btn.getAttribute('data-fc-edition-year'), 10);
                    if (!isNaN(edLat) && !isNaN(edLon)) {
                        hoveredEdition = { year: edYear, city: edCity, lat: edLat, lon: edLon };
                        document.querySelectorAll('[data-fc-edition-year="' + edYear + '"]').forEach(function (b) {
                            b.classList.add('is-hovered');
                        });
                        focusGlobeOnEdition(btn);
                    }
                });
                btn.addEventListener('mouseleave', function () {
                    if (isTouchDevice) return;
                    var edYear = parseInt(btn.getAttribute('data-fc-edition-year'), 10);
                    if (hoveredEdition && hoveredEdition.year === edYear) {
                        hoveredEdition = null;
                    }
                    if (!isNaN(edYear)) {
                        document.querySelectorAll('[data-fc-edition-year="' + edYear + '"]').forEach(function (b) {
                            b.classList.remove('is-hovered');
                        });
                    }
                    markDirty();
                });
            });

            // Mobile editions bar: tap-only. Previously, scrolling the bar would
            // auto-focus the leftmost edition on the globe — that behaviour was
            // removed per spec. Selection now happens exclusively on a tap.
        });
    })();
})();
