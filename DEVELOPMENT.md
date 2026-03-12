# 開發原則與紀錄

## 未來功能待辦

- [ ] **Radio 選中顏色獨立設定** - 與邊框 active 顏色獨立設置

---

## 架構說明

### PSR-4 自動載入

本外掛使用手動 PSR-4 自動載入器（不依賴 Composer），定義於 `yangsheep-checkout-optimization.php`。

```php
// 命名空間前綴 → 目錄對應
YangSheep\CheckoutOptimizer\  →  src/
```

類別檔案依命名空間自動載入，例如：
- `YangSheep\CheckoutOptimizer\Admin\YSCheckoutSettings` → `src/Admin/YSCheckoutSettings.php`
- `YangSheep\CheckoutOptimizer\Checkout\YSShippingCards` → `src/Checkout/YSShippingCards.php`
- `YangSheep\CheckoutOptimizer\Settings\YSSettingsManager` → `src/Settings/YSSettingsManager.php`

### 模組結構

```
src/
├── Admin/          # 後台管理
│   └── YSCheckoutSettings.php     # 設定頁面（admin_menu、設定儲存、頁籤渲染）
├── Checkout/       # 結帳前台
│   ├── YSCheckoutCustomizer.php   # Color Picker enqueue
│   ├── YSCheckoutFields.php       # 結帳欄位設置（台灣化、驗證、排序）
│   ├── YSCheckoutSidebar.php      # 側邊欄（金額、購物車概要）
│   └── YSShippingCards.php        # 物流卡片（AJAX Fragment）
├── Compat/         # 第三方相容
│   ├── YSThirdPartyShippingCompat.php  # 綠界/PayNow CVS 欄位控制
│   └── YSWPLoyaltyIntegration.php      # WPLoyalty 購物金整合
├── Order/          # 訂單強化
│   └── YSOrderEnhancer.php        # 前台物流狀態卡片、後台手動配送
└── Settings/       # 設定基礎設施
    ├── YSSettingsManager.php      # Facade（統一讀寫入口）
    ├── YSSettingsRepository.php   # CRUD 操作
    ├── YSSettingsTableMaker.php   # 資料表建立
    └── YSSettingsMigrator.php     # wp_options → 自訂資料表遷移
```

### 命名規範

| 類型 | 前綴 | 範例 |
|------|------|------|
| 類別名稱 | `YS` | `YSCheckoutSettings`, `YSShippingCards` |
| 命名空間 | `YangSheep\CheckoutOptimizer\` | `YangSheep\CheckoutOptimizer\Checkout` |
| 函數/Hook | `ys_` 或 `yangsheep_` | `yangsheep_render_order_items` |
| Meta Key | `_ys_` | `_ys_paynow_store_id` |
| 常數 | `YANGSHEEP_CHECKOUT_` | `YANGSHEEP_CHECKOUT_OPTIMIZATION_VERSION` |

### 類別初始化流程

```
plugins_loaded  →  檢查 WooCommerce 是否存在
      ↓
init            →  初始化所有 Singleton 類別
                    YSCheckoutSettings::get_instance()
                    YSCheckoutCustomizer::get_instance()
                    YSCheckoutFields::get_instance()
                    YSShippingCards::get_instance()
                    YSCheckoutSidebar::get_instance()
                    YSOrderEnhancer::get_instance()
                    YSThirdPartyShippingCompat::get_instance()
                    YSWPLoyaltyIntegration::get_instance()
```

> `YSCheckoutFields` 和 `YSWPLoyaltyIntegration` 也在主入口 `init` hook 中直接初始化。

### 設定系統

設定使用自訂資料表 `{prefix}_ys_checkout_settings`（非 wp_options），透過 Facade 模式存取：

```php
// 讀取
$value = YSSettingsManager::get( 'yangsheep_checkout_button_bg_color' );

