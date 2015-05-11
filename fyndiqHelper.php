<?php
class FmHelpers
{

    public static function apiConnectionExists()
    {
        return !is_null(get_option('wcfyndiq_username')) && !is_null(get_option('wcfyndiq_apitoken'));
    }

    public static function allSettingsExist()
    {
        return FmConfig::getBool('language') && FmConfig::getBool('currency');
    }

    /**
     * wrappers around FyndiqAPI
     * uses stored connection credentials for authentication
     *
     * @param $method
     * @param $path
     * @param array $data
     * @return mixed
     */
    public static function callApi($storeId, $method, $path, $data = array())
    {
        $username = FmConfig::get('username', $storeId);
        $apiToken = FmConfig::get('apikey', $storeId);
        $userAgent = "FyndiqMerchantMagento" . FmConfig::getVersion() . "-" . Mage::getVersion();

        return FyndiqAPICall::callApiRaw($userAgent, $username, $apiToken, $method, $path, $data,
            array('FyndiqAPI', 'call'));
    }
}