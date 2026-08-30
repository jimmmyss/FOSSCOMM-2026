<?php
/**
 * /attributions/ routing — credits for photography and anything else borrowed
 * from outside the project. Its own URL rather than a landing-page section,
 * because it is a reference page you link TO, not something anyone scrolls past.
 *
 * Content is edited in FOSSCOMM → Attributions: the list in option
 * fc_attributions, the heading and intro in fc_attributions_meta.
 *
 * Mirrors inc/conduct.php exactly — a rewrite rule plus a template_redirect hook
 * that renders template-parts/attributions-page.php directly. No CPT, no tables.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'fc_attributions_register_rewrite');
function fc_attributions_register_rewrite() {
    // Trailing slash optional, so /attributions and /attributions/ both land.
    add_rewrite_rule('^attributions/?$', 'index.php?fc_attributions=1', 'top');
}

add_filter('query_vars', 'fc_attributions_query_vars');
function fc_attributions_query_vars($vars) {
    $vars[] = 'fc_attributions';
    return $vars;
}

// Theme activation: register the rewrite + flush.
add_action('after_switch_theme', 'fc_attributions_flush_on_activate');
function fc_attributions_flush_on_activate() {
    fc_attributions_register_rewrite();
    flush_rewrite_rules(false);
}

/**
 * One-shot flush so installs that received this update by file copy — rather
 * than by a fresh theme activation — still pick up the route. Without it the
 * URL 404s until somebody visits Settings → Permalinks, which is not a step
 * anybody would know to take. Idempotent: the marker option is set after the
 * first flush, so this costs one option read per request thereafter.
 */
add_action('init', 'fc_attributions_initial_flush_once', 99);
function fc_attributions_initial_flush_once() {
    if (get_option('fc_attributions_rewrite_v1') === '1') return;
    flush_rewrite_rules(false);
    update_option('fc_attributions_rewrite_v1', '1', true);
}

/**
 * Name the page in the browser tab and in search results.
 *
 * Without this the route inherits whatever title the front page resolved to,
 * which for a page whose whole purpose is to be linked to is a real defect —
 * every link to it would read as the site name. The Code of Conduct page has
 * the same gap; it is worth fixing there too, and is left alone here only
 * because that is not what this change is.
 */
add_filter('document_title_parts', 'fc_attributions_document_title');
function fc_attributions_document_title($parts) {
    if (!get_query_var('fc_attributions')) return $parts;
    $meta  = fc_attributions_meta();
    $title = fc_one(fc_bi($meta, 'title'));
    if ($title === '') {
        $d = fc_attributions_defaults();
        $title = fc_pick($d['title_el'], $d['title_en']);
    }
    // The heading may carry *highlight* markers and [text](url) links, which are
    // presentation and have no business in a <title>.
    $parts['title'] = fc_strip_inline_links(str_replace(['*', '%'], '', $title));
    return $parts;
}

// Render /attributions/ when the query var is set.
add_action('template_redirect', 'fc_attributions_maybe_render');
function fc_attributions_maybe_render() {
    if (!get_query_var('fc_attributions')) return;
    status_header(200);
    get_header();
    echo '<main class="lg:pl-[200px]">';
    get_template_part('template-parts/attributions-page');
    echo '</main>';
    get_footer();
    exit;
}

/** Public permalink for the Attributions page. */
function fc_attributions_permalink(): string {
    return home_url('/attributions/');
}

/**
 * The saved credits, cleaned of rows with nothing to show.
 *
 * A row with a link but no text is dropped rather than rendered as a bare URL:
 * an attribution is a sentence crediting somebody, and a naked href credits
 * nobody. Shared by the template and the admin page's count.
 */
function fc_attributions_rows(): array {
    $rows = get_option('fc_attributions', []);
    if (!is_array($rows)) return [];
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (fc_one(fc_bi($row, 'text')) === '') continue;
        $out[] = $row;
    }
    return $out;
}

/** Heading and intro for the page. Bilingual, with built-in defaults. */
function fc_attributions_meta(): array {
    $meta = get_option('fc_attributions_meta', []);
    if (!is_array($meta)) $meta = [];
    return $meta;
}

/** The built-in heading and intro, used as admin placeholders and as fallbacks. */
function fc_attributions_defaults(): array {
    return [
        'title_el' => 'Τα εύσημα εκεί που ανήκουν.',
        'title_en' => 'Credit where it is due.',
        'intro_el' => 'Φωτογραφίες, εικονογραφήσεις και ό,τι άλλο δανειστήκαμε για αυτόν τον ιστότοπο, '
                    . 'μαζί με τους ανθρώπους που το έφτιαξαν.',
        'intro_en' => 'Photography, artwork and everything else borrowed for this site, '
                    . 'alongside the people who made it.',
    ];
}
