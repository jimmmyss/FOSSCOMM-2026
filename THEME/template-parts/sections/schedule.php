<?php
/**
 * Schedule — the day laid out along a time line, filling the section.
 *
 * TIME RUNS ACROSS, ROOMS RUN DOWN: one row per room, each session a block as
 * long as it lasts. The field is striped grey and white hour by hour, so an
 * hour reads as a column all the way down, and each session is a light tint
 * with a solid edge where it begins.
 *
 * Two bars on top, both in the site's bar face and both locked under the site's
 * own bar while the section scrolls past:
 *   the DAYS    one per day, as many as the dashboard has;
 *   the HOURS   the time line itself. It is not inside the rail's sideways
 *               scroller — a sticky element cannot stick inside one — so it is
 *               its own bar that assets/schedule.js keeps in step with the rail.
 * Switching day keeps the hour you were looking at.
 *
 * Rooms come from the dashboard's Rooms list, in its order, and can be renamed
 * there freely; every room is a row, empty ones included. A session's end is
 * its own End time when set. (inc/schedule.php)
 *
 * A CARD fills its slot exactly — the whole height of its room's row, from the
 * minute it starts to the minute it ends, with no air around it — and reads, top
 * to bottom: the talk's name, then at the foot its length ("1H", "30′") and its
 * language. Everything else (time, speaker, what to prepare) is in the details
 * card. Sessions carry no track tags here.
 * The rail is dragged sideways with a mouse (the hand closes on the press),
 * swiped on a phone, and has no scrollbar. Tapping a session opens its details
 * in a <dialog>. (assets/schedule.js)
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$model   = fc_schedule_model();
$days    = $model['days'];
$rooms   = $model['rooms'] ?: [''];
$has     = false;
foreach ($days as $d) { if ($d['sessions']) { $has = true; break; } }

$before = fc_t('before_workshop');

/** A session's language code(s) for the card, several joined with "&":
    "GR/EN" → "GR & EN". */
