<?php

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

/**
 * Class FmField
 *
 * Handles adding of fields to WooCommerce
 */
class FmField
{
    /**
     * The render order priority of the product tab vs. other tabs
     */
    const SHOW_CONTENT_PRIORITY = 70;

    /**
     * Sets all WordPress hooks related to the Fields
     *
     * @return bool - Always returns true because add_action() aways returns true TODO: abstraction layer
     */
    public static function setHooks()
    {
        add_action(
            'woocommerce_product_write_panels',
            array(__CLASS__, 'fyndiqProductTab'),
            self::SHOW_CONTENT_PRIORITY
        );
        add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'addOrderField'));
        return true;
    }

    /**
     * Hooked to 'woocommerce_product_write_panels' - renders the product tab
     *
     * @return bool - false if the product is not exportable. Otherwise, true.
     * @throws - TODO: find where the exception is thrown here
     */
    public static function fyndiqProductTab()
    {
        $fmOutput = new FyndiqOutput();

        $product = new FmProduct(FmProduct::getWordpressCurrentPostId());

        // Renders the Fyndiq tab
        $fmOutput->output('<div id="fyndiq_tab" class="panel woocommerce_options_panel"><div class="fyndiq_tab">');

        // Renders a notification if the product is not exportable and dies
        if (!$product->isProductExportable()) {
            $fmOutput->output(
                sprintf(
                    '<div class="options_group"><p>%s</p></div>',
                    __('This product can not be exported to Fyndiq', WC_Fyndiq::TEXT_DOMAIN)
                )
            );
            return false;
        }

        // Render the div wrapper for the fields
        $fmOutput->output('<div class="options_group">');

        // 'Export to Fyndiq' checkbox
        self::fyndiqGenerateField(
            FmProduct::FYNDIQ_EXPORT_META_KEY,
            array(
            'type' => 'checkbox',
            'class' => array('form-field', 'input-checkbox'),
            'label' => _x('Export to Fyndiq', 'Label for a checkbox', WC_Fyndiq::TEXT_DOMAIN),
            ),
            (bool)$product->getIsExported()
        );

        // Absolute price text box
        self::fyndiqGenerateField(
            FmProduct::FYNDIQ_ABSOLUTE_PRICE_FIELD,
            array(
            'type' => 'text',
            'class' => array('form-field', 'short'),
            'label' => _x('Fyndiq Absolute Price', 'Label for a checkbox', WC_Fyndiq::TEXT_DOMAIN),
            'description' => __(
                'This field, when set, overrides any other pricing for the product and is used as the sale price on Fyndiq',
                WC_Fyndiq::TEXT_DOMAIN
            ),
            'required' => false,
            ),
            $product->getAbsolutePrice()
        );

        // Close the wrapper for the rendered code
        $fmOutput->output('</div></div></div>');
        return true;
    }

    /**
     * This generates the code for fields, compensating for old versions
     *
     *  @param string $fieldName - the name of the field to be added
     *  @param array  $array     - the array that would usually be passed to woocommerce_form_field()
     *  @param string $value     - the value of the field
     *
     *  @return object|null - woocommerce_form_field can return or echo the output HTML depending on arguments
     */
    public static function fyndiqGenerateField($fieldName, $array, $value)
    {
        $fmOutput = new FyndiqOutput();

        if (version_compare(WC()->version, '2.3.8') >= 0) {
            return woocommerce_form_field($fieldName, $array, $value);
        }
        return $fmOutput->output(
            sprintf(
                "<p class='form-field' 'id'=%s>
                    <label for='%s'>%s</label>
                    <input type='%s' class='input-%s' name='%s' id='%s value='%s'/>
                    <span class='description'>" . $array['description'] . "</span>
                </p>",
                $fieldName,
                $fieldName,
                $array['label'],
                $array['type'],
                $array['type'],
                $fieldName,
                $fieldName,
                $fieldName,
                $array['description']
            )
        );
    }

    /**
     * Hooked to 'woocommerce_admin_order_data_after_order_details' - generates the order handled checkbox
     *
     * @return bool - always true
     */
    public static function addOrderField()
    {
        $order = new FmOrder(FmOrder::getWordpressCurrentPostID());

        self::fyndiqGenerateField(
            FmOrder::FYNDIQ_HANDLED_ORDER_META_FIELD,
            array(
            'type' => 'checkbox',
            'class' => array('input-checkbox'),
            'label' => _x('Order handled', 'Label for checkbox', WC_Fyndiq::TEXT_DOMAIN),
            'description' => __('Report this order as handled to Fyndiq', WC_Fyndiq::TEXT_DOMAIN),
            ),
            (bool)$order->getIsHandled()
        );
        return true;
    }
}
