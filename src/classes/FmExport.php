<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

/**
 * Class FmExport - functionality for exporting data to CSV
 */
class FmExport
{

    /** TODO: what does this do? */
    const DELIMITER = ' / ';
    /** TODO: what does this do? */
    const REF_DELIMITER = '-';

    /** TODO: what does this do? */
    const DESCRIPTION_SHORT = 1;

    /** TODO: what does this do? */
    const DESCRIPTION_LONG = 2;

    /** TODO: what does this do? */
    const DESCRIPTION_SHORT_LONG = 3;

    /** TODO: what does this do? */
    const REF_SKU = 1;

    /** TODO: what does this do? */
    const REF_ID = 2;

    /** @var array - TODO: what is this? */
    private $productImages;

    /** @var string - list of categories, cached to avoid fetching them every time we need them*/
    private $categoryCache;

    /**
     * FmExport constructor
     *
     * @param $filePath
     * @param $fmOutput
     */
    function __construct($filePath, $fmOutput)
    {
        $this->filePath = $filePath;
        $this->fmOutput = $fmOutput;
    }

    /**
     * Verifies whether the user has the right to get the feed. Regenerates the file if need be.
     *
     * @return mixed - true on success, false or an error message on failure
     */
    public function generateFeed()
    {
        $username = get_option('wcfyndiq_username');
        $token = get_option('wcfyndiq_apitoken');

        if (isset($username) && isset($token)) {
            if (FyndiqUtils::mustRegenerateFile($this->filePath)) {
                $this->feedFileHandling();
            }
            if (file_exists($this->filePath)) {
                // Clean output buffer if possible. Not this is not guaranteed to work because we don't control
                // when/if ob_start will be called
                ob_get_clean();
                $lastModified = filemtime($this->filePath);
                $file = fopen($this->filePath, 'r');
                $this->fmOutput->header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $lastModified));
                $this->fmOutput->streamFile($file, 'feed.csv', 'text/csv', filesize($this->filePath));
                fclose($file);
                return true;
            }
            return false;
        }
        return $this->fmOutput->showError(
            500,
            'Internal Server Error',
            sprintf('Error generating feed to %s', $this->filePath)
        );
    }

    /**
     * Handles the reading and writing of the Fyndiq feed file itself
     *
     * @return bool - false on error, otherwise true.
     */
    public function feedFileHandling()
    {
        $tempFileName = FyndiqUtils::getTempFilename(dirname($this->filePath));
        FyndiqUtils::debug('$fileName', $this->filePath);
        FyndiqUtils::debug('$tempFileName', $tempFileName);

        $file = fopen($tempFileName, 'w+');
        if (!$file) {
            FyndiqUtils::debug('Cannot create file: ' . $tempFileName);
            return false;
        }

        $feedWriter = new FyndiqCSVFeedWriter($file);
        $exportResult = $this->writeFeed($feedWriter);
        fclose($file);
        if ($exportResult) {
            // File successfully generated
            FyndiqUtils::moveFile($tempFileName, $this->filePath);
            return true;
        } else {
            // Something wrong happened, clean the file
            FyndiqUtils::deleteFile($tempFileName);
            return false;
        }
    }

    /**
     * @param $productId
     * @return array
     */
    protected function getTagValuesFixed($productId)
    {
        $result = array();
        $tag_values = get_post_meta($productId, '_product_attributes', true);
        if (is_array($tag_values)) {
            foreach ($tag_values as $key => $values) {
                if (isset($values['name']) && $values['name']) {
                    $result[$key] = $values['name'];
                }
            }
        }
        return $result;
    }

    /**
     * @param $feedWriter
     * @return mixed
     */
    protected function writeFeed($feedWriter)
    {
        global $wpdb;
        $products = FmProduct::getExportedProducts();
        $wcFyndiqCurrency = get_option('wcfyndiq_currency');
        $currency = !empty($wcFyndiqCurrency) ? $wcFyndiqCurrency : get_woocommerce_currency();
        $percentage_discount = get_option('wcfyndiq_price_percentage');

        //This has to be a little less concise to keep compatibility with < PHP5.5
        $discountOption = get_option('wcfyndiq_price_discount');
        $price_discount = !empty($discountOption) ? intval(get_option('wcfyndiq_price_discount')) : 0;

        $config = array(
            'market' => WC()->countries->get_base_country(),
            'currency' => $currency,
            'minQty' => get_option('wcfyndiq_quantity_minimum'),
            'wooML' => is_plugin_active('woocommerce-multilingual/wpml-woocommerce.php'),
            'percentage_discount' => intval($percentage_discount),
            'price_discount' => $price_discount
        );

        FyndiqUtils::debug('config', $config);
        foreach ($products as $product) {
            $this->productImages = array();
            $this->productImages['product'] = array();
            $this->productImages['articles'] = array();
            $exportedArticles = array();
            $product = new WC_Product_Variable($product);
            FyndiqUtils::debug('$product', $product);

            $exportProduct = $this->getProduct($product, $config);
            $variations = $this->getAllVariations($product);
            if (count($variations) === 0) {
                FyndiqUtils::debug('Simple product', $exportProduct);
                if (!$feedWriter->addCompleteProduct($exportProduct, false)) {
                    FyndiqUtils::debug('Product Validation Errors', $feedWriter->getLastProductErrors());
                }
                continue;
            }

            $articles = array();
            $tagValuesFixed = $this->getTagValuesFixed($product->id);
            foreach ($variations as $variation) {
                $exportVariation = $this->getVariation($product, $variation, $config, $tagValuesFixed);
                if (!empty($exportVariation)) {
                    $articles[] = $exportVariation;
                }
            }
            FyndiqUtils::debug('Variations product', $exportProduct, $articles);
            if ($articles) {
                if (!$feedWriter->addCompleteProduct($exportProduct, $articles)) {
                    FyndiqUtils::debug('Articles Validation Errors', $feedWriter->getLastProductErrors());
                }
                continue;
            }
        }
        FyndiqUtils::debug('$feedWriter->getProductCount()', $feedWriter->getProductCount());
        FyndiqUtils::debug('$feedWriter->getArticleCount()', $feedWriter->getArticleCount());
        return $feedWriter->write();
    }

    /**
     * @param $product
     * @param $config
     * @return array
     */
    private function getProduct($product, $config)
    {
        $absolutePrice = get_post_meta($product->id, '_fyndiq_price_absolute', true);
        $productPrice = $this->getProductPrice($product, $config, $absolutePrice);
        $regularPrice = $this->getProductRegularPrice($product, $config);

        $productPrice = $this->getPrice($product->id, $productPrice, $config);

        $_tax = new WC_Tax(); //looking for appropriate vat for specific product
        FyndiqUtils::debug('tax class', $product->get_tax_class());
        $rates = $_tax->get_rates($product->get_tax_class());
        $rates = array_shift($rates);
        FyndiqUtils::debug('tax rate', $rates);

        // GetCategory
        $categoryId = '';
        $categoryName = '';
        $terms = wp_get_post_terms($product->id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $correctTerms = array();
            foreach ($terms as $term) {
                if (isset($term->taxonomy) && $term->taxonomy == 'product_cat') {
                    $correctTerms[] = $term;
                }
            }
            foreach ($correctTerms as $term) {
                $path = $this->getCategoriesPath($term->term_id);
                $categoryId = $term->term_id;
                $categoryName = $path;
                break;
            }
        }

        $images = array();
        $attachment_ids = $product->get_gallery_attachment_ids();
        $feat_image = wp_get_attachment_url(get_post_thumbnail_id($product->id));
        FyndiqUtils::debug('$feat_image', $feat_image);
        if (!empty($feat_image)) {
            $images[] = $feat_image;
        }
        foreach ($attachment_ids as $attachment_id) {
            $image_link = wp_get_attachment_url($attachment_id);
            $images[] = $image_link;
        }

        $quantity = 0;
        if ($product->is_in_stock()) {
            $quantity = intval($product->get_stock_quantity());
            if ($config['minQty'] > 0) {
                $quantity -= $config['minQty'];
            }
        }

        $feedProduct = array(
            FyndiqFeedWriter::ID => $product->id,
            FyndiqFeedWriter::PRODUCT_TITLE => $product->post->post_title,
            FyndiqFeedWriter::PRODUCT_DESCRIPTION => $this->getDescription($product),
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($productPrice),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($regularPrice),
            FyndiqFeedWriter::PRODUCT_VAT_PERCENT => !empty($rates['rate']) ? $rates['rate'] : 0,
            FyndiqFeedWriter::PRODUCT_CURRENCY => $config['currency'],
            FyndiqFeedWriter::PRODUCT_MARKET => $config['market'],
            FyndiqFeedWriter::PRODUCT_CATEGORY_ID => $categoryId,
            FyndiqFeedWriter::PRODUCT_CATEGORY_NAME => $categoryName,
            FyndiqFeedWriter::IMAGES => $images,
            FyndiqFeedWriter::QUANTITY => $quantity,
            FyndiqFeedWriter::SKU => $this->getReference($product),
        );

        return array_merge($feedProduct, $this->getMappedFields($product), $this->getComparisons($product));
    }

    /**
     * @param $product
     * @param $variation
     * @param $config
     * @param $tagValuesFixed
     * @return array|bool - array containing the variations, otherwise false on error
     */
    private function getVariation($product, $variation, $config, $tagValuesFixed)
    {
        if ($variation['is_downloadable'] || $variation['is_virtual']) {
            FyndiqUtils::debug('downloadable, virtual', $variation['is_downloadable'], $variation['is_virtual']);
            return false;
        }
        $variationModel = new WC_Product_Variation(
            $variation['variation_id'],
            array('parent_id' => $product->id, 'parent' => $product)
        );

        $productPrice = $variation['display_price'];
        $price = $this->getPrice($product->id, $productPrice, $config);

        $price = FyndiqUtils::formatPrice($price);
        $oldPrice = FyndiqUtils::formatPrice($productPrice);

        $images = array();
        if (!empty($variation['image_src'])) {
            $images[] = $variation['image_src'];
        }

        $quantity = 0;
        if ($variation['is_purchasable'] && $variation['is_in_stock']) {
            $quantity = intval($variationModel->get_stock_quantity());
            if ($config['minQty'] > 0) {
                $quantity -= $config['minQty'];
            }
        }

        $articleName = $product->post->post_title;
        $properties = array();

        $tag_values = $variationModel->get_variation_attributes();
        if (!empty($tag_values)) {
            $tags = array();
            foreach ($tag_values as $key => $value) {
                $key = str_replace('attribute_', '', $key);
                if (isset($tagValuesFixed[$key]) && $tagValuesFixed[$key]) {
                    $name = $tagValuesFixed[$key];
                    $property = array(
                        FyndiqFeedWriter::PROPERTY_NAME => $name,
                        FyndiqFeedWriter::PROPERTY_VALUE => $value,
                    );
                    $properties[] = $property;
                    $tags[] = $name . ': ' . $value;
                }
            }
            $articleName = implode(', ', $tags);
        }

        $feedArticle = array(
            FyndiqFeedWriter::ID => $variation['variation_id'],
            FyndiqFeedWriter::PRICE => FyndiqUtils::formatPrice($price),
            FyndiqFeedWriter::OLDPRICE => FyndiqUtils::formatPrice($oldPrice),
            FyndiqFeedWriter::SKU => $this->getReference($variationModel, $product->id),
            FyndiqFeedWriter::IMAGES => $images,
            FyndiqFeedWriter::QUANTITY => $quantity,
            FyndiqFeedWriter::ARTICLE_NAME => $articleName,
            FyndiqFeedWriter::PROPERTIES => $properties,
        );

        return array_merge($feedArticle, $this->getMappedFields($variation['variation_id']), $this->getComparisons($variation['variation_id']));
    }

    /**
     * @param $product
     * @param $config
     * @param $absolutePrice
     * @return mixed
     */
    function getProductPrice($product, $config, $absolutePrice)
    {
        if ($config['wooML']) {
            return $this->getSaleProductPrice($product, $config['currency']);
        }

        $price = (!empty($absolutePrice)) ? $absolutePrice : $product->get_price();
        if ((function_exists('wc_tax_enabled') && wc_tax_enabled()) ||
            (!function_exists('wc_tax_enabled') && FmHelpers::fyndiq_wc_tax_enabled())
        ) {
            // this get the price including taxes for 1 quantity of this product
            $price = $product->get_price_including_tax(1, $price);
        }
        return $price;
    }

    /**
     * @param $product
     * @param $config
     * @return mixed|string
     */
    function getProductRegularPrice($product, $config)
    {
        if ($config['wooML']) {
            $regularPrice = '_regular_price';
            $orderCurrency = get_post_meta($product->id, '_order_currency', true);
            $checkPrice = get_post_meta($product->id, $regularPrice . '_' . $config['currency'], true);
            if (!empty($checkPrice) && $config['currency'] != $orderCurrency) {
                $regularPrice .= '_' . $config['currency'];
            }
            FyndiqUtils::debug('$regularPrice Column', $regularPrice);
            return get_post_meta($product->id, $regularPrice, true);
        }

        $regularPrice = $product->get_regular_price();
        if ((function_exists('wc_tax_enabled') && wc_tax_enabled()) ||
            (!function_exists('wc_tax_enabled') && FmHelpers::fyndiq_wc_tax_enabled())
        ) {
            // this get the price including taxes for 1 quantity of this product
            $regularPrice = $product->get_price_including_tax(1, $regularPrice);
        }
        return $regularPrice;
    }


    /**
     * @param $product
     * @param $currency
     * @return mixed
     */
    function getSaleProductPrice($product, $currency)
    {
        $salePriceColumn = '_sale_price';
        $priceColumn = '_price';
        $priceFromColumn = '_sale_price_dates_from';
        $priceToColumn = '_sale_price_dates_to';
        $orderCurrency = get_post_meta($product->id, '_order_currency', true);
        $checkPrice = get_post_meta($product->id, $salePriceColumn . '_' . $currency, true);
        FyndiqUtils::debug('$orderCurrency', $orderCurrency);
        if (!empty($checkPrice) && $currency != $orderCurrency) {
            $salePriceColumn .= '_' . $currency;
            $priceColumn .= '_' . $currency;
            $priceFromColumn .= '_' . $currency;
            $priceToColumn .= '_' . $currency;
        }
        FyndiqUtils::debug('$salePriceColumn', $salePriceColumn);
        $salePrice = get_post_meta($product->id, $salePriceColumn, true);
        FyndiqUtils::debug('$salePrice', $salePrice);
        if (get_post_meta($product->id, '_wcml_schedule_' . $currency, true)) {
            $from = get_post_meta($product->id, $priceFromColumn, true);
            $to = get_post_meta($product->id, $priceToColumn, true);
            $now = time();
            if ($from < $now && $to > $now) {
                return $salePrice;
            }
        } elseif (!empty($salePrice)) {
            return $salePrice;
        }
        $price = get_post_meta($product->id, $priceColumn, true);
        FyndiqUtils::debug('$price', $price);
        return $price;
    }

    /**
     * @param $post
     * @return string
     */
    function getDescription($post)
    {
        $option = get_option('wcfyndiq_description_picker');
        if (!isset($option) || $option == false) {
            $option = self::DESCRIPTION_LONG;
        }
        switch ($option) {
            case self::DESCRIPTION_SHORT:
                return $post->post->post_excerpt;
            case self::DESCRIPTION_LONG:
                return $post->post->post_content;
            case self::DESCRIPTION_SHORT_LONG:
                return $post->post->post_excerpt . "\n" . $post->post->post_content;
        }
    }

    /**
     * @return string
     */
    public static function getDescriptionPOST()
    {
        $option = get_option('wcfyndiq_description_picker');
        if (!isset($option) || $option == false) {
            $option = self::DESCRIPTION_LONG;
        }
        switch ($option) {
            case self::DESCRIPTION_SHORT:
                return $_POST['post_excerpt'];
            case self::DESCRIPTION_LONG:
                return $_POST['post_content'];
            case self::DESCRIPTION_SHORT_LONG:
                return $_POST['post_excerpt'] . "\n" . $_POST['post_content'];
        }
    }

    /**
     * @param $exportedProduct
     * @return array
     */
    private function getAllVariations($exportedProduct)
    {
        $available_variations = array();
        global $product;
        $product = $exportedProduct;

        foreach ($product->get_children() as $child_id) {
            $variation = $product->get_child($child_id);

            $variation_attributes = $variation->get_variation_attributes();
            $availability = $variation->get_availability();
            $availability_html = empty($availability['availability']) ? '' : '<p class="stock ' . esc_attr($availability['class']) . '">' . wp_kses_post($availability['availability']) . '</p>';
            $availability_html = apply_filters('woocommerce_stock_html', $availability_html, $availability['availability'], $variation);

            if (has_post_thumbnail($variation->get_variation_id())) {
                $attachment_id = get_post_thumbnail_id($variation->get_variation_id());

                $attachment = wp_get_attachment_image_src($attachment_id, apply_filters('single_product_large_thumbnail_size', 'shop_single'));
                $image = $attachment ? current($attachment) : '';

                $attachment = wp_get_attachment_image_src($attachment_id, 'full');
                $image_link = $attachment ? current($attachment) : '';

                $image_title = get_the_title($attachment_id);
                $image_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            } else {
                $image = $image_link = $image_title = $image_alt = '';
            }

            $filters = array(
                'variation_id' => $child_id,
                'variation_is_visible' => $variation->variation_is_visible(),
                'is_purchasable' => $variation->is_purchasable(),
                'attributes' => $variation_attributes,
                'image_src' => $image,
                'image_link' => $image_link,
                'image_title' => $image_title,
                'image_alt' => $image_alt,
                'price_html' => $variation->get_price() === "" || $product->get_variation_price('min') !== $product->get_variation_price('max') ? '<span class="price">' . $variation->get_price_html() . '</span>' : '',
                'availability_html' => $availability_html,
                'sku' => $variation->get_sku(),
                'weight' => $variation->get_weight() . ' ' . esc_attr(get_option('woocommerce_weight_unit')),
                'dimensions' => $variation->get_dimensions(),
                'min_qty' => 1,
                'max_qty' => $variation->backorders_allowed() ? '' : $variation->get_stock_quantity(),
                'backorders_allowed' => $variation->backorders_allowed(),
                'is_in_stock' => $variation->is_in_stock(),
                'is_downloadable' => $variation->is_downloadable(),
                'is_virtual' => $variation->is_virtual(),
                'is_sold_individually' => $variation->is_sold_individually() ? 'yes' : 'no',
            );

            if (version_compare(WC()->version, '2.2.11') > 0) {
                $filters['variation_is_active'] = $variation->variation_is_active();
                $filters['display_regular_price'] = $variation->get_display_price($variation->get_regular_price());

                $filters['display_price'] = $variation->get_display_price();
                if (wc_tax_enabled()) {
                    $filters['display_price'] = $variation->get_price_including_tax();
                }

            } else {
                $tax_display_mode = get_option('woocommerce_tax_display_shop');
                $display_price = $tax_display_mode == 'incl' ? $variation->get_price_including_tax() : $variation->get_price_excluding_tax();
                $display_regular_price = $tax_display_mode == 'incl' ? $variation->get_price_including_tax(1, $variation->get_regular_price()) : $variation->get_price_excluding_tax(1, $variation->get_regular_price());
                $display_sale_price = $tax_display_mode == 'incl' ? $variation->get_price_including_tax(1, $variation->get_sale_price()) : $variation->get_price_excluding_tax(1, $variation->get_sale_price());

                $price = $display_price;
                if (isset($display_sale_price)) {
                    $price = $display_sale_price;
                }

                $filters['display_price'] = $price;
                $filters['display_regular_price'] = $display_regular_price;
            }

            $available_variations[] = apply_filters('woocommerce_available_variation', $filters, $product, $variation);
        }

        FyndiqUtils::debug('$available_variations', $available_variations);

        return $available_variations;
    }

    /**
     * @param $product_id
     * @param $product_price
     * @param $config
     * @return float
     */
    public function getPrice($product_id, $product_price, $config)
    {
        $discount = $this->getDiscount($config['percentage_discount']);

        return FyndiqUtils::getFyndiqPrice($product_price, $discount, $config['price_discount']);
    }

    /**
     * @param $discount
     * @return int
     */
    private function getDiscount($discount)
    {
        if ($discount > 100) {
            $discount = 100;
        } elseif ($discount < 0) {
            $discount = 0;
        }

        return $discount;
    }


    /**
     * @param $categoryId
     * @return mixed
     */
    private function getCategoriesPath($categoryId)
    {
        if (isset($this->categoryCache[$categoryId])) {
            return $this->categoryCache[$categoryId];
        }
        $categories = array();

        $parent = $categoryId;
        do {
            $category = get_term($parent, 'product_cat');
            $categories[] = $category->name;
            $parent = $category->parent;
        } while (isset($parent) && $parent > 0);

        $categories = array_reverse($categories);
        $this->categoryCache[$categoryId] = implode(self::DELIMITER, $categories);
        return $this->categoryCache[$categoryId];
    }

    /**
     * @param $product
     * @param bool $parent_id
     * @return mixed|string
     */
    private function getReference($product, $parent_id = false)
    {
        $option = get_option('wcfyndiq_reference_picker');
        switch ($option) {
            case self::REF_ID:
                return ($parent_id) ? $parent_id . self::REF_DELIMITER . $product->get_variation_id() : $product->id;
            default:
                $sku = $product->get_sku();
                if ($parent_id == false) {
                    $sku = get_post_meta($product->id, '_sku');
                    $sku = array_shift($sku);
                }
                return $sku;
        }
    }

    /**
     * getMappedFields - gets mapped fields for a given product
     *
     * @param $product - either the post object of the product or the post ID
     * @return array - an associative array of fields and their mapped values
     */
    private function getMappedFields($product)
    {
        return array(
            FyndiqCSVFeedWriter::PRODUCT_BRAND_NAME => $this->getValueForFields('brand', $product),
            FyndiqCSVFeedWriter::ARTICLE_EAN => $this->getValueForFields('ean', $product),
            FyndiqCSVFeedWriter::ARTICLE_ISBN => $this->getValueForFields('isbn', $product),
            FyndiqCSVFeedWriter::ARTICLE_MPN => $this->getValueForFields('mpn', $product),
        );
    }

    /**
     * @param $key
     * @param $product
     * @return string
     */
    private function getValueForFields($key, $product)
    {
        //Get the options, if empty it is not set, return empty string
        $option = get_option('wcfyndiq_field_map_' . $key);
        if (empty($option)) {
            return '';
        }
        FyndiqUtils::debug('$option', $option);
        //if product is int, it will not be a get_attribute function called, skip it
        $attribute = '';
        if (!is_int($product)) {
            $attribute = $product->get_attribute('pa_' . $option);
        }
        if (empty($attribute)) {
            //handling if product is integer
            $productId = is_int($product) ? $product : $product->id;
            $meta = get_post_meta($productId, '_product_attributes');
            foreach ($meta as $attrkey => $attr) {
                if (isset($attr[$option])) {
                    $chosenAttr = $attr[$option];
                    FyndiqUtils::debug('$attr', $chosenAttr);
                    $attribute = $chosenAttr['value'];
                }
            }
        }
        FyndiqUtils::debug('attribute', $attribute);
        return $attribute;
    }

    /**
     * checkFieldIsSet - checks whether a field map is set for a given key
     *
     * @param $key - key for field mapping
     * @return bool - returns true if a mapping exists, otherwise false
     */
    private function checkFieldIsSet($key)
    {
        $option = get_option('wcfyndiq_field_map_' . $key);
        return !empty($option);
    }

    /**
     *
     * @param $product
     * @return array
     */
    private function getComparisons($product)
    {
        $feedProduct = array();
        if ($this->checkFieldIsSet('comp_price')) {
            $comparisonUnit = $this->getValueForFields('comp_unit', $product);
            $comparisonPrice = $this->getValueForFields('comp_price', $product);
            if (!empty($comparisonUnit) && !empty($comparisonPrice)) {
                $feedProduct[FyndiqFeedWriter::PRODUCT_PORTION] =
                    number_format((float)$comparisonPrice, 2, '.', '');
                $feedProduct[FyndiqFeedWriter::PRODUCT_COMPARISON_UNIT] = $comparisonUnit;
            }
        }
        return $feedProduct;
    }
}
