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
        'year'    => ['type' => 'number', 'label' => 'Year'],
        'city'    => ['type' => 'text',   'label' => 'City'],
        'lat'     => ['type' => 'number', 'label' => 'Latitude (decimal, optional — auto-detected from city name)'],
        'lon'     => ['type' => 'number', 'label' => 'Longitude (decimal, optional — auto-detected from city name)'],
        'url'     => ['type' => 'url',    'label' => 'Archive URL (e.g. https://2024.fosscomm.gr)'],
        'current' => ['type' => 'bool',   'label' => 'Current edition (pin renders in accent color)'],
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
            'city'             => 'text',
            'lat'              => 'text',
            'lon'              => 'text',
            'university_title' => 'bilingual',
            'coords_label'     => 'text',
            'google_maps_url'  => 'url',
            'address'          => 'bilingual_textarea',
            'cluster_label'    => 'text',
        ],
        'render_form' => function ($values) use ($card_fields, $edition_fields, $info_fields) {
            fc_bilingual_field('title', $values, ['label' => 'Section title']);
            ?>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label>City (block label, e.g. "ATHENS")</label>
                    <input type="text" name="fc_field[city]" value="<?php echo esc_attr((string) ($values['city'] ?? 'ATHENS')); ?>">
                </div>
                <div class="fc-field">
                    <label>Latitude (display only — globe meta)</label>
                    <input type="text" name="fc_field[lat]" value="<?php echo esc_attr((string) ($values['lat'] ?? '37.98°N')); ?>">
                </div>
                <div class="fc-field">
                    <label>Longitude (display only — globe meta)</label>
                    <input type="text" name="fc_field[lon]" value="<?php echo esc_attr((string) ($values['lon'] ?? '23.73°E')); ?>">
                </div>
            </div>

            <h2 style="margin-top:2rem;">Venue card (left of the globe)</h2>
            <p class="description">The big title is the venue/university name. On hover it scrambles into the coordinates label below. Click the title to open the venue in Google Maps.</p>
            <?php
            fc_bilingual_field('university_title', $values, [
                'label' => 'Venue / University title (shown big, display font)',
                'placeholder_en' => 'Palexpo Center',
                'placeholder_el' => 'Παλεξπό Σέντερ',
            ]);
            ?>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label>Coordinates label (shown on hover instead of the title)</label>
                    <input type="text" name="fc_field[coords_label]" value="<?php echo esc_attr((string) ($values['coords_label'] ?? '')); ?>" placeholder="37.98°N · 23.73°E">
                </div>
                <div class="fc-field">
                    <label>Google Maps URL (opened on click)</label>
                    <input type="url" name="fc_field[google_maps_url]" value="<?php echo esc_attr((string) ($values['google_maps_url'] ?? '')); ?>" placeholder="https://maps.google.com/?q=...">
                </div>
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
            <p class="description">Each edition appears as a pin on the globe AND in the "Editions" list (opened from the globe's ED button on desktop, or the sticky editions bar on mobile). Selecting a year pans the globe to its coordinates. The year marked "current" is always highlighted in accent color. If lat/lon are left empty, coordinates are auto-detected from the city name.</p>
            <div class="fc-field">
                <label>Cluster label (shown on the single pin when zoomed out)</label>
                <input type="text" name="fc_field[cluster_label]" value="<?php echo esc_attr((string) ($values['cluster_label'] ?? 'FOSSCOMM')); ?>" placeholder="FOSSCOMM">
            </div>
            <?php
            fc_repeater([
                'name'      => 'fc_editions',
                'rows'      => (array) ($values['editions'] ?? []),
                'fields'    => $edition_fields,
                'add_label' => 'Add edition',
            ]);
            ?>

            <h2 style="margin-top:2rem;">Travel cards (How to get here)</h2>
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
            return $clean;
        },
    ]);
}
