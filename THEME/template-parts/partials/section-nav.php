<?php
/**
 * Left-rail / mobile-scroller section navigation.
 * Built automatically from active sections marked `in_nav => true`.
 * Active-section highlight handled by assets/section-nav.js (IntersectionObserver-free
 * scroll check).
 *
 * Two render modes:
 *   • sticky (landing page) — passed `['sticky' => true]` from front-page.php and
 *     placed inside the post-hero column (.fc-rest). On lg it's an absolute paper
 *     rail in the 200px gutter whose inner <nav> is position:sticky, so it LOCKS at
 *     the Manifesto section line and rides through to the footer (same mechanism as
 *     the venue editions panel — no scroll thresholds). On mobile the rail is
 *     display:contents, so the <nav> is a normal sticky horizontal bar.
 *   • fixed (news / coc pages) — the original fixed left rail + paper strip.
 */
if (!defined('ABSPATH')) {
    exit;
}

$nav_sections = fc_nav_sections();
if (empty($nav_sections)) return;

$sticky = !empty($args['sticky']);

// Shared link list (identical in both modes).
ob_start();
?>
<ul class="flex lg:flex-col gap-x-5 gap-y-2 lg:pointer-events-auto">
    <?php foreach ($nav_sections as $section) :
        $label = fc_section_eyebrow($section);
        if ($label === '') $label = fc_section_label($section);
        // Landing page: in-page hash link (#manifesto, …) intercepted by
        // assets/section-nav.js for smooth scroll. Other pages: full URL home.
        $href = fc_section_anchor_url($section['key']);
        ?>
        <li class="shrink-0">
            <a href="<?php echo esc_url($href); ?>"
               data-fc-nav-target="<?php echo esc_attr($section['key']); ?>"
               data-fc-nav-link
               class="fc-nav-link hover:text-accent">
                <?php echo esc_html($label); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php
$links_html = (string) ob_get_clean();
$aria = esc_attr(fc_t('sections_nav_label'));
?>
<?php if ($sticky) : ?>
    <div class="fc-nav-rail">
        <nav
            aria-label="<?php echo $aria; ?>"
            <?php echo fc_island_attrs('section-nav'); ?>
            data-fc-section-nav
            class="
                fc-nav-bar font-mono text-[11px] uppercase tracking-widest text-ink-muted
                bg-paper border-t border-b border-border -mb-px
                sticky top-0 fc-bar-mobile px-4 flex items-center overflow-x-auto whitespace-nowrap fc-nav-no-scrollbar
                lg:sticky lg:top-10 lg:h-auto lg:block lg:overflow-visible
                lg:border-t-0 lg:border-b-0 lg:mb-0 lg:px-5 lg:py-8
            "
        >
            <?php echo $links_html; ?>
        </nav>
    </div>
<?php else : ?>
    <nav
        aria-label="<?php echo $aria; ?>"
        <?php echo fc_island_attrs('section-nav'); ?>
        data-fc-section-nav
        class="
            z-40 font-mono text-[11px] uppercase tracking-widest text-ink-muted
            bg-paper border-b border-border
            lg:fixed lg:top-10 lg:left-0 lg:w-[200px]
            lg:border-b-0 lg:border-r lg:border-border
            lg:bg-paper lg:py-8 lg:px-5 lg:pointer-events-none
            sticky top-0 fc-bar-mobile px-4 flex items-center overflow-x-auto whitespace-nowrap
            fc-nav-no-scrollbar lg:h-auto lg:block
        "
    >
        <?php echo $links_html; ?>
    </nav>
    <div aria-hidden="true" class="fc-sidebar-strip"></div>
<?php endif; ?>
<style>
/* ── Non-landing fixed rail's full-height paper strip + right divider. ───────── */
.fc-sidebar-strip { display: none; }
@media (min-width: 1024px) {
    .fc-sidebar-strip {
        display: block;
        position: fixed;
        top: var(--fc-bar-h);      /* below the status bar */
        left: 0;
        bottom: 0;
        width: 200px;
        background-color: var(--paper, #FAFAF7);
        border-right: 1px solid var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
        z-index: 30;
        pointer-events: none;
    }
}

/* ── Landing sticky rail. ────────────────────────────────────────────────────
   Mobile: display:contents so the inner <nav> is a normal sticky bar inside
   .fc-rest (its containing block is the tall column, so top:0 sticking works).
   lg: an absolute paper rail filling the 200px gutter of .fc-rest, with the
   inner <nav> position:sticky (locks at the Manifesto line, releases at the
   footer).

   Stacking: the nav LINKS carry z-index 45 so they stay above the sections
   (mobile, where opaque section backgrounds scroll up under the pinned bar) and
   above the venue editions panel (z-40), but below the status bar's z-50. The
   desktop rail itself has NO z-index on purpose, so (a) it doesn't create a
   stacking context — the links keep their global z-45 — and (b) its paper
   background sits BEHIND the editions panel, which renders BELOW the links via
   --fc-sections-end. So the links and the editions both show, like the original. */
.fc-nav-rail { display: contents; }
.fc-nav-bar { z-index: 45; }
@media (min-width: 1024px) {
    .fc-nav-rail {
        display: block;
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 200px;
        pointer-events: none;
        background-color: var(--paper, #FAFAF7);
        border-right: 1px solid var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
    }
    .fc-nav-rail .fc-nav-bar {
        pointer-events: auto;
        /* Opaque, so the links hide the editions panel behind them; the editions
           still shows BELOW the links (over the rail's own paper background).
           Set here (not only via a utility class) so it can't be beaten by the
           Tailwind CDN's compile timing. */
        background-color: var(--paper, #FAFAF7);
    }
}
</style>
