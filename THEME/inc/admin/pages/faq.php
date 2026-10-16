<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_faq', 20);
function fc_admin_register_faq() {
    add_submenu_page(FC_ADMIN_SLUG, 'FAQ', '— FAQ', FC_ADMIN_CAP, 'fc_section_faq', 'fc_admin_page_faq');
}

function fc_admin_page_faq() {
    $fields = [
        'question' => ['type' => 'bilingual', 'label' => 'Question'],
        'answer'   => ['type' => 'bilingual_textarea', 'label' => 'Answer', 'rows' => 4],
    ];
    fc_render_collection_admin_page([
        'slug'       => 'fc_section_faq',
        'title'      => 'FAQ',
        'option_key' => 'fc_faq',
        'intro'      => 'The FAQ section is the questions and nothing else — it has no heading of its own, '
                      . 'and each question fills the full width of the section.<br><br>'
                      . 'Wrap part of a <strong>question or an answer</strong> in <code>*asterisks*</code> to set it in '
                      . 'the accent pixel face, as a highlighted word is everywhere else on the site — it goes orange '
                      . 'on the blue when the question is hovered or open, like the <code>[+]</code>. '
                      . '<code>%percent signs%</code> make a small grey aside in the same way.<br><br>'
                      . 'Inside an answer, wrap text in <code>[text](url)</code> to render a hyperlink. Here it is '
                      . 'drawn like a button: black words with a blue <code>&gt;</code> after them, turning white '
                      . 'with an orange <code>&gt;</code> on the blue. Allowed URLs: <code>https://…</code>, '
                      . '<code>mailto:…</code>, <code>tel:…</code>, and on-page anchors like <code>#schedule</code>, '
                      . '<code>#venue</code>, <code>#volunteer</code>.',
        'fields'     => $fields,
        'add_label'  => 'Add Q&A',
        // The "Section heading" field that used to be here is gone with the
        // heading itself. Anything saved under fc_section_faq stays in the
        // database, unread, so putting the heading back is a revert of this.
        'render_before' => function ($rows) {
            echo '<h2 style="margin-top:0.5rem;">' . esc_html__('Questions & answers', 'fosscomm') . '</h2>';
        },
    ]);
}
