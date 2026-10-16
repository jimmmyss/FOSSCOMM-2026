<?php
/**
 * One community card — the name, the small lines under it, and the logo.
 *
 * The speakers' card with the portrait swapped for a logo: the name one word
 * per line with its hollow run and the mono lines are the speakers' own classes
 * (template-parts/partials/speakers-style.php), so they come out at the same
 * sizes. The logo sits in a square the size of a portrait, fitted inside it with
 * room round it, so every logo takes the same box whatever its shape. No outline,
 * no greyscale: always in its own colours.
 *
 * Shared by the landing page's row (template-parts/sections/communities.php) and
 * /communities/ (template-parts/communities-page.php).
 *
 * $args:
 *   item   one row from fc_communities_items()
 *   index  its position — decides eager vs lazy loading
 *   id     optional id for the <li>, so /communities/#slug can land on it
 *   sizes  optional `sizes` for the logo, where the card is laid out other
 *          than in the landing page's row (the /communities/ grid)
 */
if (!defined('ABSPATH')) {
    exit;
}

$it = (array) ($args['item'] ?? []);
if (($it['name'] ?? '') === '') {
    return;
}
$i   = (int) ($args['index'] ?? 0);
$id  = (string) ($args['id'] ?? '');
$tag = ($it['url'] ?? '') !== '' ? 'a' : 'div';
?>
<li class="fc-spk"<?php if ($id !== '') echo ' id="' . esc_attr($id) . '"'; ?>>
    <<?php echo $tag; ?> class="fc-spk-card fc-com-card"<?php if ($tag === 'a') echo ' href="' . esc_url($it['url']) . '"'; ?>>
        <?php /* The name as ONE line of text that wraps only where it has to —
                 not a speaker's one-word-per-line stack, which broke "Hack the
                 Box" into three lines in a column that holds two of its words.
                 The words keep their hollow runs (fc_hollow_split()). */ ?>
        <h3 class="fc-spk-name fc-com-name" aria-label="<?php echo esc_attr($it['name']); ?>">
            <span aria-hidden="true"><?php echo implode(' ', (array) $it['words']); ?></span>
        </h3>
        <?php if (!empty($it['roles'])) : ?>
            <ul class="fc-spk-roles">
                <?php foreach ($it['roles'] as $role) : ?>
                    <li><?php echo fc_format($role); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
        /* The logo's own width, for `sizes`: the column is
           max(160px, clamp(195px, 17vw, 290px)) and the logo 45% of it (the
           box's 27.5% side padding) — 88px up to a 1147px window, 7.65vw, then
           131px from 1706px. It was described as the PORTRAIT's 260/420px, two
           to three times too wide, so the browser fetched and decoded a file
           that much bigger than it ever drew. A vector has no srcset and
           ignores this. */
        $logo_sizes = (string) ($args['sizes'] ?? '');
        if ($logo_sizes === '') $logo_sizes = '(max-width: 1147px) 88px, (max-width: 1705px) 7.65vw, 131px';
        $in = (string) ($it['photo_alt'] ?? '');
        ?>
        <div class="fc-com-logo-box<?php if ($in !== '' && ($it['photo'] ?? '') !== '') echo ' fc-logo-in'; ?>">
            <?php if (($it['photo'] ?? '') !== '') :
                $img = fc_media_img_attrs($it['photo']);
                /* The logo for inside the cursor's highlight, if there is one:
                   handed to assets/spotlight-dots.js, whose copy of this box
                   (the part inside the highlight) shows it instead. */
                $in_img = $in !== '' ? fc_media_img_attrs($in) : null;
                ?>
                <img class="fc-com-logo"
                     src="<?php echo esc_url($img['src']); ?>"
                     <?php if ($img['srcset'] !== '') : ?>
                     srcset="<?php echo esc_attr($img['srcset']); ?>"
                     <?php endif; ?>
                     <?php if ($img['srcset'] !== '' || ($in_img && $in_img['srcset'] !== '')) : ?>
                     sizes="<?php echo esc_attr($logo_sizes); ?>"
                     <?php endif; ?>
                     <?php if ($in_img) : ?>
                     data-fc-in-src="<?php echo esc_url($in_img['src']); ?>"
                     <?php if ($in_img['srcset'] !== '') : ?>data-fc-in-srcset="<?php echo esc_attr($in_img['srcset']); ?>"<?php endif; ?>
                     <?php endif; ?>
                     alt="<?php echo esc_attr($it['name']); ?>"
                     draggable="false" decoding="async"
                     loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>">
            <?php endif; ?>
        </div>
    </<?php echo $tag; ?>>
</li>
