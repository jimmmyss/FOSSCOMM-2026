<?php
/**
 * Canonical registry of section types.
 * Adding a new section = add an entry here + drop a template-parts/sections/<key>.php + (optional) an admin page.
 */
if (!defined('ABSPATH')) {
    exit;
}

function fc_section_registry(): array {
    static $registry = null;
    if ($registry !== null) {
        return $registry;
    }

    /**
     * Each entry:
     *   key            stable identifier; matches template-parts/sections/<key>.php
     *   label_el/en    label shown in the section nav and admin
     *   eyebrow        small numbered prefix like "01 / Manifesto" — bilingual default
     *   option_key     wp_options row that stores this section's editable copy (for static sections)
     *   has_admin_page if true, the section has its own dedicated admin sub-page
     *   collection     if true, the section reads from a list option (admin page is a CRUD UI)
     *   default_active whether the section is enabled on a fresh install
     *   default_order  initial menu order on a fresh install
     *   in_nav         show in the left-rail nav
     */
    $registry = [
        'hero' => [
            'key'            => 'hero',
            'label_el'       => 'Home',
            'label_en'       => 'Home',
            'eyebrow_el'     => '00 / Home',
            'eyebrow_en'     => '00 / Home',
            'option_key'     => 'fc_section_hero',
            'has_admin_page' => true,
            'collection'     => false,
            'default_active' => true,
            'default_order'  => 10,
            'in_nav'         => true,
        ],
        'manifesto' => [
            'key'            => 'manifesto',
            'label_el'       => 'Μανιφέστο',
            'label_en'       => 'Manifesto',
            'eyebrow_el'     => '01 / Μανιφέστο',
            'eyebrow_en'     => '01 / Manifesto',
            'option_key'     => 'fc_section_manifesto',
            'has_admin_page' => true,
            'collection'     => false,
            'default_active' => true,
            'default_order'  => 20,
            'in_nav'         => true,
        ],
        'speakers' => [
            'key'            => 'speakers',
            'label_el'       => 'Ομιλητές',
            'label_en'       => 'Speakers',
            'eyebrow_el'     => '02 / Ομιλητές',
            'eyebrow_en'     => '02 / Speakers',
            'option_key'     => 'fc_speakers',
            'has_admin_page' => true,
            'collection'     => true,
            'default_active' => true,
            'default_order'  => 30,
            'in_nav'         => true,
        ],
        'schedule' => [
            'key'            => 'schedule',
            'label_el'       => 'Πρόγραμμα',
            'label_en'       => 'Schedule',
            'eyebrow_el'     => '03 / Πρόγραμμα',
            'eyebrow_en'     => '03 / Schedule',
            'option_key'     => 'fc_sessions',
            'has_admin_page' => true,
            'collection'     => true,
            'default_active' => true,
            'default_order'  => 40,
            'in_nav'         => true,
        ],
        'news' => [
            'key'            => 'news',
            'label_el'       => 'Νέα',
            'label_en'       => 'News',
            'eyebrow_el'     => '04 / Νέα',
            'eyebrow_en'     => '04 / News',
            'option_key'     => 'fc_news',
            'has_admin_page' => true,
            'collection'     => true,
            'default_active' => true,
            'default_order'  => 45,
            'in_nav'         => true,
        ],
        'venue' => [
            'key'            => 'venue',
            'label_el'       => 'Χώρος',
            'label_en'       => 'Venue',
            'eyebrow_el'     => '05 / Χώρος',
            'eyebrow_en'     => '05 / Venue',
            'option_key'     => 'fc_section_venue',
            'has_admin_page' => true,
            'collection'     => false,
            'default_active' => true,
            'default_order'  => 50,
            'in_nav'         => true,
        ],
        'sponsors' => [
            'key'            => 'sponsors',
            'label_el'       => 'Χορηγοί',
            'label_en'       => 'Sponsors',
            'eyebrow_el'     => '06 / Χορηγοί',
            'eyebrow_en'     => '06 / Sponsors',
            'option_key'     => 'fc_sponsors',
            'has_admin_page' => true,
            'collection'     => true,
            'default_active' => true,
            'default_order'  => 60,
            'in_nav'         => true,
        ],
        // NOTE: the old `past_editions` section was removed — its data now lives in the
        // Venue section's "Editions" repeater (fc_section_venue.editions). Existing
        // installs are migrated by fc_migrate_past_editions_into_venue() in inc/seed.php.
        'volunteer' => [
            'key'            => 'volunteer',
            'label_el'       => 'Πάρε Μέρος',
            'label_en'       => 'Get Involved',
            'eyebrow_el'     => '07 / Πάρε Μέρος',
            'eyebrow_en'     => '07 / Get Involved',
            'option_key'     => 'fc_section_volunteer',
            'has_admin_page' => true,
            'collection'     => false,
            'default_active' => true,
            'default_order'  => 80,
            'in_nav'         => true,
        ],
        'faq' => [
            'key'            => 'faq',
            'label_el'       => 'Συχνές Ερωτήσεις',
            'label_en'       => 'FAQ',
            'eyebrow_el'     => '08 / Συχνές Ερωτήσεις',
            'eyebrow_en'     => '08 / FAQ',
            'option_key'     => 'fc_faq',
            'has_admin_page' => true,
            'collection'     => true,
            'default_active' => true,
            'default_order'  => 90,
            'in_nav'         => true,
        ],
        'footer' => [
            'key'            => 'footer',
            'label_el'       => 'Footer',
            'label_en'       => 'Footer',
            'eyebrow_el'     => '',
            'eyebrow_en'     => '',
            'option_key'     => 'fc_section_footer',
            'has_admin_page' => true,
            'collection'     => false,
            'default_active' => true,
            'default_order'  => 100,
            'in_nav'         => false,
        ],
    ];

    return apply_filters('fc_section_registry', $registry);
}

function fc_section_def(string $key): ?array {
    $reg = fc_section_registry();
    return $reg[$key] ?? null;
}

/**
 * Section names (used in the left-rail nav and eyebrow strips) are English-only.
 * Per-section body content remains bilingual.
 */
function fc_section_label(array $def): string {
    $en = (string) ($def['label_en'] ?? '');
    return $en !== '' ? $en : (string) ($def['label_el'] ?? '');
}

function fc_section_eyebrow(array $def): string {
    $en = (string) ($def['eyebrow_en'] ?? '');
    return $en !== '' ? $en : (string) ($def['eyebrow_el'] ?? '');
}
