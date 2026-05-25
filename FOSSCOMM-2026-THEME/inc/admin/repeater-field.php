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
 *   media (WP media-library image picker), select (with options), multiselect, bool
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
    ];
    $args = array_merge($defaults, $args);
    $name   = (string) $args['name'];
    $rows   = is_array($args['rows']) ? array_values($args['rows']) : [];
    $fields = (array) $args['fields'];

    $template = fc_repeater_row_html($name, '__INDEX__', $fields, []);
    ?>
    <div class="fc-repeater-wrap">
        <div class="fc-repeater" data-name="<?php echo esc_attr($name); ?>" data-template="<?php echo esc_attr($template); ?>">
            <?php foreach ($rows as $idx => $row) {
                echo fc_repeater_row_html($name, (string) $idx, $fields, is_array($row) ? $row : []);
            } ?>
        </div>
        <p class="fc-add-row">
            <button type="button" class="button fc-add-row-btn"><?php echo esc_html($args['add_label']); ?></button>
        </p>
    </div>
    <?php
}

function fc_repeater_row_html(string $name, string $idx, array $fields, array $values): string {
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
                fc_repeater_field_input($name_prefix, $fkey, $type, $fdef, $values);
                continue;
            }
            ?>
            <div class="fc-field">
                <label><?php echo esc_html($label); ?></label>
                <?php fc_repeater_field_input($name_prefix, $fkey, $type, $fdef, $values); ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function fc_repeater_field_input(string $name_prefix, string $fkey, string $type, array $fdef, array $values): void {
    switch ($type) {
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
            $val = !empty($values[$fkey]);
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
            ?>
            <div class="fc-media">
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
