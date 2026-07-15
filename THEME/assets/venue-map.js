/* FOSSCOMM 2026 — Venue map island (replaces the old ASCII globe).
 *
 * MapLibre GL JS v5 + OpenFreeMap (positron) vector tiles, recoloured to the
 * paper/ink/blue palette and shown on a rotating globe. Editions become map
 * pins via a hard zoom switch (no pixel clustering): ALL editions are ONE
 * date-less sprite pin (the whole-Greece dot) below CLUSTER_UNTIL_ZOOM, then at
 * or above that threshold the dot gives way and every VENUE shows as its own
 * pin — no in-between regional clusters. Editions sharing a venue (identical
 * coords — Athens hosted 7, Thessaloniki/Heraklion 3…) collapse into ONE marker
 * (groupColocated) rather than stacking or fanning out: a venue is a single pin
 * no matter how many years it hosted, and hovering it lights up ALL of that
 * venue's editions in the sidebar at once. The custom +/−/⌂
 * controls match the old globe's buttons. The editions sidebar (desktop panel +
 * mobile bar in template-parts/sections/venue.php) drives the map: hover moves
 * + highlights, click opens the archive in a new tab (or a sass message when an
 * edition has no link). On mobile (no hover) the leftmost item under the bar's
 * scroll is auto-highlighted.
 *
 * Self-mounts on every [data-fc-island="venue-map"]; safe to load anywhere.
 */
