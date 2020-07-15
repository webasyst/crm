<?php

$_dir = wa('crm')->getAppPath('lib/handlers');
$_files = array('complete', 'create', 'delete', 'pay', 'process', 'refund', 'restore', 'ship');

foreach ($_files as $_f) {
    $_path = $_dir.'/shop.order_action.'.$_f.'.handler.php';
    if (!file_exists($_path)) {
        continue;
    }
    try {
        waFiles::delete($_path);
    } catch (waException $e) {
        // wait a second, maybe race-condition
        sleep(1);
        if (file_exists($_path)) {
            // nope, something gone wrong with deletion
            throw $e;
        }
    }
}
