/* FOSSCOMM 2026 — FAQ "scramble swap".
 *
 * Replaces the old accordion. Instead of opening a container, the question
 * title text glitches (window.fcScramble) into the answer IN PLACE — same
 * element, so it keeps the question's size/colour. Both the EN and EL lines
 * transform into their respective answers and the marker flips [+] → [−].
 *
 * Interaction: CLICK ONLY (no hover). Click reveals the answer; click again
 * reverts to the question.
 *
 * The template uses data-fc-island="faq-scramble" (not "faq-list"), so the
 * legacy accordion in assets/dist/fc.js finds nothing and stays out of the way.
 */
(function () {
    'use strict';

    function bindItem(item) {
        var btn    = item.querySelector('[data-fc-faq-toggle]');
        var marker = item.querySelector('[data-fc-faq-marker]');
        var lines  = item.querySelectorAll('[data-fc-faq-line]');
        if (!btn || !lines.length) return;

        var showing = false; // currently showing the answer

        function setMarker(open) {
            if (marker) marker.textContent = open ? '[−]' : '[+]';
        }

        function render(toAttr, open) {
            showing = open;
            setMarker(open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            lines.forEach(function (line) {
                var text = line.getAttribute(toAttr);
                if (text === null) return;
                if (text === '' && open) return; // no answer for this language
                // The answer may contain rich HTML ([text](url) → <a>); the
                // scramble animates plain text only, so we scramble first and
                // swap the HTML in once the animation settles. Closing
                // (toAttr === 'data-fc-q') always lands on plain text.
                var htmlAttr = (open ? line.getAttribute('data-fc-a-html') : null);
                var hasHtml  = (htmlAttr != null && htmlAttr !== '');
                if (typeof window.fcScramble === 'function') {
                    window.fcScramble(line, text, hasHtml ? {
                        onComplete: function () { line.innerHTML = htmlAttr; }
                    } : undefined);
                } else if (hasHtml) {
                    line.innerHTML = htmlAttr; // graceful fallback
                } else {
                    line.textContent = text;
                }
            });
        }

        function toggle(e) {
            // Ignore clicks that originate inside a link in the rendered
            // answer — those should navigate, not collapse the row.
            if (e.target && e.target.closest && e.target.closest('a')) {
                return;
            }
            e.preventDefault();
            if (showing) {
                render('data-fc-q', false);
            } else {
                render('data-fc-a', true);
            }
        }

        btn.addEventListener('click', toggle);
        // The toggle is a <div role="button">, not a real <button>, because the
        // rendered answer can contain <a> tags (invalid inside <button>). Bring
        // keyboard activation back manually: Enter and Space behave like clicks.
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                if (e.target && e.target.closest && e.target.closest('a')) {
                    return; // let the link handle its own keyboard activation
                }
                toggle(e);
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-fc-island="faq-scramble"]').forEach(function (list) {
            list.querySelectorAll('[data-fc-faq-item]').forEach(bindItem);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
