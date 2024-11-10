<?php
/**
 * ZWSGR_BACKEND_API Class
 *
 * Handles the API functionality.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'ZWSGR_BACKEND_API' ) ) {
    
    class ZWSGR_BACKEND_API {

        private $client;

        private $jwt_handler;

        public function __construct( ) {

            // Instantiate the Google My Business Connector
            $zwsgr_gmb_connector = new Zwsgr_Google_My_Business_Initializer();

            // Access the Google Client through the connector
            $this->client = $zwsgr_gmb_connector->get_client();

            $zwsgr_jwt_handler = new ZWSGR_JWT_HANDLER();

            $this->jwt_handler = $zwsgr_jwt_handler->get_jwt_handler();

            add_action('rest_api_init', array($this, 'action__zwsgr_register_rest_routes'));

        }

        /**
         * Registers the REST API route for OAuth authentication.
         * 
         * This method sets up the /auth endpoint under the zwsgr-google/v1 namespace. 
         * The route is designed to handle POST requests and trigger the OAuth initiation process. 
         * A permission callback of '__return_true' is used, meaning no authentication is required to access this route.
         * 
         * @return void
         */
        public function action__zwsgr_register_rest_routes() {
            
            // A custom REST API route for initializing OAuth using the /auth endpoint
            register_rest_route('zwsgr/v1', '/auth', [
                'methods' => 'POST',
                'callback' => [$this, 'zwsgr_handle_rest_initiated_oauth'],
                'permission_callback' => '__return_true'
            ]);

            // A custom REST API route for getting a JWT token using the authorization code
            register_rest_route('zwsgr/v1', '/get-jwt-token', [
                'methods' => 'POST',
                'callback' => [$this, 'zwsgr_handle_jwt_token_request'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route('zwsgr/v1', '/get-access-token', [
                'methods' => 'POST',
                'callback' => [$this, 'zwsgr_handle_access_token_request'],
                'permission_callback' => '__return_true', // Set additional security if needed
            ]);

        }

        /**
         * Initiates the OAuth process for Google My Business authentication.
         * 
         * This method extracts user data from the incoming POST request, validates the data,
         * checks for existing OAuth data associated with the user, and creates or updates
         * the corresponding post in the WordPress database. It then generates an OAuth URL 
         * to begin the Google OAuth process.
         *
         * @return WP_REST_Response The response containing the authorization URL to redirect the user for OAuth.
         */
        public function zwsgr_handle_rest_initiated_oauth() {

            // Extract user information from the POST data, sanitize the inputs to prevent XSS
            $zwsgr_user_id       = isset($_POST['zwsgr_user_id'])       ? sanitize_text_field($_POST['zwsgr_user_id'])       : '';
            $zwsgr_user_email    = isset($_POST['zwsgr_user_email'])    ? sanitize_email($_POST['zwsgr_user_email'])         : '';
            $zwsgr_user_site_url = isset($_POST['zwsgr_user_site_url']) ? sanitize_text_field($_POST['zwsgr_user_site_url']) : '';

            // Validate that required fields are provided in the POST request
            if (empty($zwsgr_user_id) || empty($zwsgr_user_email) || empty($zwsgr_user_site_url)) {
                wp_send_json_error([
                    'code' => 'missing_required_fields',
                    'message' => 'Required data is missing: user_id, user_email, and site_url. Please provide all the necessary fields.',
                ], 400);
            }

            // Validate the email address format
            if (!is_email($zwsgr_user_email)) {
                
                // Log the error for internal tracking
                error_log('Invalid email provided: ' . $zwsgr_user_email);

                // Display an error message to the user for invalid email
                wp_send_json_error([
                    'code' => 'invalid_email',
                    'message' => 'The email address provided is invalid. Please provide a valid email.'
                ], 400); // 400 Bad Request

            }

            // Check if a oAuth data already exists with the same user_id, user_email, and site_url
            $zwsgr_oauth_id = get_posts(array(
                'post_type'      => 'zwsgr_oauth_data',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => array(
                    array(
                        'key'     => 'zwsgr_user_id',
                        'value'   => $zwsgr_user_id,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'zwsgr_user_email',
                        'value'   => $zwsgr_user_email,
                        'compare' => '='
                    ),
                    array(
                        'key'     => 'zwsgr_user_site_url',
                        'value'   => $zwsgr_user_site_url,
                        'compare' => '='
                    ),
                ),
                'fields'         => 'ids',
            ))[0] ?? null;

            // Prepare the oAuth Data to save the OAuth details
            $zwsgr_oauth_data = array(
                'post_title'   => $zwsgr_user_id .' - '. $zwsgr_user_site_url,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'zwsgr_oauth_data',
                'meta_input'   => array(
                    'zwsgr_user_id'       => $zwsgr_user_id,
                    'zwsgr_user_email'    => $zwsgr_user_email,
                    'zwsgr_user_site_url' => $zwsgr_user_site_url,
                    'zwsgr_oauth_status'  => 'in_progress',
                ),
            );

             // Check if the oauth data already exists, and update or insert accordingly
            if ($zwsgr_oauth_id) {
                // Update the existing post if it was found
                $zwsgr_oauth_data['ID'] = $zwsgr_oauth_id;
                $zwsgr_oauth_id = wp_update_post($zwsgr_oauth_data);

                // Handle any errors during the post update
                if (is_wp_error($zwsgr_oauth_id)) {
                    wp_send_json_error([
                        'code'    => 'oauth_update_failed',
                        'message' => 'Failed to update OAuth data. Please try again later or contact support.'
                    ], 500); // 500 Internal Server Error
                }

            } else {
                
                // Insert a new post if no existing post was found
                $zwsgr_oauth_id = wp_insert_post($zwsgr_oauth_data);

                // Handle any errors during the post insertion
                if (is_wp_error($zwsgr_oauth_id)) {
                    wp_send_json_error([
                        'code'    => 'oauth_creation_failed',
                        'message' => 'Failed to create OAuth data. Please try again later or contact support.'
                    ], 500); // 500 Internal Server Error
                }

            }

            // Create the state parameter with all required information
            $zwsgr_gmb_state = urlencode(json_encode([
                'zwsgr_user_id'       => $zwsgr_user_id,
                'zwsgr_user_email'    => $zwsgr_user_email,
                'zwsgr_user_site_url' => $zwsgr_user_site_url
            ]));

            // Set the auth URL with the 'state' parameter
            $this->client->setState($zwsgr_gmb_state);
            $zwsgr_oauth_url = $this->client->createAuthUrl();

            // Send a success response with the auth_url in JSON format
            wp_send_json_success([
                'code'    => 'auth_url_generated',
                'message' => 'The authentication URL has been successfully generated. Please visit the link to authenticate.',
                'data'    => [
                    'zwsgr_oauth_url' => $zwsgr_oauth_url
                ]
            ]);
    
        }


        public function zwsgr_handle_jwt_token_request($zwsgr_request) {

            $zwsgr_auth_code = sanitize_text_field($zwsgr_request->get_param('zwsgr_auth_code'));
        
            // Find the post ID associated with this auth code
            $zwsgr_oauth_id = get_posts([
                'post_type' => 'zwsgr_oauth_data',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'zwsgr_auth_code', 
                        'value' => $zwsgr_auth_code, 
                        'compare' => '='
                    ],
                    [
                        'key' => 'zwsgr_auth_code_expiry', 
                        'value' => time(), 
                        'compare' => '>='
                    ]
                ]
            ])[0] ?? null;
        
            if ($zwsgr_oauth_id) {

                // Retrieve the JWT token
                $zwsgr_jwt_token = get_post_meta($zwsgr_oauth_id, 'zwsgr_jwt_token', true);
        
                // Invalidate the authorization code after use
                delete_post_meta($zwsgr_oauth_id, 'zwsgr_auth_code');
                delete_post_meta($zwsgr_oauth_id, 'zwsgr_auth_code_expiry');
        
                // Return the JWT token as JSON on success
                wp_send_json_success([
                    'code'    => 'jwt_token_granted',
                    'message' => 'The JWT token has been successfully granted.',
                    'data'    => [
                        'zwsgr_jwt_token' => $zwsgr_jwt_token
                    ]
                ]);

            } else {

                // Invalid or expired authorization code
                wp_send_json_error([
                    'code'    => 'invalid_authorization_code',
                    'message' => 'The authorization code is either invalid or expired. Please try again with a valid code.'
                ], 401);

            }
            
        }

        function zwsgr_handle_access_token_request($zwsgr_request) {

            $zwsgr_jwt_token = sanitize_text_field($zwsgr_request->get_param('zwsgr_jwt_token'));
        
            // Decode and verify the JWT token
            $zwsgr_jwt_payload = $this->jwt_handler->zwsgr_verify_jwt_token($zwsgr_jwt_token);

            if (!$zwsgr_jwt_payload) {
                wp_send_json_error([
                    'code' => 'invalid_jwt_token',
                    'message' => 'The JWT token is invalid or has expired. Please authenticate again to continue.',
                ], 401);
            }
        
            // Retrieve the post ID associated with the user ID in the JWT payload
            $zwsgr_oauth_id = get_posts([
                'post_type' => 'zwsgr_oauth_data',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'zwsgr_user_id', 
                        'value' => $zwsgr_jwt_payload['zwsgr_user_id'], 
                        'compare' => '='
                    ],
                    [
                        'key' => 'zwsgr_user_email', 
                        'value' => $zwsgr_jwt_payload['zwsgr_user_email'], 
                        'compare' => '='
                    ]
                ]
            ])[0] ?? null;
        
            if (!$zwsgr_oauth_id) {
                wp_send_json_error([
                    'code' => 'oauth_connection_missing',
                    'message' => 'OAuth connection not found. Please verify your connection settings or contact support for assistance.',
                ], 404);
            }

            // Retrieve the refresh token from the database or another secure source
            $zwsgr_gmb_refresh_token = get_post_meta($zwsgr_oauth_id, 'zwsgr_gmb_refresh_token', true);

            if (!$zwsgr_gmb_refresh_token) {
                wp_send_json_error([
                    'code'    => 'missing_refresh_token',
                    'message' => 'Refresh token not found. Please authenticate again.',
                ], 400); // Bad Request
            }
            
            // Attempt to refresh the token
            try {
                $this->client->refreshToken($zwsgr_gmb_refresh_token);
            } catch (Exception $e) {
                wp_send_json_error([
                    'code'    => 'token_refresh_failed',
                    'message' => 'Failed to refresh the token. Please try again later.',
                    'error'   => $e->getMessage()
                ], 500); // 500 Internal Server Error
            }

            // Retrieve the new access token
            $zwsgr_new_access_token = $this->client->getAccessToken();
        
            if (!$zwsgr_new_access_token || !isset($zwsgr_new_access_token['access_token'])) {
                wp_send_json_error([
                    'code' => 'token_refresh_failed',
                    'message' => 'Failed to refresh access token. Please try again or contact support.',
                ], 401);
            }
        
            // Update the access token in post meta
            update_post_meta($zwsgr_oauth_id, 'zwsgr_gmb_access_token', $zwsgr_new_access_token['access_token']);

            // Return success response with the new access token
            wp_send_json_success([
                'message' => 'Access token refreshed successfully.',
                'data'    => [
                    'access_token' => $zwsgr_new_access_token['access_token']
                ]
            ], 200);

        }

    }

    // Instantiate the class
    new ZWSGR_BACKEND_API();

}