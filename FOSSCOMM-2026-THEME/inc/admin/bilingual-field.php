<?php
/**
 * Reusable bilingual (EL/EN) field renderer. Used by every admin page that edits user-facing copy.
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders an EL/EN tabbed pair of inputs.
 *
 * @param string $name_base  HTML name base. Outputs name="{base}_el" and name="{base}_en".
 * @param array  $values     Associative; keys "{base}_el", "{base}_en" (or just values if scalar).
 * @param array  $args       label, type=text|textarea|wysiwyg|ascii, placeholder_el/en, rows, help
 */
function fc_bilingual_field(string $name_base, $values, array $args = []): void {
    $defaults = [
        'label'          => '',
        'type'           => 'text',
        'placeholder_el' => '',
        'placeholder_en' => '',
        'rows'           => 4,
        'help'           => '',
        'name_prefix'    => 'fc_field',
    ];
    $args = array_merge($defaults, $args);
    $val_el = is_array($values) ? (string) ($values[$name_base . '_el'] ?? '') : '';
    $val_en = is_array($values) ? (string) ($values[$name_base . '_en'] ?? '') : '';

    $prefix = (string) $args['name_prefix'];
    if ($prefix !== '') {
        $input_name_el = $prefix . '[' . $name_base . '_el]';
        $input_name_en = $prefix . '[' . $name_base . '_en]';
    } else {
        $input_name_el = $name_base . '_el';
        $input_name_en = $name_base . '_en';
    }
    $id_el = $prefix !== '' ? $prefix . '_' . $name_base . '_el' : $name_base . '_el';
    $id_en = $prefix !== '' ? $prefix . '_' . $name_base . '_en' : $name_base . '_en';
    ?>
    <div class="fc-field fc-bilingual" data-base="<?php echo esc_attr($name_base); ?>">
        <?php if ($args['label']) : ?>
            <label><?php echo esc_html($args['label']); ?></label>
        <?php endif; ?>
        <div class="fc-tabs">
            <button type="button" class="active" data-pane="en">EN · English</button>
            <button type="button" data-pane="el">EL · Ελληνικά</button>
        </div>
        <div class="fc-pane active" data-pane="en">
            <?php fc_bilingual_field_input($args['type'], $id_en, $input_name_en, $val_en, $args['placeholder_en'], (int) $args['rows']); ?>
        </div>
        <div class="fc-pane" data-pane="el">
            <?php fc_bilingual_field_input($args['type'], $id_el, $input_name_el, $val_el, $args['placeholder_el'], (int) $args['rows']); ?>
        </div>
        <?php if ($args['help']) : ?>
            <p class="description"><?php echo esc_html($args['help']); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function fc_bilingual_field_input(string $type, string $id, string $name, string $value, string $placeholder, int $rows): void {
    switch ($type) {
        case 'textarea':
            printf(
                '<textarea id="%s" name="%s" rows="%d" placeholder="%s">%s</textarea>',
                esc_attr($id), esc_attr($name), max(2, $rows), esc_attr($placeholder), esc_textarea($value)
            );
            break;
        case 'ascii':
            printf(
                '<textarea id="%s" name="%s" rows="%d" class="ascii" placeholder="%s">%s</textarea>',
                esc_attr($id), esc_attr($name), max(4, $rows), esc_attr($placeholder), esc_textarea($value)
            );
            break;
        case 'wysiwyg':
            wp_editor($value, str_replace('-', '_', $id), [
                'textarea_name' => $name,
                'textarea_rows' => max(6, $rows),
                'media_buttons' => false,
                'teeny'         => true,
            ]);
            break;
        case 'text':
        default:
            printf(
                '<input type="text" id="%s" name="%s" value="%s" placeholder="%s">',
                esc_attr($id), esc_attr($name), esc_attr($value), esc_attr($placeholder)
            );
            break;
    }
}

/**
 * Sanitize an inbound POST array down to declared bilingual+scalar keys.
 *
 * @param array $raw     The $_POST sub-array.
 * @param array $schema  e.g. [ 'title' => 'bilingual', 'rich' => 'bilingual_html', 'asciiblock' => 'bilingual_ascii', 'order' => 'int', 'url' => 'url', 'flag' => 'bool', 'plain' => 'text' ]
 */
function fc_sanitize_fields(array $raw, array $schema): array {
    $out = [];
    foreach ($schema as $key => $kind) {
        switch ($kind) {
            case 'bilingual':
                $out[$key . '_el'] = sanitize_text_field((string) ($raw[$key . '_el'] ?? ''));
                $out[$key . '_en'] = sanitize_text_field((string) ($raw[$key . '_en'] ?? ''));
                break;
            case 'bilingual_textarea':
                $out[$key . '_el'] = wp_check_invalid_utf8((string) ($raw[$key . '_el'] ?? ''));
                $out[$key . '_en'] = wp_check_invalid_utf8((string) ($raw[$key . '_en'] ?? ''));
                $out[$key . '_el'] = sanitize_textarea_field((string) $out[$key . '_el']);
                $out[$key . '_en'] = sanitize_textarea_field((string) $out[$key . '_en']);
                break;
            case 'bilingual_html':
                $out[$key . '_el'] = wp_kses_post((string) ($raw[$key . '_el'] ?? ''));
                $out[$key . '_en'] = wp_kses_post((string) ($raw[$key . '_en'] ?? ''));
                break;
            case 'bilingual_ascii':
                $out[$key . '_el'] = fc_sanitize_ascii((string) ($raw[$key . '_el'] ?? ''));
                $out[$key . '_en'] = fc_sanitize_ascii((string) ($raw[$key . '_en'] ?? ''));
                break;
            case 'int':
                $out[$key] = (int) ($raw[$key] ?? 0);
                break;
            case 'bool':
                $out[$key] = !empty($raw[$key]);
                break;
            case 'url':
                $out[$key] = esc_url_raw((string) ($raw[$key] ?? ''));
                break;
            case 'email':
                $out[$key] = sanitize_email((string) ($raw[$key] ?? ''));
                break;
            case 'ascii':
                $out[$key] = fc_sanitize_ascii((string) ($raw[$key] ?? ''));
                break;
            case 'textarea':
                $out[$key] = sanitize_textarea_field((string) ($raw[$key] ?? ''));
                break;
            case 'text':
            default:
                $out[$key] = sanitize_text_field((string) ($raw[$key] ?? ''));
                break;
        }
    }
    return $out;
}
