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
            // 'text', not a bool type: this schema has no bool, and the value is
            // only ever the literal '1' or '0' from the paired hidden input +
            // checkbox in the form. fc_greek_enabled() compares against '1'.
            'greek_enabled' => 'text',
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
            /* Greek on/off. A hidden input paired with the checkbox so that
             * UNTICKING it posts a value: an unchecked box sends nothing at all,
             * which post_process cannot tell apart from "the field was not on the
             * form", and the setting could then never be turned off. The hidden
             * input is first, so the checkbox's "1" wins when it is ticked. */
            $greek_on = fc_greek_enabled();
            ?>
            <hr style="margin:2rem 0;">
            <div class="fc-field">
                <label>Languages</label>
                <input type="hidden" name="fc_field[greek_enabled]" value="0">
                <label style="font-weight:400;display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="fc_field[greek_enabled]" value="1"
                           <?php checked($greek_on, true); ?>>
                    <span>Offer Greek as well as English</span>
                </label>
                <p class="description">
                    On: the <code>ΕΛΛΗΝΙΚΑ / ENGLISH</code> toggle appears at the far left of the
                    top bar and visitors can switch, with their choice remembered for a year.<br>
                    Off: the toggle is hidden and <strong>the whole site is English</strong> — including
                    for anyone who had already chosen Greek, and for old <code>?lang=el</code> links.
                    Nothing is deleted: every Greek field you have filled in stays in the database and
                    comes back the moment you switch this on again.
                </p>
            </div>
            <?php
        },
    ]);
}
