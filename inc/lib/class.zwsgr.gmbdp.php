<?php

if ( ! class_exists( 'Zwsgr_Google_My_Business_Data_Processor' ) ) {

    class Zwsgr_Google_My_Business_Data_Processor {

        private $client;

        private $jwt_handler;

        public function __construct() {

            // Instantiate the Google My Business
            $zwsgr_gmb_initializer = new Zwsgr_Google_My_Business_Initializer();

            // Access the Google Client through the connector
            $this->client = $zwsgr_gmb_initializer->get_client();

            // Instantiate the JWT HANDLER
            $zwsgr_jwt_handler = new ZWSGR_JWT_HANDLER();

            // Access the jwt handler through the handler class
            $this->jwt_handler = $zwsgr_jwt_handler->get_jwt_handler();

            // Register REST API route for initiating OAuth flow
            add_action('init', [$this, 'action__add_custom_rewrite_rules']);
            add_action('template_redirect', [$this, 'action__zwsgr_handle_oauth_flow']);

        }

        // Add custom rewrite rule to handle the "connect-google" URL
        public function action__add_custom_rewrite_rules() {
            add_rewrite_rule('^connect-google/?$', 'index.php?zwsgr_oauth_flow=1', 'top');
            flush_rewrite_rules();  // Flush rewrite rules after adding them
        }

        public function action__zwsgr_handle_oauth_flow() {

            // Check if the 'code' and 'state' parameters are present
            if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {

                

                // Decode the 'state' parameter
                $zwsgr_oauth_state = json_decode(urldecode($_GET['state']), true);
        
                // Retrieve the user's ID, email, and site URL from the 'state' parameter
                if ( isset($zwsgr_oauth_state['zwsgr_user_name'], $zwsgr_oauth_state['zwsgr_user_email'], $zwsgr_oauth_state['zwsgr_user_site_url']) ) {
                    
                    $zwsgr_user_name     = sanitize_text_field($zwsgr_oauth_state['zwsgr_user_name']);
                    $zwsgr_user_email    = sanitize_email($zwsgr_oauth_state['zwsgr_user_email']);
                    $zwsgr_user_site_url = sanitize_text_field($zwsgr_oauth_state['zwsgr_user_site_url']);
        
                    // Validate the email address
                    if ( ! is_email($zwsgr_user_email) ) {
                        $error_message = 'Invalid or empty email address.';
                        // Redirect the user back to the site URL with the error message
                        $zwsgr_user_site_url = esc_url($zwsgr_user_site_url);
                        wp_redirect(
                            add_query_arg(
                                'error', 
                                urlencode($error_message), 
                                $zwsgr_user_site_url
                            )
                        );
                        exit;
                    }

                    // Get the post ID for the 'zwsgr_oauth_data' post that matches all meta fields
                    $zwsgr_oauth_data_id = get_posts(array(
                        'post_type'      => 'zwsgr_oauth_data',
                        'posts_per_page' => 1,
                        'post_status'    => 'publish',
                        'meta_query'     => array(
                            array(
                                'key'     => 'zwsgr_user_name',
                                'value'   => $zwsgr_user_name,
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

                    // Check if we found a post
                    if ($zwsgr_oauth_data_id) {

                        // Exchange code for access token
                        $zwsgr_oauth_code = sanitize_text_field( $_GET['code'] );

                        try {

                            $zwsgr_access_token = $this->client->fetchAccessTokenWithAuthCode($zwsgr_oauth_code);
                        
                            if (isset($zwsgr_access_token['access_token']) && isset($zwsgr_access_token['refresh_token'])) {

                                // Update access token in post meta
                                $access_token_updated = update_post_meta($zwsgr_oauth_data_id, 'zwsgr_gmb_access_token', $zwsgr_access_token['access_token']);
                        
                                // Update refresh token in post meta
                                $refresh_token_updated = update_post_meta($zwsgr_oauth_data_id, 'zwsgr_gmb_refresh_token', $zwsgr_access_token['refresh_token']);
                        
                                // Check if both tokens were successfully updated
                                if ($access_token_updated && $refresh_token_updated) {

                                        // Update the OAuth status to "complete"
                                        update_post_meta($zwsgr_oauth_data_id, 'zwsgr_oauth_status', 'CONNECTED');

                                        $zwsgr_oauth2 = new Google_Service_Oauth2($this->client);

                                        $zwsgr_google_user_info = $zwsgr_oauth2->userinfo->get();

                                        // Get the email from Google's response
                                        $zwsgr_google_email = $zwsgr_google_user_info->email;

                                        // Compare Google email with the provided email
                                        if ($zwsgr_google_email === $zwsgr_user_email) {

                                            $zwsgr_jwt_secret = get_post_meta($zwsgr_oauth_data_id, 'zwsgr_jwt_secret', true);

                                            if (empty($zwsgr_jwt_secret)) {
                                                $zwsgr_jwt_secret = bin2hex(random_bytes(32)); // 256-bit key
                                                update_post_meta($zwsgr_oauth_data_id, 'zwsgr_jwt_secret', $zwsgr_jwt_secret);
                                            }

                                            // Generate JWT token for the verified user
                                            $zwsgr_jwt_payload = [
                                                'zwsgr_user_name'     => $zwsgr_user_name,
                                                'zwsgr_user_email'    => $zwsgr_user_email,
                                                'zwsgr_user_site_url' => $zwsgr_user_site_url
                                            ];

                                            $zwsgr_jwt_token = $this->jwt_handler->zwsgr_generate_jwt_token($zwsgr_jwt_payload, $zwsgr_jwt_secret);

                                            if ($zwsgr_jwt_token) {
                                                // If successful, update the post meta
                                                update_post_meta($zwsgr_oauth_data_id, 'zwsgr_jwt_token', $zwsgr_jwt_token);
                                            }

                                            // Generate a unique authorization code
                                            $zwsgr_auth_code = bin2hex(random_bytes(16)); // Changed to use consistent variable name

                                            update_post_meta($zwsgr_oauth_data_id, 'zwsgr_auth_code', $zwsgr_auth_code);
                                            update_post_meta($zwsgr_oauth_data_id, 'zwsgr_auth_code_expiry', time() + 300); // Set expiration for 5 minutes

                                            // Ensure the URL is safe and properly formed
                                            $zwsgr_user_site_url = esc_url_raw($zwsgr_user_site_url);

                                            // Construct the redirect URL with the authorization code and consent
                                            $zwsgr_redirect_url = add_query_arg(
                                                array(
                                                    'auth_code' => $zwsgr_auth_code,
                                                    'consent'   => 'true'
                                                ),
                                                $zwsgr_user_site_url
                                            );

                                            // Redirect to the URL safely
                                            wp_redirect($zwsgr_redirect_url);
                                            exit;

                                        } else {
                                            wp_die('Unauthorized: Email verification failed.');
                                        }
                        
                                    exit;
                                } else {

                                    $error_message = 'Unauthorized: Email verification failed. Please check your email address and try again.';
                                    // Redirect the user back to the site URL with the error message
                                    $redirect_url = esc_url($zwsgr_user_site_url);
                                    wp_redirect(
                                        add_query_arg(
                                            'error', 
                                            urlencode($error_message), 
                                            $redirect_url
                                        )
                                    );
                                    exit;
                                }
                            } else {
                                $error_message = 'Failed to obtain access or refresh token. Please try again later or contact support if the issue persists.';
                                // Redirect back to the original site with the error message
                                $redirect_url = esc_url($zwsgr_user_site_url); // Ensure the URL is safe
                                wp_redirect(
                                    add_query_arg(
                                        'error', 
                                        urlencode($error_message), 
                                        $redirect_url
                                    )
                                );
                                exit;

                            }
                        } catch (Exception $e) {
                            $error_message = $e->getMessage();
                            // Redirect back to the original site with the error message
                            $zwsgr_redirect_url = esc_url($zwsgr_user_site_url); // Ensure the URL is safe
                            wp_redirect(
                                add_query_arg(
                                    'error', 
                                    urlencode($error_message), 
                                    $zwsgr_redirect_url
                                )
                            );
                        }
                        
                    } else {
                        $error_message = 'No auth credentials found';
                        // Redirect back to the original site with the error message
                        $zwsgr_redirect_url = esc_url($zwsgr_user_site_url); // Ensure the URL is safe
                        wp_redirect(
                            add_query_arg(
                                'error', 
                                urlencode($error_message), 
                                $zwsgr_redirect_url
                            )
                        );
                    }

                } else {
                    // Redirect back to the original site with the error message
                    $zwsgr_redirect_url = esc_url($zwsgr_user_site_url); // Ensure the URL is safe
                    wp_redirect(
                        add_query_arg(
                            'error', 
                            urlencode($error_message), 
                            $zwsgr_redirect_url
                        )
                    );
                }
            }
        }   

        // Getter method to access the client
        public function get_client() {
            return $this->client;
        }

    }

    new Zwsgr_Google_My_Business_Data_Processor();
}