<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_volunteer', 20);
function fc_admin_register_volunteer() {
    add_submenu_page(FC_ADMIN_SLUG, 'Get Involved', '— Get Involved', FC_ADMIN_CAP, 'fc_section_volunteer', 'fc_admin_page_volunteer');
}

function fc_admin_page_volunteer() {
    // Every heading here uses the Sponsors tiers' two markers, so the help says so once.
    $markers = 'Wrap part of it in *single asterisks* to set it in the accent blue, or '
             . '**double asterisks** to draw it outlined — the same two markers as the '
             . 'sponsor tier titles.';

    fc_render_section_admin_page([
        'slug'       => 'fc_section_volunteer',
        'title'      => 'Get Involved',
        'option_key' => 'fc_section_volunteer',
        'intro'      => 'Three columns — a title, a small line under it, and a description each — side by side on a wide screen and one under the other on a phone. The first one, <strong>Participate</strong>, has a live countdown built into its title.',
        'schema'     => [
            'p_title1'  => 'bilingual',
            'p_counter' => 'bilingual',
            'p_title2'  => 'bilingual',
            'p_start'   => 'text',
            'p_stop'    => 'text',
            'p_closed'  => 'bilingual',
            'p_body'    => 'bilingual_textarea',
            'p_btn'       => 'bilingual',
            'p_btn_hover' => 'bilingual',
            'p_url'       => 'url',
            'c2_title'     => 'bilingual',
            'c2_label'     => 'bilingual',
            'c2_body'      => 'bilingual_textarea',
            'c2_btn'       => 'bilingual',
            'c2_btn_hover' => 'bilingual',
            'c2_url'       => 'url',
            'c3_title'     => 'bilingual',
            'c3_label'     => 'bilingual',
            'c3_body'      => 'bilingual_textarea',
            'c3_btn'       => 'bilingual',
            'c3_btn_hover' => 'bilingual',
            'c3_url'       => 'url',
        ],
        'render_form' => function ($values) use ($markers) {
            // Carries the old cards and deadline over until this page is saved
            // in the new shape — see fc_involved_values().
            $values = fc_involved_values(is_array($values) ? $values : []);

            $time = function (string $key, string $label, string $help) use ($values) {
                ?>
                <div class="fc-field">
                    <label><?php echo esc_html($label); ?></label>
                    <input type="datetime-local" name="fc_field[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) ($values[$key] ?? '')); ?>">
                    <p class="description"><?php echo esc_html($help); ?></p>
                </div>
                <?php
            };
            // The button under a column's description, in the site's button style.
            $button = function (string $base) use ($values) {
                fc_bilingual_field($base . '_btn', $values, [
                    'label' => 'Button label (optional — the “ >” is added automatically)',
                ]);
                fc_bilingual_field($base . '_btn_hover', $values, [
                    'label' => 'Button hover label (optional — scrambles in on hover, desktop only)',
                ]);
                ?>
                <div class="fc-field">
                    <label>Button link</label>
                    <input type="text" name="fc_field[<?php echo esc_attr($base); ?>_url]" value="<?php echo esc_attr((string) ($values[$base . '_url'] ?? '')); ?>" placeholder="#schedule or https://…">
                    <p class="description">Where the button goes. A full address (<code>https://…</code>) or an on-page anchor such as <code>#schedule</code>. With no label above, no button is shown.</p>
                </div>
                <?php
            };
            ?>
            <h2 style="margin-top:2rem;">1 · Participate — the countdown column</h2>
            <p class="description">The title is <strong>first text</strong> + <strong>counter text</strong> + <strong>last text</strong>, like the Sponsors funding line. The counter text is set in Qaroxe (the accent face — Latin letters and figures only) and drawn outlined, with a fill inside it that runs out, left to right, from the start time to the stop time. Under the title is the countdown to the stop time, on its own.</p>
            <?php
            fc_bilingual_field('p_title1',  $values, ['label' => 'Title — first text', 'help' => $markers]);
            fc_bilingual_field('p_counter', $values, ['label' => 'Title — counter text (the part with the progress inside it)']);
            fc_bilingual_field('p_title2',  $values, ['label' => 'Title — last text', 'help' => $markers]);
            $time('p_start', 'Counter starts', 'When submissions open. Before this date the word stays full and the countdown shows the whole window — 1 to 11 December reads “10D 00H 00M 00S”, not the wait until December.');
            $time('p_stop',  'Counter stops',  'When submissions close: the fill has run out and the countdown reads zero. Leave empty to hide both.');
            fc_bilingual_field('p_closed', $values, ['label' => 'Small line — once the stop has passed (before it, the line is the countdown alone)', 'placeholder_en' => 'Submissions closed', 'placeholder_el' => 'Οι υποβολές έκλεισαν']);
            fc_bilingual_field('p_body',   $values, ['label' => 'Description', 'type' => 'textarea', 'rows' => 4]);
            $button('p');

            foreach ([2, 3] as $n) :
                ?>
                <h2 style="margin-top:2.5rem;"><?php echo (int) $n; ?> · Column <?php echo (int) $n; ?></h2>
                <?php
                fc_bilingual_field('c' . $n . '_title', $values, ['label' => 'Title', 'help' => $markers]);
                fc_bilingual_field('c' . $n . '_label', $values, ['label' => 'Small line under the title (optional)']);
                fc_bilingual_field('c' . $n . '_body',  $values, ['label' => 'Description', 'type' => 'textarea', 'rows' => 4]);
                $button('c' . $n);
            endforeach;
        },
    ]);
}
