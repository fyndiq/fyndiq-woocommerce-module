<?php

class FmUpdate
{

    const UPDATE_URL = 'http://developers.fyndiq.com/repos/fyndiq/fyndiq-woocommerce-module/releases/latest.json';

    function updateNotification()
    {
        $time = get_option('wcfyndiq_update_date');
        if (!isset($time) || $time < strtotime('-1 day', time())) {
            $version = $this->get_update_version();

            if (!is_null($version)) {
                update_option('wcfyndiq_update_version', $version->tag_name);
                update_option('wcfyndiq_update_url', $version->html_url);
                update_option('wcfyndiq_update_date', time());
            }
        }

        $version = get_option('wcfyndiq_update_version');
        $current_version = FmHelpers::get_plugin_version();
        if (!is_null($version) && version_compare($version, $current_version) > 0) {
            add_action('admin_notices', array(&$this, 'updateNotificiation_shower'));
        }
    }

    function updateNotificiation_shower()
    {
        $url = get_option('wcfyndiq_update_url');
        ?>
        <div class="updated">
        <p><?php _e('It exist a new version of Fyndiq plugin, install it by clicking on the link:', 'fyndiq'); ?> <a href="<?php echo $url; ?>"><?php echo $url; ?></a> </p>
        </div>
        <?php
    }

    function get_update_version()
    {
        $data = $this->update_curl();
        if (isset($data->tag_name)) {
            return $data;
        }
        return null;
    }

    function update_curl()
    {

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

        # extract different parts of the response
        $response['http_status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response['header_size'] = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response['header'] = substr($response['data'], 0, $response['header_size']);
        $response['body'] = substr($response['data'], $response['header_size']);
        return json_decode($response['body']);
    }
}
