<?php
/**
 * Persisted state for section activation and order.
 * Stored in a single option `fc_sections_state` so the front page can read it in one query.
 */
if (!defined('ABSPATH')) {
    exit;
}

const FC_SECTIONS_STATE_OPTION = 'fc_sections_state';

/**
 * Returns the persisted state, falling back to registry defaults for any key not yet stored.
 * Shape: [ 'manifesto' => ['active' => true, 'order' => 20], ... ]
 */
function fc_sections_state(): array {
    $stored = get_option(FC_SECTIONS_STATE_OPTION, []);
    if (!is_array($stored)) {
        $stored = [];
    }
    $out = [];
    foreach (fc_section_registry() as $key => $def) {
        $entry = $stored[$key] ?? [];
        $out[$key] = [
            'active' => array_key_exists('active', $entry) ? (bool) $entry['active'] : (bool) ($def['default_active'] ?? true),
            'order'  => isset($entry['order']) ? (int) $entry['order'] : (int) ($def['default_order'] ?? 999),
        ];
    }
    return $out;
}

function fc_save_sections_state(array $state): void {
    $clean = [];
    foreach (fc_section_registry() as $key => $def) {
        if (!isset($state[$key])) continue;
        $clean[$key] = [
            'active' => !empty($state[$key]['active']),
            'order'  => isset($state[$key]['order']) ? (int) $state[$key]['order'] : (int) ($def['default_order'] ?? 999),
        ];
    }
    update_option(FC_SECTIONS_STATE_OPTION, $clean, false);
}

/**
 * Returns active sections in display order, each merged with its registry definition.
 * This is the canonical "what does the front page render" function.
 */
function fc_active_sections(): array {
    $state = fc_sections_state();
    $registry = fc_section_registry();
    $rows = [];
    foreach ($registry as $key => $def) {
        if (empty($state[$key]['active'])) continue;
        $rows[] = array_merge($def, [
            'order'  => (int) $state[$key]['order'],
            'active' => true,
        ]);
    }
    usort($rows, fn($a, $b) => $a['order'] <=> $b['order']);
    return $rows;
}

/**
 * Returns active sections that should appear in the left-rail nav.
 */
function fc_nav_sections(): array {
    return array_values(array_filter(fc_active_sections(), fn($s) => !empty($s['in_nav'])));
}

/**
 * Section "meta" = the heading copy (title_el / title_en) shown above a
 * section on the landing page. For collection-style sections (schedule,
 * news, speakers, sponsors, faq) the option that stores their ROWS
 * (fc_sessions, fc_news, …) deliberately has no room for a header pair, so
 * the heading lives in a parallel option `fc_section_<key>` written by the
 * section's admin page. Static sections (manifesto, venue, volunteer) keep
 * their title inside their own data option and don't need this — they pass
 * those values straight into fc_section_open().
 *
 * Returns ['title_el' => …, 'title_en' => …], with empty strings falling back
 * to the supplied defaults so a freshly-installed site still ships with the
 * original wording.
 */
function fc_section_meta(string $section_key, array $defaults = []): array {
    $data = get_option('fc_section_' . $section_key, []);
    if (!is_array($data)) $data = [];
    $el = (string) ($data['title_el'] ?? '');
    $en = (string) ($data['title_en'] ?? '');
    return [
        'title_el' => $el !== '' ? $el : (string) ($defaults['title_el'] ?? ''),
        'title_en' => $en !== '' ? $en : (string) ($defaults['title_en'] ?? ''),
    ];
}
