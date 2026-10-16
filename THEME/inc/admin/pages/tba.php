<?php
/**
 * Admin page: FOSSCOMM → Empty section text (TBA).
 * One bilingual textarea per "data-driven" section (speakers, communities,
 * schedule, sponsors, news, faq). The matching section template renders
 * fc_render_tba(<key>) when its data store is empty, falling back to a global
 * default if no copy is saved. Each is headed by the section's own name (as
 * renamed on the Sections page) and a line saying exactly when it shows.
 *
 * Stored in option `fc_tba_text` as: [ <section_key> => ['el' => '…', 'en' => '…'] ].
 * Reader: fc_tba_text() in inc/helpers.php.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_tba', 25);
function fc_admin_register_tba() {
    add_submenu_page(
        FC_ADMIN_SLUG,
        'Empty Section Text',
        '— TBA Text',
        FC_ADMIN_CAP,
        'fc_tba_text',
        'fc_admin_page_tba'
    );
}

/** Sections that opt-in to a TBA fallback, in the order they sit on the page. */
function fc_tba_section_keys(): array {
    return [
        'speakers'    => 'Speakers',
        'communities' => 'Communities',
        'schedule'    => 'Schedule',
        'sponsors'    => 'Sponsors',
        'news'        => 'News',
        'faq'         => 'FAQ',
    ];
}

/** When each section shows its empty text — one line under its heading here. */
function fc_tba_section_notes(): array {
    return [
        'speakers'    => 'Shown on the landing page while no speaker is ticked <strong>Highlight</strong>, and on <code>/speakers/</code> while there are no speakers at all.',
        'communities' => 'Shown on the landing page and on <code>/communities/</code> until the first community is added (FOSSCOMM → Communities).',
        'schedule'    => 'Shown until the first session is added. The day bar and the timeline appear with it — days and rooms on their own are not enough.',
        'sponsors'    => 'Shown while there are no sponsor tiers. The funding sentence and the buttons below it still show.',
        'news'        => 'Shown while there are no news entries.',
        'faq'         => 'Shown while there are no questions.',
    ];
}

function fc_admin_page_tba() {
    if (!current_user_can(FC_ADMIN_CAP)) {
        wp_die(__('Insufficient permissions.', 'fosscomm'));
    }

    $keys = fc_tba_section_keys();

    if (
        isset($_POST['fc_tba_save']) &&
        check_admin_referer('fc_tba_save', 'fc_tba_nonce')
    ) {
        // wp_unslash: strip WordPress's transport slashes before sanitising, or
        // quotes/apostrophes accumulate backslashes on every save.
        $post  = wp_unslash($_POST);
        $raw   = isset($post['fc_tba']) && is_array($post['fc_tba']) ? $post['fc_tba'] : [];
        $clean = [];
        foreach ($keys as $k => $_label) {
            $row = isset($raw[$k]) && is_array($raw[$k]) ? $raw[$k] : [];
            $clean[$k] = [
                'el' => sanitize_textarea_field((string) ($row['tba_el'] ?? '')),
                'en' => sanitize_textarea_field((string) ($row['tba_en'] ?? '')),
            ];
        }
        update_option('fc_tba_text', $clean, false);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'fosscomm') . '</p></div>';
    }

    $stored = get_option('fc_tba_text', []);
    if (!is_array($stored)) $stored = [];
    $default = 'Insert profound, life-changing content here. (Check back when we figure out what that is).';
    ?>
    <div class="wrap fc-wrap">
        <h1>FOSSCOMM — Empty Section Text</h1>
        <div class="fc-callout">
            When a section has nothing in it yet, the site shows this short line in its place, centred, in the small type. Each one is set per section and per language; wrap a word in <code>*asterisks*</code> to set it in the accent colour. Leave a box empty to show nothing at all.
        </div>
        <form method="post">
            <?php wp_nonce_field('fc_tba_save', 'fc_tba_nonce'); ?>
            <?php $notes = fc_tba_section_notes();
            foreach ($keys as $key => $label) :
                $row = isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : [];
                $row_normalised = [
                    'tba_el' => (string) ($row['el'] ?? $default),
                    'tba_en' => (string) ($row['en'] ?? $default),
                ];
                // The section's own name, as renamed on the Sections page.
                $def  = fc_section_def($key);
                $name = $def ? fc_section_label($def) : $label;
                $state = function_exists('fc_sections_state') ? fc_sections_state() : [];
                $off   = isset($state[$key]) && empty($state[$key]['active']);
                ?>
                <h2 style="margin-top:2rem;"><?php echo esc_html($name ?: $label); ?><?php if ($off) : ?> <span style="font-weight:400;font-size:13px;color:#8c8f94;">— hidden on the Sections page</span><?php endif; ?></h2>
                <?php if (!empty($notes[$key])) : ?>
                    <p class="description" style="margin-top:-0.25rem;"><?php echo wp_kses_post($notes[$key]); ?></p>
                <?php endif; ?>
                <?php fc_bilingual_field('tba', $row_normalised, [
                    'label'       => '',
                    'type'        => 'textarea',
                    'rows'        => 3,
                    'name_prefix' => 'fc_tba[' . $key . ']',
                ]); ?>
            <?php endforeach; ?>
            <p class="fc-savebar">
                <button type="submit" name="fc_tba_save" value="1" class="button button-primary">
                    <?php esc_html_e('Save changes', 'fosscomm'); ?>
                </button>
                <span class="fc-dirty-note">You have unsaved changes.</span>
            </p>
        </form>
    </div>
    <?php
}
