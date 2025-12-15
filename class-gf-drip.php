<?php
/**
 * Gravity Forms Drip Add-On
 *
 * @package GF_Drip
 * @author  Your Name
 * @version 1.0.1
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

// Include the Gravity Forms Feed Add-On Framework.
// This MUST be called before the class definition
GFForms::include_feed_addon_framework();

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
	 * Should match the plugin folder name for consistency
	 *
	 * @var string
	 */
	protected $_slug = 'gravityformsdrip';

	/**
	 * Plugin path (relative to plugins folder)
	 * Must match the actual plugin folder name
	 *
	 * @var string
	 */
	protected $_path = 'gravity-forms-drip/drip-gravity-forms.php';

	/**
	 * Full path to this class file
	 * Following Gravity Forms pattern: use __FILE__ to point to the class file
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

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
	 * Constructor
	 */
	public function __construct() {
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
	 * Note: GFAddOn uses its own instance management, but we keep this for compatibility
	 *
	 * @var GF_Drip
	 */
	private static $_instance = null;

	/**
	 * Get instance of this class
	 * GFAddOn::register() will create the instance, so this may not be needed
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
		
		// Add JavaScript to show connection status message after page load
		add_action( 'admin_footer', array( $this, 'output_connection_status_script' ) );
		
		// Hook into settings save to test connection
		add_action( 'gform_post_update_plugin_settings', array( $this, 'test_connection_after_save' ), 10, 2 );
	}
	
	/**
	 * Test connection after settings are saved
	 *
	 * @param array $settings Settings that were saved
	 * @param string $slug    Add-on slug
	 */
	public function test_connection_after_save( $settings, $slug ) {
		// Only run for this add-on
		if ( $slug !== $this->get_slug() ) {
			return;
		}
		
		// Get the saved settings
		$api_token = isset( $settings['api_token'] ) ? $settings['api_token'] : '';
		$account_id = isset( $settings['account_id'] ) ? $settings['account_id'] : '';
		
		// Test connection if both are provided
		if ( ! empty( $api_token ) && ! empty( $account_id ) ) {
			$this->log_debug( 'Testing Drip API connection after settings save...' );
			$connection_result = $this->test_api_connection( $api_token, $account_id );
			
			if ( is_wp_error( $connection_result ) ) {
				$error_message = $connection_result->get_error_message();
				$this->log_error( 'Drip API connection test failed: ' . $error_message );
				delete_transient( 'gf_drip_connection_status' );
				set_transient( 'gf_drip_connection_error', $error_message, 300 );
			} else {
				$this->log_debug( 'Drip API connection test successful!' );
				set_transient( 'gf_drip_connection_status', true, HOUR_IN_SECONDS );
				delete_transient( 'gf_drip_connection_error' );
			}
		} else {
			// Clear status if credentials are missing
			delete_transient( 'gf_drip_connection_status' );
			delete_transient( 'gf_drip_connection_error' );
		}
	}
	
	/**
	 * Output JavaScript to display connection status message
	 */
	public function output_connection_status_script() {
		// Only show on plugin settings page
		if ( ! $this->is_plugin_settings( $this->get_slug() ) ) {
			return;
		}
		
		$is_connected = get_transient( 'gf_drip_connection_status' );
		$connection_error = get_transient( 'gf_drip_connection_error' );
		
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var statusMessage = $('#gf_drip_connection_status_message');
			<?php if ( $is_connected ) : ?>
				statusMessage.html('<span style="color: #00a32a; font-weight: bold;">✓ ' + '<?php echo esc_js( __( 'Account connected', 'gravityforms-drip' ) ); ?>' + '</span>');
			<?php elseif ( $connection_error ) : ?>
				statusMessage.html('<span style="color: #d63638; font-weight: bold;">✗ ' + '<?php echo esc_js( $connection_error ); ?>' + '</span>');
			<?php endif; ?>
		});
		</script>
		<?php
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
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.0.1
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		$icon_path = $this->get_base_path() . '/images/menu-icon.svg';
		if ( file_exists( $icon_path ) ) {
			return file_get_contents( $icon_path );
		}
		return '';
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
					'type'              => 'password',
					'class'             => 'medium',
					'required'          => true,
					'feedback_callback' => array( $this, 'validate_api_connection' ),
					'description'       => sprintf(
						/* translators: %s: Link to Drip API documentation */
						esc_html__( 'Enter your Drip API token. You can find this in your Drip account under Settings > User Settings > API Token. %s', 'gravityforms-drip' ),
						'<a href="https://www.getdrip.com/user/edit" target="_blank">' . esc_html__( 'Get your API token', 'gravityforms-drip' ) . '</a>'
					) . '<div id="gf_drip_connection_status_message" style="margin-top: 10px;"></div>',
				),
				array(
					'name'              => 'account_id',
					'label'             => esc_html__( 'Account ID', 'gravityforms-drip' ),
					'type'              => 'text',
					'class'             => 'medium',
					'required'          => true,
					'feedback_callback' => array( $this, 'validate_api_connection' ),
					'description'       => esc_html__( 'Enter your Drip Account ID. You can paste the numeric ID (e.g., 123456) or your full account URL (e.g., https://www.getdrip.com/123456/).', 'gravityforms-drip' ),
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

		$api_token  = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : '';
		$account_id = isset( $_POST['account_id'] ) ? $this->normalize_account_id( sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) ) : '';

		if ( empty( $api_token ) || empty( $account_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'API token and Account ID are required.', 'gravityforms-drip' ) ) );
		}

		$result = $this->test_api_connection( $api_token, $account_id );

		if ( is_wp_error( $result ) ) {
			// Clear connection status on failure
			delete_transient( 'gf_drip_connection_status' );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Store connection status for 1 hour
		set_transient( 'gf_drip_connection_status', true, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'message' => esc_html__( 'Successfully connected to Drip!', 'gravityforms-drip' ) ) );
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

		$account_id = $this->normalize_account_id( $account_id );

		if ( empty( $api_token ) || empty( $account_id ) ) {
			return new WP_Error( 'missing_credentials', esc_html__( 'API token and Account ID are required.', 'gravityforms-drip' ) );
		}

		// Test using an account-scoped endpoint so we validate both the token and account ID.
		// Keep the response lightweight by only requesting a single record.
		$url = sprintf( 'https://api.getdrip.com/v2/%s/campaigns?per_page=1', rawurlencode( (string) $account_id ) );
		
		$this->log_debug( 'Testing Drip API connection to: ' . $url );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_token . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Accept'        => 'application/json',
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

		$this->log_debug( 'Drip API response code: ' . $response_code );

		if ( 200 !== (int) $response_code ) {
			$error_message = esc_html__( 'Invalid API credentials.', 'gravityforms-drip' );

			$error_data = json_decode( $response_body, true );
			if ( is_array( $error_data ) && isset( $error_data['errors'][0]['message'] ) ) {
				$error_message = (string) $error_data['errors'][0]['message'];
			} else {
				$error_message = sprintf(
					/* translators: 1: HTTP status code */
					esc_html__( 'Drip API request failed (HTTP %1$d). Please verify your API token and Account ID.', 'gravityforms-drip' ),
					(int) $response_code
				);
			}

			$this->log_error( 'API connection test failed: HTTP ' . $response_code . ' - ' . $error_message );
			$this->log_error( 'Response body: ' . substr( (string) $response_body, 0, 500 ) ); // Log first 500 chars.
			return new WP_Error( 'invalid_credentials', $error_message );
		}

		$this->log_debug( 'Drip API connection test successful!' );
		return true;
	}

	/**
	 * Validate API connection when settings are saved
	 * This is used as feedback_callback for both api_token and account_id fields
	 * Shows green tick if connected, red tick if error, no tick if not tested yet
	 *
	 * @param string $value Field value
	 * @return bool|null True if connection is valid, false on failure, null if not tested
	 */
	public function validate_api_connection( $value ) {
		// Check cached status first
		$is_connected     = get_transient( 'gf_drip_connection_status' );
		$connection_error = get_transient( 'gf_drip_connection_error' );

		// Prefer freshly submitted values (during settings save UI)
		$posted_api_token  = isset( $_POST['api_token'] ) ? sanitize_text_field( wp_unslash( $_POST['api_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$posted_account_id = isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$api_token  = ! empty( $posted_api_token ) ? $posted_api_token : $this->get_plugin_setting( 'api_token' );
		$account_id = ! empty( $posted_account_id ) ? $posted_account_id : $this->get_plugin_setting( 'account_id' );
		$account_id = $this->normalize_account_id( $account_id );

		// If credentials are blank, clear state and show no feedback.
		if ( empty( $api_token ) || empty( $account_id ) ) {
			delete_transient( 'gf_drip_connection_status' );
			delete_transient( 'gf_drip_connection_error' );
			return null;
		}

		// If we already have a cached status, honor it
		if ( $is_connected ) {
			return true;
		}
		if ( $connection_error ) {
			return false;
		}

		// If both credentials are present but no cached status, test now so feedback shows immediately
		if ( ! empty( $api_token ) && ! empty( $account_id ) ) {
			$result = $this->test_api_connection( $api_token, $account_id );
			if ( is_wp_error( $result ) ) {
				set_transient( 'gf_drip_connection_error', $result->get_error_message(), 300 );
				delete_transient( 'gf_drip_connection_status' );
				return false;
			}

			set_transient( 'gf_drip_connection_status', true, HOUR_IN_SECONDS );
			delete_transient( 'gf_drip_connection_error' );
			return true;
		}

		// No credentials or no status yet
		return null;
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
		$account_id = $this->normalize_account_id( $this->get_plugin_setting( 'account_id' ) );

		if ( empty( $api_token ) || empty( $account_id ) ) {
			$this->log_error( 'API credentials are not configured.' );
			return;
		}

		// Get email field
		$email_field_id = rgars( $feed, 'meta/email' );
		if ( empty( $email_field_id ) || ! is_scalar( $email_field_id ) ) {
			$this->log_error( 'Email field is not mapped in feed.' );
			return;
		}

		$email = $this->get_field_value( $form, $entry, $email_field_id );
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
			if ( empty( $field_id ) || ! is_scalar( $field_id ) ) {
				continue;
			}

			$field_value = $this->get_field_value( $form, $entry, $field_id );
			if ( '' !== $field_value && null !== $field_value ) {
				$subscriber_data['subscribers'][0][ $field_name ] = is_scalar( $field_value ) ? sanitize_text_field( $field_value ) : wp_json_encode( $field_value );
			}
		}

		// Map custom fields
		$custom_fields = rgars( $feed, 'meta/custom_fields' );
		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
			$subscriber_data['subscribers'][0]['custom_fields'] = array();
			foreach ( $custom_fields as $drip_field => $gf_field_id ) {
				if ( empty( $gf_field_id ) || ! is_scalar( $gf_field_id ) || ! is_scalar( $drip_field ) ) {
					continue;
				}

				$field_value = $this->get_field_value( $form, $entry, $gf_field_id );
				if ( '' !== $field_value && null !== $field_value ) {
					$subscriber_data['subscribers'][0]['custom_fields'][ sanitize_text_field( $drip_field ) ] = is_scalar( $field_value ) ? sanitize_text_field( $field_value ) : wp_json_encode( $field_value );
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
		$send_result = $this->send_to_drip( $api_token, $account_id, $subscriber_data, $entry );
		if ( is_wp_error( $send_result ) ) {
			$this->log_error( 'Failed to send subscriber to Drip: ' . $send_result->get_error_message() );
		}
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
		$account_id = $this->normalize_account_id( $account_id );
		if ( empty( $account_id ) ) {
			return new WP_Error( 'missing_account_id', esc_html__( 'Account ID is required.', 'gravityforms-drip' ) );
		}

		$url = sprintf( 'https://api.getdrip.com/v2/%s/subscribers', rawurlencode( (string) $account_id ) );

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $api_token . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Gravity Forms Drip Add-On/' . $this->_version,
				),
				'body'    => wp_json_encode( $subscriber_data ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Failed to send subscriber to Drip: ' . $response->get_error_message() );
			return new WP_Error( 'connection_error', esc_html__( 'Failed to send subscriber to Drip.', 'gravityforms-drip' ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 201 !== $response_code && 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['errors'][0]['message'] ) ? $error_data['errors'][0]['message'] : esc_html__( 'Unknown error occurred.', 'gravityforms-drip' );
			$this->log_error( 'Drip API error (HTTP ' . $response_code . '): ' . $error_message );
			return new WP_Error( 'api_error', $error_message );
		}

		$this->log_debug( 'Subscriber successfully sent to Drip: ' . $subscriber_data['subscribers'][0]['email'] );
		return true;
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
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @since 1.0.1
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName' => esc_html__( 'Feed Name', 'gravityforms-drip' ),
			'email'    => esc_html__( 'Email Field', 'gravityforms-drip' ),
			'tags'     => esc_html__( 'Tags', 'gravityforms-drip' ),
		);
	}

	/**
	 * Returns the value to be displayed in the Email column.
	 *
	 * @since 1.0.1
	 * @access public
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string Email field label. Empty string on failure.
	 */
	public function get_column_value_email( $feed ) {
		$email_field_id = isset( $feed['meta']['email'] ) ? $feed['meta']['email'] : '';
		if ( empty( $email_field_id ) ) {
			return esc_html__( 'Not mapped', 'gravityforms-drip' );
		}

		// Get the form to find the field label
		$form_id = isset( $feed['form_id'] ) ? $feed['form_id'] : 0;
		if ( empty( $form_id ) ) {
			return esc_html__( 'Unknown', 'gravityforms-drip' );
		}

		$form = GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) || empty( $form ) ) {
			return esc_html__( 'Form not found', 'gravityforms-drip' );
		}

		$field = GFAPI::get_field( $form, $email_field_id );
		if ( is_wp_error( $field ) || empty( $field ) ) {
			return esc_html__( 'Field not found', 'gravityforms-drip' );
		}

		return esc_html( $field->label );
	}

	/**
	 * Returns the value to be displayed in the Tags column.
	 *
	 * @since 1.0.1
	 * @access public
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string Tags. Empty string if no tags.
	 */
	public function get_column_value_tags( $feed ) {
		$tags = isset( $feed['meta']['tags'] ) ? $feed['meta']['tags'] : '';
		if ( empty( $tags ) ) {
			return esc_html__( '—', 'gravityforms-drip' );
		}

		// Limit display to first 50 characters
		$tags_display = esc_html( $tags );
		if ( strlen( $tags_display ) > 50 ) {
			$tags_display = substr( $tags_display, 0, 50 ) . '...';
		}

		return $tags_display;
	}

	/**
	 * Check if user can create a new feed
	 * Only allow creating new feeds if at least one feed already exists
	 * But always allow the first feed if settings are configured
	 *
	 * @param int $form_id Form ID
	 * @return bool
	 */
	public function can_create_feed( $form_id = 0 ) {
		// Get current form ID if not provided
		if ( empty( $form_id ) ) {
			$form_id = rgget( 'id' );
		}

		if ( empty( $form_id ) ) {
			return false;
		}

		// Check if settings are configured
		$api_token = $this->get_plugin_setting( 'api_token' );
		$account_id = $this->normalize_account_id( $this->get_plugin_setting( 'account_id' ) );
		
		// If settings are not configured, don't allow feed creation
		if ( empty( $api_token ) || empty( $account_id ) ) {
			return false;
		}

		// Settings are configured; allow creating feeds (including the first one).
		return true;
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
			$sanitized['account_id'] = $this->normalize_account_id( sanitize_text_field( $sanitized['account_id'] ) );
		}

		// Test connection when both fields are present and have values
		if ( ! empty( $sanitized['api_token'] ) && ! empty( $sanitized['account_id'] ) ) {
			// Test with the sanitized values (the new values being saved)
			$this->log_debug( 'Testing Drip API connection with provided credentials...' );
			$connection_result = $this->test_api_connection( $sanitized['api_token'], $sanitized['account_id'] );
			
			if ( is_wp_error( $connection_result ) ) {
				// Connection failed
				$error_message = $connection_result->get_error_message();
				$this->log_error( 'Drip API connection test failed: ' . $error_message );
				
				// Set field errors to display the error message
				$this->set_field_error( 'api_token', $error_message );
				$this->set_field_error( 'account_id', $error_message );
				
				// Store error status
				delete_transient( 'gf_drip_connection_status' );
				delete_transient( 'gf_drip_connection_error' );
				set_transient( 'gf_drip_connection_error', $error_message, 300 ); // Store error for 5 minutes
			} else {
				// Connection successful
				$this->log_debug( 'Drip API connection test successful!' );
				set_transient( 'gf_drip_connection_status', true, HOUR_IN_SECONDS );
				delete_transient( 'gf_drip_connection_error' );
			}
		} else {
			// Clear connection status if credentials are missing
			delete_transient( 'gf_drip_connection_status' );
			delete_transient( 'gf_drip_connection_error' );
		}

		return $sanitized;
	}

	/**
	 * Normalize the Account ID value.
	 *
	 * Users often paste a full URL (e.g. https://www.getdrip.com/123456/).
	 * This method extracts the account ID and returns a trimmed string.
	 *
	 * @param string $account_id_raw Raw account ID or URL.
	 * @return string Normalized account ID.
	 */
	private function normalize_account_id( $account_id_raw ) {
		$account_id_raw = is_scalar( $account_id_raw ) ? (string) $account_id_raw : '';
		$account_id_raw = trim( $account_id_raw );

		if ( '' === $account_id_raw ) {
			return '';
		}

		// If it's a URL, extract the first path segment.
		if ( false !== strpos( $account_id_raw, '://' ) ) {
			$parsed = wp_parse_url( $account_id_raw );
			if ( is_array( $parsed ) && ! empty( $parsed['path'] ) ) {
				$path  = trim( (string) $parsed['path'], '/' );
				$parts = explode( '/', $path );
				if ( ! empty( $parts[0] ) ) {
					$account_id_raw = (string) $parts[0];
				}
			}
		}

		// If it's a path-like value, take the first segment.
		if ( false !== strpos( $account_id_raw, '/' ) ) {
			$parts = explode( '/', trim( $account_id_raw, '/' ) );
			if ( ! empty( $parts[0] ) ) {
				$account_id_raw = (string) $parts[0];
			}
		}

		// Drip account IDs are typically numeric; keep digits if present.
		$numeric = preg_replace( '/\D+/', '', $account_id_raw );
		return '' !== $numeric ? $numeric : $account_id_raw;
	}
}
