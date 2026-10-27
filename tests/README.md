# 超取結帳回歸測試

這些測試不建立訂單、不執行付款，也不連線到商店。PHP 測試使用可控制的 WordPress／WooCommerce collaborators；前端測試載入真實 WooCommerce `checkout.js`，以 AJAX recorder 取代送出。

## PHP

需要 PHP 8.0+，在外掛根目錄執行：

```sh
php tests/cvs-checkout-fields-cache.php
php tests/cvs-store-validation.php
php tests/cvs-guard-bootstrap.php
php tests/cvs-match-matrix.php
php tests/checkout-fields-compat-matrix.php
php tests/custom-fields-matrix.php
php tests/settings-transfer-matrix.php
```

`custom-fields-matrix.php` 載入實際的自訂欄位類別（定義、結帳輸出／驗證／寫入訂單、訂單顯示），以替身取代 WordPress／WooCommerce，不連資料庫、不建立訂單。

地址測試涵蓋欄位編輯器較晚覆寫、初始化快取、兩家超商、PayNow、POST 優先於舊 session、郵寄／混合包裹與電話必填。門市矩陣覆蓋實際 provider 的欄位與 session 契約；bootstrap 測試只檢查實際主檔的接線，不以替身當作 provider 驗證證據。

## 前端

需要 Node.js 與 jsdom 26.1.0。可使用已安裝的 jsdom，或在測試目錄安裝：

```sh
npm install --prefix tests --no-save --ignore-scripts jsdom@26.1.0
```

設定下列環境變數，指向本地安裝的原始檔案；不要將商店資料或憑證加入測試 fixture：

| 環境變數 | 檔案 |
| --- | --- |
| `YS_CVS_JSDOM_MODULE` | 可選；已安裝的 `jsdom` 模組目錄，未設定時使用正常 Node module resolution |
| `YS_CVS_JQUERY_JS` | WordPress 的 `wp-includes/js/jquery/jquery.min.js` |
| `YS_CVS_BLOCKUI_JS` | WooCommerce 的 `assets/js/jquery-blockui/jquery.blockUI.min.js` |
| `YS_CVS_CHECKOUT_JS` | WooCommerce 的 `assets/js/frontend/checkout.js` |

```sh
node tests/cvs-checkout-guard.cjs --output=report.json
```

測試使用 WooCommerce 11.2.1 的真實 checkout.js 驗證過。報告記錄載入來源的 SHA256 與每項 alert／checkout AJAX 次數。`--without-guard` 可重現未安裝防護的失敗情況，應以非零狀態結束。

涵蓋滑鼠點擊、Enter／requestSubmit、jQuery submit／triggerHandler、disabled／重複欄位、缺漏門市、品牌或服務不符、混合包裹、fragment 替換新 rate、付款區重建及補選門市後重試。所有測試的網路資源載入均被禁用。

## 上線前驗收

本次修正針對傳統結帳，未涵蓋 Checkout Block／Store API。合併或部署前，應依商店實際啟用的物流，檢查每家超商的選店／未選店、超取與宅配來回切換、完整宅配地址及原生姓名／電話／地區限制。未知或自訂服務仍交由對應物流外掛驗證。
