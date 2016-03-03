<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

/**
 * Class FmProduct
 *
 * Object model for products
 */
class FmProduct extends FmPost
{

    /**
     * The status code for items pending acceptance on Fyndiq
     */
    const STATUS_PENDING = 'PENDING';

    /**
     * The status code for items being sold on Fyndiq
     */
    const STATUS_FOR_SALE = 'FOR_SALE';

    /**
     * The status code for items that are selected to be exported to Fyndiq
     */
    const EXPORTED = TRUE;

    /**
     * The status code for items that are not selected to be exported to Fyndiq
     */
    const NOT_EXPORTED = FALSE;

    /**
     * Flag set in $_SESSION for indicating that notices are to be shown on the admin pages
     */
    const NOTICES = 'fyndiq_notices';

    /**
     * Name of the metadata field that contains the Fyndiq percentage price discount
     */
    const FYNDIQ_PRICE_PERCENTAGE_META_KEY = '_fyndiq_price_percentage';

    /**
     * Name of the metadata field that contains whether the product is exported to Fyndiq
     */
    const FYNDIQ_EXPORT_META_KEY = '_fyndiq_export';

    /**
     * Name of the metadata field that contains the product status on Fyndiq
     */
    const FYNDIQ_STATUS_META_KEY = '_fyndiq_status';

    /**
     * Name of the metadata field that contains the absolute price (price after discount) of the item sold on Fyndiq
     */
    const FYNDIQ_ABSOLUTE_PRICE_FIELD = '_fyndiq_price_absolute';

    /**
     * Gets the product object that WooCommerce uses
     *
     * @return object(WC_Product)|void - The WC_Product object for the product or void for a bad ID
     */
    public function getWooCommerceProductObject()
    {
         return wc_get_product($this->getPostID());
    }


    /**
     * Uses various criteria defined in array to decide whether instantiated product is exportable
     *
     * @return bool - Returns true if product is exportable, inverse applies
     * @throws exception - If the function is given a group of criterion that it cannot handle
     */
    public function isProductExportable()
    {
        $product = $this->getWooCommerceProductObject();

        /*
         * Defines the criteria we use to decide whether a product is exportable. This would ideally be a
         * constant, but that's only possible in PHP 5.6
         *
         * forbiddenProperties executes functions of names contained within and returns false if any
         * of the executed return true
         *
         * forbiddenTypes substitutes into is_type(), returning false if any of these calls return true
         */
        $exportCriteria = array(
            'forbiddenProperties' => array(
                'is_downloadable',
                'is_virtual'
            ),
            'forbiddenTypes' => array(
                'external',
                'grouped'
            )
        );

        //Iterate through criteria, giving us categoryName (e.g. forbiddenProperties) => array()
        foreach ($exportCriteria as $exportCriterionCatName => $exportCriterionArray) {
            switch ($exportCriterionCatName) {
                case 'forbiddenProperties': {
                    foreach ($exportCriterionArray as $forbiddenProperty)
                        if($product->$forbiddenProperty($product)) {
                            return false;
                        };
                    break;
                }

                case 'forbiddenTypes': {
                    foreach ($exportCriterionArray as $forbiddenType)
                        if ($product->is_type($forbiddenType)) {
                            return false;
                        }
                    break;
                }

                default:
                    throw new Exception('Bad export criterion category supplied to isProductExportable()');
            }
        }
        return true;
    }

    /**
     * Checks the post metadata to see if the Product is marked as exported.
     * Takes into account that a flag in $_POST might have been set.
     *
     * @param bool $saving - TRUE if a post is being saved
     * @return bool - Returns true if product is exported, inverse applies
     */
    public function getIsExported($saving = FALSE)
    {
        if ($saving) {
            if (isset($_POST[self::FYNDIQ_EXPORT_META_KEY])) {
                return FmProduct::EXPORTED;
            }
            return FmProduct::NOT_EXPORTED;
        }

        //This code handles legacy situations where EXPORT constants have been strings. Some upgrade code will be written.

        switch ($this->getMetaData(self::FYNDIQ_EXPORT_META_KEY)) {
            case 'exported':
                return FmProduct::EXPORTED;
            case 'not exported':
                return FmProduct::NOT_EXPORTED;
        }
        return (bool)$this->getMetaData(self::FYNDIQ_EXPORT_META_KEY);
    }

