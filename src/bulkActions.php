<?php
/**
 *
 * bulkActionDispatcher, taking various actions and routing them to the appropriate function
 *
 * TODO: get all bulk actions to use the dispatcher
 *
 */
add_action('load-edit.php', function () {
    switch (getAction('WP_Posts_List_Table')) {
        case 'fyndiq_mark_orders':
            fyndiq_orders_mark_bulk_action();
            break;
        case 'fyndiq_unmark_orders':
            fyndiq_orders_unmark_bulk_action();
            break;
        case 'fyndiq_delivery':
            fyndiq_order_delivery_note_bulk_action();
            break;
        default:
            break;
    }
}
);


/**
 *
 * Mark multiple orders as handled (fyndiq_orders_mark) - is called by dispatcher
 *
 */
function fyndiq_orders_mark_bulk_action()
{
    if (getRequestPost() > 0) {
        $posts = array();
        foreach (getRequestPost() as $post) {
            $posts[] = array(
                'id' => $post,
                'marked' => 1
            );
        }
        updateHandledStatuses($posts);
    }
}


/**
 *
 * Mark multiple orders as not handled (fyndiq_orders_unmark) - is called by dispatcher
 *
 */
function fyndiq_orders_unmark_bulk_action()
{
    if (getRequestPost() > 0) {
        $posts = array();
        foreach (getRequestPost() as $post) {
            $posts[] = array(
                'id' => $post,
                'marked' => 0
            );
        }
        updateHandledStatuses($posts);
    }
}

/**
 *
 * doBulkActionMessages
 *
 * writes contents of $_SESSION['bulkMessage'] as a message to the user
 *
 */
add_action('admin_notices', function () {
    if (isset($_SESSION['bulkMessage']) && $GLOBALS['pagenow'] === 'edit.php') {
        $GLOBALS['fmOutput']->output('<div class="updated"><p>' . $_SESSION['bulkMessage'] . '</p></div>');
        unset($_SESSION['bulkMessage']);
    }
});


/**
 *
 * productExportBulkAction
 *
 * The bulk action for doing product exports
 *
 *
 * TODO: split this up into a function that doesn't repeat itself/uses the dispatcher
 *
 */
add_action('load-edit.php', function () {
    $action = getAction('WP_Posts_List_Table');

    switch ($action) {
        case 'fyndiq_export':
            $report_action = 'fyndiq_exported';
            $exporting = true;
            break;
        case 'fyndiq_no_export':
            $report_action = 'fyndiq_removed';
            $exporting = false;
            break;
        default:
            return;
    }

    $changed = 0;
    $post_ids = array();
    $posts = getRequestPost();
    if (!is_null($posts)) {
        if ($exporting) {
            foreach ($posts as $post_id) {
                $product = new FmProduct($post_id);
                if ($product->isProductExportable()) {
                    $product->exportToFyndiq();
                    $post_ids[] = $post_id;
                    $changed++;
                }
            }
        } else {
            foreach ($posts as $post_id) {
                $product = new FmProduct($post_id);
                if ($product->isProductExportable()) {
                    $product->removeFromFyndiq();
                    $post_ids[] = $post_id;
                    $changed++;
                }
            }
        }
    }
    return bulkRedirect($report_action, $changed, $post_ids);
});


/**
 *
 * bulkExportNotices
 *
 * Shows notices related to bulk export/deletion from Fyndiq
 *
 * TODO: The functions responsible for the messages ought to use the $_SESSION['bulkMessage'] variable instead
 *
 */
add_action('admin_notices', function () {
    global $post_type, $pagenow;

    if ($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_removed']) && (int)$_REQUEST['fyndiq_removed']) {
        $_SESSION['bulkMessage'] = sprintf(
            _n(
                'Products removed from Fyndiq.',
                '%s products removed from Fyndiq.',
                $_REQUEST['fyndiq_removed']
            ),
            number_format_i18n($_REQUEST['fyndiq_removed'])
        );
    }

    if ($pagenow == 'edit.php' && isset($_REQUEST['fyndiq_exported']) && (int)$_REQUEST['fyndiq_exported']) {
        $_SESSION['bulkMessage'] = sprintf(
            _n(
                'Products exported to Fyndiq.',
                '%s products exported to Fyndiq.',
                $_REQUEST['fyndiq_exported']
            ),
            number_format_i18n($_REQUEST['fyndiq_exported'])
        );
    }
});


