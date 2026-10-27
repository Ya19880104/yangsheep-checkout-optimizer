/**
 * 結帳自訂欄位的彈出視窗（原生 <dialog>）。
 *
 * 付款區會隨每次更新結帳重繪，所以一律用 document 層的事件委派，
 * 不綁在個別節點上。瀏覽器不支援 showModal() 時改用 open 屬性顯示。
 */
(function () {
    'use strict';

    function openDialog(dialog) {
        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) {
                dialog.showModal();
            }
        } else {
            dialog.setAttribute('open', '');
        }
    }

    function closeDialog(dialog) {
        if (typeof dialog.close === 'function') {
            if (dialog.open) {
                dialog.close();
            }
        } else {
            dialog.removeAttribute('open');
        }
    }

    function agree(checkboxId) {
        var checkbox = checkboxId ? document.getElementById(checkboxId) : null;
        if (!checkbox || checkbox.type !== 'checkbox' || checkbox.checked) {
            return;
        }
        checkbox.checked = true;
        // WooCommerce 的即時驗證聽 change；同時讓勾選樣式更新。
        if (window.jQuery) {
            window.jQuery(checkbox).trigger('change');
        } else {
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        var link = target.closest('.ys-cf-popup-link');
        if (link) {
            var dialog = document.getElementById(link.getAttribute('aria-controls') || '');
            if (dialog) {
                event.preventDefault();
                openDialog(dialog);
            }
            return;
        }

        var closer = target.closest('[data-ys-cf-close]');
        if (closer) {
            var owner = closer.closest('dialog.ys-cf-dialog');
            if (owner) {
                if (closer.hasAttribute('data-ys-cf-agree')) {
                    agree(closer.getAttribute('data-ys-cf-agree'));
                }
                closeDialog(owner);
            }
            return;
        }

        // 點在對話框外圍（::backdrop）時，事件目標是 dialog 本身。
        if (target.matches('dialog.ys-cf-dialog')) {
            closeDialog(target);
        }
    });
})();
