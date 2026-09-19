/* FOSSCOMM 2026 — the speakers belt.
 *
 * What this file does, and all it does: decide whether the row of speakers is
 * wider than the screen, and if it is, duplicate it and hand the stylesheet two
 * numbers — how long one set is, and how long one lap should take. The movement
 * itself is a CSS animation on the compositor. Nothing here runs per frame.
 *
 * It replaces a 42KB carousel that drove the row from JavaScript: a
 * requestAnimationFrame loop writing a transform every frame, a drag gesture
 * with velocity and decay, and a hit test that sampled each cut-out's ALPHA
 * CHANNEL through a canvas so the pointer only counted as "on" a speaker when it
 * was over opaque pixels. Together they read layout and wrote style in the same
 * frames, which is the read-after-write pattern that forces a synchronous
 * layout of the whole document — on a phone, sixty times a second, next to a
 * wave canvas and a mascot doing their own work. That is what made the section
 * lag.
 *
 * What went with it, deliberately:
 *   • dragging — the row moves on its own, and a CSS animation cannot be
 *     dragged without putting the frame loop back;
 *   • the alpha hit test — :hover on the card is a pixel or two less precise
 *     around a cut-out's shoulders, and costs nothing;
 *   • the grab/grabbing cursors, which described the drag.
 *
 * Inert when the section is absent: it looks for [data-fc-speakers].
 */
