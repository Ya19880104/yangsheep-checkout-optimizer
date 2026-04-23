# YANGSHEEP 結帳強化外掛

優化 WooCommerce 結帳頁面、我的帳號、訂單頁面的 WordPress 外掛。

## 版本資訊

**當前版本**：1.6.15
**最後更新**：2026-04-08
**開發者**：羊羊數位科技有限公司
**網站**：https://yangsheep.com.tw

---

## 功能特色

### 1. 結帳頁面優化
- **結帳頁面重新佈局** - 透過自訂 Hook 重新排列結帳流程
- **桌機版 Flex 佈局** - sidebar 20% + form 80%
- **TWzipcode 整合** - 台灣地址選擇器自動帶入郵遞區號

### 2. 結帳欄位設置
- **關閉 Last Name** - 啟用後只顯示「姓名」欄位（使用 First Name），適合台灣使用習慣
- **台灣化欄位** - 帳單欄位簡化為姓名、電話、電子郵件；運送欄位調整為台灣格式
- **收件人電話必填** - 收件人區塊的電話欄位預設為必填
- **手機號碼驗證** - 台灣手機格式驗證（09 開頭、10 位數字），前後端雙重驗證
- **訂單備註開關** - 用戶需勾選「我需要填寫訂單備註」才顯示備註欄位

### 3. 物流卡片選擇
- 將物流選項從訂單明細表分離，採用卡片式 Radio 選擇
- AJAX 即時更新，地址變更時自動重新計算可用物流
- 虛擬商品自動隱藏物流選擇區塊與收件人欄位

### 4. 結帳側邊欄
- 獨立顯示結帳金額、運輸方式、購物車內容
- 桌機版固定顯示結帳金額與購物車概要

### 5. 第三方物流相容性
支援以下第三方物流外掛的超商取貨功能：

#### 好用版 RY Tools for WooCommerce（綠界 ECPay）
- 支援 7-11、全家、萊爾富、OK 超商
- CVS 欄位（門市名稱、地址、電話）僅在選擇綠界超商物流時顯示
- 「超商門市」選擇按鈕自動置中加粗
- 內部欄位（LogisticsSubType、CVSStoreID）自動隱藏

#### 好用版 PayNow Shipping（PayNow 超取）
- 支援 7-11、全家、萊爾富、OK 超商（C2C/B2C）
- 超取欄位（門市名稱、門市代號、地址）僅在選擇 PayNow 超取時顯示
- 「選擇超商」按鈕自動置中加粗
- 內部欄位（Reserved NO、Ship Date）自動隱藏

### 6. 訂單頁面強化

#### 前台物流狀態卡片
- 卡片式物流狀態顯示
- 進度條顯示（訂單成立 → 商品準備中 → 運送中 → 已到店/配送完成 → 已取貨）
- 一鍵更新貨態按鈕
- 物流單號複製功能

#### 支援的物流系統
- **YS PayNow 物流** - 超商取貨（7-11/全家/萊爾富）、黑貓宅配
- **WPBR PayUni 物流** - 7-11 超取（大智通/交貨便）、黑貓宅配

#### 後台手動配送資訊
- 支援新增多筆物流資訊
- 自動偵測物流類型，只對非自動串接物流顯示
- 隱藏條件：PayUni 物流、YS PayNow 物流
- 顯示條件：綠界、好用版 PayNow、其他自訂物流

### 7. 後台樣式設定
- 按鈕顏色（主色、Hover 色）
- 物流卡片顏色
- 側邊欄背景色
- 區塊圓角設定
- 一鍵恢復預設配色

---

## 檔案結構

