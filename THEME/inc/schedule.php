<?php
/**
 * The schedule, read once and shaped for drawing.
 *
 * The rows are the same three options the dashboard writes (FOSSCOMM →
 * Schedule): `fc_sessions`, `fc_schedule_days` and `fc_tracks`. Every layout in
 * template-parts/sections/schedule.php draws from this one model, so they can
 * be compared on exactly the same programme.
 *
 * A session's END is its own "End time" field when that is filled in and later
 * than the start. When it is not, one is inferred — the rail has to draw a
 * block of some length: until the next session in the same room that day, else
 * an hour, never under 20 minutes, never over two hours.
 *
 * ROOMS are a list of their own (`fc_schedule_rooms`, FOSSCOMM → Schedule →
 * Rooms): each a stable hidden key and a bilingual name. A session stores the
 * KEY, so renaming a room renames it everywhere and breaks no link. The rail
 * shows every room in the list's order, empty ones included. A session whose
 * room is typed text from before the list existed is matched by name instead,
 * and anything matching nothing still gets a row, after the listed ones.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** "10:00" / "9:30" → minutes since midnight, or null. */
function fc_schedule_minutes(string $time): ?int {
    if (!preg_match('/^\s*(\d{1,2})[:.](\d{2})/', $time, $m)) return null;
    $h = (int) $m[1];
    $i = (int) $m[2];
    if ($h > 23 || $i > 59) return null;
    return $h * 60 + $i;
}

function fc_schedule_hhmm(int $minutes): string {
    return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
}

/**
 * @return array{
 *   days: array<string, array{key:string, index:int, name:string, short:string, date:string, iso:string, sessions:array, slots:array}>,
 *   rooms: string[],
 *   tracks: array<string, string>
 * }
 */
function fc_schedule_model(): array {
    $sessions = get_option('fc_sessions', []);
    $days_opt = get_option('fc_schedule_days', []);
    $tracks   = get_option('fc_tracks', []);
    if (!is_array($sessions)) $sessions = [];
    if (!is_array($days_opt)) $days_opt = [];
    if (!is_array($tracks))   $tracks = [];

    $track_names = [];
    foreach ($tracks as $t) {
        $slug = (string) ($t['slug'] ?? '');
        if ($slug !== '') $track_names[$slug] = fc_one(fc_bi($t, 'name'));
    }

    $days  = [];
    $index = 0;
    foreach ($days_opt as $d) {
        if (!is_array($d)) continue;
        $key = (string) ($d['key'] ?? '');
        if ($key === '') continue;
        $iso  = (string) ($d['date'] ?? '');
        $date = '';
        $short = '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso) && ($ts = strtotime($iso)) !== false) {
            $date  = strtoupper(date('d M', $ts));
            $short = strtoupper(date_i18n('D', $ts));
        } else {
            $iso = '';
        }
        $name = trim(fc_pick((string) ($d['name_el'] ?? ''), (string) ($d['name_en'] ?? '')));
        $index++;
        $days[$key] = [
            'key'      => $key,
            'index'    => $index,
            'name'     => $name !== '' ? $name : fc_t('day_label') . ' ' . $index,
            'short'    => $short !== '' ? $short : fc_t('day_label') . ' ' . $index,
            'date'     => $date,
            'iso'      => $iso,
            'sessions' => [],
            'slots'    => [],
        ];
    }

    // The rooms, in the dashboard's order: key => displayed name.
    $room_names = [];
    foreach (fc_schedule_rooms() as $r) {
        $room_names[$r['key']] = fc_one(fc_bi($r, 'name')) ?: $r['key'];
    }
    $by_name = [];
    foreach ($room_names as $k => $n) $by_name[strtolower($n)] = $k;
    $extra = [];

    foreach ($sessions as $s) {
        if (!is_array($s)) continue;
        $day = (string) ($s['day'] ?? '');
        if (!isset($days[$day])) continue;
        $time  = trim((string) ($s['time'] ?? ''));
        $start = fc_schedule_minutes($time);
        $end   = fc_schedule_minutes(trim((string) ($s['end'] ?? '')));
        if ($start === null || $end === null || $end <= $start) $end = null;

        // The room: a key from the list, or legacy text matched by name.
        $raw_room = trim((string) ($s['room'] ?? ''));
        if (isset($room_names[$raw_room])) {
            $room = $room_names[$raw_room];
        } elseif ($raw_room !== '' && isset($by_name[strtolower($raw_room)])) {
            $room = $room_names[$by_name[strtolower($raw_room)]];
        } else {
            $room = $raw_room;
            if ($room !== '' && !in_array($room, $extra, true)) $extra[] = $room;
        }

        $row_tracks = [];
        foreach ((array) ($s['tracks'] ?? []) as $slug) {
            $slug = (string) $slug;
            if (isset($track_names[$slug])) $row_tracks[$slug] = $track_names[$slug];
        }
        $title = fc_one(fc_bi($s, 'title'));
        $days[$day]['sessions'][] = [
            'id'      => substr(md5($day . '|' . $time . '|' . $room . '|' . $title), 0, 10),
            'time'    => $start !== null ? fc_schedule_hhmm($start) : $time,
            'start'   => $start,
            'end'     => $end,
            'end_set' => $end !== null,
            'title'   => $title,
            'speaker' => trim((string) ($s['speaker'] ?? '')),
            'room'    => $room,
            'tracks'  => $row_tracks,
            'lang'    => trim((string) ($s['lang'] ?? '')),
            // Its own colour (FOSSCOMM → Schedule → a session's Colour): the bar
            // down its left side, and the tint of its card and details card.
            'colour'  => (string) (sanitize_hex_color((string) ($s['colour'] ?? '')) ?: FC_OUTLINE_HOVER),
            'prereq'  => fc_one(fc_bi($s, 'prereq')),
        ];
    }
    natcasesort($extra);
    $rooms = array_values(array_merge(array_values($room_names), $extra));

    foreach ($days as &$day) {
        // Timed sessions in order; anything without a readable time goes last.
        usort($day['sessions'], static function ($a, $b) {
            return [$a['start'] === null, $a['start'] ?? 0, $a['room']]
               <=> [$b['start'] === null, $b['start'] ?? 0, $b['room']];
        });
        // The inferred end: see the note at the top of the file.
        foreach ($day['sessions'] as $i => &$s) {
            if ($s['start'] === null || $s['end_set']) continue;
            $next = null;
            foreach ($day['sessions'] as $j => $o) {
                if ($j === $i || $o['start'] === null || $o['start'] <= $s['start']) continue;
                if ($o['room'] !== $s['room']) continue;
                $next = $next === null ? $o['start'] : min($next, $o['start']);
            }
            $len = $next !== null ? $next - $s['start'] : 60;
            $s['end'] = $s['start'] + max(20, min(120, $len));
        }
        unset($s);
        // Time slots: the distinct start times, each with its sessions.
        foreach ($day['sessions'] as $s) {
            $day['slots'][$s['time']][] = $s;
        }
    }
    unset($day);

    return ['days' => $days, 'rooms' => $rooms, 'tracks' => $track_names];
}

