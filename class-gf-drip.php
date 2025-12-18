<?php
/**
 * Gravity Forms Drip Add-On
 *
 * @package GF_Drip
 * @author  Megan Jones
 * @version 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Include the Gravity Forms Feed Add-On Framework, mirroring official add-ons like EmailOctopus.
GFForms::include_feed_addon_framework();

/**
 * Main add-on class
 *
 * @since 1.0.0
 */
class GF_Drip extends GFFeedAddOn {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $_version = GF_DRIP_VERSION;

	/**
	 * Minimum Gravity Forms version required.
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
	 * Plugin path
	 *
	 * @var string
	 */
	protected $_path = 'drip-gravity-forms.php';

	/**
	 * Full path to this file
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
	 * Cached result of the last API initialization attempt.
	 *
	 * @since 1.0.0
	 *
	 * @var bool|null True if valid, false if invalid, null if not yet checked.
	 */
	protected $api_initialized = null;

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

		// Add filter to modify plugin row meta
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		
		// Add Settings link to plugin action links - use plugin basename
		$plugin_basename = plugin_basename( GF_DRIP_PLUGIN_FILE );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'plugin_action_links' ) );
		
		// Enqueue scripts and styles for the details popup
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
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
						// Use live validation for feedback, mirroring the Kit add-on behaviour.
						'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
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
						'description'       => esc_html__( 'Enter your Drip Account ID. You can find this in your Drip account URL (e.g., https://www.getdrip.com/{account_id}/).', 'gravityforms-drip' ),
						// Use the same feedback callback so both fields get ticks/crosses.
						'feedback_callback' => array( $this, 'plugin_settings_fields_feedback_callback' ),
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

		// Get Drip custom fields for the dropdown - ensure API is initialized first
		$drip_custom_field_choices = array();
		if ( $this->initialize_api() ) {
			$drip_custom_field_choices = $this->get_drip_custom_field_choices();
		}

		$custom_fields = array(
			array(
				'name'           => 'custom_fields',
				'label'          => esc_html__( 'Custom Fields', 'gravityforms-drip' ),
				'type'           => 'generic_map',
				'key_field'      => array(
					'allow_custom' => false,
					'choices'      => $drip_custom_field_choices,
					'placeholder'  => esc_html__( 'Select a Drip field', 'gravityforms-drip' ),
				),
				'value_field'    => array(
					'allow_custom' => true,
					'placeholder'  => esc_html__( 'Select a Value', 'gravityforms-drip' ),
				),
				'disable_custom' => true,
				'description'    => '<p>' . esc_html__( 'Map form fields to Drip custom fields. Select a Drip custom field from the dropdown (left column) and map it to a Gravity Forms field (right column).', 'gravityforms-drip' ) . '</p>',
				'tooltip'        => '<h6>' . esc_html__( 'Custom Fields', 'gravityforms-drip' ) . '</h6>' . esc_html__( 'Map form fields to Drip custom fields. The left column shows the Drip custom field name, and the right column allows you to select the form field to map to it.', 'gravityforms-drip' ),
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
	 * Form settings page title
	 *
	 * @since 1.0.0
	 * @return string Form Settings Title
	 */
	public function feed_settings_title() {
		return esc_html__( 'Feed Settings', 'gravityforms-drip' );
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.0.0
	 *
	 * @return string SVG content or empty string on failure
	 */
	public function get_menu_icon() {
		// Use plugin_dir_path with the main plugin file - this ensures the path is always
		// relative to wherever the plugin is installed, regardless of folder name
		// This matches the approach suggested in the prompt for bundled plugins
		$icon_path = plugin_dir_path( GF_DRIP_PLUGIN_FILE ) . 'images/menu-icon.svg';
		
		// Normalize path separators for cross-platform compatibility
		$icon_path = wp_normalize_path( $icon_path );
		
		if ( file_exists( $icon_path ) ) {
			$icon_content = file_get_contents( $icon_path );
			if ( false !== $icon_content && ! empty( $icon_content ) ) {
				return $icon_content;
			}
		}
		
		// Fallback: try using the plugin directory constant
		$fallback_path = wp_normalize_path( GF_DRIP_PLUGIN_DIR . 'images/menu-icon.svg' );
		if ( file_exists( $fallback_path ) ) {
			$icon_content = file_get_contents( $fallback_path );
			if ( false !== $icon_content && ! empty( $icon_content ) ) {
				return $icon_content;
			}
		}
		
		// If we get here, log an error but don't break the plugin
		$this->log_error( __METHOD__ . '(): Menu icon file not found. Tried: ' . $icon_path . ' and ' . $fallback_path );
		return '';
	}

	/**
	 * Test API connection
	 *
	 * @param string $api_token  API token
	 * @param string $account_id Account ID
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function test_api_connection( $api_token = '', $account_id = '' ) {
		// Fall back to saved settings if explicit values not provided.
		if ( empty( $api_token ) ) {
			$api_token = $this->get_plugin_setting( 'api_token' );
		}

		if ( empty( $account_id ) ) {
			$account_id = $this->get_plugin_setting( 'account_id' );
		}

		// Sanitize and trim to avoid subtle issues with copy/pasted spaces.
		$api_token  = sanitize_text_field( $api_token );
		$account_id = sanitize_text_field( $account_id );

		if ( empty( $api_token ) || empty( $account_id ) ) {
			return new WP_Error( 'missing_credentials', esc_html__( 'API token and Account ID are required.', 'gravityforms-drip' ) );
		}

		// Test by fetching subscribers with limit=1 - this validates credentials using an endpoint we know works.
		$url = sprintf( 'https://api.getdrip.com/v2/%s/subscribers?limit=1', $account_id );

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

		// Log the response for debugging.
		$this->log_debug( __METHOD__ . '(): Drip API response code: ' . $response_code );

		if ( 200 !== $response_code ) {
			$error_data    = json_decode( $response_body, true );
			$error_message = isset( $error_data['errors'][0]['message'] ) ? $error_data['errors'][0]['message'] : '';

			if ( empty( $error_message ) ) {
				// Fall back to a generic, less alarming message if the API didn't provide details.
				$error_message = esc_html__( 'Unable to verify your Drip API credentials. Please check your token and Account ID, save your settings, and try again.', 'gravityforms-drip' );
			}

			$this->log_error( __METHOD__ . '(): API connection test failed: HTTP ' . $response_code . ' - ' . $error_message );

			return new WP_Error( 'invalid_credentials', $error_message );
		}

		// Success - log for debugging.
		$this->log_debug( __METHOD__ . '(): Drip API connection test successful.' );

		return true;
	}

	/**
	 * Get available Drip custom fields for use in the dynamic field map.
	 *
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function get_drip_custom_field_choices() {
		$choices = array();

		// Make sure we have valid credentials before calling the API.
		if ( ! $this->initialize_api() ) {
			$this->log_debug( __METHOD__ . '(): Drip API could not be initialized. No custom fields loaded.' );
			return $choices;
		}

		$account_id = $this->get_plugin_setting( 'account_id' );
		$api_token  = $this->get_plugin_setting( 'api_token' );

		if ( rgblank( $account_id ) || rgblank( $api_token ) ) {
			return $choices;
		}

		// Attempt to fetch custom field identifiers from Drip.
		// Try the custom_field_identifiers endpoint first, fall back to custom_fields if needed.
		$url      = sprintf( 'https://api.getdrip.com/v2/%s/custom_field_identifiers', sanitize_text_field( $account_id ) );
		$this->log_debug( __METHOD__ . '(): Attempting to fetch custom fields from: ' . $url );
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( sanitize_text_field( $api_token ) . ':' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'Gravity Forms Drip Add-On/' . $this->_version,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( __METHOD__ . '(): Failed to retrieve Drip custom fields. ' . $response->get_error_message() );
			return $choices;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		
		if ( 200 !== $code ) {
			$this->log_error( __METHOD__ . '(): Unexpected response code when retrieving Drip custom fields: ' . $code . '. Response body: ' . $body_raw );
			return $choices;
		}

		$body = json_decode( $body_raw, true );

		// Log the raw response for debugging
		$this->log_debug( __METHOD__ . '(): Drip API response body: ' . print_r( $body, true ) );

		if ( empty( $body['custom_field_identifiers'] ) || ! is_array( $body['custom_field_identifiers'] ) ) {
			$this->log_debug( __METHOD__ . '(): No custom field identifiers found in Drip response. Response keys: ' . ( is_array( $body ) ? implode( ', ', array_keys( $body ) ) : 'not an array' ) );
			return $choices;
		}

		// Standard Drip fields that are already covered in the standard fields section
		// These should be excluded from the custom fields dropdown
		$standard_fields = array( 'email', 'first_name', 'last_name', 'phone', 'address', 'city', 'state', 'zip', 'country' );
		
		// Drip API returns custom_field_identifiers as an array of strings (field identifiers)
		foreach ( $body['custom_field_identifiers'] as $field_identifier ) {
			if ( empty( $field_identifier ) ) {
				continue;
			}
			
			// Handle both string format (direct identifier) and object format (with 'id' key) for compatibility
			$identifier = is_array( $field_identifier ) && isset( $field_identifier['id'] ) 
				? $field_identifier['id'] 
				: $field_identifier;
			
			if ( empty( $identifier ) || ! is_string( $identifier ) ) {
				continue;
			}
			
			// Skip standard fields that are already covered in the standard fields section
			if ( in_array( strtolower( $identifier ), array_map( 'strtolower', $standard_fields ), true ) ) {
				continue;
			}
			
			$choices[] = array(
				'value' => esc_attr( $identifier ),
				'label' => esc_html( $identifier ),
			);
		}

		// Log how many choices were loaded for debugging
		$this->log_debug( __METHOD__ . '(): Loaded ' . count( $choices ) . ' Drip custom field choices.' );

		return $choices;
	}

	/**
	 * Initialize the Drip API using saved credentials.
	 *
	 * This is used by the add-on framework (not the settings field feedback)
	 * to determine if the API is ready for use on feed and form settings pages.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if initialized and valid, false otherwise.
	 */
	public function initialize_api() {
		if ( null !== $this->api_initialized ) {
			return $this->api_initialized;
		}

		$api_token  = $this->get_plugin_setting( 'api_token' );
		$account_id = $this->get_plugin_setting( 'account_id' );

		// Require both values to be present before we try to initialize.
		if ( rgblank( $api_token ) || rgblank( $account_id ) ) {
			$this->api_initialized = false;

			return false;
		}

		$result = $this->test_api_connection( $api_token, $account_id );

		if ( is_wp_error( $result ) ) {
			$this->log_debug( __METHOD__ . '(): Drip API credentials could not be validated. ' . $result->get_error_message() );
			$this->api_initialized = false;
		} else {
			$this->log_debug( __METHOD__ . '(): Drip API credentials are valid.' );
			$this->api_initialized = true;
		}

		return $this->api_initialized;
	}

	/**
	 * Clear the API initialization cache when settings are updated.
	 *
	 * This ensures the feedback callback uses fresh values after saving.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The settings being saved.
	 *
	 * @return void
	 */
	public function update_plugin_settings( $settings ) {
		// Clear the cached API initialization result so feedback callbacks use fresh values.
		$this->api_initialized = null;

		parent::update_plugin_settings( $settings );
	}

	/**
	 * Feedback callback for API Token and Account ID in plugin settings.
	 *
	 * Mirrors the Kit (ConvertKit) add-on: validates the currently entered
	 * values (not just the saved ones) so the tick/cross reflects what the
	 * user has typed before they save.
	 *
	 * Returning null when either field is empty prevents any icon being shown.
	 *
	 * @since 1.0.0
	 *
     * @param string                                        $value The value of the field being validated.
     * @param \Gravity_Forms\Gravity_Forms\Settings\Fields\Text $field  The field object being validated.
	 *
	 * @return bool|null True if valid, false if invalid, null if not enough data.
	 */
	public function plugin_settings_fields_feedback_callback( $value, $field ) {

		// If the current field is empty, do not show any icon yet.
		if ( empty( $value ) ) {
			return null;
		}

		// Get both API Token and Account ID.
		// The $value parameter contains the current field's value (saved value after save, or POST value during typing).
		// For the other field, check POST first (for live validation while typing), then saved settings (for after save).
		if ( 'api_token' === $field->name ) {
			$api_token  = $value;
			// Check POST first for live typing feedback, then saved settings.
			$account_id = rgpost( '_gaddon_setting_account_id' );
			if ( rgblank( $account_id ) ) {
				$account_id = $this->get_plugin_setting( 'account_id' );
			}
		} else {
			$account_id = $value;
			// Check POST first for live typing feedback, then saved settings.
			$api_token = rgpost( '_gaddon_setting_api_token' );
			if ( rgblank( $api_token ) ) {
				$api_token = $this->get_plugin_setting( 'api_token' );
			}
		}

		// If we still don't have both values, don't show an icon yet.
		if ( rgblank( $api_token ) || rgblank( $account_id ) ) {
			return null;
		}

		// Sanitize values before testing.
		$api_token  = sanitize_text_field( $api_token );
		$account_id = sanitize_text_field( $account_id );

		// Test the API connection with these credentials.
		$result = $this->test_api_connection( $api_token, $account_id );

		// Return true for success (green tick), false for failure (red X).
		if ( is_wp_error( $result ) ) {
			// Log the error so it appears in Gravity Forms logs.
			$this->log_error( __METHOD__ . '(): Drip API validation failed for field ' . $field->name . '. ' . $result->get_error_message() );

			return false;
		}

		// Success - log and return true for green tick.
		$this->log_debug( __METHOD__ . '(): Drip API credentials are valid for field ' . $field->name . '.' );

		return true;
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
			$this->log_debug( __METHOD__ . '(): Feed condition not met. Skipping feed processing.' );
			return;
		}

		// Get API credentials
		$api_token = $this->get_plugin_setting( 'api_token' );
		$account_id = $this->get_plugin_setting( 'account_id' );

		if ( empty( $api_token ) || empty( $account_id ) ) {
			$this->add_feed_error( esc_html__( 'Feed was not processed because API credentials are not configured.', 'gravityforms-drip' ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): API credentials are not configured.' );
			return;
		}

		// Get email field
		$email_field_id = rgars( $feed, 'meta/email' );
		if ( empty( $email_field_id ) ) {
			$this->add_feed_error( esc_html__( 'Feed was not processed because an email field is not mapped.', 'gravityforms-drip' ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Email field is not mapped in feed.' );
			return;
		}

		$email = rgar( $entry, $email_field_id );
		if ( empty( $email ) ) {
			$this->add_feed_error( esc_html__( 'Feed was not processed because an email address was not provided.', 'gravityforms-drip' ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Email address was not provided.' );
			return;
		}

		if ( ! is_email( $email ) ) {
			$this->add_feed_error( sprintf( esc_html__( "Feed was not processed because '%s' is not a valid email address.", 'gravityforms-drip' ), $email ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Invalid email address: ' . $email );
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

		// Map custom fields.
		// The generic_map setting stores an array of objects with 'key' (Drip field) and 'value' (GF field ID).
		$custom_fields = rgars( $feed, 'meta/custom_fields' );
		if ( ! empty( $custom_fields ) && is_array( $custom_fields ) ) {
			$subscriber_data['subscribers'][0]['custom_fields'] = array();

			foreach ( $custom_fields as $mapped_field ) {
				// Handle both old format (associative array) and new format (array of objects).
				if ( isset( $mapped_field['key'] ) && isset( $mapped_field['value'] ) ) {
					// New format: array of objects with 'key' and 'value'.
					$drip_field  = $mapped_field['key'];
					$gf_field_id = $mapped_field['value'];
				} else {
					// Old format: associative array (for backward compatibility).
					$drip_field  = key( $mapped_field );
					$gf_field_id = current( $mapped_field );
				}

				if ( empty( $drip_field ) || empty( $gf_field_id ) ) {
					continue;
				}

					$field_value = rgar( $entry, $gf_field_id );
				if ( $field_value === '' || $field_value === null ) {
					continue;
				}

						$subscriber_data['subscribers'][0]['custom_fields'][ sanitize_text_field( $drip_field ) ] = sanitize_text_field( $field_value );
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

		// Log subscriber data before sending (sanitized for privacy)
		$log_data = $subscriber_data;
		if ( isset( $log_data['subscribers'][0]['email'] ) ) {
			$log_data['subscribers'][0]['email'] = sanitize_email( $log_data['subscribers'][0]['email'] );
		}
		$this->log_debug( __METHOD__ . '(): Attempting to send subscriber to Drip with data: ' . print_r( $log_data, true ) );

		// Send to Drip API
		$this->send_to_drip( $api_token, $account_id, $subscriber_data, $entry, $feed, $form );
	}

	/**
	 * Send subscriber data to Drip API
	 *
	 * @param string $api_token       API token
	 * @param string $account_id      Account ID
	 * @param array  $subscriber_data Subscriber data
	 * @param array  $entry           Entry object
	 * @param array  $feed            Feed object
	 * @param array  $form            Form object
	 * @return void
	 */
	private function send_to_drip( $api_token, $account_id, $subscriber_data, $entry, $feed, $form ) {
		$url = sprintf( 'https://api.getdrip.com/v2/%s/subscribers', $account_id );

		$this->log_debug( __METHOD__ . '(): Sending request to Drip API: ' . $url );

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
			$error_message = sprintf( esc_html__( 'Error subscribing to Drip: %s', 'gravityforms-drip' ), $response->get_error_message() );
			$this->add_feed_error( $error_message, $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Failed to send subscriber to Drip: ' . $response->get_error_message() );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 201 !== $response_code && 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['errors'][0]['message'] ) 
				? $error_data['errors'][0]['message'] 
				: sprintf( esc_html__( 'Unknown error occurred (HTTP %d).', 'gravityforms-drip' ), $response_code );
			
			$this->add_feed_error( sprintf( esc_html__( 'Error subscribing to Drip: %s', 'gravityforms-drip' ), $error_message ), $feed, $entry, $form );
			$this->log_error( __METHOD__ . '(): Drip API error (HTTP ' . $response_code . '): ' . $error_message );
			return;
		}

		// Success - log and add note to entry
		$response_data = json_decode( $response_body, true );
		$subscriber_id = isset( $response_data['subscribers'][0]['id'] ) ? $response_data['subscribers'][0]['id'] : '';
		
		$this->log_debug( __METHOD__ . '(): Subscriber successfully sent to Drip. Response: ' . json_encode( $response_data ) );
		
		if ( ! empty( $entry['id'] ) ) {
			$note_message = ! empty( $subscriber_id ) 
				? sprintf( esc_html__( 'Subscriber created in Drip. ID: %s', 'gravityforms-drip' ), $subscriber_id )
				: esc_html__( 'Subscriber created in Drip.', 'gravityforms-drip' );
			$this->add_note( $entry['id'], $note_message, 'success' );
			$this->log_debug( __METHOD__ . '(): Added success note to entry #' . $entry['id'] . ': ' . $note_message );
		}
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

	/**
	 * Enable feed duplication.
	 *
	 * @since 1.0.0
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {
		return true;
	}

	/**
	 * Configure which columns should be displayed on the feed list page.
	 *
	 * Mirrors the Kit add-on by showing the feed name plus a second column
	 * which gives additional context.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feed_name' => esc_html__( 'Name', 'gravityforms-drip' ),
			'form_id'   => esc_html__( 'Form', 'gravityforms-drip' ),
		);
	}

	/**
	 * Return the value to be displayed in the Feed Name column.
	 *
	 * Ensures pre-existing feeds without a stored feedName still render a
	 * clickable label so they can be edited.
	 *
	 * @since 1.0.0
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_feed_name( $feed ) {
		$name = rgar( $feed['meta'], 'feedName' );

		if ( empty( $name ) ) {
			$name = sprintf(
				/* translators: %d is the feed ID. */
				esc_html__( 'Drip Feed #%d', 'gravityforms-drip' ),
				rgar( $feed, 'id' )
			);
		}

		return $name;
	}

	/**
	 * Returns the value for the Form column.
	 *
	 * Mirrors the Kit add-on by displaying contextual information for each feed.
	 * Here we display the associated Gravity Form title.
	 *
	 * @since 1.0.0
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_form_id( $feed ) {
		$form_id = rgar( $feed, 'form_id' );

		if ( empty( $form_id ) ) {
			return '';
		}

		$form = GFAPI::get_form( $form_id );

		return is_wp_error( $form ) || empty( $form ) ? '' : esc_html( rgar( $form, 'title' ) );
	}

	/**
	 * Add Settings link to plugin action links
	 *
	 * @param array $links Existing plugin action links
	 * @return array Modified plugin action links
	 */
	public function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) ) . '">' . esc_html__( 'Settings', 'gravityforms-drip' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Modify plugin row meta to add "View details" link
	 *
	 * @param array  $plugin_meta Array of plugin meta links
	 * @param string $plugin_file Plugin file path
	 * @return array Modified plugin meta links
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		// Check if this is our plugin using plugin basename
		$plugin_basename = plugin_basename( GF_DRIP_PLUGIN_FILE );
		if ( $plugin_file !== $plugin_basename ) {
			return $plugin_meta;
		}

		// Remove "Visit plugin site" if it exists
		foreach ( $plugin_meta as $key => $meta ) {
			if ( strpos( $meta, 'Visit plugin site' ) !== false ) {
				unset( $plugin_meta[ $key ] );
			}
		}

		// Add "View details" link
		$plugin_meta[] = '<a href="#" class="gf-drip-view-details" data-plugin="' . esc_attr( $this->_slug ) . '">' . esc_html__( 'View details', 'gravityforms-drip' ) . '</a>';

		return $plugin_meta;
	}

	/**
	 * Enqueue admin scripts and styles for the details popup and settings UI.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Ensure the Drip icon in the Gravity Forms settings navigation doesn't shrink
		// when the sidebar is collapsed or space is constrained.
		// This applies to all Gravity Forms pages (settings, feeds, etc.)
		// Check if we're on any Gravity Forms admin page
		if ( strpos( $screen->id, 'forms_page_' ) === 0 || strpos( $screen->id, 'toplevel_page_gf_' ) === 0 ) {
			$css = '
			/* Prevent the Drip icon from being collapsed by flexbox in the GF Settings UI */
			/* Target the icon in all contexts: settings page, feed settings page, etc. */
			svg.gf-drip-icon,
			.gf-addon-menu-item svg.gf-drip-icon,
			.gf-addon-menu-item[data-slug="gravityforms-drip"] svg,
			#gform-settings-page svg.gf-drip-icon,
			.gform-settings-page svg.gf-drip-icon {
				flex-shrink: 0 !important;
				min-width: 24px !important;
				width: 24px !important;
				height: 24px !important;
				display: inline-block !important;
			}
			/* Also target any parent containers that might be causing shrinking */
			.gf-addon-menu-item[data-slug="gravityforms-drip"] {
				flex-shrink: 0;
				min-width: 24px;
			}
			';

			// Register a lightweight admin style handle for our inline CSS.
			wp_register_style( 'gf-drip-admin', false );
			wp_enqueue_style( 'gf-drip-admin' );
			wp_add_inline_style( 'gf-drip-admin', $css );
		}

		// Scripts and styles for the plugin row "View details" popup on the Plugins screen.
		if ( 'plugins' === $screen->id ) {
			// Enqueue WordPress's built-in thickbox for modal
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_style( 'thickbox' );

			// Add inline styles for the changelog popup (without style tags)
			$styles = "
			.gf-drip-changelog {
				padding: 20px;
			}
			.gf-drip-changelog h2 {
				margin-top: 0;
				margin-bottom: 20px;
				font-size: 18px;
				font-weight: 600;
				line-height: 1.4;
				color: #23282d;
			}
			.gf-drip-changelog-content {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 15px 20px;
				margin-top: 15px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.gf-drip-changelog-content h4 {
				margin-top: 0;
				margin-bottom: 12px;
				font-size: 14px;
				font-weight: 600;
				color: #23282d;
			}
			.gf-drip-changelog-content ul {
				margin: 0 0 0 20px;
				padding: 0;
				list-style-type: disc;
			}
			.gf-drip-changelog-content li {
				margin-bottom: 8px;
				line-height: 1.6;
				color: #50575e;
			}
			";

			// Add inline styles
			wp_add_inline_style( 'thickbox', $styles );

			// Get changelog content
			$changelog    = $this->get_changelog();
			$popup_title  = esc_js( $this->_title ) . ' v' . esc_js( $this->_version ) . ' ' . esc_js( __( 'Changelog', 'gravityforms-drip' ) );
			$popup_content = '<div id="gf-drip-details-popup" style="display:none;"><div class="gf-drip-changelog">' . wp_kses_post( $changelog ) . '</div></div>';

			// Add inline script for the popup
			$script = "
			(function($) {
				$(document).ready(function() {
					// Add popup content to body if it doesn't exist
					if ($('#gf-drip-details-popup').length === 0) {
						$('body').append(" . json_encode( $popup_content ) . ");
					}
					
					// Handle click on View details link
					$(document).on('click', '.gf-drip-view-details', function(e) {
						e.preventDefault();
						if (typeof tb_show !== 'undefined') {
							tb_show(" . json_encode( $popup_title ) . ", '#TB_inline?inlineId=gf-drip-details-popup&width=600&height=500');
						}
					});
				});
			})(jQuery);
			";

			wp_add_inline_script( 'thickbox', $script );
		}
	}

	/**
	 * Get the changelog content
	 *
	 * @return string Changelog HTML
	 */
	private function get_changelog() {
		$changelog = '<h2>' . esc_html( $this->_title ) . ' v' . esc_html( $this->_version ) . ' ' . esc_html__( 'Changelog', 'gravityforms-drip' ) . '</h2>';
		$changelog .= '<div class="gf-drip-changelog-content">';
		$changelog .= '<h4>' . esc_html( $this->_version ) . '</h4>';
		$changelog .= '<ul>';
		$changelog .= '<li>' . esc_html__( 'Initial release', 'gravityforms-drip' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Basic integration with Drip API', 'gravityforms-drip' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Field mapping support', 'gravityforms-drip' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Conditional logic support', 'gravityforms-drip' ) . '</li>';
		$changelog .= '<li>' . esc_html__( 'Tag support', 'gravityforms-drip' ) . '</li>';
		$changelog .= '</ul>';
		$changelog .= '</div>';

		return $changelog;
	}
}
