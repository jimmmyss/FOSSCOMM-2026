<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_footer', 20);
function fc_admin_register_footer() {
    add_submenu_page(FC_ADMIN_SLUG, 'Footer', '— Footer', FC_ADMIN_CAP, 'fc_section_footer', 'fc_admin_page_footer');
}

function fc_admin_page_footer() {
    // Each footer column: title + optional paragraph + a list of links.
    $link_fields = [
        'label'       => ['type' => 'bilingual', 'label' => 'Link title'],
        'hover_label' => ['type' => 'bilingual', 'label' => 'Hover label (optional — scrambles in on hover, desktop only)'],
        'url'         => ['type' => 'url', 'label' => 'URL'],
    ];

    fc_render_section_admin_page([
        'slug'       => 'fc_section_footer',
        'title'      => 'Footer',
        'option_key' => 'fc_section_footer',
        'intro'      => 'Three columns. Each has a title, an optional paragraph (hidden if left empty), and a list of links — add as many as you want per column.',
        'schema'     => [
            'col1_title' => 'bilingual',
            'col1_body'  => 'bilingual_textarea',
            'col2_title' => 'bilingual',
            'col2_body'  => 'bilingual_textarea',
            'col3_title' => 'bilingual',
            'col3_body'  => 'bilingual_textarea',
        ],
        'render_form' => function ($values) use ($link_fields) {
            foreach ([1, 2, 3] as $col) {
                echo '<h2 style="margin-top:1.5rem;">Column ' . esc_html((string) $col) . '</h2>';
                fc_bilingual_field("col{$col}_title", $values, ['label' => 'Column title (small caps)']);
                fc_bilingual_field("col{$col}_body",  $values, ['label' => 'Paragraph (optional — leave empty to hide)', 'type' => 'textarea', 'rows' => 4]);
                echo '<p class="description" style="margin:0.75rem 0 0.35rem;">Links</p>';
                fc_repeater([
                    'name'      => "fc_col{$col}_links",
                    'rows'      => (array) ($values["col{$col}_links"] ?? []),
                    'fields'    => $link_fields,
                    'add_label' => 'Add link',
                ]);
            }
        },
        'post_process' => function ($clean, $raw) use ($link_fields) {
            foreach ([1, 2, 3] as $col) {
                $rows = isset($raw["fc_col{$col}_links"]) && is_array($raw["fc_col{$col}_links"])
                    ? $raw["fc_col{$col}_links"] : [];
                $clean["col{$col}_links"] = fc_sanitize_repeater($rows, $link_fields);
            }
            return $clean;
        },
    ]);
}
