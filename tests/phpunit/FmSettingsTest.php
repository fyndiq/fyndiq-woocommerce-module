<?php

/**
 *
 * File for handling .
 *
 */
class FmSettingsTest extends WP_UnitTestCase
{
    function testFyndiqAllSettings_correctOutput()
    {
        $settings = array();
        $return = FmSettings::fyndiqAllSettings($settings, 'wcfyndiq');

        $expected = array(
            array('name' => 'Fyndiq',
                'type' => 'title',
                'desc' => '',
                'id' => 'wc_settings_wcfyndiq_section_title'),
            array('name' => 'General Settings',
                'type' => 'title',
                'desc' => 'The following options are used to configure Fyndiq',
                'id' => 'wcfyndiq'),
            array('name' => 'Username',
                'desc_tip' => 'This is the username you use for login on Fyndiq Merchant',
                'id' => 'wcfyndiq_username',
                'type' => 'text',
                'desc' => 'Must be your username'),
            array('name' => 'API-token',
                'desc_tip' => 'This is the API V2 Token on Fyndiq',
                'id' => 'wcfyndiq_apitoken',
                'type' => 'text',
                'desc' => 'Must be API v2 token'),
            array('name' => 'Global Price Percentage',
                'desc_tip' => 'The percentage that will be removed from the price when sending to fyndiq.',
                'id' => 'wcfyndiq_price_percentage',
                'type' => 'text',
                'default' => '10',
                'desc' => 'Can be 0 if the price should be the same as in your shop.'),
            array('name' => 'Global Price Discount',
                'desc_tip' => 'The amount that will be removed from the price when sending to fyndiq.',
                'id' => 'wcfyndiq_price_discount',
                'type' => 'text',
                'default' => '0',
                'desc' => 'Can be 0 if the price should not change'),
            array('name' => 'Used Currency',
                'desc_tip' => 'Choose currency to be used for Fyndiq.',
                'id' => 'wcfyndiq_currency',
                'type' => 'select',
                'options' => array('SEK' => 'SEK', 'EUR' => 'EUR'),
                'desc' => 'This must be picked accurate'),
            array('name' => 'Minimum Quantity Limit',
                'desc_tip' => 'this quantity will be reserved by you and will be removed from the quantity that is sent to Fyndiq.',
                'id' => 'wcfyndiq_quantity_minimum',
                'type' => 'text',
                'default' => '0',
                'desc' => 'Stay on 0 if you want to send all stock to Fyndiq.'),
            array('name' => 'Enable Orders',
                'desc_tip' => 'This will disable all order logic for Fyndiq',
                'id' => 'wcfyndiq_order_enable',
                'type' => 'select',
                'options' => array(
                    2 => 'Enable',
                    1 => 'Disable',
                ),
                'desc' => 'Default is to have orders enabled'),
            array('name' => 'Order Status',
                'desc_tip' => 'When a order is imported from fyndiq, this status will be applied.',
                'id' => 'wcfyndiq_create_order_status',
                'type' => 'select',
                'options' => array(
                    'completed' => 'completed',
                    'processing' => 'processing',
                    'pending' => 'pending',
                    'on-hold' => 'on-hold'
                ),
                'desc' => 'This must be picked accurate'),
        );


        $expected[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        $expected[] = array(
            'name'     => 'Field Mappings',
            'type'     => 'title',
            'desc'     => '',
            'id'       => 'wc_settings_wcfyndiq_section_title'
        );


        // Add Description picker
        $expected[] = array(
            'name' => 'Description to use',
            'desc_tip' => __(
                'Set how you want your description to be exported to Fyndiq.',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_description_picker',
            'type' => 'select',
            'options' => array(
                FmExport::DESCRIPTION_LONG => 'Long Description',
                FmExport::DESCRIPTION_SHORT => 'Short Description',
                FmExport::DESCRIPTION_SHORT_LONG => 'Short and Long Description',
            ),
            'desc' => 'Default is Long Description',
        );

        // Map Field for EAN
        $expected[] = array(
            'name' => 'EAN',
            'desc_tip' => __(
                'EAN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_ean',
            'type' => 'select',
            'options' => array('' => ''),
            'desc' => 'This must be picked accurate',
        );

        // Map Field for ISBN
        $expected[] = array(
            'name' => 'ISBN',
            'desc_tip' => __(
                'ISBN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_isbn',
            'type' => 'select',
            'options' => array('' => ''),
            'desc' => 'This must be picked accurate',
        );

        // Map Field for MPN
        $expected[] = array(
            'name' => 'MPN',
            'desc_tip' => __(
                'MPN',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_mpn',
            'type' => 'select',
            'options' => array('' => ''),
            'desc' => 'This must be picked accurate',
        );

        // Map Field for MPN
        $expected[] = array(
            'name' => 'Brand',
            'desc_tip' => __(
                'Brand',
                'fyndiq'
            ),
            'id' => 'wcfyndiq_field_map_brand',
            'type' => 'select',
            'options' => array('' => ''),
            'desc' => 'This must be picked accurate',
        );

        $expected[] = array(
            'type' => 'sectionend',
            'id' => 'wc_settings_wcfyndiq_section_end'
        );

        $this->assertEquals($expected, $return);
    }

    function testAddSettingsTab() {
        $expected = array('wcfyndiq' => __('Fyndiq', 'fyndiq'));
        $return = FmSettings::addSettingsTab(array());
        $this->assertEquals($expected, $return);
    }

    function testPluginActionLink() {
        $expected = array('<a href="http://example.org/wp-admin/admin.php?page=wc-settings&#038;tab=products&#038;section=wcfyndiq">Settings</a>');
        $return = FmSettings::pluginActionLink(array());
        $this->assertEquals($expected, $return);
    }
}
