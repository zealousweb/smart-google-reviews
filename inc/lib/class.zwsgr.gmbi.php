<?php
/**
 * Zwsgr_Google_My_Business_Initializer Class
 *
 * Initialize GMB Client.
 *
 * @package WordPress
 * @subpackage Smart Google Reviews
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Zwsgr_Google_My_Business_Initializer' ) ) {

    // Include the Google API Client's autoload file from the specified directory.
    require_once ZWSGR_DIR . '/inc/lib/google-api-php-client/vendor/autoload.php';

    /**
     * Class Zwsgr_Google_My_Business_Initializer
     * 
     * This class is responsible for initializing the Google API client and configuring it
     * to interact with Google My Business API.
     * It handles authentication, client setup, and defines necessary scopes.
     */
    class Zwsgr_Google_My_Business_Initializer {

        // The Google Client instance.
        private $client;

        public function __construct() {

            $this->client = new Google_Client();
            $this->client->setApplicationName('Smart Google Reviews');
            $this->client->setClientId('637773123304-na845vs42isjuaeuham894p2d7v7jq4a.apps.googleusercontent.com');
            $this->client->setClientSecret('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');
            $this->client->setRedirectUri('https://siteproofs.com/projects/zealousweb/plugindev/reviews-plugin/connect-google');
            $this->client->addScope('https://www.googleapis.com/auth/userinfo.email');
            $this->client->addScope('https://www.googleapis.com/auth/business.manage');
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');

        }

        /**
         * Get method to access the Google Client instance.
         * 
         * @return Google_Client The initialized Google Client.
         */
        public function get_client() {
            return $this->client;
        }

    }

    // Instantiate the class to initialize the Google API client when this file is included.
    new Zwsgr_Google_My_Business_Initializer();
}