// 寫入
YSSettingsManager::set( 'yangsheep_checkout_button_bg_color', '#8fa8b8' );
```

### 模板注意事項

- 模板檔案位於 `templates/` 目錄
- 模板透過 `woocommerce_locate_template` filter 覆寫 WooCommerce 預設模板
- 模板中若需使用命名空間類別，必須在檔案頂部加入 `use` 宣告（`include` 不會繼承命名空間）

```php
// templates/checkout/shipping-cards.php
use YangSheep\CheckoutOptimizer\Checkout\YSShippingCards;
```

---

## 開發原則

### 1. 清晰優於聰明
- 程式碼優先考慮可讀性與維護性
- 避免過度抽象或複雜的設計模式
- 使用明確的變數名稱與函式名稱
- 每個函式只做一件事

### 2. 架構清晰
- 每個檔案有明確的單一職責
- 相關功能放在同一目錄
- 清楚的資料夾結構與命名規範
- 類別統一使用 `YS` 前綴 + PSR-4 命名空間

### 3. 版本控制
- 每次功能更新須更新版本號
- README.md 永遠記錄版本變更
- 程式碼內標註修改日期與版本

### 4. 文件優先
- 每個功能開發前先規劃文件
- 程式碼加上適當註解
- 複雜邏輯須有說明

---

## 開發紀錄

### 2026-01-07 - v1.3.0 物流選擇卡片化

#### 需求說明
將結帳頁面的物流選擇從訂單明細表格中分離出來，並採用更直覺的卡片式 Radio 選擇介面。

#### 技術要點

1. **AJAX Fragment 機制**
   - 必須透過 `woocommerce_update_order_review_fragments` filter 註冊新區塊
   - CSS Selector 必須與 HTML class 完全一致

2. **保留標準 Hooks**
   - 維持 `woocommerce_review_order_before_shipping` 和 `after_shipping`
   - 確保第三方物流外掛相容性

3. **Input 命名**
   - 保持 `<input name="shipping_method[...]">` 結構
   - WooCommerce Core JS 依賴此命名

4. **模板版本維護**
   - 記錄已覆寫的 WooCommerce 模板
   - WC 大版本更新時需檢查同步

5. **CSS 佈局**
   - 物流選項改用 `<div>` 卡片結構
   - 響應式設計支援手機版

#### 新增檔案
- `src/Checkout/YSShippingCards.php`（原 `includes/class-yangsheep-shipping-cards.php`）
- `templates/checkout/shipping-cards.php`
- `assets/css/yangsheep-shipping-cards.css`
- `assets/js/yangsheep-shipping-cards.js`

#### 修改檔案
- `yangsheep-checkout-optimization.php` - 載入新類別
- `templates/checkout/form-checkout.php` - 新增物流卡片區塊位置
- `assets/css/yangsheep-checkout.css` - 調整區塊順序

---

## 模板覆寫清單

| WooCommerce 原始模板 | 外掛覆寫版本 | 原始版本 |
|---------------------|-------------|---------|
| checkout/form-checkout.php | 自訂 (v1.4.15) | 3.5.0 |
| checkout/form-billing.php | 自訂 | - |
| checkout/form-shipping.php | 自訂 | - |
| checkout/payment.php | 自訂 | - |
| checkout/review-order.php | 自訂 (v1.3.1) | - |

> ⚠️ WooCommerce 大版本更新時，需檢查以上模板是否有官方異動

---

## v1.3.1 開發紀錄 (2026-01-08)

### 商品明細重構

#### 需求說明
將 Order Review 區塊重構為「商品明細」，支援數量修改和刪除功能。

#### 技術要點
1. **AJAX debounce** - 數量修改後延遲 1.5 秒才發送請求
2. **保留 HOOK** - 隱藏總額但保留所有 action hook
3. **RWD** - 手機版使用 flex 佈局

#### 新增/修改檔案
- `templates/checkout/review-order.php` - 商品明細模板
- `yangsheep-checkout-optimization.php` - AJAX handler
- `assets/js/yangsheep-checkout.js` - 數量控制/刪除
- `assets/css/yangsheep-checkout.css` - 商品明細樣式

---

## v1.3.13 開發紀錄 (2026-01-10)

### 修復：進度條線條消失問題

#### 問題描述
前台「我的帳號 > 訂單」列表中，展開物流詳情時，進度條的藍色線條沒有顯示，只有灰色底線和圓點。

#### 原因分析
CSS 只定義了 `::before` 偽元素繪製灰色底線，但缺少活躍狀態的藍色線條繪製邏輯。

#### 解決方案
1. 新增 `::after` 偽元素繪製藍色活躍線條
2. 使用 CSS 變數 `--progress-width` 動態控制寬度
3. JS 端計算進度百分比並透過 inline style 注入

#### 技術變更

**CSS (`yangsheep-order-enhancer.css`)**:
```css
.ys-mini-progress::after {
    content: '';
    position: absolute;
    top: 6px;
    left: 5%;
    height: 3px;
    background: #1565c0;
    z-index: 1;
    width: var(--progress-width, 0%);
    transition: width 0.3s ease;
}
```

**JS (`yangsheep-order-enhancer.js`)**:
```javascript
var progressWidth = 0;
if (steps.length > 1 && activeIndex > 0) {
    progressWidth = (activeIndex / (steps.length - 1)) * 90;
}
html = '<div class="ys-mini-progress" style="--progress-width: ' + progressWidth + '%;">';
```

---

### 修復：手動物流單號 HPOS 相容性

#### 問題描述
手動物流單號輸入後無法正確儲存，因為使用了 `update_post_meta()` 而非 HPOS 相容的方式。

#### 解決方案
修改 `save_manual_tracking_data()` 使用 `$order->update_meta_data()` 和 `$order->save()`。

#### 修改檔案
- `src/Order/YSOrderEnhancer.php`（原 `class-yangsheep-checkout-order-enhancer.php`）

