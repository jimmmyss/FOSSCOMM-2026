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
            // Active language only: one question line that scrambles into its
            // own answer in place (assets/faq.js binds to [data-fc-faq-line]).
            $q = fc_one(fc_bi($row, 'question'));
            $a = fc_one(fc_bi($row, 'answer'));
            if ($q === '') continue;
            $a_plain = fc_strip_inline_links($a);
            $a_html  = fc_format_inline_links($a);
            ?>
            <li class="border-b border-border" data-fc-faq-item>
                <!-- Using a div with role="button" (not a <button>) so the answer HTML
                     can legally contain <a> tags after the scramble swap. -->
                <div role="button" tabindex="0" class="w-full flex items-baseline gap-4 py-5 text-left cursor-pointer" data-fc-faq-toggle aria-expanded="false">
                    <span class="font-mono text-sm text-accent w-6" data-fc-faq-marker>[+]</span>
                    <span class="flex-1">
                        <span class="font-display text-xl md:text-2xl block"
                              data-fc-faq-line
                              data-fc-q="<?php echo esc_attr($q); ?>"
                              data-fc-a="<?php echo esc_attr($a_plain); ?>"
                              data-fc-a-html="<?php echo esc_attr($a_html); ?>"><?php echo esc_html($q); ?></span>
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
