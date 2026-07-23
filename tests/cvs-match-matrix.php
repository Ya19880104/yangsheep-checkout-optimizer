<?php
/**
 * 回歸矩陣：後台指定超商物流 rate id 比對（v1.7.2 P1）
 *
 * 直接測真實產品純函式 YSCheckoutFields::method_matches_cvs_list()。
 * 🔴 核心防護：flat_rate:1 不得命中 flat_rate:10（含 ":" 只能完整相等）。
 *
 * 執行：php tests/cvs-match-matrix.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

require_once dirname( __DIR__ ) . '/src/Checkout/YSCheckoutFields.php';

use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutFields;

$failed = 0;
$total  = 0;

/**
 * @param string $method   chosen shipping method
 * @param array  $list     後台設定清單
 * @param bool   $expected 預期是否判為超商
 * @param string $label
 */
function matrix( string $method, array $list, bool $expected, string $label ): void {
    global $failed, $total;
    $total++;
    $actual = YSCheckoutFields::method_matches_cvs_list( $method, $list );
    if ( $actual === $expected ) {
        echo "PASS {$label}\n";
    } else {
        $failed++;
        echo 'FAIL ' . $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ")\n";
    }
}

/**
 * 多包裹聚合：全域地址是否可免必填/隱藏 = 全部 package 都超商。
 *
 * @param array  $selected   各 package 已選 method
 * @param array  $configured 後台設定清單（空=自動偵測）
 * @param bool   $expected
 * @param string $label
 */
function matrix_all( array $selected, array $configured, bool $expected, string $label ): void {
    global $failed, $total;
    $total++;
    $actual = YSCheckoutFields::all_methods_cvs( $selected, $configured );
    if ( $actual === $expected ) {
        echo "PASS {$label}\n";
    } else {
        $failed++;
        echo 'FAIL ' . $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ")\n";
    }
}

// 🔴 核心回歸：完整 rate id 前綴不得誤中
matrix( 'flat_rate:10', array( 'flat_rate:1' ), false, 'flat_rate:1 設定不得命中 flat_rate:10（前綴誤判）' );
matrix( 'flat_rate:1', array( 'flat_rate:1' ), true, 'flat_rate:1 設定命中 flat_rate:1（完整相等）' );
matrix( 'flat_rate:10', array( 'flat_rate:10' ), true, 'flat_rate:10 設定命中 flat_rate:10' );
matrix( 'flat_rate:1', array( 'flat_rate:10' ), false, 'flat_rate:10 設定不得命中 flat_rate:1' );

// 超商實例：同 method_id 不同 instance 不得互中
matrix( 'paynow_shipping_c2c_711:30', array( 'paynow_shipping_c2c_711:3' ), false, 'paynow ...711:3 設定不得命中 ...711:30' );
matrix( 'paynow_shipping_c2c_711:3', array( 'paynow_shipping_c2c_711:3' ), true, 'paynow ...711:3 設定命中同一實例' );

// 舊版 base id（不含 ":"）：允許 method base 相等
matrix( 'flat_rate:10', array( 'flat_rate' ), true, '舊版 base flat_rate 命中 flat_rate:10' );
matrix( 'flat_rate:1', array( 'flat_rate' ), true, '舊版 base flat_rate 命中 flat_rate:1' );
matrix( 'ys_paynow_shipping_711:5', array( 'ys_paynow_shipping_711' ), true, '舊版 base ys_paynow_shipping_711 命中含實例' );
matrix( 'ys_paynow_shipping_family:2', array( 'ys_paynow_shipping_711' ), false, '舊版 base ys_paynow_shipping_711 不得命中 family' );

// 多筆設定：其一命中即可
matrix( 'paynow_shipping_c2c_family:4', array( 'flat_rate:1', 'paynow_shipping_c2c_family:4' ), true, '多筆設定命中其一' );
matrix( 'flat_rate:10', array( 'flat_rate:1', 'paynow_shipping_c2c_family:4' ), false, '多筆設定皆不含選中方式' );

// 邊界
matrix( '', array( 'flat_rate:1' ), false, '空 method 不命中' );
matrix( 'flat_rate:1', array(), false, '空設定清單不命中' );
matrix( 'flat_rate:1', array( '' ), false, '設定清單含空字串不命中' );

