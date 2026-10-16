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

    /* The Communities row is the same belt running the other way. It borrows
       the viewport/rail markers, and says which way it travels with
       data-fc-belt-dir="right"; its CSS reverses the animation, and the sign
       of --fc-drag-span below follows, so a hand still moves it with the hand. */
    var wraps = [].slice.call(document.querySelectorAll('[data-fc-speakers], [data-fc-communities]'));
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
                    // State belongs to the card under the pointer, never to its
                    // repeat: a card lit when the row was rebuilt stays lit
                    // otherwise, as nothing here ever points at the clone.
                    if (copy.classList.contains('is-hot')) copy.classList.remove('is-hot');
                    [].forEach.call(copy.querySelectorAll('.is-hot'), function (n) { n.classList.remove('is-hot'); });
                    [].forEach.call(copy.querySelectorAll('[data-fc-hov], [data-fc-on]'), function (n) {
                        n.removeAttribute('data-fc-hov'); n.removeAttribute('data-fc-on');
                    });
                    frag.appendChild(copy);
                }
            }
            belt.rail.appendChild(frag);
            belt.sets = want;
        }

        belt.wrap.style.setProperty('--fc-belt-w', one.toFixed(2) + 'px');
        belt.wrap.style.setProperty('--fc-belt-dur', (one / SPEED).toFixed(2) + 's');
        /* How far one cycle of the animation moves the content, SIGNED: the row
         * travels left, so negative — unless it is a row that runs right.
         * assets/belt-drag.js turns a hand's pixels into animation time with it,
         * which is how the indicator above stays in step with the row under it —
         * one position, not two. */
        var dir = belt.wrap.getAttribute('data-fc-belt-dir') === 'right' ? 1 : -1;
        belt.wrap.style.setProperty('--fc-drag-span', (dir * one).toFixed(2) + 'px');
        // One dot per speaker, and the indicator's fill is animated on the same
        // clock — see .fc-spk-dots in the stylesheet.
        belt.wrap.style.setProperty('--fc-belt-count', String(belt.originals.length));
        belt.wrap.classList.add('is-looping');
        armWarm(belt);
    }

    /* A row that LOOPS brings every portrait past the window in turn, so none
     * stays out of sight for long — and left lazy, each was fetched (and
     * decoded) only as it neared the edge, mid-animation: the hitch now and
     * then. So once the row is about a screen away, every picture in it is
     * asked for at once (copies made later take the originals' `loading`);
     * not at page load, which would cost a phone every portrait whether or not
     * anyone scrolled down to them.
     * Where there is a mouse (a desktop, with memory to spare) they are also
     * DECODED ahead, once per file, while the page is idle. Not on a phone:
     * measured on a throttled one, decoding all of them at once cost more than
     * it saved. */
    function armWarm(belt) {
        if (belt.warmArmed) return;
        belt.warmArmed = true;
        if (!('IntersectionObserver' in window)) { warm(belt); return; }
        var io = new IntersectionObserver(function (entries) {
            if (!entries[0].isIntersecting) return;
            io.disconnect();
            warm(belt);
        }, { rootMargin: '100% 0px' });
        io.observe(belt.wrap);
    }
    function warm(belt) {
        var imgs = belt.rail.querySelectorAll('img');
        for (var i = 0; i < imgs.length; i++) {
            if (imgs[i].loading === 'lazy') imgs[i].loading = 'eager';
        }
        if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) return;
        var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 200); };
        idle(function () {
            var seen = {};
            for (var k = 0; k < imgs.length; k++) {
                var im = imgs[k], u = im.currentSrc || im.src;
                if (!u || seen[u] || !im.decode) continue;
                seen[u] = true;
                im.decode().catch(function () {});
            }
        });
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
    if (!window.matchMedia || !window.matchMedia('(hover: hover)').matches) {
        /* A phone has no hover to hold a row with — but a row off screen still
           holds still there, belt and dots together (see `away` below). */
        if ('IntersectionObserver' in window) {
            [].forEach.call(document.querySelectorAll('[data-fc-speakers], [data-fc-communities]'), function (root) {
                new IntersectionObserver(function (entries) {
                    var hold = !entries[0].isIntersecting;
                    root.toggleAttribute('data-fc-away', hold);   // the ONLINE pulses, in site.css
                    if (!root.getAnimations || root.classList.contains('is-dragging')) return;
                    [].forEach.call(root.querySelectorAll('.fc-spk-rail, .fc-com-rail, .fc-spk-dots, .fc-com-dots'), function (el) {
                        el.getAnimations().forEach(function (a) {
                            if (!/^(fc-spk-belt|fc-spk-progress|fc-com-belt|fc-com-progress)$/.test(a.animationName || '')) return;
                            if (hold) a.pause(); else a.play();
                        });
                    });
                }, { rootMargin: '100px' }).observe(root);
            });
        }
        return;
    }

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

    /** Is (x, y), in client coordinates, on an opaque pixel of this image?
        `centred`: the picture sits in the MIDDLE of its box (a community's
        logo) rather than on the floor at the left (a speaker's portrait). */
    function onInk(img, x, y, centred) {
        var r = img.getBoundingClientRect();
        if (!r.width || !r.height) return false;
        var map = alphaMap(img);
        if (!map) return true;

        var nw = img.naturalWidth || r.width;
        var nh = img.naturalHeight || r.height;
        // `object-fit: contain` with `object-position: bottom left`: the picture
        // is scaled to fit, pinned to the left edge and standing on the floor —
        // or, centred, with equal room either side and above and below.
        var scale = Math.min(r.width / nw, r.height / nh);
        var dw = nw * scale;
        var dh = nh * scale;
        var left = centred ? r.left + (r.width - dw) / 2 : r.left;
        var top = centred ? r.top + (r.height - dh) / 2 : r.bottom - dh;
        var u = (x - left) / dw;
        var v = (y - top) / dh;
        if (u < 0 || u > 1 || v < 0 || v > 1) return false;

        var col = Math.min(GRID - 1, Math.max(0, Math.floor(u * GRID)));
        var row = Math.min(GRID - 1, Math.max(0, Math.floor(v * GRID)));
        return map[(row * GRID + col) * 4 + 3] > OPAQUE;
    }

    /* The belt's own animations, paused while a card is lit and playing when
       not. The CSS says the same with animation-play-state — but once
       assets/belt-drag.js has paused or played them by hand (a drag does),
       the CSS no longer decides until .is-holding changes again. Saying it
       here too keeps the two in step. Left alone mid-drag: the hand owns the
       timeline then, and belt-drag.js hands it back itself. */
    var BELT_NAMES = /^(fc-spk-belt|fc-spk-progress|fc-com-belt|fc-com-progress)$/;
    /* Asked of the four things that carry them — the rails and the progress
       dots — and not of the whole row: a row's subtree holds every card's
       animations too (and the cursor spotlight's copies of them), and walking
       all of that on each card the pointer crossed was a stall of its own. */
    var BELT_PARTS = '.fc-spk-rail, .fc-com-rail, .fc-spk-dots, .fc-com-dots';
    // run fn once the frame being made now is drawn (a message is handled
    // after the frame's rendering; a timeout can be held back much longer)
    var later = [], laterPort = null;
    function afterFrame(fn) {
        if (!window.MessageChannel) { setTimeout(fn, 0); return; }
        if (!laterPort) {
            var ch = new MessageChannel();
            ch.port1.onmessage = function () { var q = later; later = []; for (var i = 0; i < q.length; i++) q[i](); };
            laterPort = ch.port2;
        }
        later.push(fn);
        if (later.length === 1) laterPort.postMessage(0);
    }
    function holdAnimations(root, hold) {
        if (!root.getAnimations || root.classList.contains('is-dragging')) return;
        var parts = root.querySelectorAll(BELT_PARTS);
        for (var i = 0; i < parts.length; i++) {
            // the cursor spotlight's copies follow their originals' clocks
            // (assets/spotlight-dots.js) and are held while out of its reach
            if (parts[i].closest('.fc-spot-dupwrap')) continue;
            parts[i].getAnimations().forEach(function (a) {
                if (!BELT_NAMES.test(a.animationName || '')) return;
                if (hold) a.pause(); else a.play();
            });
        }
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
        // A community's card: its logo counts where its pixels are opaque.
        var logoBox = target.closest('.fc-com-logo-box');
        if (logoBox) {
            // the logo itself — not the cursor spotlight's copy of it, which
            // sits in the same box (assets/spotlight-dots.js) and comes first
            var logo = logoBox.querySelector(':scope > .fc-com-logo');
            if (logo && onInk(logo, x, y, true)) return card;
        }
        return null;
    }

    /* The belt on the landing page. The grid at /speakers/ is listed too, but in
       practice this file has already returned above when there is no belt on the
       page — and it does not need it: nothing moves in a grid, so a plain
       `:hover` on the card is honest there and the page's own stylesheet uses
       one. The selector stays so that a page carrying both would behave. */
    /* The Communities row takes the same test: the name, its small lines, or an
       opaque pixel of its logo — anything else on the card is air. */
    var roots = [].slice.call(document.querySelectorAll('[data-fc-speakers], .fc-spk-grid, [data-fc-communities]'));
    roots.forEach(function (root) {
        var hot = null;
        var pending = null;
        var frame = 0;
        /* Off screen, the row and its dots hold still too, as the sponsors'
           belts do (assets/logo-belt.js): nobody sees them, and the dots' fill
           is worked out on the page's main thread every frame it runs —
           restyled and laid out again, for the whole time somebody reads the
           rest of the page. Both together, so they never part. */
        var away = false;
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                away = !entries[0].isIntersecting;
                root.toggleAttribute('data-fc-away', away);       // the ONLINE pulses, in site.css
                holdAnimations(root, !!hot || away);
            }, { rootMargin: '100px' }).observe(root);
        }

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
            holdLater();
        }
        /* …held or let go just AFTER this frame is drawn, when the page's
           style is worked out already. getAnimations() brings all style up to
           date before it answers, and asked here, straight after the card's
           class changed, that was a whole style pass of its own every time a
           card lit or went out — the more of the page on screen (zoomed out),
           the dearer. A frame later is a fraction of a pixel of belt. */
        var holdQueued = false;
        function holdLater() {
            if (holdQueued) return;
            holdQueued = true;
            afterFrame(function () { holdQueued = false; holdAnimations(root, !!hot || away); });
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
            holdAnimations(root, away);
        });
    });
}());
