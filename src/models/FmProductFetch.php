<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmProductFetch extends FyndiqPaginatedFetch
{


    public function getInitialPath()
    {
        return 'product_info/';
    }

    public function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_PRODUCT_INFO_RPS;
    }

    /**
     * Get product single page products' info
     *
     * @param string $path
     * @return mixed
     */
    public function getPageData($path)
    {
        $ret = FmHelpers::callApi('GET', $path);

        return $ret['data'];
    }

    /**
     * Update product status
     *
     * @param mixed $data
     * @return bool
     */
    public function processData($data)
    {
        $result = true;
        foreach ($data as $statusRow) {
            $status = FmProduct::STATUS_FOR_SALE;
            if ($statusRow->for_sale === 'NOT_FOR_SALE') {
                $status = FmProduct::STATUS_PENDING;
            }

            $result &= FmProduct::updateStatus($statusRow->product_id, $status);
        }
        return $result;
    }

    public function getAll()
    {
        FmProduct::updateStatusAllProducts(FmProduct::STATUS_PENDING);
        return parent::getAll();
    }
}
