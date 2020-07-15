<?php
$m = new waModel();
$m->exec('ALTER TABLE `crm_message` CHANGE `event` `event` VARCHAR (64) NULL DEFAULT NULL');