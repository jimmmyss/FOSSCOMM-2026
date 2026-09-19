<?php
/**
 * /speakers/ routing — the full list of speakers at its own URL. The landing
 * page's Speakers section is a moving sample of the same people; its heading
 * links here.
 *
 * Mirrors inc/conduct.php and inc/news.php: a rewrite rule plus a
 * template_redirect hook that renders template-parts/speakers-page.php
 * directly. No CPT, no DB tables — the speakers are the same option the section
 * reads (FOSSCOMM → Speakers).
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'fc_speakers_register_rewrite');
function fc_speakers_register_rewrite() {
    add_rewrite_rule('^speakers/?$', 'index.php?fc_speakers=1', 'top');
}

add_filter('query_vars', 'fc_speakers_query_vars');
function fc_speakers_query_vars($vars) {
    $vars[] = 'fc_speakers';
    return $vars;
}

// Theme activation: register the rewrite + flush.
add_action('after_switch_theme', 'fc_speakers_flush_on_activate');
function fc_speakers_flush_on_activate() {
    fc_speakers_register_rewrite();
    flush_rewrite_rules(false);
}

// One-shot flush so installs that received this update via file copy (not a
// fresh theme activation) still pick up the /speakers/ route. Idempotent — the
// marker option is set after the first flush.
add_action('init', 'fc_speakers_initial_flush_once', 99);
function fc_speakers_initial_flush_once() {
    if (get_option('fc_speakers_rewrite_v1') === '1') return;
    flush_rewrite_rules(false);
    update_option('fc_speakers_rewrite_v1', '1', true);
}

// Render /speakers/ when the query var is set.
add_action('template_redirect', 'fc_speakers_maybe_render');
function fc_speakers_maybe_render() {
    if (!get_query_var('fc_speakers')) return;
    status_header(200);
    get_header();
    echo '<main class="lg:pl-[200px]">';
    get_template_part('template-parts/speakers-page');
    echo '</main>';
    get_footer();
    exit;
}

/** Public permalink for the all-speakers page. */
function fc_speakers_permalink(): string {
    return home_url('/speakers/');
}
