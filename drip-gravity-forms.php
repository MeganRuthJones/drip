<?php
/**
 * Plugin Name: Gravity Forms Drip Add-On
 * Plugin URI: https://example.com/gravity-forms-drip
 * Description: Integrates Gravity Forms with Drip email marketing platform
 * Version: 1.0.0
 * Author: Megan Jones
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
	// Check if Gravity Forms is installed and activated
	if ( ! class_exists( 'GFForms' ) ) {
		add_action( 'admin_notices', 'gf_drip_gravity_forms_required_notice' );
		return;
	}

	// Check Gravity Forms version
	if ( ! version_compare( GFCommon::$version, GF_DRIP_MIN_GF_VERSION, '>=' ) ) {
		add_action( 'admin_notices', 'gf_drip_gravity_forms_version_notice' );
		return;
	}

	// Load the add-on
	require_once GF_DRIP_PLUGIN_DIR . 'class-gf-drip.php';
	GFAddOn::register( 'GF_Drip' );
}
add_action( 'gform_loaded', 'gf_drip_init', 5 );

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
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Minimum required version, 2: Current version */
				esc_html__( 'Gravity Forms Drip Add-On requires Gravity Forms version %1$s or higher. You are running version %2$s.', 'gravityforms-drip' ),
				esc_html( GF_DRIP_MIN_GF_VERSION ),
				esc_html( GFCommon::$version )
			);
			?>
		</p>
	</div>
	<?php
}

