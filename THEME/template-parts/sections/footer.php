<?php
/**
 * Footer — three bilingual columns. Each: title, an optional paragraph
 * (rendered only when non-empty), and a list of links.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$data    = fc_section_data($section);

$columns = [];
foreach ([1, 2, 3] as $col) {
    $columns[] = [
        'title' => fc_bi($data, "col{$col}_title"),
        'body'  => fc_bi($data, "col{$col}_body"),
        'links' => (array) ($data["col{$col}_links"] ?? []),
    ];
}
?>
<!-- fc-section-dots marks the footer as transparent so the global wave
     canvas (assets/wave-bg.js) shows through it. The footer doesn't go
     through fc_section_open(), so no default bg-paper class is added — the
     marker is purely a label for grep / consistency with the other dots
     sections (speakers, news, sponsors). -->
<footer class="fc-section-dots border-t border-border">
    <div class="max-w-[1440px] mx-auto px-4 md:px-8 py-16 grid grid-cols-1 md:grid-cols-3 gap-12">
        <?php foreach ($columns as $c) :
            $ctitle = $c['title'];
            $cbody  = $c['body'];
            $clinks = $c['links'];
            $has_body = ($cbody['en'] !== '' || $cbody['el'] !== '');
            if ($ctitle['el'] === '' && $ctitle['en'] === '' && !$has_body && empty($clinks)) continue;
            ?>
            <div>
                <?php if ($ctitle['el'] !== '' || $ctitle['en'] !== '') : ?>
                    <div class="font-mono text-[10px] uppercase tracking-widest text-ink-muted mb-3">
                        <?php echo fc_bi_inline($ctitle['el'], $ctitle['en']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($has_body) : $cbody_text = fc_one($cbody); ?>
                    <div class="text-sm leading-relaxed space-y-2 mb-4">
                        <div><?php echo wp_kses_post(fc_format_block($cbody_text)); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($clinks)) : ?>
                    <ul class="text-sm leading-relaxed">
                        <?php foreach ($clinks as $link) :
                            if (!is_array($link)) continue;
                            $label = fc_bi($link, 'label');
                            $hover = fc_bi($link, 'hover_label');
                            $url   = (string) ($link['url'] ?? '');
                            if ($label['el'] === '' && $label['en'] === '') continue;

                            // Active language only. Hover label (if any) scrambles
                            // in over the default on mouseenter (assets/hover-scramble.js).
                            $default = fc_one($label);
                            $alt = fc_one($hover);
                            $has_hover = ($hover['en'] !== '' || $hover['el'] !== '');
                            ?>
                            <li>
                                <?php if ($url !== '') : ?>
                                    <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noreferrer"
                                       class="no-underline hover:text-accent transition-colors"
                                       <?php if ($has_hover) echo 'data-fc-hover-link'; ?>>
                                        <?php if ($has_hover) : ?>
                                            <span data-fc-hover-default="<?php echo esc_attr($default); ?>" data-fc-hover-alt="<?php echo esc_attr($alt); ?>"><?php echo esc_html($default); ?></span>
                                        <?php else : ?>
                                            <?php echo fc_bi_inline($label['el'], $label['en']); ?>
                                        <?php endif; ?>
                                        <span aria-hidden="true">→</span>
                                    </a>
                                <?php else : ?>
                                    <span><?php echo fc_bi_inline($label['el'], $label['en']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</footer>
