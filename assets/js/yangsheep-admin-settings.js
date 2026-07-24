/**
 * Settings transfer controls for the checkout optimizer admin screen.
 */
(function($) {
    'use strict';

    var config = window.yangsheep_settings_transfer || {};
    var $file = $('#ys-settings-import-file');
    var $import = $('#ys-settings-import');
    var $export = $('#ys-settings-export');
    var $message = $('#ys-settings-transfer-message');

    if (!$file.length || !config.ajaxUrl || !config.nonce) {
        return;
    }

    function message(text, type) {
        var cssClass = type === 'success' ? 'notice-success' : 'notice-error';
        $message
            .empty()
            .append($('<div class="notice inline"></div>').addClass(cssClass).append($('<p></p>').text(text)));
    }

    function responseMessage(payload, fallback) {
        return payload && payload.data && payload.data.message ? payload.data.message : fallback;
    }

    $file.on('change', function() {
        var selected = this.files && this.files[0];
        $('.ys-settings-import-name').text(selected ? selected.name : '選擇 JSON 設定檔');
        $import.prop('disabled', !selected);
        $message.empty();
    });

    $export.on('click', function() {
        var original = $export.html();
        var formData = new FormData();
        formData.append('action', 'yangsheep_export_settings');
        formData.append('nonce', config.nonce);

        $export.prop('disabled', true).text((config.i18n && config.i18n.exporting) || '匯出中...');

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function(response) {
            if (!response.ok) {
                return response.json().then(function(payload) {
                    throw new Error(responseMessage(payload, (config.i18n && config.i18n.exportFailed) || '設定匯出失敗。'));
                });
            }
            return response.blob().then(function(blob) {
                var disposition = response.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="([^"]+)"/);
                return { blob: blob, filename: match ? match[1] : 'yangsheep-checkout-settings.json' };
            });
        }).then(function(download) {
            if (download.blob.type && download.blob.type.indexOf('application/json') === -1) {
                throw new Error((config.i18n && config.i18n.exportFailed) || '設定匯出失敗。');
            }
            var url = URL.createObjectURL(download.blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = download.filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        }).catch(function(error) {
            message(error.message || ((config.i18n && config.i18n.exportFailed) || '設定匯出失敗。'), 'error');
        }).finally(function() {
            $export.prop('disabled', false).html(original);
        });
    });

    $import.on('click', function() {
        var selected = $file[0].files && $file[0].files[0];
        if (!selected) {
            return;
        }
        if (!window.confirm((config.i18n && config.i18n.confirmImport) || '確定匯入完整設定？')) {
            return;
        }

        var original = $import.html();
        var formData = new FormData();
        formData.append('action', 'yangsheep_import_settings');
        formData.append('nonce', config.nonce);
        formData.append('settings_file', selected, selected.name);

        $import.prop('disabled', true).text((config.i18n && config.i18n.importing) || '驗證並匯入中...');
        $export.prop('disabled', true);
        $message.empty();

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
            headers: { 'Accept': 'application/json' }
        }).then(function(response) {
            return response.json().then(function(payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(responseMessage(payload, (config.i18n && config.i18n.importFailed) || '設定匯入失敗。'));
                }
                return payload;
            });
        }).then(function(payload) {
            message(responseMessage(payload, (config.i18n && config.i18n.imported) || '設定已完整匯入。'), 'success');
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        }).catch(function(error) {
            message(error.message || ((config.i18n && config.i18n.importFailed) || '設定匯入失敗。'), 'error');
            $import.prop('disabled', false).html(original);
            $export.prop('disabled', false);
        });
    });
})(jQuery);
