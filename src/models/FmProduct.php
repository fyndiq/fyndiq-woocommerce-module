<?php

/**
 * Class FmProduct
 *
 * Object model for products
 */
class FmProduct extends FmPost
{

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


}