/** An ISO local datetime for a day + minutes, for the browser to compare against. */
function fc_schedule_iso(string $date_iso, ?int $minutes): string {
    if ($date_iso === '' || $minutes === null) return '';
    return $date_iso . 'T' . fc_schedule_hhmm($minutes);
}

/**
 * The rooms list, as saved — [['key', 'name_el', 'name_en'], …] in order.
 */
function fc_schedule_rooms(): array {
    $rooms = get_option('fc_schedule_rooms', []);
    if (!is_array($rooms)) return [];
    $out = [];
    foreach ($rooms as $r) {
        if (!is_array($r) || (string) ($r['key'] ?? '') === '') continue;
        $out[] = [
            'key'     => (string) $r['key'],
            'name_el' => (string) ($r['name_el'] ?? ''),
            'name_en' => (string) ($r['name_en'] ?? ''),
        ];
    }
    return $out;
}

/**
 * ONE-TIME: build the rooms list from the rooms sessions were typed with, and
 * point each session at its room's key.
 *
 * Before the list existed a session's room was free text ("Room 1"). The
 * dashboard's room field is a dropdown of the list now, and a dropdown shown a
 * value it does not contain would quietly save its first option over it — so
 * the text has to become keys before anyone opens the page. Runs once, marked
 * by the list option existing at all; a list emptied on purpose stays empty.
 */
add_action('admin_init', 'fc_schedule_migrate_rooms');
add_action('template_redirect', 'fc_schedule_migrate_rooms');
function fc_schedule_migrate_rooms(): void {
    if (get_option('fc_schedule_rooms', null) !== null) return;

    $sessions = get_option('fc_sessions', []);
    if (!is_array($sessions)) $sessions = [];

    $rooms = [];     // key => name
    $keys  = [];     // lower-case name => key
    foreach ($sessions as $s) {
        $name = is_array($s) ? trim((string) ($s['room'] ?? '')) : '';
        if ($name === '' || isset($keys[strtolower($name)])) continue;
        $key = sanitize_title($name) ?: 'room-' . (count($rooms) + 1);
        $base = $key; $n = 2;
        while (isset($rooms[$key])) { $key = $base . '-' . $n; $n++; }
        $rooms[$key] = $name;
        $keys[strtolower($name)] = $key;
    }
    uasort($rooms, 'strnatcasecmp');

    $list = [];
    foreach ($rooms as $key => $name) {
        $list[] = ['key' => $key, 'name_el' => $name, 'name_en' => $name];
    }
    foreach ($sessions as &$s) {
        if (!is_array($s)) continue;
        $name = strtolower(trim((string) ($s['room'] ?? '')));
        if ($name !== '' && isset($keys[$name])) $s['room'] = $keys[$name];
    }
    unset($s);

    update_option('fc_schedule_rooms', $list, false);
    update_option('fc_sessions', $sessions, false);
}

/** A length in minutes as "30′", "1H", "1H30". */
function fc_schedule_duration(int $minutes): string {
    if ($minutes < 60) return $minutes . '′';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . 'H' . ($m ? sprintf('%02d', $m) : '');
}
/**
 * The schedule's look, from FOSSCOMM → Schedule → Look:
 *   tint       how strong every session card's colour is, 0–100 (%) — the
 *              colour itself is each session's own; 30 by default;
 *   scrollbar  the bar under the hours — the accent blue by default.
 */
function fc_schedule_style(): array {
    $saved = get_option('fc_schedule_style', []);
    if (!is_array($saved)) $saved = [];
    $scrollbar = sanitize_hex_color((string) ($saved['scrollbar'] ?? ''));
    $tint = isset($saved['tint']) && $saved['tint'] !== '' && is_numeric($saved['tint']) ? (int) $saved['tint'] : 30;
    return [
        'tint'      => max(0, min(100, $tint)),
        'scrollbar' => $scrollbar ? $scrollbar : FC_OUTLINE_REST,
    ];
}