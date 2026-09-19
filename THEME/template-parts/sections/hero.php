<?php
/**
 * Hero — sponsor-cover layout (see SPONSOR-BROCHURE/1.html).
 *
 *   • LEFT (1/3 on lg, top on mobile): solid accent-blue wordmark panel.
 *       — top-stamp: ■ blip + the 19th-Panhellenic EN line (mono caps).
 *       — wordmark : hard-coded FOSS / COMM / outlined "/26" (Space Grotesk
 *         700, line-height .84, letter-spacing -.05em, white). NOT editable —
 *         matches 1.html exactly. The admin brand/year fields are no longer
 *         used here.
 *       — foot     : the 19th-Panhellenic EL line (mono caps).
 *
 *   • RIGHT (2/3 on lg, below on mobile): paper panel carrying the functional
 *     landing content — When / Where / How-much rows, the CTA list, and
 *     socials + email — as ONE block centered in the middle of the panel.
 *     .fc-section-dots keeps it transparent so the global wave canvas shows
 *     through.
 *
 * The hero breaks out of front-page.php's lg:pl-[200px] gutter (see the
 * margin/width rule in the <style> block) so it spans the full viewport width;
 * the section-nav sidebar is hidden over the hero and only appears at Manifesto
 * (assets/section-nav.js + assets/site.css).
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$top   = fc_bi($data, 'top_label');
$dates = fc_bi($data, 'dates');
$venue = fc_bi($data, 'venue');
$cost  = fc_bi($data, 'cost');
// Optional second line under each value — small grey, editable (e.g. a tagline
// like "…and beer"). Bilingual so it switches with the language toggle.
$dates_sub = fc_bi($data, 'dates_sub');
$venue_sub = fc_bi($data, 'venue_sub');
$cost_sub  = fc_bi($data, 'cost_sub');
$email = (string) ($data['email'] ?? '');
$socials = (array) ($data['socials'] ?? []);

// CTAs — dynamic repeater (Home admin). Each row: label + optional hover label
// + url. Falls back to the legacy fixed primary/secondary/tertiary fields for
// installs that haven't re-saved the Home section yet.
$hero_ctas = [];
foreach ((array) ($data['ctas'] ?? []) as $row) {
    if (!is_array($row)) continue;
    $pair = fc_bi($row, 'label');
    if ($pair['en'] === '' && $pair['el'] === '') continue;
    $hero_ctas[] = [
        'pair'  => $pair,
        'hover' => fc_bi($row, 'label_hover'),
        'url'   => (string) ($row['url'] ?? '#'),
    ];
}
if (empty($hero_ctas)) {
    $legacy = [
        ['base' => 'cta_primary',   'url' => (string) ($data['cta_primary_url']   ?? '#schedule')],
        ['base' => 'cta_secondary', 'url' => (string) ($data['cta_secondary_url'] ?? '#volunteer')],
        ['base' => 'cta_tertiary',  'url' => (string) ($data['cta_tertiary_url']  ?? '#sponsors')],
    ];
    foreach ($legacy as $l) {
        $pair = fc_bi($data, $l['base']);
        if ($pair['en'] === '' && $pair['el'] === '') continue;
        $hero_ctas[] = [
            'pair'  => $pair,
            'hover' => fc_bi($data, $l['base'] . '_hover'),
            'url'   => $l['url'] !== '' ? $l['url'] : '#',
        ];
    }
}

$info_rows = array_values(array_filter([
    ['label_en' => 'When',     'label_el' => 'Πότε', 'value_en' => $dates['en'], 'value_el' => $dates['el'], 'sub_en' => $dates_sub['en'], 'sub_el' => $dates_sub['el'], 'url' => (string) ($data['dates_url'] ?? '')],
    ['label_en' => 'Where',    'label_el' => 'Πού',  'value_en' => $venue['en'], 'value_el' => $venue['el'], 'sub_en' => $venue_sub['en'], 'sub_el' => $venue_sub['el'], 'url' => (string) ($data['venue_url'] ?? '')],
    ['label_en' => 'How much', 'label_el' => 'Πόσο', 'value_en' => $cost['en'],  'value_el' => $cost['el'],  'sub_en' => $cost_sub['en'],  'sub_el' => $cost_sub['el'],  'url' => (string) ($data['cost_url'] ?? '')],
], function ($r) {
    return $r['value_en'] !== '' || $r['value_el'] !== '';
}));

$socials = array_values(array_filter($socials, function ($s) {
    return is_array($s) && (string) ($s['label'] ?? '') !== '';
}));

$has_top_en = $top['en'] !== '';
$has_top_el = $top['el'] !== '';
$has_bottom_right = (!empty($socials) || $email !== '');
/* No eyebrow on the hero. "00 / Home" labelled the panel the wordmark is
   already the label for. The section's name still exists in the registry, so
   the sidebar nav is unchanged. */
