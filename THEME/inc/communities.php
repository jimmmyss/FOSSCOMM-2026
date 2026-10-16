<?php
/**
 * Communities — the landing page's section under Speakers, and the full list at
 * /communities/.
 *
 * Mirrors inc/speakers.php: a rewrite rule plus a template_redirect hook that
 * renders template-parts/communities-page.php directly. No CPT, no DB tables.
 *
 * The communities are edited in FOSSCOMM → Communities.
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'fc_communities_register_rewrite');
function fc_communities_register_rewrite() {
    add_rewrite_rule('^communities/?$', 'index.php?fc_communities=1', 'top');
}

add_filter('query_vars', 'fc_communities_query_vars');
function fc_communities_query_vars($vars) {
    $vars[] = 'fc_communities';
    return $vars;
}

add_action('after_switch_theme', 'fc_communities_flush_on_activate');
function fc_communities_flush_on_activate() {
    fc_communities_register_rewrite();
    flush_rewrite_rules(false);
}

// One-shot flush for installs that receive this by file copy rather than a theme
// activation — the same arrangement as fc_speakers_initial_flush_once().
add_action('init', 'fc_communities_initial_flush_once', 99);
function fc_communities_initial_flush_once() {
    if (get_option('fc_communities_rewrite_v1') === '1') return;
    flush_rewrite_rules(false);
    update_option('fc_communities_rewrite_v1', '1', true);
}

add_action('template_redirect', 'fc_communities_maybe_render');
function fc_communities_maybe_render() {
    if (!get_query_var('fc_communities')) return;
    status_header(200);
    get_header();
    echo '<main class="lg:pl-[200px]">';
    get_template_part('template-parts/communities-page');
    echo '</main>';
    get_footer();
    exit;
}

/** Public permalink for the all-communities page. */
function fc_communities_permalink(): string {
    return home_url('/communities/');
}

/**
 * The communities, as saved in FOSSCOMM → Communities (`fc_communities`), in
 * the dashboard's order.
 *
 * Row shape — a speaker's, deliberately, so fc_speaker_cards() builds the cards:
 *   name            display name; a *starred* part is drawn hollow
 *   roles_el/_en    the small lines under the name, one per line
 *   photo           the logo
 *   url             where the card goes
 */
function fc_communities_list(): array {
    $stored = get_option('fc_communities', []);
    return is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
}
/**
 * The communities as speaker cards (fc_speaker_cards()), each with a slug for its
 * anchor on /communities/. Returns ['items' => [...], 'longest' => int].
 */
function fc_communities_items(array $rows): array {
    $built = fc_speaker_cards($rows);
    $items = [];
    foreach ($built['cards'] as $card) {
        $items[] = $card + ['slug' => sanitize_title($card['name'])];
    }
    return ['items' => $items, 'longest' => $built['longest']];
}
/**
 * The heading for /communities/, as an ['el' => …, 'en' => …] pair — the twin of
 * fc_speakers_page_title().
 *
 * Its own field (FOSSCOMM → Communities → "Heading on the communities page"),
 * stored beside the section's heading in `fc_section_communities`. Empty means
 * "say what the section says", so the page always has a heading.
 */
function fc_communities_page_title(): array {
    $data = get_option('fc_section_communities', []);
    if (!is_array($data)) $data = [];
    $meta = fc_section_meta('communities', [
        'title_el' => 'Κοινότητες που χτίζουν μαζί μας.',
        'title_en' => 'Communities that build with us.',
    ]);
    $el = trim((string) ($data['page_title_el'] ?? ''));
    $en = trim((string) ($data['page_title_en'] ?? ''));
    return [
        'el' => $el !== '' ? $el : (string) $meta['title_el'],
        'en' => $en !== '' ? $en : (string) $meta['title_en'],
    ];
}