```
yangsheep-checkout-optimizer/
├── assets/
│   ├── css/
│   │   ├── yangsheep-checkout.css         # 結帳頁面樣式
│   │   ├── yangsheep-sidebar.css          # 側邊欄樣式
│   │   ├── yangsheep-myaccount.css        # 我的帳號樣式
│   │   ├── yangsheep-order.css            # 訂單頁面樣式
│   │   ├── yangsheep-shipping-cards.css   # 物流卡片樣式
│   │   └── yangsheep-order-enhancer.css   # 訂單強化樣式
│   └── js/
│       ├── jquery.twzipcode.min.js        # TWzipcode 套件
│       ├── yangsheep-checkout.js          # 結帳頁面 JS
│       ├── yangsheep-sidebar.js           # 側邊欄 JS
│       ├── yangsheep-shipping-cards.js    # 物流卡片 JS
│       ├── yangsheep-order-enhancer.js    # 訂單強化 JS
│       └── color-picker-init.js           # 後台顏色選擇器初始化
├── src/                                   # PSR-4 自動載入（命名空間：YangSheep\CheckoutOptimizer）
│   ├── Admin/
│   │   └── YSCheckoutSettings.php         # 後台設定頁面
│   ├── Checkout/
│   │   ├── YSCheckoutCustomizer.php       # 自訂器（Color Picker enqueue）
│   │   ├── YSCheckoutFields.php           # 結帳欄位設置
│   │   ├── YSCheckoutSidebar.php          # 側邊欄類別
│   │   └── YSShippingCards.php            # 物流卡片類別
│   ├── Compat/
│   │   ├── YSThirdPartyShippingCompat.php # 第三方物流相容性
│   │   └── YSWPLoyaltyIntegration.php     # WPLoyalty 購物金整合
│   ├── Order/
│   │   └── YSOrderEnhancer.php            # 訂單頁面強化
│   └── Settings/
│       ├── YSSettingsManager.php          # 設定管理門面（Facade）
│       ├── YSSettingsRepository.php       # 設定 CRUD 操作
│       ├── YSSettingsTableMaker.php       # 設定資料表建立
│       └── YSSettingsMigrator.php         # 設定資料遷移
├── templates/
│   ├── checkout/
│   │   ├── form-checkout.php              # 結帳表單佈局
│   │   ├── form-billing.php               # 帳單表單
│   │   ├── form-shipping.php              # 運送表單
│   │   ├── form-login.php                 # 登入表單
│   │   ├── review-order.php               # 訂單明細
│   │   └── shipping-cards.php             # 物流卡片模板
│   └── myaccount/                         # 我的帳號模板覆寫
├── DEVELOPMENT.md                         # 開發文件
├── README.md                              # 本檔案
└── yangsheep-checkout-optimization.php    # 主外掛檔案（含 PSR-4 自動載入器）
```

---

## 核心類別說明

所有類別統一使用 `YS` 前綴，命名空間為 `YangSheep\CheckoutOptimizer\{Module}`。

### YSCheckoutFields (`Checkout\YSCheckoutFields`)
結帳欄位設置類別，處理：
- WooCommerce 運送設定檢查
- First Name / Last Name 關閉選項
- 台灣化欄位設置
- 欄位排序與寬度
- 訂單備註設置
- **收件人電話驗證**（`woocommerce_checkout_process` hook）

### YSThirdPartyShippingCompat (`Compat\YSThirdPartyShippingCompat`)
第三方物流相容性處理：
- 綠界 CVS 欄位顯示/隱藏控制
- PayNow CVS 欄位顯示/隱藏控制
- 內部欄位（Reserved NO、Ship Date、LogisticsSubType、CVSStoreID）CSS 隱藏
- **手機號碼前端驗證**（JS 即時驗證）
- 僅在啟用對應物流外掛時載入

### YSOrderEnhancer (`Order\YSOrderEnhancer`)
訂單頁面強化：
- 前台物流狀態卡片渲染
- 後台手動配送 Meta Box
- 物流類型偵測（YS PayNow / PayUni / 其他）
- AJAX 貨態更新

### YSCheckoutSettings (`Admin\YSCheckoutSettings`)
後台設定頁面，包含：
- 顏色設定（按鈕、物流卡片、側邊欄等）
- 功能開關（台灣化欄位、我的帳號視覺等）
- 遷移管理 UI
- MyAccount CSS 變數注入

### YSSettingsManager (`Settings\YSSettingsManager`)
設定存取門面（Facade），統一所有設定的讀寫：
- `YSSettingsManager::get( $key, $default )` - 讀取設定
- `YSSettingsManager::set( $key, $value )` - 寫入設定
- 底層使用自訂資料表 `wp_ys_checkout_settings`

---

## 手機號碼驗證機制

### 前端驗證（JS）
位置：`src/Compat/YSThirdPartyShippingCompat.php`

```javascript
// 驗證格式：必須是 09 開頭的 10 位數字
var isValid = /^09\d{8}$/.test(numericValue);
```

