<?php
/**
 * Speakers admin page. Collection of speakers stored in option `fc_speakers`.
 *
 * Fields: name, cut-out photo, bilingual ROLES (one per line), optional link.
 *
 * Roles are a multi-line textarea rather than a nested repeater with add/remove
 * buttons. The repeater in inc/admin/repeater-field.php builds new rows from an
 * `__INDEX__` template, and nesting one inside another means rewriting indices
 * at two depths on every add, move and delete — a lot of new admin JS for a
 * field whose entire content is a short line of text. A line per role adds and
 * removes with Enter and Backspace, reorders by dragging text, and cannot get
 * its indices out of step.
 *
 * Affiliation and short bio are GONE from the card design. The migration below
 * folds an existing affiliation into the roles list rather than dropping it.
 *
 * Card colours live in their own option (fc_speakers_style) because they belong
 * to the section, not to any one speaker. The reader is fc_speakers_style() in
 * inc/helpers.php — front-end code cannot call anything defined in this file.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_speakers', 20);
function fc_admin_register_speakers() {
    add_submenu_page(FC_ADMIN_SLUG, 'Speakers', '— Speakers', FC_ADMIN_CAP, 'fc_section_speakers', 'fc_admin_page_speakers');
}

/**
 * One-time migration to the card layout: `role` → `roles`, affiliation folded in.
 *
 * The old schema had a single-line role plus a separate affiliation; the new one
 * has a list. Without this, the first save after the redesign would sanitise
 * both away — fc_sanitize_repeater() keeps only the fields in the schema — and
 * every speaker would silently lose their title.
 *
 * Affiliation becomes a second role line. It is a line of small type under the
 * name either way, which is exactly what the new list holds, so folding it in
 * preserves the content instead of discarding it on the user's behalf.
 *
 * Flag-guarded on admin_init, matching fc_maybe_drop_seeded_programme().
 */
add_action('admin_init', 'fc_maybe_migrate_speaker_roles');
function fc_maybe_migrate_speaker_roles(): void {
    if (get_option('fc_speakers_roles_migrated')) {
        return;
    }
    update_option('fc_speakers_roles_migrated', 1, true);

    $rows = get_option('fc_speakers', []);
    if (!is_array($rows) || !$rows) {
        return;
    }

    $changed = false;
    foreach ($rows as $i => $row) {
        if (!is_array($row)) continue;
        foreach (['el', 'en'] as $lang) {
            // Already migrated by hand, or nothing to carry over.
            if (isset($row['roles_' . $lang]) && trim((string) $row['roles_' . $lang]) !== '') {
                continue;
            }
            $lines = [];
            foreach (['role_' . $lang, 'affiliation_' . $lang] as $legacy) {
                $value = trim((string) ($row[$legacy] ?? ''));
                if ($value !== '') $lines[] = $value;
            }
            if (!$lines) continue;
            $rows[$i]['roles_' . $lang] = implode("\n", $lines);
            $changed = true;
        }
    }

    if ($changed) {
        update_option('fc_speakers', $rows, false);
    }
}

/** One colour box: a native picker and the hex beside it, kept in step. */
function fc_speakers_colour_field(string $name, string $value, string $label, string $help): void {
    $id = 'fc-col-' . $name;
    ?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <input type="color"
                   id="<?php echo esc_attr($id); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   data-fc-colour-for="<?php echo esc_attr($name); ?>"
                   style="vertical-align:middle;width:48px;height:32px;padding:2px;">
            <input type="text"
                   name="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   data-fc-colour-hex="<?php echo esc_attr($name); ?>"
                   class="regular-text code"
                   style="width:110px;vertical-align:middle;margin-left:8px;"
                   placeholder="#FFFFFF">
            <p class="description"><?php echo esc_html($help); ?></p>
        </td>
    </tr>
    <?php
}

