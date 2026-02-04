# YANGSHEEP 結帳強化外掛

優化 WooCommerce 結帳頁面、我的帳號、訂單頁面的 WordPress 外掛。

## 版本資訊

**當前版本**：1.4.12
**最後更新**：2026-02-04
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
│       └── yangsheep-order-enhancer.js    # 訂單強化 JS
├── includes/
│   ├── class-yangsheep-checkout-customizer.php     # 自訂器
│   ├── class-yangsheep-checkout-settings.php       # 設定頁面
│   ├── class-yangsheep-checkout-sidebar.php        # 側邊欄類別
│   ├── class-yangsheep-checkout-fields.php         # 結帳欄位設置
│   ├── class-yangsheep-shipping-cards.php          # 物流卡片類別
│   ├── class-yangsheep-checkout-order-enhancer.php # 訂單強化類別
│   └── class-yangsheep-third-party-shipping-compat.php # 第三方物流相容性
├── templates/
│   └── checkout/
│       ├── form-checkout.php              # 結帳表單佈局
│       ├── review-order.php               # 訂單明細
│       └── shipping-cards.php             # 物流卡片模板
├── README.md                              # 本檔案
└── yangsheep-checkout-optimization.php    # 主外掛檔案
```

---

## 核心類別說明

### YANGSHEEP_Checkout_Fields
結帳欄位設置類別，處理：
- WooCommerce 運送設定檢查
- First Name / Last Name 關閉選項
- 台灣化欄位設置
- 欄位排序與寬度
- 訂單備註設置
- **收件人電話驗證**（`woocommerce_checkout_process` hook）

### YANGSHEEP_Third_Party_Shipping_Compat
第三方物流相容性處理：
- 綠界 CVS 欄位顯示/隱藏控制
- PayNow CVS 欄位顯示/隱藏控制
- 內部欄位（Reserved NO、Ship Date、LogisticsSubType、CVSStoreID）CSS 隱藏
- **手機號碼前端驗證**（JS 即時驗證）
- 僅在啟用對應物流外掛時載入

### YANGSHEEP_Checkout_Order_Enhancer
訂單頁面強化：
- 前台物流狀態卡片渲染
- 後台手動配送 Meta Box
- 物流類型偵測（YS PayNow / PayUni / 其他）
- AJAX 貨態更新

---

## 手機號碼驗證機制

### 前端驗證（JS）
位置：`class-yangsheep-third-party-shipping-compat.php`

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
位置：`class-yangsheep-checkout-fields.php`

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

### v1.4.12 (2026-02-04)
- **修復**：我的帳號收件人地址編輯頁面電話欄位現在正確顯示為全寬

### v1.4.11 (2026-02-04)
- **修復**：我的帳號帳單地址編輯頁面現在正確只顯示姓名、電話、電子郵件（與結帳頁面一致）
- **修復**：關閉 Last Name 時，姓名欄位現在正確顯示為全寬（form-row-wide）

### v1.4.10 (2026-02-04)
- **修復**：新增 `woocommerce_address_to_edit` filter 確保我的帳號地址編輯頁面欄位正確隱藏
- **修復**：公司、地址2 欄位在前台我的帳號頁面現在正確隱藏

### v1.4.9 (2026-02-04)
- **修復**：帳單地址 TWzipcode 初始值問題（WooCommerce state code 轉換為中文名稱）
- **修復**：帳單地址欄位隱藏邏輯（啟用台灣化欄位時正確隱藏公司、地址2）
- **修復**：我的帳號地址編輯頁面欄位寬度（姓名與電話正確並排顯示）

### v1.4.8 (2026-02-04)
- **新增**：我的帳號地址編輯頁面支援 TWzipcode 縣市下拉選單
- **新增**：啟用台灣化欄位時，我的帳號帳單/收件地址也隱藏公司、地址2 欄位
- **修復**：設定欄位讀取改用 YSSettingsManager，修復設定儲存後無法正確顯示的問題

### v1.4.7 (2026-02-04)
- **重構**：完全自訂設定儲存機制，不再使用 WordPress Settings API 的自動儲存
- **修復**：設定現在只會儲存到自訂資料表，不再寫入 wp_options

### v1.4.6 (2026-02-04)
- **修復**：修正 `pre_update_option` 攔截器參數順序錯誤導致的 Fatal Error

### v1.4.3 (2026-02-04)
- **修復**：設定儲存不再重複寫入 wp_options
- **修復**：統一所有設定存取使用 YSSettingsManager
- **修復**：我的帳號頁面樣式只在啟用時載入

### v1.4.2 (2026-02-04)
- **修復**：我的帳號模板只在啟用「我的帳號視覺」時覆寫

### v1.4.1 (2026-01-20)
- **重構**：設定系統遷移至自訂資料表 `wp_ys_checkout_settings`
- **新增**：YSSettingsManager 統一設定存取介面
- **新增**：wp_options 設定遷移功能

### v1.3.32 (2026-01-12)
- **新增**：收件人電話手機號碼驗證（09 開頭、10 位數字）
- **新增**：前端 JS 即時驗證 + 後端 PHP 驗證
- **優化**：手動配送 Meta Box 顯示邏輯，PayUni / YS PayNow 物流自動隱藏

### v1.3.31 (2026-01-12)
- **修復**：第三方物流內部欄位（Reserved NO、Ship Date、LogisticsSubType、CVSStoreID）CSS 隱藏
- **優化**：移除 JS 隱藏邏輯，改用純 CSS 強制隱藏

### v1.3.3 (2026-01-12)
- **新增**：第三方物流相容性類別
- **新增**：綠界 ECPay / PayNow CVS 欄位自動顯示/隱藏
- **新增**：CVS 欄位 2 欄 Grid 排版
- **新增**：「選擇超商」「超商門市」標籤置中加粗樣式

### v1.3.2 (2026-01-10)
- **新增**：訂單強化類別（物流狀態卡片）
- **新增**：後台手動配送 Meta Box
- **新增**：PayUni / YS PayNow 物流狀態整合

### v1.3.1 (2026-01-08)
- **新增**：結帳側邊欄區塊
- **新增**：桌機版佈局重構 - sidebar 20% + form 80% flex 佈局
- **新增**：後台設定 - 按鈕 Hover 顏色、物流卡片顏色、側邊欄背景色

### v1.3.0 (2026-01-07)
- **新增**：物流選擇卡片化
- **新增**：物流選擇區塊獨立放置
- **優化**：AJAX Fragment 更新機制

### v1.2.0 (先前版本)
- 結帳頁面自訂佈局
- TWzipcode 台灣縣市鄉鎮郵遞區號下拉選單
- 後台可調整按鈕顏色、區塊顏色、圓角等樣式
- 優惠券 AJAX 套用功能
- 購物金區塊整合
- 智慧折價券區塊支援
- 我的帳號頁面與訂單頁面樣式優化

---

## 金流/物流整合服務

我們提供 **PayUni 統一金** 與 **Shopline** 金流整合服務：

- 刷卡手續費最低 **2% 起**
- 專業 WooCommerce 金流串接
- 完整技術支援

歡迎聯繫洽詢：https://yangsheep.com.tw

---

## 開發者

羊羊數位科技有限公司
https://yangsheep.com.tw
