<?php
/**
 * Reusable repeater. Used by Schedule, Tracks, Sponsors, Past Editions, FAQ admin pages.
 *
 * The repeater renders an outer wrap with a server-rendered row template the JS clones for "Add row".
 *
 *   fc_repeater([
 *     'name'    => 'sessions',        // POST key root: name="sessions[<idx>][title_el]"
 *     'rows'    => $rows_array,
 *     'fields'  => [ 'title' => ['type' => 'bilingual', 'label' => 'Title'], ... ],
 *     'add_label' => 'Add session',
 *   ]);
 *
 * Field type values:
 *   bilingual, bilingual_textarea, bilingual_ascii, text, textarea, number, url,
 *   media (WP media-library image picker), select (with options), multiselect,
 *   bool, colour, repeater (a repeater NESTED inside a row — see below)
 *
 * ── Nesting ─────────────────────────────────────────────────────────────────
 * A 'repeater' field puts a whole repeater inside each row, which is how the
 * Sponsors page holds its sponsors inside a tier. Two things make that work:
 *
 *   1. Each DEPTH gets its own row-index placeholder. The outer template says
 *      __INDEX__ and the inner one __INDEX1__, so cloning an outer row does not
 *      also stamp the inner template's own index. The repeater publishes which
 *      placeholder is its own in `data-placeholder`, and the Add-row script
 *      replaces only that one.
 *   2. Names, `data-name` and the nested `data-template` all carry the OUTER
 *      row's index, so all three have to be rewritten when a row is dragged or
 *      deleted. fcReindexRepeater() in inc/admin/menu.php does that by prefix.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_repeater(array $args): void {
    $defaults = [
        'name'      => 'rows',
        'rows'      => [],
        'fields'    => [],
        'add_label' => 'Add row',
        // Nesting depth. Only used to pick a placeholder that the enclosing
        // repeater's own clone-and-replace cannot collide with.
        'depth'     => 0,
    ];
    $args = array_merge($defaults, $args);
    $name   = (string) $args['name'];
    $rows   = is_array($args['rows']) ? array_values($args['rows']) : [];
    $fields = (array) $args['fields'];
    $depth  = (int) $args['depth'];
    $placeholder = fc_repeater_placeholder($depth);

    $template = fc_repeater_row_html($name, $placeholder, $fields, [], $depth);
    ?>
    <div class="fc-repeater-wrap<?php echo $depth > 0 ? ' is-nested' : ''; ?>">
        <div class="fc-repeater"
             data-name="<?php echo esc_attr($name); ?>"
             data-placeholder="<?php echo esc_attr($placeholder); ?>"
             data-template="<?php echo esc_attr($template); ?>">
            <?php foreach ($rows as $idx => $row) {
                echo fc_repeater_row_html($name, (string) $idx, $fields, is_array($row) ? $row : [], $depth);
            } ?>
        </div>
        <p class="fc-add-row">
            <button type="button" class="button fc-add-row-btn"><?php echo esc_html($args['add_label']); ?></button>
        </p>
    </div>
    <?php
}

/** The row-index token for a given nesting depth: __INDEX__, __INDEX1__, … */
function fc_repeater_placeholder(int $depth): string {
    return $depth <= 0 ? '__INDEX__' : '__INDEX' . $depth . '__';
}

