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
            $(document).on('click', '.fc-row-delete', function() {
                if (!confirm('Delete this row?')) return;
                $(this).closest('.fc-repeater-row').remove();
                fcReindexRepeater($(this).closest('.fc-repeater'));
            });

            $(document).on('click', '.fc-add-row-btn', function() {
                var $btn = $(this);
                var $rep = $btn.closest('.fc-repeater-wrap').find('.fc-repeater');
                var tpl = $rep.data('template');
                if (!tpl) return;
                var nextIdx = $rep.find('.fc-repeater-row').length;
                $rep.append(tpl.replace(/__INDEX__/g, nextIdx));
                fcReindexRepeater($rep);
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
                    var url = (isImage && att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
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

            $('.fc-repeater').each(function() {
                var $rep = $(this);
                $rep.sortable({
                    handle: '.fc-row-handle',
                    update: function() { fcReindexRepeater($rep); }
                });
            });

            function fcReindexRepeater($rep) {
                $rep.find('.fc-repeater-row').each(function(idx) {
                    $(this).find('[name]').each(function() {
                        var $i = $(this);
                        var name = $i.attr('name');
                        if (!name) return;
                        name = name.replace(/\[\d+\]/, '[' + idx + ']');
                        $i.attr('name', name);
                    });
                });
            }
        });
    })(jQuery);
JS;
}
