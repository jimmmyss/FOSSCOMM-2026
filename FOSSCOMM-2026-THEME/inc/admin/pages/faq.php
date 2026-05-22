<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_faq', 20);
function fc_admin_register_faq() {
    add_submenu_page(FC_ADMIN_SLUG, 'FAQ', '— FAQ', FC_ADMIN_CAP, 'fc_section_faq', 'fc_admin_page_faq');
}

function fc_admin_page_faq() {
    $defaults = [
        'title_el' => 'Λογικές ερωτήσεις, απλές απαντήσεις.',
        'title_en' => 'Reasonable questions, plain answers.',
    ];
    $fields = [
        'question' => ['type' => 'bilingual', 'label' => 'Question'],
        'answer'   => ['type' => 'bilingual_textarea', 'label' => 'Answer', 'rows' => 4],
    ];
    fc_render_collection_admin_page([
        'slug'       => 'fc_section_faq',
        'title'      => 'FAQ',
        'option_key' => 'fc_faq',
        'intro'      => 'Inside an answer, wrap text in <code>[text](url)</code> to render a hyperlink — same underline + accent hover treatment as the home CTAs. Allowed URLs: <code>https://…</code>, <code>mailto:…</code>, <code>tel:…</code>, and on-page anchors like <code>#schedule</code>, <code>#venue</code>, <code>#volunteer</code>.',
        'fields'     => $fields,
        'add_label'  => 'Add Q&A',
        'render_before' => function ($rows) use ($defaults) {
            fc_section_meta_render('faq', $defaults);
            echo '<h2 style="margin-top:2rem;">' . esc_html__('Questions & answers', 'fosscomm') . '</h2>';
        },
        'post_process' => function ($clean, $raw) {
            fc_section_meta_save('faq', $raw);
            return $clean;
        },
    ]);
}
