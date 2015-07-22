<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 08/07/15
 * Time: 13:22
 */
class FmProduct
{

    const STATUS_PENDING = 'PENDING';
    const STATUS_FOR_SALE = 'FOR_SALE';

    public function getExportedProducts()
    {
        $args = array(
            'numberposts' => -1,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'post_type' => 'product',
            'post_status' => 'publish',
            'suppress_filters' => true,
            'meta_key' => '_fyndiq_export',
            'meta_value' => 'exported'
        );
        return get_posts($args);
    }

    public function updateStatus($product_id, $status)
    {
        return update_post_meta($product_id, '_fyndiq_status', $status);
    }

    public function updateStatusAllProducts($status)
    {
        $posts_array = $this->getExportedProducts();
        foreach ($posts_array as $product) {
            $this->updateStatus($product->ID, $status);
        }
    }
}
