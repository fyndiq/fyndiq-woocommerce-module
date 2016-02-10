<?php
/**
 *
 * Code for columns on the orders page
 *
 */

if (FmHelpers::ordersEnabled()) {

	/**
	 * orderAddColumn
	 *
	 * Adds the fyndiq_order column to the page
	 */
	add_filter('manage_edit-shop_order_columns', function($defaults) {
		$defaults['fyndiq_order'] = __('Fyndiq Order', 'fyndiq');
		return $defaults;
	});


	/**
	 * orderColumnExport
	 *
	 * Adds the logic behind what is displayed in the custom column that we've added
	 */
	add_action('manage_shop_order_posts_custom_column', function($column, $postid) {
		$product = new FmOrder($postid);
		if ($column == 'fyndiq_order') {
			$fyndiq_order = $product->getFyndiqOrderID();
			if ($fyndiq_order != '') {
				$GLOBALS['fmOutput']->output($fyndiq_order);
			} else {
				$product->setFyndiqOrderID('-');
				$GLOBALS['fmOutput']->output('-');
			}
		}
	},5, 2);


	/**
	 * orderColumnSortable
	 *
	 * Marks columns in array as sortable
	 */
	add_filter('manage_edit-shop_order_sortable_columns', function() {
		return array('fyndiq_ord er' => 'fyndiq_order');
	});

	/**
	 * orderColumnSortBy
	 *
	 * Allows the user to sort orders by their Fyndiq order ID
	 */
	add_action('pre_get_posts', function($query) {
		if (!is_admin()) {
			return;
		}
		$orderby = $query->get('orderby');
		if ('fyndiq_order' == $orderby) {
			$query->set('meta_key', 'fyndiq_id');
			$query->set('orderby', 'meta_value_integer');
		}
	});

}