$lang_codes = static function (string $code): string {
    $parts = preg_split('/\s*[\/,&+]\s*/', strtoupper(trim($code)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return implode(' & ', $parts);
};

/** A session's language code(s) spelled out — "GR" → "Greek", and several
    joined with "&": "GR/EN" → "Greek & English". Unknown codes pass through. */
$lang_names = static function (string $code): string {
    $names = [
        'GR' => fc_pick('Ελληνικά', 'Greek'),
        'EL' => fc_pick('Ελληνικά', 'Greek'),
        'EN' => fc_pick('Αγγλικά', 'English'),
    ];
    $parts = preg_split('/\s*[\/,&+]\s*/', strtoupper(trim($code)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return implode(' & ', array_map(static function ($c) use ($names) { return $names[$c] ?? $c; }, $parts));
};

/** "Speaker · GR", whichever parts exist — for the details card. */
$meta = static function (array $s): string {
    $parts = [];
    if ($s['speaker'] !== '') $parts[] = fc_format($s['speaker']);
    if ($s['lang'] !== '') $parts[] = esc_html($s['lang']);
    return implode('<span class="fc-sx-dot" aria-hidden="true"> · </span>', $parts);
};
?>
<?php
// The card strength and the scrollbar colour, from FOSSCOMM → Schedule → Look.
$sx_style = fc_schedule_style();
?>
<section id="<?php echo esc_attr((string) ($section['key'] ?? 'schedule')); ?>"
         class="bg-paper relative border-t border-border fc-sx" data-fc-sx
         style="--fc-sx-tint: <?php echo (int) $sx_style['tint']; ?>%; --fc-sx-tint-hover: <?php echo (int) min(100, $sx_style['tint'] + 10); ?>%; --fc-sx-scrollbar: <?php echo esc_attr($sx_style['scrollbar']); ?>;">

    <?php if (!$has) :
        /* Empty: an ordinary section — the "04 / Schedule" eyebrow, as every
           other section has, over the "to be announced" line. Once there is a
           session the timeline fills the section and the eyebrow goes. */
        $eyebrow = fc_section_eyebrow($section);
        ?>
        <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-24 md:py-40">
            <?php if ($eyebrow !== '') : ?>
                <div class="fc-eyebrow fc-label text-ink-muted mb-6"><?php echo fc_eyebrow_html($eyebrow); ?></div>
            <?php endif; ?>
            <?php fc_render_tba('schedule'); ?>
        </div>
    <?php else : ?>

    <?php /* ── The days ─────────────────────────────────────────────────── */ ?>
    <div class="fc-sx-days fc-bar fc-label" role="tablist" aria-label="<?php echo esc_attr(fc_t('filter_day')); ?>">
        <?php /* One line of text, as the site's top bar is: the days separated
                 by plain spaces and a full-strength `//`. */ ?>
        <span class="fc-sx-days-list"><?php $first = true; foreach ($days as $d) :
            if (!$first) echo ' <span aria-hidden="true">//</span> ';
            ?><button type="button" role="tab" class="fc-sx-day<?php echo $first ? ' is-on' : ''; ?>"
                    aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
                    data-fc-sx-day="<?php echo esc_attr($d['key']); ?>"
                    data-fc-sx-date="<?php echo esc_attr($d['iso']); ?>"><?php
                echo '<span class="fc-sx-day-name">' . esc_html($d['name']) . '</span>';
                if ($d['date'] !== '') echo ' <span class="fc-sx-day-sep">/</span> <span class="fc-sx-day-date">' . esc_html($d['date']) . '</span>';
            ?></button><?php $first = false; endforeach; ?></span>

    </div>

    <?php /* ── One panel per day: its hour bar, then its rail ─────────────── */ ?>
    <?php $first = true; foreach ($days as $d) :
        $starts = array_filter(array_column($d['sessions'], 'start'), 'is_int');
        $ends   = array_filter(array_column($d['sessions'], 'end'), 'is_int');
        $from   = $starts ? intdiv(min($starts), 60) * 60 : 9 * 60;
        $to     = $ends ? (int) ceil(max($ends) / 60) * 60 : 18 * 60;
        $span   = max(60, $to - $from);
        ?>
        <div class="fc-sx-panel" data-fc-sx-panel="<?php echo esc_attr($d['key']); ?>"
             data-from="<?php echo (int) $from; ?>" data-to="<?php echo (int) $to; ?>"
             style="--fc-sx-mins: <?php echo (int) $span; ?>;"<?php echo $first ? '' : ' hidden'; ?>>

            <div class="fc-sx-hours fc-bar fc-label" data-fc-sx-hours aria-hidden="true">
                <div class="fc-sx-hours-corner"></div>
                <div class="fc-sx-hours-track">
                    <div class="fc-sx-hours-inner" data-fc-sx-hours-inner>
                        <?php /* One box per half hour, its time centred in it. */
                        for ($m = $from; $m < $to; $m += 30) : ?>
                            <span class="fc-sx-tick<?php echo $m % 60 ? ' is-half' : ''; ?>" style="--at: <?php echo $m - $from; ?>;"><?php echo esc_html(fc_schedule_hhmm($m)); ?></span>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php /* The scrollbar: a thumb riding the bar's bottom edge, as long
                         as the share of the day on screen and as far along as the
                         view is. Shown only when the day is wider than the screen;
                         it can be dragged. (assets/schedule.js) */ ?>
                <div class="fc-sx-scrollbar" data-fc-sx-scrollbar hidden>
                    <span class="fc-sx-thumb" data-fc-sx-thumb></span>
                </div>
            </div>

            <?php /* assets/schedule.js sets data-fc-grab while the rail can be
                     dragged and data-fc-grabbing while it is held: the
                     attributes the custom cursor CSS in inc/bootstrap.php already
                     dresses, so an uploaded hand shows here as on the mascot. */ ?>
            <div class="fc-sx-rail-scroll" data-fc-sx-scroll>
                <div class="fc-sx-rail" data-fc-sx-rail data-date="<?php echo esc_attr($d['iso']); ?>">
                    <?php foreach ($rooms as $r) : ?>
                        <div class="fc-sx-room fc-label"><?php echo esc_html($r); ?></div>
                        <div class="fc-sx-lane">
                            <?php foreach ($d['sessions'] as $s) :
                                if ($s['start'] === null || ($r !== '' && $s['room'] !== $r)) continue;
                                $span_label = $s['time'] . '–' . fc_schedule_hhmm($s['end']);
                                // A short talk: tighter padding; anything too long for it is cut with an ellipsis.
                                $short = ($s['end'] - $s['start']) <= 35;
                                ?>
                                <button type="button" class="fc-sx-block<?php echo $short ? ' is-short' : ''; ?>" data-fc-sx-open data-fc-sx-when
                                        data-start="<?php echo esc_attr(fc_schedule_iso($d['iso'], $s['start'])); ?>"
                                        data-end="<?php echo esc_attr(fc_schedule_iso($d['iso'], $s['end'])); ?>"
                                        style="--at: <?php echo $s['start'] - $from; ?>; --len: <?php echo $s['end'] - $s['start']; ?>; --tone: <?php echo esc_attr($s['colour']); ?>;">
                                    <span class="fc-sx-title"><?php echo fc_format($s['title']); ?></span>
                                    <?php if ($s['speaker'] !== '') : ?>
                                        <span class="fc-sx-b-speaker fc-label"><?php echo fc_format($s['speaker']); ?></span>
                                    <?php endif; ?>
                                    <?php /* The length in minutes, then the language: "60′ / GR". */ ?>
                                    <span class="fc-sx-b-foot fc-label"><?php
                                        echo esc_html(($s['end'] - $s['start']) . '′');
                                        if ($s['lang'] !== '') echo ' / ' . esc_html($lang_codes($s['lang']));
                                    ?></span>

                                    <?php /* The details card's copy. */ ?>
                                    <template data-fc-sx-detail>
                                        <h3 class="fc-sx-sheet-title"><?php echo fc_format($s['title']); ?></h3>
                                        <?php if ($s['speaker'] !== '') : ?>
                                            <p class="fc-sx-sheet-speaker fc-label"><?php echo fc_format($s['speaker']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($s['prereq'] !== '') : ?>
                                            <div class="fc-sx-prereq">
                                                <p class="fc-label"><?php echo esc_html($before); ?></p>
                                                <p><?php echo esc_html($s['prereq']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        <p class="fc-sx-sheet-where fc-label"><?php
                                            echo esc_html(implode(' / ', array_filter([
                                                $s['time'] . ' - ' . fc_schedule_hhmm($s['end']),
                                                $d['name'],
                                                $d['date'],
                                                $s['room'],
                                                $lang_names($s['lang']),
                                            ], 'strlen')));
                                        ?></p>                                    </template>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="fc-sx-nowline" data-fc-sx-nowline hidden aria-hidden="true"></div>
                </div>
            </div>
        </div>
    <?php $first = false; endforeach; ?>

    <dialog class="fc-sx-sheet" data-fc-sx-sheet aria-label="<?php echo esc_attr(fc_pick('Λεπτομέρειες ομιλίας', 'Talk details')); ?>">
        <?php /* The way back: the Code of Conduct's "< / BACK" (.fc-back in
                 assets/site.css), the same line in the same colours, as a button
                 that closes the card. */ ?>
        <button type="button" class="fc-sx-sheet-close fc-back fc-label" data-fc-sx-close><span class="fc-back-mark" aria-hidden="true">&lt;</span> / <?php echo esc_html(fc_pick('Πίσω', 'Back')); ?></button>
        <div data-fc-sx-sheet-body tabindex="-1" autofocus></div>
    </dialog>

    <?php endif; /* $has */ ?>
</section>
<?php
get_template_part('template-parts/partials/schedule-style');
