<?php

class FmProductFetch extends FyndiqPaginatedFetch
{
    private $fmProduct = null;


    public function __construct()
    {
        $this->fmProduct = new FmProduct();
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
            $result &= $this->fmProduct->updateStatus($statusRow->product_id, $statusRow->for_sale);
        }
        return $result;
    }

    public function getAll()
    {
        $this->fmProduct->updateStatusAllProducts(FmProduct::STATUS_PENDING);
        return parent::getAll();
    }
}
