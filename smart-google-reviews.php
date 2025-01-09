<?php
/**
 * Plugin Name: Smart Google Reviews
 * Plugin URL: https://wordpress.org/plugin-url/
 * Description: The Smart Google Reviews enables users to easily embed Google Reviews on your WordPress site.
 * Version: 1.0.0
 * Author: ZealousWeb
 * Author URI: https://www.zealousweb.com/
 * Developer: The ZealousWeb Team
 * Developer E-Mail: support@zealousweb.com
 * Text Domain: zw-smart-google-reviews
 * Domain Path: /languages
 *
 * Copyright: © 2009-2020 ZealousWeb.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Basic plugin definitions
 *
 * @package Smart Google Reviews
 * @since 1.0.0
 */

if ( !defined( 'ZWSSGR_VERSION' ) ) {
	define( 'ZWSSGR_VERSION', '1.0.0' ); // Version of plugin
}

if ( !defined( 'ZWSSGR_FILE' ) ) {
	define( 'ZWSSGR_FILE', __FILE__ ); // Plugin File
}

if ( !defined( 'ZWSSGR_DIR' ) ) {
	define( 'ZWSSGR_DIR', dirname( __FILE__ ) ); // Plugin dir
}

if ( !defined( 'ZWSSGR_URL' ) ) {
	define( 'ZWSSGR_URL', plugin_dir_url( __FILE__ ) ); // Plugin url
}

if ( !defined( 'ZWSSGR_PLUGIN_BASENAME' ) ) {
	define( 'ZWSSGR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Plugin base name
}

if ( !defined( 'ZWSSGR_META_PREFIX' ) ) {
	define( 'ZWSSGR_META_PREFIX', 'zwssgr_' ); // Plugin metabox prefix
}

if ( !defined( 'ZWSSGR_PREFIX' ) ) {
	define( 'ZWSSGR_PREFIX', 'zwssgr' ); // Plugin prefix
}

/**
 * Initialize the main class
 */
if ( !function_exists( 'ZWSGR' ) ) {

	if ( is_admin() ) {
		require_once( ZWSSGR_DIR . '/inc/admin/class.' . ZWSSGR_PREFIX . '.admin.php' );
		require_once( ZWSSGR_DIR . '/inc/admin/class.' . ZWSSGR_PREFIX . '.admin.action.php' );
		require_once( ZWSSGR_DIR . '/inc/admin/class.' . ZWSSGR_PREFIX . '.admin.filter.php' );		
	}

	require_once( ZWSSGR_DIR . '/inc/lib/class.' . ZWSSGR_PREFIX . '.jwth.php' );	
	require_once( ZWSSGR_DIR . '/inc/lib/class.' . ZWSSGR_PREFIX . '.gmbi.php' );
	require_once( ZWSSGR_DIR . '/inc/lib/class.' . ZWSSGR_PREFIX . '.gmbdp.php' );
	require_once( ZWSSGR_DIR . '/inc/lib/api/class.' . ZWSSGR_PREFIX . '.zba.php' );
	require_once( ZWSSGR_DIR . '/inc/lib/class.' . ZWSSGR_PREFIX . '.lib.php' );

	//Initialize all the things.
	require_once( ZWSSGR_DIR . '/inc/class.' . ZWSSGR_PREFIX . '.php' );

}