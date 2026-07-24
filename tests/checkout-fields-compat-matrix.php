<?php

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
}

namespace YangSheep\CheckoutOptimizer\Settings {
	final class YSSettingsManager {
		public static array $values = array();

		public static function get( string $key, $default = null ) {
			return self::$values[ $key ] ?? $default;
		}
	}
}

namespace {
	use YangSheep\CheckoutOptimizer\Checkout\YSCheckoutFields;
	use YangSheep\CheckoutOptimizer\Settings\YSSettingsManager;

	function __( string $text, string $domain = '' ): string {
		return $text;
	}

	function _x( string $text, string $context, string $domain = '' ): string {
		return $text;
	}

	function wc_clean( $value ) {
		return $value;
	}

	function wp_unslash( $value ) {
		return $value;
	}

	final class FakeCustomer {
		public function get_shipping_country(): string {
			return 'TW';
		}
	}

	final class FakeCountries {
		public function get_base_country(): string {
			return 'TW';
		}
	}

	final class FakeSession {
		public array $chosen = array();

		public function get( string $key, $default = array() ) {
			return 'chosen_shipping_methods' === $key ? $this->chosen : $default;
		}
	}

	final class FakeWooCommerce {
		public FakeCustomer $customer;
		public FakeCountries $countries;
		public FakeSession $session;

		public function __construct() {
			$this->customer  = new FakeCustomer();
			$this->countries = new FakeCountries();
			$this->session   = new FakeSession();
		}
	}

	$GLOBALS['ys_fake_wc'] = new FakeWooCommerce();

	function WC(): FakeWooCommerce {
		return $GLOBALS['ys_fake_wc'];
	}

	require_once dirname( __DIR__ ) . '/src/Checkout/YSCheckoutFields.php';

	$total  = 0;
	$failed = 0;

	function check( bool $condition, string $message ): void {
		global $total, $failed;
		++$total;
		if ( $condition ) {
			echo "PASS {$message}\n";
			return;
		}
		++$failed;
		echo "FAIL {$message}\n";
	}

	function fixture_fields(): array {
		$field = static fn( bool $required = false ): array => array(
			'required' => $required,
			'class'    => array( 'form-row-wide' ),
		);

		return array(
			'billing'  => array(
				'billing_first_name' => $field( true ),
				'billing_last_name'  => $field( true ),
				'billing_phone'      => $field( true ),
				'billing_email'      => $field( true ),
				'billing_company'    => $field(),
				'billing_country'    => $field( true ),
				'billing_postcode'   => $field( true ),
				'billing_state'      => $field( true ),
				'billing_city'       => $field( true ),
				'billing_address_1'  => $field( true ),
				'billing_address_2'  => $field(),
				'billing_tax_id'     => $field( true ),
			),
			'shipping' => array(
				'shipping_first_name' => $field( true ),
				'shipping_last_name'  => $field( true ),
				'shipping_phone'      => $field( true ),
				'shipping_company'    => $field(),
				'shipping_country'    => $field(),
				'shipping_postcode'   => $field( true ),
				'shipping_state'      => $field( true ),
				'shipping_city'       => $field( true ),
				'shipping_address_1'  => $field( true ),
				'shipping_address_2'  => $field(),
				'shipping_store_id'   => $field( true ),
			),
			'order'    => array(),
		);
	}

	$reflection = new \ReflectionClass( YSCheckoutFields::class );
	$subject    = $reflection->newInstanceWithoutConstructor();

	if ( ! method_exists( $subject, 'enforce_checkout_field_compatibility' ) ) {
		check( false, 'checkout field compatibility method exists' );
		echo "SUMMARY total={$total} failed={$failed}\n";
		exit( 1 );
	}

	YSSettingsManager::$values = array(
		'yangsheep_checkout_field_compatibility' => 'no',
		'yangsheep_checkout_tw_fields'           => 'yes',
		'yangsheep_checkout_close_lname'         => 'yes',
		'yangsheep_cvs_shipping_methods'         => array( 'ry_ecpay_shipping_cvs_711:8' ),
	);
	WC()->session->chosen       = array( 'ry_ecpay_shipping_cvs_711:8' );

	$unchanged = $subject->enforce_checkout_field_compatibility( fixture_fields() );
	check(
		isset( $unchanged['billing']['billing_address_1'] ),
		'compatibility mode is opt-in and leaves field-editor output untouched when disabled'
	);

	YSSettingsManager::$values['yangsheep_checkout_field_compatibility'] = 'yes';
	$forced = $subject->enforce_checkout_field_compatibility( fixture_fields() );

	check(
		array_keys( $forced['billing'] ) === array(
			'billing_first_name',
			'billing_phone',
			'billing_email',
			'billing_tax_id',
		),
		'forced Taiwan mode removes only core billing address fields and preserves custom billing fields'
	);
	check(
		! isset( $forced['shipping']['shipping_last_name'] )
		&& ! isset( $forced['shipping']['shipping_company'] )
		&& ! isset( $forced['shipping']['shipping_address_2'] )
		&& isset( $forced['shipping']['shipping_store_id'] ),
		'forced mode reapplies YS shipping rules without deleting provider fields'
	);
	check(
		false === $forced['shipping']['shipping_postcode']['required']
		&& false === $forced['shipping']['shipping_state']['required']
		&& false === $forced['shipping']['shipping_city']['required']
		&& false === $forced['shipping']['shipping_address_1']['required'],
		'forced mode reapplies CVS optional-address requirements after field editors'
	);

	WC()->session->chosen = array( 'flat_rate:1' );
	$delivery             = $subject->enforce_checkout_field_compatibility( fixture_fields() );
	check(
		true === $delivery['shipping']['shipping_address_1']['required'],
		'forced mode keeps delivery addresses required for non-CVS shipping'
	);

	echo "SUMMARY total={$total} failed={$failed}\n";
	exit( $failed > 0 ? 1 : 0 );
}