(function () {
    'use strict';
    if (typeof maplibregl === 'undefined') return;

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var mqMobile = window.matchMedia && window.matchMedia('(max-width: 1023.98px)');
    function isMobile() { return !!(mqMobile && mqMobile.matches); }

    // Palette (literals — setPaintProperty can't resolve CSS vars).
    // Line-art look: paper-white continents (they blend into the page). Their
    // coastline and the country borders inside them are drawn with the SAME grey
    // line (CONT) so they look identical, on an outline-grey sea.
    var PAPER  = '#FAFAF7';   // site paper — the area around the globe
    var LAND   = '#FAFAF7';   // continents — paper white (same as the page; CONT lines define their shape)
    var CONT   = '#B6B3A9';   // coastline + landuse + country borders — one consistent grey line
    var WATER  = '#C9C7BF';   // sea — the theme's outline grey
    var INK    = '#0A0A0A';
    var INK_MUTED = '#6B6B66';
    var ACCENT = '#0033FF';

    var STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';

    var mounts = document.querySelectorAll('[data-fc-island="venue-map"]');
    Array.prototype.forEach.call(mounts, initMap);

    function initMap(mount) {
        var editions = [];
        try { editions = JSON.parse(mount.getAttribute('data-fc-editions') || '[]') || []; } catch (e) { editions = []; }

        var spriteUrl      = mount.getAttribute('data-fc-pin-sprite') || '';
        var spotlightUrl   = mount.getAttribute('data-fc-spotlight-sprite') || '';
        var venueLat    = parseFloat(mount.getAttribute('data-fc-venue-lat'));
        var venueLon    = parseFloat(mount.getAttribute('data-fc-venue-lon'));

        // Per-sprite size multipliers from the admin (× PIN_BASE). Fall back to
        // 1.0 for a blank/invalid value so a pin is never scaled away to nothing.
        var pinScale       = parseFloat(mount.getAttribute('data-fc-pin-scale'));
        var spotlightScale = parseFloat(mount.getAttribute('data-fc-spotlight-scale'));
        if (!isFinite(pinScale)       || pinScale       <= 0) pinScale = 1;
        if (!isFinite(spotlightScale) || spotlightScale <= 0) spotlightScale = 1;
        var PIN_BASE = 1.25;   // base icon-size that both scales multiply

        // All editions with usable coordinates. A spotlighted edition also
        // centres the map on the venue (see computeCenter).
        var allPins = editions.filter(function (ed) {
            var la = parseFloat(ed.lat), lo = parseFloat(ed.lon);
            return !isNaN(la) && !isNaN(lo);
        }).map(function (ed) {
            return {
                year:      parseInt(ed.year, 10) || 0,
                city:      String(ed.city || ''),
                lat:       parseFloat(ed.lat),
                lon:       parseFloat(ed.lon),
                url:       String(ed.url || ''),
                spotlight: !!ed.spotlight
            };
        });

        var defaultCenter = computeCenter(allPins, venueLat, venueLon);

        // Every edition gets a pin now. A spotlighted edition renders with the
        // spotlight sprite (and centres the globe, via computeCenter); the rest
        // use the regular pin sprite. The editions sidebars list them all too.
        var pins = allPins;

        // Spotlight resolution, shared by BOTH the zoomed-out single dot and the
        // zoomed-in per-venue pins. spotImg/spotSize are the image id + size a
        // spotlighted pin uses; when NO spotlight sprite is uploaded they point at
        // the regular pin ('fc-pin') at the regular size, so the spotlight simply
        // falls back to the normal sprite ("no spotlight sprite ⇒ normal sprite")
        // and no layer references a non-existent 'fc-pin-spotlight'. Where they
        // apply differs by zoom:
        //   • zoomed OUT — the single dot ALWAYS wears the spotlight sprite (it's
        //                  the event's one hero pin), regardless of which editions
        //                  are ticked. With none uploaded that's just the normal pin.
        //   • zoomed IN  — only editions ticked "Spotlight" wear it; every other
        //                  venue pin stays the normal pin.
        var spotImg  = spotlightUrl ? 'fc-pin-spotlight' : 'fc-pin';
        var spotSize = spotlightUrl ? (PIN_BASE * spotlightScale) : (PIN_BASE * pinScale);
        // ── Resting-view tweakables (the home / ⌂ button returns here) ─────────
        //   DEFAULT_ZOOM       — resting zoom AT REST_REF_WIDTH. Lower = globe smaller.
        //   REST_REF_WIDTH     — FIXED reference box width (px). At this width the globe
        //                        frames at DEFAULT_ZOOM; narrower/wider boxes scale the
        //                        resting zoom by log2(width / this), so the globe shows
        //                        the SAME composition no matter whether the page LOADS
        //                        at desktop or mobile width. Set it to the desktop map
        //                        width where DEFAULT_ZOOM looks right.
        //   BOTTOM_HIDDEN      — fraction of the box the globe's BOTTOM is clipped by.
        //   MIN_ZOOM           — zoom-OUT threshold (can't zoom out past this).
        //   MAX_ZOOM           — zoom-IN threshold (can't zoom in past this).
        //   CLUSTER_UNTIL_ZOOM — single-pin threshold. BELOW it all editions are one
        //                        dot (the whole-Greece pin); at/above it that dot is
        //                        replaced by every individual edition pin. Raise to
        //                        keep the one dot longer, lower to reveal the pins
        //                        sooner.
        //   REST_LAT           — the LATITUDE the globe is centred on at rest (⌂ /
        //                        home view). This is the "rotate the globe" knob:
        //                        LOWER it to roll the globe down so Greece rides up
        //                        toward the top with Africa/Arabia filling the view;
        //                        set it to the venue's own latitude (~38) to put
        //                        Greece back in the middle. The pins always use the
        //                        venue coords, so they stay on Greece regardless.
        var DEFAULT_ZOOM       = 2.4;
        var BOTTOM_HIDDEN      = 0.50;
        var MIN_ZOOM           = 2.1;
        var MAX_ZOOM           = 14;
        var CLUSTER_UNTIL_ZOOM = 3;
        var REST_LAT           = 25;             // ~North-Africa latitude → Greece sits high (photo 2). Use ~38 to centre Greece (photo 1).
        var restZoom           = DEFAULT_ZOOM;   // ACTUAL settled resting zoom (set on load); used as the zoom-out floor
        var REST_REF_WIDTH     = 660;            // FIXED reference box width (px): the globe frames at DEFAULT_ZOOM here; every other width scales the zoom by log2(width/this) → SAME composition whether the page loads at desktop or mobile width
        var loaded             = false;          // true once the load handler has framed; gates the resize re-frame (was keyed off REST_REF_WIDTH, now a constant)

        // Resting camera target: the venue's longitude (keeps Greece horizontally
        // centred) but aimed at REST_LAT, so the globe shows the orientation above
        // without moving the editions pins (those stay on defaultCenter).
        var restCenter = [defaultCenter[0], REST_LAT];

        // Editions that shared a venue have IDENTICAL coords (Athens hosted 7,
        // Thessaloniki/Heraklion 3, Lamia 2). Rather than stack overlapping pins
        // (or fan them into a ring), collapse each same-place group into ONE
        // marker that remembers every edition it holds, so the map shows a single
        // pin per venue and hovering it lights up ALL of that venue's editions in
        // the sidebar. `pins` keeps the full per-edition list — the sidebar,
        // fly-to and the zoomed-out "all editions" dot still need it.
        var markers = groupColocated(pins);

        // ---- DOM: aspect-boxed map + custom controls ----
        mount.innerHTML = '';
        mount.style.position = 'relative';

        var box = document.createElement('div');
        box.style.position = 'relative';
        box.style.width = '100%';
        box.style.aspectRatio = '10 / 7';
        box.style.overflow = 'hidden';
        box.style.background = PAPER;
        mount.appendChild(box);

        var mapEl = document.createElement('div');
        mapEl.style.position = 'absolute';
        mapEl.style.inset = '0';
        mapEl.style.background = PAPER;
        box.appendChild(mapEl);

        var map = new maplibregl.Map({
            container: mapEl,
            style: STYLE_URL,
            center: restCenter,
            zoom: DEFAULT_ZOOM,
            minZoom: MIN_ZOOM,
            maxZoom: MAX_ZOOM,
            dragRotate: false,
            pitchWithRotate: false,
            touchPitch: false,
            attributionControl: { compact: true }
        });

        // Custom controls — same look as the old globe's buttons (now bottom-left).
        buildControls(box, map, defaultCenter, DEFAULT_ZOOM, resetView);

        var highlightedYears = [];     // edition years currently lit up in the sidebar
        var highlightKey = '';         // dedup key so repeated hovers don't re-apply

        map.on('load', function () {
            try { map.setProjection({ type: 'globe' }); } catch (e) {}
            recolor();
            // Width-responsive resting zoom (restingZoom()) — framed IDENTICALLY at
            // any load width. Lower the zoom-out floor to it FIRST, so a narrow
            // (mobile) box can actually settle at its lower resting zoom instead of
            // being clamped to MIN_ZOOM and staying oversized / cropped.
            restZoom = restingZoom();
            map.setMinZoom(Math.min(restZoom, MIN_ZOOM));
            frameView();
            restZoom = map.getZoom();   // actual settled zoom = the zoom-out floor
            map.setMinZoom(restZoom);
            loaded = true;

            addDefaultImages();
            setupLayers();
            wireMapInteractions();
            wireSidebar();
            startSpin();
            watchSectionLeave();

            // Swap in the admin pixel sprites once they load (keeps the default
            // pins visible meanwhile). Same ids → layers refresh automatically.
            swapSprite(spriteUrl,    'fc-pin');
            swapSprite(spotlightUrl, 'fc-pin-spotlight');
        });

        // Re-fit on container resize. The globe's size is zoom-driven, so we also
        // recompute the resting zoom for the new width and re-frame WHEN the map is
        // at rest — that scales the whole globe with the box instead of letting
        // MapLibre crop a fixed-size globe. A zoomed-in user is left where they are
        // (we just resize + re-pad), but the zoom-out floor still tracks the new
        // resting zoom. setMinZoom is lowered BEFORE the jump so a narrower box can
        // actually settle at the lower resting zoom (not get clamped to the old floor).
        if ('ResizeObserver' in window) new ResizeObserver(function () {
            map.resize();
            // Until the load handler has framed, keep the original safe behaviour
            // (resize + re-pad only) — the map isn't ready for a re-frame yet.
            if (!loaded) { try { map.setPadding(camPad()); } catch (e) {} return; }
            var wasResting = Math.abs(map.getZoom() - restZoom) < 0.5;
            restZoom = restingZoom();
            try { map.setMinZoom(Math.min(restZoom, MAX_ZOOM)); } catch (e) {}
            if (wasResting) {
                map.jumpTo({ center: restCenter, zoom: restZoom, padding: camPad() });
            } else {
                try { map.setPadding(camPad()); } catch (e) {}
            }
        }).observe(box);

        // ---- map recolour ----
        function recolor() {
            var layers;
            try { layers = (map.getStyle() && map.getStyle().layers) || []; } catch (e) { return; }
            var waterSource = null, waterSourceLayer = null;   // for the coastline line
            var borderWidth = null, borderOpacity = null;      // copied off the country-border lines
            layers.forEach(function (ly) {
                try {
                    if (ly.type === 'symbol') {
                        // No place / road / water names anywhere — keep it label-free.
                        map.setLayoutProperty(ly.id, 'visibility', 'none');
                    } else if (ly.type === 'background') {
                        map.setPaintProperty(ly.id, 'background-color', LAND);          // land = paper white
                    } else if (/water|ocean|sea|river|lake|bay/i.test(ly.id)) {
                        if (ly.type === 'fill') {
                            map.setPaintProperty(ly.id, 'fill-color', WATER);            // sea = outline grey
                            if (ly['source-layer'] && !waterSourceLayer) {
                                waterSource      = ly.source;
                                waterSourceLayer = ly['source-layer'];
                            }
                        } else if (ly.type === 'line') {
                            map.setPaintProperty(ly.id, 'line-color', WATER);
                        }
                    } else if (ly.type === 'fill' && /(landcover|landuse|park|wood|forest|grass|green|residential|built|sand|rock)/i.test(ly.id)) {
                        map.setPaintProperty(ly.id, 'fill-color', CONT);               // continent cut-outs = darker grey
                    } else if (ly.type === 'line' && /(boundary|admin|border)/i.test(ly.id)) {
                        map.setPaintProperty(ly.id, 'line-color', CONT);               // country borders = the "inside" outline
                        if (borderWidth === null) {
                            try { borderWidth   = map.getPaintProperty(ly.id, 'line-width'); }   catch (e) {}
                            try { borderOpacity = map.getPaintProperty(ly.id, 'line-opacity'); } catch (e) {}
                        }
                    }
                } catch (e) {}
            });

            // Continent outline (coastline): a LINE drawn on the water polygons,
            // styled IDENTICALLY to the country-border lines above — same colour
            // (CONT), same width/opacity copied off them, normal line antialiasing
            // — so the outline of the continents looks the same as the outlines
            // inside them.
            if (waterSourceLayer && !map.getLayer('fc-coastline')) {
                var paint = { 'line-color': CONT };
                paint['line-width'] = (borderWidth != null) ? borderWidth : 0.8;
                if (borderOpacity != null) paint['line-opacity'] = borderOpacity;
                try {
                    map.addLayer({
                        id: 'fc-coastline',
                        type: 'line',
                        source: waterSource,
                        'source-layer': waterSourceLayer,
                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                        paint: paint
                    });
                } catch (e) {}
            }
        }

        // ---- pin images ----
        function makeDot(fill) {
            var s = 40, c = document.createElement('canvas');
            c.width = s; c.height = s;
            var ctx = c.getContext('2d');
            var cx = s / 2, cy = s / 2, r = s / 2 - 7;
            ctx.beginPath(); ctx.arc(cx, cy, r + 3, 0, Math.PI * 2); ctx.fillStyle = PAPER; ctx.fill();
            ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fillStyle = fill; ctx.fill();
            return ctx.getImageData(0, 0, s, s);
        }
        function addDefaultImages() {
            try {
                if (!map.hasImage('fc-pin')) map.addImage('fc-pin', makeDot(INK), { pixelRatio: 2 });
                // A distinct spotlight image is only needed when a spotlight sprite
                // is actually set — this accent dot is just its loading placeholder
                // until swapSprite() replaces it. With no sprite, the layers point
                // at 'fc-pin' (see spotImg), so nothing references a missing image.
                if (spotlightUrl && !map.hasImage('fc-pin-spotlight')) map.addImage('fc-pin-spotlight', makeDot(ACCENT), { pixelRatio: 2 });
            } catch (e) {}
        }
        // Load an admin sprite URL into a map image id, replacing the built-in
        // default. No-op for an empty URL (the default dot stays visible).
        function swapSprite(url, id) {
            if (!url) return;
            var img = new Image();
            img.onload = function () {
                try {
                    if (map.hasImage(id)) map.removeImage(id);
                    map.addImage(id, img, { pixelRatio: 2 });
                } catch (e) {}
            };
            img.src = url;
        }

        // ---- source + layers ----
        function setupLayers() {
            var features = markers.map(function (m) {
                return {
                    type: 'Feature',
                    id: m.id,
                    geometry: { type: 'Point', coordinates: [m.lon, m.lat] },
                    // `years` is the comma-joined list of every edition at this
                    // venue. MapLibre flattens array/object feature properties, so
                    // we keep a string and split it back in the hover/click handlers.
                    // `spotlight` (a plain boolean) drives the pin sprite + size.
                    properties: { years: m.years.join(','), city: m.city, url: m.url, spotlight: !!m.spotlight }
                };
            });

            // No pixel clustering (supercluster's grouping is unpredictable here —
            // a huge radius still split Greece into 2-3 blobs mid-zoom, and the
            // cluster centroid sat on empty map so zooming in lost the pins). A hard
            // zoom switch is exact instead:
            //   • zoom <  CLUSTER_UNTIL_ZOOM → ONE dot for everything (fc-all-pin).
            //   • zoom >= CLUSTER_UNTIL_ZOOM → that dot is gone and EVERY edition is
            //     its own pin (fc-points) — no in-between regional clusters.
            // The handoff is each layer's native maxzoom/minzoom, so exactly one
            // layer is ever on screen and a pin can never vanish into a gap.
            map.addSource('editions', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: features }
            });

            // The single "all editions" dot, shown only when zoomed out. Pinned to
            // the map's resting centre (the venue) so zooming into it lands right on
            // the editions rather than an empty patch of map.
            map.addSource('editions-all', {
                type: 'geojson',
                data: { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: defaultCenter } }
            });
            map.addLayer({
                id: 'fc-all-pin',
                type: 'symbol',
                source: 'editions-all',
                maxzoom: CLUSTER_UNTIL_ZOOM,   // hidden the moment you pass the threshold
                layout: {
                    // The single dot is the event's one hero pin at rest, so it
                    // ALWAYS wears the spotlight sprite (or the normal pin when none
                    // is uploaded) — independent of which editions are ticked.
                    'icon-image': spotImg,
                    'icon-size': spotSize,
                    'icon-anchor': 'bottom',   // base of the pin/flag sits on the point
                    'icon-allow-overlap': true,
                    'icon-ignore-placement': true
                }
            });

            // Individual pins — one per venue. A spotlighted edition swaps to the
            // 'fc-pin-spotlight' sprite at the spotlight scale; every other pin
            // uses 'fc-pin' at the regular scale. Shown only from CLUSTER_UNTIL_ZOOM
            // up — below that the single fc-all-pin stands in, so these vanish the
            // moment you zoom back out.
            //
            // NB: both icon-image and icon-size are DATA-DRIVEN off the feature's
            // `spotlight` PROPERTY (['get','spotlight']) — that IS allowed in layout
            // properties. What is NOT supported is `feature-state` in layout: a
            // `['feature-state', …]` size evaluates to null ("Expected value to be of
            // type number, but found null") and the whole layer silently stops
            // rendering. So keep these expressions property-based only; pin
            // hover/selection feedback stays on the sidebar (CSS), never on the icon.
            map.addLayer({
                id: 'fc-points',
                type: 'symbol',
                source: 'editions',
                minzoom: CLUSTER_UNTIL_ZOOM,
                layout: {
                    'icon-image': ['case', ['get', 'spotlight'], spotImg, 'fc-pin'],
                    'icon-anchor': 'bottom',   // base of the pin/flag sits on the exact coordinate
                    'icon-allow-overlap': true,
                    'icon-ignore-placement': true,
                    'icon-size': ['case', ['get', 'spotlight'], spotSize, PIN_BASE * pinScale]
                }
            });
        }

        // ---- map ↔ sidebar interactions ----
        function wireMapInteractions() {
            map.on('mouseenter', 'fc-points', function (e) {
                map.getCanvas().style.cursor = 'pointer';
                if (isMobile()) return;
                var f = e.features && e.features[0];
                if (f) highlightYears(yearsOf(f), false);   // one venue pin lights up ALL its editions
            });
            map.on('mouseleave', 'fc-points', function () {
                map.getCanvas().style.cursor = '';
                if (isMobile()) return;
                clearHighlight();
            });
            map.on('click', 'fc-points', function (e) {
                var f = e.features && e.features[0];
                if (!f) return;
                var url = f.properties && f.properties.url;
                if (url) { window.open(url, '_blank', 'noopener'); return; }
                yearsOf(f).forEach(sassAtYear);   // no archive for this venue — sass every edition
            });

            // The single zoomed-out dot stands in for every edition: hovering it
            // lights them ALL up in the sidebar; clicking zooms past the threshold
            // so the individual pins take over.
            map.on('mouseenter', 'fc-all-pin', function () {
                map.getCanvas().style.cursor = 'pointer';
                if (isMobile()) return;
                highlightYears(pins.map(function (p) { return p.year; }), false);
            });
            map.on('mouseleave', 'fc-all-pin', function () {
                map.getCanvas().style.cursor = '';
                if (isMobile()) return;
                clearHighlight();
            });
            map.on('click', 'fc-all-pin', function () {
                map.easeTo({ center: defaultCenter, zoom: CLUSTER_UNTIL_ZOOM + 1, padding: camPad() });
            });
        }

        function setState(year, state, on) {
            if (year == null) return;
            try { map.setFeatureState({ source: 'editions', id: year }, fcState(state, on)); } catch (e) {}
        }
        function fcState(state, on) { var o = {}; o[state] = on; return o; }

        // Light up one OR many editions in the sidebar (a cluster pin lights up all
        // of its co-located editions). Only flies the map when exactly one year is
        // highlighted (hovering/scrolling a single edition).
        function highlightYears(years, fly) {
            var arr = (years || []).map(Number).filter(function (y) { return !isNaN(y); });
            var key = arr.slice().sort(function (a, b) { return a - b; }).join(',');
            if (key === highlightKey) { if (fly && arr.length === 1) flyToYear(arr[0]); return; }
            clearHighlight();
            highlightKey = key;
            highlightedYears = arr;
            arr.forEach(function (year) {
                setState(year, 'hover', true);
                document.querySelectorAll('[data-fc-edition-year="' + year + '"]').forEach(function (b) {
                    b.classList.add('is-hovered');
                });
            });
            if (fly && arr.length === 1) flyToYear(arr[0]);
        }
        function highlightYear(year, fly) { highlightYears([year], fly); }
        function clearHighlight() {
            if (!highlightedYears.length) return;
            highlightedYears.forEach(function (year) {
                setState(year, 'hover', false);
                document.querySelectorAll('[data-fc-edition-year="' + year + '"]').forEach(function (b) {
                    b.classList.remove('is-hovered');
                });
            });
            highlightedYears = [];
            highlightKey = '';
        }
        function flyToYear(year) {
            var p = pinByYear(year);
            if (!p) return;
            map.flyTo({ center: [p.lon, p.lat], zoom: 8, speed: 4, padding: camPad() });
        }
        function pinByYear(year) {
            for (var i = 0; i < pins.length; i++) if (pins[i].year === year) return pins[i];
            return null;
        }
        // Parse a venue pin's comma-joined `years` property back into numbers.
        function yearsOf(f) {
            var raw = f && f.properties && f.properties.years;
            if (raw == null) return [];
            return String(raw).split(',').map(Number).filter(function (y) { return !isNaN(y); });
        }

        // Camera padding. Reserving space at the TOP pushes the globe DOWN so its
        // BOTTOM (~BOTTOM_HIDDEN of the box) is the part that's clipped. It
        // persists across camera ops, so the idle spin keeps the same frame. To
        // hide the TOP of the globe instead, move BOTTOM_HIDDEN onto `bottom:`.
        function camPad() {
            var h = box.clientHeight || Math.round(box.clientWidth * 0.7) || 0;
            return { top: Math.round(h * BOTTOM_HIDDEN), right: 0, bottom: 0, left: 0 };
        }
        // Resting zoom scaled to the box width. A MapLibre globe's on-screen size
        // is set by ZOOM (≈ proportional to 2^zoom), NOT by the container — so at a
        // FIXED zoom a narrower box just CROPS the globe instead of shrinking it
        // (the reported bug). We anchor DEFAULT_ZOOM to the width measured on load
        // (REST_REF_WIDTH) and shift the zoom by log2(width ratio): a half-width box
        // drops the zoom by 1, halving the globe so the WHOLE thing scales down and
        // the resting framing stays identical at any size.
        function restingZoom() {
            var w = box.clientWidth;
            if (!REST_REF_WIDTH || !w) return DEFAULT_ZOOM;
            var z = DEFAULT_ZOOM + Math.log2(w / REST_REF_WIDTH);
            return Math.max(0.5, Math.min(MAX_ZOOM, z));
        }
        function frameView() {
            map.jumpTo({ center: restCenter, zoom: restingZoom(), padding: camPad() });
        }
        function resetView() {
            clearHighlight();
            map.flyTo({ center: restCenter, zoom: restingZoom(), bearing: 0, pitch: 0, padding: camPad(), speed: 1.2 });
        }

        // ---- editions sidebar (desktop panel + mobile bar) ----
        var SASS = ['(Nope)', '(Boop)', '(So?)', '(Again)', '(Crickets)', '(Missed)'];
        function nextSass(cur) {
            var pool = cur ? SASS.filter(function (m) { return m !== cur; }) : SASS;
            return pool[Math.floor(Math.random() * pool.length)];
        }
        function sassAtYear(year) {
            document.querySelectorAll('[data-fc-edition-year="' + year + '"]').forEach(function (b) {
                var txt = b.querySelector('.fc-edition-text');
                if (!txt) return;
                if (!txt.getAttribute('data-fc-label')) txt.setAttribute('data-fc-label', txt.textContent);
                var msg = nextSass(txt.getAttribute('data-fc-sass') || '');
                txt.setAttribute('data-fc-sass', msg);
                txt.textContent = msg;
                clearTimeout(b._sassT);
                b._sassT = setTimeout(function () { txt.textContent = txt.getAttribute('data-fc-label'); }, 1400);
            });
        }

        function wireSidebar() {
            document.querySelectorAll('[data-fc-edition-year]').forEach(function (btn) {
                var year = parseInt(btn.getAttribute('data-fc-edition-year'), 10);
                var url  = btn.getAttribute('data-fc-edition-url') || '';

                btn.addEventListener('mouseenter', function () {
                    if (isMobile()) return;
                    highlightYear(year, true);
                });
                btn.addEventListener('mouseleave', function () {
                    if (isMobile()) return;
                    clearHighlight();
                });
                // <a> rows (with a URL) navigate natively in a new tab. Only the
                // link-less <button> rows need the sass reply on click/tap.
                if (btn.tagName !== 'A') {
                    btn.addEventListener('click', function () {
                        if (url) { window.open(url, '_blank', 'noopener'); return; }
                        sassAtYear(year);
                    });
                }
            });

            // Mobile bar: no hover — the leftmost item under the scroll is the
            // "hovered" one. Updates as you scroll; a trailing spacer (added in
            // the template) lets the last item reach the highlight edge.
            var bar = document.querySelector('[data-fc-editions-mobile]');
            if (bar) {
                var raf = null;
                bar.addEventListener('scroll', function () {
                    if (!isMobile()) return;
                    if (raf) return;
                    raf = requestAnimationFrame(function () {
                        raf = null;
                        selectLeftmost(bar);
                    });
                }, { passive: true });
            }
        }
        function selectLeftmost(bar) {
            var label = bar.querySelector('.fc-editions-label');
            var refX = bar.getBoundingClientRect().left + (label ? label.getBoundingClientRect().width : 0) + 8;
            var best = null, bestDx = Infinity;
            bar.querySelectorAll('[data-fc-edition-year]').forEach(function (b) {
                var r = b.getBoundingClientRect();
                var dx = Math.abs(r.left - refX);
                if (r.right > refX - 4 && dx < bestDx) { bestDx = dx; best = b; }
            });
            if (best) {
                var year = parseInt(best.getAttribute('data-fc-edition-year'), 10);
                highlightYear(year, true);
            }
        }

        // ---- idle auto-rotation (globe spin) ----
        var lastInteraction = performance.now();
        ['mousedown', 'wheel', 'touchstart', 'dragstart'].forEach(function (ev) {
            map.on(ev, function () { lastInteraction = performance.now(); });
        });
        var visible = true;
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; }, { rootMargin: '80px' }).observe(box);
        }
        function startSpin() {
            if (reducedMotion) return;
            var IDLE_MS = 2500, STEP = 0.06;
            (function spin() {
                requestAnimationFrame(spin);
                if (!visible) return;
                var now = performance.now();
                if (now - lastInteraction < IDLE_MS) return;
                if (Math.abs(map.getZoom() - restZoom) > 0.35) return;
                if (map.isMoving() || map.isZooming()) return;
                // Normal Earth-axis spin: pan the centre longitude so the globe
                // turns on the equator/meridian like a real planet. The editions
                // (all in Greece) rotate to the back for part of each turn and
                // return — the intended true-globe behaviour.
                var c = map.getCenter();
                map.setCenter([c.lng + STEP, c.lat]);
            })();
        }

        // ---- auto-reset when the venue section leaves the active threshold ----
        function watchSectionLeave() {
            var sectionEl = box.closest('section');
            if (!sectionEl) return;
            var THRESH = 0.35, wasActive = null;
            function venueActive() {
                var secs = document.querySelectorAll('section');
                if (!secs.length) return false;
                var trigger = window.scrollY + window.innerHeight * THRESH;
                var activeId = null;
                secs.forEach(function (s) {
                    var top = s.getBoundingClientRect().top + window.scrollY;
                    if (top <= trigger) activeId = s.id;
                });
                return activeId === sectionEl.id;
            }
            function check() {
                var a = venueActive();
                if (wasActive === null) { wasActive = a; return; }
                if (wasActive && !a) resetView();
                wasActive = a;
            }
            window.addEventListener('scroll', check, { passive: true });
            window.addEventListener('resize', check);
            check();
        }
    }

    function computeCenter(pins, venueLat, venueLon) {
        if (!isNaN(venueLat) && !isNaN(venueLon)) return [venueLon, venueLat];
        var cur = null, i;
        for (i = 0; i < pins.length; i++) if (pins[i].spotlight) { cur = pins[i]; break; }
        if (cur) return [cur.lon, cur.lat];
        if (pins.length) {
            var la = 0, lo = 0;
            for (i = 0; i < pins.length; i++) { la += pins[i].lat; lo += pins[i].lon; }
            return [lo / pins.length, la / pins.length];
        }
        return [23.73, 37.98]; // Athens fallback
    }

    // Collapse editions that share a venue into ONE marker per venue, so the map
    // shows a single pin no matter how many years a city hosted. Grouping is by
    // EXACT coordinate equality: only editions whose lat AND lon match to the last
    // digit merge (the admin stores them as the same decimal string, so identical
    // venues parse to identical numbers). Two distinct spots in the same city —
    // even a few metres apart — stay separate pins. Each marker keeps:
    //   • years   — every edition year at this venue, so a hover can light them
    //               ALL up in the sidebar at once (see yearsOf / highlightYears);
    //   • lat/lon — the shared coordinate (identical → exactly that point);
    //   • url     — a representative archive link (the most recent edition that
    //               has one) for a click on the merged pin;
    //   • id      — the venue's latest year: unique per venue and stable across
    //               reloads, used as the GeoJSON feature id.
    // Groups of one (Patras, Syros, Larissa, Piraeus…) become a one-year marker.
    // Does NOT mutate pins (it returns a fresh markers array), so fly-to and the
    // sidebar keep working off the original per-edition coordinates.
    function groupColocated(pins) {
        var groups = [];
        pins.forEach(function (p) {
            for (var i = 0; i < groups.length; i++) {
                var a = groups[i][0];
                if (p.lat === a.lat && p.lon === a.lon) { groups[i].push(p); return; }
            }
            groups.push([p]);
        });
        return groups.map(function (g) {
            g.sort(function (x, y) { return x.year - y.year; });
            var n = g.length, spotlight = false, url = '', k;
            for (k = 0; k < n; k++) {
                if (g[k].spotlight) spotlight = true;
            }
            // Click target for the merged pin: the most recent edition that
            // actually has an archive link.
            for (k = n - 1; k >= 0; k--) { if (g[k].url) { url = g[k].url; break; } }
            return {
                lat:       g[0].lat,        // identical across the group by definition
                lon:       g[0].lon,
                years:     g.map(function (p) { return p.year; }),
                city:      g[n - 1].city,
                url:       url,
                spotlight: spotlight,       // any spotlighted edition marks the whole venue pin
                id:        g[n - 1].year     // unique, stable marker id (the venue's latest year)
            };
        });
    }

    function buildControls(box, map, defaultCenter, defaultZoom, resetView) {
        var ctrlGap = 'clamp(8px, 1.5vw, 16px)';
        var controls = document.createElement('div');
        controls.style.position = 'absolute';
        controls.style.left = ctrlGap;
        controls.style.bottom = ctrlGap;
        controls.style.display = 'flex';
        controls.style.flexDirection = 'row';
        controls.style.gap = '4px';
        controls.style.alignItems = 'center';
        controls.style.zIndex = '2';
        controls.style.fontFamily = 'JetBrains Mono, ui-monospace, monospace';
        controls.style.fontSize = '14px';
        var btnStyle = 'border:1px solid var(--ink-faint);background:#FAFAF7;color:var(--ink);width:28px;height:28px;line-height:1;cursor:pointer;padding:0;font-family:inherit;font-size:inherit;';
        function mk(label, aria, fn) {
            var b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('style', btnStyle);
            b.setAttribute('aria-label', aria);
            b.textContent = label;
            b.addEventListener('click', fn);
            controls.appendChild(b);
            return b;
        }
        mk('+', 'Zoom in', function () { map.zoomIn(); });
        mk('−', 'Zoom out', function () { map.zoomOut(); });
        mk('⌂', 'Reset view', function () { resetView(); });
        box.appendChild(controls);
    }
})();
