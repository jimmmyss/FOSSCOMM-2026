<?php
/**
 * Admin page: FOSSCOMM → News.
 * Bilingual news/article entries with a featured photo. Stored as a flat list of
 * rows in option `fc_news`. Empty state renders the bilingual TBA text managed
 * in FOSSCOMM → TBA Text.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_news', 20);
function fc_admin_register_news() {
    add_submenu_page(FC_ADMIN_SLUG, 'News', '— News', FC_ADMIN_CAP, 'fc_section_news', 'fc_admin_page_news');
}

function fc_admin_page_news() {
    $defaults = [
        'title_el' => 'Νέα και ανακοινώσεις.',
        'title_en' => 'News & announcements.',
    ];
    $fields = [
        'photo' => ['type' => 'media',              'label' => 'Featured photo'],
        'date'  => ['type' => 'date',               'label' => 'Publish date'],
        'title' => ['type' => 'bilingual',          'label' => 'Title (EN + EL)'],
        'body'  => ['type' => 'bilingual_textarea', 'label' => 'Description (EN + EL)', 'rows' => 5],
        'url'   => ['type' => 'url',                'label' => 'External source URL (optional — appears as a link on the article page)'],
    ];
    fc_render_collection_admin_page([
        'slug'       => 'fc_section_news',
        'title'      => 'News',
        'option_key' => 'fc_news',
        'intro'      => 'News / blog posts. Each entry needs a featured photo, bilingual title and bilingual description. Each saved article gets its own URL under <code>/news/&lt;slug&gt;/</code> (slug derived from the English title). Wrap any <code>*word*</code> in asterisks to render it in the theme accent colour.',
        'fields'     => $fields,
        'add_label'  => 'Add news entry',
        'render_before' => function ($rows) use ($defaults) {
            fc_section_meta_render('news', $defaults);
            echo '<h2 style="margin-top:2rem;">' . esc_html__('Articles', 'fosscomm') . '</h2>';
        },
        'post_process' => function ($clean, $raw) {
            fc_section_meta_save('news', $raw);
            return $clean;
        },
    ]);
}
