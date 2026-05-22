<?php
/**
 * Left-rail / mobile-scroller section navigation.
 * Built automatically from active sections marked `in_nav => true`.
 * Active-section highlight handled by assets/src/section-nav.ts (IntersectionObserver).
 */
if (!defined('ABSPATH')) {
    exit;
}

$nav_sections = fc_nav_sections();
if (empty($nav_sections)) return;
?>
<nav
    aria-label="<?php echo esc_attr(fc_t('sections_nav_label')); ?>"
    <?php echo fc_island_attrs('section-nav'); ?>
    data-fc-section-nav
    class="
        z-40 font-mono text-[11px] uppercase tracking-widest text-ink-muted
        bg-paper border-b border-border
        lg:fixed lg:top-10 lg:left-0 lg:w-[200px]
        lg:border-b-0 lg:border-r lg:border-border
        lg:bg-paper lg:py-8 lg:px-5 lg:pointer-events-none
        sticky top-0 h-10 px-4 flex items-center overflow-x-auto whitespace-nowrap
        fc-nav-no-scrollbar lg:h-auto lg:block
    "
>
    <ul class="flex lg:flex-col gap-x-5 gap-y-2 lg:pointer-events-auto">
        <?php foreach ($nav_sections as $section) :
            $label = fc_section_eyebrow($section);
            if ($label === '') $label = fc_section_label($section);
            // On the landing page: in-page hash link (#hero, #manifesto, …)
            // and assets/section-nav.js intercepts the click for smooth scroll.
            // On other pages (/news/<slug>/, /coc/): full URL back to the
            // landing page anchor — section-nav.js finds no #key locally and
            // lets the browser handle the navigation normally.
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
</nav>
<?php /* Sidebar's full-height paper-bg strip + right divider. Uses a hand-
   written CSS rule (not a Tailwind utility) so the bg + border are
   guaranteed to apply — Tailwind v4 browser-compile can occasionally miss
   classes that only appear once on the page, and an empty sidebar with a
   wave-canvas showing through is a visual regression. The element is
   `hidden` until lg+, where the rule takes over. */ ?>
<style>
.fc-sidebar-strip { display: none; }
@media (min-width: 1024px) {
    .fc-sidebar-strip {
        display: block;
        position: fixed;
        top: 40px;                 /* below the 40px status bar */
        left: 0;
        bottom: 0;
        width: 200px;
        background-color: var(--paper, #FAFAF7);
        border-right: 1px solid var(--color-border, color-mix(in oklab, #0A0A0A 12%, transparent));
        /* Below the nav (z-40) so the links paint on top, above plain
           in-flow content so the canvas is hidden behind us. */
        z-index: 30;
        pointer-events: none;
    }
}
</style>
<div aria-hidden="true" class="fc-sidebar-strip"></div>
