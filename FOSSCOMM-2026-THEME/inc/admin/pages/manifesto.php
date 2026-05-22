<?php
/**
 * Admin page: FOSSCOMM → Manifesto.
 * Registers its own sub-menu and renders via the shared section-page scaffold.
 * Fields: bilingual title, bilingual body (multi-paragraph), repeating stats (3 cards).
 */
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'fc_admin_register_manifesto', 20);
function fc_admin_register_manifesto() {
    add_submenu_page(
        FC_ADMIN_SLUG,
        __('Manifesto', 'fosscomm'),
        '— ' . __('Manifesto', 'fosscomm'),
        FC_ADMIN_CAP,
        'fc_section_manifesto',
        'fc_admin_page_manifesto'
    );
}

function fc_admin_page_manifesto() {
    $stats_fields = [
        'number' => ['type' => 'text', 'label' => __('Number', 'fosscomm')],
        'label'  => ['type' => 'bilingual', 'label' => __('Label', 'fosscomm')],
    ];

    fc_render_section_admin_page([
        'slug'       => 'fc_section_manifesto',
        'title'      => __('Manifesto', 'fosscomm'),
        'option_key' => 'fc_section_manifesto',
        'intro'      => __('This section renders both Greek and English columns side-by-side on the page (it IS the bilingual manifesto). The page language toggle does not hide either column here.', 'fosscomm'),
        'schema' => [
            'title' => 'bilingual',
            'body'  => 'bilingual_textarea',
        ],
        'render_form' => function ($values) use ($stats_fields) {
            fc_bilingual_field('title', $values, [
                'label' => __('Section heading', 'fosscomm'),
                'type'  => 'text',
                'placeholder_el' => 'Μια συνάντηση κοινοτήτων…',
                'placeholder_en' => 'A meeting of communities…',
            ]);
            fc_bilingual_field('body', $values, [
                'label' => __('Manifesto body', 'fosscomm'),
                'type'  => 'textarea',
                'rows'  => 10,
                'help'  => __('One paragraph per blank line. Both EL and EN render on the page.', 'fosscomm'),
            ]);

            echo '<h2 style="margin-top:2rem;">' . esc_html__('Stats row (under the manifesto)', 'fosscomm') . '</h2>';
            echo '<p class="description">' . esc_html__('Three cards by default (e.g. "19 editions since 2008"). The number stays the same in both languages.', 'fosscomm') . '</p>';
            fc_repeater([
                'name'      => 'fc_stats',
                'rows'      => (array) ($values['stats'] ?? []),
                'fields'    => $stats_fields,
                'add_label' => __('Add stat', 'fosscomm'),
            ]);
        },
        'post_process' => function ($clean, $raw) use ($stats_fields) {
            $rows = isset($raw['fc_stats']) && is_array($raw['fc_stats']) ? $raw['fc_stats'] : [];
            $clean['stats'] = fc_sanitize_repeater($rows, $stats_fields);
            return $clean;
        },
    ]);
}
