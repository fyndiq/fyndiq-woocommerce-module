<?php
/**
 * Class FmProduct
 *
 * Object model for products
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmProduct extends FmPost
{

    const STATUS_PENDING = 'PENDING';
    const STATUS_FOR_SALE = 'FOR_SALE';

    const EXPORTED = true;
    const NOT_EXPORTED = false;

    const NOTICES = 'fyndiq_notices';

    const FYNDIQ_PRICE_PERCENTAGE_META_FIELD = '_fyndiq_price_percentage';
    const FYNDIQ_EXPORT_META_FIELD = '_fyndiq_export';
    const FYNDIQ_ABSOLUTE_PRICE_FIELD = '_fyndiq_price_absolute';

    protected function getProductObject()
    {
        return get_product($this->getPostID());
    }


    public function isProductExportable()
    {
        $product = $this->getProductObject();
        return (!$product->is_downloadable() && !$product->is_virtual() && !$product->is_type('external') && !$product->is_type('grouped'));
    }

    public function getIsExported()
    {
        if (isset($_POST['_fyndiq_export'])) {
            return FmProduct::EXPORTED;
        }
        return (bool)get_post_meta($this->getPostID(), self::FYNDIQ_EXPORT_META_FIELD, self::EXPORTED);
    }

    // Sets the product as exported when true is passed
    public function setIsExported($value)
    {
        if ((bool) $value) {
            $this->exportToFyndiq();
        } else {
            $this->removeFromFyndiq();
        }
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

    public function getAbsolutePrice()
    {
        if (isset($_POST[self::FYNDIQ_ABSOLUTE_PRICE_FIELD])) {
            return $_POST[self::FYNDIQ_ABSOLUTE_PRICE_FIELD];
        }
        return $this->getMetaData(self::FYNDIQ_ABSOLUTE_PRICE_FIELD);
    }

    public function setAbsolutePrice($price)
    {
        return $this->setMetaData(self::FYNDIQ_ABSOLUTE_PRICE_FIELD,$price);
    }

    private function exportToFyndiq()
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

    private function removeFromFyndiq()
    {
        $this->setInternalExportedStatus(false);
    }

    /**
     * This is validating product data and show error if
     * it is not following the fyndiq validations
     */
    private function validateProduct()
    {
        if ($this->getIsExported()) {
            $error = false;
            $postTitleLength = mb_strlen($_POST['post_title']);
            if ($postTitleLength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_TITLE] ||
                $postTitleLength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_TITLE]) {
                FmError::handleError(
                    sprintf(
                        __('Title needs to be between %s and %s in length, now it is: %s', 'fyndiq'),
                        FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_TITLE],
                        FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_TITLE],
                        $postTitleLength
                    ));
                $error = true;
            }

            $postDescriptionLength = mb_strlen(FmExport::getDescriptionPOST());
            if ($postDescriptionLength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_DESCRIPTION] ||
                $postDescriptionLength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_DESCRIPTION]) {
                FmError::handleError(
                    sprintf(
                        __('Description needs to be between %s and %s in length, now it is: %s', 'fyndiq'),
                        FyndiqFeedWriter::$minLength[FyndiqFeedWriter::PRODUCT_DESCRIPTION],
                        FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::PRODUCT_DESCRIPTION],
                        $postDescriptionLength
                    ));
                $error = true;
            }

            $postSKULength = mb_strlen($_POST['_sku']);
            if ($postSKULength < FyndiqFeedWriter::$minLength[FyndiqFeedWriter::ARTICLE_SKU] ||
                $postSKULength > FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::ARTICLE_SKU]) {
                FmError::handleError(
                    sprintf(
                        __('SKU needs to be between %s and %s in length, now it is: %s', 'fyndiq'),
                        FyndiqFeedWriter::$minLength[FyndiqFeedWriter::ARTICLE_SKU],
                        FyndiqFeedWriter::$lengthLimitedColumns[FyndiqFeedWriter::ARTICLE_SKU],
                        $postSKULength
                    ));
                $error = true;
            }

            $postRegularPrice = intval($_POST['_regular_price']);
            $type = $_POST['product-type'];
            if ($type != 'variable' && $postRegularPrice <= 0) {
                FmError::handleError(
                    sprintf(
                        __('Regular Price needs to be set above 0, now it is: %s', 'fyndiq'),
                        $postRegularPrice
                    ));
                $error = true;
            }

            if ($error) {
                $this->setIsExported(this::NOT_EXPORTED);
            }
        }
    }

    /**
     * Here be dragons. By dragons, I mean static methods.
     */

    public static function getExportedProducts()
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

    public static function updateStatus($product_id, $status)
    {
        return update_post_meta($product_id, '_fyndiq_status', $status);
    }

    public static function updateStatusAllProducts($status)
    {
        $posts_array = FmProduct::getExportedProducts();
        foreach ($posts_array as $product) {
            FmProduct::updateStatus($product->ID, $status);
        }
    }

    static public function getWordpressCurrentProductId()
    {
        return get_the_ID();
    }

    static public function setHooks() {
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'saveFyndiqProduct'));
    }

    //Hooked action for saving products (woocommerce_process_product_meta)
    static public function saveFyndiqProduct($productId)
    {
        $product = new FmProduct($productId);

        $product->setIsExported($product->getIsExported());
        $product->setAbsolutePrice($product->getAbsolutePrice());

        $product->validateProduct();
    }
}
