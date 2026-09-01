<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_volunteer', 20);
function fc_admin_register_volunteer() {
    add_submenu_page(FC_ADMIN_SLUG, 'Get Involved', '— Get Involved', FC_ADMIN_CAP, 'fc_section_volunteer', 'fc_admin_page_volunteer');
}

function fc_admin_page_volunteer() {
    $card_fields = [
        'title'       => ['type' => 'bilingual', 'label' => 'Card title (EN + EL only — the “ / ” and “ →” are added automatically)'],
        'hover_title' => ['type' => 'bilingual', 'label' => 'Hover label (optional — scrambles in with the “hack” effect on hover, desktop only)'],
        'url'         => ['type' => 'url', 'label' => 'Link / redirect target'],
        'body'        => ['type' => 'bilingual_textarea', 'label' => 'Description', 'rows' => 3],
    ];
    fc_render_section_admin_page([
        'slug'       => 'fc_section_volunteer',
        'title'      => 'Get Involved',
        'option_key' => 'fc_section_volunteer',
        'intro'      => 'The <strong>Call for Participation</strong> block (title + body only) renders above the volunteer cards. Leave its fields empty to hide it.',
        'schema'     => [
            'title'        => 'bilingual',
            'intro'        => 'bilingual_textarea',
            // textarea, not text: the heading is set one line per line now, and
            // sanitize_text_field() (which 'bilingual' uses) strips newlines, so
            // every break typed into the box would be eaten on save.
            'cfp_title'    => 'bilingual_textarea',
            'cfp_body'     => 'bilingual_textarea',
            'cfp_deadline' => 'text',
            'fund_goal'    => 'int',
            'fund_raised'  => 'int',
        ],
        'render_form' => function ($values) use ($card_fields) {
            fc_bilingual_field('title', $values, ['label' => 'Section title']);
            fc_bilingual_field('intro', $values, ['label' => 'Intro paragraph (optional)', 'type' => 'textarea', 'rows' => 4]);

            // Colours before the content they apply to, the same place Speakers,
            // Manifesto and Venue put theirs.
            $cfp_style = fc_cfp_style();
            ?>
            <h2 style="margin-top:2rem;">CFP heading colours</h2>
            <p class="description">The <code>*starred*</code> part of the CFP heading below. The rest of it
               stays the theme's ink and is not affected — that is what makes the marked part read as the
               accent rather than as the odd one out in a heading that is already entirely coloured.
               Hovering the heading moves it, and on a phone it moves while the heading is near the middle
               of the screen. Kept separate from the other three so tuning one does not move the others.</p>
            <table class="form-table" role="presentation">
                <?php
                fc_admin_colour_field(
                    'fc_cfp_rim',
                    $cfp_style['rim'],
                    'Outline',
                    'The resting colour of the hollow part.'
                );
                fc_admin_colour_field(
                    'fc_cfp_rim_hover',
                    $cfp_style['rim_hover'],
                    'Outline on hover',
                    'Swapped in when the pointer is over the heading. Desktop only — there is no hover on a phone.'
                );
                ?>
            </table>
            <?php
            fc_admin_colour_sync_script();
            ?>
            <h2 style="margin-top:2.5rem;">Call for Participation block</h2>
            <p class="description">Renders at the top of the Get Involved section. Leave fields empty to hide.</p>
            <?php
            fc_bilingual_field('cfp_title', $values, [
                'label' => 'CFP heading',
                'type'  => 'textarea',
                'rows'  => 3,
                'help'  => 'ONE LINE PER LINE — press Enter to break it where you want. Wrap any part '
                         . 'in *asterisks* to draw it HOLLOW: outlined in the colours below, the same '
                         . 'treatment as a speaker\'s name and the venue\'s. So "Submit a talk. '
                         . '*Or a workshop.* Or both." outlines the middle sentence, and a run can '
                         . 'span a line break. It turns orange on hover, and on a phone it lights up '
                         . 'while the heading is near the middle of the screen, since there is no '
                         . 'pointer to hover with. NOTE: in this field *asterisks* mean hollow, not '
                         . 'the accent COLOUR they mean elsewhere — the two cannot both own the same '
                         . 'asterisks.',
            ]);
            fc_bilingual_field('cfp_body',  $values, ['label' => 'CFP body (paragraphs)', 'type' => 'textarea', 'rows' => 6]);
            ?>
            <div class="fc-field">
                <label>Submission deadline</label>
                <input type="datetime-local" name="fc_field[cfp_deadline]" value="<?php echo esc_attr((string) ($values['cfp_deadline'] ?? '')); ?>">
                <p class="description">Powers the live countdown on the right (talks, booths, etc.). Leave empty to hide it.</p>
            </div>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label>Funding goal (€)</label>
                    <input type="number" min="0" step="1" name="fc_field[fund_goal]" value="<?php echo esc_attr((string) ($values['fund_goal'] ?? '')); ?>">
                </div>
                <div class="fc-field">
                    <label>Raised so far (€)</label>
                    <input type="number" min="0" step="1" name="fc_field[fund_raised]" value="<?php echo esc_attr((string) ($values['fund_raised'] ?? '')); ?>">
                </div>
            </div>
            <p class="description" style="margin-top:-0.5rem;">Drives the progress bar. If "raised" exceeds the goal, the bar overflows and animates back and forth past the right edge. Set the goal to 0 to hide the bar.</p>

            <h2 style="margin-top:2.5rem;">Volunteer cards</h2>
            <?php
            fc_repeater([
                'name'      => 'fc_cards',
                'rows'      => (array) ($values['cards'] ?? []),
                'fields'    => $card_fields,
                'add_label' => 'Add card',
            ]);
        },
        'post_process' => function ($clean, $raw) use ($card_fields) {
            $rows = isset($raw['fc_cards']) && is_array($raw['fc_cards']) ? $raw['fc_cards'] : [];
            $clean['cards'] = fc_sanitize_repeater($rows, $card_fields);

            // Its own option, not part of the section payload: the section data is
            // content, these are presentation the template reads directly. Same
            // split the other three hollow headings use.
            update_option(FC_CFP_STYLE_OPTION, [
                'rim'       => sanitize_hex_color((string) ($raw['fc_cfp_rim'] ?? '')),
                'rim_hover' => sanitize_hex_color((string) ($raw['fc_cfp_rim_hover'] ?? '')),
            ], false);

            return $clean;
        },
    ]);
}
