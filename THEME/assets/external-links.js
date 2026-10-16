/**
 * Anything that leaves the site opens in a new tab.
 *
 * Every outward link on the page, wherever it came from — a CTA typed into the
 * dashboard, a sponsor's logo, a speaker's profile, a [text](url) written inside
 * an answer, the mascot's credit line in the attributions — gets target="_blank"
 * and a safe `rel`. Links to this site do not: following one of those in a new
 * tab leaves a dead copy of the page behind you.
 *
 * Done here rather than in PHP because the links are printed from a dozen
 * templates and half a dozen helpers, and a reader who is about to leave the
 * site does not care which of them printed the anchor. One rule, one place, and
 * nothing to remember the next time a field that takes a URL is added to the
 * dashboard.
 *
 * WHAT COUNTS AS LEAVING: a different host. Not a different path, not a
 * fragment, and not another scheme — mailto: and tel: hand off to another
 * application entirely and would leave an empty tab sitting there. A `download`
 * link keeps its own behaviour, and an anchor that already names a target is
 * left exactly as the template wrote it.
 *
 * TWO PASSES, because one is not enough:
 *   • a sweep after the document parses, which covers everything printed;
 *   • a capture-phase click, which covers everything added after that — the
 *     sponsor belt's clones, a card the speakers row duplicated. The browser
 *     reads `target` when it activates the link, which is after this listener
 *     has run, so marking it there still works.
 */
(function () {
    'use strict';

    /* Without the "www." — a site reached at both spellings is one site, and a
       link written with the other one is not somebody leaving. */
    function host(url) {
        return (url.hostname || '').replace(/^www\./i, '').toLowerCase();
    }

    var here = host(window.location);

    function outward(a) {
        if (!a || a.target || a.hasAttribute('download')) return false;
        // href="" and href="#thing" both resolve to this page.
        if (a.protocol !== 'http:' && a.protocol !== 'https:') return false;
        return host(a) !== here;
    }

    function mark(a) {
        if (!outward(a)) return;
        a.target = '_blank';
        /* noopener closes the new tab's handle on this one — without it the page
           that opens can navigate the tab it came from. noreferrer is the theme's
           existing habit on sponsor links; keeping both means every outward link
           behaves the same way. An author who wrote their own rel keeps it, with
           these added. */
        var rel = (a.getAttribute('rel') || '').split(/\s+/).filter(Boolean);
        ['noopener', 'noreferrer'].forEach(function (token) {
            if (rel.indexOf(token) === -1) rel.push(token);
        });
        a.setAttribute('rel', rel.join(' '));
    }

    function sweep() {
        var links = document.querySelectorAll('a[href]');
        for (var i = 0; i < links.length; i++) mark(links[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sweep);
    } else {
        sweep();
    }

    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (a) mark(a);
    }, true);
}());
