<?php
class FmOrderFetch extends FyndiqPaginatedFetch
{
    function __construct($settingExists = false)
    {
        $this->storeId = 0;
        $this->settingExists = $settingExists;
    }

    function getInitialPath()
    {
        $date = false;
        if ($this->settingExists) {
            $date = 0;
        }
        $url = 'orders/' . (empty($date) ? '' : '?min_date=' . urlencode($date));

        return $url;
    }

    function getPageData($path)
    {
        $ret = FmHelpers::callApi('GET', $path);

        return $ret['data'];
    }

    function processData($data)
    {
        $orderModel = new FmOrder();
        foreach ($data as $order) {
            if (!$orderModel->orderExists($order->id)) {
                $orderModel->createOrder($order);
            }
        }

        return true;
    }

    function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_ORDER_RPS;
    }
}
