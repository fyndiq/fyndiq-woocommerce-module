<?php

/**
 * Product object (single orders only)
 *
 * @todo: product bundle support?
 *
 */
class FmProduct
{

    const EXPORTED = 'exported';
    const NOT_EXPORTED = 'not exported';
    const NOTICES = 'fyndiq_notices';

    private $post;

    public function __construct($postID)
    {
        $this->post = get_post($postID);
    }

    public function getProductObject()
    {
        return get_product($this->post->ID);
    }

    public function getPostID()
    {
        return $this->post->ID;
    }

    public function isProductExportable()
    {
        $product = $this->getProductObject();
        return (!$product->is_downloadable() && !$product->is_virtual() && !$product->is_type('external') && !$product->is_type('grouped'));
    }

    public function isProductExported()
    {
        return (bool)get_post_meta($this->getPostID(), '_fyndiq_export', self::EXPORTED);
    }


    public function exportToFyndiq()
    {

        /*This only adds post meta if it doesn't exist. Otherwise, the if statement criteria itself sets the
         *post meta through update_post_meta
         */
        if (!update_post_meta($this->getPostID(), '_fyndiq_export', self::EXPORTED)) {
            add_post_meta($this->getPostID(), '_fyndiq_export', self::EXPORTED, true);
        }

        $percentage = get_post_meta($this->getPostID(), '_fyndiq_price_percentage', true);
        if (empty($percentage)) {
            update_post_meta($this->getPostID(), '_fyndiq_price_percentage', get_option('wcfyndiq_price_percentage'));
        }
    }

    public function removeFromFyndiq()
    {
        if (!update_post_meta($this->getPostID(), '_fyndiq_export', self::NOT_EXPORTED)) {
            add_post_meta($this->getPostID(), '_fyndiq_export', self::NOT_EXPORTED, true);
        };
    }
}
