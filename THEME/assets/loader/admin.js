/* FOSSCOMM 2026 — FOSSCOMM → Loading Screen, the interactive half.
 *
 * Three jobs:
 *   1. the flicker TIMELINE — add, move, duplicate and delete steps, keeping the
 *      last one the light "staying on" and every POST name in order;
 *   2. the small preview box on the Flicker tab, which is the real loader
 *      (window.FCLoader from assets/loader/loader.js) re-configured from the form
 *      on every edit;
 *   3. the big preview at the top: the real front page in an iframe, at a
 *      computer or a phone size, running the form's settings — saved or not —
 *      through the preview URL inc/loader.php understands.
 *
 * Nothing here posts anything or marks the page as changed by itself: the
 * preview controls are plain buttons, which raise no input/change events for the
 * shared unsaved-changes tracker (inc/admin/menu.php) to see.
 */
(function (w, d) {
    'use strict';

    var ADMIN = w.FC_LOADER_ADMIN || {};
    var form = d.querySelector('.fc-wrap form');
    if (!form || !w.FCLoader) return;

    var timeline = form.querySelector('[data-fcl-timeline]');
    var template = d.getElementById('fcl-step-template');
    var defaults = ADMIN.defaults || {};

    /* ── Reading the form ───────────────────────────────────────────────────
     * The option's raw shape, exactly as fc_loader_sanitize() takes it — the
     * preview URL carries this, and the small box reads it (with the colours
     * resolved) through FCLoader.readConfig(). */
    function field(key) { return form.querySelector('[name="fc_field[' + key + ']"]'); }
    function val(key) { var el = field(key); return el ? el.value : ''; }
    function ticked(key) { var el = field(key); return !!(el && el.checked); }

    function cells() { return timeline ? Array.prototype.slice.call(timeline.querySelectorAll('[data-fcl-step]')) : []; }

    function readSteps() {
        var list = cells();
        return list.map(function (cell, i) {
            var last = i === list.length - 1;
            return {
                state: last ? 'on' : cell.querySelector('[data-fcl-state]').value,
                ms: cell.querySelector('[data-fcl-ms]').value,
                bounce: cell.querySelector('[data-fcl-bounce]').value
            };
        });
    }

    function readRaw() {
        return {
            enabled: ticked('enabled'),
            where: val('where'),
            repeat: val('repeat'),
            logo_off: val('logo_off'),
            logo_on: val('logo_on'),
            tint_off: val('tint_off').trim(),
            tint_on: val('tint_on').trim(),
            size_desktop: val('size_desktop'),
            size_mobile: val('size_mobile'),
            glow: val('glow'),
            glow_size: val('glow_size'),
            glow_colour: val('glow_colour').trim(),
            glow_style: val('glow_style'),
            bounce_origin: val('bounce_origin'),
            bounce_ms: val('bounce_ms'),
            steps: readSteps(),
            max_wait_s: val('max_wait_s'),
            buzz: ticked('buzz'),
            mascot: ticked('mascot'),
            fall_at: val('fall_at'),
            swipe_sync: val('swipe_sync'),
            swipe_delay: val('swipe_delay'),
            layer1: val('layer1'),
            layer2: val('layer2'),
            layer3: val('layer3'),
            swipe_ms: val('swipe_ms'),
            gap_ms: val('gap_ms'),
            ease: val('ease'),
            edge: val('edge'),
            wave_height: val('wave_height'),
            wave_length: val('wave_length'),
            wave_speed: val('wave_speed'),
            layer2_dots: ticked('layer2_dots'),
            layer2_spot: ticked('layer2_spot'),
            reveal_build: ticked('reveal_build')
        };
    }

    /** The same, with "follow the site" resolved — what the small box runs on. */
    function readConfig() {
        var raw = readRaw();
        ['layer1', 'layer2', 'layer3'].forEach(function (k) {
            if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(raw[k] || '').trim())) raw[k] = defaults[k] || '';
            else raw[k] = String(raw[k]).trim();
        });
        return raw;
    }

    /* ── The timeline ───────────────────────────────────────────────────────
     * POST order is DOM order, so every change renumbers every name. The last
     * cell is always the light staying on: its state box is hidden (CSS, via
     * data-fcl-last) and forced to "on", and it cannot be moved or deleted. */
    var MAX_STEPS = w.FCLoader.MAX_STEPS || 60;     // "Stays on" included (FC_LOADER_MAX_STEPS)
    function renumber() {
        var list = cells();
        var full = list.length >= MAX_STEPS;
        list.forEach(function (cell, i) {
            var last = i === list.length - 1;
            cell.querySelector('.fcl-step-n').textContent = '#' + (i + 1);
            cell.querySelectorAll('[name^="fc_loader_steps["]').forEach(function (el) {
                el.name = el.name.replace(/^fc_loader_steps\[[^\]]*\]/, 'fc_loader_steps[' + i + ']');
            });
            if (last) {
                cell.setAttribute('data-fcl-last', '');
                cell.querySelector('[data-fcl-state]').value = 'on';
            } else {
                cell.removeAttribute('data-fcl-last');
            }
            cell.classList.toggle('is-on', last || cell.querySelector('[data-fcl-state]').value === 'on');
            // Nothing may be moved past "Stays on".
            var later = cell.querySelector('[data-fcl-move="1"]');
            if (later) later.disabled = i >= list.length - 2;
            var earlier = cell.querySelector('[data-fcl-move="-1"]');
            if (earlier) earlier.disabled = i === 0;
            // a full timeline takes no more (the server would drop the extras)
            var dup = cell.querySelector('[data-fcl-dup]');
            if (dup) dup.disabled = full;
        });
        var add = form.querySelector('[data-fcl-add]');
        if (add) add.disabled = full;
        var count = d.querySelector('[data-fcl-step-count]');
        if (count) count.textContent = String(list.length);
    }

    /** Tell the unsaved-changes tracker (and the preview) that something moved. */
    function announce() {
        var el = timeline && timeline.querySelector('[name]');
        if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function newCell(state, ms, bounce) {
        var holder = d.createElement('div');
        holder.innerHTML = template.innerHTML;
        var cell = holder.querySelector('[data-fcl-step]');
        cell.querySelector('[data-fcl-state]').value = state;
        cell.querySelector('[data-fcl-ms]').value = String(ms);
        cell.querySelector('[data-fcl-bounce]').value = String(bounce);
        return cell;
    }

    if (timeline && template) {
        timeline.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('button');
            var cell = b && b.closest('[data-fcl-step]');
            if (!cell || cell.hasAttribute('data-fcl-last')) return;
            var list = cells();
            if (b.hasAttribute('data-fcl-del')) {
                if (list.length > 1) cell.parentNode.removeChild(cell);
            } else if (b.hasAttribute('data-fcl-dup')) {
                if (list.length >= MAX_STEPS) return;
                var copy = cell.cloneNode(true);
                // cloneNode copies attributes, not what has been typed since.
                var from = cell.querySelectorAll('select, input');
                var to = copy.querySelectorAll('select, input');
                Array.prototype.forEach.call(from, function (el, k) { to[k].value = el.value; });
                copy.classList.remove('is-current');
                cell.parentNode.insertBefore(copy, cell.nextSibling);
            } else if (b.hasAttribute('data-fcl-move')) {
                var dir = Number(b.getAttribute('data-fcl-move'));
                var i = list.indexOf(cell);
                var j = i + dir;
                if (j < 0 || j > list.length - 2) return;      // never past "Stays on"
                if (dir < 0) cell.parentNode.insertBefore(cell, list[j]);
                else cell.parentNode.insertBefore(cell, list[j].nextSibling);
            } else {
                return;
            }
            renumber();
            announce();
        });

        timeline.addEventListener('change', function (e) {
            if (e.target.matches && e.target.matches('[data-fcl-state]')) renumber();
        });

        var add = form.querySelector('[data-fcl-add]');
        if (add) {
            add.addEventListener('click', function () {
                var list = cells();
                if (list.length >= MAX_STEPS) return;
                var last = list[list.length - 1];
                var before = list[list.length - 2];
                // Alternate with the step before it — that is what a flicker is.
                var prevOn = before ? before.querySelector('[data-fcl-state]').value === 'on' : false;
                var cell = prevOn ? newCell('off', 80, 0) : newCell('on', 60, 0.5);
                timeline.insertBefore(cell, last);
                renumber();
                announce();
            });
        }
        renumber();
    }

    /* ── Logos: the shared media picker fires no event ──────────────────────
     * inc/admin/menu.php writes the URL into the hidden input with jQuery's
     * .val(), which raises nothing — so neither the unsaved-changes warning nor
     * this page could hear a logo being picked. Its preview <img> changing is
     * the one thing that always happens; watch that, and raise the event. */
    form.querySelectorAll('.fcl-logos .fc-media').forEach(function (box) {
        var input = box.querySelector('.fc-media-input');
        var preview = box.querySelector('.fc-media-preview');
        if (!input || !preview || !w.MutationObserver) return;
        new MutationObserver(function () {
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }).observe(preview, { childList: true, subtree: true, attributes: true, attributeFilter: ['src'] });
    });

    /* ── Colours that can be emptied ────────────────────────────────────────
     * An empty colour box means something here — the site's colour, the logo's
     * own colours, white — so each has a "Clear" link to get back to it, shown
     * only while the box holds a colour. */
    function syncClear(hexBox) {
        var link = hexBox.parentNode && hexBox.parentNode.querySelector('[data-fcl-clear-colour]');
        if (link) link.hidden = !String(hexBox.value || '').trim();
    }
    form.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('[data-fcl-clear-colour]');
        if (!link) return;
        var hexBox = link.parentNode.querySelector('.fc-colour-hex');
        if (!hexBox) return;
        hexBox.value = '';
        link.hidden = true;
        hexBox.dispatchEvent(new Event('input', { bubbles: true }));
        hexBox.dispatchEvent(new Event('change', { bubbles: true }));
    });
    form.addEventListener('input', function (e) {
        var t = e.target;
        if (t.classList && t.classList.contains('fc-colour-hex')) syncClear(t);
        // The picker writes into its hex box from a handler that runs after
        // this one, so look again a moment later.
        if (t.classList && t.classList.contains('fc-colour-pick')) {
            var box = t.parentNode.querySelector('.fc-colour-hex');
            if (box) setTimeout(function () { syncClear(box); }, 0);
        }
    });

    /* ── The small preview box (Flicker tab) ──────────────────────────────── */
    var mini = form.querySelector('[data-fcl-mini]');
    var miniRoot = mini && mini.querySelector('.fc-loader');
    var ctl = null;
    var miniOptions = { stage: true, direction: 'left', maxLogo: 0, onStep: markStep };

    function markStep(i) {
        cells().forEach(function (cell, k) { cell.classList.toggle('is-current', k === i); });
    }
    function clearMarks() { markStep(-1); }

    function fitLogo() {
        // Keep the logo inside the box, at its real size where it fits.
        if (!mini) return;
        var r = mini.getBoundingClientRect();
        if (r.width && r.height) miniOptions.maxLogo = Math.max(24, Math.floor(Math.min(r.width * 0.55, r.height * 0.7)));
    }

    if (miniRoot) {
        fitLogo();
        ctl = w.FCLoader.create(miniRoot, readConfig(), miniOptions);
        ctl.light(true);
    }

    var lastLogos = { off: val('logo_off'), on: val('logo_on') };
    var pending = 0;
    var pendingPop = null;

    /* Re-read the whole form a frame later. Later on purpose: the shared colour
       picker copies its value into the hex box from a handler that runs AFTER
       this one, so reading straight away would show the colour before last. */
    function refresh() {
        pending = 0;
        refreshEntrance();
        if (!ctl) return;
        fitLogo();
        ctl.setConfig(readConfig());
        var logos = { off: val('logo_off'), on: val('logo_on') };
        if (logos.off !== lastLogos.off || logos.on !== lastLogos.on) {
            ctl.setLogos(logos);
            if (eCtl) eCtl.setLogos(logos);
            lastLogos = logos;
        }
        if (pendingPop) {
            ctl.light(pendingPop.on);
            ctl.pop(pendingPop.bounce);
            pendingPop = null;
        }
    }
    function schedule() { if (!pending) pending = requestAnimationFrame(refresh); }

    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    /* The box is measured when the page loads, while its tab is still hidden
       (0 × 0): the logo is fitted again whenever the box gets a size — its tab
       shown, the window resized. */
    if (mini && w.ResizeObserver) new ResizeObserver(function () { schedule(); }).observe(mini);
    // Typing a bounciness pops that step once, so the number has a feel to it.
    if (timeline) {
        timeline.addEventListener('input', function (e) {
            var t = e.target;
            if (!t.matches || !t.matches('[data-fcl-bounce]')) return;
            var cell = t.closest('[data-fcl-step]');
            var b = parseFloat(t.value);
            if (!cell || !(b > 0)) return;
            pendingPop = { on: cell.classList.contains('is-on'), bounce: Math.min(10, b) };
        });
    }

    function lastBounce() {
        var list = cells();
        var last = list[list.length - 1];
        var b = last ? parseFloat(last.querySelector('[data-fcl-bounce]').value) : 2;
        return isFinite(b) ? b : 2;
    }

    function miniButton(sel, fn) {
        var b = form.querySelector(sel);
        if (b) b.addEventListener('click', fn);
    }
    /* Each button press is a new run of the box: whatever an earlier one left
       waiting — the cover going back after a swipe, a flicker's highlight in
       the timeline — is called off, so it cannot cut into this one. */
    var miniGen = 0, miniTimer = 0;
    function miniStart() {
        miniGen++;
        clearTimeout(miniTimer);
        miniTimer = 0;
        clearMarks();
        return miniGen;
    }
    miniButton('[data-fcl-mini-play]', function () {
        if (!ctl) return;
        var gen = miniStart();
        refresh();
        ctl.reset();
        ctl.flicker().then(function () { if (gen === miniGen) clearMarks(); });
    });
    miniButton('[data-fcl-mini-pop]', function () {
        if (!ctl) return;
        // mid-swipe or holding the "site": the cover back first, so the pop shows
        if (miniRoot.hasAttribute('data-phase')) { miniStart(); ctl.reset(); }
        ctl.light(true);
        ctl.pop(lastBounce());
    });
    miniButton('[data-fcl-mini-swipe]', function () {
        if (!ctl) return;
        var gen = miniStart();
        refresh();
        ctl.reset();
        ctl.light(true);
        ctl.exit().then(function (ok) {
            if (!ok || gen !== miniGen) return;
            // Hold the revealed "site" for a moment, then put the cover back.
            miniTimer = setTimeout(function () {
                miniTimer = 0;
                if (gen !== miniGen) return;
                ctl.reset();
                ctl.light(true);
            }, 900);
        });
    });

    /* ── The Opening preview (Mascot & swipe tab) ─────────────────────────────
     * The whole entrance in a box, by the settings it depends on: the lit
     * logo; the mascot — the real one, as saved in FOSSCOMM → Mascot — taking
     * its place and playing Opening; the layers sweeping away with this page's
     * edge, straight or the wave, the white one with the site's moving dots;
     * and his fall at the moment set. This form's settings, saved or not; the
     * mascot's module is only fetched the first time it plays. */
    var ebox = form.querySelector('[data-fcl-entrance]');
    var eRoot = ebox && ebox.querySelector('.fc-loader');
    var eOptions = { stage: true, direction: 'left', maxLogo: 0 };
    var eCtl = null, eRun = 0, eMascot = null, eMascotLoad = null, eMount = null;

    function eFit() {
        var r = ebox.getBoundingClientRect();
        if (r.width && r.height) eOptions.maxLogo = Math.max(24, Math.floor(Math.min(r.width * 0.45, r.height * 0.55)));
        return r.width > 0 && r.height > 0;
    }
    /* Made once the box has a size (its tab is hidden when the page loads);
       after that, kept in step with the form like the small box. */
    function refreshEntrance() {
        if (!eRoot || !eFit()) return;
        if (!eCtl) {
            eCtl = w.FCLoader.create(eRoot, readConfig(), eOptions);
            eCtl.light(true);
        } else {
            eCtl.setConfig(readConfig());
        }
    }
    function loadMascot() {
        if (eMascot) return Promise.resolve(eMascot);
        if (eMascotLoad) return eMascotLoad;
        if (!ADMIN.mascotEnabled || !ADMIN.mascotModule || !w.FC_MASCOT) return Promise.resolve(null);
        eMascotLoad = import(ADMIN.mascotModule).then(function (mod) {
            var mount = eMount = d.createElement('div');
            mount.id = 'fosscomm-mascot';
            mount.setAttribute('aria-hidden', 'true');
            ebox.appendChild(mount);
            // his real size; the box is no phone, so no phone size either
            return mod.createMascot(mount, Object.assign({}, w.FC_MASCOT, { mobileBreakpoint: 0 }), { arrival: 'wait' });
        }).then(function (m) {
            eMascot = m;
            return m;
        }, function (err) {
            if (w.console) console.warn('[loader] the mascot could not be loaded for the preview', err);
            var note = form.querySelector('[data-fcl-entrance-note]');
            if (note) note.textContent = 'The mascot could not be loaded here — playing the logo and the swipe without him.';
            return null;
        });
        return eMascotLoad;
    }

    if (eRoot) {
        if (w.ResizeObserver) new ResizeObserver(function () { refreshEntrance(); }).observe(ebox);
        var eplay = form.querySelector('[data-fcl-entrance-play]');
        if (eplay) eplay.addEventListener('click', function () {
            var mine = ++eRun;
            refreshEntrance();
            if (!eCtl) return;
            loadMascot().then(function (m) {
                if (mine !== eRun) return;
                refreshEntrance();
                // nowhere to be seen until he takes the logo's place, as on the site
                if (eMount) eMount.style.visibility = 'hidden';
                var show = function () { if (eMount && mine === eRun) eMount.style.visibility = ''; };
                eCtl.entrance(m, { takeover: show }).then(show);
            });
        });
        form.querySelectorAll('[data-fcl-entrance-dir]').forEach(function (b) {
            b.addEventListener('click', function () {
                eOptions.direction = b.getAttribute('data-fcl-entrance-dir') === 'up' ? 'up' : 'left';
                form.querySelectorAll('[data-fcl-entrance-dir]').forEach(function (o) {
                    o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
                });
            });
        });
    }

    /* ── The big preview: the real site in an iframe ──────────────────────── */
    var card = d.querySelector('[data-fcl-preview]');
    var wrap = card && card.querySelector('[data-fcl-frame-wrap]');
    var empty = card && card.querySelector('[data-fcl-frame-empty]');
    var DEVICES = { desktop: { w: 1440, h: 900 }, phone: { w: 390, h: 844 } };
    var device = 'desktop';
    var slow = 0;
    var frame = null;

    function press(group, value) {
        card.querySelectorAll('[data-fcl-' + group + ']').forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-fcl-' + group) === String(value) ? 'true' : 'false');
        });
    }

    /* Scale the device-sized page down to the card: the page is laid out at the
       real size (so the breakpoint, the swipe direction and the mascot's size are
       the real ones) and only drawn smaller. */
    function layout() {
        if (!wrap) return;
        var dev = DEVICES[device];
        var avail = Math.max(240, wrap.parentNode.clientWidth);
        var maxH = device === 'phone' ? 640 : 560;
        var k = Math.min(1, avail / dev.w, maxH / dev.h);
        wrap.style.width = Math.round(dev.w * k) + 'px';
        wrap.style.height = Math.round(dev.h * k) + 'px';
        if (frame) {
            frame.style.width = dev.w + 'px';
            frame.style.height = dev.h + 'px';
            frame.style.transform = 'scale(' + k + ')';
        }
    }

    /* base64url of the UTF-8 JSON — what fc_loader_b64url_decode() reads. */
    function b64url(text) {
        var bin = unescape(encodeURIComponent(text));
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function play() {
        if (!wrap || !ADMIN.previewBase) return;
        var url = ADMIN.previewBase
            + (ADMIN.previewBase.indexOf('?') >= 0 ? '&' : '?')
            + 'fc_loader_preview=' + b64url(JSON.stringify(readRaw()))
            + '&fc_loader_slow=' + slow
            + '&_t=' + Date.now();          // a fresh load every time, never a cached one
        if (empty) empty.style.display = 'none';
        if (frame) frame.parentNode.removeChild(frame);
        frame = d.createElement('div');
        frame.className = 'fcl-frame';
        var iframe = d.createElement('iframe');
        iframe.setAttribute('title', 'Loading screen preview');
        iframe.src = url;
        frame.appendChild(iframe);
        wrap.appendChild(frame);
        layout();
    }

    if (card) {
        card.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('button');
            if (!b) return;
            if (b.hasAttribute('data-fcl-device')) {
                device = b.getAttribute('data-fcl-device') === 'phone' ? 'phone' : 'desktop';
                press('device', device);
                layout();
                if (frame) play();
            } else if (b.hasAttribute('data-fcl-slow')) {
                slow = Number(b.getAttribute('data-fcl-slow')) || 0;
                press('slow', slow);
            } else if (b.hasAttribute('data-fcl-play')) {
                play();
            }
        });
        layout();
        w.addEventListener('resize', layout);
    }
}(window, document));
