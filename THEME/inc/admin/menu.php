<?php
/**
 * Top-level FOSSCOMM admin menu. Per-section sub-pages register themselves.
 */
if (!defined('ABSPATH')) {
    exit;
}

const FC_ADMIN_SLUG     = 'fosscomm';
const FC_ADMIN_CAP      = 'manage_options';

add_action('admin_menu', 'fc_register_admin_menu', 5);
function fc_register_admin_menu() {
    add_menu_page(
        'FOSSCOMM',
        'FOSSCOMM',
        FC_ADMIN_CAP,
        FC_ADMIN_SLUG,
        'fc_admin_sections_page',
        'dashicons-editor-code',
        58
    );
    add_submenu_page(
        FC_ADMIN_SLUG,
        __('Sections', 'fosscomm'),
        __('Sections', 'fosscomm'),
        FC_ADMIN_CAP,
        FC_ADMIN_SLUG,
        'fc_admin_sections_page'
    );
}

add_action('admin_enqueue_scripts', 'fc_admin_assets');
function fc_admin_assets($hook) {
    if (strpos((string) $hook, FC_ADMIN_SLUG) === false) {
        return;
    }
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_media();
    wp_register_style('fc-admin', false, [], FC_THEME_VERSION);
    wp_enqueue_style('fc-admin');
    wp_add_inline_style('fc-admin', fc_admin_inline_css());
    wp_register_script('fc-admin', false, ['jquery', 'jquery-ui-sortable'], FC_THEME_VERSION, true);
    wp_enqueue_script('fc-admin');
    wp_add_inline_script('fc-admin', fc_admin_inline_js());
}

