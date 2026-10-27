/**
 * 後台「自訂欄位」分頁：新增、刪除、排序欄位，並即時更新每列標題。
 *
 * 欄位順序就是表單送出順序（PHP 依 POST 順序建立陣列），所以排序只需移動 DOM。
 */
(function ($) {
    'use strict';

    var config = window.ysCustomFieldsAdmin || {};
    var $root = $('[data-ys-cf-admin]');
    if (!$root.length || !config.placeholder) {
        return;
    }

    var $list = $root.find('[data-ys-cf-list]');
    var $empty = $root.find('[data-ys-cf-empty]');
    var template = $root.find('template[data-ys-cf-template]').get(0);
    var i18n = config.i18n || {};
    var typeLabels = config.typeLabels || {};
    var maxFields = parseInt(config.maxFields, 10) || 20;

    function newId() {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var id = 'cf_';
        var bytes = new Uint8Array(10);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < bytes.length; i++) {
                bytes[i] = Math.floor(Math.random() * 256);
            }
        }
        for (var j = 0; j < bytes.length; j++) {
            id += chars.charAt(bytes[j] % chars.length);
        }
        return id;
    }

    function plainLabel($row) {
        var label = $.trim($row.find('[data-ys-cf-label]').val() || '');
        var link = $.trim($row.find('[data-ys-cf-link]').val() || '');
        if (!label) {
            return i18n.untitled || '';
        }
        return label.indexOf('{popup}') !== -1 ? label.replace('{popup}', link) : label + link;
    }

    function syncRow($row) {
        var type = $row.find('[data-ys-cf-type]').val();
        $row.attr('data-type', type);
        $row.find('[data-ys-cf-badge]').text(typeLabels[type] || '');
        $row.find('[data-ys-cf-title]').text(plainLabel($row));
        $row.toggleClass('is-disabled', !$row.find('[data-ys-cf-enabled]').is(':checked'));
    }

    function syncList() {
        var $rows = $list.children('[data-ys-cf-row]');
        $empty.prop('hidden', $rows.length > 0);
        $root.find('[data-ys-cf-add]').prop('disabled', $rows.length >= maxFields);
    }

    $root.on('click', '[data-ys-cf-add]', function () {
        if (!template || $list.children('[data-ys-cf-row]').length >= maxFields) {
            window.alert(i18n.maxReached || '');
            return;
        }
        var id = newId();
        var html = template.innerHTML.split(config.placeholder).join(id);
        var $row = $($.parseHTML($.trim(html)));
        $list.append($row);
        syncRow($row);
        syncList();
        $row.find('[data-ys-cf-label]').trigger('focus');
    });

    $root.on('click', '[data-ys-cf-remove]', function () {
        if (!window.confirm(i18n.confirmRemove || '')) {
            return;
        }
        $(this).closest('[data-ys-cf-row]').remove();
        syncList();
    });

    $root.on('click', '[data-ys-cf-move]', function () {
        var $row = $(this).closest('[data-ys-cf-row]');
        if ($(this).attr('data-ys-cf-move') === 'up') {
            $row.prev('[data-ys-cf-row]').before($row);
        } else {
            $row.next('[data-ys-cf-row]').after($row);
        }
        $(this).trigger('focus');
    });

    $root.on('input change', '[data-ys-cf-label], [data-ys-cf-link], [data-ys-cf-type], [data-ys-cf-enabled]', function () {
        syncRow($(this).closest('[data-ys-cf-row]'));
    });

    $list.children('[data-ys-cf-row]').each(function () {
        syncRow($(this));
    });
    syncList();
})(jQuery);
