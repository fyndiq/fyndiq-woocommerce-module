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
        $settingsTabs[self::ID] = __('Fyndiq', WC_Fyndiq::TEXT_DOMAIN);
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
        $links[] = '<a href="'.$settingUrl.'">'.__('Settings', WC_Fyndiq::TEXT_DOMAIN).'</a>';
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
                    _x(
                        'Uh-oh. It looks like you shouldn\'t be here.', 'Warning to user if they try to do something not allowed',
                        WC_Fyndiq::TEXT_DOMAIN
                    )
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
            'name'     => __('Fyndiq', WC_Fyndiq::TEXT_DOMAIN),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Adds Title to the Settings
        $settings[] = array(
            'name' => __('General Settings', WC_Fyndiq::TEXT_DOMAIN),
            'type' => 'title',
            'desc' => __('The following options are used to configure Fyndiq', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq'
        );

        // Add second text field option
        $settings[] = array(
            'name' => __('Username', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_username',
            'type' => 'text',
            'desc' => __('The username you use to log in to the Fyndiq merchant page', WC_Fyndiq::TEXT_DOMAIN),
        );

        // Add second text field option
        $settings[] = array(
            'name' => __('API-token', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __('This is the API V2 Token on Fyndiq', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_apitoken',
            'type' => 'text',
            'desc' => __('Must be a new API v2 token, rather than an old style v1 token', WC_Fyndiq::TEXT_DOMAIN),
        );


        //Price Percentage
        $settings[] = array(
            'name' => __('Global Percentage Price Discount', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'The percentage discount be applied to all prices when sold through Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_price_percentage',
            'type' => 'text',
            'default' => '10',
            'desc' => __('Can be set to \'0\' if no discount should be applied', WC_Fyndiq::TEXT_DOMAIN),
        );

        //Price Discount
        $settings[] = array(
            'name' => __('Global Absolute Price Discount', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'The absolute discount applied to all prices when sold through Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_price_discount',
            'type' => 'text',
            'default' => '0',
            'desc' => __('Can be \'0\' if no discount should be applied', WC_Fyndiq::TEXT_DOMAIN),
        );

        // Sales currency
        $settings[] = array(
            'name' => __('Fyndiq Sales Currency', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'Select the currency to be used for sales through Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_currency',
            'type' => 'select',
            'options' => $currencies
        );

        // Minimum Quantity limit
        $settings[] = array(
            'name' => __('Minimum Quantity Reserve Limit', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'This is the lower limit at which point a product is removed from sale on Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_quantity_minimum',
            'type' => 'text',
            'default' => '0',
            'desc' => __(
                'Setting this field to \'0\' allows all stock to be sold through Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
        );

        // Enables or disables trading through Fyndiq
        $settings[] = array(
            'name' => __('Enable Fyndiq Orders', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'Sets whether orders may be placed through Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_order_enable',
            'type' => 'select',
            'options' => array(
                FmOrder::ORDERS_ENABLE => __('Enable', WC_Fyndiq::TEXT_DOMAIN),
                FmOrder::ORDERS_DISABLE => __('Disable', WC_Fyndiq::TEXT_DOMAIN),
            )
        );

        // WooCommerce order status to be added to imported orders
        //TODO: check if we need this
        $settings[] = array(
            'name' => __('Order Status', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'When an order is imported from Fyndiq, this status will be applied in WooCommerce',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_create_order_status',
            'type' => 'select',
            'options' => array(
                'completed' => 'completed',
                'processing' => 'processing',
                'pending' => 'pending',
                'on-hold' => 'on-hold'
            ),
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        $settings[] = array(
            'name'     => __('Field Mappings', WC_Fyndiq::TEXT_DOMAIN),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Add Description picker
        $settings[] = array(
            'name' => __('Product Description Type', WC_Fyndiq::TEXT_DOMAIN),
            'desc_tip' => __(
                'Set how you want your product description to be exported to Fyndiq.',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'id' => 'wcfyndiq_description_picker',
            'type' => 'select',
            'options' => array(
                FmExport::DESCRIPTION_LONG => __('Long Description', WC_Fyndiq::TEXT_DOMAIN),
                FmExport::DESCRIPTION_SHORT => __('Short Description', WC_Fyndiq::TEXT_DOMAIN),
                FmExport::DESCRIPTION_SHORT_LONG => __('Short and Long Description', WC_Fyndiq::TEXT_DOMAIN),
            ),
            'desc' => __('The default is to use the long description', WC_Fyndiq::TEXT_DOMAIN),
        );

        // Map Field for EAN
        $settings[] = array(
            'name' => __('EAN', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_field_map_ean',
            'type' => 'select',
            'options' => $attributes,
        );

        // Map Field for ISBN
        $settings[] = array(
            'name' => __('ISBN', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_field_map_isbn',
            'type' => 'select',
            'options' => $attributes,
        );

        // Map Field for MPN
        $settings[] = array(
            'name' => __('MPN', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_field_map_mpn',
            'type' => 'select',
            'options' => $attributes,
        );

        // Map Field for Brand
        $settings[] = array(
            'name' => _x('Brand', 'noun - in context of marketing', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_field_map_brand',
            'type' => 'select',
            'options' => $attributes,
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end_mapping'
        );

        // Troubleshooting
        $settings[] = array(
            'name' => __('Troubleshooting', WC_Fyndiq::TEXT_DOMAIN),
            'type' => 'title',
            'id' => 'wc_settings_troubleshooting'
        );

        // Enables the use of 'event=debug'
        $settings[] = array(
            'name' => __('Enable Debug Tools', WC_Fyndiq::TEXT_DOMAIN),
            'id' => 'wcfyndiq_enable_debug',
            'type' => 'select',
            'options' => array(
                FmHelpers::DEBUG_DISABLED => __('No', WC_Fyndiq::TEXT_DOMAIN),
                FmHelpers::DEBUG_ENABLED => __('Yes', WC_Fyndiq::TEXT_DOMAIN),
            ),
            'desc' => __('Enables debug tools for Fyndiq', WC_Fyndiq::TEXT_DOMAIN),
        );

        $settings[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end_troubleshooting'
        );

        return apply_filters('wc_settings_tab_' . self::ID, $settings);
    }
}
