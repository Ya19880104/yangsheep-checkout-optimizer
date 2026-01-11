# YANGSHEEP 結帳優化外掛

優化 WooCommerce 結帳頁面體驗的 WordPress 外掛。

## 版本紀錄

### v1.3.1 (2026-01-08)
- **新增**：結帳側邊欄區塊 - 獨立顯示結帳金額、運輸方式、購物車內容
- **新增**：桌機版佈局重構 - sidebar 20% + form 80% flex 佈局
- **新增**：後台設定 - 按鈕 Hover 顏色、物流卡片顏色、側邊欄背景色
- **優化**：CSS 變數系統整理，確保所有設定正確套用
- **優化**：PHP 區塊順序符合 CSS order
- **修復**：ct-order-review margin-bottom 問題

### v1.3.0 (2026-01-07)
- **新增**：物流選擇卡片化 - 將物流選項從訂單明細表分離，採用卡片式 Radio 選擇
- **新增**：物流選擇區塊獨立放置於購物金選擇區塊下方
- **優化**：AJAX Fragment 更新機制確保物流選項正確刷新
- **建立**：開發原則文檔與版本控制規範

### v1.2.0 (先前版本)
- 結帳頁面自訂佈局
- TWzipcode 台灣縣市鄉鎮郵遞區號下拉選單
- 後台可調整按鈕顏色、區塊顏色、圓角等樣式
- 優惠券 AJAX 套用功能
- 購物金區塊整合
- 智慧折價券區塊支援
- 我的帳號頁面與訂單頁面樣式優化

## 功能特色

1. **結帳頁面重新佈局** - 透過自訂 Hook 重新排列結帳流程
2. **TWzipcode 整合** - 台灣地址選擇器自動帶入郵遞區號
3. **後台樣式設定** - 完整的 WordPress 設定頁面
4. **物流卡片選擇** - 直覺化的物流方式選擇介面
5. **結帳側邊欄** - 桌機版固定顯示結帳金額與購物車概要

## 檔案結構

```
yangsheep-checkout-optimizer/
├── assets/
│   ├── css/
│   │   ├── yangsheep-checkout.css      # 結帳頁面樣式
│   │   ├── yangsheep-sidebar.css       # 側邊欄樣式 [NEW]
│   │   ├── yangsheep-myaccount.css     # 我的帳號樣式
│   │   ├── yangsheep-order.css         # 訂單頁面樣式
│   │   └── yangsheep-shipping-cards.css # 物流卡片樣式
│   └── js/
│       ├── jquery.twzipcode.min.js     # TWzipcode 套件
│       ├── yangsheep-checkout.js       # 結帳頁面 JS
│       ├── yangsheep-sidebar.js        # 側邊欄 JS [NEW]
│       └── yangsheep-shipping-cards.js  # 物流卡片 JS
├── includes/
│   ├── class-yangsheep-checkout-customizer.php  # 自訂器
│   ├── class-yangsheep-checkout-settings.php    # 設定頁面
│   ├── class-yangsheep-checkout-sidebar.php     # 側邊欄類別 [NEW]
│   └── class-yangsheep-shipping-cards.php       # 物流卡片類別
├── templates/
│   └── checkout/
│       ├── form-checkout.php           # 結帳表單佈局
│       ├── review-order.php            # 訂單明細 [TO MODIFY]
│       └── shipping-cards.php          # 物流卡片模板
├── README.md                           # 本檔案
├── DEVELOPMENT.md                      # 開發原則與紀錄
└── yangsheep-checkout-optimization.php # 主外掛檔案
```

## 技術注意事項

- 遵循 WooCommerce 模板覆寫規範
- 使用 `woocommerce_update_order_review_fragments` filter 確保 AJAX 更新
- 保留標準 Action Hooks 以相容第三方外掛
- 維持 `shipping_method[...]` input name 結構

## 開發者

羊羊數位科技有限公司
https://yangsheep.art
