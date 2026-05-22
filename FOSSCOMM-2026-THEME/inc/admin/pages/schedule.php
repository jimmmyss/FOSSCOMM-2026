<?php
/**
 * Schedule admin page. Hosts THREE related repeaters in one form:
 *   • fc_section_schedule   bilingual section heading (title_el / title_en)
 *   • fc_schedule_days      list of conference days (key / name / date)
 *   • fc_tracks             list of session categories (slug / name)
 *   • fc_sessions           list of sessions (the main collection)
 *
 * A track or a day has only a bilingual title (+ date for days); its `key`
 * slug is kept in a hidden field so existing session→day / session→track
 * links survive a re-save, and is auto-derived from the title when blank.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_schedule', 20);
function fc_admin_register_schedule() {
    add_submenu_page(FC_ADMIN_SLUG, 'Schedule', '— Schedule', FC_ADMIN_CAP, 'fc_section_schedule', 'fc_admin_page_schedule');
}

/**
 * Default days used on a fresh install / when the option is empty. Also the
 * shape the schedule template falls back to for any session whose day key
 * doesn't match a configured day.
 */
function fc_schedule_default_days(): array {
    return [
        ['key' => 'sat', 'name_el' => 'Σάββατο', 'name_en' => 'Saturday', 'date' => '2026-10-17'],
        ['key' => 'sun', 'name_el' => 'Κυριακή', 'name_en' => 'Sunday',   'date' => '2026-10-18'],
    ];
}

