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
            <p><?php _e('It exist a new version of Fyndiq plugin, install it by clicking on the link:', 'fyndiq'); ?> <a
                    href="<?php echo $url; ?>"><?php echo $url; ?></a></p>
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
        $response = wp_remote_get(self::UPDATE_URL);

        if (isset($response['response']['code']) && 200 == $response['response']['code']) {
            return json_decode($response['body']);
        }

        return null;
    }
}
