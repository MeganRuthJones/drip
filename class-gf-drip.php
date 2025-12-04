<?php
/**
 * Gravity Forms Drip Add-On
 *
 * @package GF_Drip
 * @author  Your Name
 * @version 1.0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Only proceed if parent class exists - this check happens in main file before requiring
if ( ! class_exists( 'GFFeedAddOn' ) ) {
	return;
}

/**
 * Main add-on class
 *
 * @since 1.0.0
 */
class GF_Drip extends GFFeedAddOn {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	protected $_version = GF_DRIP_VERSION;

	/**
	 * Minimum Gravity Forms version required
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = GF_DRIP_MIN_GF_VERSION;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	protected $_slug = 'gravityforms-drip';

	/**
	 * Plugin path (relative to plugins folder)
	 * Must match the actual plugin folder name
	 *
	 * @var string
	 */
	protected $_path = 'drip-main/drip-gravity-forms.php';

	/**
	 * Full path to the main plugin file (not this class file)
	 *
	 * @var string
	 */
	protected $_full_path = '';

	/**
	 * Title of the plugin
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms Drip Add-On';

	/**
	 * Short title of the plugin
	 *
	 * @var string
	 */
	protected $_short_title = 'Drip';

	/**
	 * Constructor - ensure properties are set before parent constructor
	 */
	public function __construct() {
		// Set full path to main plugin file if not already set
		if ( empty( $this->_full_path ) && defined( 'GF_DRIP_PLUGIN_FILE' ) ) {
			$this->_full_path = GF_DRIP_PLUGIN_FILE;
		}
		
		// Call parent constructor
		parent::__construct();
	}

	/**
	 * Capabilities required to access plugin settings
	 *
	 * @var string
	 */
	protected $_capabilities_settings_page = 'gravityforms_edit_settings';

	/**
	 * Capabilities required to access plugin form settings
	 *
	 * @var string
	 */
	protected $_capabilities_form_settings = 'gravityforms_edit_forms';

	/**
	 * Capabilities required to uninstall plugin
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'gravityforms_uninstall';

	/**
	 * Permissions required to access plugin
	 *
	 * @var array
	 */
	protected $_capabilities = array( 'gravityforms_edit_forms', 'gravityforms_edit_settings' );

