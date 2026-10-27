<?php
/**
 * 顯示訂單上記錄的結帳自訂欄位：後台訂單頁、訂單 Email、顧客的訂單明細。
 *
 * 讀取的是下單當時的欄位文字與值（YSCustomFieldsCheckout 寫入），
 * 之後修改或刪除欄位設定不影響已成立的訂單。
 *
 * @package YangSheep\CheckoutOptimizer\CustomFields
 */

namespace YangSheep\CheckoutOptimizer\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class YSCustomFieldsOrderDisplay {

    /** @var self|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render_admin' ) );
        add_action( 'woocommerce_email_order_meta', array( $this, 'render_email' ), 20, 3 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer' ) );
    }

    /**
     * 訂單上的紀錄（格式不符的項目略過）。
     *
     * @param mixed $order
     * @return array<int,array{label:string,value:string,type:string}>
     */
    public static function get_entries( $order ): array {
        if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_meta' ) ) ) {
            return array();
        }
        $stored = $order->get_meta( YSCustomFieldsCheckout::ORDER_META_KEY, true );
        if ( ! is_array( $stored ) ) {
            return array();
        }

        $entries = array();
        foreach ( $stored as $entry ) {
            if (
                ! is_array( $entry )
                || ! isset( $entry['label'], $entry['value'], $entry['type'] )
                || ! is_string( $entry['label'] )
                || ! is_string( $entry['value'] )
                || ! in_array( $entry['type'], YSCustomFieldDefinitions::TYPES, true )
            ) {
                continue;
            }
            $entries[] = array(
                'label' => $entry['label'],
                'value' => $entry['value'],
                'type'  => $entry['type'],
            );
        }

        return $entries;
    }

    /**
     * @param array{label:string,value:string,type:string} $entry
     */
    private static function value_text( array $entry ): string {
        if ( 'checkbox' === $entry['type'] ) {
            return 'yes' === $entry['value']
                ? __( '已勾選', 'yangsheep-checkout-optimization' )
                : __( '未勾選', 'yangsheep-checkout-optimization' );
        }

        return $entry['value'];
    }

    /**
     * @param array{label:string,value:string,type:string} $entry
     */
    private static function value_html( array $entry ): string {
        $text = esc_html( self::value_text( $entry ) );

        return 'textarea' === $entry['type'] ? nl2br( $text ) : $text;
    }

    /**
     * @param \WC_Order $order
     */
    public function render_admin( $order ): void {
        $entries = self::get_entries( $order );
        if ( ! $entries ) {
            return;
        }
        echo '<div class="form-field form-field-wide ys-cf-order-fields">';
        echo '<h3>' . esc_html__( '結帳自訂欄位', 'yangsheep-checkout-optimization' ) . '</h3>';
        foreach ( $entries as $entry ) {
            echo '<p><strong>' . esc_html( $entry['label'] ) . '</strong><br>' . self::value_html( $entry ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in value_html().
        }
        echo '</div>';
    }

    /**
     * @param \WC_Order $order
     * @param bool      $sent_to_admin
     * @param bool      $plain_text
     */
    public function render_email( $order, $sent_to_admin = false, $plain_text = false ): void {
        $entries = self::get_entries( $order );
        if ( ! $entries ) {
            return;
        }
        $title = $sent_to_admin
            ? __( '結帳自訂欄位', 'yangsheep-checkout-optimization' )
            : __( '其他資訊', 'yangsheep-checkout-optimization' );

        if ( $plain_text ) {
            echo "\n" . esc_html( wp_strip_all_tags( $title ) ) . "\n";
            foreach ( $entries as $entry ) {
                echo esc_html( wp_strip_all_tags( $entry['label'] ) ) . '：' . esc_html( wp_strip_all_tags( self::value_text( $entry ) ) ) . "\n";
            }
            return;
        }

        echo '<h2>' . esc_html( $title ) . '</h2><ul class="ys-cf-email-fields">';
        foreach ( $entries as $entry ) {
            echo '<li><strong>' . esc_html( $entry['label'] ) . '：</strong>' . self::value_html( $entry ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in value_html().
        }
        echo '</ul>';
    }

    /**
     * @param \WC_Order $order
     */
    public function render_customer( $order ): void {
        $entries = self::get_entries( $order );
        if ( ! $entries ) {
            return;
        }
        ?>
        <section class="woocommerce-ys-custom-fields ys-cf-customer-fields">
            <h2 class="woocommerce-column__title"><?php esc_html_e( '其他資訊', 'yangsheep-checkout-optimization' ); ?></h2>
            <table class="woocommerce-table shop_table ys-cf-customer-table">
                <tbody>
                    <?php foreach ( $entries as $entry ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $entry['label'] ); ?></th>
                            <td><?php echo self::value_html( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in value_html(). ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php
    }
}
