# YANGSHEEP 結帳強化外掛

優化 WooCommerce 結帳頁面、我的帳號、訂單頁面的 WordPress 外掛。

## 版本資訊

**當前版本**：1.7.11
**最後更新**：2026-08-27
**開發者**：羊羊數位科技有限公司
**網站**：https://yangsheep.com.tw

---

## 功能特色

### 1. 結帳頁面優化
- **結帳頁面漸進式重新佈局** - 保留 WooCommerce 標準 Hook/模板，前端契約完整時才啟用 YS 版面
- **桌機版 Flex 佈局** - sidebar 20% + form 80%
- **TWzipcode 整合** - 台灣地址選擇器自動帶入郵遞區號

### 2. 結帳欄位設置
- **關閉 Last Name** - 啟用後只顯示「姓名」欄位（使用 First Name），適合台灣使用習慣
- **台灣化欄位** - 帳單欄位簡化為姓名、電話、電子郵件；運送欄位調整為台灣格式
- **收件人電話必填** - 收件人區塊的電話欄位預設為必填
- **手機號碼驗證** - 台灣手機格式驗證（09 開頭、10 位數字），前後端雙重驗證
- **訂單備註開關** - 用戶需勾選「我需要填寫訂單備註」才顯示備註欄位

### 3. 物流卡片選擇
- 以卡片式 proxy 操作 WooCommerce 原生物流 Radio；原生欄位仍是唯一提交來源
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
- 自動辨識已知 C2C 7-11、全家、萊爾富方法；B2C、OK 或自訂方法由後台以完整 rate ID 指定
- 保留原生門市欄位作為送單資料源；增強成功後改以不可編輯的門市摘要顯示名稱、代號與地址
- 選店區統一為「超商門市」標題、狀態文字、白底虛線面板與全寬按鈕
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
- 主要、標題、次要與輔助文字顏色
- 物流卡片顏色
- 側邊欄背景色
- 區塊圓角設定
- 一鍵恢復預設配色

### 8. 購物金與設定移轉
- YITH Points & Rewards 以安全代理介面直接輸入點數、全部使用、顯示上限，並鏡射 YITH 官方即時換算的折抵金額
- WPLoyalty 保留原生兌換視窗與活動規則，套用一致的結帳視覺
- 完整設定 JSON 匯入／匯出，包含所有功能、配色、物流與 WPLoyalty 顯示文字
- 嚴格 schema、值驗證、寫入鎖與失敗回滾，拒絕未知或不完整的設定檔

---

## 檔案結構

```
yangsheep-checkout-optimizer/
├── assets/
│   ├── css/
│   │   ├── yangsheep-checkout.css         # 結帳頁面樣式（media="not all"，增強後啟用）
│   │   ├── yangsheep-cvs-mode.css         # 超取模式隱藏地址欄位（永遠載入，不受增強 gate）
│   │   ├── yangsheep-cart.css             # 購物車折扣代碼欄位對齊
│   │   ├── yangsheep-sidebar.css          # 側邊欄樣式
│   │   ├── yangsheep-myaccount.css        # 我的帳號樣式
│   │   ├── yangsheep-order.css            # 訂單頁面樣式
│   │   ├── yangsheep-shipping-cards.css   # 物流卡片樣式
│   │   └── yangsheep-order-enhancer.css   # 訂單強化樣式
│   └── js/
│       ├── jquery.twzipcode.min.js        # TWzipcode 套件
│       ├── yangsheep-admin-settings.js    # 設定匯入／匯出
│       ├── yangsheep-checkout.js          # 結帳頁面 JS
│       ├── yangsheep-shipping-cards.js    # 物流卡片 JS
│       ├── yangsheep-wployalty.js          # WPLoyalty 整合 JS
│       ├── yangsheep-order-enhancer.js    # 訂單強化 JS
│       └── color-picker-init.js           # 後台顏色選擇器初始化
├── src/                                   # PSR-4 自動載入（命名空間：YangSheep\CheckoutOptimizer）
│   ├── Admin/
│   │   └── YSCheckoutSettings.php         # 後台設定頁面
│   ├── Checkout/
│   │   ├── YSCheckoutCustomizer.php       # 自訂器（Color Picker enqueue）
│   │   ├── YSCheckoutFields.php           # 結帳欄位設置
│   │   ├── YSCheckoutLayout.php           # 標準 Hook 區塊與漸進式版面目標
│   │   ├── YSCheckoutSidebar.php          # 側邊欄三盒（結帳金額/運輸方式/購物車內容，id fragment 更新）
│   │   └── YSShippingCards.php            # 物流卡片類別
│   ├── Compat/
│   │   ├── YSThirdPartyShippingCompat.php # 第三方物流相容性
│   │   ├── YSWPLoyaltyIntegration.php     # WPLoyalty 購物金整合
│   │   ├── YSYithCouponDisplay.php        # YITH 折扣標籤整合
│   │   └── YSYithPointsIntegration.php    # YITH Points 搬移與診斷
│   ├── Order/
│   │   └── YSOrderEnhancer.php            # 訂單頁面強化
│   └── Settings/
│       ├── YSSettingsManager.php          # 設定管理門面（Facade）
│       ├── YSSettingsRepository.php       # 設定 CRUD 操作
│       ├── YSSettingsTableMaker.php       # 設定資料表建立
│       ├── YSSettingsMigrator.php         # 設定資料遷移
│       └── YSSettingsTransfer.php         # 完整設定匯入／匯出、驗證與回滾
├── templates/
│   └── checkout/
│       └── shipping-cards.php             # 直接 include 的視覺 proxy partial
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

### YSSettingsTransfer (`Settings\YSSettingsTransfer`)
完整設定移轉服務：
- 依 canonical key 清單輸出完整、版本化 JSON
- 嚴格拒絕未知 key、缺少 key、非法 CSS、HTML 與不支援的 schema
- 匯入寫入失敗時還原先前設定；與一般儲存、遷移及清理共用寫入鎖

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
- 第三方物流欄位由各物流外掛原生節點作為資料來源，YS 只在已辨識的節點上做漸進式排版與顯示同步

---

## 版本紀錄

格式基於 [Keep a Changelog](https://keepachangelog.com/zh-TW/1.0.0/)，版本號遵循 [Semantic Versioning](https://semver.org/lang/zh-TW/)。

### v1.7.11 (2026-08-27)

- **收件人電話位置被 WooCommerce 前端 locale 重排修正**：WooCommerce 8.9+ 將 phone 納入 address-i18n.js 的 locale 欄位——頁面載入與切換國家時，前端會用 core locale 的 phone 條目（priority 100、label「聯絡電話」、class `form-row-wide`）蓋掉伺服器輸出並依 locale priority 重排 DOM，導致電話欄位掉到詳細地址之後、「訂購人電話／收件人電話」被統一改名「聯絡電話」（伺服器端 HTML 順序其實正確，是前端 JS 事後搬動）。新增 `woocommerce_get_country_locale_default` / `_base` filter 將 locale phone 條目對齊 YS 契約：priority 15（同 `force_phone_fields`）、label / class / placeholder 交還伺服器端欄位定義、required 保留原值（避免 address-i18n 把欄位視覺降為選填並拔掉前端必填驗證 class）。

### v1.7.10 (2026-08-27)

- **PayNow 官方物流（wc-paynow-shipping）相容**：修正選「非 PayNow 物流」（宅配等）時收件人電話被 PayNow 前端 JS 連坐隱藏 — `shipping_phone` 掛有 `paynow-shipping-field` class，該外掛切換物流時會 hide 全部自家欄位；改以永遠載入的 `!important` 規則強制顯示，enhanced / native 模式皆生效。
- **站方自訂選店複製鈕（`button.ys-cvs-btn`）樣式接管**：站點以自訂片段複製第二顆 `#choose-cvs-btn` 到收件欄位區時，native 模式修正為全寬按鈕（不再被欄位 Grid 擠成窄條斷行）；enhanced 模式已有 YS 超商門市面板、且 PayNow 點擊事件只綁第一顆按鈕，複製鈕屬重複死鈕 → 直接隱藏。
- **訂單備註切換器跟隨備註欄位**：切換器改由 JS 固定插在 `#order_comments_field` 正上方，電子發票（速買配等）模組插入其他資訊區塊時維持「發票模組 → 切換器 → 備註欄位」順序，與第三方腳本執行先後無關；Grid 版面下切換器獨占整列。
- **備註顯示同步閘門放寬**：由「僅 enhanced」放寬為「enhanced 或切換器實際可見」— CSS 優化外掛（WP Rocket RUCSS 等）把 `media="not all"` 增強樣式攤平時，native 模式也會顯示切換器，此時同樣接管備註欄位顯示，修正「勾選框看得到但勾了沒反應」；切換器隱藏時維持 Woo 原生行為（fail-open）。

