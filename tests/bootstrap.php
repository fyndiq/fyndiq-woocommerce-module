<?php

require dirname(__DIR__) . '/vendor/autoload.php';

WP_Test_Suite::load_plugins(array(
    dirname(__DIR__) . '/woocommerce-fyndiq.php.php'
));

WP_Test_Suite::run();
