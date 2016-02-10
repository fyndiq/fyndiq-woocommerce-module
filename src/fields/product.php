<?php
/**
 *
 * Here lies the code for the added fields to the product pages
 *
 */


/**
 * productSave
 *
 * Handles saving product metadata
 *
 */
add_action('woocommerce_process_product_meta', function($post_id) {
    $woocommerce_checkbox = getExportState();
    $woocommerce_pricepercentage = getPricePercentage();

    update_post_meta($post_id, '_fyndiq_export', $woocommerce_checkbox);

    if ($woocommerce_checkbox == EXPORTED) {
        if (empty($woocommerce_pricepercentage)) {
            update_post_meta($post_id, '_fyndiq_price_percentage', get_option('wcfyndiq_price_percentage'));
        }
    }
    if (!empty($woocommerce_pricepercentage)) {
        update_post_meta($post_id, '_fyndiq_price_percentage', $woocommerce_pricepercentage);
    }

    fyndiq_product_validate($post_id);
});



/**
 *
 * fyndiqProductTab
 *
 */
add_action('woocommerce_product_write_panel_tabs', function() {
    echo '<li class="fyndiq_tab"><a href="#fyndiq_tab">' . __('Fyndiq', 'fyndiq') . '</a></li>';
});


/**
 *
 * productTabContent
 *
 * Responsible for drawing fields on the product pages
 *
 */
add_action('woocommerce_product_write_panels', function () {
    $product = get_product(getPostId());
    $price = $GLOBALS['fmExport']->getPrice($product->id, $product->price);

    echo '<div id="fyndiq_tab" class="panel woocommerce_options_panel"><div class="fyndiq_tab">';

    //Check that we can actually export this item
    if (!isProductExportable($product)) {
        $GLOBALS['fmOutput']->output(sprintf(
            '<div class="options_group"><p>%s</p></div>',
            __('Can\'t export this product to Fyndiq', 'fyndiq')
        ));
        return;
    }
    $GLOBALS['fmOutput']->output('<div class="options_group">');

    // Checkbox for exporting to fyndiq
    fyndiq_generate_field('_fyndiq_export', array(
        'type' => 'text',
        'class' => array('form-field', 'short'),
        'label' => __('Fyndiq Discount Percentage', 'fyndiq'),
        'description' => __(
            'The percentage specific for this product, it will override the globel percentage for this product.',
            'fyndiq')
    ), (get_post_meta($product->id, '_fyndiq_export', true) == EXPORTED) ? 1 : 0);

    //The price percentage for fyndiq for this specific product.
    fyndiq_generate_field('_fyndiq_price_percentage',
        array(
            array(
                'type' => 'checkbox',
                'class' => array('form-field', 'input-checkbox'),
                'label' => __('Export to Fyndiq', 'fyndiq'),
                'description' => __('mark this as true if you want to export to Fyndiq', 'fyndiq')
            ),
            'required' => false), get_post_meta($product->id, '_fyndiq_price_percentage', true));

    $GLOBALS['fmOutput']->output(sprintf(
        '<p>%s %s %s</p></div>',
        __('Fyndiq Price with set Discount percentage: ', 'fyndiq'),
        $price,
        get_woocommerce_currency()
    ));

    echo '</div></div>';
});
