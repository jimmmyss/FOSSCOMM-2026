<?php
/**
 * FOSSCOMM → Schedule.
 *
 * One form, four tabs, one Save:
 *   SESSIONS  the talks (fc_sessions) — day, start, optional end, room, name,
 *             speaker, language, and anything to prepare. Listed in programme
 *             order, each as a one-line summary that opens to edit.
 *   DAYS      the conference days (fc_schedule_days) — as many as you like.
 *   ROOMS     the rows of the schedule (fc_schedule_rooms), top to bottom in
 *             this order. A session stores its room's hidden key, so renaming a
 *             room renames it everywhere.
 *   LOOK      how strong every session card's colour is, and the scrollbar's
 *             colour (fc_schedule_style). Each session's colour is its own —
 *             the Colour field on the session.
 *
 * Tracks are no longer shown: sessions carry no tags on the site. The old
 * `fc_tracks` option is left untouched in the database.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'fc_admin_register_schedule', 20);
function fc_admin_register_schedule() {
    add_submenu_page(FC_ADMIN_SLUG, 'Schedule', '— Schedule', FC_ADMIN_CAP, 'fc_section_schedule', 'fc_admin_page_schedule');
}

function fc_admin_page_schedule() {
    $days = get_option('fc_schedule_days', []);
    if (!is_array($days)) $days = [];
    $day_options = ['' => '— choose a day —'];
    $day_order   = [];
    foreach ($days as $i => $d) {
        $key = (string) ($d['key'] ?? '');
        if ($key === '') continue;
        $label = trim((string) ($d['name_en'] ?? '')) ?: trim((string) ($d['name_el'] ?? '')) ?: $key;
        if (!empty($d['date'])) $label .= ' · ' . date('j M', strtotime((string) $d['date']));
        $day_options[$key] = $label;
        $day_order[$key]   = $i;
    }

    $rooms = function_exists('fc_schedule_rooms') ? fc_schedule_rooms() : [];
    $room_options = ['' => '— choose a room —'];
    foreach ($rooms as $r) {
        $room_options[$r['key']] = $r['name_en'] !== '' ? $r['name_en'] : ($r['name_el'] !== '' ? $r['name_el'] : $r['key']);
    }

    $style = function_exists('fc_schedule_style') ? fc_schedule_style() : ['tint' => 30, 'scrollbar' => '#0033FF'];

    $day_fields = [
        'name' => ['type' => 'bilingual', 'label' => 'Day name', 'help' => 'e.g. Saturday / Σάββατο'],
        'date' => ['type' => 'date',      'label' => 'Date'],
        'key'  => ['type' => 'hidden'],
    ];
    $room_fields = [
        'name' => ['type' => 'bilingual', 'label' => 'Room name', 'help' => 'e.g. Room 1 / Αίθουσα 1'],
        'key'  => ['type' => 'hidden'],
    ];

    $fields = [
        'title'   => ['type' => 'bilingual', 'label' => 'Session name'],
        'speaker' => ['type' => 'text',   'label' => 'Speaker(s)', 'help' => 'Shown under the name, in the small type.'],
        'day'     => ['type' => 'select', 'label' => 'Day',  'options' => $day_options],
        'room'    => ['type' => 'select', 'label' => 'Room', 'options' => $room_options],
        'time'    => ['type' => 'time',   'label' => 'Starts'],
        'end'     => ['type' => 'time',   'label' => 'Ends', 'help' => 'Optional. Left empty, the session runs until the next one in the same room (or an hour).'],
        'colour'  => ['type' => 'colour', 'label' => 'Colour', 'default' => '#EE8101', 'help' => 'This session\'s colour: the bar down its left side, and the tint of its card. How strong the tint is, is set for all sessions in the Look tab. Leave empty for the house orange.'],
        'lang'    => ['type' => 'select', 'label' => 'Language', 'options' => ['' => '—', 'GR' => 'Greek', 'EN' => 'English', 'GR/EN' => 'Greek & English']],
        'prereq'  => ['type' => 'bilingual_textarea', 'label' => 'Before the session (optional)', 'rows' => 3, 'help' => 'What to bring or install — shown in the session\'s details. Leave empty for talks.'],
    ];

    fc_render_collection_admin_page([
        'slug'       => 'fc_section_schedule',
        'title'      => 'Schedule',
        'option_key' => 'fc_sessions',
        'intro'      => 'Set up the <strong>Days</strong> and <strong>Rooms</strong> first, then add <strong>Sessions</strong> — each one picks its day and room from those lists. Everything is saved together with the button at the bottom.',
        'fields'     => $fields,
        'render_rows' => function ($rows) use ($fields, $days, $day_fields, $rooms, $room_fields, $style, $day_order) {
            // Programme order in the list: by day, then start time.
            usort($rows, static function ($a, $b) use ($day_order) {
                $da = $day_order[(string) ($a['day'] ?? '')] ?? 999;
                $db = $day_order[(string) ($b['day'] ?? '')] ?? 999;
                return [$da, (string) ($a['time'] ?? '')] <=> [$db, (string) ($b['time'] ?? '')];
            });
            ?>
            <nav class="fc-page-tabs" aria-label="Schedule">
                <a href="#" class="active" data-panel="sessions">Sessions<span class="fc-tab-count"><?php echo count($rows); ?></span></a>
                <a href="#" data-panel="days">Days<span class="fc-tab-count"><?php echo count($days); ?></span></a>
                <a href="#" data-panel="rooms">Rooms<span class="fc-tab-count"><?php echo count($rooms); ?></span></a>
                <a href="#" data-panel="look">Look</a>
            </nav>

            <div class="fc-page-panel active" data-panel="sessions">
                <p class="fc-panel-help">Every talk and workshop. Click a line to open it; drag ⋮⋮ to reorder (the site always shows them by time anyway). <strong>Duplicate</strong> is the quickest way to add a session like an existing one.</p>
                <?php if (!$days || !$rooms) : ?>
                    <div class="notice notice-warning inline"><p>Add at least one <strong>Day</strong> and one <strong>Room</strong> (tabs above) and save, so sessions have something to pick from.</p></div>
                <?php endif; ?>
                <?php fc_repeater([
                    'name'      => 'fc_rows',
                    'rows'      => $rows,
                    'fields'    => $fields,
                    'add_label' => '+ Add session',
                    'summary'   => ['day', 'time', 'room', 'title'],
                    'item'      => 'session',
                ]); ?>
            </div>

            <div class="fc-page-panel" data-panel="days">
                <p class="fc-panel-help">The days of the conference, in order — as many as you need. They appear as the day bar above the schedule. Removing a day leaves its sessions without one until you give them another.</p>
                <?php fc_repeater([
                    'name'      => 'fc_schedule_days',
                    'rows'      => $days,
                    'fields'    => $day_fields,
                    'add_label' => '+ Add day',
                    'summary'   => ['date', 'name'],
                    'item'      => 'day',
                ]); ?>
            </div>

            <div class="fc-page-panel" data-panel="rooms">
                <p class="fc-panel-help">The rows of the schedule, top to bottom in this order — drag ⋮⋮ to reorder. Rename a room here and every session in it follows. A room with no sessions still shows as an empty row. <strong>After adding a room, save once</strong> so it appears in the sessions' Room list.</p>
                <?php fc_repeater([
                    'name'      => 'fc_schedule_rooms',
                    'rows'      => $rooms,
                    'fields'    => $room_fields,
                    'add_label' => '+ Add room',
                    'summary'   => ['name'],
                    'item'      => 'room',
                ]); ?>
            </div>

            <div class="fc-page-panel" data-panel="look">
                <p class="fc-panel-help">Settings for the whole schedule. Each session's own colour is on the session itself (Sessions tab → open a session → Colour). Changes show on the site after you save.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="fc-sx-tint">Card strength</label></th>
                        <td>
                            <input type="range" id="fc-sx-tint" name="fc_schedule_style[tint]" min="0" max="100" step="1"
                                   value="<?php echo (int) $style['tint']; ?>"
                                   oninput="this.nextElementSibling.textContent = this.value + '%'"
                                   style="width:280px;vertical-align:middle;">
                            <output style="margin-left:10px;font-family:ui-monospace,Menlo,monospace;"><?php echo (int) $style['tint']; ?>%</output>
                            <p class="description">How much of each session's colour fills its card (and its details card) — 0% is plain paper, 100% the full colour. The card gets 10% more under the pointer. Default: 30%.</p>
                        </td>
                    </tr>
                    <?php fc_admin_colour_field('fc_schedule_style[scrollbar]', (string) $style['scrollbar'], 'Scrollbar colour', 'The bar under the hours that shows where you are in the day. Default: the accent blue #0033FF.'); ?>
                </table>
                <?php fc_admin_colour_sync_script(); ?>
            </div>
            <?php
        },
        'post_process' => function ($clean, $raw) use ($day_fields, $room_fields) {
            // Days. The key is kept from the hidden field so sessions stay
            // attached; a new day gets one from its English (or Greek) name.
            $days_dirty = fc_sanitize_repeater(is_array($raw['fc_schedule_days'] ?? null) ? $raw['fc_schedule_days'] : [], $day_fields);
            $days_clean = [];
            $used = [];
            foreach ($days_dirty as $i => $row) {
                $name_en = (string) ($row['name_en'] ?? '');
                $name_el = (string) ($row['name_el'] ?? '');
                if ($name_en === '' && $name_el === '') continue;
                $key = sanitize_title((string) ($row['key'] ?? '')) ?: sanitize_title($name_en !== '' ? $name_en : $name_el) ?: 'day-' . ($i + 1);
                $base = $key; $n = 2;
                while (isset($used[$key])) { $key = $base . '-' . $n; $n++; }
                $used[$key] = true;
                $days_clean[] = ['key' => $key, 'name_el' => $name_el, 'name_en' => $name_en, 'date' => (string) ($row['date'] ?? '')];
            }
            update_option('fc_schedule_days', $days_clean, false);

            // Rooms, the same way.
            $rooms_dirty = fc_sanitize_repeater(is_array($raw['fc_schedule_rooms'] ?? null) ? $raw['fc_schedule_rooms'] : [], $room_fields);
            $rooms_clean = [];
            $used = [];
            foreach ($rooms_dirty as $i => $row) {
                $name_en = (string) ($row['name_en'] ?? '');
                $name_el = (string) ($row['name_el'] ?? '');
                if ($name_en === '' && $name_el === '') continue;
                $key = sanitize_title((string) ($row['key'] ?? '')) ?: sanitize_title($name_en !== '' ? $name_en : $name_el) ?: 'room-' . ($i + 1);
                $base = $key; $n = 2;
                while (isset($used[$key])) { $key = $base . '-' . $n; $n++; }
                $used[$key] = true;
                $rooms_clean[] = ['key' => $key, 'name_el' => $name_el, 'name_en' => $name_en];
            }
            update_option('fc_schedule_rooms', $rooms_clean, false);

            // The look: card strength (0–100) and the scrollbar's colour.
            $tint_raw  = $raw['fc_schedule_style']['tint'] ?? '';
            $scrollbar = sanitize_hex_color(trim((string) ($raw['fc_schedule_style']['scrollbar'] ?? '')));
            update_option('fc_schedule_style', [
                'tint'      => is_numeric($tint_raw) ? max(0, min(100, (int) $tint_raw)) : 30,
                'scrollbar' => $scrollbar ? $scrollbar : '',
            ], false);

            // Sessions with neither a name nor a time are empty forms, not sessions.
            return array_values(array_filter($clean, static function ($s) {
                return trim((string) ($s['title_en'] ?? '')) !== '' || trim((string) ($s['title_el'] ?? '')) !== '' || trim((string) ($s['time'] ?? '')) !== '';
            }));
        },
    ]);
}