### v1.7.9 (2026-07-28)

- **HPOS 宣告歸位**：由結帳強化主外掛使用已註冊的主檔宣告相容性。
- **Hub Client 2.0.5**：vendor library 不再以自己的檔案路徑宣告 HPOS，避免 WooCommerce 持續寫入 `Invalid plugin file` 錯誤。

### v1.7.8 (2026-07-25)

- **設定動作列整理**：將「一鍵恢復預設配色」移入設定表單底部，與儲存按鈕位於同一動作列；恢復功能、確認流程與 AJAX 寫入契約不變。
- **獨立入口前移**：`結帳強化` 頂層入口移至 WooCommerce 區段後、`電商工具箱` 上方，工具箱子入口與雙入口資料契約維持不變。
- **Hub Client 2.0.4**：新增晚期選單 normalizer；舊版 Client 先載入時仍會校正父選單名稱、圖示與位置，並固定將「系統資訊」「聯絡我們」排在工具箱最後。

### v1.7.7 (2026-07-25)

- **父選單正名「電商工具箱」**：Hub Client 升級 2.0.3，`ys-toolbox` 頂層選單中央統一註冊為「電商工具箱」（`dashicons-store`、位置 56，與開發準則 §4 及各外掛自建選單完全一致）；外掛端另備 label 校正，同站若有尚未更新的舊版 Hub Client（≤2.0.2 先註冊成「YS Plugin」）也會被統一回「電商工具箱」。
- **恢復獨立「結帳強化」入口**：重新註冊舊版頂層端點 `admin.php?page=yangsheep_checkout_optimization`，同時保留 `電商工具箱 → 結帳強化` 的 `admin.php?page=ys-checkout-optimizer` 子入口；兩者共用相同設定頁、權限與資料來源。
- **雙入口導覽相容**：設定頁完整載入色彩及匯入匯出資產，表單儲存後留在管理員原先開啟的入口；未知或偽造 page slug 會回到現行電商工具箱子入口。

### v1.7.6 (2026-07-25)

- **YITH 折抵金額鏡射**：v1.7.5 代理介面漏掉 YITH 原生的「輸入點數 → 可折抵 NT$X」換算，現鏡射官方 `.woocommerce-Price-amount` 金額並經原生 keyup 觸發 YITH 即時換算（YS 不自行以比例推算）；換算中、無效數字與官方端點無回應各有獨立狀態，fragment 重建時清除舊 observer 與 timer。
- **折抵介面版面調整（使用者指定）**：移除「折抵點數」文字 label（aria-label 保留供無障礙）；輸入框靠左，與「套用折抵」「全部使用」同列；「本次最多可使用 X 點」移至區塊右上角，與「購物金折抵」標題垂直置中對齊。窄幅（<480px）時輸入框獨占一列、上限文字靠左。後台預覽同步新版面。

### v1.7.5 (2026-07-25)

- **購物金整合重整**：WPLoyalty 與 YITH Points & Rewards 集中在「購物金整合」分頁，各自提供外掛狀態、啟用開關與正確的操作說明；共同預覽會直接反映結帳頁按鈕、欄位、邊框、背景與圓角設定。
- **YITH 安全直接折抵介面**：不再複製第三方表單。原生 YITH form 留在結帳 form 外作為唯一提交來源，YS 只建立無 `form/name/id` 的數字代理，支援直接輸入、Enter、全部使用、上限、錯誤提示，以及「輸入點數 → 可折抵金額」的 YITH 官方即時換算結果；YS 不自行猜測兌換比例，原生合約、樣式或增強任一失敗即保留原生介面。
- **WPLoyalty 共存硬化**：只接管一個可見且具有原生 reward link 的訊息來源，仍由 WPLoyalty 視窗選擇活動；停用整合時完全不搬移或隱藏原生內容，可與 YITH 同時顯示。
- **結帳設計 token 完整化**：新增主要、標題、次要、輔助文字配色；購物金、物流卡、側邊欄與相容面板統一取用後台按鈕、欄位、區塊及圓角設定。所有 runtime CSS 值先經共用 validator，舊資料若不合法會回到安全預設值。
- **完整設定匯入／匯出**：資料庫管理頁可下載及匯入版本化 JSON，涵蓋全部 canonical 設定與 WPLoyalty 顯示文字；採 exact-key schema、1 MB 上限、CSS／物流 rate id／placeholder 驗證、管理員 nonce、共享寫入鎖與補償回滾。
- **設定儲存安全**：一般後台儲存改與匯入共用 validator；遷移會補齊自訂表缺少但仍存在於 `wp_options` 的 fallback 值，確認完整前禁止清除舊資料。

