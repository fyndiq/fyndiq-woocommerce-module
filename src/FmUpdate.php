<?php

class FmUpdate {

        const UPDATE_URL = 'http://developers.fyndiq.com/repos/woocommerce/releases/latest.json?';

        function updateNotification()
        {
            $time = get_option('wcfyndiq_update_date');
            if(!isset($time) || $time < strtotime('-1 day', time())) {

                $version = $this->get_update_version();

                if(!is_null($version)) {
                    update_option('wcfyndiq_update_version', $version);
                    update_option('wcfyndiq_update_date', time());
                }
            }

            $version = get_option('wcfyndiq_update_version');
            $current_version = FmHelpers::get_plugin_version();
            if(!is_null($version) && version_compare($version, $current_version) > 0) {
                add_action( 'admin_notices', array(&$this, 'updateNotificiation_shower') );
            }
        }

        function updateNotificiation_shower() {
        ?>
        <div class="updated">
            <p><?php _e( 'It exist a new version of Fyndiq plugin, install it by clicking on the link:', 'fyndiq' ); ?></p>
        </div>
        <?php
        }

        function get_update_version() {
            $data = $this->update_curl();
        }

        function update_curl() {

            $curlOpts = array(
                CURLOPT_URL => self::UPDATE_URL,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array('Content-type: application/json'),

                CURLOPT_VERBOSE => true,
                CURLOPT_HEADER => true,

                #CURLOPT_SSLVERSION => 3,
                #CURLOPT_SSL_VERIFYPEER => true,
                #CURLOPT_SSL_VERIFYHOST => 2,

                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_RETURNTRANSFER => 1,
            );

            # make the call
            $ch = curl_init();
            curl_setopt_array($ch, $curlOpts);
            $response['data'] = curl_exec($ch);
            return $response;
        }
}
