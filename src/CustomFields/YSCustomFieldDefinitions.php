<?php
/**
 * 結帳自訂欄位與同意事項的定義：型別、位置、上限與正規化規則。
 *
 * 後台儲存、設定匯入與前台輸出共用同一份規則；清單中任何一筆不合規則
 * 就整份視為無效（與設定匯入匯出的 fail-closed 驗證一致），不會寫入
 * 半套資料。
 *
 * @package YangSheep\CheckoutOptimizer\CustomFields
 */

namespace YangSheep\CheckoutOptimizer\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

class YSCustomFieldDefinitions {

    public const SETTING_KEY = 'yangsheep_checkout_custom_fields';

    public const MAX_FIELDS        = 20;
    public const MAX_LABEL         = 200;
    public const MAX_POPUP_LINK    = 60;
    public const MAX_PLACEHOLDER   = 120;
    public const MAX_POPUP_CONTENT = 20000;
    public const MAX_TEXT_VALUE     = 200;
    public const MAX_TEXTAREA_VALUE = 2000;

    /** 欄位文字中標示彈出視窗連結位置的記號；沒有記號時連結接在文字最後。 */
    public const POPUP_TOKEN = '{popup}';

    public const TYPES = array( 'checkbox', 'text', 'textarea' );

    public const POSITIONS = array( 'before_submit', 'after_billing', 'after_shipping', 'before_notes', 'after_notes' );

    /** 正規化後每筆欄位的鍵（依此順序輸出，匯出檔與比對皆穩定）。 */
    public const FIELD_KEYS = array(
        'id',
        'type',
        'label',
        'popup_link',
        'popup_content',
        'placeholder',
        'position',
        'required',
        'enabled',
    );

    /**
     * @return array<string,string>
     */
    public static function type_labels(): array {
        return array(
            'checkbox' => __( '勾選框（同意事項）', 'yangsheep-checkout-optimization' ),
            'text'     => __( '單行文字', 'yangsheep-checkout-optimization' ),
            'textarea' => __( '多行文字', 'yangsheep-checkout-optimization' ),
        );
    }

    /**
     * @return array<string,string>
     */
    public static function position_labels(): array {
        return array(
            'before_submit'  => __( '下單按鈕上方（付款區）', 'yangsheep-checkout-optimization' ),
            'after_billing'  => __( '帳單資訊下方', 'yangsheep-checkout-optimization' ),
            'after_shipping' => __( '收件資訊下方', 'yangsheep-checkout-optimization' ),
            'before_notes'   => __( '訂單備註上方', 'yangsheep-checkout-optimization' ),
            'after_notes'    => __( '訂單備註下方', 'yangsheep-checkout-optimization' ),
        );
    }

    /**
     * 讀取已儲存的欄位清單；資料無效時視為沒有任何欄位（fail-closed）。
     *
     * @return array<int,array<string,string>>
     */
    public static function get_fields(): array {
        $stored     = YSSettingsManager::get( 'yangsheep_checkout_custom_fields', array() );
        $normalized = self::normalize_list( $stored );

        return $normalized['valid'] ? $normalized['value'] : array();
    }

    /**
     * @return array<int,array<string,string>>
     */
    public static function get_enabled_fields(): array {
        return array_values(
            array_filter(
                self::get_fields(),
                static function ( array $field ): bool {
                    return 'yes' === $field['enabled'];
                }
            )
        );
    }

    /**
     * 前台表單欄位名稱（同時作為 HTML id 前綴）。
     */
    public static function input_name( string $id ): string {
        return 'ys_' . $id;
    }

    /**
     * 純文字版欄位名稱：彈出視窗記號換成連結文字，用於錯誤訊息與訂單紀錄。
     *
     * @param array<string,string> $field
     */
    public static function plain_label( array $field ): string {
        $link  = $field['popup_link'] ?? '';
        $label = $field['label'] ?? '';
        if ( false !== strpos( $label, self::POPUP_TOKEN ) ) {
            return trim( str_replace( self::POPUP_TOKEN, $link, $label ) );
        }

        return trim( $label . $link );
    }