(function () {
    'use strict';

    /* How fast the belt travels, in CSS pixels per second. The old one drifted
       at 22px/s; this is the same walk. */
    var SPEED = 22;

    var wraps = [].slice.call(document.querySelectorAll('[data-fc-speakers]'));
    if (!wraps.length) return;

    var belts = wraps.map(function (wrap) {
        var viewport = wrap.querySelector('[data-fc-spk-viewport]');
        var rail = wrap.querySelector('[data-fc-spk-rail]');
        if (!viewport || !rail) return null;
        return {
            wrap: wrap,
            viewport: viewport,
            rail: rail,
            // The cards as authored. Copies are appended after them and are the
            // only things ever removed, so this list stays the truth.
            originals: [].slice.call(rail.children),
            sets: 1
        };
    }).filter(Boolean);

    /** One set's width, measured on the originals only. */
    function setWidth(belt) {
        var w = 0;
        for (var i = 0; i < belt.originals.length; i++) {
            w += belt.originals[i].getBoundingClientRect().width;
        }
        return w;
    }

    /**
     * How many copies: enough to fill the viewport, plus TWO whole sets of
     * slack. Two movements are stacked on this row and each can consume a set —
     * the animation slides the rail by one before it loops, and a drag can pull
     * it by another before its offset wraps. With only the viewport covered,
     * dragging with the drift left a gap at the trailing end, which is the text
     * that "disappeared" on the strips.
     */
    function layout(belt) {
        var one = setWidth(belt);
        var view = belt.viewport.getBoundingClientRect().width;
        if (!one || !view) return;


        if (one <= view) {
            // Everyone fits: no belt, no copies, no animation. A three-speaker
            // conference should not have a carousel limping along with a gap.
            if (belt.sets !== 1) {
                while (belt.rail.children.length > belt.originals.length) {
                    belt.rail.removeChild(belt.rail.lastElementChild);
                }
                belt.sets = 1;
            }
            belt.wrap.classList.remove('is-looping');
            // Nothing to drag into: assets/belt-drag.js stands down at zero.
            belt.wrap.style.setProperty('--fc-drag-span', '0px');
            return;
        }

        var want = Math.max(3, Math.ceil(view / one) + 2);
        if (want !== belt.sets) {
            while (belt.rail.children.length > belt.originals.length) {
                belt.rail.removeChild(belt.rail.lastElementChild);
            }
            var frag = document.createDocumentFragment();
            for (var s = 1; s < want; s++) {
                for (var i = 0; i < belt.originals.length; i++) {
                    var copy = belt.originals[i].cloneNode(true);
                    // The same speakers again: in the page once is enough.
                    copy.setAttribute('aria-hidden', 'true');
                    frag.appendChild(copy);
                }
            }
            belt.rail.appendChild(frag);
            belt.sets = want;
        }

        belt.wrap.style.setProperty('--fc-belt-w', one.toFixed(2) + 'px');
        belt.wrap.style.setProperty('--fc-belt-dur', (one / SPEED).toFixed(2) + 's');
        /* How far one cycle of the animation moves the content, SIGNED: the row
         * travels left, so negative. assets/belt-drag.js turns a hand's pixels
         * into animation time with it, which is how the indicator above stays
         * in step with the row under it — one position, not two. */
        belt.wrap.style.setProperty('--fc-drag-span', (-one).toFixed(2) + 'px');
        // One dot per speaker, and the indicator's fill is animated on the same
        // clock — see .fc-spk-dots in the stylesheet.
        belt.wrap.style.setProperty('--fc-belt-count', String(belt.originals.length));
        belt.wrap.classList.add('is-looping');
    }

    function layoutAll() {
        for (var i = 0; i < belts.length; i++) layout(belts[i]);
    }

    layoutAll();

    /* Re-decided only when something could have changed the answer: the window's
       width, the webfont arriving (names are the widest part of a card), and the
       portraits decoding. Debounced, because a resize fires a stream of events
       and each pass measures every card. */
    var timer = 0;
    function later() {
        clearTimeout(timer);
        timer = setTimeout(layoutAll, 150);
    }
    window.addEventListener('resize', later, { passive: true });
    window.addEventListener('load', later);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(later);

    /* ── Who is the pointer actually on? ────────────────────────────────────
     *
     * A card's box is mostly empty air: the strip beside a short name, and the
     * transparent quarters of a cut-out. `:hover` on the card lights a speaker
     * while the pointer is plainly beside them, so the test is made properly —
     * the name, the roles and the ONLINE badge count as themselves, and the
     * photo counts only where its pixels are opaque.
     *
     * The alpha is read ONCE per image into a 64×64 map and cached on the
     * element; a test after that is one array lookup. The old carousel did the
     * same thing but re-read layout on every pointer move while a frame loop was
     * writing transforms — the cost was the company it kept, not the lookup.
     *
     * Events are coalesced to one pass per frame, and the whole thing is skipped
     * where there is no pointer to ask about.
     */
    if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) return;

    var GRID = 64;          // the alpha map, per side
    var OPAQUE = 40;        // 0-255; below this the pixel is "not the speaker"

    function alphaMap(img) {
        if (img.__fcAlpha !== undefined) return img.__fcAlpha;
        var map = null;
        try {
            var c = document.createElement('canvas');
            c.width = c.height = GRID;
            var ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(img, 0, 0, GRID, GRID);
            map = ctx.getImageData(0, 0, GRID, GRID).data;
        } catch (err) {
            // A cross-origin portrait taints the canvas. Rather than lose the
            // highlight entirely, the whole box counts as the speaker.
            map = null;
        }
        img.__fcAlpha = map;
        return map;
    }

    /** Is (x, y), in client coordinates, on an opaque pixel of this image? */
    function onInk(img, x, y) {
        var r = img.getBoundingClientRect();
        if (!r.width || !r.height) return false;
        var map = alphaMap(img);
        if (!map) return true;

        var nw = img.naturalWidth || r.width;
        var nh = img.naturalHeight || r.height;
        // `object-fit: contain` with `object-position: bottom left`: the picture
        // is scaled to fit, pinned to the left edge and standing on the floor.
        var scale = Math.min(r.width / nw, r.height / nh);
        var dw = nw * scale;
        var dh = nh * scale;
        var u = (x - r.left) / dw;
        var v = (y - (r.bottom - dh)) / dh;
        if (u < 0 || u > 1 || v < 0 || v > 1) return false;

        var col = Math.min(GRID - 1, Math.max(0, Math.floor(u * GRID)));
        var row = Math.min(GRID - 1, Math.max(0, Math.floor(v * GRID)));
        return map[(row * GRID + col) * 4 + 3] > OPAQUE;
    }

    function speakerAt(target, x, y) {
        if (!target || !target.closest) return null;
        var card = target.closest('.fc-spk-card');
        if (!card) return null;
        if (target.closest('.fc-spk-name, .fc-spk-roles, .fc-spk-online')) return card;
        var photo = target.closest('.fc-spk-photo');
        if (photo) {
            var img = photo.querySelector('.fc-spk-shot');
            if (img && onInk(img, x, y)) return card;
        }
        return null;
    }

    // Both lists: the belt on the landing page and the still one at /speakers/.
    var roots = [].slice.call(document.querySelectorAll('[data-fc-speakers], .fc-spk-grid'));
    roots.forEach(function (root) {
        var hot = null;
        var pending = null;
        var frame = 0;

        function paint() {
            frame = 0;
            var e = pending;
            pending = null;
            if (!e) return;
            var card = speakerAt(e.target, e.clientX, e.clientY);
            if (card === hot) return;
            if (hot) hot.classList.remove('is-hot');
            hot = card;
            if (hot) hot.classList.add('is-hot');
            // The row holds still while a speaker is lit, so a name can be read
            // and a card clicked without chasing it — and keeps moving while the
            // pointer is merely passing over the air between them.
            root.classList.toggle('is-holding', !!hot);
        }

        root.addEventListener('pointermove', function (e) {
            if (e.pointerType === 'touch') return;
            pending = e;
            if (!frame) frame = requestAnimationFrame(paint);
        }, { passive: true });

        root.addEventListener('pointerleave', function () {
            pending = null;
            if (hot) hot.classList.remove('is-hot');
            hot = null;
            root.classList.remove('is-holding');
        });
    });
}());
