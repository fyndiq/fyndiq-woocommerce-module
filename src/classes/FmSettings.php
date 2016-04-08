<?php
/**
 * Class responsible for settings for the plugin
 */


class FmSettings
{
    /**
     * Priority of the settings tab that we add to WooCommerce in terms of the order in which they are rendered
     */
    const SETTING_TAB_PRIORITY = 50;

    /**
     * Slug used for settings
     */
    const ID = 'wcfyndiq';

    /**
     * Sets all WordPress hooks related to the Fields
     *
     * @return bool - Always returns true because add_action() aways returns true TODO: abstraction layer
     */
    public static function setHooks()
    {
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'addSettingsTab'), self::SETTING_TAB_PRIORITY);
        add_action('woocommerce_settings_tabs_wcfyndiq', array(__CLASS__, 'settingsTab'));
        add_action('woocommerce_update_options_wcfyndiq', array(__CLASS__, 'updateSettings'));
        add_filter(
            'plugin_action_links_' .
            plugin_basename(dirname(__FILE__) . '/woocommerce-fyndiq.php'),
            array(__CLASS__, 'pluginSettingsActionLink')
        );
    }


    /**
     * Hooked to 'woocommerce_settings_tabs_array' - filter to inject our tab
     *
     *  @param array $settingsTabs - an associative array of tab names to be rendered where: [label => slug]
     *
     * @return mixed - should be the array of tabs that this was passed, plus the one for our plugin
     */
    public static function addSettingsTab($settingsTabs)
    {
        $settingsTabs[self::ID] = __('Fyndiq', 'fyndiq');
        return $settingsTabs;
    }


    /**
     * Hooked to 'woocommerce_settings_tabs_wcfyndiq' - renders the settings tab contents
     *
     * @return bool - always true
     */
    public static function settingsTab()
    {
        woocommerce_admin_fields(self::fyndiqAllSettings());
        return true;
    }

    /**
     * Hooked to 'plugin_action_links_<slug_name>' - injects an action link for our settings page
     *
     *  @param array $links - the existing array of action links
     *
     * @return array - the passed array plus our injected link
     */
    public static function pluginSettingsActionLink($links)
    {
        $settingUrl = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=products&section=' . self::ID));
        $links[] = '<a href="'.$settingUrl.'">'.__('Settings', 'fyndiq').'</a>';
        return $links;
    }

    /**
     * Hooked to 'woocommerce_update_options_wcfyndiq' - triggers the saving of the options for our plugin
     * also generates pingToken
     *
     * @return bool - false if there is an error, otherwise true.
     */
    public static function updateSettings()
    {
        woocommerce_update_options(self::fyndiqAllSettings());
        try {
            self::updateUrls();
        } catch (Exception $e) {
            if ($e->getMessage() === 'Unauthorized') {
                WC_Admin_Settings::add_error(
                    __('Uh-oh. It looks like your Fyndiq credentials aren\'t correct.', 'fyndiq')
                );
            }
            return false;
        }
        return true;
    }

    /**
     * Updates the saved URLs to have the correct pingToken
     *
     * @return mixed - the result of FmHelpers:callAPI()
     */
    public static function updateUrls()
    {
        //Generates pingToken
        $pingToken = md5(uniqid());
        update_option('wcfyndiq_ping_token', $pingToken);

        $data = array(
            FyndiqUtils::NAME_PRODUCT_FEED_URL => get_site_url() . '/?fyndiq_feed&pingToken=' . $pingToken,
            FyndiqUtils::NAME_PING_URL => get_site_url() .
                '/?fyndiq_notification=1&event=ping&pingToken=' . $pingToken
        );
        if (FmOrder::getOrdersEnabled()) {
            $data[FyndiqUtils::NAME_NOTIFICATION_URL] = get_site_url() . '/?fyndiq_notification=1&event=order_created';
        }
        return FmHelpers::callApi('PATCH', 'settings/', $data);
    }


    /**
     * Contains the data for the various settings options that we use
     *
     * @return mixed|void - returns WP function apply_filters(), which is the value of the settings after filtration
     */
    public static function fyndiqAllSettings()
    {
        //Turns standard array into associate array, with the key and value being the same
        $currencies = array_combine(FyndiqUtils::$allowedCurrencies, FyndiqUtils::$allowedCurrencies);

        //Gets list of product attributes to be used as options
        $attributes = FmHelpers::getAllTerms();

        $settings = array();

        $settings[] = array(
            'name'     => __('Fyndiq', 'fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Adds Title to the Settings
        $settings[] = array(
            'name' => __('General Settings', 'fyndiq'),
            'type' => 'title',
            'desc' => __('The following options are used to configure Fyndiq', 'fyndiq'),
            'id' => 'wcfyndiq'
        );

        // Add second text field option
        $settings[] = array(
            'name' => __('Username', 'fyndiq'),
            'desc_tip' => __('This is the username you use for login on Fyndiq Merchant', 'fyndiq'),
            'id' => 'wcfyndiq_username',
            'type' => 'text',
            'desc' => __('Must be your username', 'fyndiq'),
        );

        // Add second text field option
        $settings[] = array(
            'name' => __('API-token', 'fyndiq'),
            'desc_tip' => __('This is the API V2 Token on Fyndiq', 'fyndiq'),
            'id' => 'wcfyndiq_apitoken',
            'type' => 'text',
            'desc' => __('Must be API v2 token', 'fyndiq'),
        );


        //Price Percentage
        $settings[] = array(
            'name' => __('Global Price Percentage', 'fyndiq'),
            'desc_tip' => __(
                'The percentage that will be removed from the price when sending to fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_price_percentage',
            'type' => 'text',
            'default' => '10',
            'desc' => __('Can be 0 if the price should be the same as in your shop.', 'fyndiq'),
        );

        //Price Discount
        $settings[] = array(
            'name' => __('Global Price Discount', 'fyndiq'),
            'desc_tip' => __(
                'The amount that will be removed from the price when sending to fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_price_discount',
            'type' => 'text',
            'default' => '0',
            'desc' => __('Can be 0 if the price should not change', 'fyndiq'),
        );

        // Add currency setting
        $settings[] = array(
            'name' => __('Used Currency', 'fyndiq'),
            'desc_tip' => __(
                'Choose currency to be used for Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_currency',
            'type' => 'select',
            'options' => $currencies,
            'desc' => __('This must be picked accurate', 'fyndiq'),

        );

        //Minimum Quantity limit
        $settings[] = array(
            'name' => __('Minimum Quantity Limit', 'fyndiq'),
            'desc_tip' => __(
                'this quantity will be reserved by you and will be removed from the quantity that is sent to Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_quantity_minimum',
            'type' => 'text',
            'default' => '0',
            'desc' => __('Stay on 0 if you want to send all stock to Fyndiq.', 'fyndiq'),
        );

        // Add Description picker
        $settings[] = array(
            'name' => __('Enable Orders', 'fyndiq'),
            'desc_tip' => __(
                'This will disable all order logic for Fyndiq',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_order_enable',
            'type' => 'select',
            'options' => array(
                FmOrder::ORDERS_ENABLE => __('Enable', 'fyndiq'),
                FmOrder::ORDERS_DISABLE => __('Disable', 'fyndiq'),
            ),
            'desc' => __('Default is to have orders enabled', 'fyndiq'),
        );

        // Add order status setting
        $settings[] = array(
            'name' => __('Order Status', 'fyndiq'),
            'desc_tip' => __(
                'When a order is imported from fyndiq, this status will be applied.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_create_order_status',
            'type' => 'select',
            'options' => array(
                'completed' => 'completed',
                'processing' => 'processing',
                'pending' => 'pending',
                'on-hold' => 'on-hold'
            ),
            'desc' => __('This must be picked accurate', 'fyndiq')
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        $settings[] = array(
            'name'     => __('Field Mappings', 'fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Add Description picker
        $settings[] = array(
            'name' => __('Description to use', 'fyndiq'),
            'desc_tip' => __(
                'Set how you want your description to be exported to Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_description_picker',
            'type' => 'select',
            'options' => array(
                FmExport::DESCRIPTION_LONG => __('Long Description', 'fyndiq'),
                FmExport::DESCRIPTION_SHORT => __('Short Description', 'fyndiq'),
                FmExport::DESCRIPTION_SHORT_LONG => __('Short and Long Description', 'fyndiq'),
            ),
            'desc' => __('Default is Long Description', 'fyndiq'),
        );

        // Map Field for EAN
        $settings[] = array(
            'name' => __('EAN', 'fyndiq'),
            'desc_tip' => __(
                'EAN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_ean',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
        );

        // Map Field for ISBN
        $settings[] = array(
            'name' => __('ISBN', 'fyndiq'),
            'desc_tip' => __(
                'ISBN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_isbn',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
        );

        // Map Field for MPN
        $settings[] = array(
            'name' => __('MPN', 'fyndiq'),
            'desc_tip' => __(
                'MPN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_mpn',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
        );

        // Map Field for Brand
        $settings[] = array(
            'name' => __('Brand', 'fyndiq'),
            'desc_tip' => __(
                'Brand',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_brand',
            'type' => 'select',
            'options' => $attributes,
            'desc' => __('This must be picked accurate', 'fyndiq'),
        );


        //TODO: what sets this?
        if (isset($_GET['set_sku'])) {
            // Add SKU picker
            $settings[] = array(
                'name' => __('Reference to be in use', 'fyndiq'),
                'desc_tip' => __(
                    'If you have multi SKU as in variations changing this will make it work better',
                    'fyndiq'
                ),
                'id' => 'wcfyndiq_reference_picker',
                'type' => 'select',
                'options' => array(
                    FmExport::REF_SKU => __('SKU', 'fyndiq'),
                    FmExport::REF_ID => __('Product and Article ID', 'fyndiq'),
                ),
                'desc' => __(
                    'If this value is changed, products already existing on Fyndiq will be removed and uploaded again and orders might not be able to be imported with old SKU.',
                    'fyndiq'
                ),
            );
        }

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end_mapping'
        );

        // Troubleshooting
        $settings[] = array(
            'name' => __('Troubleshooting', 'fyndiq'),
            'type' => 'title',
            'id' => 'wc_settings_troubleshooting'
        );

        // Enables the use of 'event=debug'
        $settings[] = array(
            'name' => __('Enable Debug', 'fyndiq'),
            'desc_tip' => __('Enables debugging.', 'fyndiq'),
            'id' => 'wcfyndiq_enable_debug',
            'type' => 'select',
            'options' => array(
                FmHelpers::DEBUG_DISABLED => __('No', 'fyndiq'),
                FmHelpers::DEBUG_ENABLED => __('Yes', 'fyndiq'),
            ),
            'desc' => __('Enable Debug', 'fyndiq'),
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end_troubleshooting'
        );

        return apply_filters('wc_settings_tab_' . self::ID, $settings);
    }
}
