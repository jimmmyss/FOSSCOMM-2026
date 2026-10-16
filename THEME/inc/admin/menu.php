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
    .fc-repeater:empty::before { content: "Nothing here yet — use the button below to add the first one."; display: block; padding: 1rem; color: #8c8f94; font-style: italic; }
    .fc-repeater-row { border-bottom: 1px solid #e5e5e5; position: relative; }
    .fc-repeater-row:last-child { border-bottom: 0; }
    /* The row's header: handle, arrow + summary (one button: the whole line
       opens the row), and the row's actions on the right. */
    .fc-row-head { display: flex; align-items: center; gap: 0.5rem; padding: 0.55rem 0.75rem; }
    .fc-repeater-row.is-open > .fc-row-head { border-bottom: 1px solid #f0f0f1; background: #fbfbfc; }
    .fc-row-handle { cursor: grab; color: #a7aaad; user-select: none; padding: 0 0.15rem; }
    .fc-row-toggle { flex: 1 1 auto; min-width: 0; display: flex; align-items: center; gap: 0.5rem; background: none; border: 0; padding: 0.25rem 0; text-align: left; cursor: pointer; color: #1d2327; font-size: 13px; }
    .fc-row-toggle:hover .fc-row-summary { color: #0033ff; }
    .fc-row-arrow { display: inline-block; width: 1em; color: #8c8f94; transition: transform 120ms ease; }
    .fc-repeater-row.is-open > .fc-row-head .fc-row-arrow { transform: rotate(90deg); }
    .fc-row-summary { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
    .fc-row-summary .fc-sum-meta { font-weight: 400; color: #646970; font-family: ui-monospace, Menlo, monospace; font-size: 11px; margin-right: 0.6rem; }
    .fc-row-summary.is-empty { color: #8c8f94; font-style: italic; font-weight: 400; }
    .fc-row-actions { flex: 0 0 auto; display: flex; gap: 0.9rem; }
    .fc-row-actions .button-link { font-size: 12px; text-decoration: none; }
    .fc-row-actions .fc-row-delete { color: #b32d2e; }
    .fc-row-body { display: none; padding: 1rem 1rem 0.25rem 2.4rem; }
    .fc-repeater-row.is-open > .fc-row-body { display: block; }
    .fc-repeater-row.is-filtered-out { display: none; }
    .fc-repeater-row.is-new > .fc-row-head { background: #f0f6fc; }
    .fc-repeater-tools { display: flex; align-items: center; gap: 0.5rem; margin: 0 0 0.5rem; color: #8c8f94; }
    .fc-repeater-tools .button-link { font-size: 12px; text-decoration: none; }
    .fc-repeater-filter { margin-left: auto; min-width: 220px; }
    .fc-repeater-count { font-size: 12px; color: #646970; }
    .fc-add-row { margin-top: 0.75rem; }
    /* Save, always within reach at the foot of the window. */
    .fc-savebar { position: sticky; bottom: 0; z-index: 10; margin: 1.5rem 0 0 !important; padding: 0.75rem 0; background: #f0f0f1; border-top: 1px solid #dcdcde; display: flex; align-items: center; gap: 1rem; }
    .fc-savebar .fc-dirty-note { color: #996800; font-size: 12px; display: none; }
    .fc-savebar.is-dirty .fc-dirty-note { display: inline; }
    /* A page in sections: tabs across the top, one panel at a time. */
    .fc-page-tabs { display: flex; gap: 0; border-bottom: 1px solid #c3c4c7; margin: 1rem 0 1.25rem; }
    .fc-page-tabs a { padding: 0.6rem 1rem; text-decoration: none; color: #50575e; border-bottom: 2px solid transparent; margin-bottom: -1px; font-weight: 600; }
    .fc-page-tabs a:hover { color: #0033ff; }
    .fc-page-tabs a.active { color: #0033ff; border-bottom-color: #0033ff; }
    .fc-page-tabs .fc-tab-count { font-weight: 400; color: #8c8f94; margin-left: 0.3rem; }
    .fc-page-panel { display: none; }
    .fc-page-panel.active { display: block; }
    .fc-panel-help { color: #50575e; margin: 0 0 1rem; max-width: 760px; }
    /* Nested repeater (a tier's sponsors). Tinted and inset so the boundary
       between "this row" and "the rows inside it" is visible at a glance —
       without that, a page of identical white boxes gives no clue which delete
       button removes a sponsor and which removes the whole tier. */
    .fc-field-repeater { margin-top: 1rem; }
    .fc-repeater-wrap.is-nested { margin-top: 0.35rem; }
    .fc-repeater-wrap.is-nested > .fc-repeater { background: #f6f7f7; border-color: #dcdcde; }
    .fc-repeater-wrap.is-nested > .fc-repeater > .fc-repeater-row { background: #fff; margin: 0.5rem; border: 1px solid #e5e5e5; border-radius: 2px; }
    .fc-repeater-wrap.is-nested > .fc-repeater:empty::before { font-style: normal; }
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
            /* ── Rows: open/close, summaries, duplicate, delete ─────────────── */

            // A row's one-line summary, from the fields its repeater names in
            // data-summary: a bilingual field reads EN then EL, a select reads
            // the chosen option's text. The first named field is the headline;
            // the rest ride before it, small, as metadata.
            function fcRowSummary($row) {
                var $rep = $row.parent('.fc-repeater');
                var keys = String($rep.attr('data-summary') || '').split(',').filter(Boolean);
                var $body = $row.children('.fc-row-body');
                // This row's OWN fields — not those of a list nested inside it.
                function own(sel) {
                    return $body.find(sel).filter(function () { return $(this).closest('.fc-repeater-row')[0] === $row[0]; });
                }
                function read(key) {
                    var $f = own('[name$="[' + key + '_en]"]').first();
                    if (!$f.length || !$.trim($f.val())) {
                        var $el = own('[name$="[' + key + '_el]"]').first();
                        if ($el.length && $.trim($el.val())) $f = $el;
                    }
                    if (!$f.length || !$.trim($f.val())) $f = own('[name$="[' + key + ']"]').first();
                    if (!$f.length) return '';
                    if ($f.is('select')) return $.trim($f.find('option:selected').text()).replace(/^—$/, '');
                    return $.trim(String($f.val() || ''));
                }
                var vals = keys.map(read);
                var head = vals.length ? vals[vals.length - 1] : '';
                var meta = vals.slice(0, -1).filter(Boolean);
                var $s = $row.find('> .fc-row-head .fc-row-summary');
                if (!head && !meta.length) { $s.addClass('is-empty').text('(untitled — click to fill in)'); return; }
                $s.removeClass('is-empty').empty();
                meta.forEach(function (m) { $('<span class="fc-sum-meta"></span>').text(m).appendTo($s); });
                $s.append(document.createTextNode(head || '(untitled)'));
            }
            function fcAllSummaries() { $('.fc-repeater-row').each(function () { fcRowSummary($(this)); }); }

            function fcSetOpen($row, open) {
                $row.toggleClass('is-open', open);
                $row.find('> .fc-row-head .fc-row-toggle').attr('aria-expanded', open ? 'true' : 'false');
            }

            $(document).on('click', '.fc-row-toggle', function () {
                var $row = $(this).closest('.fc-repeater-row');
                fcSetOpen($row, !$row.hasClass('is-open'));
            });
            $(document).on('input change', '.fc-row-body :input', function () {
                fcRowSummary($(this).closest('.fc-repeater-row'));
            });

            $(document).on('click', '.fc-row-delete', function() {
                var $row = $(this).closest('.fc-repeater-row');
                var $rep = $row.parent('.fc-repeater');
                var what = $rep.attr('data-item') || 'entry';
                var label = $.trim($row.find('> .fc-row-head .fc-row-summary').text());
                if (!confirm('Delete this ' + what + (label && !/^\(untitled/.test(label) ? ' — “' + label + '”' : '') + '?\n\nIt is removed when you save.')) return;
                $row.remove();
                fcReindexRepeater($rep);
                fcCount();
                fcDirty();
            });

            // Duplicate: a copy of the row with everything typed so far, placed
            // right after it and opened. Hidden keys/slugs are cleared so the
            // copy becomes its own day, room or track on save.
            $(document).on('click', '.fc-row-dup', function () {
                var $row = $(this).closest('.fc-repeater-row');
                var $rep = $row.parent('.fc-repeater');
                var $copy = $row.clone();
                var $from = $row.find(':input'), $to = $copy.find(':input');
                $from.each(function (i) {
                    var $t = $to.eq(i);
                    if (this.type === 'checkbox' || this.type === 'radio') { $t.prop('checked', this.checked); }
                    else if ($(this).is('select')) { $t.val($(this).val()); }
                    else { $t.val($(this).val()); }
                });
                $copy.find('input[type=hidden]').filter(function () { return /\[(key|slug)\]$/.test(this.name || ''); }).val('');
                $copy.removeAttr('data-fc-sortable').find('[data-fc-sortable]').removeAttr('data-fc-sortable');
                $copy.addClass('is-new');
                $row.after($copy);
                fcReindexRepeater($rep);
                fcInitSortables();
                fcSetOpen($copy, true);
                fcRowSummary($copy);
                fcCount();
                fcDirty();
            });

            /* ── The list's tools: expand/collapse all, filter, count ───────── */
            $(document).on('click', '.fc-expand-all, .fc-collapse-all', function () {
                var open = $(this).hasClass('fc-expand-all');
                $(this).closest('.fc-repeater-wrap').children('.fc-repeater').children('.fc-repeater-row').each(function () { fcSetOpen($(this), open); });
            });
            $(document).on('input', '.fc-repeater-filter', function () {
                var q = $.trim(this.value).toLowerCase();
                var $rows = $(this).closest('.fc-repeater-wrap').children('.fc-repeater').children('.fc-repeater-row');
                $rows.each(function () {
                    var hay = $(this).find(':input').map(function () {
                        return $(this).is('select') ? $(this).find('option:selected').text() : (this.type === 'hidden' ? '' : $(this).val());
                    }).get().join(' ').toLowerCase();
                    $(this).toggleClass('is-filtered-out', q !== '' && hay.indexOf(q) < 0);
                });
                fcCount();
            });
            function fcCount() {
                $('.fc-repeater-tools').each(function () {
                    var $rows = $(this).closest('.fc-repeater-wrap').children('.fc-repeater').children('.fc-repeater-row');
                    var shown = $rows.not('.is-filtered-out').length;
                    var total = $rows.length;
                    $(this).find('.fc-repeater-count').text(total ? (shown === total ? total + ' in total' : shown + ' of ' + total + ' shown') : '');
                });
            }

            /* ── Unsaved changes ────────────────────────────────────────────── */
            var fcIsDirty = false;
            function fcDirty() {
                fcIsDirty = true;
                $('.fc-savebar').addClass('is-dirty');
            }
            $(document).on('input change', '.fc-wrap form :input', fcDirty);
            $(document).on('submit', '.fc-wrap form', function () { fcIsDirty = false; });
            window.addEventListener('beforeunload', function (e) {
                if (!fcIsDirty) return;
                e.preventDefault();
                e.returnValue = '';
            });

            /* ── Tabbed pages (Schedule) ────────────────────────────────────── */
            $(document).on('click', '.fc-page-tabs a', function (e) {
                e.preventDefault();
                var id = $(this).attr('data-panel');
                $(this).addClass('active').siblings().removeClass('active');
                $('.fc-page-panel').removeClass('active').filter('[data-panel="' + id + '"]').addClass('active');
                try { sessionStorage.setItem('fc-tab:' + location.search, id); } catch (err) {}
            });
            (function () {
                var id = null;
                try { id = sessionStorage.getItem('fc-tab:' + location.search); } catch (err) {}
                if (id && $('.fc-page-tabs a[data-panel="' + id + '"]').length) $('.fc-page-tabs a[data-panel="' + id + '"]').trigger('click');
            }());

            fcAllSummaries();
            fcCount();

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
                var $new = $(tpl.split(ph).join(nextIdx));
                $rep.append($new);
                fcReindexRepeater($rep);
                fcInitSortables();
                fcSetOpen($new, true);
                $new.addClass('is-new');
                fcRowSummary($new);
                fcCount();
                fcDirty();
                var $first = $new.find('.fc-row-body :input:visible').first();
                if ($first.length) { $new[0].scrollIntoView({ block: 'center' }); $first.trigger('focus'); }
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