事件綁定：
- `input` 事件：輸入時即時驗證（不顯示錯誤）
- `blur` 事件：失焦時顯示錯誤訊息
- `submit` 事件：表單提交前驗證
- `checkout_error` 事件：WooCommerce 結帳錯誤時再次驗證

### 後端驗證（PHP）
位置：`src/Checkout/YSCheckoutFields.php`

```php
// 驗證格式：必須是 09 開頭的 10 位數字
if ( ! preg_match( '/^09\d{8}$/', $phone_numeric ) ) {
    wc_add_notice( '錯誤訊息', 'error' );
}
```

驗證條件：
- 只在勾選「運送到不同地址」且有填寫 shipping_phone 時驗證

---

## 手動配送 Meta Box 顯示邏輯

| 物流外掛 | method_id 前綴 | 顯示手動配送？ |
|---------|---------------|--------------|
| WPBR PayUni | `payuni_shipping_*` | ❌ 隱藏 |
| YS PayNow | `ys_paynow_shipping_*` | ❌ 隱藏 |
| 好用版 PayNow | `paynow_shipping_*` | ✅ 顯示 |
| 綠界 ECPay (RY Tools) | `ry_ecpay_*` | ✅ 顯示 |
| WooCommerce 內建物流 | `flat_rate`, `free_shipping` 等 | ✅ 顯示 |
| 其他自訂物流 | 其他 | ✅ 顯示 |

---

## 技術注意事項

- 遵循 WooCommerce 模板覆寫規範
- 使用 `woocommerce_update_order_review_fragments` filter 確保 AJAX 更新
- 保留標準 Action Hooks 以相容第三方外掛
- 維持 `shipping_method[...]` input name 結構
- CSS Grid 排版，使用 `grid-column` 控制欄位寬度
- 第三方物流欄位使用 CSS `:not(.ys-cvs-shown)` 選擇器控制顯示

---

## 版本紀錄

