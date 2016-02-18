<?php

/**
 * Class FmProduct
 *
 * Object model for products
 */
class FmProduct extends FmPost
{

    const STATUS_PENDING = 'PENDING';
    const STATUS_FOR_SALE = 'FOR_SALE';

    const EXPORTED = 'exported';
    const NOT_EXPORTED = 'not exported';
    const NOTICES = 'fyndiq_notices';

    const FYNDIQ_PRICE_PERCENTAGE_META_FIELD = '_fyndiq_price_percentage';
    const FYNDIQ_EXPORT_META_FIELD = '_fyndiq_export';

    public function getProductObject()
    {
        return get_product($this->getPostID());
    }


    public function isProductExportable()
    {
        $product = $this->getProductObject();
        return (!$product->is_downloadable() && !$product->is_virtual() && !$product->is_type('external') && !$product->is_type('grouped'));
    }

    public function isProductExported()
    {
        return (bool)get_post_meta($this->getPostID(), self::FYNDIQ_EXPORT_META_FIELD, self::EXPORTED);
    }


    private function setInternalExportedStatus($isSet)
    {
        switch ($isSet) {
            case true:
            {
                if (!$this->setMetaData(self::FYNDIQ_EXPORT_META_FIELD, self::EXPORTED)) {
                    $this->setMetaData(self::FYNDIQ_EXPORT_META_FIELD, self::EXPORTED, 'add');
                }
                break;
            }

            case false:
            {
                if (!$this->setMetaData(self::FYNDIQ_EXPORT_META_FIELD, self::NOT_EXPORTED)) {
                    $this->setMetaData(self::FYNDIQ_EXPORT_META_FIELD, self::NOT_EXPORTED, 'add');
                }
                break;
            }

            default:
            {
                return null;
            }
        }
    }

    public function exportToFyndiq()
    {

        /*This only adds post meta if it doesn't exist. Otherwise, the if statement criteria itself sets the
         *post meta through update_post_meta
         */
        $this->setInternalExportedStatus(true);

        $percentage = get_post_meta($this->getPostID(), self::FYNDIQ_PRICE_PERCENTAGE_META_FIELD, true);
        if (empty($percentage)) {
            $this->setMetaData(self::FYNDIQ_PRICE_PERCENTAGE_META_FIELD, get_option('wcfyndiq_price_percentage'));
        }
    }

    public function removeFromFyndiq()
    {
        $this->setInternalExportedStatus(false);
    }


    /**
     * Here be dragons. By dragons, I mean static methods.
     */

    static public function getExportedProducts()
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

    static public function updateStatus($product_id, $status)
    {
        return update_post_meta($product_id, '_fyndiq_status', $status);
    }

    static public function updateStatusAllProducts($status)
    {
        $posts_array = FmProduct::getExportedProducts();
        foreach ($posts_array as $product) {
            FmProduct::updateStatus($product->ID, $status);
        }
    }

}
