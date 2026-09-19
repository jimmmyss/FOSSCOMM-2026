<?php
/**
 * The two ring filters every portrait uses — one at rest, one highlighted —
 * printed ONCE per page and referenced by class from the stylesheet.
 *
 * It used to be one filter per card, so that each card's feFlood could inherit
 * that card's own --fc-spk-rim and the colour could be animated. That cost a
 * filter element per speaker, doubled again by the belt's copies, and every one
 * of them was a live SVG filter graph; the colour fade it bought re-ran a
 * Gaussian blur and a threshold on every frame of the transition. Two fixed
 * filters and an instant swap cost nothing and look the same in motion.
 *
 * The shape, and why it is a blur rather than a spread: blurring the alpha turns
 * the silhouette into a distance ramp — at d pixels outside the edge the blurred
 * alpha is about 0.5 * erfc(d / (sigma * sqrt(2))) — and slicing that ramp
 * steeply at a chosen height gives a ring of a chosen WIDTH with a HARD edge.
 * feFuncA computes A' = slope * A + intercept, and the edge lands where
 * A' = 0.5, so intercept = 0.5 - slope * blurredAlphaAt(width). With sigma 3 and
 * a 4.5px ring that is a slope of 60 and an intercept of about -3.6. The slope
 * is the sharpness dial; drop it toward 20 if the curves ever look stepped.
 *
 * This replaced feMorphology, which dilates with a RECTANGULAR kernel — fine at
 * 2px, visibly chunky at the corners once the ring got fat — and before that
 * eight chained drop-shadows, which could not be made crisp at all.
 *
 * Attributes, each load-bearing:
 *   color-interpolation-filters="sRGB" — the default is linearRGB, and the
 *     8-bit round trip darkens and bands the anti-aliased edge.
 *   in="SourceAlpha" — blurring SourceGraphic would spread each channel
 *     separately and fringe the colour.
 *   the 120% region — the default clips the OUTPUT, so a ring drawn outside it
 *     is simply cut off.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!empty($GLOBALS['fc_speaker_filters_printed'])) {
    return;
}
$GLOBALS['fc_speaker_filters_printed'] = true;

$style = fc_speakers_style();
$rims  = ['rest' => $style['rim'], 'hot' => $style['rim_hover']];
?>
<svg class="fc-spk-defs" width="0" height="0" aria-hidden="true" focusable="false">
    <?php foreach ($rims as $state => $colour) : ?>
        <filter id="fc-spk-rim-<?php echo esc_attr($state); ?>"
                x="-10%" y="-10%" width="120%" height="120%"
                color-interpolation-filters="sRGB">
            <feGaussianBlur in="SourceAlpha" stdDeviation="3" result="spread"/>
            <feComponentTransfer in="spread" result="ring">
                <feFuncA type="linear" slope="60" intercept="-3.6"/>
            </feComponentTransfer>
            <feFlood result="paint" flood-color="<?php echo esc_attr($colour); ?>"/>
            <feComposite in="paint" in2="ring" operator="in" result="outline"/>
            <feMerge>
                <feMergeNode in="outline"/>
                <feMergeNode in="SourceGraphic"/>
            </feMerge>
        </filter>
    <?php endforeach; ?>
</svg>
