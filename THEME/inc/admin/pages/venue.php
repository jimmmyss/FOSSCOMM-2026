<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_venue', 20);
function fc_admin_register_venue() {
    add_submenu_page(FC_ADMIN_SLUG, 'Venue', '— Venue', FC_ADMIN_CAP, 'fc_section_venue', 'fc_admin_page_venue');
}

function fc_admin_page_venue() {
    $card_fields = [
        'title' => ['type' => 'bilingual', 'label' => 'Card title'],
        'body'  => ['type' => 'bilingual_textarea', 'label' => 'Card body', 'rows' => 3],
    ];
    $edition_fields = [
        'year'      => ['type' => 'number',    'label' => 'Year'],
        'city'      => ['type' => 'bilingual', 'label' => 'City'],
        'lat'       => ['type' => 'decimal', 'precision' => 10, 'label' => 'Latitude (decimal, up to 10 places — required for the globe pin)'],
        'lon'       => ['type' => 'decimal', 'precision' => 10, 'label' => 'Longitude (decimal, up to 10 places — required for the globe pin)'],
        'url'       => ['type' => 'url',     'label' => 'Archive URL (e.g. https://2024.fosscomm.gr)'],
        'spotlight' => ['type' => 'bool',    'label' => 'Spotlight (renders with the spotlight sprite + centres the globe)'],
    ];
    $info_fields = [
        'label' => ['type' => 'bilingual', 'label' => 'Row label (e.g. "Capacity")'],
        'value' => ['type' => 'bilingual', 'label' => 'Row value (e.g. "15,000+")'],
    ];
    fc_render_section_admin_page([
        'slug'       => 'fc_section_venue',
        'title'      => 'Venue',
        'option_key' => 'fc_section_venue',
        'schema'     => [
            'title'            => 'bilingual',
            'university_title' => 'bilingual',
            'hover_text'       => 'text',
            'google_maps_url'  => 'url',
            'address'          => 'bilingual_textarea',
            'cluster_label'    => 'text',
            'pin_sprite'       => 'url',
            'spotlight_sprite' => 'url',
            'getting_here'     => 'bilingual',
        ],
        'render_form' => function ($values) use ($card_fields, $edition_fields, $info_fields) {
            fc_bilingual_field('title', $values, ['label' => 'Section title']);
            ?>
            <h2 style="margin-top:2rem;">Venue card (left of the globe)</h2>
            <p class="description">The big title is the venue/university name. On hover it scrambles into your hover text below. Click the title to open the venue in Google Maps.</p>
            <?php
            fc_bilingual_field('university_title', $values, [
                'label' => 'Venue / University title (shown big, display font)',
                'placeholder_en' => 'Palexpo Center',
                'placeholder_el' => 'Παλεξπό Σέντερ',
            ]);
            // Hover text — what the big title scrambles into on hover. Free text
            // now (was a lat/lon pair); legacy coords_lat is read as a fallback.
            $hover_text = (string) ($values['hover_text'] ?? ($values['coords_lat'] ?? ''));
            ?>
            <div class="fc-field">
                <label>Hover text (what the title scrambles into on hover)</label>
                <input type="text" name="fc_field[hover_text]" value="<?php echo esc_attr($hover_text); ?>" placeholder="37.9838°N, 23.7275°E">
                <p class="description">Any text you like — coordinates, a tagline, anything. Leave blank to disable the hover effect.</p>
            </div>
            <div class="fc-field">
                <label>Google Maps URL (opened on click)</label>
                <input type="url" name="fc_field[google_maps_url]" value="<?php echo esc_attr((string) ($values['google_maps_url'] ?? '')); ?>" placeholder="https://maps.google.com/?q=...">
            </div>
            <?php
            fc_bilingual_field('address', $values, [
                'label' => 'Address (mono block — line breaks preserved)',
                'type'  => 'textarea',
                'rows'  => 4,
                'placeholder_en' => "Route François-Peyrot 30\n1218 Le Grand-Saconnex\nGeneva, Switzerland",
                'placeholder_el' => "Οδός François-Peyrot 30\n1218 Le Grand-Saconnex\nΓενεύη, Ελβετία",
            ]);
            ?>

            <h2 style="margin-top:2rem;">Info rows (label / value pairs under the address)</h2>
            <p class="description">Each row renders as a horizontal pair: mono uppercase label on the left, value on the right. Examples: "Capacity / 15,000+", "Transit / GVA Airport (5m)", "Access / Main Entrance via North Hall".</p>
            <?php
            fc_repeater([
                'name'      => 'fc_info',
                'rows'      => (array) ($values['info_rows'] ?? []),
                'fields'    => $info_fields,
                'add_label' => 'Add info row',
            ]);
            ?>

            <h2 style="margin-top:2rem;">Editions (globe pins + editions list)</h2>
            <p class="description">Each edition appears as a pin on the globe AND in the "Editions" list (opened from the globe's ED button on desktop, or the sticky editions bar on mobile). Selecting a year pans the globe to its coordinates. An edition ticked "Spotlight" renders with the spotlight sprite and centres the globe on it. Editions without explicit lat/lon are listed in the editions bar but don't get a pin on the globe.</p>
            <div class="fc-field">
                <label>Cluster label (fallback name for the grouped pin)</label>
                <input type="text" name="fc_field[cluster_label]" value="<?php echo esc_attr((string) ($values['cluster_label'] ?? 'FOSSCOMM')); ?>" placeholder="FOSSCOMM">
            </div>
            <?php
            $pin_sprite       = (string) ($values['pin_sprite'] ?? '');
            $spotlight_sprite = (string) ($values['spotlight_sprite'] ?? '');
            $pin_scale        = (float)  ($values['pin_scale'] ?? 1.0);       if ($pin_scale <= 0)       $pin_scale = 1.0;
            $spotlight_scale  = (float)  ($values['spotlight_scale'] ?? 1.0); if ($spotlight_scale <= 0) $spotlight_scale = 1.0;
            // Trim trailing zeros so 1.0 shows as "1", 1.50 as "1.5".
            $fmt_scale = function ($v) { return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); };
            ?>
            <div class="fc-field">
                <label>Pin sprite (pixel-art marker on the map)</label>
                <p class="description">Small PNG (≤ ~96px, transparent background). Used for every non-spotlight map pin — grouped/co-located editions collapse into one sprite with no date. Leave empty for the built-in default pin.</p>
                <div class="fc-media">
                    <input type="hidden" class="fc-media-input" name="fc_field[pin_sprite]" value="<?php echo esc_attr($pin_sprite); ?>">
                    <div class="fc-media-preview"><?php if ($pin_sprite !== '') : ?><img src="<?php echo esc_url($pin_sprite); ?>" alt=""><?php endif; ?></div>
                    <button type="button" class="button fc-media-pick"><?php echo $pin_sprite !== '' ? 'Replace image' : 'Select image'; ?></button>
                    <button type="button" class="button fc-media-clear"<?php echo $pin_sprite === '' ? ' style="display:none"' : ''; ?>>Remove</button>
                </div>
            </div>
            <div class="fc-field">
                <label>Pin size (× multiplier — 1.0 = normal)</label>
                <input type="number" step="0.05" min="0.1" name="fc_field[pin_scale]" value="<?php echo esc_attr($fmt_scale($pin_scale)); ?>" style="max-width:120px;">
                <p class="description">How big the regular pin sprite renders. 1.0 is the default; tweak up or down to taste.</p>
            </div>
            <div class="fc-field">
                <label>Spotlight pin sprite (pixel-art marker for spotlighted editions)</label>
                <p class="description">Small PNG (≤ ~96px, transparent background). Editions ticked "Spotlight" below render with THIS sprite instead of the regular pin. Leave empty for the built-in accent default.</p>
                <div class="fc-media">
                    <input type="hidden" class="fc-media-input" name="fc_field[spotlight_sprite]" value="<?php echo esc_attr($spotlight_sprite); ?>">
                    <div class="fc-media-preview"><?php if ($spotlight_sprite !== '') : ?><img src="<?php echo esc_url($spotlight_sprite); ?>" alt=""><?php endif; ?></div>
                    <button type="button" class="button fc-media-pick"><?php echo $spotlight_sprite !== '' ? 'Replace image' : 'Select image'; ?></button>
                    <button type="button" class="button fc-media-clear"<?php echo $spotlight_sprite === '' ? ' style="display:none"' : ''; ?>>Remove</button>
                </div>
            </div>
            <div class="fc-field">
                <label>Spotlight size (× multiplier — 1.0 = normal)</label>
                <input type="number" step="0.05" min="0.1" name="fc_field[spotlight_scale]" value="<?php echo esc_attr($fmt_scale($spotlight_scale)); ?>" style="max-width:120px;">
                <p class="description">How big the spotlight sprite renders. 1.0 is the default; tweak to make the featured edition stand out.</p>
            </div>
            <?php
            // City became bilingual (city_el / city_en). Pre-fill the English tab
            // from a legacy single `city` value so existing rows don't lose it on
            // the first re-save.
            $edition_rows = array_map(function ($row) {
                if (is_array($row) && ($row['city_en'] ?? '') === '' && ($row['city_el'] ?? '') === '' && ($row['city'] ?? '') !== '') {
                    $row['city_en'] = (string) $row['city'];
                }
                return $row;
            }, (array) ($values['editions'] ?? []));
            fc_repeater([
                'name'      => 'fc_editions',
                'rows'      => $edition_rows,
                'fields'    => $edition_fields,
                'add_label' => 'Add edition',
            ]);
            ?>

            <h2 style="margin-top:2rem;">Travel cards (How to get here)</h2>
            <?php
            fc_bilingual_field('getting_here', $values, [
                'label'          => 'Section label (the small heading shown beside the travel cards)',
                'placeholder_en' => 'Getting here',
                'placeholder_el' => 'Πώς θα έρθεις',
            ]);
            ?>
            <?php
            fc_repeater([
                'name'      => 'fc_travel',
                'rows'      => (array) ($values['travel_cards'] ?? []),
                'fields'    => $card_fields,
                'add_label' => 'Add travel card',
            ]);
        },
        'post_process' => function ($clean, $raw) use ($card_fields, $edition_fields, $info_fields) {
            $travel_rows = isset($raw['fc_travel']) && is_array($raw['fc_travel']) ? $raw['fc_travel'] : [];
            $clean['travel_cards'] = fc_sanitize_repeater($travel_rows, $card_fields);
            $edition_rows = isset($raw['fc_editions']) && is_array($raw['fc_editions']) ? $raw['fc_editions'] : [];
            $clean['editions'] = fc_sanitize_repeater($edition_rows, $edition_fields);
            $info_rows = isset($raw['fc_info']) && is_array($raw['fc_info']) ? $raw['fc_info'] : [];
            $clean['info_rows'] = fc_sanitize_repeater($info_rows, $info_fields);
            // Pin size multipliers (× scale; default 1.0). Not in the schema (no
            // float type there), so sanitised here. Clamped so a stray value can't
            // blow a sprite off the map or shrink it to nothing.
            foreach (['pin_scale', 'spotlight_scale'] as $sk) {
                $v = $raw['fc_field'][$sk] ?? '';
                $v = is_numeric($v) ? (float) $v : 1.0;
                $clean[$sk] = max(0.1, min(10.0, $v));
            }
            return $clean;
        },
    ]);
}
