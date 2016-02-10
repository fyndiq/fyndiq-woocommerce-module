<?php

//Deactivation routine
register_deactivation_hook(__FILE__, function () {
    //First empty the settings on fyndiq
    if (!checkCredentials()) {
        $data = array(
            FyndiqUtils::NAME_PRODUCT_FEED_URL => '',
            FyndiqUtils::NAME_PING_URL => '',
            FyndiqUtils::NAME_NOTIFICATION_URL => ''
        );
        try {
            FmHelpers::callApi('PATCH', 'settings/', $data);
        } catch (Exception $e) {
        }
    }
    //Empty all settings
    delete_option('wcfyndiq_ping_token', '');
    delete_option('wcfyndiq_username', '');
    delete_option('wcfyndiq_apitoken', '');
});