### v1.7.4 (2026-07-24)

- **避免原生結帳首屏閃現**：主結帳 body 先進入 `ys-checkout-pending`，以 head 內極小 critical CSS 暫時隱藏原生 form；YS 增強成功後立即顯示最終版面。主 JS／CSS／DOM 契約失敗時由 `window.load`、2 秒 timeout、CSS animation 與 `<noscript>` 多路徑自動恢復原生結帳，不會形成白頁。
- **結帳通知固定於主欄**：漸進增強成功後建立 `.yangsheep-checkout-notice-host`，統一收納頁面初始通知、折扣 AJAX 回應及 WooCommerce `checkout_error`，避免通知維持整頁寬度而穿過左側 sticky sidebar；未增強時仍寫入第一個原生 notice wrapper。
- **錯誤後重新對準通知**：WooCommerce 原生送單錯誤原本會先依尚未搬移的 notice group 捲動；同步至主欄後重新計算位置，確保錯誤內容留在可見範圍。

### v1.7.3 (2026-07-23)

#### 結帳欄位外掛相容與舊版收件流程回歸
- 新增「結帳欄位外掛相容強制模式」後台開關（預設關閉）。啟用後會在 Flexible Checkout Fields 等高優先級欄位編輯器執行完畢後，重新套用 YS 的 Last Name、台灣化欄位及超取地址必填規則。
- 強制模式只覆蓋 WooCommerce 核心姓名／地址欄位；第三方自訂帳單欄位、統編欄位及物流 provider 欄位會保留。
- 恢復 v1.6.x 的收件流程：shipping address 固定作為收件資料來源；「運送到不同地址」原生控制只在 YS 漸進增強成功後隱藏，增強失敗時維持 WooCommerce 原生可操作。
- 新增欄位相容矩陣與契約，覆蓋開關 off/on、台灣化欄位、CVS／宅配必填及第三方欄位保留。

#### My Account、設定與樣式架構收斂
- 外掛不再透過 `woocommerce_locate_template` 覆寫任何 checkout、myaccount 或 order 模板；即使開啟「我的帳號視覺」，頁面與訂單明細仍使用目前安裝的 WooCommerce／WooCommerce Subscriptions／主題模板。這保留最新 Actions 欄、POST 回填、表單屬性、`woocommerce_my_account_after_my_address`、`woocommerce_order_details_status` 與 `woocommerce_after_order_details` 等標準擴充點；My Account 強化只載入 opt-in CSS。
- 退役六個沒有前台消費端的設定：登入歡迎文字、登入文字 padding／顏色／背景、結帳連結顏色、訂單總覽背景。升級遷移會同時從 YS 自訂設定表與 `wp_options` 清除，不再顯示無效控制項。
- 設定遷移改為在一般外掛升級請求自動執行，不再只依賴 activation hook；v1→v2 只執行退役設定清理，不會重播初版 `wp_options` 匯入而覆寫自訂表中的現行設定，失敗時也不會提前推進 migration version。
- 後台預設值、runtime fallback、重設配色、自訂表寫入與 checkbox 聚合 API 改共用 `YSSettingsManager::DEFAULT_VALUES`；基準值統一為區塊圓角 `12px`、區塊背景 `#f5f8fa`、欄位背景 `#ffffff`。既有商店已儲存的有效自訂值不會被覆寫。
- 移除沒有現行 markup 生產者的 `.yangsheep-login*`、`.yangsheep-wide*`、`.yangsheep-account-*`、`.yangsheep-create-account` 與 `.ct-order-review` 規則；訂單 CSS 不再強制改寫 Woo 核心顧客地址結構。
- 修正 My Account opt-in CSS 與 Blocksy 雙欄結構衝突：桌機保留主題側欄／內容欄，導覽項目使用可讀的單列垂直配置；390px 手機改為容器內橫向捲動，頁面本身不產生水平溢位。
- PayNow B2C 方法只保留選店介面的相容辨識，不會自動放寬地址必填；如特定 B2C 方案確實為超取，必須在後台以完整 rate ID 明確勾選，未知方法維持要求地址的 fail-safe 行為。
- 架構契約擴充至設定生命週期、模板 ownership、CSS markup ownership、後台 CSS 變數消費端與 PayNow B2C 分流，避免未來再次把無效控制項、過期模板或無作用樣式設定帶回。

### v1.7.2 (2026-07-23)

