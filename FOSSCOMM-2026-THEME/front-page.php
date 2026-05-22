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
<main class="lg:pl-[200px]">
<?php
foreach (fc_active_sections() as $section) {
    fc_render_section($section);
}
?>
</main>
<?php
get_footer();