    /**
     * Sets the instantiated product as exported (or not according to $value)
     *
     * @param bool $value - True if the product is to be exported, inverse applies.
     * @return bool|int|null - whatever the native WP function spits out @todo - write abstraction class
     */
    public function setIsExported($value)
    {
        //This part ensures that the price percentage is set, and if it isn't set, updates it.
        $percentage = get_post_meta($this->getPostID(), self::FYNDIQ_PRICE_PERCENTAGE_META_KEY, true);
        if (empty($percentage)) {
            $this->setMetaData(self::FYNDIQ_PRICE_PERCENTAGE_META_KEY, get_option('wcfyndiq_price_percentage'));
        }
        
        if (FmError::enforceTypeSafety($value, 'boolean')) {

            return $this->setInternalExportedStatus(true);
        }
            return $this->setInternalExportedStatus(false);
    }


    /**
     * Internal logic for setting whether an item has been exported to Fyndiq or not
     *
     * @param bool $isSet - true if the product is to be marked as exported to Fyndiq, inverse applies
     * @return bool|int|null - whatever the native WP function spits out @todo - write abstraction class
     */
    private function setInternalExportedStatus($isSet)
    {
        //enforceTypeSafety returns passed variable if type is OK
        if (FmError::enforceTypeSafety($isSet, 'boolean')) {
            return $this->setMetaData(self::FYNDIQ_EXPORT_META_KEY, self::EXPORTED);
        }
            return $this->setMetaData(self::FYNDIQ_EXPORT_META_KEY, self::NOT_EXPORTED);
    }

    /**
     * Gets the absolute price (price of item with Fyndiq discount applied) of instantiated product
     *
     * @return int - The absolute price of the product
     */
    public function getAbsolutePrice()
    {
        if (isset($_POST[self::FYNDIQ_ABSOLUTE_PRICE_FIELD])) {
            return (int)$_POST[self::FYNDIQ_ABSOLUTE_PRICE_FIELD];
        }
        return (int)$this->getMetaData(self::FYNDIQ_ABSOLUTE_PRICE_FIELD);
    }

    /**
     * Sets the absolute price (price of item with Fyndiq discount applied) of instantiated product
     *
     * @param int $price - Absolute price of the item
     * @return bool|int|null - whatever the native WP function spits out @todo - write abstraction class
     */
    public function setAbsolutePrice($price)
    {
        return $this->setMetaData(self::FYNDIQ_ABSOLUTE_PRICE_FIELD, FmError::enforceTypeSafety($price, 'integer'));
    }

    /**
     * Sets the status on Fyndiq of instantiated product
     *
     * @param string $status - Status of the item on fyndiq
     * @return bool|int|null - whatever the native WP function spits out @todo - write abstraction class
     */
    public function setStatus($status)
    {
        return $this->setMetaData(self::FYNDIQ_STATUS_META_KEY, FmError::enforceTypeSafety($status, 'string'));
    }

    /**
     * This validates product data and displays an error if
     * it does not follow the fyndiq validation criteria
     *
     * @todo Make this return something meaningful
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
                    )
                );
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
                    )
                );
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
                    )
                );
                    $error = true;
            }

            $postRegularPrice = intval($_POST['_regular_price']);
            $type = $_POST['product-type'];
            if ($type != 'variable' && $postRegularPrice <= 0) {
                FmError::handleError(
                    sprintf(
                        __('Regular Price needs to be set above 0, now it is: %s', 'fyndiq'),
                        $postRegularPrice
                    )
                );
                    $error = true;
            }

            if ($error) {
                $this->setIsExported(self::NOT_EXPORTED);
            }
        }
    }

    /*
     * Here be dragons. By dragons, I mean static methods.
     */


    /**
     * Returns an array of WordPress post objects that are that are exported products
     *
     * @return array(objects) - an array of WordPress post objects
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
            'meta_key' => self::FYNDIQ_EXPORT_META_KEY,
            'meta_value' => 'exported'
        );
        return get_posts($args);
    }


    /**
     * This sets all WordPress hooks related to the product model
     *
     * @return bool - Always returns true because add_action() aways returns true
     */
    public static function setHooks()
    {
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'saveFyndiqProduct'));
        return true;
    }

    /**
     * Hooked action for saving products (woocommerce_process_product_meta)
     *
     * @param int $productId - WordPress post ID of the product
     */
    public static function saveFyndiqProduct($productId)
    {
        $product = new FmProduct($productId);

        $isExported = $product->getIsExported(true);
        $isExported = FmError::enforceTypeSafety($isExported, 'boolean');
        $product->setIsExported($isExported);
        $absolutePrice = $product->getAbsolutePrice();
        $absolutePrice = FmError::enforceTypeSafety($absolutePrice, 'integer');
        $product->setAbsolutePrice($absolutePrice);

        $product->validateProduct();
    }
}