#### 修復：後台指定超商物流 → 前端隱藏地址欄位失效（v1.7.0 regression）
- **根因**：v1.7.0 為漸進增強 fail-open，把主結帳 CSS 改成 `media="not all"`、只在 enhancement 成功後啟用。而「超取模式隱藏地址欄位」規則（`body.yangsheep-cvs-mode #shipping_*_field`）留在該主 CSS 內：`yangsheep-checkout.js` 雖仍在選中超商物流時對 `<body>` 加 `yangsheep-cvs-mode`，但當 enhancement 未套用（CSS 優化外掛合併、契約未通過、載入時序）時 CSS 不生效 → 地址欄位不隱藏。v1.6.34 以前此 CSS 為無條件載入，故功能正常。
- **修法**：將「隱藏地址欄位」規則抽到新檔 `assets/css/yangsheep-cvs-mode.css`，以**無 `media` gate 的方式永遠 enqueue**，還原無條件行為，並與 server 端 `is_cvs_shipping_selected()`（把地址設非必填、本就未 gate）一致。純視覺並排 grid 排版仍留在 gated 主 CSS。
- 後台設定路徑（`yangsheep_cvs_shipping_methods`）與自動偵測路徑皆適用；不影響已增強站點既有行為。
- **同輪修 rate id 前綴誤判（P1，訂單資料風險）**：後台儲存完整 rate id（`method_id:instance_id`），但前後端曾以前綴比對，`flat_rate:1` 會誤中 `flat_rate:10` → 宅配單被當超商、地址免必填、可能缺地址成單。改為「含 `:` 只完整相等、不含 `:` 才視為舊版 base id 允許 base 相等」，JS/PHP 同步，並新增 `tests/cvs-match-matrix.php` 回歸。
- **多包裹聚合語意修正（P1，訂單資料風險）**：WC 分裝多包裹（`shipping_method[0]`、`[1]`…）時，舊邏輯「遇任一超商即回 `true`」會讓「超取+宅配」混合訂單也把全域收件地址設非必填/隱藏。改為**唯有「所有已選包裹都是超商」才免地址**（任一宅配 → 保留地址與必填，順序無關，fail-safe）：PHP 抽 `all_methods_cvs()`/`is_single_method_cvs()` 純函式；JS 收集所有 package 的 checked/hidden rate 用 `every()`、cache 鍵改為所有 method 的 signature。矩陣含 `超取+宅配`、`宅配+超取` 皆 false的順序無關案例。
- **納入 WooCommerce 單一物流 hidden input（P1）**：WC 在「單一可用物流」時渲染 `<input type="hidden" name="shipping_method[…]">`（不符 `:checked`）。JS 前端改用 `#order_review input.shipping_method` 並過濾 `type==='hidden' || checked`（與 `yangsheep-shipping-cards.js` 的 source-of-truth 一致），避免單一超商漏判、或「hidden 宅配 + checked 超商」誤判全 CVS 而與後端不一致導致隱藏欄位驗證錯誤。
- **PHP/JS 自動偵測 allowlist 對齊（P2）**：統一為 base（去 `:instance`）小寫比對 — PayUni(含 711/fami/hilife)、ECPay(含 ecpay+cvs)、YS PayNow(含 711/family/hilife)，以及好用版 PayNow 的已知 `paynow_shipping_c2c_*` / `woomp_paynow_shipping_c2c_*` 7-11、全家、萊爾富方法；B2C、宅配與未知方法一律非 CVS（fail-safe）。矩陣加入每種自動偵測 ID，總計 **43/43**。
- **PAYUNi 多包裹地址衝突（P1）**：PAYUNi Store Selector 只讀 `shipping_method[0]`，所以「第 1 包 PAYUNi 超取 + 第 2 包宅配」時會在 YS 正確判定混合配送後，再以 inline style 隱藏全域地址。新增範圍受控的 provider bridge：只在目前完整 signature 同時含 PAYUNi 超取與非超取時，等待第三方 handler 完成後呼叫其公開 `showAddressFields()`（必要時同步還原 billing）；使用者快速切換或已回到全超取時不執行，舊版無公開 API 才走限定欄位 fallback。
- **第三方相容樣式收斂（P2）**：Git 追溯確認 PayNow 的三個 `readonly` 門市欄位是 v1.3.34（commit `6813990`）引入的「2 欄輸入框」外觀，並沒有已提交的「純文字摘要」版本。現在只在增強成功且驗證到 PayNow chooser DOM 後，保留原生 inputs 作為可提交資料源、隱藏其視覺容器，改顯示不可編輯的門市摘要；選店列使用 provider-scoped `ys-paynow-store-selector`，套用「超商門市」標題、狀態文字、白底虛線面板及全寬按鈕。同時移除舊 inline CSS 對 PayNow 資料欄位的 `grid-column: span 1` 寬度硬改；DOM 契約不成立時維持原生欄位可見（fail-open）。PAYUNi 舊版 fallback 也只移除地址欄位的 inline `display`，不再清掉其他 inline 樣式。
- **PayNow / PAYUNi 完成頁地址格式衝突（P1）**：兩支物流外掛都註冊假國碼 `PNCVS`，但分別使用 `{paynow_*}` 與 `{payuni_*}` placeholder；兩者共存時後載入的 PAYUNi 會讓 PayNow 訂單顯示未替換的 `{payuni_storename}`。相容層只對實際使用 woomp PayNow CVS 且已有 `_shipping_paynow_storeid` 的訂單改用 `YS_PAYNOW_CVS`，資料與其他物流完全不受影響。
- **相容層與後台設定硬化**：第三方選店控制同步收集所有 checked/hidden package，不再只讀第一包；後台清楚說明完整 rate ID、混合包裹與手動指定規則，儲存時移除空值/重複值並用嚴格比對回顯。
- **驗證關卡**：架構契約 **94/94**、CVS 判定矩陣 **43/43**（含「超商 + 未解析 package」一律保留地址），另含 dev-checkout 真實選店、下單、完成頁、故障注入、桌機/手機視覺與跨物流回歸。

### v1.7.1 (2026-07-18)

#### 發布品質關卡與視覺收尾（發布後 REVIEW 三項）
- **契約測試跨平台修正（P1 release gate）**：`tests/contract-v1.7.0.php` 的 `source()` 讀檔統一將 CRLF 正規化為 LF。Windows 乾淨 checkout（`core.autocrlf=true`）工作樹為 CRLF，固定 LF 字串斷言會誤判 1 條（shipping company Taiwan-fields gate）；正規化後 74/74 在 LF/CRLF 工作樹皆可重現。
- **折扣碼輸入框 CSS 同步（P2）**：`.yangsheep_checkout_coupon #coupon_code` → `#ys_coupon_code`（v1.7.0 改唯一 id 後樣式成死碼，依賴主題樣式）。
- **YITH proxy 版面（P2）**：flatten 容器改用 proxy 專屬 class `ys-yith-proxy-form`，移除對第三方 `.ywpar_apply_discounts` 的 `flex-direction: column !important` 硬改——該規則會把直接文字節點拆成獨立 flex item（訊息逐行碎裂、手機高度爆增）。新樣式：訊息自然流排、點數輸入框行內、套用按鈕全寬。

### v1.7.0 (2026-07-16)

#### 原始設計版面回歸（CYBERBIZ 參考設計）
- 主欄順序回歸原始設計：**運送國家 → 商品明細 → 折扣代碼 → 選擇運送方式 → 帳單資訊 → 選擇支付方式**（付款區改掛 `woocommerce_checkout_after_customer_details`）。
- 側邊欄回歸三盒設計：**結帳金額**（商品小計/運費/應付總額）、**運輸方式**（已選名稱）、**購物車內容**（可摺疊、預設展開）。由 `YSCheckoutSidebar` 伺服器端渲染，內容以 `#id` fragment 隨每次 `update_checkout` 重繪 — **不在會被 Woo fragment 替換的節點上放自訂標記**（根治 AJAX 後樣式/toggle 失效）。
- 原生 `#order_review`（持久 wrapper）移到運送卡片下方：核心列（商品/小計/運費/總計）由 gated CSS 隱藏，**第三方掛標準 review hooks 的內容（超商選店等）就地顯示**，位置與原始設計一致、無表格框線。
- 折扣區視覺位置回到主欄（商品明細與運送方式之間）。**YITH 兌換介面改走「視覺 proxy」**（與物流卡片同一模式）：原生介面留在 `form.checkout` 外原位置作為提交事實源，coupon 區內只放淨化後的代理（無 name/id/`<form>`，代理按鈕同步點數值後觸發原生按鈕）。**原因（P0）**：Woo/YITH fragment 以 HTML 字串重繪節點時，位於 `form.checkout` 內的巢狀 `<form>` 會被 parser 丟棄，兌換按鈕的 form owner 變成 checkout form — 按「套用折抵」會同時觸發 `checkout_place_order` 與付款請求（瀏覽器實證）。含 `<form>` 的第三方節點一律不得移入 checkout form（JS 有防線檢查）。非 AJAX POST 的欄位遺失由既有快照機制還原。
- 超商選店等第三方 review-hook 內容顯示於「選擇運送方式」**容器內**（`#order_review` appendTo 物流 wrapper），不再是獨立區塊。
- `initPointRedeemBlock` 加增強 gate：未增強時不搬移任何第三方購物金節點（避免移入 hidden 區塊消失）。
- 側邊欄「運輸方式」fragment 根節點**空狀態也輸出**（否則一次空 fragment 會讓後續 AJAX 找不到替換目標）；摺疊控制改為 `<button aria-expanded aria-controls>`（鍵盤可操作）。
- 修 fail-open 漏洞：`#shipping_country_field` 與 `#coupons_list` 改為**增強成功後**才移入 YS 區塊（先前未增強也搬 → 原生控制項會消失在 hidden 區塊內）。
- 樣式啟用加入 href fallback（style id 被 CSS 優化外掛改寫時仍可啟用；完全找不到則維持原生結帳）；增強初始化改為可在 `updated_checkout` 重試。
- 手機版側邊欄移至付款區前、桌機還原為 grid 欄（同 v1.6.x 行為）。