// 🔴🔴 多包裹聚合（後台指定）：唯有全部包裹都超商才免地址，順序無關
matrix_all( array( 'flat_rate:1' ), array( 'flat_rate:1' ), true, '單包裹全超商 → true' );
matrix_all( array( 'flat_rate:1', 'payuni_shipping_tcat_frozen:14' ), array( 'flat_rate:1' ), false, '超商+宅配 → false（宅配缺地址風險）' );
matrix_all( array( 'payuni_shipping_tcat_frozen:14', 'flat_rate:1' ), array( 'flat_rate:1' ), false, '宅配+超商（順序相反）→ false' );
matrix_all( array( 'flat_rate:1', 'paynow_shipping_c2c_711:3' ), array( 'flat_rate:1', 'paynow_shipping_c2c_711:3' ), true, '多包裹皆超商 → true' );
matrix_all( array( 'flat_rate:1', 'flat_rate:10' ), array( 'flat_rate:1' ), false, '超商+被前綴誤判者(flat_rate:10)→ false' );
matrix_all( array( 'flat_rate:1', '' ), array( 'flat_rate:1' ), false, '超商+未解析包裹 → false（不得濾掉空 method）' );

// 多包裹聚合（自動偵測，後台清單空）
matrix_all( array( 'payuni_shipping_711_c2c_normal:5' ), array(), true, '自動偵測：單超商 → true' );
matrix_all( array( 'payuni_shipping_711_c2c_normal:5', 'payuni_shipping_tcat_frozen:14' ), array(), false, '自動偵測：超商+黑貓宅配 → false' );
matrix_all( array( 'payuni_shipping_tcat_frozen:14' ), array(), false, '自動偵測：純宅配 → false' );

// 邊界
matrix_all( array(), array( 'flat_rate:1' ), false, '空選擇 → false' );
matrix_all( array( '', '' ), array( 'flat_rate:1' ), false, '全空字串 → false' );

// 自動偵測 allowlist（後台清單空）：每種已知 ID 類型 — is_single_method_cvs($m, [])
function matrix_auto( string $method, bool $expected, string $label ): void {
    global $failed, $total;
    $total++;
    $actual = YSCheckoutFields::is_single_method_cvs( $method, array() );
    if ( $actual === $expected ) {
        echo "PASS auto:{$label}\n";
    } else {
        $failed++;
        echo 'FAIL auto:' . $label . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ")\n";
    }
}
matrix_auto( 'payuni_shipping_711_c2c_normal:5', true, 'PayUni 711 超取' );
matrix_auto( 'payuni_shipping_tcat_frozen:14', false, 'PayUni 黑貓宅配' );
matrix_auto( 'ry_ecpay_shipping_cvs_711:20', true, '綠界 ECPay CVS' );
matrix_auto( 'ys_paynow_shipping_711:1', true, 'YS PayNow 711' );
matrix_auto( 'ys_paynow_shipping_family:2', true, 'YS PayNow 全家' );
matrix_auto( 'ys_paynow_shipping_hilife:3', true, 'YS PayNow 萊爾富' );
matrix_auto( 'ys_paynow_shipping_tcat:4', false, 'YS PayNow 黑貓宅配' );
matrix_auto( 'ys_paynow_shipping_ok:5', false, 'YS PayNow 未知門市(fail-safe→非CVS)' );
matrix_auto( 'paynow_shipping_c2c_711:3', true, '好用版 PayNow C2C 7-11' );
matrix_auto( 'paynow_shipping_c2c_family:4', true, '好用版 PayNow C2C 全家' );
matrix_auto( 'paynow_shipping_c2c_hilife:5', true, '好用版 PayNow C2C 萊爾富' );
matrix_auto( 'woomp_paynow_shipping_c2c_711_frozen:21', true, '好用版 PayNow 冷凍 C2C 7-11' );
matrix_auto( 'woomp_paynow_shipping_c2c_family_frozen:22', true, '好用版 PayNow 冷凍 C2C 全家' );
matrix_auto( 'paynow_shipping_b2c_711:23', false, '好用版 PayNow B2C 不推測為 CVS' );
matrix_auto( 'paynow_shipping_tcat:24', false, '好用版 PayNow 宅配' );
matrix_auto( 'woomp_paynow_shipping_c2c_unknown:25', false, '好用版 PayNow 未知 C2C 類型 fail-safe' );
matrix_auto( 'flat_rate:1', false, '一般宅配 flat_rate' );

echo "SUMMARY total={$total} failed={$failed}\n";
exit( $failed === 0 ? 0 : 1 );
