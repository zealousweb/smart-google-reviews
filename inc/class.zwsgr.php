<?php
/**
 * ZWSGR Class
 *
 * Handles the plugin functionality.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ZWSGR' ) ) {

	/**
	 * The main ZWSGR class
	 */
	class ZWSGR {
		
		private static $_instance = null;

		public static function instance() {

			if ( is_null( self::$_instance ) )
				self::$_instance = new self();

			return self::$_instance;

		}

		function __construct() {

			add_action( 'setup_theme',   array( $this, 'action__setup_theme' ) );
			
		}

		function action__setup_theme() {
			
			if ( is_admin() ) {
				ZWSGR()->admin = new ZWSGR_Admin;
				ZWSGR()->admin->action = new ZWSGR_Admin_Action;
				ZWSGR()->admin->filter = new ZWSGR_Admin_Filter;	
			}

			ZWSGR()->lib = new ZWSGR_Lib;
				
		}
	}

	function ZWSGR() {
		return ZWSGR::instance();
	}

	new ZWSGR();
}
