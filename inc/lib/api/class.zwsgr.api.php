<?php
/**
 * ZWSGR_GMB_API Class
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

if ( ! class_exists( 'ZWSGR_GMB_API' ) ) {
    class ZWSGR_GMB_API {

        private $zwsgr_access_token;
        private $zwsgr_base_url;
        private $zwsgr_api_version = 'v4';

        public function __construct( $zwsgr_access_token ) {

            $this->zwsgr_access_token = $zwsgr_access_token;
            
            $this->zwsgr_base_url = "https://mybusiness.googleapis.com/{$this->zwsgr_api_version}/";

            add_action('wp_ajax_zwsgr_fetch_oauth_url', array($this, 'zwsgr_fetch_oauth_url'));
            add_action('wp_ajax_zwsgr_add_update_review_reply', array($this, 'zwsgr_add_update_review_reply'));
            add_action('wp_ajax_zwsgr_delete_review_reply', array($this, 'zwsgr_delete_review_reply'));
        }

        /**
         * Makes an API request to the specified endpoint with optional parameters, method, version, and base URL.
         * Ensures the access token is valid before making the request.
         * 
         * @param string $zwsgr_api_endpoint The API endpoint to request.
         * @param array  $zwsgr_api_params   Optional query parameters for GET requests or body parameters for POST requests.
         * @param string $method             The HTTP method to use ('GET', 'POST', 'DELETE', etc.), default is 'GET'.
         * @param string $zwsgr_api_version  The API version to use (default: 'v4').
         * @param string $base_url           Optional base URL to override the default API URL.
         * @return array The decoded JSON response from the API.
         * @throws Exception If the API request fails or returns an error.
         */
        private function zwsgr_api_request( $zwsgr_api_endpoint, $zwsgr_api_params = [], $zwsgr_api_method = 'GET', $zwsgr_api_version = 'v4', $zwsgr_base_url = '' ) {

            // Construct the API URL
            $zwsgr_api_url = $this->build_api_url( $zwsgr_api_endpoint, $zwsgr_api_version, $zwsgr_base_url );

            $zwsgr_api_args = [
                'method'  => strtoupper($zwsgr_api_method),
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->zwsgr_access_token,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 15,
            ];

            if ( $zwsgr_api_method === 'GET' && ! empty( $zwsgr_api_params ) ) {
                $zwsgr_api_url = add_query_arg( $zwsgr_api_params, $zwsgr_api_url );
            } elseif ( in_array( $zwsgr_api_method, ['POST', 'PUT', 'DELETE'] ) && ! empty( $zwsgr_api_params ) ) {
                $zwsgr_api_args['body'] = json_encode( $zwsgr_api_params );
                $zwsgr_api_args['headers']['Content-Type'] = 'application/json';
            }

            try {

                // Make the API request
                $zwsgr_api_response = wp_remote_request( $zwsgr_api_url, $zwsgr_api_args );
        
                // Check if there was an error with the request
                if ( is_wp_error( $zwsgr_api_response ) ) {
                    throw new Exception( 'Request failed: ' . $zwsgr_api_response->get_error_message() );
                }
        
                // Check the response status code
                $zwsgr_api_status_code = wp_remote_retrieve_response_code( $zwsgr_api_response );

                if ( $zwsgr_api_status_code !== 200 ) {
                    throw new Exception( "API Request failed with response code: $zwsgr_api_status_code" );
                }
        
                // Get the response body and decode it
                $zwsgr_api_response_body = wp_remote_retrieve_body( $zwsgr_api_response );
                return json_decode( $zwsgr_api_response_body, true );
        
            } catch (Exception $e) {

                if (defined('DOING_AJAX') && DOING_AJAX) {
                    
                    // For AJAX requests, send a JSON error response
                    wp_send_json_error([
                        'status'  => 'error',
                        'message' => $e->getMessage(),
                    ]);

                } elseif (is_admin()) {
                    
                    // For admin requests, display a WordPress admin notice
                    add_action('admin_notices', function() use ($e) {
                        echo "<div class='notice notice-error'><p>Error: {$e->getMessage()}</p></div>";
                    });

                } else {

                    // For other contexts, log the error
                    error_log("API Error: {$e->getMessage()}");
                    
                }

                return false;

            }

        }

        /**
         * Helper function to build the full API URL from base URL, version, and endpoint.
         * 
         * @param string $endpoint    The API endpoint.
         * @param string $api_version The API version to use.
         * @param string $base_url    The base URL to use; defaults to Google My Business API if not provided.
         * @return string The constructed API URL.
         */
        private function build_api_url( $endpoint, $api_version, $base_url = '' ) {
            // Set default base URL if not provided
            $base_url = $base_url ?: "https://mybusiness.googleapis.com/{$api_version}/";

            // Concatenate the base URL and endpoint, ensuring correct formatting
            return rtrim( $base_url, '/' ) . '/' . ltrim( $endpoint, '/' );
        }

        public function set_access_token($zwsgr_access_token) {
            $this->zwsgr_access_token = $zwsgr_access_token;
        }

        /**
         * Retrieves the valid access token, refreshing it if expired or not available.
         *
         * @return string The valid access token.
         * @throws Exception if token retrieval or refresh fails.
         */
        public function zwsgr_get_access_token() {

            // Check if the access token is valid, refresh if necessary
            if (!$this->zwsgr_is_access_token_valid()) {
                // Refresh the token if it's not valid
                $this->zwsgr_refresh_access_token();
            }

            return $this->zwsgr_access_token;
        }

        /**
         * Checks if a valid access token exists in the transient.
         *
         * @return bool True if the access token is found and valid, false otherwise.
         */
        public function zwsgr_is_access_token_valid() {

            $zwsgr_access_token = get_transient('zwsgr_access_token');
            
            if ($zwsgr_access_token !== false) {
                $this->zwsgr_access_token = $zwsgr_access_token;
                return true;
            }
            
            return false;
        }

        /**
         * Fetches and stores a new access token using the refresh token.
         * 
         * @return string The new access token.
         * @throws Exception if the token refresh fails.
         */
        public function zwsgr_refresh_access_token() {

            // Get 'auth_code' and 'consent' from function params parameters
            $zwsgr_jwt_token = get_option('zwsgr_jwt_token');

            // Prepare the payload for the request
            $zwsgr_payload_data = [
                'zwsgr_jwt_token' => $zwsgr_jwt_token
            ];

            // Make the API request to get oauth URl.
            $zwsgr_response = $this->zwsgr_api_request( 'zwsgr/v1/get-access-token', $zwsgr_payload_data, 'POST', '', 'https://siteproofs.com/projects/zealousweb/plugindev/reviews-plugin/wp-json/');

            // Check if the response is successful and contains the access token.
            if (!empty($zwsgr_response['success']) && $zwsgr_response['success'] == 1 && !empty($zwsgr_response['data']['data']['access_token'])) {
                
                $access_token = $zwsgr_response['data']['data']['access_token'];
                
                // Store the new access token in a transient for future use.
                set_transient('zwsgr_access_token', $access_token, 3600); // Store for 1 hour or as needed.
                
                // Return the access token.
                return $access_token;

            }

        }

        /**
         * Retrieves a list of Google My Business accounts.
         *
         * @param string|null $zwsgr_page_token Optional page token for paginated results.
         * 
         * @return array The list of accounts.
         * @throws Exception if the API request fails.
         */
        public function zwsgr_get_accounts( $zwsgr_page_token = null ) {

            // Add page token to parameters if provided.
            $zwsgr_api_params = $zwsgr_page_token ? [ 'pageToken' => $zwsgr_page_token ] : [];
            
            // Make the API request to the 'accounts' endpoint.
            return $this->zwsgr_api_request( 'accounts', $zwsgr_api_params, 'GET', 'v1' );
        }

        /**
         * Retrieves locations for a specific Google My Business account.
         *
         * @param string      $zwsgr_account_id   The ID of the account to fetch locations for.
         * @param string|null $zwsgr_page_token   Optional page token for paginated results.
         * 
         * @return array The list of locations.
         * @throws Exception if the API request fails.
         */
        public function zwsgr_get_locations( $zwsgr_account_id, $zwsgr_page_token = null ) {

            // Define the endpoint with account ID and query parameters.
            $zwsgr_api_endpoint = "accounts/{$zwsgr_account_id}/locations?readMask=name,storeCode&pageSize=50";
            $zwsgr_api_params   = $zwsgr_page_token ? [ 'pageToken' => $zwsgr_page_token ] : [];

            // Make the API request to fetch locations.
            return $this->zwsgr_api_request( $zwsgr_api_endpoint, $zwsgr_api_params, 'GET', 'v1' );

        }

        /**
         * Retrieves reviews for a specific location in a Google My Business account.
         *
         * @param string      $zwsgr_api_endpoint The account ID or other relevant endpoint part.
         * @param string      $zwsgr_location_id  The ID of the location to fetch reviews for.
         * @param string|null $zwsgr_page_token   Optional page token for paginated results.
         * 
         * @return array The list of reviews.
         * @throws Exception if the API request fails.
         */
        public function zwsgr_get_reviews( $zwsgr_account_id, $zwsgr_location_id, $zwsgr_page_token = null ) {

            // Define the reviews API endpoint using account and location IDs.
            $zwsgr_api_endpoint = "accounts/{$zwsgr_account_id}/locations/{$zwsgr_location_id}/reviews";
        
            // Prepare query parameters for pagination if a page token is provided.
            $zwsgr_api_params = [];
            if ($zwsgr_page_token) {
                $zwsgr_api_params['pageToken'] = $zwsgr_page_token;
            }

            // Make the API request to fetch reviews.
            return $this->zwsgr_api_request( $zwsgr_api_endpoint, $zwsgr_api_params, 'GET');
        }

        /**
         * Adds or updates a reply to a specific Google My Business review using the review ID.
         *
         * @param string $zwsgr_account_id The account ID associated with the Google My Business account.
         * @param string $zwsgr_location_id The location ID for the specific business location.
         * @param string $zwsgr_review_id The ID of the review to reply to.
         * @param string $zwsgr_reply_comment The text of the reply to add or update.
         * @return array The decoded JSON response from the API.
         * @throws Exception If the API request fails or returns an error.
         */
        public function zwsgr_add_update_review_reply( ) {

            // Check nonce and AJAX referer
            check_ajax_referer('zwsgr_add_update_reply_nonce', 'security');

            // Retrieve POST values
            $zwsgr_reply_comment  = isset($_POST['zwsgr_reply_comment']) ? sanitize_textarea_field($_POST['zwsgr_reply_comment']) : '';
            $zwsgr_account_number = isset($_POST['zwsgr_account_number']) ? sanitize_text_field($_POST['zwsgr_account_number']) : '';
            $zwsgr_location_code  = isset($_POST['zwsgr_location_code']) ? sanitize_text_field($_POST['zwsgr_location_code']) : '';
            $zwsgr_review_id      = isset($_POST['zwsgr_review_id']) ? sanitize_text_field($_POST['zwsgr_review_id']) : '';
            $zwsgr_wp_review_id   = isset($_POST['zwsgr_wp_review_id']) ? sanitize_text_field($_POST['zwsgr_wp_review_id']) : ''; // Capture post ID

            // Check that all parameters are provided
            if ( empty( $zwsgr_account_number ) || empty( $zwsgr_location_code ) || empty( $zwsgr_review_id ) || empty( $zwsgr_reply_comment ) ) {
                wp_send_json_error(
                    array(
                        'message' => 'Account ID, location ID, review ID, and reply comment all are required.',
                        'status'  => 400
                    )
                );
                return;
            }

            $this->zwsgr_get_access_token();

            // Prepare the payload for the request
            $zwsgr_payload_data = [
                'comment' => $zwsgr_reply_comment,
            ];

            // Construct the Google My Business endpoint URL using account, location, and review IDs
            $zwsgr_endpoint = "accounts/{$zwsgr_account_number}/locations/{$zwsgr_location_code}/reviews/{$zwsgr_review_id}/reply";

            // Make the API request to add/update the review reply on Google’s server
            $zwsgr_response = $this->zwsgr_api_request( $zwsgr_endpoint, $zwsgr_payload_data, 'PUT', 'v4');

            // Check if the API response was successful
            if ( isset($zwsgr_response['error']) ) {

                // If there's an error in the response, send a JSON error response
                wp_send_json_error( array(
                    'status'  => 'error',
                    'message' => $zwsgr_response['error']['message'] ?? 'Unknown error occurred.',
                ));

            }

            if (isset($zwsgr_response) && isset($zwsgr_response['comment']) && isset($zwsgr_response['updateTime'])) {

                $zwsgr_reply_comment     = $zwsgr_response['comment'];
                $zwsgr_reply_update_time = $zwsgr_response['updateTime'];

                update_post_meta($zwsgr_wp_review_id, 'zwsgr_reply_comment', $zwsgr_reply_comment);
                update_post_meta($zwsgr_wp_review_id, 'zwsgr_reply_update_time', $zwsgr_reply_update_time);

                // Send a success response back to the client
                wp_send_json_success( array(
                    'message' => __('Review updated successfully', 'zw-smart-google-reviews'),
                    'status'  => 200,
                ));
                exit;

            }
        }
        
        /**
         * Deletes a reply to a specific Google My Business review using the review ID.
         *
         * This function sends a DELETE request to the Google My Business API to remove
         * an existing reply associated with a specific review.
         *
         * @param string $zwsgr_account_id The account ID associated with the Google My Business account.
         * @param string $zwsgr_location_id The location ID for the specific business location.
         * @param string $zwsgr_review_id The ID of the review from which the reply will be deleted.
         * @return array The decoded JSON response from the API if successful.
         * @throws Exception If the API request fails or returns an error.
         */
        public function zwsgr_delete_review_reply() {

            // Check nonce and AJAX referer for security
            check_ajax_referer('zwsgr_delete_review_reply', 'security');

            // Sanitize and retrieve account number, location code, and review ID from AJAX request
            $zwsgr_account_number = isset($_POST['zwsgr_account_number']) ? sanitize_text_field($_POST['zwsgr_account_number']) : '';
            $zwsgr_location_code  = isset($_POST['zwsgr_location_code']) ? sanitize_text_field($_POST['zwsgr_location_code']) : '';
            $zwsgr_review_id      = isset($_POST['zwsgr_review_id']) ? sanitize_text_field($_POST['zwsgr_review_id']) : '';
            $zwsgr_wp_review_id   = isset($_POST['zwsgr_wp_review_id']) ? sanitize_text_field($_POST['zwsgr_wp_review_id']) : '';

            // Ensure all required parameters are provided
            if ( empty( $zwsgr_account_number ) || empty( $zwsgr_location_code ) || empty( $zwsgr_review_id ) ) {
                // Send a JSON error response if any parameters are missing
                wp_send_json_error(
                    array(
                        'message' => 'Account ID, location ID, and review ID are all required.',
                        'status'  => 400
                    )
                );
                return;
            }

            // Get the access token required to authenticate with Google My Business API
            $this->zwsgr_get_access_token();

            // Construct the Google My Business API endpoint URL for deleting the reply to the specified review
            $zwsgr_endpoint = "accounts/{$zwsgr_account_number}/locations/{$zwsgr_location_code}/reviews/{$zwsgr_review_id}/reply";

            // Send the DELETE request to the Google My Business API to delete the review reply
            $zwsgr_response = $this->zwsgr_api_request( $zwsgr_endpoint, [], 'DELETE', 'v4' );

            // Check if the API response was successful
            if ( isset($zwsgr_response['error']) ) {

                // If there's an error in the response, send a JSON error response
                wp_send_json_error( array(
                    'status'  => 'error',
                    'message' => $zwsgr_response['error']['message'] ?? 'Unknown error occurred.',
                ));

            }

            // Delete the reply comment from the postmeta table if it's associated with the review
            if ( !empty( $zwsgr_wp_review_id ) ) {
                delete_post_meta( $zwsgr_wp_review_id, 'zwsgr_reply_comment' );
            }

            // Send a success response back to the client
            wp_send_json_success( array(
                'message' => 'Review reply successfully deleted.',
                'status'  => 200,
            ));

        }

        public function zwsgr_fetch_oauth_url() {

            $zwsgr_current_user  = wp_get_current_user();
            $zwsgr_user_name     = $zwsgr_current_user->user_login;
            $zwsgr_user_site_url = admin_url('admin.php?page=zwsgr_connect_google');

            // Get user email from $_POST data
            if (isset($_POST['zwsgr_user_email'])) {
                $zwsgr_user_email = sanitize_email($_POST['zwsgr_user_email']);
            }

            // Validate user email address
            if (empty($zwsgr_user_email) || !is_email($zwsgr_user_email)) {
                wp_send_json_error(
                    [
                        'message' => 'Invalid email address provided',
                        'code' => 'invalid_email_address'
                    ]
                );
                wp_die();
            }

            // Prepare the payload for the request
            $zwsgr_payload_data = [
                'zwsgr_user_name'     => $zwsgr_user_name,
                'zwsgr_user_email'    => $zwsgr_user_email,
                'zwsgr_user_site_url' => $zwsgr_user_site_url
            ];

            // Make the API request to get oauth URl.
            $zwsgr_response = $this->zwsgr_api_request( 'zwsgr/v1/auth', $zwsgr_payload_data, 'POST', '', 'https://siteproofs.com/projects/zealousweb/plugindev/reviews-plugin/wp-json/');

            // Check if the response is successful and contains the oauth URL
            if (isset($zwsgr_response['success']) && $zwsgr_response['success'] === true && isset($zwsgr_response['data']['data']['zwsgr_oauth_url'])) {
                $zwsgr_oauth_url = $zwsgr_response['data']['data']['zwsgr_oauth_url'];
                // Return a success response with the OAuth URL
                wp_send_json_success(
                    array(
                        'message' => __('Redirecting to OAuth for authentication...', 'your-text-domain'),
                        'zwsgr_oauth_url' => esc_url_raw($zwsgr_oauth_url)
                    )
                );
                exit;
            } else {
                // Return a failure message
                wp_send_json_error(
                    array(
                        'message' => __('Failed to generate OAuth URL or invalid response.', 'your-text-domain'),
                        'code' => 'oauth_url_error'
                    )
                );
            }

            wp_die();

        }
        
        public function zwsgr_fetch_jwt_token($zwsgr_request) {

            // Get 'auth_code' and 'consent' from function params parameters
            $zwsgr_auth_code = isset($_GET['auth_code']) ? sanitize_text_field($_GET['auth_code']) : '';

            // Prepare the payload for the request
            $zwsgr_payload_data = [
                'zwsgr_auth_code' => $zwsgr_auth_code
            ];

            // Make the API request to get oauth URl.
            $zwsgr_response = $this->zwsgr_api_request( 'zwsgr/v1/get-jwt-token', $zwsgr_payload_data, 'POST', '', 'https://siteproofs.com/projects/zealousweb/plugindev/reviews-plugin/wp-json/');

            return $zwsgr_response;

        }
        
    }
}