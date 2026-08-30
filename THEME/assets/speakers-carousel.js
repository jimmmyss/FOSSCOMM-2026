/**
 * Speakers row: a continuously drifting, draggable, infinite belt — but only
 * when there is enough to drift.
 *
 * The decision is measured, never assumed. If the cards already fit the
 * viewport the row stays as the server rendered it: centred, still, no drag, no
 * duplicate cards, no rAF loop. A three-speaker conference gets three centred
 * cards, not a carousel limping round with a hole in it.
 *
 * When it does overflow, the card list is duplicated and the belt is translated.
 * Wrapping the offset by the width of ONE set makes the seam invisible: at any
 * moment the copy shows exactly what the original would have.
 *
 * Motion model — one position, one velocity:
 *   drift    a constant crawl, the resting state
 *   velocity inertia from letting go of a drag, decaying
 *   drag     sets the position directly while a pointer is down
 *
 * Pointing is decided GEOMETRICALLY, not from pointerover/pointerout — see
 * hitAt(). A speaker's card is mostly empty air and a cut-out photo is mostly
 * transparent, so the events fire in places where there is nothing to point at.
 */
(function () {
    'use strict';

    var DRIFT = 22;            // px/s, leftward. A crawl, not a slideshow.
    var DECAY = 0.0025;        // velocity remaining after 1s (exponential)
    var MIN_FLICK = 40;        // px/s below which a release adds no inertia
    var MASK_W = 64;           // alpha-mask sample columns (see buildMask)
    var MASK_MIN_ALPHA = 24;   // 0-255; below this a pixel counts as empty

    var reduced = window.matchMedia
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * A coarse opacity map of one photo, so "is the pointer on the person"
     * can be answered without asking the DOM.
     *
     * Sampled down to MASK_W columns: a cursor decision does not need per-pixel
     * fidelity, and a 64-wide grid is a few KB instead of megabytes. Same-origin
     * media, so the canvas is not tainted — but wrapped anyway, because a
     * tainted canvas throws on read and a broken cursor is not worth an
     * exception that takes the whole carousel down.
     */
    var maskCache = {};
    function buildMask(img) {
        var key = img.currentSrc || img.src;
        if (maskCache[key] !== undefined) return maskCache[key];

        // NOT cached when the image simply has not decoded yet.
        //
        // This used to write null into the cache BEFORE checking, so the very
        // first measuring pass — which runs before anything has loaded — poisoned
        // every entry permanently. Later passes found the cached null, and a null
        // mask means "unreadable, treat the whole rectangle as solid". So every
        // photo's transparent corners counted as the speaker, which is exactly
        // the highlight firing where there is plainly nothing to point at.
        // Only a real failure is worth remembering.
        if (!img.naturalWidth || !img.naturalHeight) return null;

        try {
            var w = MASK_W;
            var h = Math.max(1, Math.round(w * img.naturalHeight / img.naturalWidth));
            var canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            var ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(img, 0, 0, w, h);
            var data = ctx.getImageData(0, 0, w, h).data;
            var mask = new Uint8Array(w * h);
            for (var i = 0; i < w * h; i++) {
                mask[i] = data[i * 4 + 3] > MASK_MIN_ALPHA ? 1 : 0;
            }
            maskCache[key] = {
                w: w, h: h, bits: mask,
                // The TRUE aspect, not w/h — those are rounded to whole mask
                // cells. Needed to work out where inside its box the picture is
                // actually drawn.
                aspect: img.naturalWidth / img.naturalHeight,
            };
        } catch (err) {
            maskCache[key] = null;    // cross-origin: fall back to the whole box
        }
        return maskCache[key];
    }

    function init(wrap) {
        var viewport = wrap.querySelector('[data-fc-spk-viewport]');
        var rail = wrap.querySelector('[data-fc-spk-rail]');
        if (!viewport || !rail) return;

        var originals = Array.prototype.slice.call(rail.children);
        if (!originals.length) return;

        var looping = false;
        var setW = 0;          // width of ONE set of cards
        var offset = 0;
        var velocity = 0;
        var raf = 0;
        var lastTs = 0;
        var clones = [];
        var boxes = [];        // hit-test geometry, rail-relative
        var hotCard = null;
        var pointing = false;
        // Set when a drag travelled far enough that the click it produces should
        // be thrown away. Cleared by the next press so it can never outlive the
        // gesture that set it.
        var suppressClick = false;
        // Which speaker the press that is about to become a click started on.
        //
        // Decided at pointerdown and remembered, rather than re-asked at click
        // time. Releasing pointer capture makes the browser recompute the hit
        // target, which can fire pointerout/pointerleave on the viewport — and
        // that cleared the hover state a moment before the click arrived, so the
        // gate below saw "not pointing" and cancelled a perfectly good click on
        // a link. A click belongs to the press that started it.
        var pressedCard = null;

        // No hover available — a phone. Highlighting then follows the centre of
        // the row rather than a pointer that does not exist.
        //
        // Watched rather than read once: a tablet with a keyboard folio attached
        // and removed changes this mid-session, and the highlight should follow.
        var hoverless = window.matchMedia ? window.matchMedia('(hover: none)') : null;
        var touchMode = !!(hoverless && hoverless.matches);
        if (hoverless) {
            var onHoverChange = function (e) {
                touchMode = e.matches;
                setHot(null);
                updateHot();
            };
            if (hoverless.addEventListener) hoverless.addEventListener('change', onHoverChange);
            else if (hoverless.addListener) hoverless.addListener(onHoverChange);
        }

        var pointer = { x: 0, y: 0, inside: false };
        var drag = { id: null, startX: 0, startOffset: 0, lastX: 0, lastT: 0, moved: 0 };

        /* ── measuring ──────────────────────────────────────────────────── */

        function measure() {
            // Clones must be gone before measuring, or the second pass would
            // measure two sets and never stop looping.
            //
            // NB: nothing equalises card widths here any more. The photo is
            // boxed to a fixed 9:16 ratio in CSS, so every card is the same
            // width by arithmetic — correct before a single image has decoded,
            // instead of after a measuring pass that could only run once they had.
            removeClones();
            var total = 0;
            for (var i = 0; i < originals.length; i++) {
                total += originals[i].getBoundingClientRect().width;
            }
            setW = total;
            return total > viewport.clientWidth + 1;
        }

        function addClones() {
            // The belt wraps into [-setW, 0), so at the far end of that range the
            // originals have slid a full set left and the copies must cover the
            // viewport behind them: ceil(viewport / setW) EXTRA sets, no more.
            var needed = Math.max(1, Math.ceil(viewport.clientWidth / Math.max(setW, 1)));
            for (var pass = 0; pass < needed; pass++) {
                for (var i = 0; i < originals.length; i++) {
                    var copy = originals[i].cloneNode(true);
                    // Duplicates are decoration: the real list is already in the
                    // accessibility tree, and announcing every speaker twice is
                    // worse than announcing them once.
                    copy.setAttribute('aria-hidden', 'true');
                    copy.querySelectorAll('a').forEach(function (a) { a.tabIndex = -1; });
                    rail.appendChild(copy);
                    clones.push(copy);
                }
            }
        }

        function removeClones() {
            for (var i = 0; i < clones.length; i++) {
                if (clones[i].parentNode) clones[i].parentNode.removeChild(clones[i]);
            }
            clones = [];
            if (hotCard && !hotCard.isConnected) setHot(null);
        }

        /**
         * Cache what can be pointed at, in RAIL coordinates.
         *
         * Rail-relative rather than viewport-relative because the rail is what
         * moves: these numbers stay valid for the life of the layout, and a hit
         * test then costs one rect read for the rail plus arithmetic. Every card
         * is measured, clones included, so a hit resolves to the element
         * actually under the pointer rather than to its far-away twin.
         */
        function measureBoxes() {
            var railRect = rail.getBoundingClientRect();
            var rel = function (el) {
                if (!el) return null;
                var r = el.getBoundingClientRect();
                if (!r.width || !r.height) return null;
                return { x: r.left - railRect.left, y: r.top - railRect.top, w: r.width, h: r.height };
            };

            boxes = [];
            var items = rail.children;
            for (var i = 0; i < items.length; i++) {
                var card = items[i].querySelector('.fc-spk-card');
                if (!card) continue;
                var shot = items[i].querySelector('.fc-spk-shot');
                // Each LINE of type, not the blocks that hold them. A block
                // stretches the full column, so the empty strip beside a short
                // name counted as the name — pointing at nothing lit the speaker
                // up. The lines are `width: fit-content`, so their boxes are the
                // words themselves.
                var text = [];
                var lines = items[i].querySelectorAll('.fc-spk-name span, .fc-spk-roles li');
                for (var n = 0; n < lines.length; n++) {
                    var box = rel(lines[n]);
                    if (box) text.push(box);
                }
                var photo = rel(shot);
                var mask = shot ? buildMask(shot) : null;
                boxes.push({
                    card: card,
                    // The card's own rect, for the centre rule on touch — there
                    // is no pointer to ask there, so position decides instead.
                    rect: rel(card),
                    text: text,
                    photo: photo,
                    drawn: drawnRect(photo, mask),
                    mask: mask,
                });
            }
        }

        function inBox(b, x, y) {
            return b && x >= b.x && x <= b.x + b.w && y >= b.y && y <= b.y + b.h;
        }

        /**
         * Where inside its box the picture is ACTUALLY painted.
         *
         * The <img> is `object-fit: contain`, anchored bottom-left. Unless the
         * file happens to match the box's 3:4 exactly, the picture is smaller
         * than the box and the remainder is empty — above it for a wide crop,
         * to its right for a tall one.
         *
         * The hit test used the BOX for both the bounds check and for mapping a
         * point into the alpha mask, which silently assumed the picture filled
         * it. It does not, so the empty band was treated as part of the image
         * and mapped onto real pixels: hovering the gap above his head landed
         * somewhere on his face in the mask, and counted as pointing at him.
         */
        function drawnRect(box, mask) {
            if (!box || !mask || !mask.aspect) return box;
            var w = box.w;
            var h = box.h;
            if (mask.aspect > box.w / box.h) h = box.w / mask.aspect;   // wide: fits by width
            else w = box.h * mask.aspect;                               // tall: fits by height
            return { x: box.x, y: box.y + box.h - h, w: w, h: h };      // bottom-left
        }

        /** Is this point on a drawn pixel of that photo? */
        function onPixels(box, x, y) {
            if (!box.mask) return true;    // unreadable: treat the whole box as solid
            var m = box.mask;
            var d = box.drawn;             // the picture, not the box around it
            // Clamped, not rejected. A point exactly on the right or bottom edge
            // maps to index w or h — one past the end — and rejecting that made
            // the very bottom row of every photo unpointable, which is precisely
            // where a portrait standing on the floor line has most of its body.
            var col = Math.min(m.w - 1, Math.floor(((x - d.x) / d.w) * m.w));
            var row = Math.min(m.h - 1, Math.floor(((y - d.y) / d.h) * m.h));
            if (col < 0 || row < 0) return false;
            return m.bits[row * m.w + col] === 1;
        }

        /**
         * Which speaker, if any, is under this screen point.
         *
         * The text counts by its box; the photo counts only where it is not
         * transparent. That is the difference between "the pointer is over the
         * rectangle a cut-out happens to occupy" and "the pointer is on the
         * person".
         */
        function hitAt(clientX, clientY) {
            if (!boxes.length) return null;
            var railRect = rail.getBoundingClientRect();
            var x = clientX - railRect.left;
            var y = clientY - railRect.top;
            for (var i = 0; i < boxes.length; i++) {
                var b = boxes[i];
                for (var t = 0; t < b.text.length; t++) {
                    if (inBox(b.text[t], x, y)) return b.card;
                }
                // b.drawn, not b.photo: the empty band left by object-fit is not
                // part of the picture and must not even be a candidate.
                if (inBox(b.drawn, x, y) && onPixels(b, x, y)) return b.card;
            }
            return null;
        }

        /* ── state ──────────────────────────────────────────────────────── */

        function setHot(card) {
            if (hotCard === card) return;
            if (hotCard) hotCard.classList.remove('is-hot');
            hotCard = card;
            if (hotCard) hotCard.classList.add('is-hot');
            var want = !!hotCard;
            if (want !== pointing) {
                pointing = want;
                wrap.classList.toggle('is-pointing', pointing);
            }
        }

        /**
         * The speaker nearest the middle of the row.
         *
         * The touch answer to "which one is highlighted". There is no pointer to
         * ask on a phone, so position decides: whichever card's centre is closest
         * to the centre of the viewport, and only ever one. It follows the belt,
         * so the highlight travels along the row as it drifts and as you drag.
         */
        function nearestToCentre() {
            if (!boxes.length) return null;
            var railRect = rail.getBoundingClientRect();
            var view = viewport.getBoundingClientRect();
            var mid = view.left + view.width / 2;
            var best = null;
            var bestGap = Infinity;
            for (var i = 0; i < boxes.length; i++) {
                var r = boxes[i].rect;
                if (!r) continue;
                var gap = Math.abs((railRect.left + r.x + r.w / 2) - mid);
                if (gap < bestGap) { bestGap = gap; best = boxes[i].card; }
            }
            return best;
        }

        function updateHot() {
            if (touchMode) { setHot(nearestToCentre()); return; }
            if (!pointer.inside || drag.id !== null) { setHot(null); return; }
            setHot(hitAt(pointer.x, pointer.y));
        }

        function stop() {
            if (raf) { cancelAnimationFrame(raf); raf = 0; }
            lastTs = 0;
        }

        /** Re-decide everything. Safe to call repeatedly. */
        function layout() {
            var shouldLoop = measure();

            if (!shouldLoop) {
                looping = false;
                stop();
                wrap.classList.remove('is-looping');
                rail.style.transform = '';
                offset = 0;
                velocity = 0;
                measureBoxes();
                updateHot();
                return;
            }

            looping = true;
            wrap.classList.add('is-looping');
            addClones();
            offset = wrapOffset(offset);
            render();
            measureBoxes();
            if (!raf) raf = requestAnimationFrame(frame);
        }

        /* ── motion ─────────────────────────────────────────────────────── */

        /** Fold any offset into [-setW, 0) — the one interval that looks identical. */
        function wrapOffset(x) {
            if (setW <= 0) return 0;
            var m = x % setW;
            if (m > 0) m -= setW;
            return m;
        }

        function render() {
            rail.style.transform = 'translate3d(' + offset.toFixed(2) + 'px, 0, 0)';
        }

        function frame(ts) {
            raf = requestAnimationFrame(frame);
            var dt = lastTs ? Math.min(0.05, (ts - lastTs) / 1000) : 0.016;
            lastTs = ts;

            if (drag.id === null) {
                // Held still only while the POINTER is on a speaker.
                //
                // On touch there is always a highlighted card — the centred one —
                // so pausing on `hotCard` alone would freeze the row for good.
                // The highlight there is a consequence of the motion, not a
                // reason to stop it.
                const held = hotCard && !touchMode;
                if (!held && !reduced) offset -= DRIFT * dt;
                offset += velocity * dt;
                // Frame-rate independent decay: the same fraction is lost per
                // second whatever the display refresh rate.
                velocity *= Math.pow(DECAY, dt);
                if (Math.abs(velocity) < 1) velocity = 0;
                offset = wrapOffset(offset);
            }

            // BEFORE render(), never after. Both hitAt() and nearestToCentre()
            // read the rail's rect, and a read that follows a transform write in
            // the same frame forces a synchronous layout of the whole document —
            // the exact pattern that made this section crawl once already.
            //
            // On touch this runs every frame BECAUSE the belt is moving: which
            // card is centred changes as it goes.
            if (touchMode || pointer.inside) updateHot();

            render();
        }

        /* ── pointer ────────────────────────────────────────────────────── */

        function trackPointer(e) {
            pointer.x = e.clientX;
            pointer.y = e.clientY;
            pointer.inside = true;
        }

        function onPointerMove(e) {
            if (e.pointerType !== 'touch') {
                trackPointer(e);
                // While the belt is still, nothing else will re-test.
                if (!raf || !looping) updateHot();
            }

            if (drag.id !== e.pointerId) return;
            var dx = e.clientX - drag.startX;
            drag.moved = Math.max(drag.moved, Math.abs(dx));
            offset = wrapOffset(drag.startOffset + dx);

            // Velocity from the last movement only, so a release throws it the
            // way the hand was going at that moment rather than the average of
            // the whole gesture.
            var dt = (e.timeStamp - drag.lastT) / 1000;
            if (dt > 0.001) {
                velocity = (e.clientX - drag.lastX) / dt;
                drag.lastX = e.clientX;
                drag.lastT = e.timeStamp;
            }
            if (!raf) render();
            e.preventDefault();
        }

        function onPointerDown(e) {
            // Every new press starts clean, whatever the last one left behind.
            suppressClick = false;
            // Recorded BEFORE the drag clears the hover state below, and before
            // capture can move the hit target.
            pressedCard = hitAt(e.clientX, e.clientY);
            if (!looping || drag.id !== null) return;
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            drag.id = e.pointerId;
            drag.startX = drag.lastX = e.clientX;
            drag.startOffset = offset;
            drag.lastT = e.timeStamp;
            drag.moved = 0;
            velocity = 0;
            // Dragging is not pointing — but on touch the centred card stays
            // highlighted THROUGH the drag, and travels as you push the row.
            if (!touchMode) setHot(null);
            wrap.classList.add('is-dragging');
            viewport.setPointerCapture(e.pointerId);
        }

        function endDrag(e) {
            if (drag.id !== e.pointerId) return;
            drag.id = null;
            wrap.classList.remove('is-dragging');
            if (viewport.hasPointerCapture(e.pointerId)) {
                viewport.releasePointerCapture(e.pointerId);
            }
            if (Math.abs(velocity) < MIN_FLICK) velocity = 0;

            // A drag that TRAVELLED is not a click; a press that did not move is.
            // A flag rather than a temporary listener: the listener version was
            // only removed by a click actually arriving, so a drag that ended
            // outside the row — or on a card with no link — left it attached and
            // it ate the NEXT genuine click. Which is exactly "clicking a speaker
            // does not open their link".
            suppressClick = drag.moved > 6;
            trackPointer(e);
            updateHot();
        }

        // Belt and braces for the native image drag. `draggable="false"` on the
        // tag and `user-drag: none` in CSS cover Chromium and WebKit; Firefox
        // has historically started a drag anyway, and one that begins takes the
        // pointer with it, so the carousel simply stops responding mid-gesture.
        viewport.addEventListener('dragstart', function (e) { e.preventDefault(); });

        viewport.addEventListener('pointerdown', onPointerDown);
        viewport.addEventListener('pointermove', onPointerMove);
        viewport.addEventListener('pointerup', endDrag);
        viewport.addEventListener('pointercancel', endDrag);
        viewport.addEventListener('pointerleave', function () {
            pointer.inside = false;
            // "The pointer left" is not a reason to clear anything on touch —
            // there the highlight belongs to whichever card is centred, and a
            // still row has no frame loop to put it back.
            if (!touchMode) setHot(null);
        });

        // Clicks are handled ENTIRELY here: the native one is always cancelled,
        // and the navigation is performed explicitly when it is deserved.
        //
        // Letting the default through "when appropriate" was the obvious design
        // and it kept not working — the link would highlight in the status bar
        // and then nothing. Whether a default action survives a gesture that
        // involved pointer capture, a cancelled dragstart and a moving target is
        // not something worth reasoning about. Doing the navigation ourselves is
        // one line and has one outcome.
        //
        // Two reasons NOT to navigate, both about the PRESS rather than where
        // the pointer is now: the gesture travelled, so it was a drag; or it
        // began somewhere transparent, so it was a click on nothing.
        viewport.addEventListener('click', function (e) {
            var wasDrag = suppressClick;
            suppressClick = false;
            e.preventDefault();
            e.stopPropagation();

            if (wasDrag || !pressedCard) return;
            var href = pressedCard.getAttribute('href');
            if (!href) return;

            // Modified clicks and the middle button keep their usual meaning.
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) {
                window.open(href, '_blank', 'noopener');
            } else {
                window.location.href = href;
            }
        }, true);

        // Nothing to animate off-screen or on a hidden tab.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop();
            else if (looping && !raf) raf = requestAnimationFrame(frame);
        });

        var resizeRaf = 0;
        window.addEventListener('resize', function () {
            if (resizeRaf) return;
            resizeRaf = requestAnimationFrame(function () { resizeRaf = 0; layout(); });
        }, { passive: true });

        // Card widths and alpha masks both depend on decoded images, so the
        // first measurement has to wait for them. Load is the backstop for
        // anything cached late or lazy.
        layout();
        window.addEventListener('load', layout, { once: true });

        var imgs = rail.querySelectorAll('img');
        var left = imgs.length;
        var done = function () { if (--left <= 0) layout(); };
        imgs.forEach(function (img) {
            if (img.complete) { done(); return; }
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
        });
    }

    function boot() {
        document.querySelectorAll('[data-fc-speakers]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
