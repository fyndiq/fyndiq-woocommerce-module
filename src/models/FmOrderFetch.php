<?php

class FmOrderFetch extends FyndiqPaginatedFetch
{
    public function __construct($settingExists = false)
    {
        $this->storeId = 0;
        $this->settingExists = $settingExists;
    }

    public function getInitialPath()
    {
        $date = false;
        if ($this->settingExists) {
            $time = get_option('wcfyndiq_order_time');
            $date = date('Y-m-d H:i:s', $time);
        }
        $url = 'orders/' . (empty($date) ? '' : '?min_date=' . urlencode($date));

        return $url;
    }

    public function getPageData($path)
    {
        $ret = FmHelpers::callApi('GET', $path);

        return $ret['data'];
    }

    public function processData($data)
    {
        $errors = array();
        $orderModel = new FmOrderHelper();
        foreach ($data as $order) {
            try {
                if (!$orderModel->orderExists($order->id)) {
                    $orderModel->createOrder($order);
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($errors) {
            throw new Exception(implode("\n", $errors));
        }

        return true;
    }

    public function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_ORDER_RPS;
    }
}