格式基於 [Keep a Changelog](https://keepachangelog.com/zh-TW/1.0.0/)，版本號遵循 [Semantic Versioning](https://semver.org/lang/zh-TW/)。

### v1.6.15 (2026-04-23)

#### 變更
- **Padding 採固定值，移除多層 media query 覆寫** — 使用者指定：
  - `.yangsheep-pay-summary`：`padding: 20px 10px`
  - `.yangsheep-design-pay-page .yangsheep-payment`：`padding: 20px 10px !important`
  - `li.wc_payment_method`：`padding: 5px !important`
  - `.payment_box` SDK 容器：`padding: 5px !important`
  - 移除 768/480/400 media queries 裡針對這 4 個 class 的 padding 覆寫，保留字級/margin/縮圖尺寸規則
- **變更付款方式頁重新設計** — 新增 `templates/checkout/form-change-payment-method.php` 覆寫 WooCommerce Subscriptions 的 change-payment-method 模板：
  - 套用與 order-pay 相同的 `.yangsheep-design-pay-page` 區塊式視覺
  - 商品列改 div-based（縮圖 + 商品名 + 單價×數量 + 小計）
  - Totals 區獨立 + 總計主色強調
  - 保留 WCS 專屬功能：`supports-payment-method-changes` class、`update_all_subscriptions_payment_method` checkbox、`_wcsnonce`、`woocommerce_change_payment` hidden input、所有 WCS hooks/filters（`woocommerce_change_payment_button_text`、`wcs_gateway_change_payment_button_text`、`woocommerce_change_payment_button_html`、`woocommerce_subscriptions_change_payment_before/after_submit`）

### v1.6.14 (2026-04-23)

#### 變更（order-pay 手機視圖進一步減少 padding）
- 手機 SDK 貼邊優化：使用者實測 SHOPLINE SDK 信用卡欄位在 ≤ 400px 仍被擠
- 斷點新增 **480px** 中間層級（原只有 768/400）
- 三層 padding 全面壓縮：
  - `.yangsheep-payment` 外框：768px → 12/8、480px → 10/6、400px → 8/4
  - `.wc_payment_method` 卡片：768px → 10/8、480px → 8/6、400px → 8/4
  - `.payment_box` SDK 容器：768px → 8/2、480px → 6/0、400px → 4/0
- `.payment_box > div, iframe` 清除 margin-left/right 避免 SDK 內部額外縮進
- 商品縮圖：768px → 48×48、400px → 42×42（原 52 / 46）

### v1.6.13 (2026-04-23)

#### 修復（order-pay 頁）
- **「NT$80 經由 HELLO」字級同大** — WC 原本用 `<small class="shipped_via">` 縮小文字，新增選擇器讓 `shipped_via` / `includes_tax` / `small` 強制 `font-size: inherit; font-weight: inherit; color: inherit`
- **移除多餘外層 padding** — `.yangsheep-design-pay-page` 移除 `padding: 0 15px`（Blocksy `ct-container` 已提供水平 padding）
- **RWD 優化** — 新增 900px / 768px / 400px 三段斷點：
  - 768px：訂單明細卡片 padding 14px、商品列縮圖 52×52、付款區 padding 14/12、payment_box padding 10/6、總計金額 20px
  - 400px：縮圖 46×46、padding 再收斂，避免 SDK 付款欄位擠壓
- **付款區塊讓給 SDK** — `.yangsheep-payment` 在手機 padding 14/12（原本 20）；`.wc_payment_method` padding 10/10（原本 12/15）；`.payment_box` padding 10/6，讓 SHOPLINE SDK iframe 橫向空間更多

### v1.6.12 (2026-04-23)

#### 變更
- **Order Pay 訂單明細重新設計** — 放棄原本 shop_table tfoot 混排方式，改為 div-based 卡片版面：
  - 商品列（`.yangsheep-pay-item`）：60×60 縮圖 + 商品名 + 單價/數量 + 右側小計，CSS Grid 三欄排版
  - Totals 區（`.yangsheep-pay-totals`）：獨立於商品列，小計/運送 flex 行顯示
  - **總計列**（`.is-final`）：上方分隔線 + 金額放大至 22px + 主色強調
  - 明細 header 新增「訂單編號 #xxx」pill badge
  - 移除重複的「付款方式」行（下方選擇區塊已處理）
  - 手機響應式：縮圖 52×52、padding 縮小

### v1.6.11 (2026-04-23)

#### 新增
- **Order Pay（重新付款）頁面區塊式設計** — 覆寫 `templates/checkout/form-pay.php`，加上 `.yangsheep-design-checkout-page .yangsheep-design-pay-page` wrapper，讓訂單明細、付款方式、送出按鈕套用與結帳頁相同的區塊背景 / 圓角 / 主色。商品明細改用表格區塊化排版（thead 有主色背景、列分隔線、總計強調），付款區塊延用既有 `.yangsheep-payment` 動態 CSS 變數，按鈕套用結帳頁主色按鈕樣式。

### v1.6.10 (2026-04-23)

#### 修復
- **`templates/myaccount/form-login.php` 無限遞迴造成 500** — 當 WooCommerce 開啟「允許在我的帳號頁註冊」且使用者未登入時，第 61 行 `wc_get_template( 'myaccount/form-login.php' )` 會遞迴呼叫自己觸發 stack overflow。移除遞迴，改為內嵌註冊表單 HTML（對齊 WooCommerce core form-login.php v9.9.0 結構）
- **觸發條件**：新 Chrome instance（無 session）+ 我的帳號視覺開啟 + WC 註冊選項開啟

### v1.6.9 (2026-04-08)

#### 修復
- **收件人電話欄位位置錯誤** - 第三方物流外掛（如 WPBR SF Express）覆蓋 priority 和 class 導致電話欄位跑到地址下方，`force_phone_fields()` 現在強制覆蓋 priority=15 並清除第三方加入的 `form-row-wide`、`wpbc-*` class
- **建立帳號密碼異常顯示** - WooCommerce 將 `#account_password_field` 渲染在 billing grid 而非帳號容器內，改用 CSS `.ys-show` class + `!important` 直接控制欄位顯隱，不依賴容器嵌套
- **強制註冊時密碼欄位不顯示** - 無 `#createaccount` checkbox 時 JS 自動判定為強制註冊，密碼欄位永遠顯示
- **密碼欄位寬度修正** - 顯示時設定 `grid-column: 1/-1` 全寬顯示

### v1.4.17 (2026-03-13)

#### 修復
- **電商工具箱空白子選單** - 修正 `remove_submenu_page` 執行時機，以 priority 999 延後移除
- **模板版本標記同步** - 所有檔案版本統一為 1.4.17

### v1.4.16 (2026-03-13)

#### 重構
- **後台設定改為「電商工具箱」子選單** - 遵循 YS 外掛開發準則第 4 節
  - 頂層選單 `ys-toolbox`（電商工具箱，位置 56，緊跟 WooCommerce）
  - 子選單 slug 改為 `ys-checkout-optimizer`
  - 自動偵測頂層選單避免重複建立

#### 修復
- **後台 AJAX 訊息 XSS 防護** - `.html()` 改為 jQuery DOM 建構 + `.text()`

### v1.4.15 (2026-03-12)

#### 重構
- **PSR-4 目錄結構遷移** - 將 `includes/` 下所有類別遷移至 `src/` 目錄
  - 命名空間統一為 `YangSheep\CheckoutOptimizer\{Module}`
  - 手動 PSR-4 自動載入器（不依賴 Composer）
  - 移除 `composer.json`（不再需要）

#### 清理
- 移除 `.review_tmp/` 重構臨時檔案
- 更新 `.gitignore` 排除 `.claude/` 和 `.review_tmp/`

### v1.4.14 (2026-03-12)

#### 修復
- **WooCommerce 通知訊息位置** - 將通知從結帳佈局容器外移至商品明細上方
  - 移除 `woocommerce_output_all_notices` 從 `woocommerce_before_checkout_form`
  - 在 form 內商品明細上方加入 `.woocommerce-notices-wrapper`
  - 優惠券 AJAX 通知改為插入 `.woocommerce-notices-wrapper`

#### 變更
- 合併 CHANGELOG.md 至 README.md，移除獨立 CHANGELOG 檔案

### v1.4.13 (2026-02-12)

#### 修復
- **建立帳號密碼欄位顯示/隱藏**
  - 新增初始狀態檢查：頁面載入時根據 `#createaccount` checkbox 狀態同步密碼欄位顯隱
  - 新增 `updated_checkout` 事件監聽，WooCommerce AJAX 更新後重新同步
- **密碼欄位 Grid 全寬修正**
  - 移除被 `.form-row { width: auto !important }` 覆蓋的過時 `width: 100%` 規則
  - 電腦版和平板版 Grid 區塊新增 `.yangsheep-create-account` 內部元素全寬規則
- **國家選擇區塊動態隱藏**
  - 新增 `toggleCountryBlock()`：當 `#shipping_country_field` 不存在時隱藏 `.yangsheep-checkout-country`
  - `updated_checkout` 後延遲 100ms 檢查，確保 DOM 移動完成

#### 技術變更
- `yangsheep-checkout.js` v2.7.0 - 新增帳號欄位初始狀態檢查、國家區塊動態顯隱
- `yangsheep-checkout.css` - Grid 佈局下建立帳號區塊全寬規則重構

### v1.4.11 (2026-02-06)

#### 改進
- **物流單號改為託運單號顯示**
  - PayNow 物流：優先讀取 `_ys_paynow_payment_no`（託運單號），無值時降回 `_ys_paynow_logistic_number`
  - PayUni 物流：新增 `tracking_label => '託運單號'`
  - 手動配送：保持原本「物流單號」標籤
  - JS 端使用動態 `tracking_label` 渲染，預設降回「物流單號」
- **物流進度條狀態關鍵字擴充**
  - `calculate_step()` 新增轉運、理貨、暫置、離店等運送中關鍵字
  - `get_status_class()` 同步新增相同關鍵字判斷

#### 技術變更
- `src/Order/YSOrderEnhancer.php` - PayNow / PayUni 回傳資料新增 `tracking_label` 欄位
- `yangsheep-order-enhancer.js` - 物流單號標籤改為 `d.tracking_label || '物流單號'` 動態渲染

### v1.4.10 (2026-02-04)

#### 修復
- **我的帳號地址編輯頁面欄位隱藏**
  - 新增 `woocommerce_address_to_edit` filter（`filter_address_to_edit` 方法）
  - 確保公司、地址2 欄位在前台我的帳號頁面正確隱藏
  - 同時處理「關閉 Last Name」和「台灣化欄位」的設定

#### 技術變更
- `src/Checkout/YSCheckoutFields.php` 新增 `filter_address_to_edit()` 方法
- Hook: `woocommerce_address_to_edit` (priority: 20)

### v1.4.9 (2026-02-04)

#### 修復
- **帳單地址 TWzipcode 初始值問題**
  - 新增 WooCommerce 台灣 state code 對應表（TPE → 臺北市 等）
  - `convertStateCode()` 方法自動轉換 WooCommerce state code 為中文名稱
  - `trySetSelectValue()` 方法支援「台」和「臺」的雙向轉換
- **帳單地址欄位隱藏邏輯改進**
  - `customize_address_fields()` 改為不論國家，啟用台灣化欄位即隱藏公司、地址2
  - 新增 billing/shipping 地址類型偵測，正確取得對應國家
- **我的帳號地址編輯頁面欄位寬度**
  - 新增 CSS 樣式確保 `form-row-first` 和 `form-row-last` 正確並排
  - 姓名（48%）+ 電話（48%）正確顯示在同一行

#### 技術變更
- `yangsheep-myaccount-address.js` 更新至 v1.1.0
- `yangsheep-myaccount.css` 新增地址編輯頁面欄位樣式

### v1.4.8 (2026-02-04)

#### 新增
- **我的帳號地址編輯頁面 TWzipcode 支援**
  - 新增 `yangsheep-myaccount-address.js` 模組
  - 啟用「台灣化欄位」時，地址編輯頁面自動載入 TWzipcode
  - 支援 billing 和 shipping 地址的縣市/鄉鎮市區下拉選單
- **我的帳號帳單地址欄位統一隱藏**
  - 啟用「台灣化欄位」時，我的帳號頁面也隱藏公司、地址2 欄位

#### 修復
- **設定欄位讀取改用 YSSettingsManager**
  - `add_color_field()` / `add_text_field()` / `add_checkbox_field()` 改用 `YSSettingsManager::get()`
  - 修復設定儲存後刷新頁面值不正確的問題

### v1.4.7 (2026-02-04)

#### 重構
- **完全自訂設定儲存機制**
  - 移除 `register_setting()` 的使用，不再依賴 WordPress Settings API 的自動儲存
  - 新增 `handle_settings_save()` 方法處理表單提交
  - 使用自訂的 nonce 驗證（`ys_save_settings`）

#### 修復
- **設定只儲存到自訂資料表**
  - 直接使用 `YSSettingsManager::set()` 儲存到 `wp_ys_checkout_settings` 資料表

### v1.4.6 (2026-02-04)

#### 修復
- **Fatal Error 修復** - `pre_update_option` 參數順序 `($value, $old_value, $option)` 修正

### v1.4.3 (2026-02-04)

#### 修復
- **設定儲存不再重複寫入 wp_options** - 新增 `pre_update_option` 攔截器
- **統一所有設定存取使用 YSSettingsManager**
- **我的帳號頁面樣式只在啟用時載入**

### v1.4.2 (2026-02-04)

#### 修復
- **我的帳號模板覆寫條件** - 只在啟用「我的帳號視覺」設定時才覆寫 myaccount 模板

### v1.4.1 (2026-01-20)

#### 新增
- **設定系統重構**
  - 新增自訂資料表 `wp_ys_checkout_settings` 儲存設定
  - 新增 `YSSettingsTableMaker`、`YSSettingsRepository`、`YSSettingsManager`、`YSSettingsMigrator`
- **後台遷移管理 UI** - 顯示遷移狀態、一鍵遷移、清理舊 wp_options

### v1.3.34 (2026-01-12)

#### 新增
- **WPLoyalty（購物金）整合功能** - 自動偵測、美化兌換區塊、按鈕樣式連動
- **側邊欄優惠券刪除按鈕**
- **訂單狀態設定頁籤**

#### 變更
- **結帳頁面區塊順序調整** - 國家選擇移至「折扣代碼」與「物流選擇」之間

### v1.3.31 (2026-01-11)

#### 變更
- **移除 wc-paynow-shipping 支援** - 僅保留 YS PayNow 和 PayUni 外掛的相容性支援

### v1.3.30 (2026-01-11)

#### 修復
- **清除 WC Session 後門市資料仍顯示** - 前端 JS 載入順序調整 + localStorage 清除檢查

### v1.3.29 (2026-01-11)

#### 改進
- **重構 CVS 相容性 JavaScript** - 整合 PayUni / PayNow 的 CVS 清除邏輯為 `CVSCompatibility` 物件

### v1.3.28 (2026-01-11)

#### 修復
- **CVS Session 刷新後仍存在** - 改用 Server-Side 在 `woocommerce_checkout_update_order_review` 清除 Session

### v1.3.27 (2026-01-11)

#### 修復
- **跨外掛/同外掛 CVS 切換問題** - 任何涉及 CVS 的物流切換都清除所有 Session

### v1.3.26 (2026-01-11)

#### 修復
- **超商選擇器消失問題** - `clearCVSSessions(keepPlugin)` 新增保留當前外掛 Session

### v1.3.25 (2026-01-11)

#### 修復
- **CVS Session 清除錯誤** - 新增 `wc_checkout_params` 備用方案
- **收件人欄位位置** - 改用 Grid 和 priority 控制欄位順序

#### 改進
- **姓氏標籤改為姓名** - 訂購人和收件人統一改為「姓名」

### v1.3.24 (2026-01-11)

#### 修復
- **CVS Session 清除錯誤** - `clearOtherCVSSessions()` 改為 `clearCVSSessions()`

### v1.3.23 (2026-01-11)

#### 改進
- **側邊欄標題/文字/金額樣式統一** - 統一字級與顏色

### v1.3.22 (2026-01-11)

#### 修復
- **收件人欄位並排問題** - 修正非 PayUni 物流時姓名與電話變成上下排列

### v1.3.21 (2026-01-11)

#### 改進
- **付款方式區塊標題** - 新增「選擇支付方式」標題
- **付款方式 Radio 顏色** - 使用 `accent-color` 與主題色一致

#### 修復
- **Checkbox 無法勾選** - 容器從 `<div>` 改為 `<label>`

### v1.3.20 (2026-01-11)

#### 改進
- **商品名稱超連結** - 商品名稱新增連結至商品頁面

### v1.3.19 (2026-01-11)

#### 改進
- **商品明細 HTML 結構簡化** - 新增 `.yangsheep-item-content`，電腦版 Flex + 手機版 Grid

### v1.3.18 (2026-01-11)

#### 改進
- **區塊 Padding 簡化** - 電腦版統一 `20px`
- **Checkbox 樣式統一** - 改用物流卡片相同的勾選框樣式
- **商品明細背景色連動** - 改用 `--section-bg-color`

### v1.3.17 (2026-01-11)

#### 修復
- **刪除按鈕垂直置中** / **區塊 Padding 強制統一** / **標題顏色統一**

### v1.3.16 (2026-01-11)

#### 改進
- **區塊 Padding / 標題統一** - 電腦版 `20px 35px`，手機版 `20px 15px`；標題 18px、600

### v1.3.15 (2026-01-11)

#### 改進
- **商品明細 Grid 佈局** - 從 flex 改為 grid，`grid-template-columns: 20px 50px 1fr auto auto`

### v1.3.14 (2026-01-10)

#### 改進
- **手機版物流卡片/商品明細佈局優化**

### v1.3.13 (2026-01-10)

#### 修復
- **進度條線條消失** - 新增 `::after` 偽元素 + CSS 變數 `--progress-width`
- **HPOS 相容性** - 手動物流單號改用 `$order->update_meta_data()`

### v1.3.12 (2026-01-09)

#### 功能
- WooCommerce Block Checkout 相容性、結帳頁面自訂佈局、TWzipcode、物流卡片、CVS Session 相容性

### v1.3.0 (2026-01-07)

- 物流選擇卡片化、物流選擇區塊獨立、AJAX Fragment 更新機制

### v1.2.0 (先前版本)

- 結帳頁面自訂佈局、TWzipcode、後台調色與圓角、優惠券 AJAX、購物金整合、我的帳號/訂單頁面樣式

### v1.0.0 (初始版本)

- 初始版本發布、基本結帳頁面優化、WooCommerce 模板覆寫

---

## 金流/物流整合服務

我們提供 **PayUni 統一金** 與 **Shopline** 金流特約申辦：

- 刷卡手續費最低 **2% 起**
- 提供 WooCommerce 金流串接模組
- 技術支援

歡迎聯繫洽詢：https://yangsheep.com.tw

---

## 開發者

羊羊數位科技有限公司
https://yangsheep.com.tw