function fc_admin_page_schedule() {
    $title_defaults = [
        'title_el' => 'Πρόγραμμα — δύο μέρες.',
        'title_en' => 'Two days. Four rooms. One weekend.',
    ];

    $days_raw_opt = get_option('fc_schedule_days');
    // Only fall back to defaults when the option has never been saved (false/null).
    // An empty array means the user deleted all days intentionally — respect that.
    if ($days_raw_opt === false || $days_raw_opt === null) {
        $days = fc_schedule_default_days();
    } else {
        $days = is_array($days_raw_opt) ? $days_raw_opt : [];
    }
    $day_options = [];
    foreach ($days as $d) {
        $key = (string) ($d['key'] ?? '');
        if ($key === '') continue;
        $label = (string) ($d['name_en'] ?? $d['name_el'] ?? $key);
        $day_options[$key] = $label;
    }

    $tracks = get_option('fc_tracks', []);
    if (!is_array($tracks)) $tracks = [];
    $track_options = [];
    foreach ($tracks as $t) {
        $slug = (string) ($t['slug'] ?? '');
        if ($slug === '') continue;
        $label = (string) ($t['name_en'] ?? $t['name_el'] ?? $slug);
        $track_options[$slug] = $label;
    }

    $day_fields = [
        'name' => ['type' => 'bilingual', 'label' => 'Day name (e.g. Saturday / Σάββατο)'],
        'date' => ['type' => 'date',      'label' => 'Date'],
        'key'  => ['type' => 'hidden'],
    ];
    $track_fields = [
        'name' => ['type' => 'bilingual', 'label' => 'Track title'],
        'slug' => ['type' => 'hidden'],
    ];

    $fields = [
        'day'     => ['type' => 'select', 'label' => 'Day',  'options' => $day_options],
        'time'    => ['type' => 'text',   'label' => 'Time (e.g. 10:00)'],
        'title'   => ['type' => 'bilingual', 'label' => 'Session title'],
        'speaker' => ['type' => 'text', 'label' => 'Speaker(s)'],
        'room'    => ['type' => 'text', 'label' => 'Room (where the talk happens, e.g. Room 1)'],
        'tracks'  => ['type' => 'multiselect', 'label' => 'Tracks (categories — hold Ctrl/Cmd for multiple)', 'options' => $track_options, 'size' => 6],
        'lang'    => ['type' => 'select', 'label' => 'Language', 'options' => ['GR' => 'GR', 'EN' => 'EN', 'GR/EN' => 'GR/EN']],
        'prereq'  => ['type' => 'bilingual_textarea', 'label' => 'Prerequisites (for workshops)', 'rows' => 3],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_schedule',
        'title'      => 'Schedule',
        'option_key' => 'fc_sessions',
        'intro'      => 'Configure your <strong>Days</strong> (add, rename or remove them — sessions reference whichever days exist) and your <strong>Tracks</strong> (categories), then add sessions and tag each with one day and one or more tracks. With zero days configured, the schedule renders a single "to be announced" message instead of an empty day list.',
        'fields'     => $fields,
        'add_label'  => 'Add session',
        'render_before' => function ($rows) use ($title_defaults, $days, $day_fields, $tracks, $track_fields) {
            fc_section_meta_render('schedule', $title_defaults);

            echo '<hr style="margin:2rem 0;">';
            ?>
            <h2 style="margin-top:0.5rem;">Days</h2>
            <p class="description">Add or remove conference days. Sessions reference them by the bilingual name; the slug is auto-derived from the English name on save. Removing a day will leave any session that referenced it without a day until you re-tag it.</p>
            <?php
            fc_repeater([
                'name'      => 'fc_schedule_days',
                'rows'      => $days,
                'fields'    => $day_fields,
                'add_label' => 'Add day',
            ]);

            echo '<h2 style="margin-top:2.5rem;">Tracks (categories)</h2>';
            echo '<p class="description">The categories you tag sessions with, and that power the schedule filter. Title only — the rest is handled automatically.</p>';
            fc_repeater([
                'name'      => 'fc_tracks',
                'rows'      => $tracks,
                'fields'    => $track_fields,
                'add_label' => 'Add track',
            ]);

            echo '<h2 style="margin-top:2.5rem;">Sessions</h2>';
        },
        'post_process' => function ($clean, $raw) use ($day_fields, $track_fields) {
            fc_section_meta_save('schedule', $raw);

            // Persist Days into fc_schedule_days. Slug derived from English name
            // when blank; falls back to Greek name then to a numeric fallback so
            // every row always has a usable key.
            $days_raw   = isset($raw['fc_schedule_days']) && is_array($raw['fc_schedule_days']) ? $raw['fc_schedule_days'] : [];
            $days_dirty = fc_sanitize_repeater($days_raw, $day_fields);
            $days_clean = [];
            $used_keys  = [];
            foreach ($days_dirty as $i => $row) {
                $name_en = (string) ($row['name_en'] ?? '');
                $name_el = (string) ($row['name_el'] ?? '');
                if ($name_en === '' && $name_el === '') continue;
                $key = sanitize_title((string) ($row['key'] ?? ''));
                if ($key === '') {
                    $key = sanitize_title($name_en !== '' ? $name_en : $name_el);
                }
                if ($key === '') $key = 'day-' . ($i + 1);
                // De-duplicate so two rows with the same name don't collide.
                $base = $key; $n = 2;
                while (isset($used_keys[$key])) { $key = $base . '-' . $n; $n++; }
                $used_keys[$key] = true;
                $days_clean[] = [
                    'key'     => $key,
                    'name_el' => $name_el,
                    'name_en' => $name_en,
                    'date'    => (string) ($row['date'] ?? ''),
                ];
            }
            update_option('fc_schedule_days', $days_clean, false);

            // Persist Tracks (same pattern as Days).
            $tracks_raw   = isset($raw['fc_tracks']) && is_array($raw['fc_tracks']) ? $raw['fc_tracks'] : [];
            $tracks_dirty = fc_sanitize_repeater($tracks_raw, $track_fields);
            $tracks_clean = [];
            foreach ($tracks_dirty as $row) {
                $name_en = (string) ($row['name_en'] ?? '');
                $name_el = (string) ($row['name_el'] ?? '');
                if ($name_en === '' && $name_el === '') continue;
                $slug = sanitize_title((string) ($row['slug'] ?? ''));
                if ($slug === '') $slug = sanitize_title($name_en !== '' ? $name_en : $name_el);
                $tracks_clean[] = [
                    'slug'    => $slug,
                    'name_el' => $name_el,
                    'name_en' => $name_en,
                ];
            }
            update_option('fc_tracks', $tracks_clean, false);

            return $clean; // sessions unchanged → saved to fc_sessions
        },
    ]);
}
