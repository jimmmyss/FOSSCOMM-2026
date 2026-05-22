<?php
/**
 * Speakers admin page. Collection of speakers stored in option `fc_speakers`.
 * Fields: name, photo, bilingual role, bilingual affiliation, bilingual short
 * bio, optional link. Role and affiliation are kept as SEPARATE fields so the
 * template can lay them out cleanly without a "·" separator.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_speakers', 20);
function fc_admin_register_speakers() {
    add_submenu_page(FC_ADMIN_SLUG, 'Speakers', '— Speakers', FC_ADMIN_CAP, 'fc_section_speakers', 'fc_admin_page_speakers');
}

function fc_admin_page_speakers() {
    $defaults = [
        'title_el' => 'Άνθρωποι που εμφανίστηκαν',
        'title_en' => 'People who showed up.',
    ];
    $fields = [
        'name'        => ['type' => 'text',                'label' => 'Name'],
        'photo'       => ['type' => 'media',               'label' => 'Photo (square crop works best)'],
        'role'        => ['type' => 'bilingual',           'label' => 'Role / title'],
        'affiliation' => ['type' => 'bilingual',           'label' => 'Affiliation (organisation, community, etc.)'],
        'bio'         => ['type' => 'bilingual_textarea',  'label' => 'Short bio (one or two sentences)', 'rows' => 3],
        'url'         => ['type' => 'url',                 'label' => 'Link (optional — homepage, Mastodon, etc.)'],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_speakers',
        'title'      => 'Speakers',
        'option_key' => 'fc_speakers',
        'intro'      => 'One entry per speaker. <strong>Role</strong> and <strong>affiliation</strong> are separate fields so the front-end can stack them without a "·" separator. Drag to reorder; reordering on the admin reflects the front-end order.',
        'fields'     => $fields,
        'add_label'  => 'Add speaker',
        'render_before' => function ($rows) use ($defaults) {
            fc_section_meta_render('speakers', $defaults);
            echo '<h2 style="margin-top:2rem;">' . esc_html__('Speakers', 'fosscomm') . '</h2>';
        },
        'post_process' => function ($clean, $raw) {
            fc_section_meta_save('speakers', $raw);
            return $clean;
        },
    ]);
}
