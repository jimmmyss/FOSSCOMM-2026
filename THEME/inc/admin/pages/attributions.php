<?php
/**
 * Admin page: FOSSCOMM → Attributions.
 *
 * A heading and intro, then a repeater of credits — each one a line of text and
 * an optional link. Lives at /attributions/ (inc/attributions.php).
 *
 * Two options, the same split sponsors.php uses: the list in fc_attributions
 * (written by the collection scaffolding) and the page's own heading in
 * fc_attributions_meta (written by post_process below). The scaffolding owns
 * exactly one option, so anything alongside the rows has to be saved here.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_attributions', 20);
function fc_admin_register_attributions() {
    add_submenu_page(
        FC_ADMIN_SLUG,
        'Attributions',
        '— Attributions',
        FC_ADMIN_CAP,
        'fc_section_attributions',
        'fc_admin_page_attributions'
    );
}

function fc_admin_page_attributions() {
    $fields = [
        'text' => ['type' => 'bilingual', 'label' => 'Credit'],
        'url'  => ['type' => 'url', 'label' => 'Link (optional)'],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_attributions',
        'title'      => 'Attributions',
        'option_key' => 'fc_attributions',
        'add_label'  => 'Add attribution',
        'fields'     => $fields,
        'intro'      => 'Lives on its own page at <code>' . esc_html(fc_attributions_permalink()) . '</code> — not as a '
            . 'section on the landing page. Credit anything borrowed from outside the project: speaker '
            . 'photographs, illustrations, fonts, icons, libraries. Rows render in the order below; drag the '
            . '<code>⋮⋮</code> handle to reorder.',
        'render_before' => function ($rows) {
            $meta     = fc_attributions_meta();
            $defaults = fc_attributions_defaults();
            ?>
            <h2 style="margin-top:0;">Page heading</h2>
            <p class="description">Shown at the top of the page. Leave either blank to use the built-in wording (the greyed-out text in each box). Both support <code>*highlight*</code> and the <code>[text](url)</code> inline-link syntax.</p>
            <?php
            fc_bilingual_field('title', $meta, [
                'label'          => 'Heading',
                'name_prefix'    => 'fc_attributions_meta',
                'placeholder_en' => $defaults['title_en'],
                'placeholder_el' => $defaults['title_el'],
            ]);
            fc_bilingual_field('intro', $meta, [
                'label'          => 'Intro paragraph',
                'type'           => 'textarea',
                'rows'           => 3,
                'name_prefix'    => 'fc_attributions_meta',
                'placeholder_en' => $defaults['intro_en'],
                'placeholder_el' => $defaults['intro_el'],
                'help'           => 'One paragraph per blank line.',
            ]);
            ?>
            <hr style="margin:2rem 0;">
            <h2>Attributions list</h2>
            <p class="description">
                <strong>Credit</strong> is the line as it should read — “Photograph of Jane Doe by A. Photographer”,
                “Icons from Feather, MIT licensed”. Filling in only English is fine: a credit with no Greek
                falls back to the English on the Greek site, so a name or a licence needs typing once.
                <strong>Link</strong> is optional; with one, the whole row becomes a link and the site it points
                at is shown underneath. A row with a link but no credit is dropped — a bare URL credits nobody.
            </p>
            <?php
        },
        'post_process' => function ($clean, $raw) {
            $meta_raw = isset($raw['fc_attributions_meta']) && is_array($raw['fc_attributions_meta'])
                ? $raw['fc_attributions_meta']
                : [];
            update_option('fc_attributions_meta', [
                'title_el' => sanitize_text_field((string) ($meta_raw['title_el'] ?? '')),
                'title_en' => sanitize_text_field((string) ($meta_raw['title_en'] ?? '')),
                'intro_el' => sanitize_textarea_field((string) ($meta_raw['intro_el'] ?? '')),
                'intro_en' => sanitize_textarea_field((string) ($meta_raw['intro_en'] ?? '')),
            ], false);
            return $clean;
        },
    ]);
}