	/**
	 * Singleton instance
	 *
	 * @var GF_Drip
	 */
	private static $_instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return GF_Drip
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}


	/**
	 * Plugin starting point
	 */
	public function init() {
		parent::init();

		// Add AJAX handlers
		add_action( 'wp_ajax_gf_drip_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Check if plugin settings page should be displayed
	 * 
	 * @return bool
	 */
	public function has_plugin_settings_page() {
		return true;
	}

	/**
	 * Check if form settings page should be displayed
	 * 
	 * @return bool
	 */
	public function has_form_settings_page() {
		return true;
	}

	/**
	 * Plugin settings fields
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Drip API Settings', 'gravityforms-drip' ),
				'fields' => array(
					array(
						'name'              => 'api_token',
						'label'             => esc_html__( 'API Token', 'gravityforms-drip' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'feedback_callback' => array( $this, 'is_valid_api_token' ),
						'description'       => sprintf(
							/* translators: %s: Link to Drip API documentation */
							esc_html__( 'Enter your Drip API token. You can find this in your Drip account under Settings > User Settings > API Token. %s', 'gravityforms-drip' ),
							'<a href="https://www.getdrip.com/user/edit" target="_blank">' . esc_html__( 'Get your API token', 'gravityforms-drip' ) . '</a>'
						),
					),
					array(
						'name'              => 'account_id',
						'label'             => esc_html__( 'Account ID', 'gravityforms-drip' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'feedback_callback' => array( $this, 'is_valid_account_id' ),
						'description'       => esc_html__( 'Enter your Drip Account ID. You can find this in your Drip account URL (e.g., https://www.getdrip.com/{account_id}/).', 'gravityforms-drip' ),
					),
					array(
						'name'        => 'test_connection',
						'type'        => 'test_connection',
						'label'       => esc_html__( 'Test Connection', 'gravityforms-drip' ),
						'description' => esc_html__( 'Click the button below to test your API connection.', 'gravityforms-drip' ),
					),
				),
			),
		);
	}

	/**
	 * Feed settings fields
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		$standard_fields = array(
			array(
				'name'     => 'feedName',
				'label'    => esc_html__( 'Feed Name', 'gravityforms-drip' ),
				'type'     => 'text',
				'class'    => 'medium',
				'required' => true,
				'tooltip'  => '<h6>' . esc_html__( 'Feed Name', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'Enter a name for this feed. This will help you identify it later.', 'gravityforms-drip' ),
			),
			array(
				'name'     => 'email',
				'label'    => esc_html__( 'Email Address', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => true,
				'tooltip'  => '<h6>' . esc_html__( 'Email Address', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'Select the form field that contains the email address. This field is required.', 'gravityforms-drip' ),
			),
		);

		$standard_drip_fields = array(
			array(
				'name'     => 'first_name',
				'label'    => esc_html__( 'First Name', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'last_name',
				'label'    => esc_html__( 'Last Name', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'phone',
				'label'    => esc_html__( 'Phone', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'address',
				'label'    => esc_html__( 'Address', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'city',
				'label'    => esc_html__( 'City', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'state',
				'label'    => esc_html__( 'State', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'zip',
				'label'    => esc_html__( 'ZIP Code', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
			array(
				'name'     => 'country',
				'label'    => esc_html__( 'Country', 'gravityforms-drip' ),
				'type'     => 'field_select',
				'required' => false,
			),
		);

		$custom_fields = array(
			array(
				'name'  => 'custom_fields',
				'label' => esc_html__( 'Custom Fields', 'gravityforms-drip' ),
				'type'  => 'dynamic_field_map',
				'tooltip' => '<h6>' . esc_html__( 'Custom Fields', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'Map form fields to Drip custom fields. The left column shows the Drip custom field name, and the right column allows you to select the form field to map to it.', 'gravityforms-drip' ),
			),
		);

		$additional_settings = array(
			array(
				'name'    => 'tags',
				'label'   => esc_html__( 'Tags', 'gravityforms-drip' ),
				'type'    => 'text',
				'class'   => 'large',
				'tooltip' => '<h6>' . esc_html__( 'Tags', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'Enter tags separated by commas. These tags will be applied to the subscriber in Drip.', 'gravityforms-drip' ),
			),
			array(
				'name'    => 'double_optin',
				'label'   => esc_html__( 'Double Opt-In', 'gravityforms-drip' ),
				'type'    => 'checkbox',
				'choices' => array(
					array(
						'label' => esc_html__( 'Enable double opt-in', 'gravityforms-drip' ),
						'name'  => 'double_optin',
					),
				),
				'tooltip' => '<h6>' . esc_html__( 'Double Opt-In', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'If enabled, subscribers will receive a confirmation email before being added to Drip.', 'gravityforms-drip' ),
			),
			array(
				'name'    => 'feed_condition',
				'label'   => esc_html__( 'Conditional Logic', 'gravityforms-drip' ),
				'type'    => 'feed_condition',
				'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be sent to Drip when the conditions are met. When disabled, all form submissions will be sent to Drip.', 'gravityforms-drip' ),
			),
		);

		return array(
			array(
				'title'  => esc_html__( 'Feed Settings', 'gravityforms-drip' ),
				'fields' => $standard_fields,
			),
			array(
				'title'  => esc_html__( 'Standard Drip Fields', 'gravityforms-drip' ),
				'fields' => $standard_drip_fields,
			),
			array(
				'title'  => esc_html__( 'Custom Fields', 'gravityforms-drip' ),
				'fields' => $custom_fields,
			),
			array(
				'title'  => esc_html__( 'Additional Settings', 'gravityforms-drip' ),
				'fields' => $additional_settings,
			),
		);
	}

	/**
	 * Render test connection field
	 *
	 * @param array $field Field configuration
	 * @param bool  $echo  Whether to echo the output
	 * @return string
	 */
	public function settings_test_connection( $field, $echo = true ) {
		$html = sprintf(
			'<button type="button" id="gf_drip_test_connection" class="button button-secondary">%s</button>',
			esc_html__( 'Test Connection', 'gravityforms-drip' )
		);

		$html .= '<div id="gf_drip_test_result" style="margin-top: 10px;"></div>';

		// Add JavaScript for AJAX test
		$html .= '<script type="text/javascript">
			jQuery(document).ready(function($) {
				$("#gf_drip_test_connection").on("click", function() {
					var $button = $(this);
					var $result = $("#gf_drip_test_result");
					
					$button.prop("disabled", true).text("' . esc_js( __( 'Testing...', 'gravityforms-drip' ) ) . '");
					$result.html("");
					
					$.ajax({
						url: ajaxurl,
						type: "POST",
						data: {
							action: "gf_drip_test_connection",
							api_token: $("#api_token").val(),
							account_id: $("#account_id").val(),
							nonce: "' . wp_create_nonce( 'gf_drip_test_connection' ) . '"
						},
						success: function(response) {
							if (response.success) {
								$result.html("<div class=\'notice notice-success inline\'><p>" + response.data.message + "</p></div>");
							} else {
								$result.html("<div class=\'notice notice-error inline\'><p>" + response.data.message + "</p></div>");
							}
						},
						error: function() {
							$result.html("<div class=\'notice notice-error inline\'><p>' . esc_js( __( 'An error occurred while testing the connection.', 'gravityforms-drip' ) ) . '</p></div>");
						},
						complete: function() {
							$button.prop("disabled", false).text("' . esc_js( __( 'Test Connection', 'gravityforms-drip' ) ) . '");
						}
					});
				});
			});
		</script>';

		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $html;
	}

	/**
	 * AJAX handler for testing connection
	 */
	public function ajax_test_connection() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gf_drip_test_connection' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'gravityforms-drip' ) ) );
		}

		// Check permissions
		if ( ! $this->current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'gravityforms-drip' ) ) );
		}

		$api_token = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
		$account_id = isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : '';

		if ( empty( $api_token ) || empty( $account_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'API token and Account ID are required.', 'gravityforms-drip' ) ) );
		}

		$result = $this->test_api_connection( $api_token, $account_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Connection successful! Your API credentials are valid.', 'gravityforms-drip' ) ) );
	}

	/**
	 * Test API connection
	 *
	 * @param string $api_token  API token
	 * @param string $account_id Account ID
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function test_api_connection( $api_token = '', $account_id = '' ) {
		if ( empty( $api_token ) ) {
			$api_token = $this->get_plugin_setting( 'api_token' );
		}

		if ( empty( $account_id ) ) {
			$account_id = $this->get_plugin_setting( 'account_id' );
		}

		if ( empty( $api_token ) || empty( $account_id ) ) {
			return new WP_Error( 'missing_credentials', esc_html__( 'API token and Account ID are required.', 'gravityforms-drip' ) );
		}

		// Test by fetching account info
		$url = sprintf( 'https://api.getdrip.com/v2/%s/accounts', $account_id );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_token . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Gravity Forms Drip Add-On/' . $this->_version,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API connection test failed: ' . $response->get_error_message() );
			return new WP_Error( 'connection_error', esc_html__( 'Failed to connect to Drip API. Please check your credentials and try again.', 'gravityforms-drip' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['errors'][0]['message'] ) ? $error_data['errors'][0]['message'] : esc_html__( 'Invalid API credentials.', 'gravityforms-drip' );
			$this->log_error( 'API connection test failed: HTTP ' . $response_code . ' - ' . $error_message );
			return new WP_Error( 'invalid_credentials', $error_message );
		}

		return true;
	}

	/**
	 * Check if API token is valid
	 *
	 * @param string $value API token value
	 * @return bool
	 */
	public function is_valid_api_token( $value ) {
		return ! empty( $value ) && strlen( $value ) > 10;
	}

	/**
	 * Check if Account ID is valid
	 *
	 * @param string $value Account ID value
	 * @return bool
	 */
	public function is_valid_account_id( $value ) {
		return ! empty( $value );
	}

	/**
	 * Process the feed
	 *
	 * @param array $feed  Feed object
	 * @param array $entry Entry object
	 * @param array $form  Form object
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {
		// Check if feed conditions are met
		if ( ! $this->is_feed_condition_met( $feed, $form, $entry ) ) {
			$this->log_debug( 'Feed condition not met. Skipping feed processing.' );
			return;
		}

		// Get API credentials
		$api_token = $this->get_plugin_setting( 'api_token' );
		$account_id = $this->get_plugin_setting( 'account_id' );

		if ( empty( $api_token ) || empty( $account_id ) ) {
			$this->log_error( 'API credentials are not configured.' );
			return;
		}

		// Get email field
		$email_field_id = rgars( $feed, 'meta/email' );
		if ( empty( $email_field_id ) ) {
			$this->log_error( 'Email field is not mapped in feed.' );
			return;
		}

		$email = rgar( $entry, $email_field_id );
		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->log_error( 'Invalid email address: ' . $email );
			return;
		}

		// Build subscriber data
		$subscriber_data = array(
			'subscribers' => array(
				array(
					'email' => sanitize_email( $email ),
				),
			),
		);

		// Map standard fields
		$standard_fields = array( 'first_name', 'last_name', 'phone', 'address', 'city', 'state', 'zip', 'country' );
		foreach ( $standard_fields as $field_name ) {
			$field_id = rgars( $feed, 'meta/' . $field_name );
			if ( ! empty( $field_id ) ) {
				$field_value = rgar( $entry, $field_id );
				if ( ! empty( $field_value ) ) {
					$subscriber_data['subscribers'][0][ $field_name ] = sanitize_text_field( $field_value );
				}
			}
		}

		// Map custom fields
		$custom_fields = rgars( $feed, 'meta/custom_fields' );
		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
			$subscriber_data['subscribers'][0]['custom_fields'] = array();
			foreach ( $custom_fields as $drip_field => $gf_field_id ) {
				if ( ! empty( $gf_field_id ) ) {
					$field_value = rgar( $entry, $gf_field_id );
					if ( ! empty( $field_value ) ) {
						$subscriber_data['subscribers'][0]['custom_fields'][ sanitize_text_field( $drip_field ) ] = sanitize_text_field( $field_value );
					}
				}
			}
		}

		// Add tags
		$tags = rgars( $feed, 'meta/tags' );
		if ( ! empty( $tags ) ) {
			$tags_array = array_map( 'trim', explode( ',', $tags ) );
			$tags_array = array_filter( $tags_array );
			if ( ! empty( $tags_array ) ) {
				$subscriber_data['subscribers'][0]['tags'] = array_map( 'sanitize_text_field', $tags_array );
			}
		}

		// Add double opt-in setting
		$double_optin = rgars( $feed, 'meta/double_optin' );
		if ( ! empty( $double_optin ) ) {
			$subscriber_data['subscribers'][0]['double_optin'] = true;
		}

		// Send to Drip API
		$this->send_to_drip( $api_token, $account_id, $subscriber_data, $entry );
	}

	/**
	 * Send subscriber data to Drip API
	 *
	 * @param string $api_token      API token
	 * @param string $account_id     Account ID
	 * @param array  $subscriber_data Subscriber data
	 * @param array  $entry          Entry object
	 * @return void
	 */
	private function send_to_drip( $api_token, $account_id, $subscriber_data, $entry ) {
		$url = sprintf( 'https://api.getdrip.com/v2/%s/subscribers', $account_id );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_token . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Gravity Forms Drip Add-On/' . $this->_version,
				),
				'body'    => wp_json_encode( $subscriber_data ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Failed to send subscriber to Drip: ' . $response->get_error_message() );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 201 !== $response_code && 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['errors'][0]['message'] ) ? $error_data['errors'][0]['message'] : esc_html__( 'Unknown error occurred.', 'gravityforms-drip' );
			$this->log_error( 'Drip API error (HTTP ' . $response_code . '): ' . $error_message );
			return;
		}

		$this->log_debug( 'Subscriber successfully sent to Drip: ' . $subscriber_data['subscribers'][0]['email'] );
	}

	/**
	 * Validate feed settings before saving
	 *
	 * @param array $field Field configuration
	 * @param array $field_setting Field setting value
	 * @return void
	 */
	public function validate_feed_settings( $field, $field_setting ) {
		// Validate email field is mapped
		if ( 'email' === $field['name'] && empty( $field_setting ) ) {
			$this->set_field_error( $field, esc_html__( 'Email field is required.', 'gravityforms-drip' ) );
		}
	}

	/**
	 * Get plugin settings
	 *
	 * @return array
	 */
	public function get_plugin_settings() {
		$settings = parent::get_plugin_settings();
		return $settings;
	}

	/**
	 * Sanitize plugin settings
	 *
	 * @param array $settings Settings to sanitize
	 * @return array
	 */
	public function sanitize_settings( $settings ) {
		$sanitized = parent::sanitize_settings( $settings );

		// Sanitize API token
		if ( isset( $sanitized['api_token'] ) ) {
			$sanitized['api_token'] = sanitize_text_field( $sanitized['api_token'] );
		}

		// Sanitize Account ID
		if ( isset( $sanitized['account_id'] ) ) {
			$sanitized['account_id'] = sanitize_text_field( $sanitized['account_id'] );
		}

		return $sanitized;
	}
}
