<?php
/**
 * Admin page: FOSSCOMM → 404 Page.
 * Bilingual heading + message shown by 404.php when a URL doesn't resolve.
 * Stored in option `fc_404` as ['title_el','title_en','message_el','message_en'].
 * Reader: 404.php (falls back to fc_t('not_found_message') when message is empty).
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_404', 26);
function fc_admin_register_404() {
    add_submenu_page(
        FC_ADMIN_SLUG,
        '404 Page',
        '— 404 Page',
        FC_ADMIN_CAP,
        'fc_section_404',
        'fc_admin_page_404'
    );
}

function fc_admin_page_404() {
    fc_render_section_admin_page([
        'slug'       => 'fc_section_404',
        'title'      => '404 Page',
        'option_key' => 'fc_404',
        'intro'      => 'Shown when a visitor hits a URL that doesn\'t exist. The optional heading appears above the message; the "← Back home" link is added automatically. You can use <code>*word*</code> to highlight a word in the theme accent colour, and <code>[text](https://…)</code> for a link.',
        'schema'     => [
            'title'   => 'bilingual',
            'message' => 'bilingual_textarea',
        ],
        'render_form' => function ($values) {
            fc_bilingual_field('title', $values, [
                'label'          => 'Heading (optional)',
                'placeholder_en' => '404',
                'placeholder_el' => '404',
            ]);
            fc_bilingual_field('message', $values, [
                'label'          => 'Message',
                'type'           => 'textarea',
                'rows'           => 3,
                'placeholder_en' => 'Page not found.',
                'placeholder_el' => 'Η σελίδα δεν βρέθηκε.',
            ]);
        },
    ]);
}
