<?php
/**
 * ZWSSGR_Admin_Action Class
 *
 * Handles the admin functionality.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

if ( !class_exists( 'ZWSSGR_Admin_Action' ) ){

	/**
	 *  The ZWSSGR_Admin_Action Class
	 */
	class ZWSSGR_Admin_Action {

		function __construct()  {

			add_action('add_meta_boxes', array($this, 'zwssgr_action__add_oauth_meta_box'));
			add_action('init', array($this, 'zwssgr_register_oauth_connections_cpt'));
		}

		// Register the OAuth Connection Details meta box
		function zwssgr_action__add_oauth_meta_box() {
			add_meta_box(
				'zwssgr_oauth_meta_box',
				__('OAuth Details', 'zw-smart-google-reviews'),
				array($this, 'zwssgr_display_oauth_meta_box__callback'),
				'zwssgr_oauth_data', // Change this to your custom post type if needed
				'normal',
				'high'
			);
		}

		/**
		 * Displays OAuth connection details in the meta box.
		 *
		 * This function retrieves and displays metadata related to 
		 * the OAuth connection for a given post. The metadata includes:
		 * - Personal information: user email, name, and site URL.
		 * - Access information: access token, refresh token, JWT secret, JWT token, and OAuth status.
		 *
		 * All fields are displayed as read-only inputs for informational purposes.
		 *
		 * @param WP_Post $zwssgr_oauth_post The current post object.
		 */
		function zwssgr_display_oauth_meta_box__callback($zwssgr_oauth_post) {

			// Retrieve stored meta values for OAuth connection details
			$zwssgr_user_email    	 = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_user_email', true);
			$zwssgr_user_name 	     = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_user_name', true);
			$zwssgr_user_site_url 	 = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_user_site_url', true);
			$zwssgr_gmb_access_token  = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_gmb_access_token', true);
			$zwssgr_gmb_refresh_token = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_gmb_refresh_token', true);
			$zwssgr_jwt_secret 		 = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_jwt_secret', true);
			$zwssgr_jwt_token 		 = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_jwt_token', true);
			$zwssgr_oauth_status 	 = get_post_meta($zwssgr_oauth_post->ID, 'zwssgr_oauth_status', true);

			// Output the HTML for the meta box content, displaying the OAuth connection details
			echo '<table class="form-table">
				<tr>
					<th colspan="2"> <strong>' . __('Personal Information', 'zw-smart-google-reviews') . '</strong> </th>
				</tr>
				<tr>
					<th><label for="zwssgr_user_email">' . __('Email', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_user_email) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th><label for="zwssgr_user_name">' . __('User Name', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_user_name) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th><label for="zwssgr_user_site_url">' . __('Site URL', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_user_site_url) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th colspan="2">
						<strong>' . __('Access Information', 'zw-smart-google-reviews') . '</strong>
					</th>
				</tr>
				<tr>
					<th><label for="zwssgr_gmb_access_token">' . __('Access Token', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_gmb_access_token) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th><label for="zwssgr_gmb_refresh_token">' . __('Refresh Token', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_gmb_refresh_token) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th><label for="zwssgr_jwt_secret">' . __('JWT Secret', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_jwt_secret) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th><label for="zwssgr_jwt_token">' . __('JWT Token', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_jwt_token) . '" readonly class="regular-text" style="width:100%;" /></td>
				</tr>
				<tr>
					<th><label for="zwssgr_oauth_status">' . __('OAuth Status', 'zw-smart-google-reviews') . '</label></th>
					<td><input type="text" value="' . esc_attr($zwssgr_oauth_status) . '" readonly class="regular-text" style="width:15%;margin-right: 15px;" /></td>
				</tr>
			</table>';
		}

		/**
		 * Registers the custom post type for OAuth Connections.
		 *
		 * Creates a secure, non-public custom post type to store OAuth data, such as 
		 * user details, access tokens, and refresh tokens, for administrative use only.
		 *
		 * Key Features:
		 * - Non-public and excluded from search.
		 * - Accessible via the WordPress admin menu.
		 * - Supports only 'read' capability for restricted access.
		 *
		 * @return void
		 */
		function zwssgr_register_oauth_connections_cpt() {

			// Define labels for the custom post type in different contexts (e.g., menu, admin bar, etc.)
			$labels = array(
				'name'                  => _x('OAuth Connections', 'zwssgr-oauth-connections', 'zw-smart-google-reviews'),
				'singular_name'         => _x('OAuth Connection', 'zwssgr-oauth-connections', 'zw-smart-google-reviews'),
				'menu_name'             => _x('OAuth Connections', 'admin menu', 'zw-smart-google-reviews'),
				'name_admin_bar'        => _x('OAuth Connection', 'add new on admin bar', 'zw-smart-google-reviews'),
				'add_new'               => _x('Add New', 'oauth connection', 'zw-smart-google-reviews'),
				'add_new_item'          => __('Add New OAuth Connection', 'zw-smart-google-reviews'),
				'new_item'              => __('New OAuth Connection', 'zw-smart-google-reviews'),
				'edit_item'             => __('Edit OAuth Connection', 'zw-smart-google-reviews'),
				'view_item'             => __('View OAuth Connection', 'zw-smart-google-reviews'),
				'all_items'             => __('All OAuth Connections', 'zw-smart-google-reviews'),
				'search_items'          => __('Search OAuth Connections', 'zw-smart-google-reviews'),
				'not_found'             => __('No OAuth Connections found.', 'zw-smart-google-reviews'),
				'not_found_in_trash'    => __('No OAuth Connections found in Trash.', 'zw-smart-google-reviews')
			);

			// Define the arguments for registering the custom post type		
			$args = array(
				'label'                 => __('OAuth Connections', 'zw-smart-google-reviews'),
				'labels'                => $labels,
				'description'           => 'Store OAuth connection data securely.',
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => true,
				'delete_with_user'      => false,
				'show_in_rest'          => false,
				'show_in_menu'          => true, 
				'menu_position' 		=> 79,
				'query_var'             => false,
				'rewrite'               => false,
				'capability_type'       => 'post',
				'has_archive'           => false,
				'show_in_nav_menus'     => false,
				'exclude_from_search'   => true,
				'capabilities'          => array(
					'read'                => true
				),
				'map_meta_cap'          => true,
				'hierarchical'          => false,
				'supports'              => array('title'),
			);
		
			// Register the custom post type with WordPress
			register_post_type('zwssgr_oauth_data', $args);
			
		}

	}
}
