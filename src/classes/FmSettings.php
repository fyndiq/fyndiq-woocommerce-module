<?php
/**
 * Class responsible for settings for the plugin
 */



class FmSettings
{
    const SETTING_TAB_PRIORITY = 50;

    public static function setHooks()
    {
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'fyndiq_add_settings_tab'), self::SETTING_TAB_PRIORITY);
        add_action('woocommerce_settings_tabs_wcfyndiq', array(__CLASS__, 'settings_tab'));
        add_action('woocommerce_update_options_wcfyndiq', array(__CLASS__, 'update_settings'));
        add_filter('plugin_action_links_' . plugin_basename(dirname(__FILE__).'/woocommerce-fyndiq.php'), array(__CLASS__, 'pluginActionLink'));
    }


    public static function fyndiq_add_settings_tab($settings_tabs)
    {
        $settings_tabs['wcfyndiq'] = __('Fyndiq', 'fyndiq');
        return $settings_tabs;
    }

    public static function settings_tab()
    {
        woocommerce_admin_fields(self::fyndiq_all_settings());
    }

    static public function pluginActionLink($links)
    {
        $settingUrl = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=products&section=wcfyndiq'));
        $links[] = '<a href="'.$settingUrl.'">'.__('Settings', 'fyndiq').'</a>';
        return $links;
    }

    public static function update_settings()
    {
        woocommerce_update_options(self::fyndiq_all_settings());
        try {
            self::updateUrls();
        } catch (Exception $e) {
            if ($e->getMessage() == 'Unauthorized') {
                FmError::handleError('Uh-oh. It looks like your Fyndiq credentials aren\'t correct.');
            }
        }
    }

    public static function updateUrls()
    {
        //Generate ping token
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

    public function fyndiq_all_settings()
    {

        //Get options for attributes
        $attributes = $this->getAllTerms();

        /**
         * Check the current section is what we want
         **/
        $settings_slider = array();

        $settings_slider[] = array(
            'name'     => __('Fyndiq', 'fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );

        // Add Title to the Settings
        $settings_slider[] = array(
            'name' => __('General Settings', 'fyndiq'),
            'type' => 'title',
            'desc' => __('The following options are used to configure Fyndiq', 'fyndiq'),
            'id' => 'wcfyndiq'
        );

        // Add second text field option
        $settings_slider[] = array(

            'name' => __('Username', 'fyndiq'),
            'desc_tip' => __('This is the username you use for login on Fyndiq Merchant', 'fyndiq'),
            'id' => 'wcfyndiq_username',
            'type' => 'text',
            'desc' => __('Must be your username', 'fyndiq'),

        );

        // Add second text field option
        $settings_slider[] = array(

            'name' => __('API-token', 'fyndiq'),
            'desc_tip' => __('This is the API V2 Token on Fyndiq', 'fyndiq'),
            'id' => 'wcfyndiq_apitoken',
            'type' => 'text',
            'desc' => __('Must be API v2 token', 'fyndiq'),
        );


        //Price Percentage
        $settings_slider[] = array(

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
        $settings_slider[] = array(

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

        if (isset($_GET['set_sku'])) {
            // Add SKU picker
            $settings_slider[] = array(

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
                'desc' => __('If this value is changed, products already existing on Fyndiq will be removed and uploaded again and orders might not be able to be imported with old SKU.', 'fyndiq'),
            );
        }

        // Add currency setting
        $settings_slider[] = array(

            'name' => __('Used Currency', 'fyndiq'),
            'desc_tip' => __(
                'Choose currency to be used for Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_currency',
            'type' => 'select',
            'options' => $this->currencies,
            'desc' => __('This must be picked accurate', 'fyndiq'),

        );

        //Minimum Quantity limit
        $settings_slider[] = array(

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
        $settings_slider[] = array(

            'name' => __('Enable Orders', 'fyndiq'),
            'desc_tip' => __(
                'This will disable all order logic for Fyndiq',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_order_enable',
            'type' => 'select',
            'options' => array(
                self::ORDERS_ENABLE => __('Enable', 'fyndiq'),
                self::ORDERS_DISABLE => __('Disable', 'fyndiq'),
            ),
            'desc' => __('Default is to have orders enabled', 'fyndiq'),


            // Add order status setting
            $settings_slider[] = array(

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
            ));

        $settings_slider[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        $settings_slider[] = array(
            'name'     => __('Field Mappings', 'fyndiq'),
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );


        // Add Description picker
        $settings_slider[] = array(
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
        $settings_slider[] = array(
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
        $settings_slider[] = array(
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
        $settings_slider[] = array(
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

        // Map Field for MPN
        $settings_slider[] = array(
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

        $settings_slider[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        return apply_filters('wc_settings_tab_wcfyndiq', $settings_slider);
    }


}
