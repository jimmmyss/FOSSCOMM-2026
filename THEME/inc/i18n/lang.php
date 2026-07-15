<?php
/**
 * Bilingual language resolver. The site shows ONE language at a time now (English
 * is the default); a toggle in the status bar switches it.
 * Priority: validated ?lang= override → cookie → default.
 */
if (!defined('ABSPATH')) {
    exit;
}

const FC_LANGS = ['el', 'en'];
const FC_LANG_DEFAULT = 'en';
const FC_LANG_COOKIE  = 'fc_lang';

/**
 * The active language for this request. Resolved once and cached: a valid
 * ?lang= query arg wins (and is persisted to a cookie on `init`), then the
 * cookie, then the default. English is the site default.
 */
function fc_current_lang(): string {
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }
    // ?lang= override (sanitized; must be a known language).
    if (isset($_GET['lang'])) {
        $q = strtolower(sanitize_key((string) wp_unslash($_GET['lang'])));
        if (in_array($q, FC_LANGS, true)) {
            return $lang = $q;
        }
    }
    // Persisted choice.
    if (isset($_COOKIE[FC_LANG_COOKIE])) {
        $c = strtolower(sanitize_key((string) wp_unslash($_COOKIE[FC_LANG_COOKIE])));
        if (in_array($c, FC_LANGS, true)) {
            return $lang = $c;
        }
    }
    return $lang = FC_LANG_DEFAULT;
}

/**
 * Persist a ?lang= choice to a cookie so it survives navigation (the landing
 * page, /coc/, /news/<slug>/ all read the same cookie). Hooked on `init`, which
 * runs before any template output, so setcookie() is safe.
 */
add_action('init', 'fc_resolve_lang_cookie');
function fc_resolve_lang_cookie(): void {
    if (!isset($_GET['lang'])) {
        return;
    }
    $q = strtolower(sanitize_key((string) wp_unslash($_GET['lang'])));
    if (!in_array($q, FC_LANGS, true)) {
        return;
    }
    $current = isset($_COOKIE[FC_LANG_COOKIE]) ? (string) $_COOKIE[FC_LANG_COOKIE] : '';
    if ($current === $q) {
        return; // already set — don't resend the header
    }
    if (!headers_sent()) {
        setcookie(FC_LANG_COOKIE, $q, [
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/',
            'samesite' => 'Lax',
        ]);
    }
    // Reflect immediately for the rest of this request.
    $_COOKIE[FC_LANG_COOKIE] = $q;
}

/** The language NOT currently active (the one the toggle switches to). */
function fc_other_lang(): string {
    return fc_current_lang() === 'en' ? 'el' : 'en';
}

/** Display name of a language in its own script ("ENGLISH" / "ΕΛΛΗΝΙΚΑ"). */
function fc_lang_endonym(string $lang): string {
    return $lang === 'el' ? 'ΕΛΛΗΝΙΚΑ' : 'ENGLISH';
}

/**
 * The current URL with the `lang` query arg set to $to — the href the status-bar
 * toggle points at. Built off the live request URI so it preserves the page and
 * any other query args.
 */
function fc_lang_switch_url(string $to): string {
    if (!in_array($to, FC_LANGS, true)) {
        $to = FC_LANG_DEFAULT;
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    return esc_url(add_query_arg('lang', $to, $uri));
}

/**
 * Reads a bilingual pair from a WP option, where the option is an array with `_el` and `_en` keys.
 */
function fc_option_text(string $option_key, string $field_base, string $fallback = ''): string {
    $opt = get_option($option_key, []);
    if (!is_array($opt)) {
        return $fallback;
    }
    $el = (string) ($opt[$field_base . '_el'] ?? '');
    $en = (string) ($opt[$field_base . '_en'] ?? '');
    $picked = fc_pick($el, $en);
    return $picked !== '' ? $picked : $fallback;
}
