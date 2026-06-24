<?php
/**
 * The one and only public page.
 * Renders the status bar, the auto-built section navigation, then every active section in saved order.
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main>
<?php
// The hero renders full-bleed. Everything from the first non-hero section
// (Manifesto) down lives in a relative column (.fc-rest) that the sticky
// section-nav rail is anchored to — so the sidebar locks at the Manifesto
// section line and rides through to the footer, the same position:sticky
// mechanism the venue editions panel uses (no scroll thresholds).
$fc_rest_open = false;
foreach (fc_active_sections() as $section) {
    if (!$fc_rest_open && ($section['key'] ?? '') !== 'hero') {
        echo '<div class="fc-rest relative lg:pl-[200px]">';
        get_template_part('template-parts/partials/section-nav', null, ['sticky' => true]);
        $fc_rest_open = true;
    }
    fc_render_section($section);
}
if ($fc_rest_open) {
    echo '</div>';
}
?>
</main>
<?php
get_footer();
