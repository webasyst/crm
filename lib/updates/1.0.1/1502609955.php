<?php

$_dir = wa('crm')->getAppPath('lib/classes');
$_paths = array(
    $_dir . '/crmSource.class.php',
    $_dir . '/crmSourceEmailWorker.class.php'
);

foreach ($_paths as $_path) {
    if (file_exists($_path)) {
        try {
            waFiles::delete($_path);
        } catch (Exception $e) {
            // wait a second, maybe race-condition
            sleep(1);
            if (!file_exists($_path)) {
                // nope, something gone wrong with deletion
                throw $e;
            }
        }
    }
}