#### 我的帳號視覺修復
- **恢復 6 份 myaccount + 2 份 order 模板**與其設定 gate（`yangsheep_myaccount_visual=yes` 才覆寫）：模板與 `yangsheep-myaccount.css`/`yangsheep-order.css` 是配對系統，先前只刪模板留 CSS 造成我的帳號原生 markup 大跑版。checkout/* 維持零覆寫。
- **`order/*` 覆寫僅限「我的帳號」頁面**（`is_account_page()`）：order-received / order-pay 一律用 Woo 核心模板 — 這些端點不載入配對 CSS，套陳舊模板會裸樣式 + 隱藏訪客訂單明細。

#### WooCommerce progressive enhancement 架構
- 移除 `woocommerce_locate_template` 的**無條件**攔截與 10 份過期 checkout 模板覆寫（form-checkout / form-pay / review-order / thankyou / payment 等），checkout 完整交還 WooCommerce 核心模板及標準 hooks；模板攔截只剩「我的帳號視覺」這組 opt-in 配對。
- 新增 `YSCheckoutLayout`，只透過標準 checkout hooks 插入 YS 區塊；前端確認原生 form、order review、payment 與所有目標容器完整後才重排。
- 主題若以額外 wrapper 包住 order review，重排時會保留 wrapper 內其他第三方節點，只移除搬空後的容器。
- 主結帳 CSS 預設以 `media="not all"` 停用，只有重排成功才啟用；初始化失敗時保留未受 YS 樣式影響的 Woo 原生結帳。
- order-pay、order-received 不再套用過期模板；使用 WooCommerce 當前版本輸出（My Account 視覺模板保留為 opt-in，見上）。
- 透過 `woocommerce_before_checkout_shipping_form` 與 `woocommerce_before_order_notes` 恢復「同訂購人姓名電話」和選用式訂單備註，不再依賴複製模板；JS 失效時控制項保持隱藏、Woo 原生欄位保持可用。
- 移除舊模板遺留的帳單國家、運送至不同地址與付款圖示硬隱藏，也不再讓備註開關誤隱藏其他第三方 additional fields。

#### 物流、折扣與第三方相容
- 物流卡片改為純視覺 proxy；原生 `shipping_method[...]` 保留 name、enabled、checked，卡片只觸發原生 radio 的一次 `change`，消除雙重 `update_checkout`。
- 不再重播 `woocommerce_review_order_before_shipping/after_shipping`；標準 hook 僅由 Woo 核心執行一次。
- 取消折扣券不再強制整頁 reload，避免使用者已填欄位遺失。
- YITH Points 只搬移第一個可見且有內容的折抵介面（重複介面標記 `ys-yith-points-duplicate` 隱藏）。
- YITH 非 AJAX 折抵 POST 會以短效 Woo session 快照保存顧客已填欄位，重新載入後還原；不保存付款控制、nonce、密碼或檔案欄位。
- checkout 會移除 woomp 誤載入的 Woo `wc-cart` handler，避免折扣券取消被送出兩次；不影響購物車頁。
- 後台新增 YITH 版本、整合開關、selector 與前台必要條件診斷；整合失敗或關閉時保留 YITH 原生介面。
- 綠界、PayNow、PAYUNI 各自只操作所屬選店節點；第三方物流 CSS 僅限定已知選店按鈕，不再強制改寫物流區所有按鈕或預先隱藏第三方標籤。

### v1.6.34 (2026-07-15)

#### 結帳第三方整合 hotfix
- `YSShippingCards` fragment 改為 priority 5，先執行標準 shipping hooks，再讓 RY/ECPay、PayNow、PAYUNI 的 priority 10 fragment reader 取得資料；修正綠界選店送出空 POST、`LogisticsType Is Not Match`。
- YITH Points 搬移改為 fail-open：整合關閉或前端 JS 失效時保留原生折抵 UI；不再以伺服器 body class 或 CSS 強制控制，只有成功搬入 `.yangsheep-coupon-point` 的節點才標記 mounted。
- Sidebar coupon label 改走 `woocommerce_cart_totals_coupon_label`，讓 `ywpar_discount_*` 顯示友善名稱。
- 移除全站 `woocommerce_shipping_show_shipping_calculator` 強制關閉，避免影響原生購物車頁。
- 移除互相矛盾的 `.woocommerce-form-login__submit { width: 100px; }` 死規則，保留既有全寬樣式。

### v1.6.32 (2026-07-15)

#### 修 YITH Points reward-cart 實際可見 UI 未搬移
**問題**：v1.6.30 誤判 `#yith-par-message-reward-cart` 一律只是 hidden submit target，因此從 default selectors 移除；但 YITH Points Premium 4.27.0 會把可見折抵表單放在 `#yith-par-message-reward-cart`。結果沒有自動搬到 `.yangsheep-coupon-point`，且主 CSS 仍以 `display:none !important` 隱藏原始區塊。

**修法**：
- `YSYithPointsIntegration` default selectors 同時支援 `#yith-par-message-cart` 與 `#yith-par-message-reward-cart`
- CSS 改為：原位置的 `#yith-par-message-reward-cart` 隱藏；搬進 `.yangsheep-coupon-point` 後 `display:block!important`
- 保留 `#yith-par-message-reward-cart input[name="ywpar_input_points"]` 作為 YITH 表單提交目標，不再把可見 UI 誤殺

### v1.6.31 (2026-07-14)

回應獨立 review P1 / P3。

#### P1 修 WPLoyalty `.empty()` 清掉 YITH race condition
**問題**：`yangsheep-checkout.js:168` 在 `updated_checkout` +300ms 搬 YITH `#yith-par-message-cart` 到 `.yangsheep-coupon-point`；但 `yangsheep-wployalty.js:60` 也在同 +300ms 觸發，且 `processWLRMessage` line 160 `$couponPoint.empty().append($customBlock)` **會清掉 `.yangsheep-coupon-point` 內所有子元素**，包含剛剛搬進來的 YITH 訊息。

WPLoyalty on + YITH on 情境下實際跑起來仍可能 YITH 訊息先被搬進去、再被 WPLoyalty 清空。

**修法**：`processWLRMessage` 兩處改為 additive / scoped，不再共用 `.empty()`
- Line 160：`$couponPoint.empty().append(...)` → `$couponPoint.find('.ys-wployalty-block').remove(); $couponPoint.append(...)`
- Line 137-141：「無 WLR 訊息就 hide 整個 couponPoint」改為只移除自己既有的 `.ys-wployalty-block`；若其他外掛（YITH）還有子元素則保留容器 show

副作用：`.ys-wployalty-block` wrapper 是 WPLoyalty 建立時就標記的；YITH 訊息保留 YITH 自己的 id（`#yith-par-message-cart`），兩者互不衝突。

#### P3 YSYithPointsIntegration docblock 修正
- 舊 docblock（v1.6.29）仍描述會搬 `#yith-par-message-reward-cart`，但 v1.6.30 實作已移除該 selector
- 更新 docblock 加入 v1.6.30 的說明段：解釋為何 reward-cart 被拿掉、真正供顯示的 selector 是誰
- 不是 runtime bug，但避免後續 review / 維護誤判

### v1.6.30 (2026-07-14)

回應獨立 review P2 / P3。

#### P2 修 `initPointRedeemBlock` early return 阻擋 YITH selector
- 舊版：`yangsheep-checkout.js:113` 只要 `yangsheep_wployalty.enabled` → 整個函式 `return`
- 副作用：同時啟用「WPLoyalty 整合」與「YITH Points 整合」時，v1.6.29 加的 YITH selector 合併邏輯**不會執行**
- 修法：拿掉 early return，改為條件式收集 selector
  - WPLoyalty enabled → 跳過 WLR selector（交由 wployalty.js）
  - YITH enabled → 照樣收集 YITH selector
  - 兩者並存 → 只搬 YITH
  - 皆未啟用 → 收集 WLR（原本邏輯）
  - 收集後 `selectors.length === 0` 才 early return

#### P3 `#yith-par-message-reward-cart` 從 selectors 移除
- 舊 v1.6.29 selectors 包含 `#yith-par-message-reward-cart`
- 但外掛內部 CSS `yangsheep-checkout.css:644` 用 `display: none !important` 硬隱藏
- 且該元素是 YITH 的 hidden submit target（內含 `ywpar_input_points` 供表單送出）
- 搬到 `.yangsheep-coupon-point` 只會複製一份不可見的元素，沒視覺效果反而 duplicate DOM
- 修法：預設 selectors 只留 `#yith-par-message-cart`（真正供顯示的訊息）
- 需要擴充仍可用 `apply_filters( 'yangsheep_yith_points_selectors', $arr )` 加回

### v1.6.29 (2026-07-14)

三合一，回應獨立 review 提出的 P1 / P2：

#### 1. 修 CVS 「門市名稱/地址/電話」空 label 假回傳
`src/Compat/YSThirdPartyShippingCompat.php::toggleEcpayCvsFields()`
- Root cause：好用版擴充 woomp / RY Tools 新版只註冊 `<p>` label wrapper（`CVSStoreName_field / CVSAddress_field / CVSTelephone_field`）**沒對應 `<input>`**，實際門市名稱寫進 `<span class="show_choose_cvs_name">`
- 舊版 `.show()` 這 3 個 `<p>` 造成永遠顯示空 label，使用者誤以為超取沒回傳
- 修法：`$ecpayFields.each()` 檢查內部有 `input/textarea/select` 才 `.show()`，否則 `.hide()`

#### 2. HFCM `#customer_details max-width:90%` scoped override
- 第三方 HFCM snippet 若注入 `max-width: 90%` 給 `form.checkout #customer_details`，會造成付款區與收件人區塊寬度不齊
- 加 3 層 CSS selector（`body.woocommerce-checkout`、`.yangsheep-design-checkout-page`、`form.checkout`）+ `!important` 覆蓋回 `max-width: 100%`
- 順便讓 `.woocommerce-checkout-payment` / `.yangsheep-payment` 一致

#### 3. YITH Points and Rewards 結帳頁整合（正式模組化）
新增 `src/Compat/YSYithPointsIntegration.php`
- 檢測 YITH Points and Rewards（YWPAR）外掛啟用
- 後台開關 `yangsheep_yith_points_integration`（預設 yes）
- 存在時 `wp_localize_script` 傳 `yangsheep_yith_points = { enabled: true, selectors: [...] }` 到前端
- `yangsheep-checkout.js::initPointRedeemBlock` 擴充：把原本只抓 WPLoyalty `.wlr_point_redeem_message` 的邏輯，並抓 YITH `#yith-par-message-cart` + `#yith-par-message-reward-cart` 一併搬進 `.yangsheep-coupon-point`
- 開放 `apply_filters( 'yangsheep_yith_points_selectors', $arr )` 供擴充
- 保留舊有 scoped CSS（`.yangsheep-coupon-point .ywpar_apply_discounts_container` 等）於主 stylesheet，不重複移動避免 backward compat

### v1.6.28 (2026-07-11)

#### 修復（Sidebar 商品名寬度擠壓）
- `.yangsheep-item-name` 加 `word-break: break-word` + `overflow-wrap: anywhere` + `hyphens: auto`
- `.yangsheep-cart-item .yangsheep-item-name` 加 `min-width: 0` 讓 flex 子元素能縮到內容以下（否則 flex intrinsic size 不允許 wrap）
- 加保險規則：空 `<span>` 不佔位（`display: none`）避免萬一有第三方 escape 疏漏

#### 新增（YITH 折扣代碼相容顯示）
- 新增 `src/Compat/YSYithCouponDisplay.php` — 攔截 `woocommerce_cart_totals_coupon_label` filter
- **內建 prefix 對照表**（可 filter 擴充）：
  - `ywpar_discount_*` → **購物金折抵**（YITH Points & Rewards）
  - `ywpar_earn_*` → **YITH 賺取點數**
  - `ywsbs_*` → **YITH 訂閱折扣**
  - `yith_ywgc_*` → **禮物卡折抵**
  - `yith_wcac_*` → **購物車回訪優惠**
  - `yith_ywraq_*` → **報價折扣**
- **後台開關** `yangsheep_yith_coupon_friendly_label`（電商工具箱 → 結帳強化 → 結帳頁面 → 欄位設定），預設啟用
- 第三方擴充：`apply_filters( 'yangsheep_yith_coupon_label_map', $map )` 允許擴充其他 prefix

### v1.6.27 (2026-07-11)

#### 修復（購物車商品名稱變體屬性顯示為 raw HTML tag）
- **症狀**：Sidebar 購物車內容 + 結帳頁下方訂單明細，變體商品名稱顯示成 `Tritan™ Renew 隨行杯<span> -</span>莫蘭迪粉`，`<span>` 標籤露出成文字
- **Root cause**：WC 變體名稱由 `wc_get_formatted_variation()` 產生，本身含 `<span>` 標籤，但外掛用 `esc_html()` 全部 escape 掉
- **修法**（兩處）：
  1. `src/Checkout/YSCheckoutSidebar.php::render_cart_contents()` — 商品名稱走 `woocommerce_cart_item_name` filter，output 改用 `wp_kses_post()`
  2. `yangsheep-checkout-optimization.php::yangsheep_render_order_items()` — 同上
- **副作用**：也允許第三方外掛（如 Product Add-ons、WPC Product Bundles）掛 `woocommerce_cart_item_name` filter 注入額外標記正確顯示

### v1.6.26 (2026-07-11)

#### 修復（結帳頁數量調整期間可送單 → 舊購物車建單）
- **購物車突變鎖（實體在途證據模型）**：點擊數量 +/−/移除商品 到 `updated_checkout` 重繪完成之間（debounce 1.5s + AJAX + 更新往返 ≈ 3 秒），畫面數量與伺服器購物車不一致；此期間送出結帳會以「舊購物車」建單（實測產生數量/金額錯誤的訂單）
- **鎖定條件＝三種在途證據任一存在**：qty debounce timer 在途、cart 突變 AJAX 在飛（jqXHR 集合＋`readyState` 過濾自癒）、cart 已寫入等待重繪落地。每個事件點重算——**無關來源（配送方式/地址）觸發的 `updated_checkout` 不會提前解鎖**，連續點擊跨越 AJAX 邊界也不會被前一輪完成事件解鎖
- **fail-closed 看門狗**：只監控「重繪等待卡死」；檢查點上若 `update_order_review` XHR 仍在飛（慢站/CDN）＝續等，確認無任何在途請求（updated_checkout 丟失、cart 已定案）才結算解鎖——絕不因「時間到」開閘
- **每商品獨立 debounce**：原共用單一 timer，第二個商品的點擊會取消第一個商品尚未送出的更新（實測兩商品各 +1 只有一項生效）→ 改為 `cartKey → timer` 各自獨立
- **突變串行佇列（generation 化＋own XHR token）**：兩條 qty/remove AJAX 併發時，PHP 端各自載入 WC session cart 再寫回，後完成者以舊快照覆蓋先完成者（last-writer-wins）→ 一次只飛一條；各筆成功只標記 `redrawNeeded`，**佇列全部清空後才觸發單次 `update_checkout`**；**上一代重繪未落地時 drain 暫停**（新一代 cart AJAX 不與上一代 `update_order_review` 併發寫 session），結算後自動恢復
- **重繪結算綁定本代自己的 XHR**：WC 原生在 `update_checkout` 後延遲 5ms 才發請求——這段空窗內舊請求完成觸發的 `updated_checkout` 不得結算本代（`awaitingOwnXhrStart` 擋）；本代 trigger 後的第一個 `update_order_review` 即 generation token（`ownRedrawXhr`），被 wc-checkout abort 時自動改綁繼任請求；**只有本代 XHR 確實完成（readyState 4）才結算解鎖**，無關來源的完成事件一概不動；看門狗同樣以本代 XHR 判「慢 vs 丟失」
- **下單按鈕共用鎖（window 級引用計數）**：與 YS Shopline 金流外掛共用 `window.__ysPlaceOrderLocks` 引用計數——「各自記取得前狀態」在雙持有情境仍會互解，改為 count 歸零才考慮 enable、並尊重首位取鎖時的外部 disable；未持鎖時完全不碰按鈕（實測雙持有 maxCount=2 全程零誤 enable）
- **abort 自癒**：突變 XHR 以 `.always()` 於 success/error/abort 全路徑移除，readyState 過濾（4=完成、0=已 abort）為第二道保險——被 abort 的請求不會變成永久在途的殭屍鎖
- **支援探針**：`window.__ysCheckoutOptimizerBuild`（runtime 版本）與 `window.__ysMutationLockDebug()`（唯讀狀態快照），純診斷不影響行為
- 對所有金流閘道皆生效（此 race 不限 SHOPLINE）

### v1.6.25 (2026-05-25)

#### 修復（手動配送單號儲存遞迴 / 訂單編輯頁轉圈圈）
- **`YSOrderEnhancer::save_manual_tracking_data()` 遞迴儲存修復**
- **症狀**：啟用「手動配送單號輸入」後，後台訂單編輯頁按「更新」時可能持續轉圈圈、timeout 或回 500，導致訂單狀態無法修改
- **Root cause**：
  1. 該 callback 同時掛在兩個 hook 上：`save_post_shop_order` + `woocommerce_process_shop_order_meta`，傳統 post type 模式下單次儲存會觸發兩次
  2. 函式末端呼叫 `$order->save()` 會重新觸發整張訂單儲存流程 → 再次觸發 `save_post_shop_order` / `woocommerce_process_shop_order_meta` → 遞迴回 `save_manual_tracking_data()`
- **修法**：
  1. `$order->save()` → **`$order->save_meta_data()`**：只持久化 meta，不觸發訂單儲存流程
  2. 加入 **static array re-entrancy guard**：同一筆 `$post_id` 在同一個 request 內只處理一次，徹底阻擋 dual hook 與遞迴重入

### v1.6.23 (2026-05-25)

#### 更新
- 更新內建 YS Plugin Hub Client 到 v2.0.1，支援市集平台篩選與分類標籤顯示。

### v1.6.22 (2026-04-25)

#### 修復
- **後台 checkbox 預設值未反映 DEFAULT_VALUES** — `add_checkbox_field()` line 644 寫死 fallback `'no'`，不論 `DEFAULT_VALUES` 設什麼一律以「關閉」狀態顯示。
- 例：v1.6.21 新增的 `yangsheep_validate_phone_shipping` 預設 `'yes'`，但 fresh state（DB 無 row）下 UI 顯示為「關閉」，導致使用者誤以為預設未啟用。
- **修法**：`add_checkbox_field()` 改為從 `YSSettingsManager::DEFAULT_VALUES[ $opt_name ]` 動態取 fallback；若 key 不在 DEFAULT_VALUES 才退回 `'no'`。
- **副作用**：使用者按儲存時，因 UI checkbox 預設已勾選，POST 會帶 `yes` → DB 寫入正確 default，循環修正。

### v1.6.21 (2026-04-25)

#### 新增（台灣手機號碼驗證後台開關）
- **後台新增兩個 checkbox**（電商工具箱 → 結帳強化 → 結帳頁面 → 欄位設定）：
  - `yangsheep_validate_phone_shipping` — **收件人電話 台灣手機驗證**（預設 yes）
  - `yangsheep_validate_phone_billing` — **訂購人電話 台灣手機驗證**（預設 no）
- 驗證規則（兩者相同）：`/^09\d{8}$/`（09 開頭 + 10 碼）；輸入時自動 strip 非數字字元（容許 `0975-011-321` 或 `09 7501 1321`）
- 錯誤訊息分三段（更友善）：
  - 不是 09 開頭 → 「必須為 09 開頭的手機號碼」
  - 不是 10 碼 → 「必須為 10 位數字」
  - 其他無效 → 「請輸入有效的手機號碼」
- **訂購人電話預設關閉**：允許市話 / 國際號碼 / 公司電話等使用情境
- **收件人電話預設啟用**：物流配送需要可送達手機 SMS 取貨通知

#### 技術
- `YSSettingsManager::ALL_SETTING_KEYS` + `DEFAULT_VALUES` 新增兩個 key
- `YSCheckoutSettings::$checkbox_options` + `$options` 新增兩個 key
- `YSCheckoutSettings::settings_init()` 新增兩個 `add_checkbox_field()`
- `YSCheckoutFields::validate_shipping_phone()` 開頭加 setting 守衛
- `YSCheckoutFields::validate_billing_phone()` 新方法 + `woocommerce_checkout_process` hook
- 兩個 hook 永遠註冊，內部依設定決定是否實際執行（不需要 conditional add_action）

### v1.6.20 (2026-04-29)

#### 修復（折扣代碼欄位高度）
- **購物車頁 coupon row 對齊**：新增 `assets/css/yangsheep-cart.css`，讓折扣代碼輸入框、使用優惠券按鈕與更新購物車按鈕維持一致高度。
- **Checkout coupon 區塊同步修正**：固定折扣代碼輸入框與按鈕高度為 56px，避免主題表單樣式覆蓋造成高度不一致。
- **手機版響應式**：窄版寬度下折扣代碼輸入框與按鈕改為上下堆疊，避免文字或按鈕擠壓。

### v1.6.19 (2026-04-25)

#### 變更（感謝頁字級統一 + 視覺融合）
使用者回饋：目前字級大小落差過大（總計 32px vs overview 16px vs label 13px），
訂購人資料內部有多餘框線。

- **總計字級收斂**：32px → 22px（降低與其他區塊落差）
- **Overview / Total 視覺融合**：
  - 移除 grid 下方分隔 border（改 margin-bottom）
  - Total 上方改為較淡 1px border（原本是 padding-top + 粗 border）
  - Total label 18 → 15px，與 Overview 對齊
  - Overview value 16 → 15px、Email 15 → 14px
  - Overview label 移除 `text-transform: uppercase` + `letter-spacing`
- **訂購人資料**：address 移除內框與淡藍背景（font: 14px, padding: 0, border/bg: none）
- **區塊標題 h2**：20 → 17px、border-bottom 2px → 1px（更淡）
- **訂單明細 tfoot 最後列**（小計唯一剩下）：16 → 15px、移除主色強調

字級層級收斂到 **13 / 14 / 15 / 17 / 22 / 28**（Hero title）範圍，整體視覺統一。

### v1.6.18 (2026-04-25)

#### 修復（感謝頁合併重複顯示）
- **訂單明細表格 tfoot 濾掉 `payment_method` 與 `order_total`**
  - 付款方式 → 只在 Overview 4 欄卡片顯示
  - 訂單總計 → 只在總計 banner（主色強調 32px）顯示
  - 訂單明細表 tfoot 只留：小計 / 運費 / 稅金（保留金額計算過程）
- 實作：template 內用匿名函式 hook `woocommerce_get_order_item_totals` priority 99，
  在 `do_action('woocommerce_thankyou')` 之後用同一變數 remove_filter，
  不影響其他頁面的訂單總計顯示

### v1.6.17 (2026-04-24)

#### 新增
- **感謝頁（Order Received / thankyou）重新設計** — 新增 `templates/checkout/thankyou.php` 覆寫：
  - Hero 區：成功 / 失敗兩種狀態，含圓形 icon（inline SVG checkmark 或 x）、客製化問候「感謝您的訂購，{客戶名}！」、副標訂購確認訊息
  - 成功狀態頂部有主色 → 輔色漸層條；失敗狀態頂部紅色條
  - **訂單概覽 4 欄卡片**：訂單編號 / 訂購日期 / Email / 付款方式（≤900px 變 2 欄、≤480px 變 1 欄）
  - **總計 banner**：主色強調 32px（手機 24px）
  - **電腦寬度 100%（max-width 1280px）**：比 order-pay 的 820px 寬，讓訂單明細表格有更多橫向空間
  - 水平 padding 交給父層 `.ct-container` 處理，不重複
  - 配色全數透過 CSS 變數對齊後台設定（`--theme-button-background-initial-color` 等）
  - 保留 WC 標準 hooks：`woocommerce_before_thankyou`、`woocommerce_thankyou_{gateway}`（ATM 轉帳指示等）、`woocommerce_thankyou`（訂單明細 + 地址）
  - 失敗狀態保留「重新付款」+「前往我的帳號」按鈕（主色實心 + ghost 外框）

### v1.6.16 (2026-04-23)

#### 變更（桌機 padding 加大）
- 桌機版外層卡片 padding 放大給呼吸感（手機 ≤768px 仍維持 20/10）：
  - `.yangsheep-pay-summary`：桌機 `28px 32px`、手機 `20px 10px`
  - `.yangsheep-design-pay-page .yangsheep-payment`：桌機 `28px 28px`、手機 `20px 10px`
  - `li.wc_payment_method` 維持 `5px`（SDK 貼邊，無論桌機手機）
  - `.payment_box` 維持 `5px`（SDK 貼邊，無論桌機手機）

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
