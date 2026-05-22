<?php
/**
 * Admin page: FOSSCOMM → Code of Conduct.
 * Bilingual title + body for the Code of Conduct section.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_conduct', 20);
function fc_admin_register_conduct() {
    add_submenu_page(FC_ADMIN_SLUG, 'Code of Conduct', '— Code of Conduct', FC_ADMIN_CAP, 'fc_section_conduct', 'fc_admin_page_conduct');
}

function fc_admin_page_conduct() {
    fc_render_section_admin_page([
        'slug'       => 'fc_section_conduct',
        'title'      => 'Code of Conduct',
        'option_key' => 'fc_section_conduct',
        'intro'      => 'Lives on its own page at <code>/coc/</code> — not as a section on the landing page. Both Greek and English columns render side-by-side. Body supports the same <code>[text](url)</code> inline-link syntax as the FAQ (https://…, mailto:, tel:, and on-page anchors like <code>#schedule</code>).',
        'schema'     => [
            'title' => 'bilingual',
            'body'  => 'bilingual_textarea',
        ],
        'render_form' => function ($values) {
            fc_bilingual_field('title', $values, [
                'label' => 'Section heading',
                'type'  => 'text',
            ]);
            fc_bilingual_field('body', $values, [
                'label' => 'Code of Conduct body',
                'type'  => 'textarea',
                'rows'  => 14,
                'help'  => 'One paragraph per blank line. Both EL and EN render on the page. Wrap any *word* in asterisks to highlight it in the theme accent colour.',
            ]);
        },
    ]);
}
