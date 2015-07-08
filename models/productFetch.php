<?php

class FmProductFetch extends FyndiqPaginatedFetch
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_FOR_SALE = 'FOR_SALE';

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
            $result &= update_post_meta($statusRow->product_id, '_fyndiq_status', $statusRow->for_sale);
        }
        return $result;
    }

    public function getAll()
    {
        $this->fmModel->updateAllProductStatus(FmModel::STATUS_PENDING);
        foreach ($data as $statusRow) {
            $result &= update_post_meta($statusRow->product_id, '_fyndiq_status', self::STATUS_PENDING);
        }
        return parent::getAll();
    }

}