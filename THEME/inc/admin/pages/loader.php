<?php
/**
 * Admin page: FOSSCOMM → Loading Screen.
 *
 * Everything the loading screen does is set here: the two logos, the flicker
 * (a timeline of steps, each light on or off, held for so many ms and popping
 * with so much bounciness — the same strip the Mascot page uses for frames), how
 * long it waits for the page, how the mascot takes over, and the swipe that
 * reveals the site.
 *
 * Two previews, both running the SAME code as the site (assets/loader/loader.js):
 *   • a small box on the Flicker tab, for tuning the light and the bounce; it
 *     follows every edit as you type;
 *   • "Preview" at the top: the real front page in an iframe, at a desktop or a
 *     phone size, running the settings on this page — saved or not — with the
 *     real mascot and the real site underneath. Nothing is saved by watching it.
 *
 * The page is the shared section-page scaffold (inc/admin/sections-page.php), so
 * it has the nonce, the sticky save bar and the unsaved-changes warning every
 * other FOSSCOMM page has. The option itself — defaults, validation, reading —
 * lives in inc/loader.php, which the front end needs too.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_loader', 16);   // after Mascot (15), before Cursor (20)
function fc_admin_register_loader() {
    add_submenu_page(FC_ADMIN_SLUG, 'Loading Screen', '— Loading Screen', FC_ADMIN_CAP, 'fc_loader', 'fc_admin_page_loader');
}

add_action('admin_enqueue_scripts', 'fc_admin_loader_assets', 11);
function fc_admin_loader_assets($hook) {
    if (strpos((string) $hook, 'fc_loader') === false) return;
    if (!function_exists('fc_loader_css')) return;

    wp_register_style('fc-loader-admin', false, [], FC_THEME_VERSION);
    wp_enqueue_style('fc-loader-admin');
    wp_add_inline_style('fc-loader-admin', fc_loader_css() . "\n" . fc_admin_loader_css());

    // The factory only — never boot.js, which would cover the dashboard.
    wp_enqueue_script('fc-loader', FC_THEME_URI . '/assets/loader/loader.js', [], FC_THEME_VERSION, true);
    wp_enqueue_script('fc-loader-admin', FC_THEME_URI . '/assets/loader/admin.js', ['fc-loader', 'fc-admin'], FC_THEME_VERSION, true);
    // The mascot, for the Opening preview on the Mascot & swipe tab: his
    // stylesheet here; his module is imported by admin.js when it first plays.
    if (function_exists('fc_mascot_js_config')) {
        wp_enqueue_style('fc-mascot', FC_THEME_URI . '/assets/mascot/mascot.css', [], FC_THEME_VERSION);
    }
    // NB: FC_LOADER_ADMIN is localised in the page callback, after a save, so the
    // previews start from what was just saved rather than what it replaced.
}

function fc_admin_loader_css(): string {
    return <<<'CSS'
.fcl-card { background: #fff; border: 1px solid #c3c4c7; padding: 14px 16px; margin: 0 0 16px; }
.fcl-card > h2 { margin: 0 0 4px; font-size: 14px; }
.fcl-card > .description { margin: 0 0 12px; }
.fcl-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin: 0 0 12px; }
.fcl-seg { display: inline-flex; }
.fcl-seg .button { border-radius: 0; margin: 0 -1px 0 0; }
.fcl-seg .button:first-child { border-radius: 3px 0 0 3px; }
.fcl-seg .button:last-child { border-radius: 0 3px 3px 0; }
.fcl-seg .button[aria-pressed="true"] { background: #2271b1; border-color: #2271b1; color: #fff; position: relative; z-index: 1; }
.fcl-bar .fcl-label { font-weight: 600; font-size: 12px; color: #50575e; margin-right: 2px; }

.fcl-frame-wrap { position: relative; overflow: hidden; background: #1d2327; border-radius: 4px; margin: 0 auto; }
.fcl-frame { position: absolute; top: 0; left: 0; transform-origin: 0 0; background: #fff; }
.fcl-frame iframe { display: block; width: 100%; height: 100%; border: 0; }
.fcl-frame-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
                   color: #c3c4c7; font-size: 13px; text-align: center; padding: 20px; }

.fcl-mini { position: relative; height: 260px; overflow: hidden; border-radius: 4px; margin: 0 0 8px;
            background: #FAFAF7; }
/* What the swipe reveals in the small box: a sketch of the hero. */
.fcl-mini::before { content: ''; position: absolute; top: 0; bottom: 0; left: 0; width: 33.3%; background: #0033FF; }
.fcl-mini::after { content: 'FOSSCOMM'; position: absolute; top: 50%; left: 16.6%; transform: translate(-50%, -50%);
                   font: 700 18px/1 sans-serif; color: #fff; letter-spacing: .02em; }
.fcl-mini-bar { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 0 0 14px; }
/* The Opening preview: taller, for the mascot's fall, with him in it (his layer
   is fixed to the window on the site; here it is the box's, as on the Mascot
   page's stage). */
.fcl-entrance { height: 300px; }
.fcl-entrance #fosscomm-mascot { position: absolute; }

.fcl-timeline { display: flex; flex-wrap: wrap; gap: 8px; margin: 6px 0 10px; }
.fcl-step { width: 128px; border: 1px solid #c3c4c7; border-radius: 4px; padding: 6px 8px 8px; background: #1d2327; color: #f0f0f1;
            display: grid; gap: 5px; font-size: 11px; position: relative; }
.fcl-step.is-on { background: #fff; color: #1d2327; border-color: #8c8f94; }
.fcl-step.is-current { box-shadow: 0 0 0 2px #EE8101; }
.fcl-step[data-fcl-last] { border-color: #2271b1; box-shadow: inset 0 0 0 1px #2271b1; }
.fcl-step[data-fcl-last].is-current { box-shadow: inset 0 0 0 1px #2271b1, 0 0 0 2px #EE8101; }
.fcl-step-head { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
.fcl-step-n { font-weight: 600; }
.fcl-step-tools { display: flex; gap: 2px; }
.fcl-step-tools button { background: none; border: 0; padding: 0 3px; cursor: pointer; color: inherit; opacity: .75; font-size: 12px; line-height: 18px; }
.fcl-step-tools button:hover { opacity: 1; color: #EE8101; }
.fcl-step label { display: grid; grid-template-columns: 52px 1fr; align-items: center; gap: 4px; }
.fcl-step input[type=number], .fcl-step select { width: 100%; min-height: 26px; font-size: 12px; padding: 0 4px; }
.fcl-step .fcl-stays { display: none; font-weight: 600; color: #2271b1; }
.fcl-step[data-fcl-last] .fcl-stays { display: inline; }
.fcl-step[data-fcl-last] [data-fcl-state] { display: none; }
.fcl-step[data-fcl-last] .fcl-step-tools { visibility: hidden; }

.fcl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px 22px; margin: 0 0 8px; }
.fcl-num > label { display: block; font-weight: 600; font-size: 12px; margin: 0 0 3px; }
.fcl-num input[type=number], .fcl-num select { width: 100%; }
.fcl-num .description { font-size: 11px; margin: 3px 0 0; line-height: 1.45; }
.fcl-num output { font-family: ui-monospace, Menlo, monospace; margin-left: 8px; }
.fcl-num input[type=range] { width: calc(100% - 60px); vertical-align: middle; }
.fcl-check { display: block; margin: 0 0 10px; }
.fcl-check .description { margin: 2px 0 0 24px; font-size: 12px; }
.fcl-logos { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 0 24px; }
/* A white "on" logo vanishes on the stock checker: show both on the overlay's blue. */
.fcl-logos .fc-media-preview { width: 120px; height: 120px; background: #0033FF; display: flex; align-items: center; justify-content: center; }
.fcl-logos .fc-media-preview img { max-width: 90%; max-height: 90%; }
.fcl-sub { margin: 18px 0 8px; font-size: 13px; }
CSS;
}

/* ─────────────────────────────────────────────────────────────────────────────
   SMALL RENDERERS
   ───────────────────────────────────────────────────────────────────────────*/

/**
 * A number box. `step="any"` and no min/max on purpose: an out-of-range value in
 * a field on a hidden tab would block Save without a word. The server clamps.
 */
function fc_admin_loader_number(string $key, $value, string $label, string $help = '', string $unit = ''): void {
    ?>
    <div class="fcl-num">
        <label for="fcl-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?><?php
            echo $unit !== '' ? ' <span style="font-weight:400;color:#646970;">(' . esc_html($unit) . ')</span>' : ''; ?></label>
        <input type="number" step="any" id="fcl-<?php echo esc_attr($key); ?>"
               name="fc_field[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $value); ?>">
        <?php if ($help !== '') : ?><p class="description"><?php echo esc_html($help); ?></p><?php endif; ?>
    </div>
    <?php
}

function fc_admin_loader_select(string $key, string $value, string $label, array $options, string $help = ''): void {
    ?>
    <div class="fcl-num">
        <label for="fcl-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
        <select id="fcl-<?php echo esc_attr($key); ?>" name="fc_field[<?php echo esc_attr($key); ?>]">
            <?php foreach ($options as $k => $text) : ?>
                <option value="<?php echo esc_attr((string) $k); ?>" <?php selected($value, (string) $k); ?>><?php echo esc_html($text); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($help !== '') : ?><p class="description"><?php echo esc_html($help); ?></p><?php endif; ?>
    </div>
    <?php
}

function fc_admin_loader_check(string $key, bool $on, string $label, string $help = ''): void {
    ?>
    <label class="fcl-check">
        <input type="checkbox" name="fc_field[<?php echo esc_attr($key); ?>]" value="1" <?php checked($on); ?>>
        <?php echo esc_html($label); ?>
        <?php if ($help !== '') : ?><span class="description" style="display:block;"><?php echo esc_html($help); ?></span><?php endif; ?>
    </label>
    <?php
}

/**
 * A colour that follows the site unless set. The shared `.fc-colour` markup
 * (picker + hex box, kept in sync by inc/admin/menu.php): the hex box is the
 * value, and empty means "follow the site", shown as its placeholder.
 */
function fc_admin_loader_colour(string $key, string $value, string $fallback, string $label, string $help = '', string $placeholder = ''): void {
    $shown = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
    ?>
    <div class="fcl-num">
        <label><?php echo esc_html($label); ?></label>
        <span class="fc-colour">
            <input type="color" class="fc-colour-pick" value="<?php echo esc_attr($shown); ?>" aria-label="<?php echo esc_attr($label); ?>">
            <input type="text" class="fc-colour-hex code" name="fc_field[<?php echo esc_attr($key); ?>]"
                   value="<?php echo esc_attr($value); ?>" placeholder="<?php echo esc_attr($placeholder !== '' ? $placeholder : $fallback); ?>" size="11">
            <?php if ($value !== '') : ?>
                <button type="button" class="button-link" data-fcl-clear-colour style="margin-left:6px;">Clear</button>
            <?php else : ?>
                <button type="button" class="button-link" data-fcl-clear-colour style="margin-left:6px;" hidden>Clear</button>
            <?php endif; ?>
        </span>
        <p class="description"><?php echo esc_html($help !== '' ? $help : 'Empty follows the site (' . $fallback . ').'); ?></p>
    </div>
    <?php
}

/**
 * One cell of the flicker timeline. The JS (assets/loader/admin.js) clones the
 * <template> copy of this for new steps and renumbers every name after any
 * add, move, duplicate or delete; the server forces the last step on, whatever
 * arrives.
 */
function fc_admin_loader_step(int $i, array $step, bool $last): void {
    $on = ($step['state'] ?? 'off') === 'on' || $last;
    $p  = 'fc_loader_steps[' . $i . ']';
    ?>
    <div class="fcl-step<?php echo $on ? ' is-on' : ''; ?>" data-fcl-step<?php echo $last ? ' data-fcl-last' : ''; ?>>
        <div class="fcl-step-head">
            <span class="fcl-step-n">#<?php echo (int) ($i + 1); ?></span>
            <span class="fcl-step-tools">
                <button type="button" data-fcl-move="-1" title="Earlier" aria-label="Move earlier">←</button>
                <button type="button" data-fcl-move="1" title="Later" aria-label="Move later">→</button>
                <button type="button" data-fcl-dup title="Duplicate" aria-label="Duplicate">⧉</button>
                <button type="button" data-fcl-del title="Delete" aria-label="Delete">✕</button>
            </span>
        </div>
        <label>Light
            <span>
                <select name="<?php echo esc_attr($p); ?>[state]" data-fcl-state>
                    <option value="off" <?php selected(!$on); ?>>Off</option>
                    <option value="on" <?php selected($on); ?>>On</option>
                </select>
                <span class="fcl-stays" title="The last step is the light staying on">Stays on</span>
            </span>
        </label>
        <label>Hold ms
            <input type="number" step="any" name="<?php echo esc_attr($p); ?>[ms]" data-fcl-ms
                   value="<?php echo esc_attr((string) (int) ($step['ms'] ?? 120)); ?>">
        </label>
        <label>Bounce
            <input type="number" step="any" name="<?php echo esc_attr($p); ?>[bounce]" data-fcl-bounce
                   value="<?php echo esc_attr((string) (float) ($step['bounce'] ?? 0)); ?>">
        </label>
    </div>
    <?php
}

/* ─────────────────────────────────────────────────────────────────────────────
   THE PAGE
   ───────────────────────────────────────────────────────────────────────────*/

function fc_admin_page_loader() {
    if (!function_exists('fc_loader_settings')) {
        echo '<div class="wrap"><h1>FOSSCOMM — Loading Screen</h1><p>inc/loader.php is missing.</p></div>';
        return;
    }
    fc_render_section_admin_page([
        'slug'         => 'fc_loader',
        'title'        => 'Loading Screen',
        'option_key'   => FC_LOADER_OPTION,
        'intro'        => 'Shown over the site while it loads: your logo flickers on, waits until the page is ready, '
                        . 'the mascot takes its place and plays his <strong>Opening</strong>, and the blue sweeps away '
                        . 'towards the hero’s blue panel (left on a computer, up on a phone), timed to take his floor '
                        . 'from under him the moment Opening ends, so he falls; then the white with the site’s own '
                        . 'dots, then blue again, revealing the site he lands on.',
        'schema'       => [],
        'render_form'  => 'fc_admin_loader_form',
        'post_process' => 'fc_admin_loader_post_process',
    ]);
}

/**
 * Rebuild the option from the POST. The flat fields arrive under fc_field[…], the
 * steps under fc_loader_steps[…]; a tick box that is not ticked does not arrive
 * at all, which fc_loader_sanitize() reads as false — exactly right here.
 */
function fc_admin_loader_post_process(array $clean, array $post): array {
    $raw = isset($post['fc_field']) && is_array($post['fc_field']) ? $post['fc_field'] : [];
    $raw['steps'] = isset($post['fc_loader_steps']) && is_array($post['fc_loader_steps'])
        ? array_values($post['fc_loader_steps'])
        : [];
    return fc_loader_sanitize($raw);
}

function fc_admin_loader_form($values) {
    // Fresh: the scaffold may have saved a moment ago, during this same request.
    $s   = fc_loader_settings(true);
    $col = fc_loader_colour_defaults();
    $mascot_on  = function_exists('fc_mascot_is_enabled') && fc_mascot_is_enabled();
    $mascot_url = admin_url('admin.php?page=fc_mascot');

    // The saved mascot, for the Opening preview: the same payload the Mascot
    // page's stage runs on, and the module that builds him.
    $mascot_cfg = ($mascot_on && function_exists('fc_mascot_js_config')) ? fc_mascot_js_config() : null;
    if ($mascot_cfg) wp_localize_script('fc-loader-admin', 'FC_MASCOT', $mascot_cfg);
    wp_localize_script('fc-loader-admin', 'FC_LOADER_ADMIN', [
        'config'        => fc_loader_js_config($s),
        'defaults'      => $col,
        'previewBase'   => fc_loader_preview_base(),
        'mascotEnabled' => $mascot_on,
        'mascotUrl'     => $mascot_url,
        'mascotModule'  => $mascot_cfg ? FC_THEME_URI . '/assets/mascot/js/main.js?ver=' . rawurlencode(FC_THEME_VERSION) : '',
    ]);
    $steps = array_values($s['steps']);
    ?>
    <div class="fcl-card" data-fcl-preview>
        <h2>Preview</h2>
        <p class="description">
            The real front page, running the settings on this page — saved or not — with the real mascot.
            Watching it saves nothing.
        </p>
        <div class="fcl-bar">
            <span class="fcl-seg" role="group" aria-label="Device">
                <button type="button" class="button" data-fcl-device="desktop" aria-pressed="true">Desktop</button>
                <button type="button" class="button" data-fcl-device="phone" aria-pressed="false">Phone</button>
            </span>
            <span>
                <span class="fcl-label">Slow load</span>
                <span class="fcl-seg" role="group" aria-label="Pretend the page loads slowly">
                    <button type="button" class="button" data-fcl-slow="0" aria-pressed="true">None</button>
                    <button type="button" class="button" data-fcl-slow="2" aria-pressed="false">+2s</button>
                    <button type="button" class="button" data-fcl-slow="5" aria-pressed="false">+5s</button>
                </span>
            </span>
            <button type="button" class="button button-primary" data-fcl-play>▶ Play</button>
        </div>
        <div class="fcl-frame-wrap" data-fcl-frame-wrap>
            <div class="fcl-frame-empty" data-fcl-frame-empty>Press ▶ Play to watch it on the real site.</div>
        </div>
    </div>

    <nav class="fc-page-tabs" aria-label="Loading screen">
        <a href="#" class="active" data-panel="logo">Logo</a>
        <a href="#" data-panel="flicker">Flicker<span class="fc-tab-count" data-fcl-step-count><?php echo count($steps); ?></span></a>
        <a href="#" data-panel="reveal">Mascot &amp; swipe</a>
        <a href="#" data-panel="settings">Settings</a>
    </nav>

    <div class="fc-page-panel active" data-panel="logo">
        <p class="fc-panel-help">Two versions of the same logo: the light off, and the light on. SVG is best. Leave one
            empty and a placeholder square stands in — black for off, white for on.</p>
        <div class="fcl-logos">
            <?php
            fc_media_field('logo_off', $s, ['label' => 'Logo — light off', 'full' => true,
                'help' => 'What shows first, before the light comes on.']);
            fc_media_field('logo_on', $s, ['label' => 'Logo — light on', 'full' => true,
                'help' => 'The lit version the flicker switches to.']);
            ?>
        </div>
        <div class="fcl-grid">
            <?php
            fc_admin_loader_colour('tint_off', (string) $s['tint_off'], '#000000', 'Colour — light off',
                'Fills the logo’s shape with one colour. Empty keeps the file’s own colours (the placeholder square is black).',
                'own colours');
            fc_admin_loader_colour('tint_on', (string) $s['tint_on'], '#FFFFFF', 'Colour — light on',
                'The same for the lit logo. Empty keeps the file’s own colours (the placeholder square is white).',
                'own colours');
            fc_admin_loader_number('size_desktop', $s['size_desktop'], 'Logo width — computer', 'The logo’s width on screens 1024px and wider.', 'px');
            fc_admin_loader_number('size_mobile', $s['size_mobile'], 'Logo width — phone', 'Below 1024px.', 'px');
            ?>
        </div>

        <h3 class="fcl-sub">Shine</h3>
        <p class="fc-panel-help" style="margin-top:0;">The light the logo gives off while it is lit. The small preview on the
            Flicker tab shows every change as you make it.</p>
        <div class="fcl-grid">
            <div class="fcl-num">
                <label for="fcl-glow">Strength</label>
                <input type="range" id="fcl-glow" name="fc_field[glow]" min="0" max="100" step="1"
                       value="<?php echo esc_attr((string) $s['glow']); ?>"
                       oninput="this.nextElementSibling.textContent = this.value + '%'">
                <output><?php echo (int) $s['glow']; ?>%</output>
                <p class="description">0 for no shine at all.</p>
            </div>
            <?php
            fc_admin_loader_number('glow_size', $s['glow_size'], 'Size', 'How far the shine reaches past the logo’s edge.', 'px');
            fc_admin_loader_colour('glow_colour', (string) $s['glow_colour'], '#FFFFFF', 'Colour',
                'Empty is white.', 'white');
            fc_admin_loader_select('glow_style', $s['glow_style'], 'Style',
                ['bloom' => 'Soft bloom — a round light behind it', 'neon' => 'Neon — hugs the logo’s own shape', 'both' => 'Both'],
                'Neon follows the outline of the logo itself, like a lit tube.');
            ?>
        </div>
    </div>

    <div class="fc-page-panel" data-panel="flicker">
        <p class="fc-panel-help">The light coming on, step by step. Each step is the light <strong>on</strong> or
            <strong>off</strong>, how long it holds, and how much it <strong>bounces</strong> when it switches — the
            same bounciness as the mascot’s frames (0 = rigid, 1 = a little give, 2–3 = a proper pop). The last step
            is the light <strong>staying on</strong>: its bounce is the pop of it coming on for good, and its hold is
            how long the logo stays steadily lit before the first layer moves — also after a stutter while a slow
            page loads, so the reveal never starts on a logo that is still flickering.</p>

        <div class="fcl-mini" data-fcl-mini>
            <?php echo fc_loader_markup($s, ['stage' => true]); ?>
        </div>
        <div class="fcl-mini-bar">
            <button type="button" class="button button-primary" data-fcl-mini-play>▶ Play flicker</button>
            <button type="button" class="button" data-fcl-mini-pop title="The last step’s pop, once">Pop</button>
            <button type="button" class="button" data-fcl-mini-swipe title="The swipe, as on a computer">Play swipe</button>
            <span class="description" data-fcl-mini-note>This box follows your edits as you make them.</span>
        </div>

        <div class="fcl-timeline" data-fcl-timeline>
            <?php foreach ($steps as $i => $step) {
                fc_admin_loader_step((int) $i, (array) $step, $i === count($steps) - 1);
            } ?>
        </div>
        <p><button type="button" class="button" data-fcl-add>+ Add step</button>
            <span class="description" style="margin-left:8px;">New steps go in before “Stays on”.</span></p>

        <div class="fcl-grid">
            <?php
            fc_admin_loader_number('bounce_ms', $s['bounce_ms'], 'Bounce length',
                'How long one bounce takes to settle, the same as the mascot’s Bounce length. Shorter is snappier.', 'ms');
            fc_admin_loader_select('bounce_origin', $s['bounce_origin'], 'Bounces from',
                ['bottom' => 'The bottom — like the mascot', 'centre' => 'The centre'],
                'The point the logo squashes towards.');
            ?>
        </div>
    </div>

    <div class="fc-page-panel" data-panel="reveal">
        <?php /* The whole entrance, by the settings it depends on: the lit logo,
                 the mascot taking its place and playing Opening, the swipe with
                 the edge set below — straight or the wave — and his fall
                 (assets/loader/admin.js, FCLoader entrance()). */ ?>
        <div class="fcl-mini fcl-entrance" data-fcl-entrance>
            <?php echo fc_loader_markup($s, ['stage' => true]); ?>
        </div>
        <div class="fcl-mini-bar">
            <button type="button" class="button button-primary" data-fcl-entrance-play>▶ Play opening</button>
            <span class="fcl-seg" role="group" aria-label="Which way the layers swipe">
                <button type="button" class="button" data-fcl-entrance-dir="left" aria-pressed="true">Computer</button>
                <button type="button" class="button" data-fcl-entrance-dir="up" aria-pressed="false">Phone</button>
            </span>
            <span class="description" data-fcl-entrance-note><?php
                echo $mascot_on
                    ? 'The lit logo, the mascot’s Opening in its place, then the swipe with the edge below — straight or the wave — and his fall. This page’s settings, saved or not.'
                    : 'The lit logo, then the swipe with the edge below — straight or the wave. This page’s settings, saved or not.';
            ?></span>
        </div>

        <h3 class="fcl-sub">The mascot</h3>
        <?php if (!$mascot_on) : ?>
            <div class="fc-callout">The mascot is switched off (<a href="<?php echo esc_url($mascot_url); ?>">FOSSCOMM → Mascot</a>),
                so the logo itself rides out with the blue.</div>
        <?php endif; ?>
        <?php fc_admin_loader_check('mascot', !empty($s['mascot']), 'Let the mascot take over from the logo',
            'Once the page is ready he replaces the lit logo — at his normal size, where the logo was — and plays his Opening animation.'); ?>
        <div class="fc-callout">His <strong>Opening</strong> animation is set up like any other state in
            <a href="<?php echo esc_url($mascot_url); ?>">FOSSCOMM → Mascot → States → Opening</a> (there is an
            “Opening” button on that page’s preview too). Leave it empty and he goes straight into his fall.</div>
        <div class="fcl-grid">
            <?php
            fc_admin_loader_select('fall_at', $s['fall_at'], 'He falls',
                [
                    'floor'  => 'When the blue sweeps out from under him',
                    'swipe'  => 'As the swipe starts',
                    'reveal' => 'Once the site is revealed (he hangs until then)',
                ],
                'After Opening he stays standing where the logo was; at this moment Fall takes over and he drops.');
            fc_admin_loader_select('swipe_sync', $s['swipe_sync'], 'The swipe starts',
                [
                    'reach' => 'So it reaches him as Opening ends',
                    'after' => 'Once Opening has ended',
                ],
                'Timed, the first blue’s edge arrives under him exactly as his Opening animation finishes — the floor goes right where the animation stops, and he drops straight out of its last frame. Otherwise the blue only starts to move once Opening is over, and he stands on its last frame while it comes.');
            fc_admin_loader_number('swipe_delay', $s['swipe_delay'], 'Pause after Opening',
                'How long he holds Opening’s last frame first: before the floor goes (timed) or before the blue starts to move. 0 for none.', 'ms');
            ?>
        </div>

        <h3 class="fcl-sub">The swipe</h3>
        <div class="fcl-grid">
            <?php
            fc_admin_loader_colour('layer1', (string) $s['layer1'], $col['layer1'], 'First layer (blue)',
                'The one with the logo on it. Empty follows the site (' . $col['layer1'] . ').');
            fc_admin_loader_colour('layer2', (string) $s['layer2'], $col['layer2'], 'Second layer (white)');
            fc_admin_loader_colour('layer3', (string) $s['layer3'], $col['layer3'], 'Third layer (blue)',
                'The last one, which reveals the site. Empty follows the site (' . $col['layer3'] . ').');
            fc_admin_loader_number('swipe_ms', $s['swipe_ms'], 'Swipe length', 'How long each layer takes to cross the screen.', 'ms');
            fc_admin_loader_number('gap_ms', $s['gap_ms'], 'Each layer follows after',
                'How long after one layer starts the next one starts. Longer shows more of each colour between them.', 'ms');
            $ease = [];
            foreach (fc_loader_eases() as $k => $def) $ease[$k] = $def[0];
            fc_admin_loader_select('ease', $s['ease'], 'Easing', $ease, 'How the layers speed up and slow down.');
            ?>
        </div>

        <h3 class="fcl-sub">The edge</h3>
        <p class="fc-panel-help" style="margin-top:0;">The shape of each layer’s edge as it sweeps across: a straight line,
            or a wave that ripples along the edge as it goes.</p>
        <div class="fcl-grid">
            <?php
            fc_admin_loader_select('edge', $s['edge'], 'Edge', ['straight' => 'Straight', 'wave' => 'Wave']);
            fc_admin_loader_number('wave_height', $s['wave_height'], 'Wave height',
                'From the middle of the edge to a crest. Wave edge only.', 'px');
            fc_admin_loader_number('wave_length', $s['wave_length'], 'Wave length',
                'From one crest to the next. Wave edge only.', 'px');
            fc_admin_loader_number('wave_speed', $s['wave_speed'], 'Wave speed',
                'Crests passing a point each second; 0 holds the wave still. Wave edge only.', 'per second');
            ?>
        </div>

        <h3 class="fcl-sub">What the layers carry</h3>
        <?php
        fc_admin_loader_check('layer2_dots', !empty($s['layer2_dots']), 'The white layer is the site’s dotted background',
            'The page’s own moving dots, on the white layer, exactly where they are on the page.');
        fc_admin_loader_check('layer2_spot', !empty($s['layer2_spot']), 'The cursor’s goo on the white layer',
            'The site’s own cursor highlight — the blue goo and the drops it throws — follows the pointer (or a finger) on the white layer as it does on the site. It is the same goo, carried on: when the layers have gone, the one on the site is the one that was on the white layer.');
        fc_admin_loader_check('reveal_build', !empty($s['reveal_build']), 'Build the page up as the layers go',
            'The white layer already shows the top bar and the FOSSCOMM wordmark in blue; the next blue layer shows the bar white with blue words and the wordmark in white; then the site, where the bar is blue again and the wordmark stays white.');
        ?>
    </div>

    <div class="fc-page-panel" data-panel="settings">
        <?php fc_admin_loader_check('enabled', !empty($s['enabled']), 'Show the loading screen'); ?>
        <div class="fcl-grid">
            <?php
            fc_admin_loader_select('where', $s['where'], 'Where',
                ['all' => 'Every page', 'front' => 'The front page only']);
            fc_admin_loader_select('repeat', $s['repeat'], 'How often',
                ['every' => 'Every page load', 'session' => 'The first page of each visit'],
                '“First page of each visit” skips it on every later page in the same tab.');
            fc_admin_loader_number('max_wait_s', $s['max_wait_s'], 'Stop waiting after',
                'The longest it waits for the page, counted from when the page started loading. A slow phone gets the site at this point even if it is still loading.', 's');
            ?>
        </div>
        <?php fc_admin_loader_check('buzz', !empty($s['buzz']), 'Buzz while waiting',
            'If the page is still loading after the flicker, the lit logo stutters now and then, so it reads as alive.'); ?>
        <p class="description">Add <code>?fc_loader=0</code> to any address to see that page without the loading screen.</p>
    </div>

    <template id="fcl-step-template"><?php fc_admin_loader_step(0, ['state' => 'on', 'ms' => 80, 'bounce' => 0.5], false); ?></template>
    <?php
}
