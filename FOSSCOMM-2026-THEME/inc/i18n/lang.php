<?php
/**
 * Bilingual language resolver. Site default is Greek per FOSSCOMM tradition.
 * Priority: ?lang= override → cookie → default.
 */
if (!defined('ABSPATH')) {
    exit;
}

const FC_LANGS = ['el', 'en'];
const FC_LANG_DEFAULT = 'en';

/**
 * The site is fully bilingual — both languages render simultaneously, English first.
 * This helper exists for chrome strings (`fc_t()`) and always returns 'en'.
 */
function fc_current_lang(): string {
    return FC_LANG_DEFAULT;
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
