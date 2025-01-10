<?php
/**
 * Zwssgr_Backend_API Class
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

if ( ! class_exists( 'Zwssgr_Backend_API' ) ) {
    
    class Zwssgr_Backend_API {

        private $client;

        private $jwt_handler;

        public function __construct( ) {

            // Instantiate the Google My Business Connector
            $zwsgr_gmb_connector = new Zwssgr_Google_My_Business_Initializer();

            // Access the Google Client through the connector
            $this->client = $zwsgr_gmb_connector->get_client();

            $zwsgr_jwt_handler = new Zwssgr_Jwt_Handler();

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
            register_rest_route('zwssgr/v1', '/auth', [
                'methods' => 'POST',
                'callback' => [$this, 'zwssgr_handle_rest_initiated_oauth'],
                'permission_callback' => '__return_true'
            ]);

            // A custom REST API route for getting a JWT token using the authorization code
            register_rest_route('zwssgr/v1', '/get-jwt-token', [
                'methods' => 'POST',
                'callback' => [$this, 'zwssgr_handle_jwt_token_request'],
                'permission_callback' => '__return_true',
            ]);

            // A custom REST API route for getting access token using jwt token
            register_rest_route('zwssgr/v1', '/get-access-token', [
                'methods' => 'POST',
                'callback' => [$this, 'zwssgr_handle_access_token_request'],
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
        public function zwssgr_handle_rest_initiated_oauth($zwssgr_request) {

            // Extract user information from the POST data, sanitize the inputs to prevent XSS
            $zwssgr_user_name     = isset($zwssgr_request['zwssgr_user_name'])     ? sanitize_text_field($zwssgr_request['zwssgr_user_name'])     : '';
            $zwssgr_user_site_url = isset($zwssgr_request['zwssgr_user_site_url']) ? sanitize_text_field($zwssgr_request['zwssgr_user_site_url']) : '';

            // Validate that required fields are provided in the POST request
            if ( empty($zwssgr_user_name) || empty($zwssgr_user_site_url)) {

                $zwssgr_response = new WP_REST_Response([
                    'error' => 'missing_required_fields',
                    'message' => 'Required data is missing: user_name and site_url. Please provide all the necessary fields.'
                ]);

                $zwssgr_response->set_status(400);

                return $zwssgr_response;

            }

            // Check if a oAuth data already exists with the same site_url
            $zwssgr_oauth_id = get_posts(array(
                'post_type'       => 'zwssgr_oauth_data',
                'posts_per_page'  => 1,
                'post_status'     => 'publish',
                'meta_query'      => array(
                    array(
                        'key'     => 'zwssgr_user_site_url',
                        'value'   => $zwssgr_user_site_url,
                        'compare' => '='
                    ),
                ),
                'fields'         => 'ids',
            ))[0] ?? null;

            // Prepare the oAuth Data to save the OAuth details
            $zwssgr_oauth_data = array(
                'post_title'   => $zwssgr_user_name .' - '. $zwssgr_user_site_url,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'zwssgr_oauth_data',
                'meta_input'   => array(
                    'zwssgr_user_name'     => $zwssgr_user_name,
                    'zwssgr_user_site_url' => $zwssgr_user_site_url,
                    'zwssgr_oauth_status'  => 'IN_PROGRESS',
                ),
            );

             // Check if the oauth data already exists, and update or insert accordingly
            if ($zwssgr_oauth_id) {

                // Update the existing post if it was found
                $zwssgr_oauth_data['ID'] = $zwssgr_oauth_id;
                $zwssgr_oauth_id         = wp_update_post($zwssgr_oauth_data);

                // Handle any errors during the post update
                if (is_wp_error($zwssgr_oauth_id)) {

                    $zwssgr_response = new WP_REST_Response([
                        'error' => 'oauth_update_failed',
                        'message' => 'Failed to update OAuth data. Please try again later or contact support.'
                    ]);
    
                    $zwssgr_response->set_status(500);
    
                    return $zwssgr_response;

                }

            } else {
                
                // Insert a new post if no existing post was found
                $zwssgr_oauth_id = wp_insert_post($zwssgr_oauth_data);

                // Handle any errors during the post insertion
                if (is_wp_error($zwssgr_oauth_id)) {

                    $zwssgr_response = new WP_REST_Response([
                        'error' => 'oauth_creation_failed',
                        'message' => 'Failed to create OAuth data. Please try again later or contact support.'
                    ]);
    
                    $zwssgr_response->set_status(500);
    
                    return $zwssgr_response;

                }

            }

            // Create the state parameter with all required information
            $zwssgr_gmb_state = urlencode(json_encode([
                'zwssgr_user_name'     => $zwssgr_user_name,
                'zwssgr_user_site_url' => $zwssgr_user_site_url
            ]));

            // Set the auth URL with the 'state' parameter
            $this->client->setState($zwssgr_gmb_state);

            $zwssgr_oauth_url = $this->client->createAuthUrl();

            // Check if the OAuth URL was generated successfully
            if ($zwssgr_oauth_url) {

                $zwssgr_response = new WP_REST_Response([
                    'zwssgr_oauth_url' => $zwssgr_oauth_url
                ]);

                $zwssgr_response->set_status(200);

                return $zwssgr_response;

            } else {

                $zwssgr_response = new WP_REST_Response([
                    'error' => 'auth_url_generaton_error',
                    'message' => 'Failed to generate the OAuth URL. Please try again later.'
                ]);

                $zwssgr_response->set_status(400);

                return $zwssgr_response;

            }
    
        }

        /**
         * Handles the request to generate a JWT token using the provided authorization code.
         *
         * This function validates the provided authorization code, checks if it's associated with a valid OAuth ID,
         * retrieves the JWT token if valid, invalidates the authorization code, and returns the JWT token in the response.
         *
         * @param WP_REST_Request $zwssgr_request The request object containing the authorization code.
         * @return void JSON response with either the JWT token or an error message.
         */
        public function zwssgr_handle_jwt_token_request($zwssgr_request) {

            // Sanitize the authorization code received from the request to prevent XSS
            $zwssgr_auth_code = sanitize_text_field($zwssgr_request->get_param('zwssgr_auth_code'));
        
            // Find the oAuth ID associated with this authcode
            $zwssgr_oauth_id = get_posts([
                'post_type' => 'zwssgr_oauth_data',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'zwssgr_auth_code', 
                        'value' => $zwssgr_auth_code, 
                        'compare' => '='
                    ],
                    [
                        'key' => 'zwssgr_auth_code_expiry', 
                        'value' => time(), 
                        'compare' => '>='
                    ]
                ]
            ])[0] ?? null;
        
            if ($zwssgr_oauth_id) {

                // Retrieve the JWT token
                $zwssgr_jwt_token = get_post_meta($zwssgr_oauth_id, 'zwssgr_jwt_token', true);
        
                // Invalidate the authorization code after use
                delete_post_meta($zwssgr_oauth_id, 'zwssgr_auth_code');
                delete_post_meta($zwssgr_oauth_id, 'zwssgr_auth_code_expiry');
        
                // Check if the JWT token is present
                if ($zwssgr_jwt_token) {

                    // Return the JWT token as JSON on success
                    $zwssgr_response = new WP_REST_Response([
                        'zwssgr_jwt_token' => $zwssgr_jwt_token
                    ]);

                    $zwssgr_response->set_status(200);
                    
                    return $zwssgr_response;

                } else {

                    // Return an error if the JWT token is not found
                    $zwssgr_response = new WP_REST_Response([
                        'error' => 'jwt_token_missing',
                        'message' => 'JWT token not found for the provided OAuth ID.'
                    ]);

                    $zwssgr_response->set_status(400);
                    
                    return $zwssgr_response;
                    
                }

            } else {

                $zwssgr_response = new WP_REST_Response([
                    'error' => 'invalid_authorization_code',
                    'message' => 'The authorization code is either invalid or expired. Please try again with a valid code.'
                ]);
                
                $zwssgr_response->set_status(401);
                
                return $zwssgr_response;

            }
            
        }

        /**
         * Handles the request to refresh the access token using a JWT token.
         *
         * This function validates the provided JWT token, verifies its integrity, and ensures that it's not expired.
         * It retrieves the associated OAuth ID for the user, then uses the stored refresh token to refresh the access token
         * from the Google My Business API. If successful, the new access token is saved in the database and returned in the response.
         * If any step fails, an appropriate error message is returned.
         *
         * @param WP_REST_Request $zwssgr_request The request object containing the JWT token to be validated.
         * @return void JSON response with either the new access token or an error message.
         */
        function zwssgr_handle_access_token_request($zwssgr_request) {

            // Sanitize and retrieve the JWT token from the request
            $zwssgr_jwt_token = sanitize_text_field($zwssgr_request->get_param('zwssgr_jwt_token'));
        
            // Decode and verify the JWT token to extract the payload
            $zwssgr_jwt_payload = $this->jwt_handler->zwssgr_verify_jwt_token($zwssgr_jwt_token);

            // If the JWT token is invalid or expired, return an error
            if (!$zwssgr_jwt_payload) {

                $zwssgr_response = new WP_REST_Response([
                    'error' => 'invalid_jwt_token',
                    'message' => 'The JWT token is invalid or has expired. Please authenticate again to continue.'
                ]);

                $zwssgr_response->set_status(401);
                
                return $zwssgr_response;

            }
        
            // Retrieve the oAuth ID associated with the user ID in the JWT payload
            $zwssgr_oauth_id = get_posts([
                'post_type' => 'zwssgr_oauth_data',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'zwssgr_user_site_url', 
                        'value' => $zwssgr_jwt_payload['zwssgr_user_site_url'], 
                        'compare' => '='
                    ]
                ]
            ])[0] ?? null;
        
            // If no OAuth connection is found, return an error
            if (!$zwssgr_oauth_id) {

                $zwssgr_response = new WP_REST_Response([
                    'error' => 'oauth_connection_missing',
                    'message' => 'OAuth connection not found. Please verify your connection settings or contact support for assistance.'
                ]);

                $zwssgr_response->set_status(404);
                
                return $zwssgr_response;

            }

            // Retrieve the stored refresh token from the database
            $zwssgr_gmb_refresh_token = get_post_meta($zwssgr_oauth_id, 'zwssgr_gmb_refresh_token', true);

            // If no refresh token is found, return an error
            if (!$zwssgr_gmb_refresh_token) {

                $zwssgr_response = new WP_REST_Response([
                    'error' => 'missing_refresh_token',
                    'message' => 'Refresh token not found. Please authenticate again.'
                ]);

                $zwssgr_response->set_status(400);
                
                return $zwssgr_response;

            }
            
            // Attempt to refresh the access token using the stored refresh token
            try {
                $this->client->refreshToken($zwssgr_gmb_refresh_token);
            } catch (Exception $e) {

                // If the token refresh fails, return an error
                $zwssgr_response = new WP_REST_Response([
                    'error' => 'token_refresh_failed',
                    'message' => $e->getMessage()
                ]);

                $zwssgr_response->set_status(500);
                
                return $zwssgr_response;

            }

            // Retrieve the new access token from the client
            $zwssgr_new_access_token = $this->client->getAccessToken();
        
            // If the new access token is not valid, return an error
            if (!$zwssgr_new_access_token || !isset($zwssgr_new_access_token['access_token'])) {

                // If the token refresh fails, return an error
                $zwssgr_response = new WP_REST_Response([
                    'error' => 'token_refresh_failed',
                    'message' => 'Failed to refresh access token. Please try again or contact support.'
                ]);

                $zwssgr_response->set_status(401);
                
                return $zwssgr_response;

            }

            // Return a success response with the new access token
            $zwssgr_response = new WP_REST_Response([
                'access_token' => $zwssgr_new_access_token['access_token']
            ]);

            $zwssgr_response->set_status(200);
            
            return $zwssgr_response;

        }

    }

    // Instantiate the class
    new Zwssgr_Backend_API();

}