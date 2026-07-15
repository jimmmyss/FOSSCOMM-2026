<?php
/**
 * /coc/ routing — Code of Conduct lives at its own URL rather than as a
 * landing-page section. Content is edited in FOSSCOMM → Code of Conduct
 * (option fc_section_conduct).
 *
 * Mirrors inc/news.php: a rewrite rule plus a template_redirect hook that
 * renders template-parts/conduct-page.php directly. No CPT, no DB tables.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'fc_conduct_register_rewrite');
function fc_conduct_register_rewrite() {
    add_rewrite_rule('^coc/?$', 'index.php?fc_conduct=1', 'top');
}

add_filter('query_vars', 'fc_conduct_query_vars');
function fc_conduct_query_vars($vars) {
    $vars[] = 'fc_conduct';
    return $vars;
}

// Theme activation: register the rewrite + flush.
add_action('after_switch_theme', 'fc_conduct_flush_on_activate');
function fc_conduct_flush_on_activate() {
    fc_conduct_register_rewrite();
    flush_rewrite_rules(false);
}

// One-shot flush so installs that received this update via file copy (not a
// fresh theme activation) still pick up the /coc/ route. Idempotent — the
// marker option is set after the first flush.
add_action('init', 'fc_conduct_initial_flush_once', 99);
function fc_conduct_initial_flush_once() {
    if (get_option('fc_conduct_rewrite_v1') === '1') return;
    flush_rewrite_rules(false);
    update_option('fc_conduct_rewrite_v1', '1', true);
}

// Render /coc/ when the query var is set.
add_action('template_redirect', 'fc_conduct_maybe_render');
function fc_conduct_maybe_render() {
    if (!get_query_var('fc_conduct')) return;
    status_header(200);
    get_header();
    echo '<main class="lg:pl-[200px]">';
    get_template_part('template-parts/conduct-page');
    echo '</main>';
    get_footer();
    exit;
}

/** Public permalink for the Code of Conduct page. */
function fc_conduct_permalink(): string {
    return home_url('/coc/');
}
