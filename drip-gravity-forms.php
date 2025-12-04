<?php
/**
 * Plugin Name: Gravity Forms Drip Add-On
 * Plugin URI: https://example.com/gravity-forms-drip
 * Description: Integrates Gravity Forms with Drip email marketing platform
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: gravityforms-drip
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'GF_DRIP_VERSION', '1.0.0' );
define( 'GF_DRIP_MIN_GF_VERSION', '2.5' );
define( 'GF_DRIP_PLUGIN_FILE', __FILE__ );
define( 'GF_DRIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF_DRIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the plugin
 */
function gf_drip_init() {
	// Prevent multiple initializations
	static $initialized = false;
	if ( $initialized ) {
		return;
	}

	// Check if Gravity Forms is installed and activated
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_notices', 'gf_drip_gravity_forms_required_notice' );
		return;
	}

	// Check if GFCommon class exists and check Gravity Forms version
	if ( class_exists( 'GFCommon' ) && ! version_compare( GFCommon::$version, GF_DRIP_MIN_GF_VERSION, '>=' ) ) {
		add_action( 'admin_notices', 'gf_drip_gravity_forms_version_notice' );
		return;
	}

	// CRITICAL: Check if GFAddOn and GFFeedAddOn classes are available BEFORE requiring the class file
	// GFFeedAddOn extends GFAddOn, so we need both
	if ( ! class_exists( 'GFAddOn' ) || ! class_exists( 'GFFeedAddOn' ) ) {
		// If we're on gform_loaded hook, try again on init as fallback
		if ( did_action( 'gform_loaded' ) && ! did_action( 'init' ) ) {
			return; // Will be handled by init hook
		}
		// If we're on init and still no GFFeedAddOn, show notice
		if ( did_action( 'init' ) ) {
			add_action( 'admin_notices', 'gf_drip_feed_addon_not_found_notice' );
		}
		return;
	}

	// Only proceed if we haven't already loaded the class
	if ( class_exists( 'GF_Drip' ) ) {
		$initialized = true;
		return;
	}

	// Load the add-on class file
	$class_file = GF_DRIP_PLUGIN_DIR . 'class-gf-drip.php';
	if ( file_exists( $class_file ) ) {
		require_once $class_file;
		
		// Register the add-on
		if ( class_exists( 'GFAddOn' ) && class_exists( 'GF_Drip' ) ) {
			GFAddOn::register( 'GF_Drip' );
			$initialized = true;
		}
	}
}
// Try to initialize on gform_loaded with a later priority to ensure all classes are loaded
add_action( 'gform_loaded', 'gf_drip_init', 20 );

// Fallback: Also try on init hook in case gform_loaded fires too early
add_action( 'init', 'gf_drip_init_fallback', 20 );

/**
 * Display notice if Gravity Forms is not installed
 */
function gf_drip_gravity_forms_required_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Gravity Forms Drip Add-On requires Gravity Forms to be installed and activated.', 'gravityforms-drip' ); ?></p>
	</div>
	<?php
}

/**
 * Display notice if Gravity Forms version is too old
 */
function gf_drip_gravity_forms_version_notice() {
	$current_version = class_exists( 'GFCommon' ) && isset( GFCommon::$version ) ? GFCommon::$version : 'Unknown';
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Minimum required version, 2: Current version */
				esc_html__( 'Gravity Forms Drip Add-On requires Gravity Forms version %1$s or higher. You are running version %2$s.', 'gravityforms-drip' ),
				esc_html( GF_DRIP_MIN_GF_VERSION ),
				esc_html( $current_version )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Fallback initialization on init hook
 */
function gf_drip_init_fallback() {
	// Only run if not already initialized
	if ( class_exists( 'GF_Drip' ) ) {
		return;
	}

	// Check if Gravity Forms is installed and activated
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}

	// Check if GFFeedAddOn class is available
	if ( ! class_exists( 'GFFeedAddOn' ) ) {
		return;
	}

	// Check if already initialized via gform_loaded
	if ( did_action( 'gform_loaded' ) ) {
		return;
	}

	// Load the add-on class file
	$class_file = GF_DRIP_PLUGIN_DIR . 'class-gf-drip.php';
	if ( file_exists( $class_file ) && ! class_exists( 'GF_Drip' ) ) {
		require_once $class_file;
		
		// Register the add-on
		if ( class_exists( 'GFAddOn' ) && class_exists( 'GF_Drip' ) ) {
			GFAddOn::register( 'GF_Drip' );
		}
	}
}

/**
 * Display notice if GFFeedAddOn class is not found
 */
function gf_drip_feed_addon_not_found_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Gravity Forms Drip Add-On requires Gravity Forms to be fully loaded. Please ensure Gravity Forms is installed and activated.', 'gravityforms-drip' ); ?></p>
	</div>
	<?php
}

