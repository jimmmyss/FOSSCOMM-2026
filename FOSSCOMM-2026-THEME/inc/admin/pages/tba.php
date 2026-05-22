<?php
/**
 * Admin page: FOSSCOMM → Empty section text (TBA).
 * One bilingual textarea per "data-driven" section (speakers, schedule, sponsors,
 * news, faq). The matching section template renders fc_render_tba(<key>) when its
 * data store is empty, falling back to a global default if no copy is saved.
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

/** Sections that opt-in to a TBA fallback. Order = display order in the admin. */
function fc_tba_section_keys(): array {
    return [
        'speakers' => 'Speakers',
        'schedule' => 'Schedule',
        'sponsors' => 'Sponsors',
        'news'     => 'News',
        'faq'      => 'FAQ',
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
        $raw   = isset($_POST['fc_tba']) && is_array($_POST['fc_tba']) ? $_POST['fc_tba'] : [];
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
            When a section has no entries yet (no speakers added, no schedule sessions, etc.), the front-end shows this bilingual placeholder centered inside the section. You can use <code>*word*</code> to highlight a word in the theme accent colour.
        </div>
        <form method="post">
            <?php wp_nonce_field('fc_tba_save', 'fc_tba_nonce'); ?>
            <?php foreach ($keys as $key => $label) :
                $row = isset($stored[$key]) && is_array($stored[$key]) ? $stored[$key] : [];
                $row_normalised = [
                    'tba_el' => (string) ($row['el'] ?? $default),
                    'tba_en' => (string) ($row['en'] ?? $default),
                ];
                ?>
                <h2 style="margin-top:2rem;"><?php echo esc_html($label); ?></h2>
                <?php fc_bilingual_field('tba', $row_normalised, [
                    'label'       => '',
                    'type'        => 'textarea',
                    'rows'        => 3,
                    'name_prefix' => 'fc_tba[' . $key . ']',
                ]); ?>
            <?php endforeach; ?>
            <p style="margin-top:1.5rem;">
                <button type="submit" name="fc_tba_save" value="1" class="button button-primary">
                    <?php esc_html_e('Save changes', 'fosscomm'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}