function fc_admin_page_speakers() {
    $defaults = [
        'title_el' => 'Άνθρωποι που εμφανίστηκαν',
        'title_en' => 'People who showed up.',
    ];
    $fields = [
        'name'  => ['type' => 'text',  'label' => 'Name'],
        'photo' => [
            'type'  => 'media',
            // The ORIGINAL upload, not WordPress's "medium" derivative. Medium is
            // 300px on its longest side by default, and the portrait is drawn at
            // up to 420 CSS px — 840 device pixels on a retina screen. Taking the
            // derivative meant every speaker was upscaled about 2.8x, which is
            // what made the photos look soft no matter what the outline filter
            // did. Nothing in the filter chain blurs the picture.
            'full'  => true,
            'label' => 'Cut-out photo (PNG with a transparent background)',
        ],
        'roles' => [
            'type'  => 'bilingual_textarea',
            'label' => 'Roles / titles — ONE PER LINE',
            'rows'  => 3,
        ],
        'url'   => ['type' => 'url', 'label' => 'Link (optional — homepage, Mastodon, etc.)'],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_speakers',
        'title'      => 'Speakers',
        'option_key' => 'fc_speakers',
        'intro'      => 'One entry per speaker: the <strong>name</strong> large, the <strong>roles</strong> '
                      . 'stacked underneath, and the <strong>photo</strong> cut out and outlined, standing '
                      . 'on the section\'s bottom edge.<br><br>'
                      . 'The name is set <strong>one word per line</strong>, and the <strong>last line is '
                      . 'drawn hollow</strong> — "Gabe Newell" is GABE solid with NEWELL outlined under it. '
                      . 'A single-word name is therefore entirely outlined.<br><br>'
                      . '<strong>Photo spec — worth following exactly:</strong><br>'
                      . '&bull; <strong>840 &times; 840 px, square.</strong> Every photo is fitted into a '
                      . 'square frame of exactly the same size, so cards are always the same width and the '
                      . 'spacing stays even. A file that is not square is not cropped or squashed — it just '
                      . 'sits a little smaller in its frame, which will read as inconsistent next to ones '
                      . 'that fill theirs. 840 is not a round number for its own sake: the frame is 420px '
                      . 'and doubles on a retina screen, so anything smaller is being enlarged and will '
                      . 'look soft.<br>'
                      . '&bull; <strong>PNG with the background already removed.</strong> The outline is '
                      . 'drawn around whatever pixels are opaque, so a rectangular photo gets a rectangular '
                      . 'outline. Cut with a hard edge — a soft or feathered cut-out makes a soft outline.<br>'
                      . '&bull; <strong>Crop flush to the bottom.</strong> No transparent gap under the '
                      . 'shoulders, or that speaker floats above the line everyone else stands on.<br>'
                      . '&bull; <strong>Keep heads about the same size</strong> within the frame, or one '
                      . 'speaker reads as closer to the camera than the others.<br><br>'
                      . 'Put each role on its <strong>own line</strong> — press Enter for another, delete '
                      . 'the line to remove it. Drag rows to reorder the speakers.',
        'fields'     => $fields,
        'add_label'  => 'Add speaker',
        'render_before' => function ($rows) use ($defaults) {
            fc_section_meta_render('speakers', $defaults);
            $style = fc_speakers_style();
            ?>
            <h2 style="margin-top:2rem;">Card colours</h2>
            <p class="description">The outline drawn around every cut-out photo. Both apply to the whole
               section — they are not per speaker.</p>
            <table class="form-table" role="presentation">
                <?php
                fc_speakers_colour_field(
                    'fc_speakers_rim',
                    $style['rim'],
                    'Outline',
                    'The resting colour. Defaults to the theme accent — the portraits stand on the '
                    . 'section\'s own paper, so a white outline would be invisible.'
                );
                fc_speakers_colour_field(
                    'fc_speakers_rim_hover',
                    $style['rim_hover'],
                    'Outline on hover',
                    'Swapped in when the pointer is over that card. Desktop only — there is no hover on a phone.'
                );
                ?>
            </table>
            <script>
            /* Keep the picker and the hex box in step, both directions. */
            (function () {
                document.querySelectorAll('[data-fc-colour-for]').forEach(function (picker) {
                    var name = picker.getAttribute('data-fc-colour-for');
                    var hex = document.querySelector('[data-fc-colour-hex="' + name + '"]');
                    if (!hex) return;
                    picker.addEventListener('input', function () { hex.value = picker.value.toUpperCase(); });
                    hex.addEventListener('input', function () {
                        if (/^#[0-9a-fA-F]{6}$/.test(hex.value.trim())) picker.value = hex.value.trim();
                    });
                });
            }());
            </script>
            <h2 style="margin-top:2rem;"><?php echo esc_html__('Speakers', 'fosscomm'); ?></h2>
            <?php
        },
        'post_process' => function ($clean, $raw) {
            fc_section_meta_save('speakers', $raw);
            // sanitize_hex_color() rejects anything malformed; fc_speakers_style()
            // then falls back, so a bad paste cannot emit broken CSS.
            update_option(FC_SPEAKERS_STYLE_OPTION, [
                'rim'       => sanitize_hex_color((string) ($raw['fc_speakers_rim'] ?? '')),
                'rim_hover' => sanitize_hex_color((string) ($raw['fc_speakers_rim_hover'] ?? '')),
            ], false);
            return $clean;
        },
    ]);
}
