<?php
/**
 * Admin page: FOSSCOMM → Text Style.
 *
 * One switch for now: whether the buttons and the small card titles — every
 * piece of text set in the sponsor tier title's face (.fc-btn) — are forced into
 * capitals. Read on the front end by fc_buttons_uppercase() in inc/helpers.php.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_text_style', 20);
function fc_admin_register_text_style() {
    add_submenu_page(FC_ADMIN_SLUG, 'Text Style', '— Text Style', FC_ADMIN_CAP, 'fc_text_style', 'fc_admin_page_text_style');
}

function fc_admin_page_text_style() {
    fc_render_section_admin_page([
        'slug'       => 'fc_text_style',
        'title'      => 'Text Style',
        'option_key' => 'fc_text_style',
        'intro'      => 'How the buttons and small titles look across the whole site.',
        'schema'     => [
            // 'text', not a bool: the value is only ever the literal '1' or '0'
            // from the hidden input + checkbox pair below — same as the Greek
            // switch on the Top Bar page.
            'buttons_caps' => 'text',
        ],
        'post_process' => function ($clean) {
            $clean['buttons_caps'] = ((string) ($clean['buttons_caps'] ?? '0')) === '1' ? '1' : '0';
            return $clean;
        },
        'render_form' => function ($values) {
            $caps = fc_buttons_uppercase();
            ?>
            <div class="fc-field">
                <label>Buttons and small titles</label>
                <?php /* Hidden input first, so UNTICKING still posts a "0" — an
                         unchecked box sends nothing, and the setting could never
                         be switched off. When ticked, the checkbox's "1" wins. */ ?>
                <input type="hidden" name="fc_field[buttons_caps]" value="0">
                <label style="font-weight:400;display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="fc_field[buttons_caps]" value="1" <?php checked($caps, true); ?>>
                    <span>Show in CAPITALS</span>
                </label>
                <p class="description">
                    Applies to the Home buttons, the Get Involved card titles, “Become a sponsor”,
                    the venue’s “Getting here” card titles, “Read more” on the news cards,
                    “External source” on a news article, and the FAQ questions and answers.<br>
                    Off (the default): shown <strong>exactly as typed</strong> — write
                    <code>Open the schedule</code> for normal case, or <code>OPEN THE SCHEDULE</code>
                    to put one in capitals by hand.<br>
                    On: everything above is capitalised, however it was typed.
                </p>
            </div>
            <?php
        },
    ]);
}
