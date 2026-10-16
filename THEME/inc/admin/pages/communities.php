<?php
/**
 * FOSSCOMM → Communities.
 *
 * The communities in the section under Speakers and on /communities/, stored in
 * `fc_communities`. Each is one card: the name (a *starred* part is drawn
 * hollow, as on a speaker), a line or two of small type under it, the logo in
 * its square, and an optional link. Drag to reorder — the site shows them in
 * this order.
 *
 * The row shape is a speaker's where the two overlap (name, roles_el/_en,
 * photo, url) so fc_speaker_cards() builds both; see inc/communities.php.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_communities', 20);
function fc_admin_register_communities() {
    add_submenu_page(FC_ADMIN_SLUG, 'Communities', '— Communities', FC_ADMIN_CAP, 'fc_section_communities', 'fc_admin_page_communities');
}

function fc_admin_page_communities() {
    $defaults = [
        'title_el' => 'Κοινότητες που χτίζουν μαζί μας.',
        'title_en' => 'Communities that build with us.',
    ];
    $fields = [
        'name'  => ['type' => 'text', 'label' => 'Name', 'help' => 'Wrap part of it in <code>*asterisks*</code> to draw that part hollow — <code>open*SUSE*</code>.'],
        'roles' => ['type' => 'bilingual_textarea', 'label' => 'Small lines under the name — one per line', 'rows' => 2, 'help' => 'Two short lines work best, e.g. <em>Linux user group</em> / <em>Greece</em>.'],
        'photo' => ['type' => 'media', 'full' => true, 'label' => 'Logo', 'help' => 'SVG (best — sharp at every size, and the lightest) or PNG, with a transparent background, cropped tight to the logo. Every logo gets the same square, so its shape does not matter.'],
        'photo_alt' => ['type' => 'media', 'full' => true, 'label' => 'Logo inside the highlight (optional)', 'help' => 'Shown in place of the logo wherever the cursor\'s blue highlight passes over it — e.g. a white version that reads on the blue. Make it the same canvas as the logo (same size, same position of the mark), since it is laid exactly over it. Left empty, the logo stays as it is.'],
        'url'   => ['type' => 'url', 'label' => 'Link (optional)', 'help' => 'Where the card goes when clicked — the community\'s website.'],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_communities',
        'title'      => 'Communities',
        'option_key' => 'fc_communities',
        'intro'      => 'The communities shown under the Speakers and on <code>/communities/</code>. Click a line to open it, drag ⋮⋮ to reorder, and save at the bottom. With none added, the section shows its "to be announced" message.',
        'fields'     => $fields,
        'add_label'  => '+ Add community',
        'summary'    => ['name'],
        'item'       => 'community',
        'render_before' => function ($rows) use ($defaults) {
            fc_section_meta_render('communities', $defaults);
            ?>
            <h2 style="margin-top:2rem;">Heading on the communities page</h2>
            <p class="description">The heading at the top of <code>/communities/</code>, where every community is
               listed — the section heading above belongs to the section on the landing page, and links to that
               page. Leave this blank to use that same heading.</p>
            <?php
            $page_meta = get_option('fc_section_communities', []);
            if (!is_array($page_meta)) $page_meta = [];
            fc_bilingual_field('page_title', $page_meta, [
                'type'        => 'text',
                'name_prefix' => 'fc_section_meta',
            ]);
            echo '<h2 style="margin-top:2rem;">' . esc_html__('Communities', 'fosscomm') . '</h2>';
        },
        'post_process' => function ($clean, $raw) {
            // The page heading rides in the same meta option as the section
            // heading, so it has to be declared here or the save would drop it.
            fc_section_meta_save('communities', $raw, ['page_title' => 'bilingual']);
            // A row with no name is an empty form, not a community.
            return array_values(array_filter($clean, static function ($r) {
                return trim((string) ($r['name'] ?? '')) !== '';
            }));
        },
    ]);
}