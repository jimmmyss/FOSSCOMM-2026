<?php
/**
 * Sticky 40px status bar. Single-language chrome with a language toggle on the
 * far left (shows the CURRENT language; clicking reloads the page in the other
 * one — the choice is remembered via cookie, see inc/i18n/lang.php).
 * The countdown ticker is hydrated by assets/src/status-bar.ts.
 */
if (!defined('ABSPATH')) {
    exit;
}

$status = get_option('fc_status_bar', []);
$brand_text     = (string) ($status['brand']       ?? 'FOSSCOMM/2026');
$location_el    = (string) ($status['location_el'] ?? 'Αθήνα');
$location_en    = (string) ($status['location_en'] ?? 'Athens');
?>
<script>
/* FOSSCOMM brand click → smooth scroll to top (matching the rest of the site's
   anchor-link feel). Only intercepts when the brand has data-fc-scroll-top
   (rendered on the landing page only). On news/coc pages the brand carries
   an absolute href back to home, so no JS interception is needed. */
document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest && e.target.closest('[data-fc-scroll-top]');
    if (!t) return;
    e.preventDefault();
    if ('scrollBehavior' in document.documentElement.style) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        window.scrollTo(0, 0);
    }
});
</script>
<header class="fixed top-0 inset-x-0 z-50 fc-bar border-b border-border bg-paper font-mono text-[11px] uppercase tracking-wider<?php echo fc_is_landing_page() ? ' fc-topbar-blue' : ''; ?>" <?php echo fc_island_attrs('status-bar', ['eventStart' => fc_get_event_start_iso()]); ?>>
    <!-- Left padding matches the section-nav (px-4 until lg, lg:pl-5) so the
         "FOSSCOMM…" brand starts at the exact same horizontal offset as the
         "00 / HOME" link in the nav strip below it on every breakpoint.
         `whitespace-nowrap overflow-x-auto fc-nav-no-scrollbar` mirrors the
         mobile section bar's behaviour: when the chrome (brand / location /
         countdown) is wider than the viewport, the bar scrolls horizontally
         instead of letting the spans shrink and stack their text vertically.
         `shrink-0` on each item locks their natural width so the layout
         doesn't break before the overflow kicks in. -->
    <div class="h-full px-4 lg:pl-5 lg:pr-8 flex items-center gap-4 text-ink-muted whitespace-nowrap overflow-x-auto fc-nav-no-scrollbar">
        <?php
        // Language toggle — far left, before the brand. Shows the CURRENT language
        // ("ENGLISH" / "ΕΛΛΗΝΙΚΑ"); clicking reloads this same page in the other
        // language (cookie-remembered). It's a plain link so it works without JS.
        $fc_other = fc_other_lang();
        ?>
        <a href="<?php echo fc_lang_switch_url($fc_other); ?>"
           class="fc-topbar-lang text-ink font-medium no-underline hover:text-accent focus:outline-none shrink-0"
           aria-label="<?php echo esc_attr(fc_t('lang_switch_label') . ': ' . fc_lang_endonym($fc_other)); ?>"
           rel="nofollow">
            <?php echo esc_html(fc_lang_endonym(fc_current_lang())); ?>
        </a>
        <span class="opacity-50 shrink-0">//</span>
        <?php
        // Landing page → "#top" anchor + smooth-scroll JS upgrade.
        // Other pages (/news/<slug>/, /coc/) → absolute URL back to home, no JS.
        $brand_is_landing = fc_is_landing_page();
        $brand_href       = $brand_is_landing ? '#top' : home_url('/');
        ?>
        <a href="<?php echo esc_url($brand_href); ?>"
           <?php if ($brand_is_landing) : ?>data-fc-scroll-top<?php endif; ?>
           class="fc-topbar-brand text-ink font-medium no-underline focus:outline-none shrink-0">
            <?php echo esc_html($brand_text); ?>
        </a>
        <span class="opacity-50 shrink-0">//</span>
        <span class="shrink-0"><?php echo fc_bi_inline($location_el, $location_en); ?></span>
        <span class="opacity-50 shrink-0">//</span>
        <span data-fc-countdown-clock class="shrink-0">…</span>
    </div>
</header>
