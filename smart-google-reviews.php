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

if ( !defined( 'ZWSGR_VERSION' ) ) {
	define( 'ZWSGR_VERSION', '1.0.0' ); // Version of plugin
}

if ( !defined( 'ZWSGR_FILE' ) ) {
	define( 'ZWSGR_FILE', __FILE__ ); // Plugin File
}

if ( !defined( 'ZWSGR_DIR' ) ) {
	define( 'ZWSGR_DIR', dirname( __FILE__ ) ); // Plugin dir
}

if ( !defined( 'ZWSGR_URL' ) ) {
	define( 'ZWSGR_URL', plugin_dir_url( __FILE__ ) ); // Plugin url
}

if ( !defined( 'ZWSGR_PLUGIN_BASENAME' ) ) {
	define( 'ZWSGR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Plugin base name
}

if ( !defined( 'ZWSGR_META_PREFIX' ) ) {
	define( 'ZWSGR_META_PREFIX', 'zwsgr_' ); // Plugin metabox prefix
}

if ( !defined( 'ZWSGR_PREFIX' ) ) {
	define( 'ZWSGR_PREFIX', 'zwsgr' ); // Plugin prefix
}

/**
 * Initialize the main class
 */
if ( !function_exists( 'ZWSGR' ) ) {

	if ( is_admin() ) {
		require_once( ZWSGR_DIR . '/inc/admin/class.' . ZWSGR_PREFIX . '.admin.php' );
		require_once( ZWSGR_DIR . '/inc/admin/class.' . ZWSGR_PREFIX . '.admin.action.php' );
		require_once( ZWSGR_DIR . '/inc/admin/class.' . ZWSGR_PREFIX . '.admin.filter.php' );		
	}

	require_once( ZWSGR_DIR . '/inc/lib/class.' . ZWSGR_PREFIX . '.jwth.php' );	
	require_once( ZWSGR_DIR . '/inc/lib/class.' . ZWSGR_PREFIX . '.gmbi.php' );
	require_once( ZWSGR_DIR . '/inc/lib/class.' . ZWSGR_PREFIX . '.gmbdp.php' );
	require_once( ZWSGR_DIR . '/inc/lib/api/class.' . ZWSGR_PREFIX . '.zba.php' );
	require_once( ZWSGR_DIR . '/inc/lib/class.' . ZWSGR_PREFIX . '.lib.php' );

	//Initialize all the things.
	require_once( ZWSGR_DIR . '/inc/class.' . ZWSGR_PREFIX . '.php' );

}