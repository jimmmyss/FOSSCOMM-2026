<?php
/**
 * What a search engine is told about the landing page.
 *
 * Three things, and only for the front page — the standalone pages (/speakers/,
 * /coc/, a news article) keep whatever the search engine works out for itself,
 * which for an article with a photograph is usually the right answer.
 *
 *   TITLE        exactly the site name, with no tagline after it. WordPress
 *                appends the tagline by default, so "FOSSCOMM 2026" became
 *                "FOSSCOMM 2026 – <tagline>". The name itself stays editable in
 *                Settings → General rather than being written into the theme.
 *   DESCRIPTION  the hero's own line — the one under the wordmark on the blue
 *                panel (FOSSCOMM → Home → "Top label"). Without one, a search
 *                engine writes its own out of whatever body text it liked,
 *                which is where the paragraph about the summit came from.
 *   NO PICTURE   `max-image-preview:none` stops the thumbnail beside the
 *                result. It only affects the PREVIEW: the page's images are
 *                still indexed and can still appear in image search, which
 *                `noimageindex` would be the thing to stop.
 *
 * None of this is binding. A description is a strong hint and is usually used
 * verbatim when it matches what was searched for; a search engine may still
 * write its own if it thinks something on the page answers the query better.
 * The image directive is obeyed.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** The landing page's title is the site's name, full stop. */
add_filter('document_title_parts', 'fc_seo_title_parts');
function fc_seo_title_parts($parts) {
    if (!is_front_page()) return $parts;
    $parts['title'] = get_bloginfo('name');
    unset($parts['tagline'], $parts['site']);
    return $parts;
}

/**
 * Priority 2: before wp_head's own output, so the description sits with the
 * charset and viewport rather than after a stylesheet.
 */
add_action('wp_head', 'fc_seo_meta', 2);
function fc_seo_meta() {
    if (!is_front_page()) return;

    $hero = get_option('fc_section_hero', []);
    $desc = is_array($hero) ? fc_one(fc_bi($hero, 'top_label')) : '';
    if ($desc === '') {
        $desc = '19th Panhellenic FOSS Communities Meeting';
    }

    printf('<meta name="description" content="%s">' . "\n", esc_attr($desc));
    echo '<meta name="robots" content="max-image-preview:none">' . "\n";
}