/**
 *
 * Handles bulk actions for delivery note - called by dispatcher
 *
 * TODO: this probably needs refinement. ask about this.
 *
 */
function fyndiq_order_delivery_note_bulk_action()
{
    try {
        if (!FmHelpers::ordersEnabled()) {
            exit();
        }

        $orders = array(
            'orders' => array()
        );
        if (!isset($_REQUEST['post'])) {
            throw new Exception(__('Pick at least one Order', 'fyndiq'));
        }
        foreach ($_REQUEST['post'] as $order) {
            $meta = get_post_custom($order);
            if (isset($meta['fyndiq_id']) && isset($meta['fyndiq_id'][0]) && $meta['fyndiq_id'][0] != '') {
                $orders['orders'][] = array('order' => intval($meta['fyndiq_id'][0]));
            }
        }

        $ret = FmHelpers::callApi('POST', 'delivery_notes/', $orders, true);

        if ($ret['status'] == 200) {
            $fileName = 'delivery_notes-' . implode('-', $_REQUEST['post']) . '.pdf';
            $file = fopen('php://temp', 'wb+');
            fputs($file, $ret['data']);
            $GLOBALS['fmOutput']->streamFile($file, $fileName, 'application/pdf', strlen($ret['data']));
            fclose($file);
        } else {
            $sendback = add_query_arg(
                array('post_type' => 'shop_order', $report_action => $changed, 'ids' => join(',', $post_ids)),
                ''
            );
            wp_redirect($sendback);
        }
    } catch (Exception $e) {
        $sendback = add_query_arg(
            array('post_type' => 'shop_order', $report_action => $changed, 'ids' => join(',', $post_ids), 'error' => $e->getMessage()),
            ''
        );
        wp_redirect($sendback);
    }
    exit();
}


/**
 *
 * generateBulkActionDropdownJS
 *
 * Adds bulk actions to the dropdown by reading array and generating relevant JS
 *
 */

add_action('admin_footer-edit.php', function () {
    global $post_type;

    //Define bulk actions for the various page types
    $bulkActionArray = array(
        'product' => array(
            'fyndiq_export' => __('Export to Fyndiq', 'fyndiq'),
            'fyndiq_no_export' => __('Remove from Fyndiq', 'fyndiq'),

        ),
        'shop_order' => array(
            'fyndiq_delivery' => __('Get Fyndiq Delivery Note', 'fyndiq'),
            'fyndiq-order-import' => __('Import From Fyndiq', 'fyndiq'),
            'fyndiq_mark_orders' => __('Mark order(s) as handled', 'fyndiq'),
            'fyndiq_unmark_orders' => __('Mark order(s) as not handled', 'fyndiq')
        )
    );


    //We need this JS header in any case. Initialises output var too. TODO: why is the IDE marking this as wrong?
    $scriptOutput = '<script type="text/javascript">jQuery(document).ready(function () {';


    //Goes through the corresponding array for the page type and writes JS needed for dropdown
    foreach ($bulkActionArray[$post_type] as $key => $value) {
        $scriptOutput .= "  jQuery('<option>').val('$key').text('$value').appendTo('select[name=\"action\"]');
                                    jQuery('<option>').val('$key').text('$value').appendTo('select[name=\"action2\"]');";
    }


    //This adds a button for importing stuff from fyndiq TODO: ask about this - it probably shouldn't be there
    switch ($post_type) {
        case 'shop_order': {
            if (FmHelpers::ordersEnabled()) {
                $scriptOutput .= "if( jQuery('.wrap h2').length && jQuery(jQuery('.wrap h2')[0]).text() != 'Filter posts list' ) {
                                        jQuery(jQuery('.wrap h2')[0]).append(\"<a href='#' id='fyndiq-order-import' class='add-new-h2'>" .
                    $bulkActionArray[$post_type]['fyndiq-order-import'] . "</a>\");
                                    } else if (jQuery('.wrap h1').length ){
                                        jQuery(jQuery('.wrap h1')[0]).append(\"<a href='#' id='fyndiq-order-import' class='page-title-action'>" .
                    $bulkActionArray[$post_type]['fyndiq-order-import'] . "</a>\");
                                    }";
            }
        }
            break;
    }

    //We also need this footer in all cases too
    $scriptOutput .= "});</script>";

    $GLOBALS['fmOutput']->output($scriptOutput);
});
