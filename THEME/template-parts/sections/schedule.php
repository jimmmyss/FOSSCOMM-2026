<?php
/**
 * Schedule — list of sessions stored in option `fc_sessions`.
 * Each session: day (key from fc_schedule_days), time, room, track (slug of a
 * track in fc_tracks), title (bilingual), speaker (text), lang (GR|EN|GR/EN),
 * prereq.
 *
 * Days are dynamic: defined in `fc_schedule_days` via the schedule admin page.
 * The empty-days case renders a single TBA message (not one per day).
 *
 * Section is opened manually (not via fc_section_open) so the filter bar can sit
 * INSIDE the <section> but OUTSIDE the max-w container — identical full-bleed,
 * sticky treatment to the venue editions bar.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section  = $args['section'] ?? [];
$sessions = fc_section_data($section);
$tracks   = get_option('fc_tracks', []);
if (!is_array($tracks)) $tracks = [];
$days_opt = get_option('fc_schedule_days', []);
if (!is_array($days_opt)) $days_opt = [];

$track_label = [];
foreach ($tracks as $t) {
    $slug = (string) ($t['slug'] ?? '');
    if ($slug !== '') {
        $track_label[$slug] = fc_bi($t, 'name');
    }
}

// Build the ordered day list. Each entry: key + bilingual name + a formatted
// date stamp ("17 OCT 2026"). Sessions whose `day` key doesn't match any
// configured day are silently dropped (the rooms filter still considers them
// so changing days never removes a room from the dropdown).
$days = [];
foreach ($days_opt as $d) {
    if (!is_array($d)) continue;
    $key = (string) ($d['key'] ?? '');
    if ($key === '') continue;
    $date_iso = (string) ($d['date'] ?? '');
    $date_lbl = '';
    if ($date_iso !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_iso)) {
        $ts = strtotime($date_iso);
        if ($ts !== false) $date_lbl = strtoupper(date('d M Y', $ts));
    }
    $days[$key] = [
        'key'  => $key,
        'el'   => (string) ($d['name_el'] ?? ''),
        'en'   => (string) ($d['name_en'] ?? ''),
        'date' => $date_lbl,
    ];
}

$by_day = [];
foreach ($days as $k => $_d) $by_day[$k] = [];
$rooms  = [];
foreach ($sessions as $s) {
    if (!is_array($s)) continue;
    $day = (string) ($s['day'] ?? '');
    if ($day !== '' && isset($by_day[$day])) {
        $by_day[$day][] = $s;
    }
    $room = trim((string) ($s['room'] ?? ''));
    if ($room !== '' && !in_array($room, $rooms, true)) {
        $rooms[] = $room;
    }
}
natcasesort($rooms);
$rooms = array_values($rooms);

$meta = fc_section_meta('schedule', [
    'title_el' => 'Πρόγραμμα — δύο μέρες.',
    'title_en' => 'Two days. Four rooms. One weekend.',
]);
$title      = fc_pick($meta['title_el'], $meta['title_en']);
$id         = (string) $section['key'];
$eyebrow    = fc_section_eyebrow($section);

// The first configured day is the one shown by default (others hidden until
// the day filter selects them). Empty days list → no day blocks rendered at
// all; we fall through to a single TBA below the title.
$default_day_key = '';
foreach ($days as $k => $_d) { $default_day_key = $k; break; }
?>
<section id="<?php echo esc_attr($id); ?>" class="bg-paper relative border-t border-border" <?php echo fc_island_attrs('schedule-filter'); ?>>
    <!-- Filter bar — same hooks (data-fc-filter / fc-sched-select) and same
         sticky offsets as the venue editions bar (top-10, h-[41px], 1px bleed
         into the section border). Don't rename the data attributes; fc.js binds
         to them by exact selector. -->
    <nav
        aria-label="Schedule filters"
        class="
            sticky fc-bar-sub z-30 fc-bar
            bg-paper border-b border-border
            font-mono text-[11px] uppercase tracking-widest text-ink-muted
            overflow-x-auto whitespace-nowrap fc-nav-no-scrollbar
            flex items-center gap-4 px-4
        "
    >
        <select data-fc-filter="day" class="fc-sched-select" aria-label="Day">
            <option value=""><?php echo esc_html(fc_t('filter_day')); ?></option>
            <?php foreach ($days as $dkey => $dmeta) :
                // Filter-dropdown label: "<day name> · <date>" in the active language
                // (or just one of them if the other is empty).
                $label = trim(fc_pick($dmeta['el'], $dmeta['en']));
                if ($dmeta['date'] !== '') {
                    $label = $label !== '' ? $label . ' · ' . $dmeta['date'] : $dmeta['date'];
                }
                if ($label === '') continue;
                ?>
                <option value="<?php echo esc_attr($dkey); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="opacity-50">//</span>
        <select data-fc-filter="room" class="fc-sched-select" aria-label="Room">
            <option value=""><?php echo esc_html(fc_t('filter_room')); ?></option>
            <?php foreach ($rooms as $room_opt) : ?>
                <option value="<?php echo esc_attr($room_opt); ?>"><?php echo esc_html($room_opt); ?></option>
            <?php endforeach; ?>
        </select>
        <span class="opacity-50">//</span>
        <select data-fc-filter="track" class="fc-sched-select" aria-label="Category">
            <option value=""><?php echo esc_html(fc_t('filter_category')); ?></option>
            <?php foreach ($track_label as $slug => $name) : ?>
                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html(fc_one($name)); ?></option>
            <?php endforeach; ?>
        </select>
    </nav>

    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-40">
        <?php if ($eyebrow !== '') : ?>
            <div class="font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-6">
                <?php echo esc_html($eyebrow); ?>
            </div>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <h2 class="font-display text-4xl md:text-6xl leading-[1.0] tracking-tight mb-16"><?php echo fc_format($title); ?></h2>
        <?php endif; ?>

        <?php if (empty($days)) : ?>
            <?php // No days configured — single TBA, not one per day. ?>
            <?php fc_render_tba('schedule'); ?>
        <?php endif; ?>

        <?php $day_index = 0; foreach ($by_day as $day_key => $rows) :
            $day = $days[$day_key];
            $day_index++;
            // Outer wrapper carries data-fc-day-list so the day filter (in fc.js)
            // can hide the whole day — header + rows — in one toggle.
            // Eyebrow uses the position in the days repeater ("Day 1", "Day 2")
            // rather than the slug, so arbitrary keys like "day-1" don't surface
            // in the chrome as "DAY DAY-1".
            ?>
            <div class="mb-16 last:mb-0" data-fc-day-list="<?php echo esc_attr($day_key); ?>" <?php echo $day_key === $default_day_key ? '' : 'hidden'; ?>>
                <!-- Day header: brutalist mono band on top, display name underneath. -->
                <div class="border-t-2 border-ink pt-4 mb-6">
                    <div class="flex items-baseline justify-between gap-4 font-mono text-[11px] uppercase tracking-widest text-ink-muted mb-2">
                        <span><?php echo esc_html(sprintf('%s %d', fc_t('day_label'), $day_index)); ?></span>
                        <span class="tabular-nums"><?php echo esc_html($day['date']); ?></span>
                    </div>
                    <div class="flex items-baseline gap-4 flex-wrap">
                        <?php $day_name = fc_pick($day['el'], $day['en']); if ($day_name !== '') : ?>
                            <h3 class="font-display text-4xl md:text-6xl leading-[0.95] tracking-tight m-0"><?php echo esc_html($day_name); ?></h3>
                        <?php endif; ?>
                    </div>
                </div>

                <ul class="list-none p-0 m-0 border-t border-border">
                    <?php foreach ($rows as $s) :
                        $title  = fc_bi($s, 'title');
                        $prereq = fc_bi($s, 'prereq');
                        $time   = (string) ($s['time']    ?? '');
                        $speaker = (string) ($s['speaker'] ?? '');
                        $lang   = (string) ($s['lang']    ?? '');
                        $room   = trim((string) ($s['room'] ?? ''));
                        $row_tracks = (array) ($s['tracks'] ?? []);
                        $track_attr = implode(' ', array_map('sanitize_html_class', $row_tracks));
                        ?>
                        <li class="fc-session border-b border-border"
                            data-fc-session
                            data-fc-room="<?php echo esc_attr($room); ?>"
                            data-fc-tracks="<?php echo esc_attr($track_attr); ?>">
                            <div class="grid grid-cols-12 gap-4 py-6">
                                <!-- Time + room: big mono-feel display number, room as a small chip. -->
                                <div class="col-span-3 md:col-span-2 flex flex-col">
                                    <div class="fc-session-time font-display text-2xl md:text-4xl leading-none tracking-tight tabular-nums text-ink"><?php echo esc_html($time); ?></div>
                                    <?php if ($room !== '') : ?>
                                        <div class="mt-3">
                                            <span class="inline-block font-mono text-[10px] uppercase tracking-widest text-ink-muted border border-border px-1.5 py-0.5 leading-none"><?php echo esc_html($room); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Title + speaker. -->
                                <div class="col-span-9 md:col-span-7 min-w-0">
                                    <?php $stitle = fc_one($title); ?>
                                    <div class="font-display text-xl md:text-2xl leading-tight"><?php echo fc_format($stitle); ?></div>
                                    <?php if ($speaker !== '') : ?>
                                        <div class="mt-3 font-mono text-xs text-ink-muted"><?php echo fc_format($speaker); ?></div>
                                    <?php endif; ?>
                                </div>
                                <!-- Tracks (chips) + language. -->
                                <div class="col-span-12 md:col-span-3 flex flex-col gap-2 md:items-end self-start">
                                    <?php if (!empty($row_tracks)) : ?>
                                        <div class="flex flex-wrap md:justify-end gap-1.5">
                                            <?php foreach ($row_tracks as $tslug) :
                                                if (!isset($track_label[$tslug])) continue;
                                                $tname = fc_one($track_label[$tslug]);
                                                ?>
                                                <span class="font-mono text-[10px] uppercase tracking-widest text-ink-muted border border-border px-1.5 py-0.5 leading-none"><?php echo esc_html($tname); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($lang !== '') : ?>
                                        <div class="font-mono text-[10px] uppercase tracking-widest text-ink-muted"><?php echo esc_html($lang); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $prereq_text = fc_one($prereq); if ($prereq_text !== '') : ?>
                                <div class="pb-6 pl-[calc(25%+1rem)] md:pl-[16.66%]">
                                    <div class="font-mono text-[10px] uppercase tracking-widest text-accent mb-1"><?php echo esc_html(fc_t('before_workshop')); ?></div>
                                    <p class="text-sm text-ink-muted leading-relaxed max-w-2xl"><?php echo esc_html($prereq_text); ?></p>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($rows)) : ?>
                        <li class="border-b border-border"><?php fc_render_tba('schedule'); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div><!-- end .max-w container -->
</section>

<style>
/* Native dropdowns in the filter bar — read as plain bar text (no border, no arrow);
   clicking opens the OS/browser picker. The chosen option replaces the segment label. */
