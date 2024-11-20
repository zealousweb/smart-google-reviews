<?php
/**
 * ZWSGR_JWT_HANDLER Class
 *
 * Handles the Toeken verification and management.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'ZWSGR_JWT_HANDLER' ) ) {
    
    class ZWSGR_JWT_HANDLER {

        private $jwt_handler;

        public function __construct( ) {

        }

        /**
         * Generates a JWT token using the provided payload and secret.
         *
         * This function creates a JWT by encoding the header, payload, and signing them with HMAC SHA256.
         * The resulting token is in the format: header.payload.signature.
         *
         * @param array|object $zwsgr_jwt_payload The payload to encode in the JWT.
         * @param string $zwsgr_jwt_secret The secret key used for signing the JWT.
         * 
         * @return string The generated JWT token.
         * @throws WP_Error If payload or secret is missing or invalid.
         */
        public function zwsgr_generate_jwt_token($zwsgr_jwt_payload, $zwsgr_jwt_secret) {

            // Check if the payload and secret are provided and valid
            if (empty($zwsgr_jwt_payload) || empty($zwsgr_jwt_secret)) {
                wp_send_json_error([
                    'message' => 'Both payload and secret are required to generate the JWT token.',
                    'error'   => 'Missing required parameters'
                ], 400);
            }

            // Ensure payload is an array or object for JSON encoding
            if (!is_array($zwsgr_jwt_payload) && !is_object($zwsgr_jwt_payload)) {
                wp_send_json_error([
                    'message' => 'The payload must be either an array or an object.',
                    'error'   => 'Invalid payload type'
                ], 400);
            }

            $zwsgr_jwt_header = json_encode(
                ['typ' => 'JWT', 'alg' => 'HS256']
            );

            $zwsgr_jwt_payload = json_encode($zwsgr_jwt_payload);
        
            $base64UrlHeader     = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($zwsgr_jwt_header));
            $base64UrlPayload    = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($zwsgr_jwt_payload));
            $zwsgr_jwt_signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $zwsgr_jwt_secret, true);
            $base64UrlSignature  = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($zwsgr_jwt_signature));
        
            return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";

        } 

        public function zwsgr_verify_jwt_token($zwsgr_jwt_token) {

            // Helper function to handle Base64 URL decoding
            function zwsgr_base64url_decode($data) {
                $data = str_replace(['-', '_'], ['+', '/'], $data);
                $padding = strlen($data) % 4;
                if ($padding) {
                    $data .= str_repeat('=', 4 - $padding);
                }
                return base64_decode($data);
            }

            $parts = explode('.', $zwsgr_jwt_token);
            if (count($parts) !== 3) {
                return false;
            }            
        
            $header              = json_decode(zwsgr_base64url_decode($parts[0]), true);
            $zwsgr_oauth_payload = json_decode(zwsgr_base64url_decode($parts[1]), true);
            $signature_provided  = $parts[2];

        
            // Retrieve the secret for the user ID in the payload
            $zwsgr_oauth_id = get_posts([
                'post_type' => 'zwsgr_oauth_data',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => 'zwsgr_user_name', 
                        'value' => $zwsgr_oauth_payload['zwsgr_user_name'], 
                        'compare' => '='
                    ]
                ]
            ])[0] ?? null;
        
            if (!$zwsgr_oauth_id) {
                return false;
            }
        
            $zwsgr_jwt_secret = get_post_meta($zwsgr_oauth_id, 'zwsgr_jwt_secret', true);

            if (!$zwsgr_jwt_secret) {
                return false;
            }
        
            // Verify the signature
            $base64_url_header    = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
            $base64_url_payload   = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($zwsgr_oauth_payload)));
            $signature            = hash_hmac('sha256', "$base64_url_header.$base64_url_payload", $zwsgr_jwt_secret, true);
            $base64_url_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
            if ($base64_url_signature !== $signature_provided) {
                return false;
            }
        
            return $zwsgr_oauth_payload;
            
        }

        // Getter method to access the client
        public function get_jwt_handler() {
            return new ZWSGR_JWT_HANDLER();
        }

    }

    // Instantiate the class
    new ZWSGR_JWT_HANDLER();

}