function fc_admin_inline_css(): string {
    return <<<CSS
    .fc-wrap { max-width: 1100px; }
    .fc-wrap h1 { font-weight: 700; letter-spacing: -0.02em; }
    .fc-tabs { display: flex; gap: 0; border-bottom: 1px solid #ccd0d4; margin: 0 0 1rem; }
    .fc-tabs button { background: transparent; border: 0; border-bottom: 2px solid transparent; padding: 0.5rem 1rem; font-family: ui-monospace, Menlo, monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #50575e; cursor: pointer; }
    .fc-tabs button.active { border-bottom-color: #0033ff; color: #0033ff; }
    .fc-pane { display: none; }
    .fc-pane.active { display: block; }
    .fc-field { margin-bottom: 1.25rem; }
    .fc-field > label { display: block; font-weight: 600; margin-bottom: 0.35rem; }
    .fc-field input[type=text], .fc-field input[type=email], .fc-field input[type=url], .fc-field input[type=number], .fc-field input[type=date], .fc-field textarea { width: 100%; }
    .fc-field textarea.ascii { font-family: ui-monospace, Menlo, monospace; line-height: 1.2; white-space: pre; overflow-x: auto; }
    .fc-media { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .fc-media-preview { width: 150px; height: 60px; border: 1px dashed #ccd0d4; background: #fff repeating-linear-gradient(45deg,#f6f7f7 0 6px,#fff 6px 12px); display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
    .fc-media-preview:empty::before { content: "no file"; color: #a7aaad; font-size: 11px; font-family: ui-monospace, Menlo, monospace; }
    .fc-media-preview img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; display: block; }
    .fc-media-preview .fc-media-file { font-family: ui-monospace, Menlo, monospace; font-size: 11px; color: #50575e; padding: 0 0.5rem; text-align: center; word-break: break-all; }
    .fc-repeater { border: 1px solid #ccd0d4; padding: 0; background: #fff; }
    .fc-repeater-row { border-bottom: 1px solid #e5e5e5; padding: 1rem; position: relative; }
    .fc-repeater-row:last-child { border-bottom: 0; }
    .fc-repeater-row .fc-row-handle { cursor: grab; color: #999; margin-right: 0.5rem; }
    .fc-repeater-row .fc-row-delete { position: absolute; top: 0.75rem; right: 0.75rem; background: transparent; border: 0; color: #b32d2e; cursor: pointer; font-family: ui-monospace, Menlo, monospace; font-size: 11px; }
    .fc-add-row { margin-top: 0.75rem; }
    /* Nested repeater (a tier's sponsors). Tinted and inset so the boundary
       between "this row" and "the rows inside it" is visible at a glance —
       without that, a page of identical white boxes gives no clue which delete
       button removes a sponsor and which removes the whole tier. */
    .fc-field-repeater { margin-top: 1rem; }
    .fc-repeater-wrap.is-nested { margin-top: 0.35rem; }
    .fc-repeater-wrap.is-nested > .fc-repeater { background: #f6f7f7; border-color: #dcdcde; }
    .fc-repeater-wrap.is-nested > .fc-repeater > .fc-repeater-row { background: #fff; margin: 0.5rem; border: 1px solid #e5e5e5; border-radius: 2px; }
    .fc-repeater-wrap.is-nested > .fc-repeater:empty::before { content: "no sponsors in this tier yet"; display: block; padding: 0.9rem 1rem; color: #a7aaad; font-size: 11px; font-family: ui-monospace, Menlo, monospace; }
    .fc-colour { display: inline-flex; align-items: center; gap: 0.5rem; }
    .fc-colour .fc-colour-pick { width: 48px; height: 32px; padding: 2px; vertical-align: middle; }
    .fc-colour .fc-colour-hex { width: 110px; }
    .fc-sections-table { width: 100%; border-collapse: collapse; background: #fff; }
    .fc-sections-table th, .fc-sections-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #e5e5e5; text-align: left; }
    .fc-sections-table tr.inactive { opacity: 0.55; }
    .fc-sections-table .fc-handle { cursor: grab; color: #888; width: 24px; text-align: center; user-select: none; }
    .fc-sections-table .fc-type { font-family: ui-monospace, Menlo, monospace; font-size: 11px; color: #50575e; text-transform: uppercase; letter-spacing: 0.08em; }
    .fc-callout { background: #fafaf7; border-left: 3px solid #0033ff; padding: 0.75rem 1rem; margin: 1rem 0; font-size: 13px; }
    .fc-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 720px) { .fc-grid-2 { grid-template-columns: 1fr; } }
CSS;
}

function fc_admin_inline_js(): string {
    return <<<'JS'
    (function($) {
        $(function() {
            // Bilingual tabs
            $(document).on('click', '.fc-tabs button', function() {
                var $btn = $(this);
                var pane = $btn.data('pane');
                var $wrap = $btn.closest('.fc-bilingual');
                $wrap.find('.fc-tabs button').removeClass('active');
                $btn.addClass('active');
                $wrap.find('.fc-pane').removeClass('active');
                $wrap.find('.fc-pane[data-pane="' + pane + '"]').addClass('active');
            });

            // Sections drag-reorder
            var $tbody = $('#fc-sections-tbody');
            if ($tbody.length) {
                $tbody.sortable({
                    handle: '.fc-handle',
                    placeholder: 'fc-row-placeholder',
                    update: function() {
                        $tbody.find('tr').each(function(idx) {
                            $(this).find('.fc-order-input').val((idx + 1) * 10);
                        });
                    }
                });
            }

            // Repeater rows
            //
            // Everything below is written for NESTING (a repeater inside a row —
            // Sponsors puts its sponsors inside a tier). Three habits that were
            // fine while every repeater was flat are wrong once one contains
            // another, and all three fail silently by mangling POST keys:
            //   • .find() reaches into nested repeaters; .children() must be used
            //     wherever "this repeater's own rows" is meant.
            //   • the row index to rewrite is not always the FIRST [n] in a name,
            //     so names are rewritten by their repeater's own prefix instead.
            //   • .data() caches the attribute it first read, so a template or
            //     name rewritten later would still hand out the stale copy —
            //     these are all read and written through .attr().
            $(document).on('click', '.fc-row-delete', function() {
                if (!confirm('Delete this row?')) return;
                var $rep = $(this).closest('.fc-repeater');
                $(this).closest('.fc-repeater-row').remove();
                fcReindexRepeater($rep);
            });

            $(document).on('click', '.fc-add-row-btn', function() {
                var $btn = $(this);
                // .children(), not .find(): an OUTER add-button's wrap also
                // contains every nested repeater, and .find() would return those
                // too — appending the new row to whichever came first.
                var $rep = $btn.closest('.fc-repeater-wrap').children('.fc-repeater');
                var tpl = $rep.attr('data-template');
                if (!tpl) return;
                // Only THIS depth's placeholder. A nested template carries the
                // outer index already resolved plus its own __INDEX1__, which
                // must survive until that inner repeater does its own adding.
                var ph = $rep.attr('data-placeholder') || '__INDEX__';
                var nextIdx = $rep.children('.fc-repeater-row').length;
                $rep.append(tpl.split(ph).join(nextIdx));
                fcReindexRepeater($rep);
                fcInitSortables();
            });

            // Media picker (WP media library) — delegated so cloned repeater rows work.
            // The wrapping .fc-media may opt-in to a different library type / button
            // text via data-fc-media-type ("image" by default, e.g. "application/pdf").
            $(document).on('click', '.fc-media-pick', function(e) {
                e.preventDefault();
                var $wrap  = $(this).closest('.fc-media');
                var $input = $wrap.find('.fc-media-input');
                var libType = $wrap.data('fc-media-type') || 'image';
                var isImage = (libType === 'image');
                var frame = wp.media({
                    title: isImage ? 'Select image' : 'Select file',
                    button: { text: 'Use this file' },
                    library: { type: libType },
                    multiple: false
                });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    // data-fc-media-full forces the ORIGINAL upload instead of the
                    // "medium" derivative. Essential for pixel art: WordPress
                    // resamples its derivatives, which both blurs the pixels and
                    // changes the width the sprite-sheet cell arithmetic depends on.
                    var wantFull = !!$wrap.data('fc-media-full');
                    var url = (isImage && !wantFull && att.sizes && att.sizes.medium)
                        ? att.sizes.medium.url
                        : att.url;
                    $input.val(url);
                    if (isImage) {
                        $wrap.find('.fc-media-preview').html('<img src="' + url + '" alt="">');
                    } else {
                        var name = (att.filename || url.split('/').pop()) + (att.filesizeHumanReadable ? ' · ' + att.filesizeHumanReadable : '');
                        $wrap.find('.fc-media-preview').html('<span class="fc-media-file">' + name + '</span>');
                    }
                    $wrap.find('.fc-media-clear').show();
                    $wrap.find('.fc-media-pick').text(isImage ? 'Replace image' : 'Replace file');
                });
                frame.open();
            });

            $(document).on('click', '.fc-media-clear', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.fc-media');
                var libType = $wrap.data('fc-media-type') || 'image';
                var isImage = (libType === 'image');
                $wrap.find('.fc-media-input').val('');
                $wrap.find('.fc-media-preview').empty();
                $(this).hide();
                $wrap.find('.fc-media-pick').text(isImage ? 'Select image' : 'Select file');
            });

            // Colour picker ↔ hex box inside repeater rows. DELEGATED, because a
            // row added after page load has inputs that
            // fc_admin_colour_sync_script()'s one-shot querySelectorAll never saw.
            $(document).on('input', '.fc-colour-pick', function() {
                $(this).closest('.fc-colour').find('.fc-colour-hex')
                    .val(String(this.value).toUpperCase());
            });
            $(document).on('input', '.fc-colour-hex', function() {
                var v = $.trim(this.value);
                if (/^#[0-9a-fA-F]{6}$/.test(v)) {
                    $(this).closest('.fc-colour').find('.fc-colour-pick').val(v);
                }
            });

            fcInitSortables();

            function fcInitSortables() {
                $('.fc-repeater').each(function() {
                    var $rep = $(this);
                    if ($rep.attr('data-fc-sortable')) return;   // never twice
                    $rep.attr('data-fc-sortable', '1');
                    $rep.sortable({
                        handle: '.fc-row-handle',
                        // This repeater's OWN rows. Without it jQuery UI's default
                        // of "> *" is right by luck, and a nested row dragged out
                        // of its tier would be a data-loss bug rather than a
                        // cosmetic one. (Nested sortables do not fight each other:
                        // jQuery UI's mouse widget marks an event handled, so only
                        // the innermost one starts a drag.)
                        items: '> .fc-repeater-row',
                        update: function() { fcReindexRepeater($rep); }
                    });
                });
            }

            /**
             * Renumber one repeater's own rows, 0..n-1.
             *
             * Everything that carries a row index is rewritten by PREFIX rather
             * than by "the first [n] in the string", because inside a nested
             * repeater the first [n] belongs to the OUTER row. A field at
             * fc_rows[2][sponsors][5][name] moving to tier 1 must become
             * fc_rows[1][sponsors][5][name] — the 5 stays.
             *
             * Three things carry it, and missing any one of them silently corrupts
             * the save: the inputs' `name`, a nested repeater's `data-name`, and
             * that nested repeater's `data-template` (whose row template has the
             * outer index baked in, ready for its own Add button).
             */
            function fcReindexRepeater($rep) {
                var base = String($rep.attr('data-name') || '');
                if (!base) return;
                var head = base + '[';

                $rep.children('.fc-repeater-row').each(function(idx) {
                    var $row = $(this);
                    var swap = function(s) {
                        return base + s.slice(base.length)
                            .replace(/^\[[^\]]*\]/, '[' + idx + ']');
                    };

                    $row.find('[name]').each(function() {
                        var n = $(this).attr('name');
                        if (!n || n.indexOf(head) !== 0) return;
                        $(this).attr('name', swap(n));
                    });

                    $row.find('.fc-repeater').each(function() {
                        var $inner = $(this);
                        var was = String($inner.attr('data-name') || '');
                        if (was.indexOf(head) !== 0) return;
                        var now = swap(was);
                        if (now === was) return;
                        $inner.attr('data-name', now);
                        var tpl = $inner.attr('data-template');
                        if (tpl) $inner.attr('data-template', tpl.split(was).join(now));
                    });
                });
            }
        });
    })(jQuery);
JS;
}
