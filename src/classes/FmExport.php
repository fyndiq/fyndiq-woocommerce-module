<?php
class FmExport
{

    const DELIMITER = ' / ';
    const REF_DELIMITER = '-';

    const DESCRIPTION_SHORT = 1;
    const DESCRIPTION_LONG = 2;
    const DESCRIPTION_SHORT_LONG = 3;

    const REF_SKU = 1;
    const REF_ID = 2;

    function __construct($filepath, $fmoutput)
    {
        $this->filepath = $filepath;
        $this->fmOutput = $fmoutput;
    }

    public function generate_feed()
    {
        $username = get_option('wcfyndiq_username');
        $token = get_option('wcfyndiq_apitoken');

        if (isset($username) && isset($token)) {
            if (FyndiqUtils::mustRegenerateFile($this->filepath)) {
                $this->feedFileHandling();
            }
            if (file_exists($this->filepath)) {
                $lastModified = filemtime($this->filepath);
                $file = fopen($this->filepath, 'r');
                $this->fmOutput->header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $lastModified));
                $this->fmOutput->streamFile($file, 'feed.csv', 'text/csv', filesize($this->filepath));
                fclose($file);
            }

            return $this->returnAndDie('');
        }
        return $this->fmOutput->showError(
            500,
            'Internal Server Error',
            sprintf('Error generating feed to %s', $this->filepath)
        );
    }

    public function feedFileHandling()
    {
        $tempFileName = FyndiqUtils::getTempFilename(dirname($this->filepath));
        FyndiqUtils::debug('$fileName', $this->filepath);
        FyndiqUtils::debug('$tempFileName', $tempFileName);

        $file = fopen($tempFileName, 'w+');
        if (!$file) {
            FyndiqUtils::debug('Cannot create file: ' . $fileName);
            return false;
        }

        $feedWriter = new FyndiqCSVFeedWriter($file);
        $exportResult = $this->feed_write($feedWriter);
        fclose($file);
        if ($exportResult) {
            // File successfully generated
            FyndiqUtils::moveFile($tempFileName, $this->filepath);
        } else {
            // Something wrong happened, clean the file
            FyndiqUtils::deleteFile($tempFileName);
        }
    }

    private function feed_write($feedWriter)
    {
        global $wpdb;
        $productmodel = new FmProduct();
        $posts_array = $productmodel->getExportedProducts();
        FyndiqUtils::debug('quantity minmum', get_option('wcfyndiq_quantity_minimum'));
        $this->tag_values_fixed = array();
        foreach ($posts_array as $product) {
            $this->productImages = array();
            $this->productImages['product'] = array();
            $this->productImages['articles'] = array();
            $exportedArticles = array();
            $product = new WC_Product_Variable($product);
            FyndiqUtils::debug('$product', $product);
            $tag_values = get_post_meta($product->id, '_product_attributes', true);
            FyndiqUtils::debug('$tag_values', $tag_values);
            foreach ($tag_values as $value) {
                FyndiqUtils::debug('$value[\'name\']', $value['name']);
                $name = str_replace('pa_', '', $value['name']);
                if (!isset($this->tag_values_fixed[$value['name']])) {
                    $label = $wpdb->get_var($wpdb->prepare("SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s;", $name));
                    $this->tag_values_fixed[$value['name']] =  $label;
                }
            }
            FyndiqUtils::debug('$tag_values_fixed', $this->tag_values_fixed);
            $variations = $this->getAllVariations($product);
            if (count($variations) > 0) {
                $prices = array();

                $attachment_ids = $product->get_gallery_attachment_ids();
                $feat_image = wp_get_attachment_url(get_post_thumbnail_id($product->id));
                if (!empty($feat_image)) {
                    $this->productImages['product'][] = $feat_image;
                }
                foreach ($attachment_ids as $attachment_id) {
                    $image_link = wp_get_attachment_url($attachment_id);
                    $this->productImages['product'][] = $image_link;
                }

                foreach ($variations as $variation) {
                    $exportVariation = $this->getVariation($product, $variation);
                    if (!empty($exportVariation)) {
                        $prices[] = $exportVariation['product-price'];
                        FyndiqUtils::debug('$exportVariation', $exportVariation);
                        $exportedArticles[] = $exportVariation;
                    }
                }

                $differentPrice = count(array_unique($prices)) > 1;
                FyndiqUtils::debug('$differentPrice', $differentPrice);

                FyndiqUtils::debug('productImages', $this->productImages);

                foreach ($exportedArticles as $articleId => $article) {
                    if (!$differentPrice) {
                        // All prices are NOT different, create articles
                        $images = $this->getImagesFromArray();
                        $article = array_merge($article, $images);
                        if (empty($article['article-sku'])) {
                            FyndiqUtils::debug('EMPTY ARTICLE SKU');
                        }
                        $feedWriter->addProduct($article);
                        FyndiqUtils::debug('Any sameprice Errors', $feedWriter->getLastProductErrors());
                        continue;
                    }

                    // Prices differ, create products
                    $id = count($article['article-sku']) > 0 ? $article['article-sku'] : null;
                    FyndiqUtils::debug('$id', $id);
                    $images = $this->getImagesFromArray($id);
                    $article = array_merge($article, $images);
                    $article['product-id'] = $article['product-id'] . '-' . $articleId;
                    if (empty($article['article-sku'])) {
                        FyndiqUtils::debug('EMPTY ARTICLE SKU');
                    }
                    $feedWriter->addProduct($article);
                    FyndiqUtils::debug('Any Validation Errors', $feedWriter->getLastProductErrors());
                }
            } else {
                $exportProduct = $this->getProduct($product);
                if (!empty($exportProduct)) {
                    $images = $this->getImagesFromArray();
                    $exportProduct = array_merge($exportProduct, $images);
                    if (empty($exportProduct['article-sku'])) {
                        FyndiqUtils::debug('EMPTY PRODUCT SKU');
                    }
                    $feedWriter->addProduct($exportProduct);
                    FyndiqUtils::debug('Product Validation Errors', $feedWriter->getLastProductErrors());
                }
            }
        }
        $feedWriter->write();
        return true;
    }


    private function getProduct($product)
    {
        //Initialize models here so it saves memory.
        $feedProduct['product-id'] = $product->id;
        $feedProduct['product-title'] = $product->post->post_title;

        $description = $this->getDescription($product);

        $feedProduct['product-description'] = $description;

        $productPrice = $product->get_price();
        $regularPrice = $product->get_regular_price();
        if ((function_exists('wc_tax_enabled') && wc_tax_enabled()) || (!function_exists('wc_tax_enabled') && FmHelpers::fyndiq_wc_tax_enabled())) {
            $productPrice = $product->get_price_including_tax();
            $regularPrice = $product->get_price_including_tax(1, $regularPrice);
        }
        $price = $this->getPrice($product->id, $productPrice);

        $_tax = new WC_Tax(); //looking for appropriate vat for specific product
        FyndiqUtils::debug('tax class', $product->get_tax_class());

        $rates = $_tax->get_rates($product->get_tax_class());
        $rates = array_shift($rates);
        FyndiqUtils::debug('tax rate', $rates);


        $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
        $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
        $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($regularPrice);
        $feedProduct['product-market'] = WC()->countries->get_base_country();
        $feedProduct['product-currency'] = get_woocommerce_currency();

        $terms = wp_get_post_terms($product->id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $correctTerms = array();
            foreach ($terms as $term) {
                if (isset($term->taxonomy) && $term->taxonomy == 'product_cat') {
                    $correctTerms[] = $term;
                }
            }
            FyndiqUtils::debug('product $correctTerms', $correctTerms);
            foreach ($correctTerms as $term) {
                $path = $this->getCategoriesPath($term->term_id);
                $feedProduct['product-category-id'] = $term->term_id;
                $feedProduct['product-category-name'] = $path;
                break;
            }
        } else {
            FyndiqUtils::debug('Product have no categories set - skipped');
            return array();
        }

        $attachment_ids = $product->get_gallery_attachment_ids();
        $feat_image = wp_get_attachment_url(get_post_thumbnail_id($product->id));
        FyndiqUtils::debug('$feat_image', $feat_image);
        if (!empty($feat_image)) {
            $this->productImages['product'][] = $feat_image;
        }
        foreach ($attachment_ids as $attachment_id) {
            $image_link = wp_get_attachment_url($attachment_id);
            $this->productImages['product'][] = $image_link;
        }

        $feedProduct['article-quantity'] = intval(0);
        if ($product->is_in_stock()) {
            $stock = $product->get_stock_quantity();
            $minimumQuantity = get_option('wcfyndiq_quantity_minimum');
            if ($minimumQuantity > 0) {
                $stock = $stock - $minimumQuantity;
            }
            FyndiqUtils::debug('$stock product', $stock);
            $feedProduct['article-quantity'] = intval($stock);
        }

        $sku = $this->getReference($product);
        $feedProduct['article-sku'] = strval($sku);

        $feedProduct['article-name'] = $product->post->post_title;

        FyndiqUtils::debug('product without images', $feedProduct);
        return $feedProduct;
    }


    private function getVariation($product, $variation)
    {
        FyndiqUtils::debug('$variation', $variation);
        if (!$variation['is_downloadable'] && !$variation['is_virtual']) {
            $variationModel = new WC_Product_Variation($variation['variation_id'], array('parent_id' => $product->id, 'parent' => $product));
            //Initialize models here so it saves memory.
            $feedProduct['product-id'] = $product->id;
            $feedProduct['product-title'] = $product->post->post_title;

            $description = $this->getDescription($product);
            $feedProduct['product-description'] = $description;

            $productPrice = $variation['display_price'];
            $price = $this->getPrice($product->id, $productPrice);
            $_tax = new WC_Tax(); //looking for appropriate vat for specific product
            $rates = $_tax->get_rates($product->get_tax_class());
            $rates = array_shift($rates);

            $feedProduct['product-price'] = FyndiqUtils::formatPrice($price);
            $feedProduct['product-vat-percent'] = !empty($rates['rate']) ? $rates['rate'] : 0;
            $feedProduct['product-oldprice'] = FyndiqUtils::formatPrice($productPrice);
            $feedProduct['product-market'] = WC()->countries->get_base_country();
            $feedProduct['product-currency'] = get_woocommerce_currency();

            $terms = wc_get_product_terms($product->id, 'product_cat');
            FyndiqUtils::debug('$terms', $terms);
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $feedProduct['product-category-id'] = $term->term_id;
                    $feedProduct['product-category-name'] = $term->name;
                    break;
                }
            } else {
                FyndiqUtils::debug('Variation have no categories set - skipped');
                return array();
            }
            $sku = $this->getReference($variationModel, $product->id);

            $feedProduct['article-sku'] = strval($sku);

            $variationImages = array();
            if (!empty($variation['image_src'])) {
                $variationImages[] = $variation['image_src'];
            }
            $this->productImages['articles'][$sku] = $variationImages;

            $feedProduct['article-quantity'] = 0;

            if ($variation['is_purchasable'] && $variation['is_in_stock']) {
                $stock = intval($variationModel->get_stock_quantity());
                $minimumQuantity = get_option('wcfyndiq_quantity_minimum');
                if ($minimumQuantity > 0) {
                    $stock = $stock - $minimumQuantity;
                }
                $feedProduct['article-quantity'] = $stock;
            }


            $feedProduct['article-name'] = $product->post->post_title;
            $tag_values = $variationModel->get_variation_attributes();

            if (!empty($tag_values)) {
                FyndiqUtils::debug('$tag_values', $tag_values);
                $propertyId = 1;
                $tags = array();
                foreach ($tag_values as $key => $value) {
                    $key = str_replace('attribute_', '', $key);
                    $feedProduct['article-property-'.$propertyId.'-name'] = $this->tag_values_fixed[$key];
                    $feedProduct['article-property-'.$propertyId.'-value'] = $value;
                    $tags[] = $this->tag_values_fixed[$key] . ': ' . $value;
                    $propertyId++;
                }

                $feedProduct['article-name'] = implode(', ', $tags);
            }

            FyndiqUtils::debug('variation without images', $feedProduct);

            return $feedProduct;
        }
    }


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

    private function getImagesFromArray($articleId = null)
    {
        $product = array();
        $urls = array();
        //If we don't want to add a specific article, add all of them.
        if (!isset($articleId)) {
            foreach ($this->productImages['product'] as $url) {
                if (!in_array($url, $product)) {
                    $urls[] = $url;
                }
            }
            foreach ($this->productImages['articles'] as $article) {
                foreach ($article as $url) {
                    if (!in_array($url, $product)) {
                        $urls[] = $url;
                    }
                }
            }
            // If we want to add just the product images and the article's images - run this.
        } else {
            foreach ($this->productImages['articles'][$articleId] as $url) {
                $urls[] = $url;
            }

            foreach ($this->productImages['product'] as $url) {
                $urls[] = $url;
            }
        }
        $imageId = 1;
        foreach ($urls as $url) {
            if ($imageId > FyndiqUtils::NUMBER_OF_ALLOWED_IMAGES) {
                break;
            }
            $product['product-image-' . $imageId . '-url'] = $url;
            $product['product-image-' . $imageId . '-identifier'] = substr(md5($url), 0, 10);
            $imageId++;
        }
        return $product;
    }

    private function getAllVariations($product)
    {

        $available_variations = array();

        foreach ($product->get_children() as $child_id) {
            $variation = $product->get_child($child_id);

            $variation_attributes = $variation->get_variation_attributes();
            $availability         = $variation->get_availability();
            $availability_html    = empty($availability['availability']) ? '' : '<p class="stock ' . esc_attr($availability['class']) . '">' . wp_kses_post($availability['availability']) . '</p>';
            $availability_html    = apply_filters('woocommerce_stock_html', $availability_html, $availability['availability'], $variation);

            if (has_post_thumbnail($variation->get_variation_id())) {
                $attachment_id = get_post_thumbnail_id($variation->get_variation_id());

                $attachment    = wp_get_attachment_image_src($attachment_id, apply_filters('single_product_large_thumbnail_size', 'shop_single'));
                $image         = $attachment ? current($attachment) : '';

                $attachment    = wp_get_attachment_image_src($attachment_id, 'full');
                $image_link    = $attachment ? current($attachment) : '';

                $image_title   = get_the_title($attachment_id);
                $image_alt     = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            } else {
                $image = $image_link = $image_title = $image_alt = '';
            }

            $filters = array(
                'variation_id'          => $child_id,
                'variation_is_visible'  => $variation->variation_is_visible(),
                'is_purchasable'        => $variation->is_purchasable(),
                'attributes'            => $variation_attributes,
                'image_src'             => $image,
                'image_link'            => $image_link,
                'image_title'           => $image_title,
                'image_alt'             => $image_alt,
                'price_html'            => $variation->get_price() === "" || $product->get_variation_price('min') !== $product->get_variation_price('max') ? '<span class="price">' . $variation->get_price_html() . '</span>' : '',
                'availability_html'     => $availability_html,
                'sku'                   => $variation->get_sku(),
                'weight'                => $variation->get_weight() . ' ' . esc_attr(get_option('woocommerce_weight_unit')),
                'dimensions'            => $variation->get_dimensions(),
                'min_qty'               => 1,
                'max_qty'               => $variation->backorders_allowed() ? '' : $variation->get_stock_quantity(),
                'backorders_allowed'    => $variation->backorders_allowed(),
                'is_in_stock'           => $variation->is_in_stock(),
                'is_downloadable'       => $variation->is_downloadable() ,
                'is_virtual'            => $variation->is_virtual(),
                'is_sold_individually'  => $variation->is_sold_individually() ? 'yes' : 'no',
            );

            $version = FmHelpers::get_woocommerce_version();
            if (version_compare($version, '2.2.11') > 0) {
                $filters['variation_is_active'] = $variation->variation_is_active();
                $filters['display_regular_price'] = $variation->get_display_price($variation->get_regular_price());

                $filters['display_price'] = $variation->get_display_price();
                if (wc_tax_enabled()) {
                    $filters['display_price'] = $variation->get_price_including_tax();
                }

            } else {
                $tax_display_mode      = get_option('woocommerce_tax_display_shop');
                $display_price         = $tax_display_mode == 'incl' ? $variation->get_price_including_tax() : $variation->get_price_excluding_tax();
                $display_regular_price = $tax_display_mode == 'incl' ? $variation->get_price_including_tax(1, $variation->get_regular_price()) : $variation->get_price_excluding_tax(1, $variation->get_regular_price());
                $display_sale_price    = $tax_display_mode == 'incl' ? $variation->get_price_including_tax(1, $variation->get_sale_price()) : $variation->get_price_excluding_tax(1, $variation->get_sale_price());

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

    public function getPrice($product_id, $product_price)
    {
        $percentage = get_post_meta($product_id, '_fyndiq_price_percentage', true);
        $discount = $this->getDiscount(intval($percentage));

        return FyndiqUtils::getFyndiqPrice($product_price, $discount);
    }

    private function getDiscount($discount)
    {
        if ($discount > 100) {
            $discount = 100;
        } elseif ($discount < 0) {
            $discount = 0;
        }

        return $discount;
    }


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

    private function getReference($product, $parent_id = false)
    {
        $option = get_option('wcfyndiq_reference_picker');
        if(!isset($option) || $option == false) {
            $option = self::REF_SKU;
        }
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

    public function returnAndDie($return)
    {
        die($return);
    }
}