?>
<section id="<?php echo esc_attr((string) $section['key']); ?>" class="fc-hero relative">

    <!-- LEFT · solid accent-blue wordmark panel. -->
    <div class="fc-hero-blue relative flex flex-col justify-between px-8 sm:px-12 lg:px-12 pt-16 pb-10 lg:pt-14 lg:pb-12">
        <?php /* my-auto CENTRES THE MARK. The panel is a column with
                 justify-between, which was right when there were three things in
                 it — stamp, mark, foot — and pinned the mark to the top the
                 moment the stamp went. Auto margins take the free space first
                 and split it equally above and below, so the mark sits in the
                 middle of the panel with the foot still on the bottom line. */ ?>
        <!-- wordmark -->
        <div class="fc-hero-wordmark-wrap py-8 my-auto">
            <h1 class="fc-hero-wordmark font-display leading-[0.84] m-0" lang="en">
                <span class="block">FOSS</span>
                <span class="block">COMM</span>
                <span class="block fc-hero-outline">/26</span>
            </h1>
        </div>

        <!-- foot: 19th-Panhellenic label — English line + Greek line together,
             on both desktop and mobile (no leading square). -->
        <?php /* The section-nav's type exactly (.fc-label), in the wordmark's
                 white rather than the muted ink the others use — it sits on the
                 blue panel, where muted grey would barely be there. No leading
                 override: the same line-height as the sidebar. */ ?>
        <?php $top_text = fc_one($top); if ($top_text !== '') : ?>
            <div class="fc-hero-foot fc-label">
                <div><?php echo esc_html($top_text); ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT · paper panel. Two containers (info + CTAs) spread with
         justify-evenly — equal gaps top / between / bottom; email + socials are
         pinned at the bottom corners, same font + edge distance as the
         19th-Panhellenic label on the blue panel. Symmetric py padding. -->
    <div class="fc-hero-paper fc-section-dots relative flex flex-col px-8 sm:px-12 lg:px-12 py-10 lg:py-12">
        <?php
        /* ── When / where / how much, as three cut strips ──────────────────
         *
         * Each fact is one strip with its key (When / Where / How much) above it.
         * The stack's size, spacing and air are the CSS below; each strip's shape
         * is two numbers further down. Both ends of every strip are cut by the
         * panel's edges, so they land on them at every window size.
         *
         * The one thing CSS cannot know is how wide a line of words will be, so
         * that is measured here and handed over, purely to stop the type growing
         * wider than the panel. A character count is a poor proxy ("17 — 18 OCT
         * 2026" is mostly 1s and spaces), so this adds up the face's real advance
         * widths in ems — Space Grotesk 700, measured in the browser; anything
         * unlisted, Greek included, takes the average capital, and 0.04 comes off
         * each character for the negative tracking the CSS sets.
         *
         * Mark part of a value with *asterisks* in FOSSCOMM → Home and that run
         * is drawn hollow, the way the wordmark's year is.
         */
        $fc_band_em_width = function (string $text): float {
            static $w = [
                'A' => .634, 'B' => .664, 'C' => .644, 'D' => .666, 'E' => .554, 'F' => .534,
                'G' => .662, 'H' => .656, 'I' => .264, 'J' => .610, 'K' => .626, 'L' => .542,
                'M' => .882, 'N' => .670, 'O' => .676, 'P' => .604, 'Q' => .676, 'R' => .632,
                'S' => .606, 'T' => .588, 'U' => .672, 'V' => .618, 'W' => .898, 'X' => .644,
                'Y' => .624, 'Z' => .576,
                '0' => .648, '1' => .452, '2' => .594, '3' => .608, '4' => .636,
                '5' => .600, '6' => .618, '7' => .554, '8' => .600, '9' => .618,
                ' ' => .254, '.' => .298, ',' => .294, ':' => .298, ';' => .298, '!' => .298,
                '?' => .578, '-' => .432, '—' => .888, '–' => .584, '/' => .388, '(' => .398,
                ')' => .390, '&' => .591, '%' => .758, '+' => .620, '·' => .298, '€' => .678,
                "'" => .294, '"' => .514,
            ];
            $upper = function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
            $sum = 0.0;
            foreach (preg_split('//u', $upper, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
                $sum += ($w[$ch] ?? 0.713) - 0.04;
            }
            return max(1.0, $sum);
        };

        /* The widest of a line's two halves, split at whichever space makes them
           most even — how wide it is once it wraps onto two lines. */
        $fc_band_em_half = function (string $text) use ($fc_band_em_width): float {
            $words = preg_split('/ +/u', trim($text)) ?: [];
            $best = $fc_band_em_width($text);
            for ($i = 1; $i < count($words); $i++) {
                $best = min($best, max(
                    $fc_band_em_width(implode(' ', array_slice($words, 0, $i))),
                    $fc_band_em_width(implode(' ', array_slice($words, $i)))
                ));
            }
            return $best;
        };

        /* One size for all three strips, so they come out the same: the widest
           line is the one that decides it.

           ON A PHONE a line that long would shrink all three strips to nothing
           ("National Technical University · Athens, GR" alone is 20 ems), so there
           any line wider than $BAND_WRAP ems breaks onto two, and the size is set
           by the widest thing left. */
        $BAND_WRAP = 12;
        $facts = [];
        $band_units = 1.0;
        foreach ($info_rows as $row) {
            $label = fc_pick($row['label_el'], $row['label_en']);
            $value = fc_pick($row['value_el'], $row['value_en']);
            if ($value === '') continue;
            $plain = fc_hollow_plain($value);
            $units = $fc_band_em_width($plain);
            $band_units = max($band_units, $units);
            $facts[] = [
                'label' => $label,
                'url'   => $row['url'],
                'lines' => fc_hollow_split($value, '/\R/u'),
                'units' => round($units, 2),
                'half'  => $units > $BAND_WRAP ? $fc_band_em_half($plain) : $units,
            ];
        }
        $band_units_sm = 1.0;
        foreach ($facts as $f) $band_units_sm = max($band_units_sm, $f['half']);
        foreach ($facts as $i => $f) {
            // two lines only where one would not fit the phone's size
            $facts[$i]['lines_sm'] = $f['units'] > $band_units_sm + 0.01 ? 2 : 1;
        }

        /* HOW EACH STRIP IS SHAPED — two numbers each, and they are the design:
         *
         *   taper  how much taller one end is than the middle. 1.08 makes the
         *          right end 1 + 1.08/2 = 1.54 times the middle and the left end
         *          1 − 1.08/2 = 0.46 times it; negative flips it (left end big).
         *          Keep it under 2 — at 2 the small end would be a point, and it
         *          reads as a wedge rather than a strip well before that.
         *   tilt   the lean of the whole strip; positive dips the right end.
         *   small  how much the words fill the SMALL end, 0 to about 0.3. The
         *          words are tapered so the air above and below them is the same
         *          number of pixels all along — which at the thin end is a large
         *          share of a small strip, and reads as a gap. This eases their
         *          taper by that fraction and lifts their size to match, so the
         *          BIG end lands exactly where it did and only the thin end
         *          closes up.
         *   lift   a vertical nudge in screen pixels, negative up. Applied
         *          outside the perspective, so it is the same few pixels
         *          everywhere — nothing against the tall end, most of the air at
         *          the thin one. This is what makes a small end's top and bottom
         *          gaps close by different amounts.
         *
         * Everything else follows. A strip turned in perspective is really a slab
         * turned about the middle of the panel, seen from $BAND_DEPTH panel widths
         * away; the angle that gives a taper T is atan(T · depth), and its height
         * along the strip then runs in a straight line from 1 − T/2 to 1 + T/2.
         *
         * The slab is drawn just long enough on each side to hang past the panel
         * edge and be cut by it, and that is the only thing worked out here. On
         * the far (shrinking) side a point u from the middle lands at
         *     X = u·cosθ / (1 + u·T·cosθ),   so it takes  u = X / (cosθ · (1 − X·T))
         * and on the near side the signs flip. Each end is taken a little past
         * the 0.5 of the panel's edge (0.56 far, 0.6 near) so the cut is clean.
         *
         * The WORDS are a separate layer, turned a little harder than the blue —
         * see .fc-band-line in the CSS for why — and only they are slid sideways
         * to centre them, so the blue never has to allow for that. */
        $BAND_DEPTH = 0.8;
        $band_shapes = [
            // WHEN — right end big. Thin end closed up, and the words sit a
            // touch high in it: most of what closes comes off the top.
            ['taper' =>  1.08, 'tilt' => '-1.2deg', 'small' => 0.16, 'lift' => -1.4],
            // WHERE — left end big. The same, a little gentler.
            ['taper' => -0.95, 'tilt' => '0.9deg',  'small' => 0.14, 'lift' => -1.2],
            // HOW MUCH — right end big, dipping. The other way: the bottom gap
            // is the one that closes, so the words sit a touch low.
            ['taper' =>  0.98, 'tilt' => '1.1deg',  'small' => 0.15, 'lift' =>  1.3],
        ];
        foreach ($band_shapes as $i => $shape) {
            $t    = (float) $shape['taper'];
            /* The cap is on the REACH maths only, and it is not the taper's own
               limit: the far end's formula only runs away as 0.56·a approaches 1,
               i.e. a approaching 1.79. 1.4 leaves that margin while still
               refusing a nonsense value. */
            $a    = min(abs($t), 1.4);
            $cos  = 1 / sqrt(1 + ($a * $BAND_DEPTH) ** 2);
            $far  = 0.56 / ($cos * (1 - 0.56 * $a));
            $near = 0.6 / ($cos * (1 + 0.6 * $a));
            $band_shapes[$i]['rl']  = round($t >= 0 ? $far : $near, 3);   // slab, left of centre
            $band_shapes[$i]['rr']  = round($t >= 0 ? $near : $far, 3);   // …and right of it
        }
        ?>
        <?php if ($facts) : ?>
            <div class="fc-bands" style="--fc-depth: <?php echo esc_attr($BAND_DEPTH); ?>; --fc-units-max: <?php echo esc_attr(round($band_units, 2)); ?>; --fc-units-sm-max: <?php echo esc_attr(round($band_units_sm, 2)); ?>;">
                <?php foreach ($facts as $i => $f) :
                    $shape = $band_shapes[$i % count($band_shapes)];
                    // With a link set in FOSSCOMM → Home the whole strip is the link.
                    $href = $f['url'] !== '' ? esc_url($f['url']) : '';
                    $tag  = $href !== '' ? 'a' : 'div';
                    ?>
                    <<?php echo $tag; ?> class="fc-band" data-fc-drag<?php if ($href !== '') echo ' href="' . $href . '"'; ?> style="--fc-taper: <?php echo esc_attr($shape['taper']); ?>; --fc-tilt: <?php echo esc_attr($shape['tilt']); ?>; --fc-rl: <?php echo esc_attr($shape['rl']); ?>; --fc-rr: <?php echo esc_attr($shape['rr']); ?>; --fc-small: <?php echo esc_attr($shape['small']); ?>; --fc-lift: <?php echo esc_attr($shape['lift']); ?>px; --fc-sign: <?php echo $shape['taper'] >= 0 ? '1' : '-1'; ?>; --fc-lines-sm: <?php echo (int) $f['lines_sm']; ?>;">
                        <div class="fc-band-slab"><?php if ($f['label'] !== '') : ?><span class="fc-band-k fc-label"><?php echo esc_html($f['label']); ?></span><?php endif; ?></div>
                        <div class="fc-band-line">
                            <?php /* ONE copy, and the three spaces that follow it. The
                                     script below repeats it until the strip is full and
                                     slides the row by exactly one copy, so the words run
                                     round the strip with the same gap between every pair
                                     of them and no seam. On a phone the copy stays alone
                                     and the line is still, as it was. */ ?>
                            <span class="fc-band-v">
                                <span class="fc-band-track"><span class="fc-band-copy"><?php
                                    foreach ($f['lines'] as $line) { echo $line; }
                                    ?><span class="fc-band-gap" aria-hidden="true">&nbsp;&nbsp;&nbsp;</span></span></span>
                            </span>
                        </div>
                    </<?php echo $tag; ?>>
                <?php endforeach; ?>
            </div>
            <?php /* CENTRING, measured rather than predicted. The words sit at the
                     middle of the panel, but the magnified half of a turned line is
                     longer than the other, so the finished line lands off to one
                     side — by an amount that depends on how long the line is on
                     screen, which only the browser knows. So this looks at where
                     each line actually landed and slides the words (not the blue)
                     back by the difference. The slide is the outermost part of the transform,
                     so it moves the finished picture rigidly: one pass is exact,
                     and it cannot change the layout it measured. It re-runs when
                     anything resizes, the type swapping in included. Without it
                     the words are still in the right place to within a few
                     per cent. */ ?>
            <script>
            (function () {
                var wrap = document.querySelector('.fc-bands');
                if (!wrap) return;
                var queued = false;

                /* THE CAROUSEL. One copy of the words is in the markup; this
                 * repeats it until the row is longer than the strip's own box
                 * and tells the CSS how far to slide before looping — exactly
                 * one copy, which is what makes the loop invisible.
                 *
                 * offsetWidth, never getBoundingClientRect(): the layer carries
                 * the perspective, so a rect measures the finished picture and
                 * the row would be filled to the wrong length. offsetWidth is
                 * the untransformed layout box, which is what is being filled.
                 *
                 * The speed is one number for the whole stack — pixels per
                 * second in the layer's own (oversampled) space, so a short
                 * strip and a long one travel at the same rate rather than
                 * taking the same time.
                 *
                 * At every width, phones included — a running line has no reason
                 * to wrap, so a strip that used to hold two centred lines holds
                 * one moving one. Off only for a reader who has asked for less
                 * movement: they keep the single copy that is already there. */
                var MARQUEE_PX_PER_S = 65;
                var still = window.matchMedia('(prefers-reduced-motion: reduce)');

                function fill() {
                    wrap.querySelectorAll('.fc-band').forEach(function (band) {
                        var line  = band.querySelector('.fc-band-line');
                        var win   = band.querySelector('.fc-band-v');
                        var track = band.querySelector('.fc-band-track');
                        var copy  = track && track.querySelector('.fc-band-copy');
                        if (!line || !win || !track || !copy) return;

                        var want = 1;
                        if (!still.matches) {
                            var cs = getComputedStyle(band);
                            var num = function (name, fallback) {
                                var v = parseFloat(cs.getPropertyValue(name));
                                return isFinite(v) ? v : fallback;
                            };
                            var k     = num('--fc-k', 2.6);
                            var taper = num('--fc-taper', 0);
                            var air   = num('--fc-air', 0.1);
                            var depth = num('--fc-depth', 0.8);
                            var copyW = copy.offsetWidth;
                            /* One panel-width, in THIS layer's pixels — and it
                             * is the line's whole width, not a k-th of it. The
                             * layer is built k times too big and scaled back
                             * INSIDE its own transform (scale(1/k) is applied
                             * before the rotation), so by the time the
                             * perspective sees it, k layer pixels have become
                             * one. Dividing by k here made every window 2.6
                             * times too short: the row stopped a third of the
                             * way along the strip, which is exactly what it
                             * looked like. */
                            var panel = line.offsetWidth;
                            if (!copyW || !panel) return;

                            /* The words' own taper — harder than the blue's, see
                             * .fc-band-line — and the reaches it gives, by the
                             * same working the PHP does for the slab: how far
                             * the row has to run each way to cover the panel
                             * once the projection has had its say. */
                            var tt   = taper / (1 - 2 * air);
                            var a    = Math.min(Math.abs(tt), 1.4);
                            var cos  = 1 / Math.sqrt(1 + Math.pow(a * depth, 2));
                            var far  = 0.56 / (cos * (1 - 0.56 * a));
                            var near = 0.6  / (cos * (1 + 0.6  * a));

                            // Which end is coming towards you: the taller one.
                            var toRight = taper >= 0;
                            var uL = toRight ? -far  : -near;
                            var uR = toRight ?  near :  far;

                            var winW  = (uR - uL) * panel;
                            var shift = ((uL + uR) / 2) * panel;
                            /* Two whole copies of slack at each end, not one.
                             * Two movements are stacked on this row and either
                             * can eat a copy: the animation slides it by one
                             * before it loops, and a hand can pull it by another
                             * before the drag offset wraps. With one spare, the
                             * two together ran the row off its own end — the
                             * words vanishing as they reached the big side. */
                            want = Math.ceil(winW / copyW) + 4;

                            band.style.setProperty('--fc-track-w', winW.toFixed(2) + 'px');
                            band.style.setProperty('--fc-track-shift', shift.toFixed(2) + 'px');
                            /* Two copies out to the left, whichever way the row
                             * travels — the slack has to be on BOTH sides now
                             * that the drift and the hand can pull it either
                             * way at once. */
                            band.style.setProperty('--fc-row-offset', (-2 * copyW).toFixed(2) + 'px');
                            band.style.setProperty('--fc-copy-w',
                                (toRight ? copyW : -copyW).toFixed(2) + 'px');
                            band.style.setProperty('--fc-marquee-dur',
                                (copyW / MARQUEE_PX_PER_S).toFixed(2) + 's');
                            /* One cycle of the marquee, SIGNED — the same
                             * distance the animation travels, which is what
                             * assets/belt-drag.js turns a hand's pixels into
                             * time with. The gain beside it is because this
                             * layer is drawn k times too big and scaled back
                             * inside its own transform, so a screen pixel is k
                             * of its own. */
                            band.style.setProperty('--fc-drag-span',
                                (toRight ? copyW : -copyW).toFixed(2) + 'px');
                            band.style.setProperty('--fc-drag-gain', k.toFixed(2));
                            band.classList.add('is-carousel');
                        } else {
                            band.classList.remove('is-carousel');
                            band.style.setProperty('--fc-drag-span', '0px');
                        }

                        // Only ever the difference: with the count already right
                        // this touches nothing, so the ResizeObserver below
                        // cannot drive itself round in circles.
                        while (track.children.length > want) {
                            track.removeChild(track.lastElementChild);
                        }
                        while (track.children.length < want) {
                            var clone = copy.cloneNode(true);
                            // The words are already in the accessibility tree once.
                            clone.setAttribute('aria-hidden', 'true');
                            track.appendChild(clone);
                        }
                    });
                }

                function centre() {
                    queued = false;
                    fill();
                    wrap.querySelectorAll('.fc-band').forEach(function (band) {
                        var line = band.querySelector('.fc-band-line');
                        var text = band.querySelector('.fc-band-v');
                        if (!line || !text) return;
                        // A carousel needs none of this: the row is placed by the
                        // reach maths in fill(), and measuring a repeated row
                        // would hand back the middle of the whole row rather
                        // than of a line.
                        if (band.classList.contains('is-carousel')) {
                            line.style.setProperty('--fc-nudge', '0px');
                            return;
                        }
                        var was = parseFloat(line.style.getPropertyValue('--fc-nudge')) || 0;
                        // Measured with the lean taken off for a moment: a leaning
                        // line's bounding box sticks out further on its magnified
                        // side, which would read as off-centre when it is not. The
                        // lean itself barely moves the line sideways. Nothing is
                        // painted in between.
                        // The WORDS, not their box: once a line wraps, the box is
                        // wider than the lines in it, and its magnified side would
                        // pull the lines off to the other.
                        var words = document.createRange();
                        words.selectNodeContents(text);
                        var tilt = band.style.getPropertyValue('--fc-tilt');
                        band.style.setProperty('--fc-tilt', '0deg');
                        var b = band.getBoundingClientRect(), t = words.getBoundingClientRect();
                        band.style.setProperty('--fc-tilt', tilt);
                        var off = (t.left + t.right) / 2 - (b.left + b.right) / 2;
                        line.style.setProperty('--fc-nudge', (was - off).toFixed(2) + 'px');
                    });
                }
                function soon() { if (!queued) { queued = true; requestAnimationFrame(centre); } }

                // The hover fill comes in from the edge OPPOSITE the one the
                // pointer crossed, like the FAQ's (assets/faq.js) — enter over the
                // top and it rises from the bottom. Written again on leaving, so it
                // drops away from the pointer rather than trailing it.
                wrap.querySelectorAll('.fc-band').forEach(function (band) {
                    function from(e) {
                        var r = band.getBoundingClientRect();
                        band.style.setProperty('--fc-band-from', (e.clientY - r.top) < r.height / 2 ? 'bottom' : 'top');
                    }
                    band.addEventListener('mouseenter', from);
                    band.addEventListener('mouseleave', from);
                });
                if ('ResizeObserver' in window) {
                    var ro = new ResizeObserver(soon);
                    ro.observe(wrap);
                    /* The COPY, not the box around it. Once the carousel is on,
                     * the box has a width this script gave it, so it no longer
                     * changes when the webfont swaps in and the observer would
                     * never fire again — leaving the row placed, and looping, by
                     * a copy width measured in the fallback face. The copy
                     * itself still measures the words. */
                    wrap.querySelectorAll('.fc-band-v, .fc-band-copy').forEach(function (el) {
                        ro.observe(el);
                    });
                }
                window.addEventListener('resize', soon);
                // A system that starts respecting reduced motion mid-session:
                // rebuild the row (or take it away).
                if (still.addEventListener) still.addEventListener('change', soon);
                if (document.fonts && document.fonts.ready) document.fonts.ready.then(soon);
                /* Two more passes, and they earn their keep: the row's length and
                 * the distance it loops by are MEASURED, and a measurement taken
                 * before the last layout has settled leaves the loop jumping by
                 * a copy width that is no longer a copy. Once everything has
                 * loaded, and once more a beat later, costs nothing and cannot
                 * be got wrong. */
                window.addEventListener('load', soon);
                setTimeout(soon, 400);
                soon();
            })();
            </script>
        <?php endif; ?>

        <div class="fc-hero-actions flex-1 flex flex-col justify-evenly w-full max-w-lg mx-auto">

<?php if (!empty($hero_ctas)) : ?>
                <ul class="list-none p-0 m-0 space-y-3">
                    <?php foreach ($hero_ctas as $cta) :
                        // Drops a typed trailing arrow — the template draws its own.
                        $strip = function (string $s): string { return (string) preg_replace('/[\s→>]+$/u', '', $s); };
                        // Active language label + its hover variant (one span now).
                        $label    = $strip(fc_one($cta['pair']));
                        $alt      = $strip(fc_one($cta['hover']));
                        $has_hover = ((string) $cta['hover']['en'] !== '' || (string) $cta['hover']['el'] !== '');
                        if ($label === '') continue;
                        /* A button with nowhere to go — no URL, or the "#"
                           placeholder — can show its hover text on a screen that
                           cannot hover: both labels are printed, and a TAP swaps
                           to the second one, a second tap back. That text is
                           usually the reason there is no link yet ("almost
                           ready"), and a phone has no pointer to reveal it with —
                           assets/hover-scramble.js never runs there. The tap is
                           bound by the script under the list; the gate is the
                           device's ability to hover, not the window's width, so
                           a narrow desktop window still hovers instead. */
                        $url     = trim((string) $cta['url']);
                        $no_link = ($url === '' || $url === '#');
                        $swap    = ($no_link && $has_hover && $alt !== '');
                        ?>
                        <li>
                            <a href="<?php echo esc_url($cta['url']); ?>"
                               class="fc-hero-cta fc-btn-size inline-flex items-baseline gap-2 text-ink hover:text-accent<?php if ($swap) echo ' fc-cta-linkless'; ?>"
                               <?php if ($has_hover) echo 'data-fc-hover-link'; ?>>
                                <span class="fc-cta-text fc-btn">
                                    <span <?php if ($has_hover) : ?>data-fc-hover-default="<?php echo esc_attr($label); ?>" data-fc-hover-alt="<?php echo esc_attr($alt); ?>"<?php endif; ?>><?php echo fc_format($label); ?></span>
                                    <?php if ($swap) : ?><span class="fc-cta-touch"><?php echo fc_format($alt); ?></span><?php endif; ?>
                                </span>
                                <span aria-hidden="true" class="fc-arrow">&gt;</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php /* The tap that stands in for the hover, on the buttons that
                         have nowhere to go. Bound only for them: a button with a
                         real link has to open it, and a tap cannot both open a
                         page and reveal a word.

                         The check is made ON THE TAP, not once at load, so a
                         tablet that gains a keyboard and a trackpad mid-session
                         goes back to hovering without a reload. */ ?>
                <script>
                (function () {
                    var links = document.querySelectorAll('.fc-hero-cta.fc-cta-linkless');
                    if (!links.length || !window.matchMedia) return;
                    links.forEach(function (link) {
                        link.addEventListener('click', function (e) {
                            if (window.matchMedia('(hover: hover)').matches) return;
                            // Nothing to navigate to — the href is "#", and
                            // following it would jump the page to the top.
                            e.preventDefault();
                            link.classList.toggle('is-alt');
                        });
                    });
                }());
                </script>
            <?php endif; ?>

            <?php if ($has_bottom_right) : ?>
                <!-- email with the socials stacked UNDER it, pinned to the panel's
                     bottom-left corner on every breakpoint (absolute → out of the
                     flex flow), so only the info + CTA blocks share the even
                     spacing above. The stack is bottom-anchored, so adding or
                     removing a social link grows the block upward and the email
                     never shifts off its baseline. Same font + edge distance as the
                     19th-Panhellenic label on the blue panel. -->
                <?php // No text-ink on the links: they take the muted ink of this
                      // wrapper, which is the sidebar's colour, so the email, the
                      // socials and the When / Where / How much labels all match. ?>
                <div class="fc-hero-contact absolute inset-x-0 bottom-10 lg:bottom-12 px-8 sm:px-12 lg:px-12 flex flex-col items-start gap-1 fc-label text-ink-muted">
                    <?php if ($email !== '') : ?>
                        <a href="<?php echo esc_url('mailto:' . $email); ?>"
                           class="max-w-full hover:text-accent no-underline break-all"><?php echo esc_html($email); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($socials)) : ?>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            <?php foreach ($socials as $s) :
                                $label = (string) ($s['label'] ?? '');
                                $url   = (string) ($s['url']   ?? '');
                                if ($label === '') continue;
                                ?>
                                <a href="<?php echo esc_url($url !== '' ? $url : '#'); ?>"
                                   target="_blank" rel="noreferrer noopener"
                                   class="fc-hero-social hover:text-accent no-underline"><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div><!-- /.flex-1 (info / CTAs / email — evenly spread on mobile) -->
    </div>
