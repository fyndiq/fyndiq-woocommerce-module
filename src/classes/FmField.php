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
    const SHOW_CONTENT_PRIORITY = 70;

   //Making an instance of this class just attaches the hooks.
   function __construct()
   {
    add_action('woocommerce_product_write_panels', array(&$this, 'fyndiq_product_tab_content'), self::SHOW_CONTENT_PRIORITY);
   }


    /**
     *
     * This is the hooked function for fields on the product pages
     *
     */
    static public function fyndiq_product_tab_content()
    {
        $fmOutput = new FyndiqOutput();

        $product = new FmProduct(FmProduct::getWordpressCurrentProductId());

        // Renders the Fyndiq tab
        $fmOutput->output('<div id="fyndiq_tab" class="panel woocommerce_options_panel"><div class="fyndiq_tab">');

        // Renders a notification if the product is not exportable and dies
        if (!$product->isProductExportable()) {
            $fmOutput->output(sprintf(
                '<div class="options_group"><p>%s</p></div>',
                __('Can\'t export this product to Fyndiq', 'fyndiq')
            ));
            return;
        }

        // Render the div wrapper for the fields
        $fmOutput->output('<div class="options_group">');

        // 'Export to Fyndiq' checkbox
        FmField::fyndiq_generate_field(FmProduct::FYNDIQ_EXPORT_META_FIELD, array(
            'type' => 'checkbox',
            'class' => array('form-field', 'input-checkbox'),
            'label' => __('Export to Fyndiq', 'fyndiq'),
            'description' => __('mark this as true if you want to export to Fyndiq', 'fyndiq'),
        ), (bool)$product->getIsExported());

        // Absolute price text box
        FmField::fyndiq_generate_field(FmProduct::FYNDIQ_ABSOLUTE_PRICE_FIELD, array(
            'type' => 'text',
            'class' => array('form-field', 'short'),
            'label' => __('Fyndiq Absolute Price', 'fyndiq'),
            'description' => __(
                'Set this price to make this the price to be set on the product when exporting to Fyndiq',
                'fyndiq'
            ),
            'required' => false,
        ), (bool)$product->getAbsolutePrice());

        // Close the wrapper for the rendered code
        $fmOutput->output('</div></div></div>');
    }

    /**
     * This generates the code for fields, compensating for old versions
     *
     * @param $fieldName - the name of the field to be added
     * @param $array - the array that would usually be passed to woocommerce_form_field()
     * @param $value - the value of the field
     */
    static public function fyndiq_generate_field($fieldName, $array, $value)
    {
        $fmOutput = new FyndiqOutput();

        if (version_compare(FmHelpers::get_woocommerce_version(), '2.3.8') >= 0) {
            woocommerce_form_field($fieldName, $array, $value);
            return;
        }
        $fmOutput->output(sprintf("
                <p class='form-field' 'id'=%s>
                    <label for='%s'>%s</label>
                    <input type='%s' class='input-%s' name='%s' id='%s value='%s'/>
                    <span class='description'>" . $array['description'] . "</span>
                </p>"), $fieldName, $fieldName, $array['label'], $array['type'], $array['type'], $fieldName, $fieldName, $fieldName, $array['description']);
    }


}
