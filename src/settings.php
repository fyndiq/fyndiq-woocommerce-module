<?php
/**
 *
 * Code related to the module settings and the saving of them
 *
 */

add_action('woocommerce_settings_tabs_wcfyndiq', 'fyndiq_all_settings');
function fyndiq_all_settings() {
    woocommerce_admin_fields(function () {

        //Get options for attributes
        $attributes = getAllTerms();

        /**
         * Check the current section is what we want
         **/
        $settings_slider = array();

        $settings_slider[] = array(
            'name' => __('Fyndiq', 'fyndiq'),
            'type' => 'title',
            'desc' => '',
            'id' => 'wc_settings_wcfyndiq_section_title'
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
                    $GLOBALS['fmExport']::REF_SKU => __('SKU', 'fyndiq'),
                    $GLOBALS['fmExport']::REF_ID => __('Product and Article ID', 'fyndiq'),
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
            'options' => array_combine(FyndiqUtils::$allowedCurrencies, FyndiqUtils::$allowedCurrencies),
            'desc' => __('This must be picked accurate', 'fyndiq'),

        );


        //Price Percentage
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
                ORDERS_ENABLE => __('Enable', 'fyndiq'),
                ORDERS_DISABLE => __('Disable', 'fyndiq'),
            ),
            'desc' => __('Default is to have orders enabled', 'fyndiq'),

        );

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
            'desc' => __('This must be picked accurate', 'fyndiq'),

        );
        $settings_slider[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        $settings_slider[] = array(
            'name' => __('Field Mappings', 'fyndiq'),
            'type' => 'title',
            'desc' => '',
            'id' => 'wc_settings_wcfyndiq_section_title'
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
                $GLOBALS['fmExport']::DESCRIPTION_LONG => __('Long Description', 'fyndiq'),
                $GLOBALS['fmExport']::DESCRIPTION_SHORT => __('Short Description', 'fyndiq'),
                $GLOBALS['fmExport']::DESCRIPTION_SHORT_LONG => __('Short and Long Description', 'fyndiq'),
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
    });
}


/**
 * addSettingsTab
 *
 * Adds the tab for fyndiq in the WC settings page
 *
 */
add_filter('woocommerce_settings_tabs_array', function($settings_tabs) {
    $settings_tabs['wcfyndiq'] = __('Fyndiq', 'fyndiq');
    return $settings_tabs;
}, 50);


/**
 * saveSettings
 *
 * Handles saving the settings for the fyndiq
 */
add_action('woocommerce_update_options_wcfyndiq', function() {
    woocommerce_update_options(fyndiq_all_settings());
    try {
        updateUrls();
    } catch (Exception $e) {
        if ($e->getMessage() == 'Unauthorized') {
            fyndiq_show_setting_error_notice();
        }
    }
});

/**
 * checkSettingsValid
 *
 * Displays an error message if the settings for the module are not valid
 */
add_action('admin_notices', function () {
    if (checkCurrency()) {
        printf(
            '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
            __('Wrong Currency', 'fyndiq'),
            __('Fyndiq only works in EUR and SEK. change to correct currency. Current Currency:', 'fyndiq'),
            get_woocommerce_currency()
        );
    }
    if (checkCountry()) {
        printf(
            '<div class="error"><p><strong>%s</strong>: %s %s</p></div>',
            __('Wrong Country', 'fyndiq'),
            __('Fyndiq only works in Sweden and Germany. change to correct country. Current Country:', 'fyndiq'),
            WC()->countries->get_base_country()
        );
    }
    if (checkCredentials()) {
        $url = admin_url('admin.php?page=wc-settings&tab=wcfyndiq');
        printf(
            '<div class="error"><p><strong>%s</strong>: %s <a href="%s">%s</a></p></div>',
            __('Fyndiq Credentials', 'fyndiq'),
            __('You need to set Fyndiq Credentials to make it work. Do it in ', 'fyndiq'),
            $url,
            __('Woocommerce Settings > Fyndiq', 'fyndiq')
        );
    }
    if (isset($_SESSION[NOTICES])) {
        $notices = $_SESSION[NOTICES];
        foreach ($notices as $type => $noticegroup) {
            $class = 'update' === $type ? 'updated' : $type;
            echo '<div class="fn_message ' . $class . '">';
            echo '<strong>' . __('Fyndiq Validations', 'fyndiq') . '</strong>';
            echo '<ul>';
            foreach ($noticegroup as $notice) :
                echo '<li>' . wp_kses($notice, wp_kses_allowed_html('post')) . '</li>';
            endforeach;
            echo '</ul>';
            echo '<p>' . __('The product will not be exported to Fyndiq until these validations are fixed.', 'fyndiq') . '</p>';
            echo '</div>';
        }
        unset($_SESSION[NOTICES]);
    }
});