    /**
     * 正規化整份清單。
     *
     * @param mixed $value
     * @return array{valid:bool,value:?array}
     */
    public static function normalize_list( $value ): array {
        if ( ! is_array( $value ) || count( $value ) > self::MAX_FIELDS ) {
            return array( 'valid' => false, 'value' => null );
        }

        $fields = array();
        $ids    = array();
        foreach ( $value as $item ) {
            $field = self::normalize_field( $item );
            if ( null === $field || isset( $ids[ $field['id'] ] ) ) {
                return array( 'valid' => false, 'value' => null );
            }
            $ids[ $field['id'] ] = true;
            $fields[]            = $field;
        }

        return array( 'valid' => true, 'value' => $fields );
    }

    /**
     * 正規化單筆欄位；不合規則回傳 null。
     *
     * 勾選框與「必填／啟用」未送出時視為 no（後台表單未勾選的 checkbox
     * 不會出現在 POST）；其餘未知鍵一律拒絕。
     *
     * @param mixed $item
     * @return array<string,string>|null
     */
    public static function normalize_field( $item ): ?array {
        if ( ! is_array( $item ) || array_diff( array_keys( $item ), self::FIELD_KEYS ) ) {
            return null;
        }

        $id = $item['id'] ?? '';
        if ( ! is_string( $id ) ) {
            return null;
        }
        $id = trim( $id );
        if ( '' === $id ) {
            $id = self::generate_id();
        }
        if ( 1 !== preg_match( '/^cf_[a-z0-9]{4,24}$/', $id ) ) {
            return null;
        }

        $type     = $item['type'] ?? '';
        $position = $item['position'] ?? '';
        if ( ! in_array( $type, self::TYPES, true ) || ! in_array( $position, self::POSITIONS, true ) ) {
            return null;
        }

        $label = self::plain_text( $item['label'] ?? '', self::MAX_LABEL );
        if ( null === $label || '' === $label || substr_count( $label, self::POPUP_TOKEN ) > 1 ) {
            return null;
        }

        $popup_link  = self::plain_text( $item['popup_link'] ?? '', self::MAX_POPUP_LINK );
        $placeholder = self::plain_text( $item['placeholder'] ?? '', self::MAX_PLACEHOLDER );
        if ( null === $popup_link || null === $placeholder ) {
            return null;
        }

        $popup_content = $item['popup_content'] ?? '';
        if ( ! is_string( $popup_content ) ) {
            return null;
        }
        $popup_content = trim( self::kses( $popup_content ) );
        if ( self::length( $popup_content ) > self::MAX_POPUP_CONTENT ) {
            return null;
        }

        $required = self::flag( $item['required'] ?? 'no' );
        $enabled  = self::flag( $item['enabled'] ?? 'no' );
        if ( null === $required || null === $enabled ) {
            return null;
        }

        return array(
            'id'            => $id,
            'type'          => $type,
            'label'         => $label,
            'popup_link'    => $popup_link,
            'popup_content' => $popup_content,
            'placeholder'   => 'checkbox' === $type ? '' : $placeholder,
            'position'      => $position,
            'required'      => $required,
            'enabled'       => $enabled,
        );
    }

    /**
     * 是否有可開啟的彈出視窗（連結文字與內容都要有）。
     *
     * @param array<string,string> $field
     */
    public static function has_popup( array $field ): bool {
        return '' !== ( $field['popup_link'] ?? '' ) && '' !== trim( $field['popup_content'] ?? '' );
    }

    /**
     * @param mixed $value
     */
    private static function plain_text( $value, int $max ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );
        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
        if ( ! is_string( $text ) ) {
            return null;
        }
        $text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );

        return self::length( $text ) > $max ? null : $text;
    }

    /**
     * @param mixed $value
     */
    private static function flag( $value ): ?string {
        if ( true === $value || 'yes' === $value ) {
            return 'yes';
        }
        if ( false === $value || 'no' === $value || '' === $value ) {
            return 'no';
        }

        return null;
    }

    private static function kses( string $html ): string {
        return function_exists( 'wp_kses_post' ) ? wp_kses_post( $html ) : strip_tags( $html );
    }

    public static function length( string $value ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
    }

    private static function generate_id(): string {
        try {
            $random = bin2hex( random_bytes( 5 ) );
        } catch ( \Throwable $exception ) {
            $random = substr( md5( uniqid( '', true ) ), 0, 10 );
        }

        return 'cf_' . $random;
    }
}