function fc_repeater_row_html(string $name, string $idx, array $fields, array $values, int $depth = 0): string {
    ob_start();
    ?>
    <div class="fc-repeater-row">
        <span class="fc-row-handle">⋮⋮</span>
        <button type="button" class="fc-row-delete">[delete]</button>
        <?php foreach ($fields as $fkey => $fdef) :
            $type   = (string) ($fdef['type'] ?? 'text');
            $label  = (string) ($fdef['label'] ?? $fkey);
            $name_prefix = $name . '[' . $idx . ']';
            if ($type === 'hidden') {
                fc_repeater_field_input($name_prefix, $fkey, $type, $fdef, $values, $depth);
                continue;
            }
            // A nested repeater brings its own heading and add-button, so the
            // <label>/<div class="fc-field"> wrapper would only add a second,
            // emptier frame around it.
            if ($type === 'repeater') {
                ?>
                <div class="fc-field fc-field-repeater">
                    <label><?php echo esc_html($label); ?></label>
                    <?php if (!empty($fdef['help'])) : ?>
                        <p class="description"><?php echo wp_kses_post((string) $fdef['help']); ?></p>
                    <?php endif; ?>
                    <?php fc_repeater_field_input($name_prefix, $fkey, $type, $fdef, $values, $depth); ?>
                </div>
                <?php
                continue;
            }
            ?>
            <div class="fc-field">
                <label><?php echo esc_html($label); ?></label>
                <?php fc_repeater_field_input($name_prefix, $fkey, $type, $fdef, $values, $depth); ?>
                <?php // NOT for 'bool': that type renders `help` as the text
                      // beside its checkbox, so a description here would print
                      // the same sentence twice.
                      if (!empty($fdef['help']) && $type !== 'bool') : ?>
                    <p class="description"><?php echo wp_kses_post((string) $fdef['help']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function fc_repeater_field_input(string $name_prefix, string $fkey, string $type, array $fdef, array $values, int $depth = 0): void {
    switch ($type) {
        case 'repeater':
            // The rows live under this row's own key, so the POST payload nests
            // exactly the way the stored option does:
            //   fc_rows[0][sponsors][2][name]
            $sub = $values[$fkey] ?? [];
            fc_repeater([
                'name'      => $name_prefix . '[' . $fkey . ']',
                'rows'      => is_array($sub) ? $sub : [],
                'fields'    => (array) ($fdef['fields'] ?? []),
                'add_label' => (string) ($fdef['add_label'] ?? 'Add row'),
                'depth'     => $depth + 1,
            ]);
            break;
        case 'colour':
            // A picker AND a hex box, because neither is enough on its own: the
            // picker cannot be pasted into from a brand guide, the hex box cannot
            // be browsed. Paired by a DELEGATED handler in fc_admin_inline_js()
            // rather than fc_admin_colour_sync_script()'s one-shot binding, which
            // only sees the fields that existed at page load — a row added
            // afterwards would have two inputs that ignored each other.
            $val = trim((string) ($values[$fkey] ?? ''));
            $field_name = $name_prefix . '[' . $fkey . ']';
            $fallback = (string) ($fdef['default'] ?? '#0033FF');
            $swatch = preg_match('/^#[0-9a-fA-F]{6}$/', $val) ? $val : $fallback;
            ?>
            <span class="fc-colour">
                <input type="color" class="fc-colour-pick" value="<?php echo esc_attr($swatch); ?>">
                <input type="text" class="fc-colour-hex code"
                       name="<?php echo esc_attr($field_name); ?>"
                       value="<?php echo esc_attr($val); ?>"
                       placeholder="<?php echo esc_attr($fallback); ?>">
            </span>
            <?php
            break;
        case 'bilingual':
            // Manual render so the EN/EL inputs are scoped to this row's POST keys
            // (fc_bilingual_field() uses a flat name prefix that doesn't fit inside repeaters).
            $val_el = (string) ($values[$fkey . '_el'] ?? '');
            $val_en = (string) ($values[$fkey . '_en'] ?? '');
            $name_el = $name_prefix . '[' . $fkey . '_el]';
            $name_en = $name_prefix . '[' . $fkey . '_en]';
            ?>
            <div class="fc-bilingual">
                <div class="fc-tabs">
                    <button type="button" class="active" data-pane="en">EN</button>
                    <button type="button" data-pane="el">EL</button>
                </div>
                <div class="fc-pane active" data-pane="en">
                    <input type="text" name="<?php echo esc_attr($name_en); ?>" value="<?php echo esc_attr($val_en); ?>">
                </div>
                <div class="fc-pane" data-pane="el">
                    <input type="text" name="<?php echo esc_attr($name_el); ?>" value="<?php echo esc_attr($val_el); ?>">
                </div>
            </div>
            <?php
            break;
        case 'bilingual_textarea':
            $val_el = (string) ($values[$fkey . '_el'] ?? '');
            $val_en = (string) ($values[$fkey . '_en'] ?? '');
            $name_el = $name_prefix . '[' . $fkey . '_el]';
            $name_en = $name_prefix . '[' . $fkey . '_en]';
            $rows = (int) ($fdef['rows'] ?? 3);
            ?>
            <div class="fc-bilingual">
                <div class="fc-tabs">
                    <button type="button" class="active" data-pane="en">EN</button>
                    <button type="button" data-pane="el">EL</button>
                </div>
                <div class="fc-pane active" data-pane="en">
                    <textarea name="<?php echo esc_attr($name_en); ?>" rows="<?php echo $rows; ?>"><?php echo esc_textarea($val_en); ?></textarea>
                </div>
                <div class="fc-pane" data-pane="el">
                    <textarea name="<?php echo esc_attr($name_el); ?>" rows="<?php echo $rows; ?>"><?php echo esc_textarea($val_el); ?></textarea>
                </div>
            </div>
            <?php
            break;
        case 'bilingual_ascii':
            $val_el = (string) ($values[$fkey . '_el'] ?? '');
            $val_en = (string) ($values[$fkey . '_en'] ?? '');
            $name_el = $name_prefix . '[' . $fkey . '_el]';
            $name_en = $name_prefix . '[' . $fkey . '_en]';
            $rows = (int) ($fdef['rows'] ?? 6);
            ?>
            <div class="fc-bilingual">
                <div class="fc-tabs">
                    <button type="button" class="active" data-pane="en">EN</button>
                    <button type="button" data-pane="el">EL</button>
                </div>
                <div class="fc-pane active" data-pane="en">
                    <textarea name="<?php echo esc_attr($name_en); ?>" rows="<?php echo $rows; ?>" class="ascii"><?php echo esc_textarea($val_en); ?></textarea>
                </div>
                <div class="fc-pane" data-pane="el">
                    <textarea name="<?php echo esc_attr($name_el); ?>" rows="<?php echo $rows; ?>" class="ascii"><?php echo esc_textarea($val_el); ?></textarea>
                </div>
            </div>
            <?php
            break;
        case 'select':
            $opts = (array) ($fdef['options'] ?? []);
            $val  = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            echo '<select name="' . esc_attr($field_name) . '">';
            foreach ($opts as $ov => $ol) {
                printf('<option value="%s"%s>%s</option>', esc_attr((string) $ov), selected($val, (string) $ov, false), esc_html((string) $ol));
            }
            echo '</select>';
            break;
        case 'multiselect':
            $opts = (array) ($fdef['options'] ?? []);
            $val  = (array) ($values[$fkey] ?? []);
            $field_name = $name_prefix . '[' . $fkey . '][]';
            echo '<select multiple size="' . (int) ($fdef['size'] ?? 5) . '" name="' . esc_attr($field_name) . '">';
            foreach ($opts as $ov => $ol) {
                $sel = in_array((string) $ov, array_map('strval', $val), true) ? ' selected' : '';
                printf('<option value="%s"%s>%s</option>', esc_attr((string) $ov), $sel, esc_html((string) $ol));
            }
            echo '</select>';
            break;
        case 'bool':
            // A field's `default` applies only where the row has NO value for it
            // — a brand-new row, and a row saved before the field existed. Once
            // the box has been submitted the stored false is a real answer and
            // wins, so unticking something never springs back.
            $val = array_key_exists($fkey, $values)
                ? !empty($values[$fkey])
                : !empty($fdef['default']);
            $field_name = $name_prefix . '[' . $fkey . ']';
            printf(
                '<label><input type="checkbox" name="%s" value="1"%s> %s</label>',
                esc_attr($field_name), checked($val, true, false), esc_html((string) ($fdef['help'] ?? ''))
            );
            break;
        case 'number':
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            printf('<input type="number" step="any" name="%s" value="%s">', esc_attr($field_name), esc_attr($val));
            break;
        case 'decimal':
            // Like number but the sanitizer preserves the fractional part up to
            // `precision` digits (the 'number' case truncates to int). Use for
            // coordinates and any field where decimals must survive a round-trip.
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            $precision = isset($fdef['precision']) ? (int) $fdef['precision'] : 10;
            $step = $precision > 0 ? ('0.' . str_repeat('0', max(0, $precision - 1)) . '1') : '1';
            printf('<input type="number" step="%s" name="%s" value="%s">', esc_attr($step), esc_attr($field_name), esc_attr($val));
            break;
        case 'date':
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            printf('<input type="date" name="%s" value="%s">', esc_attr($field_name), esc_attr($val));
            break;
        case 'url':
            // type="text" (not type="url") so editors can enter on-page anchors
            // like #sponsors alongside full URLs — esc_url_raw on save accepts both.
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            printf('<input type="text" name="%s" value="%s" placeholder="https://… or #section">', esc_attr($field_name), esc_attr($val));
            break;
        case 'media':
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            // 'full' => true keeps the ORIGINAL upload instead of WordPress's
            // "medium" derivative, which the picker takes by default and which is
            // 300px on its longest side out of the box. Anything drawn larger
            // than 300px needs this or it is an upscale — see the note in
            // fc_admin_inline_js().
            $full = !empty($fdef['full']);
            ?>
            <div class="fc-media"<?php echo $full ? ' data-fc-media-full="1"' : ''; ?>>
                <input type="hidden" class="fc-media-input" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($val); ?>">
                <div class="fc-media-preview"><?php if ($val !== '') : ?><img src="<?php echo esc_url($val); ?>" alt=""><?php endif; ?></div>
                <button type="button" class="button fc-media-pick"><?php echo $val !== '' ? 'Replace image' : 'Select image'; ?></button>
                <button type="button" class="button fc-media-clear"<?php echo $val === '' ? ' style="display:none"' : ''; ?>>Remove</button>
            </div>
            <?php
            break;
        case 'textarea':
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            $rows = (int) ($fdef['rows'] ?? 3);
            printf('<textarea name="%s" rows="%d">%s</textarea>', esc_attr($field_name), $rows, esc_textarea($val));
            break;
        case 'hidden':
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            printf('<input type="hidden" name="%s" value="%s">', esc_attr($field_name), esc_attr($val));
            break;
        case 'text':
        default:
            $val = (string) ($values[$fkey] ?? '');
            $field_name = $name_prefix . '[' . $fkey . ']';
            printf('<input type="text" name="%s" value="%s">', esc_attr($field_name), esc_attr($val));
            break;
    }
}

/**
 * Sanitize a posted repeater payload using a fields schema.
 * Returns a plain array of rows.
 */
function fc_sanitize_repeater(array $raw_rows, array $fields): array {
    $clean_rows = [];
    foreach (array_values($raw_rows) as $row) {
        if (!is_array($row)) continue;
        $clean = [];
        foreach ($fields as $fkey => $fdef) {
            $type = (string) ($fdef['type'] ?? 'text');
            switch ($type) {
                case 'repeater':
                    // Recurse with the nested schema. Always an array, even when
                    // every child row was deleted — a tier with no sponsors is a
                    // tier the editor is still filling in, not a broken row.
                    $sub = $row[$fkey] ?? [];
                    $clean[$fkey] = is_array($sub)
                        ? fc_sanitize_repeater($sub, (array) ($fdef['fields'] ?? []))
                        : [];
                    break;
                case 'colour':
                    // sanitize_hex_color() returns null for anything that is not
                    // #RGB or #RRGGBB, which is exactly the guarantee the
                    // front-end wants: this colour is interpolated into CSS.
                    $raw_val = trim((string) ($row[$fkey] ?? ''));
                    $hex = $raw_val === '' ? '' : (string) (sanitize_hex_color($raw_val) ?? '');
                    $clean[$fkey] = $hex;
                    break;
                case 'bilingual':
                    $clean[$fkey . '_el'] = sanitize_text_field((string) ($row[$fkey . '_el'] ?? ''));
                    $clean[$fkey . '_en'] = sanitize_text_field((string) ($row[$fkey . '_en'] ?? ''));
                    break;
                case 'bilingual_textarea':
                    $clean[$fkey . '_el'] = sanitize_textarea_field((string) ($row[$fkey . '_el'] ?? ''));
                    $clean[$fkey . '_en'] = sanitize_textarea_field((string) ($row[$fkey . '_en'] ?? ''));
                    break;
                case 'bilingual_ascii':
                    $clean[$fkey . '_el'] = fc_sanitize_ascii((string) ($row[$fkey . '_el'] ?? ''));
                    $clean[$fkey . '_en'] = fc_sanitize_ascii((string) ($row[$fkey . '_en'] ?? ''));
                    break;
                case 'select':
                    $clean[$fkey] = sanitize_text_field((string) ($row[$fkey] ?? ''));
                    break;
                case 'multiselect':
                    $vals = (array) ($row[$fkey] ?? []);
                    $clean[$fkey] = array_values(array_map('sanitize_text_field', array_map('strval', $vals)));
                    break;
                case 'bool':
                    $clean[$fkey] = !empty($row[$fkey]);
                    break;
                case 'number':
                    $clean[$fkey] = is_numeric($row[$fkey] ?? '') ? (int) $row[$fkey] : 0;
                    break;
                case 'decimal':
                    // Preserve as a numeric STRING so floats with long fractional
                    // parts (e.g. 37.9838000000) survive without binary-float drift.
                    $raw_val = trim((string) ($row[$fkey] ?? ''));
                    $precision = isset($fdef['precision']) ? (int) $fdef['precision'] : 10;
                    if ($raw_val !== '' && preg_match('/^(-?\d+)(?:\.(\d+))?$/', $raw_val, $m)) {
                        $int_part = $m[1];
                        $frac     = isset($m[2]) ? substr($m[2], 0, max(0, $precision)) : '';
                        $clean[$fkey] = ($precision > 0 && $frac !== '') ? ($int_part . '.' . $frac) : $int_part;
                    } else {
                        $clean[$fkey] = '';
                    }
                    break;
                case 'date':
                    $raw_val = trim((string) ($row[$fkey] ?? ''));
                    // Accept only YYYY-MM-DD; anything else collapses to empty.
                    $clean[$fkey] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_val) ? $raw_val : '';
                    break;
                case 'url':
                case 'media':
                    $clean[$fkey] = esc_url_raw((string) ($row[$fkey] ?? ''));
                    break;
                case 'textarea':
                    $clean[$fkey] = sanitize_textarea_field((string) ($row[$fkey] ?? ''));
                    break;
                case 'hidden':
                case 'text':
                default:
                    $clean[$fkey] = sanitize_text_field((string) ($row[$fkey] ?? ''));
                    break;
            }
        }
        if (!empty($clean)) {
            $clean_rows[] = $clean;
        }
    }
    return $clean_rows;
}
