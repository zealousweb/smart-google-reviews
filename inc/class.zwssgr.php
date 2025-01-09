<?php
/**
 * ZWSSGR Class
 *
 * Handles the plugin functionality.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ZWSSGR' ) ) {

	/**
	 * The main ZWSSGR class
	 */
	class ZWSSGR {
		
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
				ZWSSGR()->admin = new ZWSSGR_Admin;
				ZWSSGR()->admin->action = new ZWSSGR_Admin_Action;
				ZWSSGR()->admin->filter = new ZWSSGR_Admin_Filter;	
			}

			ZWSSGR()->lib = new ZWSSGR_Lib;
				
		}
	}

	function ZWSSGR() {
		return ZWSSGR::instance();
	}

	new ZWSSGR();
}
