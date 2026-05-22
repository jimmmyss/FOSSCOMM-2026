<?php
/**
 * FAQ — bilingual "scramble swap". No accordion: hovering or clicking a
 * question glitches its title text into the answer IN PLACE (same span, so
 * same size/colour), for both the EN and EL lines, and the marker flips
 * [+] → [−]. Click locks it open; hovering away then no longer closes it.
 *
 * Behaviour lives in assets/faq.js, driven by window.fcScramble (assets/
 * scramble.js). The island is named "faq-scramble" (not "faq-list") so the
 * legacy accordion in assets/dist/fc.js never binds to it.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section = $args['section'] ?? [];
$rows    = fc_section_data($section);

$meta = fc_section_meta('faq', [
    'title_el' => 'Λογικές ερωτήσεις, απλές απαντήσεις.',
    'title_en' => 'Reasonable questions, plain answers.',
]);
fc_section_open($section, $meta);
?>
    <ul class="border-t border-border" <?php echo fc_island_attrs('faq-scramble'); ?>>
        <?php foreach ($rows as $row) :
            $q = fc_bi($row, 'question');
            $a = fc_bi($row, 'answer');
            if ($q['el'] === '' && $q['en'] === '') continue;
            $a_plain_en = fc_strip_inline_links($a['en']);
            $a_plain_el = fc_strip_inline_links($a['el']);
            $a_html_en  = fc_format_inline_links($a['en']);
            $a_html_el  = fc_format_inline_links($a['el']);
            ?>
            <li class="border-b border-border" data-fc-faq-item>
                <!-- Using a div with role="button" (not a <button>) so the answer HTML
                     can legally contain <a> tags after the scramble swap. -->
                <div role="button" tabindex="0" class="w-full flex items-baseline gap-4 py-5 text-left cursor-pointer" data-fc-faq-toggle aria-expanded="false">
                    <span class="font-mono text-sm text-accent w-6" data-fc-faq-marker>[+]</span>
                    <span class="flex-1">
                        <?php if ($q['en'] !== '') : ?>
                            <span class="font-display text-xl md:text-2xl block" lang="en"
                                  data-fc-faq-line
                                  data-fc-q="<?php echo esc_attr($q['en']); ?>"
                                  data-fc-a="<?php echo esc_attr($a_plain_en); ?>"
                                  data-fc-a-html="<?php echo esc_attr($a_html_en); ?>"><?php echo esc_html($q['en']); ?></span>
                        <?php endif; ?>
                        <?php if ($q['el'] !== '') : ?>
                            <span class="font-display text-base text-ink-muted block mt-1"
                                  data-fc-faq-line
                                  data-fc-q="<?php echo esc_attr($q['el']); ?>"
                                  data-fc-a="<?php echo esc_attr($a_plain_el); ?>"
                                  data-fc-a-html="<?php echo esc_attr($a_html_el); ?>"><?php echo esc_html($q['el']); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (empty($rows)) : ?>
            <li><?php fc_render_tba('faq'); ?></li>
        <?php endif; ?>
    </ul>
<?php
fc_section_close();
