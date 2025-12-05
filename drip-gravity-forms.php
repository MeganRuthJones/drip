<?php
/**
 * Plugin Name: Gravity Forms Drip Add-On
 * Plugin URI: https://example.com/gravity-forms-drip
 * Description: Integrates Gravity Forms with Drip email marketing platform
 * Version: 1.0.1
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
define( 'GF_DRIP_VERSION', '1.0.1' );
define( 'GF_DRIP_MIN_GF_VERSION', '2.5' );
define( 'GF_DRIP_PLUGIN_FILE', __FILE__ );
define( 'GF_DRIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF_DRIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Loads the Gravity Forms Drip Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0.0
 */
class GF_Drip_Bootstrap {

	/**
	 * Loads the required files.
	 *
	 * @since  1.0.0
	 */
	public static function load_addon() {
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

		// Requires the class file.
		// The class file will call GFForms::include_feed_addon_framework() itself
		require_once plugin_dir_path( __FILE__ ) . 'class-gf-drip.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'GF_Drip' );
	}
}

// After GF is loaded, load the add-on.
add_action( 'gform_loaded', array( 'GF_Drip_Bootstrap', 'load_addon' ), 5 );

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
