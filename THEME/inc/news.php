<?php
/**
 * News article routing — opens each row in the `fc_news` option at its own URL
 * (e.g. /news/my-article-slug/) using a rewrite rule + a virtual template render.
 *
 * Slugs are derived from each row's English title (falling back to Greek), made
 * unique by appending -2, -3, … when two rows collide. Slugs are cached on the
 * fc_news option, so a saved row's URL is stable across renders.
 *
 * No custom post type, no DB tables — just an option, a rewrite rule and a
 * template-part. Falls back to a 404 cleanly when the slug is unknown.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build the slug map for the current `fc_news` rows.
 * Returns [ slug => row_index ].
 */
function fc_news_slug_map(): array {
    $rows = get_option('fc_news', []);
    if (!is_array($rows)) return [];
    $out  = [];
    $used = [];
    foreach (array_values($rows) as $i => $row) {
        if (!is_array($row)) continue;
        $title = (string) ($row['title_en'] ?? '');
        if ($title === '') $title = (string) ($row['title_el'] ?? '');
        $base  = sanitize_title($title);
        if ($base === '') $base = 'article-' . ($i + 1);
        $slug  = $base;
        $n     = 2;
        while (isset($used[$slug])) {
            $slug = $base . '-' . $n;
            $n++;
        }
        $used[$slug] = true;
        $out[$slug]  = $i;
    }
    return $out;
}

/** Look up a single news row by slug, or null if not found. */
function fc_news_find_by_slug(string $slug): ?array {
    $map  = fc_news_slug_map();
    if (!isset($map[$slug])) return null;
    $rows = get_option('fc_news', []);
    if (!is_array($rows)) return null;
    $rows = array_values($rows);
    return is_array($rows[$map[$slug]] ?? null) ? $rows[$map[$slug]] : null;
}

/** Permalink for one news row (for the section cards). */
function fc_news_permalink_for_row(array $row): string {
    $title = (string) ($row['title_en'] ?? '');
    if ($title === '') $title = (string) ($row['title_el'] ?? '');
    $base  = sanitize_title($title);
    if ($base === '') return home_url('/news/');
    // Disambiguate if multiple rows hash to the same base.
    $map = fc_news_slug_map();
    foreach ($map as $slug => $_idx) {
        if ($slug === $base || strpos($slug, $base . '-') === 0) {
            $candidate_row = fc_news_find_by_slug($slug);
            if ($candidate_row && $candidate_row === $row) {
                return home_url('/news/' . $slug . '/');
            }
        }
    }
    return home_url('/news/' . $base . '/');
}

add_action('init', 'fc_news_register_rewrite');
function fc_news_register_rewrite() {
    add_rewrite_tag('%fc_news_slug%', '([^&/]+)');
    add_rewrite_rule('^news/([^/]+)/?$', 'index.php?fc_news_slug=$matches[1]', 'top');
}

add_filter('query_vars', 'fc_news_query_vars');
function fc_news_query_vars($vars) {
    $vars[] = 'fc_news_slug';
    return $vars;
}

// Flush rewrite rules whenever the news option changes so newly added articles
// get reachable URLs without the editor having to visit Settings → Permalinks.
add_action('update_option_fc_news', 'fc_news_flush_rewrites_soft', 10, 0);
add_action('add_option_fc_news',    'fc_news_flush_rewrites_soft', 10, 0);
function fc_news_flush_rewrites_soft() {
    flush_rewrite_rules(false);
}

// Theme activation: register the rewrite + flush.
add_action('after_switch_theme', 'fc_news_flush_on_activate');
function fc_news_flush_on_activate() {
    fc_news_register_rewrite();
    flush_rewrite_rules(false);
}

// One-shot flush so installs that received this update via file copy (not a
// fresh theme activation) still pick up the /news/<slug>/ route. Idempotent —
// the marker option is set after the first flush and is never re-flushed by
// this hook again. Real edits still trigger fc_news_flush_rewrites_soft().
add_action('init', 'fc_news_initial_flush_once', 99);
function fc_news_initial_flush_once() {
    if (get_option('fc_news_rewrite_v1') === '1') return;
    flush_rewrite_rules(false);
    update_option('fc_news_rewrite_v1', '1', true);
}

// Render the virtual single-article page when /news/<slug>/ is hit.
add_action('template_redirect', 'fc_news_maybe_render_single');
function fc_news_maybe_render_single() {
    $slug = get_query_var('fc_news_slug');
    if (!$slug) return;
    $row = fc_news_find_by_slug((string) $slug);
    if (!$row) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }
    status_header(200);
    get_header();
    echo '<main class="lg:pl-[200px]">';
    get_template_part('template-parts/news-single', null, ['row' => $row, 'slug' => (string) $slug]);
    echo '</main>';
    get_footer();
    exit;
}