</section>

<style>
/* Sponsor-cover hero. Mobile-first stack (blue on top); lg+ becomes a 1fr/2fr
   grid — blue wordmark 1/3 on the left, paper content 2/3 on the right —
   matching SPONSOR-BROCHURE/1.html. */
.fc-hero {
    display: grid;
    grid-template-columns: 1fr;
    min-height: 100vh;
}
/* An `fr` track still refuses to go below its item's MIN-CONTENT width, so the
   long unbreakable band lines were able to shove the 1fr / 2fr split out of
   true and squeeze the blue panel down to a sliver. min-width: 0 is what makes
   the ratio actually mean the ratio; the bands clip themselves anyway. */
.fc-hero-blue,
.fc-hero-paper { min-width: 0; }
.fc-hero-blue {
    background: var(--color-accent, #0033FF);
    color: #fff;
    overflow: hidden;
    min-height: 58vh;
}
/* Mobile height tuned so the info + CTA blocks get evenly-distributed spacing
   (justify-evenly) — but ~70vh, roughly half the gaps a full 100vh produced.
   Desktop keeps the full 100vh via the lg override below. */
.fc-hero-paper { min-height: 70vh; }

/* ── The blue panel on phones and tablets: fixed, not fluid ─────────────────
   It used to be 58% of the screen tall with the wordmark centred in it, so
   every change of height — resizing the window, or a phone's address bar
   sliding away — slid the mark and the 19th-Panhellenic line up and down. Now
   it is exactly its contents, in fixed spaces that nothing about the screen
   changes:
     - above the mark: the fixed top bar's height and a small margin;
     - mark → 19th-Panhellenic line, and that line → the panel's bottom: the
       same gap, --fc-blue-gap (the second trimmed by the line's own leading,
       so the two gaps you SEE are equal, not the two boxes);
     - one side margin at every width, so neither the mark nor the line jumps
       sideways at the 640px breakpoint any more.
   The mark itself keeps its own size (--fc-mark-size, growing with the width). */
@media (max-width: 1023.98px) {
    .fc-hero-blue {
        --fc-blue-gap: 2.5rem;
        min-height: 0;
        justify-content: flex-start;
        /* Top: the fixed bar's height, then the SAME gap as below the 19th line
           (measured: the two come out within a pixel as they are). */
        padding: calc(var(--fc-bar-h, 40px) + var(--fc-blue-gap)) 2rem var(--fc-blue-gap);
    }
    .fc-hero-wordmark-wrap { margin: 0; padding: 0; }
    /* The "/" of /26 hangs below its line box by more than the line's own
       leading, which ate 10px out of the gap above (measured: 30px against 39px
       below at a 86px mark). It is in proportion to the mark, so this gives it
       back in proportion: the two gaps come out equal. */
    .fc-hero-foot { margin-top: calc(var(--fc-blue-gap) + 0.14 * var(--fc-mark-size)); }
}

@media (min-width: 1024px) {
    .fc-hero {
        grid-template-columns: 1fr 2fr;
        min-height: 100vh;
    }
    .fc-hero-blue,
    .fc-hero-paper { min-height: 100vh; }
}

/* The 19th-Panhellenic line on the blue panel: the WORDMARK's white, not a
   dimmed one. It was rgba(255,255,255,0.72), which read as a third colour
   between the mark above it and the panel behind it. */
.fc-hero-foot { color: #fff; }

/* Wordmark — same treatment as 1.html .mark: Space Grotesk weight 700, tight
   leading and negative tracking, white. Font-size scales with the viewport so
   it fills the (narrower) 1/3 column on desktop and keeps shrinking on smaller
   phones (low clamp floor). 700 is set explicitly so it always matches the HTML
   weight regardless of .font-display's default 500. */
.fc-hero-wordmark {
    font-weight: 700;
    letter-spacing: -0.05em;
    color: #fff;
    /* Fills the full-width mobile panel the same PROPORTION desktop fills its
       1/3-width column: ~24vw ≈ 3 × desktop's 8.5vw (the panel is ~3× wider), so
       the mark scales with the screen and stays big (~90px on a 375px phone),
       a tiny smaller than desktop (cap 10rem vs 11rem). */
    font-size: var(--fc-mark-size);
}
.fc-hero {
    --fc-mark-size: clamp(3rem, 24vw, 10rem);
}
@media (min-width: 1024px) {
    .fc-hero { --fc-mark-size: clamp(3rem, 8.5vw, 11rem); }
}
/* Outlined year — hollow white stroke, transparent fill (1.html .outline).
 *
 * The stroke is in em, so it stays the same PROPORTION of the letters at every
 * size. It was 3px flat, and the wordmark above is fluid — clamp(3rem, 24vw,
 * 10rem) on a phone and clamp(3rem, 8.5vw, 11rem) on desktop — so the same
 * three pixels were a very different weight depending on the screen:
 *
 *     375px phone   wordmark  90px   3px = 3.33%
 *     1440px        wordmark 122px   3px = 2.45%
 *     1920px        wordmark 163px   3px = 1.84%
 *
 * A phone therefore drew the outline about 1.4x heavier than a laptop and 1.8x
 * heavier than a wide monitor, which is exactly the "it gets thicker as it gets
 * smaller" — the outline was not getting thicker, the letters were getting
 * thinner around it.
 *
 * 0.018em is the WIDE end of that range — the proportion a flat 3px drew at
 * 1920px and above, which is where the old value looked right. It holds that
 * everywhere now: 1.6px on a 375px phone, 2.2px at 1440, 3.0px at 1920, 3.2px
 * at the 176px cap.
 *
 * It was briefly 0.022em, the middle of the desktop range, which came out
 * HEAVIER than the old 3px on a wide screen (3.6px at 1920) — thicker than the
 * thing it was meant to reproduce. One number to nudge either way.
 */
.fc-hero-outline {
    -webkit-text-stroke: 0.018em #fff;
    color: transparent;
}
/* …but only where stroking actually works. `color: transparent` with nothing
   drawn behind it is an INVISIBLE "/26", not a hollow one — the same trap the
   speakers' hollow name documents at length. Without the guard the year simply
   disappeared on any engine without -webkit-text-stroke. */
@supports not (-webkit-text-stroke: 1px white) {
    .fc-hero-outline { color: #fff; }
}

/* The CTA type (.fc-btn, .fc-arrow) lives in assets/site.css — shared with
   fc_cta_link() so the hero / Get Involved / sponsor CTAs all match. */

/* ── When / where / how much: three cut strips ───────────────────────────────
 *
 * The numbers at the top of .fc-bands decide the stack — where it starts, how
 * big it is, how far apart the strips sit, how much air the words keep. Each
 * strip's own shape (taper and tilt) is two numbers in the PHP above. Nothing
 * is placed by hand: change one and everything follows, at every window size.
 *
 * A strip is a window exactly as wide as the panel, with a blue slab inside it
 * turned away in perspective and cut by the window — so its ends are always
 * exactly on the panel's edges. The slab is drawn just long enough on each side
 * to hang past them (the PHP works out how long; --fc-rl / --fc-rr), and it is
 * turned about the middle of the panel, which is where the words sit.
 *
 * 1cqw is one per cent of a strip's own width, so the shape is written in panel
 * units and comes out identical at every size. */
.fc-bands {
    --fc-stack-top: 16.667vh;   /* how far down the screen the stack starts */
    --fc-stack-h: 44vh;         /* how much of the screen it takes */
    --fc-gap: 1.05;             /* between strips, in strip heights (room for the labels)… */
    --fc-gap-min: 5.5cqw;       /* …but never less than this much of the panel */
    /* Tight to the capitals. The strips are kept big by the stack height and by
       --fc-fill below — by making the TYPE bigger, that is, not by padding the
       words with air, which only pushes the letters away from the strip's edges
       without making the strip read as any larger. */
    --fc-air: 0.10;             /* above and below the capitals, in strip heights */
    --fc-fill: 0.98;            /* how much of the panel's width the widest line may take */
    --fc-key-x: 0.05;           /* where the keys start, across the panel from its left edge */

    container-type: inline-size;
    display: flex;
    flex-direction: column;
    flex: none;
    /* The panel's own padding comes off the top, and the sides are cancelled so
       the strips run edge to edge of the panel. */
    margin-top: calc(var(--fc-stack-top) - 2.5rem);
    margin-inline: -2rem;
}
@media (min-width: 640px) {
    .fc-bands { margin-top: calc(var(--fc-stack-top) - 3rem); margin-inline: -3rem; }
}

.fc-band {
    --fc-lh: 0.74;   /* line box = the capitals exactly (see .fc-band-v) */
    --fc-k: 2.6;     /* oversampling — see .fc-band-slab */
    /* THE WORDS' TAPER — see .fc-band-line for why it is harder than the blue's.
     *
     * --fc-small eases it back by its own fraction, which closes the air at the
     * THIN end: the words' height falls away more slowly than the strip's, so
     * they fill more of it there. --fc-vscale then puts the size back up by the
     * amount the big end lost, so that end is left exactly as it was — which is
     * the whole point of doing it here rather than by changing the type size.
     *
     * The sign is passed in (--fc-sign) rather than taken with abs(): the big
     * end is at +0.5 for a positive taper and −0.5 for a negative one, and its
     * height factor is 1 + sign · tt / 2 either way. */
    --fc-tt-base: calc(var(--fc-taper, 0) / (1 - 2 * var(--fc-air)));
    --fc-tt: calc(var(--fc-tt-base) * (1 - var(--fc-small, 0)));
    --fc-vscale: calc(
        (1 + var(--fc-sign, 1) * var(--fc-tt-base) / 2)
        / (1 + var(--fc-sign, 1) * var(--fc-tt) / 2)
    );
    /* THE TYPE first: as big as a strip's share of the stack (three strips, two
       gaps) allows once the air is taken off, unless the widest line would then
       be wider than the panel. (--fc-units-max is that line's width in ems,
       measured in the PHP, so one size suits all three and they match.) */
    --fc-fs: min(
        calc(var(--fc-stack-h) / (3 + 2 * var(--fc-gap)) * (1 - 2 * var(--fc-air)) / 0.74),
        calc(var(--fc-fill) * 100cqw / var(--fc-units-max, 12))
    );
    /* …and THEN the strip, built round it: the capitals plus the air, so the air
       is exactly --fc-air of the strip in every strip at every size. */
    --fc-band-h: calc(0.74 * var(--fc-fs) / (1 - 2 * var(--fc-air)));

    position: relative;
    height: var(--fc-band-h);
    /* Sideways only. A slab stands taller than its window at the near end and
       has to be free to; only its ends are cut. (clip + visible is the one mixed
       pairing that does not force a scroll container.) */
    overflow-x: clip;
    overflow-y: visible;
}
/* The gap between strips has to clear the corners they throw outside their own
   boxes, and those grow with the PANEL while the strips grow with the SCREEN —
   so on a wide, short window a gap measured only in strip heights closes up.
   The larger of the two keeps them apart either way. */
.fc-band + .fc-band {
    margin-top: max(calc(var(--fc-band-h) * var(--fc-gap)), var(--fc-gap-min));
}

/* THE TWO LAYERS: the blue slab, and the words over it. Both are built --fc-k
   times too big and scaled back down inside their transforms, because an
   element with a 3D transform is rasterised ONCE at its layout size and then
   stretched — at 1x the magnified end was a blown-up bitmap (3-4px of blur on
   every edge; at 2.6 it is about 1px, and no better beyond). The scale applies
   first, in the layer's own space, so the shape is unchanged. Both are turned
   about the middle of the panel and tilted alike.

   The turn is set by the taper: turned by atan(taper × depth) about the middle,
   a layer's height runs in a straight line from 1 − taper/2 at one edge of the
   panel to 1 + taper/2 at the other. */

/* The blue. Its box runs --fc-rl panels left of the panel's middle and --fc-rr
   to the right (× k) — just enough to be cut by both edges — and the turn is
   made about that middle (transform-origin). */
.fc-band-slab {
    position: absolute;
    top: calc(-50% * (var(--fc-k) - 1));
    bottom: calc(-50% * (var(--fc-k) - 1));
    left: calc(50% - var(--fc-rl, 1) * var(--fc-k) * 100%);
    right: calc(50% - var(--fc-rr, 1) * var(--fc-k) * 100%);
    transform-origin: calc(100% * var(--fc-rl, 1) / (var(--fc-rl, 1) + var(--fc-rr, 1))) 50%;
    background: var(--color-accent, #0033FF);
    transform:
        perspective(calc(var(--fc-depth, 0.8) * 100cqw))
        rotateY(calc(-1 * atan(var(--fc-taper, 0) * var(--fc-depth, 0.8))))
        rotateZ(var(--fc-tilt, 0deg))
        scale(calc(1 / var(--fc-k)));
}

/* The words — turned HARDER than the blue. With both turned alike the gap above
   and below the letters is a fixed share of the strip, so in pixels it grows
   towards the big end: the letters never get as big as the strip does. For the
   gap to stay the same number of pixels all along, the letters have to grow by
   the strip's growth × (strip ÷ letters), and that ratio is 1 / (1 − 2 × air) —
   so this layer's taper is the strip's divided by (1 − 2 × air). Same middle,
   same lean, so the words stay centred in the strip from end to end.

   --fc-nudge is set by the little script after the strips, which measures where
   each line landed and slides it back to the middle. It is outermost, so it
   moves the finished words as one piece — and it slides them ALONG the strip,
   not straight across: the strip leans, so a purely sideways slide moved the
   words off its centre line and they sat 2-3px high or low. Its slope on screen
   is tan(tilt) / cos(turn), and 1 / cos(atan(x)) is √(1 + x²). */
.fc-band-line {
    position: absolute;
    top: calc(-50% * (var(--fc-k) - 1));
    bottom: calc(-50% * (var(--fc-k) - 1));
    left: calc(50% - 50% * var(--fc-k));
    right: calc(50% - 50% * var(--fc-k));
    display: flex;
    align-items: center;      /* the line box IS the capitals — see line-height */
    justify-content: center;
    white-space: nowrap;
    color: #fff;
    transform:
        translate(
            var(--fc-nudge, 0px),
            calc(var(--fc-nudge, 0px) * tan(var(--fc-tilt, 0deg))
                 * sqrt(1 + var(--fc-tt) * var(--fc-tt) * var(--fc-depth, 0.8) * var(--fc-depth, 0.8))
                 /* …and the per-strip bias. Outermost, so it is the same few
                    pixels at both ends: nothing against the tall end, and the
                    difference between a thin end's top and bottom gaps. */
                 + var(--fc-lift, 0px))
        )
        perspective(calc(var(--fc-depth, 0.8) * 100cqw))
        rotateY(calc(-1 * atan(var(--fc-tt) * var(--fc-depth, 0.8))))
        rotateZ(var(--fc-tilt, 0deg))
        scale(calc(1 / var(--fc-k)));
}

/* ── The carousel ───────────────────────────────────────────────────────────
   The words repeat along the strip, three spaces apart, and the row slides by
   EXACTLY one copy before the animation loops — so at the moment it snaps back,
   copy 2 is standing where copy 1 was and nothing is seen to move.

   ALL of the numbers come from the script after the strips, and they have to,
   because the strip is a PROJECTION: a row laid out in the layer's own space
   does not cover the panel evenly. Going away from the viewer it compresses
   without limit, so covering the panel's far edge takes several panel-widths of
   row; coming towards the viewer it magnifies, and at
   u = 1 / (taper · cosθ) — about 1.2 panel-widths on these tapers — it reaches
   the vanishing point and blows up. A row simply filled to the layer's box runs
   straight through that, which is the first thing this rendered: letters the
   height of the screen, smeared sideways.
   So the script works out the two reaches the way the PHP does for the slab
   (with the words' own, harder taper) and hands over:
     --fc-track-w      how long the row is
     --fc-track-shift  where its middle sits, since the covered range is not
                       symmetric — the far reach is several times the near one
     --fc-copy-w       one copy, SIGNED: the row travels towards the near end,
                       so the spare copy waits at the far end where the
                       projection is squeezing rather than exploding
     --fc-marquee-dur  one copy's worth of travel at one speed for all three

   The clip is on the window, never on the row: `overflow: clip` here, the
   animation one level in. Clipping happens in this element's own space, before
   the ancestor's perspective, so it is the row that is cut to the safe range
   and never the finished picture. The clip-margin lets the outlined words keep
   their stroke. */
.fc-band-copy { display: inline-block; }
@keyframes fc-band-marquee {
    from { transform: translate3d(0, 0, 0); }
    to   { transform: translate3d(var(--fc-copy-w, 0px), 0, 0); }
}
/* The class goes on from the script — at every width, phones included — so a
   page whose JS never ran keeps the single centred line it has always had
   rather than a broken row. */
.fc-band.is-carousel .fc-band-v {
    display: block;
    width: var(--fc-track-w, auto);
    /* Flex centres the margin box, so a margin of 2S moves the box by S. */
    margin-left: calc(2 * var(--fc-track-shift, 0px));
    overflow: clip;
    overflow-clip-margin: 0.3em;
    /* A running line never wraps, whatever the phone rules below ask for. */
    white-space: nowrap;
    text-align: left;
    max-width: none;
}
/* Dragging a strip scrubs its marquee's own timeline (assets/belt-drag.js), so
   there is no second layer and no second position — the words and their clock
   agree by construction. All the strip has to say is that a sideways gesture is
   ours while up and down still scrolls the page. */
.fc-band.is-carousel {
    touch-action: pan-y;
    user-select: none;
    -webkit-user-select: none;
}
/* No hand for passing over a strip — only for holding one. A strip may also be
   a link, and the pointer for that comes from the site-wide rule in
   inc/bootstrap.php, which this only has to beat while the hand is down. */
@media (hover: hover) {
    .fc-band.is-carousel.is-dragging,
    .fc-band.is-carousel.is-dragging * { cursor: var(--fc-cur-grabbing, grabbing); }
}

.fc-band.is-carousel .fc-band-track {
    display: block;
    white-space: nowrap;
    /* The spare copy waits OUTSIDE the window, on the far side: one copy to the
       left when the row travels right, and the row's own extra length to the
       right when it travels left. Either way the window is covered for the
       whole cycle. */
    margin-left: var(--fc-row-offset, 0px);
    animation: fc-band-marquee var(--fc-marquee-dur, 20s) linear infinite;
    /* The row is inside a 3D transform and composited anyway; saying so keeps
       the projection from being re-rasterised every frame. */
    will-change: transform;
}
/* Held still while you read it — the strip is a link, and a moving target is a
   poor one. Pointer devices only: there is no hovering out of it on a phone. */
@media (hover: hover) {
    .fc-band.is-carousel:hover .fc-band-track { animation-play-state: paused; }
}
/* One line, whatever the phone's two-line rules say: a row that runs has no
   reason to break, so the strip goes back to the desktop's tight line box and
   its one-line height. Two classes deep, to beat those rules wherever they sit. */
.fc-band.is-carousel {
    --fc-lh: 0.74;
    height: var(--fc-band-h);
}
/* Anyone who has asked for less movement keeps the single, still copy: the
   script leaves the class off entirely (see fill()). */

.fc-band-v {
    position: relative;
    font-family: var(--font-display, "Space Grotesk"), ui-sans-serif, system-ui, sans-serif;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: -0.04em;
    /* A 0.74 line box closes right down onto the capitals, so centring the line
       centres the letters and --fc-air above is the gap you see. Measured on
       the real face at 1000px: the flat capitals (H, N, T — the ones the eye
       reads the height from) are 0.703em tall and sit exactly on the baseline,
       and the line box runs from 0.716em above it to 0.024em below — so there is
       more room under the letters than over them. Measured in the strips
       themselves the difference comes to 0.022em, and the letters drop half of
       it to sit dead centre. */
    line-height: var(--fc-lh);
    top: 0.011em;
    /* --fc-vscale is the lift that keeps the BIG end where it was while the
       words' taper is eased to close the thin one — see the note on --fc-tt.
       The fallback is 1, so a browser that cannot divide by an expression in
       calc() simply gets the old, untouched size. */
    font-size: calc(var(--fc-fs) * var(--fc-k) * var(--fc-vscale, 1));
}

/* THE KEY — When / Where / How much — in the sidebar's type and ink, turned
   with its strip (it lives on the blue's layer, just above its top edge, so it
   takes the same slant and perspective), yet starting at the SAME place on
   every strip: --fc-key-x across the panel.

   Where to put it on the layer so it lands there: a point u panel-widths from
   the middle of a turned layer shows up at x = u · cos(turn) · m, where m is
   the magnification there, 1 + taper · x. So for the point x it has to sit at
       u = x · √(1 + (taper · depth)²) / (1 + taper · x)
   (1 / cos(atan(taper · depth)) being that square root), measured from the
   panel's middle — which is --fc-rl panels in from the layer's left edge.

   And it is sized back so it reads at the sidebar's 12px on every strip —
   at a strip's small end the perspective alone would take it down to 7px and
   squeeze it to half its width. Height is magnified by m there and width by
   cos(turn) · m², so the type is divided by m and stretched sideways by
   1 / (cos(turn) · m): what is left is the strip's slant and a hint of its
   taper, on letters of the normal shape. Everything is × k, like the rest of
   the layer. (Three classes deep to outrank .fc-label's own size.) */
.fc-band-slab .fc-band-k.fc-label {
    --fc-kx: calc(var(--fc-key-x) - 0.5);                      /* from the middle */
    --fc-km: calc(1 + var(--fc-taper, 0) * var(--fc-kx));      /* magnification there */
    position: absolute;
    left: calc(
        (var(--fc-rl, 1)
         + var(--fc-kx) * sqrt(1 + var(--fc-taper, 0) * var(--fc-taper, 0) * var(--fc-depth, 0.8) * var(--fc-depth, 0.8))
           / var(--fc-km))
        * var(--fc-k) * 100cqw
    );
    bottom: calc(100% + 6px * var(--fc-k) / var(--fc-km));
    font-size: calc(12px * var(--fc-k) / var(--fc-km));
    transform-origin: 0 100%;   /* keep its start where it was placed */
    transform: scaleX(calc(sqrt(1 + var(--fc-taper, 0) * var(--fc-taper, 0) * var(--fc-depth, 0.8) * var(--fc-depth, 0.8)) / var(--fc-km)));
    line-height: 1;
    white-space: nowrap;
    color: var(--color-ink-muted, #6B6B66);
}

/* ── A button with nowhere to go, on a screen with no pointer ────────────────
   Both labels are in the markup; which one shows is decided here. On anything
   that can hover, the default shows and hover-scramble.js glitches it into the
   hover text while the pointer is on it, as usual.

   On a touch screen a TAP does what the hover did, and a second tap puts it
   back — the button has no link, so the tap has nothing else to do. The class
   is written by the little script under the buttons; nothing swaps on its own,
   so a phone still opens on the label the author wrote.

   `(hover: none)` and not a width breakpoint, deliberately: it asks whether the
   input can hover, which is the actual question, so a desktop window dragged
   narrow keeps its hover and a phone never does. Same gate the FAQ, the
   speakers row and the manifesto's highlight use. */
.fc-cta-touch { display: none; }
@media (hover: none) {
    .fc-cta-linkless.is-alt .fc-cta-text > span:first-child { display: none; }
    .fc-cta-linkless.is-alt .fc-cta-touch { display: inline; }
}

/* ── The email and the socials: accent on hover ──────────────────────────────
   Written out here rather than left to the `hover:text-accent` utility the
   markup carries. fc.css has an UNLAYERED `a { color: inherit }`, and in the
   cascade an unlayered rule beats a LAYERED Tailwind utility whatever its
   specificity — so that utility never applied and the links stayed muted grey
   under the pointer. Same reason .fc-link is written out in assets/site.css;
   the note there has the details.

   :focus-visible with it, so tabbing to the email shows the same thing. */
.fc-hero-contact a:hover,
.fc-hero-contact a:focus-visible {
    color: var(--color-accent, #0033FF);
}

/* ── Phones and tablets: the panel is the full width, below the blue one ──────
   A small gap above the stack, the links below it and the email and socials
   below those — every space in screen heights, so it all scales together. The
   stack sizes to the words rather than to a share of the screen. What would
   make the words tiny is one long line — an address of 20 ems forces every
   strip down to fit it — so here the PHP lets any line wider than 12 ems break
   onto two, evenly (--fc-lines-sm says which), and sizes the type to the widest
   thing left (--fc-units-sm-max). A two-line strip is simply taller; the type,
   and the air around it, are the same in all three. */
@media (max-width: 1023.98px) {
    /* 3vh, plus the room the first key needs above its strip — and more air
       round the words inside each strip than on desktop. */
    .fc-bands { margin-top: calc(3vh + 1rem - 2.5rem); --fc-air: 0.16; }
    .fc-band {
        --fc-lh: 0.95;   /* line spacing when a strip holds two lines */
        --fc-fs: min(calc(var(--fc-fill) * 100cqw / var(--fc-units-sm-max, 12)), 11vh);
        --fc-band-h: calc(0.74 * var(--fc-fs) / (1 - 2 * var(--fc-air)));
        /* a one-line strip, and each extra line adds one line of spacing */
        height: calc(var(--fc-band-h) + (var(--fc-lines-sm, 1) - 1) * var(--fc-lh) * var(--fc-fs));
    }
    /* With no carousel — no JS, or a reader who asked for less movement — the
       copy stands alone and its trailing gap goes with the wrapping: three
       trailing spaces would pull a balanced two-line title off centre. */
    .fc-band:not(.is-carousel) .fc-band-copy { display: inline; }
    .fc-band:not(.is-carousel) .fc-band-gap { display: none; }
    .fc-band:not(.is-carousel) .fc-band-v {
        white-space: normal;
        text-wrap: balance;
        text-align: center;
        /* Exactly wide enough for the widest line that is meant to stay whole, so
           the ones marked to break always do, and at the even point. */
        max-width: calc(var(--fc-fs) * var(--fc-k) * (var(--fc-units-sm-max, 12) + 0.3));
    }
    .fc-hero-actions {
        flex: none;
        justify-content: flex-start;
        margin-top: 7vh;
    }
    /* The links set like the Get Involved buttons on a phone: one per line,
       ranged left, 2rem apart (that section's gap-8) instead of 0.75rem. */
    .fc-hero-actions ul {
        display: grid;
        justify-items: start;
        gap: 2rem;
    }
    .fc-hero-actions ul > li { margin: 0; }
    .fc-hero-contact {
        position: static;
        padding-inline: 0;
        margin-top: 6vh;
    }
    /* …and the panel ends a small, screen-relative distance below the email,
       instead of stretching to a fixed height and leaving a slab of paper. */
    .fc-hero-paper {
        min-height: 0;
        padding-bottom: 3vh;
    }
}
@media (min-width: 640px) and (max-width: 1023.98px) {
    .fc-bands { margin-top: calc(3vh + 1rem - 3rem); }
}

/* ── Hover: the FAQ's fill, inverted ─────────────────────────────────────────
   The same move as a hovered FAQ row — a panel wiping across from the edge
   OPPOSITE the one the pointer came in over, in 150ms on the same hard-out curve
   — but on a strip that is already blue it wipes WHITE, and the words go black
   while the outlined ones are drawn in the blue. The fill lives on the blue's
   own layer, so it wipes along the strip's slant and taper; a thin blue line
   stays on the strip's top and bottom edges so the white strip still reads as a
   strip against the paper. Which edge it comes from is written into
   --fc-band-from by the script after the strips. Pointer devices only — on a
   touch screen it would stick — plus keyboard focus when the strip is a link. */

/* A strip with a link set in FOSSCOMM → Home is an <a>: a block, with none of
   a link's own colour or underline (an underline would run through every line
   of the words, and their colours are set below). */
a.fc-band,
a.fc-band:hover,
a.fc-band:focus-visible {
    display: block;
    color: inherit;
    text-decoration: none;
    outline: none;
}
.fc-band-slab::before {
    content: "";
    position: absolute;
    inset: 0;
    background: #fff;
    box-shadow:
        inset 0 calc(2px * var(--fc-k)) 0 var(--color-accent, #0033FF),
        inset 0 calc(-2px * var(--fc-k)) 0 var(--color-accent, #0033FF);
    transform: scaleY(0);
    transform-origin: center var(--fc-band-from, top);
    transition: transform 150ms cubic-bezier(0.2, 0, 0, 1);
}
.fc-band-v,
.fc-band-v .is-outline {
    transition: color 100ms ease, -webkit-text-stroke-color 100ms ease;
}
@media (hover: hover) {
    .fc-band:hover .fc-band-slab::before { transform: scaleY(1); }
    .fc-band:hover .fc-band-v { color: var(--color-ink, #0A0A0A); }
}
a.fc-band:focus-visible .fc-band-slab::before { transform: scaleY(1); }
a.fc-band:focus-visible .fc-band-v { color: var(--color-ink, #0A0A0A); }
@supports (-webkit-text-stroke: 1px black) {
    @media (hover: hover) {
        .fc-band:hover .fc-band-v .is-outline { -webkit-text-stroke-color: var(--color-accent, #0033FF); }
    }
    a.fc-band:focus-visible .fc-band-v .is-outline { -webkit-text-stroke-color: var(--color-accent, #0033FF); }
}
@media (prefers-reduced-motion: reduce) {
    .fc-band-slab::before,
    .fc-band-v,
    .fc-band-v .is-outline { transition: none; }
}

/* A *marked* run drawn hollow, weighted like the wordmark's "/26" RELATIVE TO
   ITS OWN SIZE: in em, so it is the same proportion of the letters whatever the
   window. (Matching the logo's pixels instead made it far too heavy on a phone,
   where the logo is huge and these letters are small.)
   0.024em rather than the logo's 0.018em: a line this fine comes out fainter
   through the warp — it is resampled, and compressed further at the far end —
   so it needs a touch more to read at the same weight. It never goes under
   1.2px on screen, below which it greys out. (Both in the k-times-too-big
   drawing space: em already is, the pixel floor is multiplied by k.) Outside the
   @supports so an engine without text-stroke shows solid type, never nothing. */
.fc-band-v .is-outline { color: inherit; }
@supports (-webkit-text-stroke: 1px black) {
    .fc-band-v .is-outline {
        color: transparent;
        -webkit-text-stroke: max(0.024em, calc(1.2px * var(--fc-k))) #fff;
    }
}
/* No transition on the socials: every colour change in the chrome is instant. */
</style>

