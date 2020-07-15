<?php

$m = new waModel();
$m->query("UPDATE wa_contact SET company_contact_id=0 WHERE is_company=1");