.fc-sched-select {
    appearance: none;
    -webkit-appearance: none;
    background: transparent;
    border: 0;
    margin: 0;
    padding: 0;
    font: inherit;
    text-transform: uppercase;
    letter-spacing: inherit;
    color: var(--ink-muted);            /* grey, same as the other bars */
    cursor: pointer;
    /* Shared bar-item hover speed — matches .fc-year-btn, .fc-nav-link, the
       topbar brand. Snappy (~80ms) so the colour change feels instantaneous
       without being a hard step. */
    transition: color 80ms ease;
}
.fc-sched-select::-ms-expand { display: none; }
.fc-sched-select:hover { color: var(--accent); }  /* blue on hover */
.fc-sched-select:focus { outline: none; }          /* back to grey after selecting */
.fc-sched-select option { color: var(--ink); text-transform: none; letter-spacing: normal; }

/* Row hover: faint accent wash and the big time number turns accent — only the
   time, so the title stays readable while still giving a clear cue. */
.fc-session { transition: background-color 200ms ease; }
.fc-session:hover { background-color: color-mix(in oklab, var(--color-accent, #0033FF) 3%, transparent); }
.fc-session:hover .fc-session-time { color: var(--color-accent, #0033FF); transition: color 200ms ease; }

@media (prefers-reduced-motion: reduce) {
    .fc-session  { transition: none; }
}
</style>
