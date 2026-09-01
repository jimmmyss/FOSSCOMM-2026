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
            echo '<p class="description">' . wp_kses_post(__('Three cards by default (e.g. "19 editions since 2008"). The number stays the same in both languages. Wrap any part in <strong>*asterisks*</strong> to draw it hollow in the colours below — so <strong>500*+*</strong> is a solid 500 with an outlined plus after it, and <strong>*500*+</strong> is the other way round. This used to happen automatically to every <code>+</code>; marking it means you choose.', 'fosscomm')) . '</p>';
            fc_repeater([
                'name'      => 'fc_stats',
                'rows'      => (array) ($values['stats'] ?? []),
                'fields'    => $stats_fields,
                'add_label' => __('Add stat', 'fosscomm'),
            ]);

            $style = fc_manifesto_style();
            ?>
            <h2 style="margin-top:2rem;"><?php echo esc_html__('Stat number colours', 'fosscomm'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Only the *starred* part of a number takes these — the rest stays the theme\'s ink, the same as every other heading. They apply to the whole stats row, not per stat.', 'fosscomm'); ?>
            </p>
            <table class="form-table" role="presentation">
                <?php
                fc_admin_colour_field(
                    'fc_manifesto_plus',
                    $style['plus'],
                    __('Outline', 'fosscomm'),
                    __('The resting colour, drawn as an outline with the middle left hollow — the same treatment as a speaker\'s last name. Defaults to the theme accent.', 'fosscomm')
                );
                fc_admin_colour_field(
                    'fc_manifesto_plus_hover',
                    $style['plus_hover'],
                    __('Outline on hover', 'fosscomm'),
                    __('Swapped in when the pointer is over that stat. Desktop only — there is no hover on a phone, where the resting colour simply stays.', 'fosscomm')
                );
                ?>
            </table>
            <?php
            fc_admin_colour_sync_script();
        },
        'post_process' => function ($clean, $raw) use ($stats_fields) {
            $rows = isset($raw['fc_stats']) && is_array($raw['fc_stats']) ? $raw['fc_stats'] : [];
            $clean['stats'] = fc_sanitize_repeater($rows, $stats_fields);

            // Its own option, not part of the section payload: the section data is
            // content, and these are presentation the template reads directly.
            // Mirrors how the Speakers page keeps its two outline colours.
            update_option(FC_MANIFESTO_STYLE_OPTION, [
                'plus'       => sanitize_hex_color((string) ($raw['fc_manifesto_plus'] ?? '')),
                'plus_hover' => sanitize_hex_color((string) ($raw['fc_manifesto_plus_hover'] ?? '')),
            ], false);

            return $clean;
        },
    ]);
}
