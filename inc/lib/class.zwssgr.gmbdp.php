<?php
/**
 * Zwssgr_Google_My_Business_Data_Processor Class
 *
 * Handles the token exchange functionality.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Zwssgr_Google_My_Business_Data_Processor' ) ) {

    /**
     * Class to handle Google My Business OAuth flow and data processing.
     * This class manages the OAuth authentication process with Google, retrieves
     * access and refresh tokens, validates user email, and generates JWT tokens.
     * It also handles redirecting the user based on success or failure.
     */
    class Zwssgr_Google_My_Business_Data_Processor {

        private $client;

        private $jwt_handler;

        public function __construct() {

            // Instantiate the Google My Business
            $zwssgr_gmb_initializer = new Zwssgr_Google_My_Business_Initializer();

            // Instantiate the JWT HANDLER
            $zwssgr_jwt_handler = new Zwssgr_Jwt_Handler();

            // Access the Google Client through the connector
            $this->client = $zwssgr_gmb_initializer->get_client();

            // Access the jwt handler through the handler class
            $this->jwt_handler = $zwssgr_jwt_handler->get_jwt_handler();

            add_action('init', [$this, 'action__add_custom_rewrite_rules']);
            add_action('template_redirect', [$this, 'action__zwssgr_handle_oauth_flow']);

        }

        /**
         * Adds a custom rewrite rule for the "connect-google" URL to trigger the OAuth flow.
         * Flushes rewrite rules to apply the new rule immediately.
         */
        public function action__add_custom_rewrite_rules() {
            add_rewrite_rule('^connect-google/?$', 'index.php?zwssgr_oauth_flow=1', 'top');
            flush_rewrite_rules();
        }

        /**
         * Handles the OAuth flow for Google authentication.
         * Validates the email, exchanges the authorization code for tokens, 
         * updates post meta with access/refresh tokens, generates a JWT token, 
         * and redirects the user with the authorization code. 
         * Redirects with error messages if any issues occur.
         */
        public function action__zwssgr_handle_oauth_flow() {

            // Check if the 'code' and 'state' parameters are present
            if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {

                // Decode the 'state' parameter
                $zwssgr_oauth_state = json_decode(urldecode($_GET['state']), true);
        
                // Retrieve the user's ID, email, and site URL from the 'state' parameter
                if ( $zwssgr_oauth_state['zwssgr_user_name'] && $zwssgr_oauth_state['zwssgr_user_site_url']) {
                    
                    $zwssgr_user_name     = sanitize_text_field($zwssgr_oauth_state['zwssgr_user_name']);
                    $zwssgr_user_site_url = sanitize_text_field($zwssgr_oauth_state['zwssgr_user_site_url']);

                    // Get the post ID for the 'zwssgr_oauth_data' post that matches all meta fields
                    $zwssgr_oauth_data_id = get_posts(array(
                        'post_type'      => 'zwssgr_oauth_data',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                        'meta_query'     => array(
                            array(
                                'key'     => 'zwssgr_user_name',
                                'value'   => $zwssgr_user_name,
                                'compare' => '='
                            ),
                            array(
                                'key'     => 'zwssgr_user_site_url',
                                'value'   => $zwssgr_user_site_url,
                                'compare' => '='
                            ),
                        ),
                        'fields'         => 'ids',
                    ))[0] ?? null;

                    // Check if we found a post
                    if ($zwssgr_oauth_data_id) {

                        // Exchange code for access token
                        $zwssgr_oauth_code = sanitize_text_field( $_GET['code'] );

                        try {

                            $zwssgr_access_token = $this->client->fetchAccessTokenWithAuthCode($zwssgr_oauth_code);
                        
                            if (isset($zwssgr_access_token['access_token']) && isset($zwssgr_access_token['refresh_token'])) {

                                // Update access token in post meta
                                $access_token_updated = update_post_meta($zwssgr_oauth_data_id, 'zwssgr_gmb_access_token', $zwssgr_access_token['access_token']);
                        
                                // Update refresh token in post meta
                                $refresh_token_updated = update_post_meta($zwssgr_oauth_data_id, 'zwssgr_gmb_refresh_token', $zwssgr_access_token['refresh_token']);
                        
                                // Check if both tokens were successfully updated
                                if ($access_token_updated && $refresh_token_updated) {

                                    // Update the OAuth status to "connected"
                                    update_post_meta($zwssgr_oauth_data_id, 'zwssgr_oauth_status', 'CONNECTED');

                                    $zwssgr_oauth2 = new Google_Service_Oauth2($this->client);

                                    $zwssgr_google_user_info = $zwssgr_oauth2->userinfo->get();

                                    // Get the email from Google's response
                                    $zwssgr_google_email = $zwssgr_google_user_info->email;

                                    $zwssgr_jwt_secret = get_post_meta($zwssgr_oauth_data_id, 'zwssgr_jwt_secret', true);

                                    if (empty($zwssgr_jwt_secret)) {
                                        $zwssgr_jwt_secret = bin2hex(random_bytes(32)); // 256-bit key
                                        update_post_meta($zwssgr_oauth_data_id, 'zwssgr_jwt_secret', $zwssgr_jwt_secret);
                                    }

                                    update_post_meta($zwssgr_oauth_data_id, 'zwssgr_user_email', $zwssgr_google_email);

                                    // Generate JWT token for the verified user
                                    $zwssgr_jwt_payload = [
                                        'zwssgr_user_email'    => $zwssgr_google_email,
                                        'zwssgr_user_site_url' => $zwssgr_user_site_url
                                    ];

                                    $zwssgr_jwt_token = $this->jwt_handler->zwssgr_generate_jwt_token($zwssgr_jwt_payload, $zwssgr_jwt_secret);

                                    if ($zwssgr_jwt_token) {
                                        // If successful, update the post meta
                                        update_post_meta($zwssgr_oauth_data_id, 'zwssgr_jwt_token', $zwssgr_jwt_token);
                                    }

                                    // Generate a unique authorization code
                                    $zwssgr_auth_code = bin2hex(random_bytes(16)); // Changed to use consistent variable name

                                    update_post_meta($zwssgr_oauth_data_id, 'zwssgr_auth_code', $zwssgr_auth_code);
                                    update_post_meta($zwssgr_oauth_data_id, 'zwssgr_auth_code_expiry', time() + 300);

                                    // Ensure the URL is safe and properly formed
                                    $zwssgr_user_site_url = esc_url_raw($zwssgr_user_site_url);

                                    // Construct the redirect URL with the authorization code and consent
                                    $zwssgr_redirect_url = add_query_arg(
                                        array(
                                            'auth_code'  => $zwssgr_auth_code,
                                            'user_email' => $zwssgr_google_email,
                                            'consent'    => 'true'
                                        ),
                                        $zwssgr_user_site_url
                                    );

                                    // Redirect to the URL safely
                                    wp_redirect($zwssgr_redirect_url);
                                    exit;

                                } else {

                                    $zwssgr_error_code = 'update_failed';
                                    $redirect_url = esc_url($zwssgr_user_site_url);
                                    wp_redirect(
                                        add_query_arg(
                                            'error', 
                                            urlencode($zwssgr_error_code), 
                                            $redirect_url
                                        )
                                    );
                                    exit;

                                }

                            } else {

                                $zwssgr_error_code = 'empty_tokens';
                                $redirect_url = esc_url($zwssgr_user_site_url); // Ensure the URL is safe
                                wp_redirect(
                                    add_query_arg(
                                        'error', 
                                        urlencode($zwssgr_error_code), 
                                        $redirect_url
                                    )
                                );
                                exit;

                            }
                        } catch (Exception $e) {

                            $error_message = $e->getMessage();
                            error_log($error_message);

                            $zwssgr_error_code = 'error_token_generation';
                            $zwssgr_redirect_url = esc_url($zwssgr_user_site_url);
                            wp_redirect(
                                add_query_arg(
                                    'error', 
                                    urlencode($zwssgr_error_code), 
                                    $zwssgr_redirect_url
                                )
                            );

                        }
                        
                    } else {

                        $zwssgr_error_code = 'missing_oauth_credentials';
                        $zwssgr_redirect_url = esc_url($zwssgr_user_site_url);
                        wp_redirect(
                            add_query_arg(
                                'error', 
                                urlencode($zwssgr_error_code), 
                                $zwssgr_redirect_url
                            )
                        );

                    }

                } else {

                    $zwssgr_error_code = 'missing_state_param';
                    $zwssgr_redirect_url = esc_url($zwssgr_user_site_url);
                    wp_redirect(
                        add_query_arg(
                            'error', 
                            urlencode($zwssgr_error_code), 
                            $zwssgr_redirect_url
                        )
                    );

                }
                
            } else {

                $zwssgr_error_code = 'missing_param';
                $zwssgr_redirect_url = esc_url($zwssgr_user_site_url);
                wp_redirect(
                    add_query_arg(
                        'error', 
                        urlencode($zwssgr_error_code), 
                        $zwssgr_redirect_url
                    )
                );

            }
        }   

        // Getter method to access the client
        public function get_client() {
            return $this->client;
        }

    }

    new Zwssgr_Google_My_Business_Data_Processor();
}