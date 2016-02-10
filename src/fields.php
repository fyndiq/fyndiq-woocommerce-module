<?php
/**
 *
 * Brings in the code for adding/rendering fields, contains the code for saving field data
 *
 */


/**
 * This generates the code for fields, compensating for old versions
 *
 * @param $fieldName - the name of the field to be added
 * @param $array - the array that would usually be passed to woocommerce_form_field()
 * @param $value - the value of the field
 */
function fyndiq_generate_field($fieldName, $array, $value)
{
    if (version_compare(FmHelpers::get_woocommerce_version(), '2.3.8') >= 0) {
        woocommerce_form_field($fieldName, $array, $value);
    } else {
        $GLOBALS['fmOutput']->output("<p class='form-field' id=$fieldName>
                <label for=\'$fieldName\'>" . $array['label'] . "</label>
				<input type='" . $array['type'] . "' class='input-" . $array['type'] . "' name='$fieldName' id='$fieldName' value='$value'>
                <span class='description'>" . $array['description'] . "</span></p>");
    }
}

require_once('fields/order.php');
require_once('fields/product.php');
