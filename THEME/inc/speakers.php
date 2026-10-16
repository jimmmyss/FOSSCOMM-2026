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

/* ── The portraits' in-between size ──────────────────────────────────────────
 * WordPress makes 300, 768 and 1024px versions of every upload. A portrait on a
 * desktop screen at 1x is drawn about 375px wide, so the browser had nothing
 * between 300 (too soft) and 768: it took the 768 — four times the pixels it
 * drew, each decoded as the row brought it round. 480px wide (height to scale)
 * fills the gap, and srcset offers it on its own, as it does every size of the
 * same shape (fc_media_img_attrs()).
 *
 * New uploads get it from WordPress. A portrait already uploaded is given it
 * once, in the dashboard — never during a visitor's page view: on the first
 * admin page after this update, and whenever FOSSCOMM → Speakers is saved, for
 * any photo that lacks it. */
const FC_PORTRAIT_SIZE = 'fc-portrait-480';
add_action('after_setup_theme', 'fc_portrait_size_register');
function fc_portrait_size_register() {
    add_image_size(FC_PORTRAIT_SIZE, 480, 0, false);
}

/**
 * Make the 480px size for every speaker photo that lacks it. Returns false if
 * it stopped early (a long list on a slow server): the rest are done next time.
 */
function fc_portraits_backfill(): bool {
    if (!function_exists('image_make_intermediate_size') || !function_exists('wp_get_attachment_metadata')) return false;
    $rows = get_option('fc_speakers', []);
    if (!is_array($rows)) return true;
    $start = microtime(true);
    foreach ($rows as $row) {
        if (microtime(true) - $start > 15) return false;
        $url = is_array($row) ? trim((string) ($row['photo'] ?? '')) : '';
        if ($url === '' || fc_is_svg_url($url)) continue;
        $id = fc_attachment_id_from_url($url);
        if (!$id) continue;
        $meta = wp_get_attachment_metadata($id);
        if (!is_array($meta) || !empty($meta['sizes'][FC_PORTRAIT_SIZE])) continue;
        if ((int) ($meta['width'] ?? 0) <= 480) continue;          // already that small
        $file = get_attached_file($id);
        if (!$file || !is_file($file)) continue;
        $made = image_make_intermediate_size($file, 480, 0, false);
        if (!$made) continue;
        if (!isset($meta['sizes']) || !is_array($meta['sizes'])) $meta['sizes'] = [];
        $meta['sizes'][FC_PORTRAIT_SIZE] = $made;
        wp_update_attachment_metadata($id, $meta);
    }
    return true;
}
add_action('admin_init', 'fc_portraits_backfill_once');
function fc_portraits_backfill_once() {
    if (get_option('fc_portrait_480_v1') === '1') return;
    if (!current_user_can('upload_files')) return;
    if (fc_portraits_backfill()) update_option('fc_portrait_480_v1', '1', false);
}
add_action('update_option_fc_speakers', 'fc_portraits_backfill_on_save');
add_action('add_option_fc_speakers', 'fc_portraits_backfill_on_save');
function fc_portraits_backfill_on_save() {
    if (is_admin()) fc_portraits_backfill();
}
