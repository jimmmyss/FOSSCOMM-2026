<?php
/**
 * One speaker card — the name, the roles and the cut-out portrait with its ring.
 *
 * Shared by the landing page's row (template-parts/sections/speakers.php) and
 * the full list at /speakers/ (template-parts/speakers-page.php), so the two
 * are the same card by construction rather than by two copies kept in step.
 * The styles live in template-parts/partials/speakers-style.php, which both
 * callers print once.
 *
 * $args:
 *   card         one row from fc_speaker_cards()
 *   index        its position — decides eager vs lazy loading
 *   sizes        the `sizes` attribute for the portrait, describing how wide
 *                the box is in THIS layout
 *   centre_item  mark the card for assets/centre-highlight.js, which lights
 *                whichever one is in the middle of a touch screen. The row does
 *                not use it: it moves, so nothing stays in the middle.
 */
if (!defined('ABSPATH')) {
    exit;
}

$card = (array) ($args['card'] ?? []);
if (($card['name'] ?? '') === '') {
    return;
}
$i           = (int) ($args['index'] ?? 0);
$sizes       = (string) ($args['sizes'] ?? '');
$centre_item = !empty($args['centre_item']);

/* The ring around the cut-out is drawn by one of two shared SVG filters —
 * template-parts/partials/speaker-filters.php — which the stylesheet picks
 * between by state. A card carries no filter of its own: it used to, one each,
 * so that the colour could fade, and that was a live filter graph per speaker
 * (doubled again by the belt's copies) for a 50ms transition. */
$tag = $card['url'] !== '' ? 'a' : 'div';
?>
<li class="fc-spk"<?php if ($centre_item) echo ' data-fc-centre-item'; ?>>
    <<?php echo $tag; ?> class="fc-spk-card"
        <?php if ($card['url'] !== '') : ?>
            href="<?php echo esc_url($card['url']); ?>"
        <?php endif; ?>>

        <?php if ($card['online']) : ?>
            <?php /* ABOVE THE NAME, and outside .fc-spk-photo. It was briefly
                     inside that box, which carries the ring filter — so the ring
                     was drawing a blurred blue outline around the label's own
                     letters. A filter applies to everything in its element, not
                     just the image. */ ?>
            <span class="fc-spk-online">
                <i aria-hidden="true"></i><?php
                    /* The text is wrapped, not left loose. A bare text node in a
                       flex container becomes an ANONYMOUS flex item whose box is
                       the whole line box, and centring the dot against that put it
                       a pixel low against all-caps type, which has no descenders
                       to fill the bottom of the line. */
                    echo '<span>' . esc_html('[' . fc_t('speaker_online', 'online') . ']') . '</span>';
                ?>
            </span>
        <?php endif; ?>

        <?php // aria-label carries the whole name: split across <span>s a screen
              // reader would otherwise read it as separate words on separate
              // lines. Each word is already HTML from fc_hollow_split() —
              // escaped, with any *starred* part wrapped in .is-outline. ?>
        <h3 class="fc-spk-name" aria-label="<?php echo esc_attr($card['name']); ?>">
            <?php foreach ($card['words'] as $word) : ?>
                <span aria-hidden="true"><?php echo $word; ?></span>
            <?php endforeach; ?>
        </h3>

        <?php if ($card['roles']) : ?>
            <ul class="fc-spk-roles">
                <?php foreach ($card['roles'] as $role) : ?>
                    <li><?php echo fc_format($role); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="fc-spk-photo">
            <?php if ($card['photo'] !== '') : ?>
                <?php
                /* ONE copy. There were briefly two, cross-faded with opacity to
                 * animate the hover colour — and that is what turned the outline
                 * muddy: the ring's outer edge is anti-aliased, so it is
                 * semi-transparent in BOTH copies, and that fringe composites
                 * orange over blue and lands on brown at every opacity.
                 *
                 * The carousel needs the image to be same-origin and readable: it
                 * samples the alpha into a mask so hovering a transparent corner
                 * does not count as hovering the speaker. Media-library uploads
                 * are.
                 *
                 * srcset, because the stored URL is the full original, which can
                 * be 1536px of transparent PNG — about 7MB of decoded bitmap per
                 * speaker on a phone, doubled by the belt's clones. `sizes` is
                 * passed in, because how wide the box is depends on the layout
                 * this card is in.
                 *
                 * draggable="false": an image's native drag-and-drop competes
                 * with the carousel's own, and wins — grab a photo and the belt
                 * does not move at all.
                 */
                $shot = fc_media_img_attrs($card['photo'], $sizes);
                ?>
                <img class="fc-spk-shot"
                     src="<?php echo esc_url($shot['src']); ?>"
                     <?php if ($shot['srcset'] !== '') : ?>
                     srcset="<?php echo esc_attr($shot['srcset']); ?>"
                     sizes="<?php echo esc_attr($shot['sizes']); ?>"
                     <?php endif; ?>
                     <?php if ($shot['width'] && $shot['height']) : ?>
                     width="<?php echo (int) $shot['width']; ?>"
                     height="<?php echo (int) $shot['height']; ?>"
                     <?php endif; ?>
                     alt="<?php echo esc_attr($card['name']); ?>"
                     draggable="false"
                     loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>"
                     decoding="async">
            <?php endif; ?>
        </div>
    </<?php echo $tag; ?>>
</li>
