<?php
namespace StockAdaptixPricing\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Settings Module
 * Handles the admin settings page for the plugin
 */
class AdminSettingsModule {

	const OPTIONS_KEY = 'stockadaptix_pricing_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'StockAdaptix Pricing', 'stockadaptix-pricing' ),
			__( 'Stock Pricing', 'stockadaptix-pricing' ),
			'manage_woocommerce',
			'stockadaptix-pricing',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			self::OPTIONS_KEY,
			self::OPTIONS_KEY,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'stockadaptix_pricing_main',
			__( 'Pricing Configuration', 'stockadaptix-pricing' ),
			array( $this, 'section_callback' ),
			'stockadaptix_pricing'
		);

		// Enable/Disable plugin
		add_settings_field(
			'enable_plugin',
			__( 'Enable Dynamic Pricing', 'stockadaptix-pricing' ),
			array( $this, 'checkbox_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'   => 'enable_plugin',
				'label' => __( 'Check this box to enable dynamic stock-based pricing', 'stockadaptix-pricing' ),
			)
		);

		// Low stock threshold (<= 5)
		add_settings_field(
			'low_stock_threshold',
			__( 'Low Stock Threshold', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'low_stock_threshold',
				'label'       => __( 'Stock level considered low (default: 5)', 'stockadaptix-pricing' ),
				'type'        => 'number',
				'placeholder' => '5',
			)
		);

		// Low stock price increase
		add_settings_field(
			'low_stock_price_increase',
			__( 'Low Stock Price Increase', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'low_stock_price_increase',
				'label'       => __( 'Price increase percentage when stock is low (default: 40%)', 'stockadaptix-pricing' ),
				'type'        => 'number',
				'placeholder' => '40',
				'suffix'      => '%',
			)
		);

		// Medium stock threshold (<= 20)
		add_settings_field(
			'medium_stock_threshold',
			__( 'Medium Stock Threshold', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'medium_stock_threshold',
				'label'       => __( 'Stock level considered medium (default: 20)', 'stockadaptix-pricing' ),
				'type'        => 'number',
				'placeholder' => '20',
			)
		);

		// Medium stock price increase
		add_settings_field(
			'medium_stock_price_increase',
			__( 'Medium Stock Price Increase', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'medium_stock_price_increase',
				'label'       => __( 'Price increase percentage when stock is medium (default: 20%)', 'stockadaptix-pricing' ),
				'type'        => 'number',
				'placeholder' => '20',
				'suffix'      => '%',
			)
		);

		// High stock threshold (>= 100)
		add_settings_field(
			'high_stock_threshold',
			__( 'High Stock Threshold', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'high_stock_threshold',
				'label'       => __( 'Stock level considered high (default: 100)', 'stockadaptix-pricing' ),
				'type'        => 'number',
				'placeholder' => '100',
			)
		);

		// High stock price decrease
		add_settings_field(
			'high_stock_price_decrease',
			__( 'High Stock Price Decrease', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'high_stock_price_decrease',
				'label'       => __( 'Price decrease percentage when stock is high (default: 15%)', 'stockadaptix-pricing' ),
				'type'        => 'number',
				'placeholder' => '15',
				'suffix'      => '%',
			)
		);

		// Customer message option
		add_settings_field(
			'customer_message_enabled',
			__( 'Enable Customer Message', 'stockadaptix-pricing' ),
			array( $this, 'checkbox_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'   => 'customer_message_enabled',
				'label' => __( 'Display a message to customers about price adjustments', 'stockadaptix-pricing' ),
			)
		);

		// Customer message text
		add_settings_field(
			'customer_message',
			__( 'Customer Message', 'stockadaptix-pricing' ),
			array( $this, 'input_field_callback' ),
			'stockadaptix_pricing',
			'stockadaptix_pricing_main',
			array(
				'key'         => 'customer_message',
				'label'       => __( 'Message shown to customers when prices are adjusted (default: "High demand – price adjusted based on availability")', 'stockadaptix-pricing' ),
				'type'        => 'text',
				'placeholder' => __( 'High demand – price adjusted based on availability', 'stockadaptix-pricing' ),
			)
		);
	}

	/**
	 * Section callback
	 */
	public function section_callback() {
		echo '<p>' . esc_html__( 'Configure how stock levels affect product pricing.', 'stockadaptix-pricing' ) . '</p>';
	}

	/**
	 * Input field callback
	 *
	 * @param array $args Arguments for the field.
	 */
	public function input_field_callback( $args ) {
		$options = get_option( self::OPTIONS_KEY );
		$value   = isset( $options[ $args['key'] ] ) ? $options[ $args['key'] ] : '';

		$type        = ! empty( $args['type'] ) ? $args['type'] : 'text';
		$placeholder = ! empty( $args['placeholder'] ) ? $args['placeholder'] : '';
		$suffix      = ! empty( $args['suffix'] ) ? ' ' . $args['suffix'] : '';

		printf(
			'<input type="%s" id="%s" name="%s[%s]" value="%s" placeholder="%s" class="regular-text" />%s',
			esc_attr( $type ),
			esc_attr( $args['key'] ),
			esc_attr( self::OPTIONS_KEY ),
			esc_attr( $args['key'] ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			esc_html( $suffix )
		);

		if ( ! empty( $args['label'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['label'] ) );
		}
	}

	/**
	 * Checkbox field callback
	 *
	 * @param array $args Arguments for the field.
	 */
	public function checkbox_field_callback( $args ) {
		$options = get_option( self::OPTIONS_KEY );
		$checked = isset( $options[ $args['key'] ] ) ? checked( $options[ $args['key'] ], 1, false ) : '';

		printf(
			'<input type="checkbox" id="%s" name="%s[%s]" value="1" %s />',
			esc_attr( $args['key'] ),
			esc_attr( self::OPTIONS_KEY ),
			esc_attr( $args['key'] ),
			esc_html( $checked )
		);

		if ( ! empty( $args['label'] ) ) {
			printf( '<label for="%s"> %s</label>', esc_attr( $args['key'] ), esc_html( $args['label'] ) );
		}
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		// Verify nonce
		if ( ! isset( $_POST['stockadaptix_pricing_settings_nonce'] ) ||
			 ! wp_verify_nonce( $_POST['stockadaptix_pricing_settings_nonce'], 'stockadaptix_pricing_settings_action' ) ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return array(); // Return empty array if user doesn't have proper capabilities
			}
			add_settings_error(
				self::OPTIONS_KEY,
				'nonce_verification_failed',
				__( 'Security verification failed. Please try saving your settings again.', 'stockadaptix-pricing' ),
				'error'
			);
			return get_option( self::OPTIONS_KEY, array() ); // Return current settings to prevent data loss
		}

		$sanitized_input = array();

		// Enable plugin
		$sanitized_input['enable_plugin'] = ! empty( $input['enable_plugin'] ) ? 1 : 0;

		// Validate numeric fields with bounds checking
		$numeric_fields = array(
			'low_stock_threshold'        => array( 'value' => 5, 'min' => 0, 'max' => 10000 ),
			'low_stock_price_increase'   => array( 'value' => 40, 'min' => 0, 'max' => 1000 ),
			'medium_stock_threshold'     => array( 'value' => 20, 'min' => 0, 'max' => 10000 ),
			'medium_stock_price_increase' => array( 'value' => 20, 'min' => 0, 'max' => 1000 ),
			'high_stock_threshold'       => array( 'value' => 100, 'min' => 0, 'max' => 10000 ),
			'high_stock_price_decrease'  => array( 'value' => 15, 'min' => 0, 'max' => 100 ),
		);

		foreach ( $numeric_fields as $field => $config ) {
			if ( isset( $input[ $field ] ) ) {
				$val = intval( $input[ $field ] );
				// Ensure value is within acceptable bounds
				$val = max( $config['min'], min( $config['max'], $val ) );
				$sanitized_input[ $field ] = $val;
			} else {
				$sanitized_input[ $field ] = $config['value'];
			}
		}

		// Sanitize customer message
		$sanitized_input['customer_message_enabled'] = ! empty( $input['customer_message_enabled'] ) ? 1 : 0;
		$sanitized_input['customer_message']         = ! empty( $input['customer_message'] ) ? sanitize_text_field( $input['customer_message'] ) : __( 'High demand – price adjusted based on availability', 'stockadaptix-pricing' );

		return $sanitized_input;
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'StockAdaptix Pricing for WooCommerce', 'stockadaptix-pricing' ); ?></h1>

			<form method="post" action="options.php">
				<?php
					settings_fields( self::OPTIONS_KEY );
					wp_nonce_field( 'stockadaptix_pricing_settings_action', 'stockadaptix_pricing_settings_nonce' );
					do_settings_sections( 'stockadaptix_pricing' );
					submit_button();
				?>
			</form>

			<div class="card" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'How It Works', 'stockadaptix-pricing' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'If stock ≤ Low Stock Threshold: increase price by Low Stock Price Increase %', 'stockadaptix-pricing' ); ?></li>
					<li><?php esc_html_e( 'If stock ≤ Medium Stock Threshold: increase price by Medium Stock Price Increase %', 'stockadaptix-pricing' ); ?></li>
					<li><?php esc_html_e( 'If stock ≥ High Stock Threshold: decrease price by High Stock Price Decrease %', 'stockadaptix-pricing' ); ?></li>
					<li><?php esc_html_e( 'Otherwise: use normal price', 'stockadaptix-pricing' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_stockadaptix-pricing' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'stockadaptix-pricing-admin',
			STOCKADAPTIX_PRICING_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			STOCKADAPTIX_PRICING_VERSION
		);
	}
}