<?php
/**
 * Code for columns on the product page
 *
 */


/**
 * productAddColumn
 *
 * Adds the fyndiq_export column to the page
 */
add_filter('manage_edit-product_columns', function ($defaults) {
	$defaults['fyndiq_export'] = __('Fyndiq', 'fyndiq');
	return $defaults;
});

/**
 * productColumnSortable
 *
 * Marks columns in array as sortable
 */
add_filter('manage_edit-product_sortable_columns', function() {
	return array(
		'fyndiq_export' => 'fyndiq_export',
	);
});

/**
 * productColumnSortBy
 *
 * Allows the user to sort products by whether they are exported to Fyndiq or not
 */
add_action('pre_get_posts', function($query) {
	if (!is_admin()) {
		return;
	}
	$orderby = $query->get('orderby');
	if ('fyndiq_export' == $orderby) {
		$query->set('meta_key', '_fyndiq_export');
		$query->set('orderby', 'meta_value');
	}
	if ('fyndiq_status' == $orderby) {
		$query->set('meta_key', '_fyndiq_status');
		$query->set('orderby', 'meta_value');
	}
});


/**
 *
 * productColumnExport
 *
 * Adds the logic behind what is displayed in the custom column that we've added
 */
add_action('manage_product_posts_custom_column', function($column, $postid) {
	$product = new FmProduct($postid);

	if ($column == 'fyndiq_export') {
		if ($product->isProductExportable($product)) {
			$exported = get_post_meta($postid, '_fyndiq_export', true);
			if ($exported != '') {
				if ($exported == EXPORTED) {
					_e('Exported', 'fyndiq');
				} else {
					_e('Not exported', 'fyndiq');
				}
			} else {
				update_post_meta($postid, '_fyndiq_export', NOT_EXPORTED);
				_e('Not exported', 'fyndiq');
			}
		} else {
			_e('Can\'t be exported', 'fyndiq');
		}
	}
	if ($column == 'fyndiq_status') {
		$status = get_post_meta($postid, '_fyndiq_status', true);
		$exported = get_post_meta($postid, '_fyndiq_export', true);

		if ($exported != '' && $status != '') {
			if ($status == FmProductHelper::STATUS_PENDING) {
				_e('Pending', 'fyndiq');
			} elseif ($status == FmProductHelper::STATUS_FOR_SALE) {
				_e('For Sale', 'fyndiq');
			}
		} else {
			_e('-', 'fyndiq');
		}
	}
}, 5, 2);
