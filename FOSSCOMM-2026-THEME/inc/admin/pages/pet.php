<?php
/**
 * Admin page: FOSSCOMM → ASCII Pet.
 * Single on/off toggle for the autonomous ASCII pet that wanders the page.
 * Persisted in option `fc_pet_enabled` (string '1' / '0'); default ON.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_pet', 15);
function fc_admin_register_pet() {
    add_submenu_page(
        FC_ADMIN_SLUG,
        'ASCII Pet',
        '— ASCII Pet',
        FC_ADMIN_CAP,
        'fc_pet_settings',
        'fc_admin_page_pet'
    );
}

function fc_admin_page_pet() {
    if (!current_user_can(FC_ADMIN_CAP)) {
        wp_die(__('Insufficient permissions.', 'fosscomm'));
    }

    if (
        isset($_POST['fc_pet_save']) &&
        check_admin_referer('fc_pet_save', 'fc_pet_nonce')
    ) {
        $enabled = !empty($_POST['fc_pet_enabled']) ? '1' : '0';
        update_option('fc_pet_enabled', $enabled, false);
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Saved.', 'fosscomm') . '</p></div>';
    }

    $enabled = fc_pet_is_enabled();
    ?>
    <div class="wrap fc-wrap">
        <h1>FOSSCOMM — ASCII Pet</h1>
        <div class="fc-callout">
            The pet is a tiny autonomous ASCII critter that wanders the page in the bottom corner. Purely decorative — disabling it removes its script and markup from every page load.
        </div>
        <form method="post">
            <?php wp_nonce_field('fc_pet_save', 'fc_pet_nonce'); ?>
            <div class="fc-field">
                <label>
                    <input type="checkbox" name="fc_pet_enabled" value="1" <?php checked($enabled); ?>>
                    Show the ASCII pet on the site
                </label>
            </div>
            <p style="margin-top:1.5rem;">
                <button type="submit" name="fc_pet_save" value="1" class="button button-primary">
                    <?php esc_html_e('Save changes', 'fosscomm'); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
}
