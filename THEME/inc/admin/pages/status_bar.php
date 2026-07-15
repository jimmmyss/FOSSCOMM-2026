<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_status_bar', 10);
function fc_admin_register_status_bar() {
    add_submenu_page(FC_ADMIN_SLUG, 'Top Bar', '— Top Bar', FC_ADMIN_CAP, 'fc_status_bar', 'fc_admin_page_status_bar');
}

function fc_admin_page_status_bar() {
    fc_render_section_admin_page([
        'slug'       => 'fc_status_bar',
        'title'      => 'Top Bar',
        'option_key' => 'fc_status_bar',
        'intro'      => 'Editable chrome that appears in the sticky bar at the top of every page.',
        'schema'     => [
            'brand'       => 'text',
            'location_en' => 'text',
            'location_el' => 'text',
            'event_start' => 'text',
        ],
        'post_process' => function ($clean, $post) {
            $event_start = trim((string) ($clean['event_start'] ?? ''));
            if ($event_start !== '') {
                $settings = get_option('fc_site_settings', []);
                if (!is_array($settings)) $settings = [];
                $settings['event_start'] = $event_start;
                update_option('fc_site_settings', $settings, false);
            }
            return $clean;
        },
        'render_form' => function ($values) {
            $settings = get_option('fc_site_settings', []);
            $event_start = (string) ($values['event_start'] ?? ($settings['event_start'] ?? '2026-10-17T09:00:00+03:00'));
            ?>
            <div class="fc-field">
                <label>Brand text</label>
                <input type="text" name="fc_field[brand]" value="<?php echo esc_attr((string) ($values['brand'] ?? 'FOSSCOMM/2026')); ?>">
                <p class="description">Shown on the far left of the top bar. Clicking it scrolls to the top of the page.</p>
            </div>
            <div class="fc-grid-2">
                <div class="fc-field">
                    <label>Location (English)</label>
                    <input type="text" name="fc_field[location_en]" value="<?php echo esc_attr((string) ($values['location_en'] ?? 'Athens')); ?>">
                </div>
                <div class="fc-field">
                    <label>Location (Ελληνικά)</label>
                    <input type="text" name="fc_field[location_el]" value="<?php echo esc_attr((string) ($values['location_el'] ?? 'Αθήνα')); ?>">
                </div>
            </div>
            <div class="fc-field">
                <label>Event start (ISO 8601)</label>
                <input type="text" name="fc_field[event_start]" value="<?php echo esc_attr($event_start); ?>" placeholder="2026-10-17T09:00:00+03:00">
                <p class="description">Drives the countdown ticker (<code>T-…</code>). Format: <code>YYYY-MM-DDTHH:MM:SS±HH:MM</code>. Mirrors into <code>fc_site_settings.event_start</code>.</p>
            </div>
            <?php
        },
    ]);
}
