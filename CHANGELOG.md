# Changelog - YANGSHEEP 結帳優化

所有此外掛的重要變更都會記錄在此檔案中。

格式基於 [Keep a Changelog](https://keepachangelog.com/zh-TW/1.0.0/)，
版本號遵循 [Semantic Versioning](https://semver.org/lang/zh-TW/)。

---

## [1.4.3] - 2026-02-04

### 修復
- **設定儲存不再重複寫入 wp_options**
  - 新增 `pre_update_option` 攔截器，阻止 WordPress Settings API 寫入 wp_options
  - 設定現在只會存入自訂資料表 `wp_ys_checkout_settings`
- **統一所有設定存取使用 YSSettingsManager**
  - `class-yangsheep-checkout-order-enhancer.php` - 改用 YSSettingsManager
  - `class-yangsheep-wployalty-integration.php` - 改用 YSSettingsManager
  - `templates/checkout/form-login.php` - 改用 YSSettingsManager
  - `templates/checkout/form-shipping.php` - 改用 YSSettingsManager

---

## [1.4.2] - 2026-02-04

### 修復
- **我的帳號模板覆寫條件**
  - 只在啟用「我的帳號視覺」設定時才覆寫 myaccount 模板
  - 停用時使用 WooCommerce 原始模板

---

## [1.4.1] - 2026-01-20

### 新增
- **設定系統重構**
  - 新增自訂資料表 `wp_ys_checkout_settings` 儲存設定
  - 新增 `YSSettingsTableMaker` - 資料表建立類別
  - 新增 `YSSettingsRepository` - CRUD 操作 + 快取
  - 新增 `YSSettingsManager` - 統一設定存取介面
  - 新增 `YSSettingsMigrator` - wp_options 資料遷移
- **後台遷移管理 UI**
  - 顯示遷移狀態（wp_options 設定數量、自訂資料表設定數量）
  - 一鍵遷移按鈕
  - 清理舊 wp_options 設定按鈕

### 修復
- **我的帳號頁面顏色設定**
  - 修正 CSS 變數引用問題
  - 增加選擇器權重確保樣式生效

---

## [1.3.34] - 2026-01-12

### 新增
- **WPLoyalty（購物金）整合功能**
  - 新增後台「購物金整合」設定頁籤
  - 自動偵測 WPLoyalty (WooCommerce Loyalty Rewards) 外掛
  - 美化購物金兌換區塊，與結帳頁面視覺整合
  - 顯示可用點數、說明文字、兌換按鈕
  - 按鈕樣式自動連動佈景主題設定

- **側邊欄優惠券刪除按鈕**
  - 結帳金額摘要中的優惠券現在顯示刪除按鈕
  - 可直接在側邊欄移除已套用的優惠券

- **訂單狀態設定頁籤**
  - 將訂單狀態顏色設定獨立為「訂單狀態」頁籤
  - 後台設定頁面結構優化

### 變更
- **結帳頁面區塊順序調整**
  - 國家選擇移至「折扣代碼」與「物流選擇」之間
  - 新順序：商品明細 → 折扣代碼/購物金 → 國家選擇 → 物流選擇 → 客戶資料 → 付款

### 修復
- 修正購物金區塊因 JS 時序問題被誤隱藏的問題
- 修正後台購物金整合頁籤的 DIV 結構
- 修正後台 Toggle Switch 的 CSS class 名稱不一致問題

### 技術變更
- 新增 `class-yangsheep-wployalty-integration.php` - WPLoyalty 整合類別
- 新增 `yangsheep-wployalty.js` - 前端購物金區塊美化處理
- 新增 `yangsheep-wployalty.css` - 購物金區塊樣式
- `yangsheep-checkout.js` - 新增 WPLoyalty 整合啟用時跳過購物金區塊管理
- `class-yangsheep-checkout-sidebar.php` - 新增優惠券顯示與刪除功能
- `yangsheep-sidebar.css` - 新增優惠券行樣式
- `form-checkout.php` v3.6.0 - 調整區塊順序

---

## [1.3.31] - 2026-01-11

### 變更
- **移除 wc-paynow-shipping 支援** - 僅保留 YS PayNow 和 PayUni 外掛的相容性支援
  - 簡化 Session Keys：移除 `paynow_cvs_store_*` 相關 keys
  - 簡化 localStorage 清理：移除 `paynow_woo_form` 清理邏輯
  - 簡化 hidden fields 清理：移除 `paynow_storeid` 等欄位處理

### 技術變更
- `class-yangsheep-cvs-compatibility.php` - 更新 `$cvs_session_keys` 和 `is_cvs_method()`
- `yangsheep-compatibility.js` v6.0.0 - 移除 wc-paynow-shipping 相關程式碼
  - 移除 `immediateCleanup` IIFE（不再需要搶先清理 localStorage）
  - 移除 `syncOnPageLoad` 方法
  - 更新 `cvsFieldPatterns`、`clearHiddenFields`、`clearLocalStorage`、`isCVSMethod`

### 說明
- YS PayNow 外掛已內建物流切換清理功能（v1.0.6）
- 結帳強化外掛僅負責 PayUni ↔ YS PayNow 跨外掛切換的情況

---

## [1.3.30] - 2026-01-11

### 修復
- **清除 WC Session 後門市資料仍顯示問題** - 修正登入用戶清除 WC Session 後，重新進入結帳頁門市仍顯示的問題
  - 根本原因：`wc-paynow-shipping` 的 `paynow-shipping-save-fields.js` 會從 localStorage 還原表單資料
  - 解法 1：前端 JS 載入順序調整為 priority 5（在 wc-paynow-shipping 的 9 之前）
  - 解法 2：在 JS 載入時立即執行 localStorage 清除檢查（IIFE）
  - 解法 3：如果 Server Session 是空的（PHP 渲染的 hidden fields 為空），清除 localStorage 中的 CVS 資料

### 技術變更
- `yangsheep-checkout-optimization.php` - 將 `yangsheep-compatibility.js` 移至 priority 5 的 `wp_enqueue_scripts` hook
- `yangsheep-compatibility.js` - 新增立即執行的 `immediateCleanup()` IIFE
  - 在 wc-paynow-shipping 執行之前檢查並清除 localStorage
  - 判斷依據：PHP 渲染的 hidden fields 值（即 Server Session 狀態）
- `yangsheep-compatibility.js` - 改進 `syncOnPageLoad()` 方法
  - 200ms 後再次檢查，防止晚執行的 localStorage 還原

---

## [1.3.29] - 2026-01-11

### 改進
- **重構 CVS 相容性 JavaScript** - 整合 PayUni / PayNow / wc-paynow-shipping 的 CVS 清除邏輯
  - 使用 `CVSCompatibility` 物件管理器，結構更清晰
  - 同時清除 `paynow_woo_form` 和 `payuni_woo_form` localStorage
  - 統一處理所有外掛的 hidden fields 清除
  - 移除重複程式碼，提升維護性

### 技術變更
- `yangsheep-compatibility.js` - 完全重構為 `CVSCompatibility` 物件
  - `clearAllCVSData()` - 統一清除入口
  - `clearHiddenFields()` - 清除 YS PayNow / PayUni / wc-paynow-shipping hidden fields
  - `clearLocalStorage()` - 清除 localStorage 記憶功能
  - `cleanLocalStorageForm()` - 過濾 localStorage 中的 CVS 相關欄位
  - `syncWithServer()` - 同步前端與 Server 狀態
  - `syncPayUniWithServer()` - PayUni 按鈕文字同步

---

## [1.3.28] - 2026-01-11

### 修復
- **CVS Session 刷新後仍存在問題** - 修正切換物流後刷新頁面，舊門市資料又出現的問題
  - 根本原因：前次切換物流時 Session 沒有真正被清除
  - 解法 1：改用 Server-Side 在 `woocommerce_checkout_update_order_review` hook 清除 Session
  - 解法 2：前端同時清除 localStorage（`paynow_woo_form`、`ys_paynow_cvs_store`）和 hidden fields

### 技術變更
- `class-yangsheep-cvs-compatibility.php` - 新增 `check_shipping_method_change()` 方法，在 Server-Side 偵測物流變更並清除 CVS Session
- `yangsheep-compatibility.js` - 重構為 `checkAndClearCVSData()` + `clearFrontendCVSData()` + `syncWithServerState()`
- `yangsheep-compatibility.js` - 新增清除 localStorage `paynow_woo_form` 和 `ys_paynow_cvs_store` 的邏輯

---

## [1.3.27] - 2026-01-11

### 修復
- **跨外掛/同外掛 CVS 切換問題** - 修正切換超商物流時，舊門市資料未清除或選擇器不顯示的問題
  - 問題 1：PayUni → PayNow → PayUni 時，第一次切回資料仍在
  - 問題 2：萊爾富 → 全家（同外掛）時，舊門市資料未清除
  - 解法：簡化邏輯，任何涉及 CVS 的物流切換都清除「所有」Session，確保乾淨狀態

### 技術變更
- `yangsheep-compatibility.js` - 移除 `keepPlugin` 邏輯，改為清除所有 CVS Session
- `yangsheep-compatibility.js` - 始終在清除後觸發 `update_checkout` 刷新 UI

---

## [1.3.26] - 2026-01-11

### 修復
- **超商選擇器消失問題** - 修正切換到超取物流時，門市選擇器不顯示的問題
  - 原因：`clearCVSSessions()` 錯誤清除了當前外掛的 Session
  - 解法：偵測當前選擇的 CVS 外掛，只清除「其他外掛」的 Session

### 技術變更
- `yangsheep-compatibility.js` - `clearCVSSessions(keepPlugin)` 新增 `keepPlugin` 參數，保留當前外掛的 Session
- `yangsheep-compatibility.js` - `initCVSSessionClearing()` 使用 `detectCVSPlugin()` 偵測當前外掛

---

## [1.3.25] - 2026-01-11

### 修復
- **CVS Session 清除錯誤** - 修正第一次點擊時 `yangsheep_cvs_compat not defined` 錯誤，新增 `wc_checkout_params` 備用方案
- **收件人欄位位置** - 移除舊的 CSS `order` 設置，改用 Grid 和 priority 控制欄位順序

### 改進
- **姓氏標籤改為姓名** - 訂購人和收件人的「姓氏」標籤統一改為「姓名」（台灣化標準）

### 技術變更
- `yangsheep-compatibility.js` - `clearCVSSessions()` 新增 `wc_checkout_params` 備用方案
- `class-yangsheep-cvs-compatibility.php` - 允許空 nonce 請求作為備用方案
- `yangsheep-checkout.css` - 移除 `#shipping_*_field` 的 `order` 設置
- `class-yangsheep-checkout-fields.php` - 無條件將 `billing_last_name` 和 `shipping_last_name` 標籤改為「姓名」

---

## [1.3.24] - 2026-01-11

### 修復
- **CVS Session 清除錯誤** - 修正切換物流時 `clearOtherCVSSessions is not defined` 錯誤

### 技術變更
- `yangsheep-compatibility.js` - 將 `clearOtherCVSSessions()` 改為 `clearCVSSessions()`

---

## [1.3.23] - 2026-01-11

### 改進
- **側邊欄標題統一** - 結帳金額、運輸方式、購物車內容 H3 標題改為 18px、`color: inherit` 與區塊標題一致
- **側邊欄文字樣式統一** - 所有 label 和商品名稱改為 12px #333
- **側邊欄金額樣式統一** - 所有金額和數量改為 12px #3c3c3c
- **應付總額樣式調整** - 文字 12px、金額 24px
- **運輸方式名稱** - 改為 12px #3c3c3c

### 技術變更
- `yangsheep-sidebar.css` - `.yangsheep-sidebar-title` 改為 18px、`color: inherit`
- `yangsheep-sidebar.css` - `.yangsheep-summary-label`、`.yangsheep-shipping-display-label`、`.yangsheep-item-name` 改為 12px #333
- `yangsheep-sidebar.css` - `.yangsheep-summary-value`、`.yangsheep-item-qty`、`.yangsheep-shipping-display-name` 改為 12px #3c3c3c
- `yangsheep-sidebar.css` - `.yangsheep-amount` 從 28px 改為 24px

---

## [1.3.22] - 2026-01-11

### 修復
- **收件人欄位並排問題** - 修正選擇非 PayUni 物流時，姓名與電話欄位變成上下排列的問題

### 技術變更
- `yangsheep-checkout.css` - 新增 `#shipping_last_name_field` 欄位順序設定 `order: 2`
- `yangsheep-checkout.css` - 電腦版和平板版新增 `#shipping_last_name_field`、`#shipping_first_name_field`、`#shipping_phone_field` 強制 `grid-column: span 1 !important`

---

## [1.3.21] - 2026-01-11

### 改進
- **付款方式區塊標題** - 新增「選擇支付方式」標題
- **付款方式 Label 樣式** - 設定 `padding: 8px 10px` 統一間距
- **付款方式 Radio 顏色** - 使用 `accent-color` 與主題色一致
- **側邊欄標題樣式統一** - 統一結帳金額、運輸方式、購物車內容等標題大小與顏色

### 修復
- **Checkbox 無法勾選** - 將「同訂購人姓名電話」與「填寫訂單備註」容器從 `<div>` 改為 `<label>`，修復點擊無反應問題

### 技術變更
- `form-checkout.php` - 付款區塊新增 `<h3 class="yangsheep-h3-title">選擇支付方式</h3>`
- `form-shipping.php` - Checkbox 容器從 `<div>` 改為 `<label>` 並加入 `for` 屬性
- `yangsheep-checkout.css` - 新增 `.wc_payment_methods` label 和 radio 樣式
- `yangsheep-sidebar.css` - 統一側邊欄標題、label、value 字體大小與顏色

---

## [1.3.20] - 2026-01-11

### 改進
- **商品名稱超連結** - 商品名稱新增連結至商品頁面，方便使用者查看商品詳情

### 技術變更
- `yangsheep-checkout-optimization.php` - `.yangsheep-item-name` 內新增 `<a>` 標籤連結至 `$_product->get_permalink()`
- `yangsheep-checkout.css` - 新增商品名稱連結樣式，hover 時顯示主題色與底線

---

## [1.3.19] - 2026-01-11

### 改進
- **商品明細 HTML 結構簡化** - 新增 `.yangsheep-item-content` 包裹商品內容，結構更易於維護
- **電腦版 Flex 佈局** - `.yangsheep-order-item` 改用 Flex 左右兩區塊：刪除按鈕 + 內容區
- **手機版 Grid 佈局** - `.yangsheep-item-content` 使用 Grid 兩行排列，圖片跨行置中

### 技術變更
- `yangsheep-checkout-optimization.php` - 商品項目新增 `.yangsheep-item-content` 包裹 div
- `yangsheep-checkout.css` - 重構商品明細樣式，電腦版 Flex + 手機版 Grid 分離
- `yangsheep-checkout.css` - 移除舊的複雜 Grid 結構，簡化選擇器

---

## [1.3.18] - 2026-01-11

### 改進
- **區塊 Padding 簡化** - 電腦版所有區塊 padding 改為統一 `20px`
- **Checkbox 樣式統一** - 「同訂購人姓名電話」與「填寫備註」改用與物流卡片相同的勾選框樣式
- **商品明細 Grid 修正** - 使用 `!important` 強制 Grid 佈局，確保刪除按鈕正確定位
- **商品明細背景色連動** - 改用 `--section-bg-color` 與其他區塊一致連動後台設定

### 技術變更
- `yangsheep-checkout.css` - 電腦版 padding 從 `20px 35px` 改為 `20px`
- `yangsheep-checkout.css` - 新增 `.yangsheep-same-as-billing`、`.yangsheep-order-notes-toggle` 自訂勾選框樣式
- `yangsheep-checkout.css` - `.yangsheep-order-item` Grid 子元素加入明確 `grid-column` 和 `grid-row`
- `yangsheep-checkout.css` - `.yangsheep-order-review` 背景色從 `--bgcolor-gray-200` 改為 `--section-bg-color`
- `yangsheep-shipping-cards.css` - 電腦版 padding 從 `20px 35px` 改為 `20px`

---

## [1.3.17] - 2026-01-11

### 修復
- **刪除按鈕垂直置中** - 商品項目的刪除按鈕新增 `align-self: center` 確保垂直置中
- **區塊 Padding 強制統一** - 所有區塊 padding 加入 `!important` 防止被覆蓋
- **標題顏色統一** - 區塊標題新增 `color: inherit` 確保顏色一致

### 技術變更
- `yangsheep-checkout.css` - `.yangsheep-remove-item` 新增 `align-self: center`
- `yangsheep-checkout.css` - RWD padding 規則加入 `!important`，移除 `.ct-order-review` 硬編碼 padding
- `yangsheep-checkout.css` - 統一區塊標題選擇器新增 `h3#order_review_heading`、`color: inherit`

---

## [1.3.16] - 2026-01-11

### 改進
- **區塊 Padding 統一** - 電腦版統一 `20px 35px`，手機版統一 `20px 15px`
- **區塊標題統一** - 所有區塊標題統一 `font-size: 18px`、`font-weight: 600`、`margin: 0 0 15px 0`
- **備註區塊標題** - 新增「其他內容」標題於 `.woocommerce-additional-fields`

### 技術變更
- `yangsheep-checkout.css` - 新增統一區塊標題選擇器，移除重複樣式定義
- `yangsheep-checkout.css` - RWD 區塊新增 `.yangsheep-order-review` padding 控制
- `yangsheep-shipping-cards.css` - 統一各 RWD 斷點的 `.yangsheep-shipping-cards-container` padding
- `form-shipping.php` - 新增 `<h3>其他內容</h3>` 標題

---

## [1.3.15] - 2026-01-11

### 改進
- **商品明細 Grid 佈局** - 將 `.yangsheep-order-item` 從 flex 改為 grid 佈局，提供更精確的元素控制
- **Coupon 區塊間距優化** - 移除 `.yangsheep-coupon` 和 `.yangsheep-coupon-point` 的 margin
- **按鈕間距清理** - 移除 coupon 按鈕下方多餘的 margin

### 技術變更
- `yangsheep-checkout.css` - 商品項目改用 `grid-template-columns: 20px 50px 1fr auto auto`
- `yangsheep-checkout.css` - 手機版 RWD 改用 Grid 兩行佈局 `grid-template-columns: 50px 1fr 20px`
- `yangsheep-checkout.css` - 清理 coupon 區塊相關 margin 設定

---

## [1.3.14] - 2026-01-10

### 改進
- **手機版物流卡片佈局** - 調整 `.yangsheep-shipping-info` 為 65%，`.yangsheep-shipping-price` 為 calc(25% - 30px)
- **手機版商品明細佈局** - 橫向排列：商品名稱左側、數量右側

### 技術變更
- `yangsheep-shipping-cards.css` - 手機版 flex 佈局優化
- `yangsheep-sidebar.css` - 手機版購物車項目佈局調整

---

## [1.3.13] - 2026-01-10

### 修復
- **進度條線條消失問題** - 修正前台訂單列表的物流進度條藍色線條不顯示的 CSS 問題
- **HPOS 相容性修復** - 手動物流單號儲存改用 `$order->update_meta_data()` 確保 HPOS 相容

### 改進
- **進度條計算優化** - 新增 CSS 變數 `--progress-width` 動態計算進度條寬度
- **步驟點樣式強化** - 活躍狀態的圓點加入邊框顏色和過渡動畫

### 技術變更
- `yangsheep-order-enhancer.css` - 重構進度條樣式，使用 `::after` 偽元素繪製活躍線條
- `yangsheep-order-enhancer.js` - 新增 `progressWidth` 計算邏輯
- `class-yangsheep-checkout-order-enhancer.php` - 修正 `save_manual_tracking_data()` 使用 HPOS API

---

## [1.3.12] - 2026-01-09

### 功能
- 優化 WooCommerce Block Checkout 相容性
- 改進結帳頁面自訂佈局
- 整合 TWzipcode 郵遞區號選擇器
- 支援後台調色與圓角設定
- 物流卡片選擇介面
- 跨外掛 CVS Session 相容性處理

### 核心功能
- **結帳頁面視覺優化**: 自訂 CSS、顏色、圓角
- **運送方式卡片**: 美化的運送方式選擇介面
- **超商相容性**: 與 PayNow 超商插件整合
- **TWzipcode**: 台灣郵遞區號自動填入
- **側邊欄優化**: 結帳流程側邊欄配置
- **我的帳號頁面**: 可選的視覺優化
- **優惠券區塊**: AJAX 優惠券輸入
- **購物車管理**: 商品數量調整、刪除功能

---

## 歷史版本

> [!NOTE]
> 以下為推測的歷史版本記錄，基於當前程式碼功能推斷。
> 實際版本歷史可能有所不同。

## [1.3.0 - 1.3.12] - 2025-2026

### 新增
- Block Checkout 支援
- 運送方式卡片介面
- CVS (超商) 相容性模組
- 結帳頁面側邊欄自訂
- TWzipcode 整合
- AJAX 購物車數量更新
- AJAX 商品移除功能
- AJAX 優惠券套用

### 改進
- CSS 變數系統
- 顏色自訂化設定
- 圓角自訂化設定
- 響應式設計優化

## [1.2.x] - 估計 2025

###新增
- 我的帳號頁面視覺優化
- 訂單明細頁面樣式

## [1.1.x] - 估計 2025

### 新增
- 結帳欄位自訂功能
- 基本樣式自訂器

## [1.0.0] - 估計 2024-2025

### 新增
- 初始版本發布
- 基本結帳頁面優化
- WooCommerce 模板覆寫

---

## 開發者資訊

- **開發者**: 羊羊數位科技有限公司
- **網站**: https://yangsheep.art
- **最低 WordPress 版本**: 5.8
- **最低 WooCommerce 版本**: 5.0
- **語言**: 繁體中文

---

## 未來規劃

### v1.4.0 計劃功能
- [ ] 完整 Block Checkout 測試
- [ ] 更多佈景主題相容性測試
- [ ] 性能優化
- [ ] 多語言支援

### v2.0.0 長期計劃
- [ ] Gutenberg 區塊支援
- [ ] 視覺化頁面編輯器
- [ ] A/B 測試整合
- [ ] 轉換率追蹤

---

**最後更新**: 2026-01-11
**目前狀態**: 穩定版本，持續維護中
