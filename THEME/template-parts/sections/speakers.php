<?php
/**
 * Speakers — reads from the `fc_speakers` collection (managed in FOSSCOMM →
 * Speakers). Each entry has: name, photo, bilingual role, bilingual affiliation,
 * bilingual short bio, optional link. Photo falls back to an ASCII portrait so
 * empty rows still read on-theme.
 */
if (!defined('ABSPATH')) {
    exit;
}

$section  = $args['section'] ?? [];
$speakers = fc_section_data($section);
if (!is_array($speakers)) $speakers = [];

$ascii_portrait =
    "▓▓▒▒░░  ░░▒▒▓▓\n" .
    "▒▒░░    ░░▒▒░░\n" .
    "░░░░    ░░░░▒▒\n" .
    "░░██████████░░\n" .
    "░░██▒▒██▒▒██░░\n" .
    "░░██░░░░░░██░░\n" .
    "░░██████████░░\n" .
    "░░░░██████░░░░";

$meta = fc_section_meta('speakers', [
    'title_el' => 'Άνθρωποι που εμφανίστηκαν',
    'title_en' => 'People who showed up.',
]);
fc_section_open($section, array_merge($meta, ['class' => 'fc-section-dots']));
?>
    <?php if (empty($speakers)) : ?>
        <?php fc_render_tba('speakers'); ?>
    <?php else : ?>
        <ol class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-px bg-border border border-border list-none p-0 m-0">
            <?php foreach (array_values($speakers) as $i => $sp) :
                $name        = (string) ($sp['name'] ?? '');
                if ($name === '') continue;
                $photo       = (string) ($sp['photo'] ?? '');
                $role        = fc_bi($sp, 'role');
                $affiliation = fc_bi($sp, 'affiliation');
                $bio         = fc_bi($sp, 'bio');
                $url         = (string) ($sp['url'] ?? '');
                $idx_label   = sprintf('%02d/', $i + 1);
                ?>
                <li class="fc-speaker bg-paper p-6 flex flex-col gap-4 relative group">
                    <!-- Number + optional link arrow, mono header strip -->
                    <div class="flex items-center justify-between font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                        <span><?php echo esc_html($idx_label); ?></span>
                        <?php if ($url !== '') : ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="text-ink-muted hover:text-accent transition-colors"
                               aria-label="<?php echo esc_attr($name); ?>">↗</a>
                        <?php endif; ?>
                    </div>

                    <!-- Square portrait: real photo if uploaded, ASCII fallback otherwise -->
                    <div class="aspect-square w-full border border-border bg-paper relative overflow-hidden">
                        <?php if ($photo !== '') : ?>
                            <img src="<?php echo esc_url($photo); ?>"
                                 alt="<?php echo esc_attr($name); ?>"
                                 class="absolute inset-0 w-full h-full object-cover">
                        <?php else : ?>
                            <pre class="ascii absolute inset-0 m-0 flex items-center justify-center text-[10px] leading-[1] text-ink-faint whitespace-pre text-center" aria-hidden="true"><?php echo esc_html($ascii_portrait); ?></pre>
                        <?php endif; ?>
                        <!-- Bottom-left tag inside the portrait, anchors the layout -->
                        <span class="absolute left-0 bottom-0 px-2 py-1 bg-paper border-t border-r border-border font-mono text-[10px] uppercase tracking-widest text-ink-muted">
                            FC/<?php echo esc_html($idx_label); ?>
                        </span>
                    </div>

                    <!-- Name -->
                    <h3 class="font-display text-xl md:text-2xl leading-[1.05] tracking-tight text-ink m-0">
                        <?php echo fc_format($name); ?>
                    </h3>

                    <!-- Role + affiliation (active language): role on top, affiliation
                         as a second mono line underneath. -->
                    <?php $role_text = fc_one($role); $affil_text = fc_one($affiliation);
                    if ($role_text !== '' || $affil_text !== '') : ?>
                        <div class="font-mono text-[11px] uppercase tracking-widest leading-snug space-y-1">
                            <?php if ($role_text !== '') : ?>
                                <div class="text-ink"><?php echo fc_format($role_text); ?></div>
                            <?php endif; ?>
                            <?php if ($affil_text !== '') : ?>
                                <div class="pt-1 border-t border-border/60 text-ink-faint">
                                    <span><?php echo fc_format($affil_text); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php $bio_text = fc_one($bio); if ($bio_text !== '') : ?>
                        <div class="text-sm leading-relaxed text-ink-muted space-y-2 mt-auto">
                            <p class="m-0"><?php echo fc_format($bio_text); ?></p>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
<?php
fc_section_close();
?>
<style>
/* Hover lifts the whole card together: a faint accent wash on the background
   and the display name turns accent. Subtle — keeps the brutalist read. */
.fc-speaker { transition: background-color 200ms ease; }
.fc-speaker:hover { background-color: color-mix(in oklab, var(--color-accent, #0033FF) 4%, var(--color-paper, #FAFAF7)); }
.fc-speaker:hover h3 { color: var(--color-accent, #0033FF); transition: color 200ms ease; }
</style>
