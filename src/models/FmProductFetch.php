<?php

class FmProductFetch extends FyndiqPaginatedFetch
{
    private $fmProduct = null;


    public function __construct()
    {
        $this->fmProduct = new FmProductHelper();
    }

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
            $status = FmProductHelper::STATUS_FOR_SALE;
            if ($statusRow->for_sale === 'NOT_FOR_SALE') {
                $status = FmProductHelper::STATUS_PENDING;
            }

            $result &= $this->fmProduct->updateStatus($statusRow->product_id, $status);
        }
        return $result;
    }

    public function getAll()
    {
        $this->fmProduct->updateStatusAllProducts(FmProductHelper::STATUS_PENDING);
        return parent::getAll();
    }
}
