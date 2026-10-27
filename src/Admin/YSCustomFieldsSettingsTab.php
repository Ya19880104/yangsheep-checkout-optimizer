<?php
/**
 * 後台「自訂欄位」分頁：編輯結帳自訂欄位與同意事項清單。
 *
 * 只負責輸出編輯介面；儲存走 YSCheckoutSettings::handle_settings_save()，
 * 驗證規則在 YSCustomFieldDefinitions。
 *
 * @package YangSheep\CheckoutOptimizer\Admin
 */

namespace YangSheep\CheckoutOptimizer\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\CustomFields\YSCustomFieldDefinitions;

class YSCustomFieldsSettingsTab {

    /** 表單有輸出這個分頁時才帶的標記；沒有標記就保留原設定（避免誤清空）。 */
    public const SUBMITTED_MARKER = 'yangsheep_checkout_custom_fields_submitted';

    /** 新增列範本中的 ID 佔位字，前端腳本換成新的欄位 ID。 */
    private const ROW_ID_PLACEHOLDER = '__YS_CF_ID__';

    /** @var self|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'yangsheep-admin-custom-fields',
            YANGSHEEP_CHECKOUT_URL . 'assets/css/yangsheep-admin-custom-fields.css',
            array(),
            yangsheep_checkout_asset_version( 'assets/css/yangsheep-admin-custom-fields.css' )
        );
        wp_enqueue_script(
            'yangsheep-admin-custom-fields',
            YANGSHEEP_CHECKOUT_URL . 'assets/js/yangsheep-admin-custom-fields.js',
            array( 'jquery' ),
            yangsheep_checkout_asset_version( 'assets/js/yangsheep-admin-custom-fields.js' ),
            true
        );
        wp_localize_script(
            'yangsheep-admin-custom-fields',
            'ysCustomFieldsAdmin',
            array(
                'placeholder' => self::ROW_ID_PLACEHOLDER,
                'maxFields'   => YSCustomFieldDefinitions::MAX_FIELDS,
                'typeLabels'  => YSCustomFieldDefinitions::type_labels(),
                'i18n'        => array(
                    'confirmRemove' => __( '確定刪除這個欄位？已成立訂單上的紀錄不受影響。', 'yangsheep-checkout-optimization' ),
                    'untitled'      => __( '未命名欄位', 'yangsheep-checkout-optimization' ),
                    'maxReached'    => __( '最多可設定 20 個欄位。', 'yangsheep-checkout-optimization' ),
                ),
            )
        );
    }

    public function render_section_header(): void {
        echo '<div class="ys-section-card"><h3 class="ys-section-title"><span class="dashicons dashicons-feedback"></span> ' . esc_html__( '結帳自訂欄位與同意事項', 'yangsheep-checkout-optimization' ) . '</h3>';
        echo '<p class="ys-section-desc ys-cf-admin-intro">'
            . esc_html__( '在結帳頁加入顧客要填寫的文字欄位，或要勾選的同意事項（例如購買須知）。同意事項可設為必填，未勾選時無法下單；顧客填寫的內容會記錄在訂單上，並顯示在後台訂單頁、訂單 Email 與顧客的訂單明細。', 'yangsheep-checkout-optimization' )
            . '</p>';
    }

    public function render_field(): void {
        $fields = YSCustomFieldDefinitions::get_fields();
        ?>
        <div class="ys-cf-admin" data-ys-cf-admin>
            <input type="hidden" name="<?php echo esc_attr( self::SUBMITTED_MARKER ); ?>" value="1" />
            <p class="ys-cf-admin-help">
                <?php esc_html_e( '「欄位文字」可用 {popup} 標示彈出視窗連結的位置，例如「我已閱讀並同意{popup}」；沒有 {popup} 時連結接在文字最後。彈出視窗內容可直接輸入文字（空一行即分段），也可使用基本 HTML。', 'yangsheep-checkout-optimization' ); ?>
            </p>
            <div class="ys-cf-admin-list" data-ys-cf-list>
                <?php
                foreach ( $fields as $field ) {
                    $this->render_row( $field );
                }
                ?>
            </div>
            <p class="ys-cf-admin-empty" data-ys-cf-empty<?php echo $fields ? ' hidden' : ''; ?>>
                <?php esc_html_e( '目前沒有自訂欄位。', 'yangsheep-checkout-optimization' ); ?>
            </p>
            <p>
                <button type="button" class="button button-secondary ys-cf-admin-add" data-ys-cf-add>
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( '新增欄位', 'yangsheep-checkout-optimization' ); ?>
                </button>
            </p>
            <template data-ys-cf-template>
                <?php
                $this->render_row(
                    array(
                        'id'            => self::ROW_ID_PLACEHOLDER,
                        'type'          => 'checkbox',
                        'label'         => '',
                        'popup_link'    => '',
                        'popup_content' => '',
                        'placeholder'   => '',
                        'position'      => 'before_submit',
                        'required'      => 'yes',
                        'enabled'       => 'yes',
                    )
                );
                ?>
            </template>
        </div>
        <?php
    }

    /**
     * @param array<string,string> $field
     */
    private function render_row( array $field ): void {
        $base        = YSCustomFieldDefinitions::SETTING_KEY . '[' . $field['id'] . ']';
        $dom_id      = 'ys-cf-admin-' . $field['id'];
        $type_labels = YSCustomFieldDefinitions::type_labels();
        $title       = '' !== $field['label']
            ? YSCustomFieldDefinitions::plain_label( $field )
            : __( '未命名欄位', 'yangsheep-checkout-optimization' );
        ?>
        <div class="ys-cf-admin-row<?php echo 'yes' === $field['enabled'] ? '' : ' is-disabled'; ?>" data-ys-cf-row data-type="<?php echo esc_attr( $field['type'] ); ?>">
            <input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( $field['id'] ); ?>" />
            <div class="ys-cf-admin-row__head">
                <strong class="ys-cf-admin-row__title" data-ys-cf-title><?php echo esc_html( $title ); ?></strong>
                <span class="ys-cf-admin-badge" data-ys-cf-badge><?php echo esc_html( $type_labels[ $field['type'] ] ?? '' ); ?></span>
                <span class="ys-cf-admin-row__actions">
                    <button type="button" class="button-link" data-ys-cf-move="up"><?php esc_html_e( '上移', 'yangsheep-checkout-optimization' ); ?></button>
                    <button type="button" class="button-link" data-ys-cf-move="down"><?php esc_html_e( '下移', 'yangsheep-checkout-optimization' ); ?></button>
                    <button type="button" class="button-link ys-cf-admin-remove" data-ys-cf-remove><?php esc_html_e( '刪除', 'yangsheep-checkout-optimization' ); ?></button>
                </span>
            </div>
            <div class="ys-cf-admin-row__grid">
                <label class="ys-cf-admin-control">
                    <span><?php esc_html_e( '類型', 'yangsheep-checkout-optimization' ); ?></span>
                    <select name="<?php echo esc_attr( $base . '[type]' ); ?>" data-ys-cf-type>
                        <?php foreach ( $type_labels as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"<?php selected( $field['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ys-cf-admin-control">
                    <span><?php esc_html_e( '顯示位置', 'yangsheep-checkout-optimization' ); ?></span>
                    <select name="<?php echo esc_attr( $base . '[position]' ); ?>">
                        <?php foreach ( YSCustomFieldDefinitions::position_labels() as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"<?php selected( $field['position'], $value ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="ys-cf-admin-control ys-cf-admin-control--wide">
                    <span><?php esc_html_e( '欄位文字', 'yangsheep-checkout-optimization' ); ?></span>
                    <input type="text" class="large-text" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $field['label'] ); ?>" maxlength="<?php echo (int) YSCustomFieldDefinitions::MAX_LABEL; ?>" placeholder="<?php esc_attr_e( '例如：我已閱讀並同意{popup}', 'yangsheep-checkout-optimization' ); ?>" data-ys-cf-label />
                </label>
                <label class="ys-cf-admin-control">
                    <span><?php esc_html_e( '彈出視窗連結文字', 'yangsheep-checkout-optimization' ); ?></span>
                    <input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[popup_link]' ); ?>" value="<?php echo esc_attr( $field['popup_link'] ); ?>" maxlength="<?php echo (int) YSCustomFieldDefinitions::MAX_POPUP_LINK; ?>" placeholder="<?php esc_attr_e( '例如：購買須知', 'yangsheep-checkout-optimization' ); ?>" data-ys-cf-link />
                </label>
                <label class="ys-cf-admin-control ys-cf-admin-placeholder">
                    <span><?php esc_html_e( '輸入提示（文字欄位）', 'yangsheep-checkout-optimization' ); ?></span>
                    <input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[placeholder]' ); ?>" value="<?php echo esc_attr( $field['placeholder'] ); ?>" maxlength="<?php echo (int) YSCustomFieldDefinitions::MAX_PLACEHOLDER; ?>" />
                </label>
                <label class="ys-cf-admin-control ys-cf-admin-control--wide" for="<?php echo esc_attr( $dom_id . '-content' ); ?>">
                    <span><?php esc_html_e( '彈出視窗內容', 'yangsheep-checkout-optimization' ); ?></span>
                    <textarea class="large-text" id="<?php echo esc_attr( $dom_id . '-content' ); ?>" name="<?php echo esc_attr( $base . '[popup_content]' ); ?>" rows="5"><?php echo esc_textarea( $field['popup_content'] ); ?></textarea>
                </label>
                <span class="ys-cf-admin-flags">
                    <label><input type="checkbox" name="<?php echo esc_attr( $base . '[required]' ); ?>" value="yes"<?php checked( $field['required'], 'yes' ); ?> /> <?php esc_html_e( '必填', 'yangsheep-checkout-optimization' ); ?></label>
                    <label><input type="checkbox" name="<?php echo esc_attr( $base . '[enabled]' ); ?>" value="yes"<?php checked( $field['enabled'], 'yes' ); ?> data-ys-cf-enabled /> <?php esc_html_e( '啟用', 'yangsheep-checkout-optimization' ); ?></label>
                </span>
            </div>
        </div>
        <?php
    }
}
