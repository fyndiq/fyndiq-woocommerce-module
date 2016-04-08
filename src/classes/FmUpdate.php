<?php

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

/**
 * Class FmUpdate - handles updating the plugin
 */
class FmUpdate
{
    /**
     * The URL of the JSON file containing the latest version number
     */
    const UPDATE_URL = 'http://developers.fyndiq.com/repos/fyndiq/fyndiq-woocommerce-module/releases/latest.json';


    /**
     * Sets all WordPress hooks related to the Fields
     *
     * @return bool - Always returns true because add_action() aways returns true TODO: abstraction layer
     */
    public static function setHooks()
    {
        add_action('admin_notices', array(__CLASS__, 'updateNotification'));
    }

    /**
     * Hooked to 'admin_notices' - checks whether there is an available update and if so, calls function to render it
     *
     * @return bool - true if an update is needed, otherwise false
     */
    public static function updateNotification()
    {
        $updater = new FmUpdate();
        $time = get_option('wcfyndiq_update_date');
        if (!isset($time) || $time < strtotime('-1 day', time())) {
            $version = $updater->getUpdateVersion();

            if (!is_null($version)) {
                update_option('wcfyndiq_update_version', $version->tag_name);
                update_option('wcfyndiq_update_url', $version->html_url);
                update_option('wcfyndiq_update_date', time());
            }
        }

        $version = get_option('wcfyndiq_update_version');
        $current_version = FmHelpers::getPluginVersion();
        if (!is_null($version) && version_compare($version, $current_version) > 0) {
            add_action('admin_notices', array(&$updater, 'renderUpdateNotification'));
            return true;
        }
        return false;
    }

    /**
     * Renders the notification about existing fyndiq plugin updates
     *
     * @return bool - always true
     */
    private function renderUpdateNotification()
    {
        $url = get_option('wcfyndiq_update_url');
        $fmOutput = new FyndiqOutput();
        $fmOutput->output(
            sprintf(
                '<div class="updated"><p>%s<a href="%s"></a></div></p>',
                __('There are updates available to the Fyndiq plugin, to install them, click here:', WC_Fyndiq::TEXT_DOMAIN),
                $url
            )
        );

        return true;
    }

    /**
     * Gets the version of the update
     *
     * @return object|array|bool - returns version data if exists, false on error
     */
    private function getUpdateVersion()
    {
        $data = $this->updateCurl();
        if (isset($data->tag_name)) {
            return $data;
        }
        return false;
    }

    /**
     * Gets the update data from the server
     *
     * @return array|object|bool - update data on success, otherwise false
     */
    private function updateCurl()
    {
        $response = wp_remote_get(self::UPDATE_URL);

        if ((!is_wp_error($response))
            && (isset($response['response']['code']) && 200 == $response['response']['code'])
        ) {
            return json_decode($response['body']);
        }
        return false;
    }
}
