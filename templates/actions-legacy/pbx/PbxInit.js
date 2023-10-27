(function($) { "use strict";

    // This file is loaded via <script src="/webasyst/crm/pbx/init.djs"> on all backend layout pages as part of $wa->header() call.
    // It initializes WebPush to receive telephony calls.
    //
    // This file is processed as a Smarty template.
    // See crmPbxActions->initJs().
    var crm_url = {$crm_url|json_encode};

    // Run background worker once in a while
    (function() {
        setTimeout(runWorker, 57000);
        function runWorker() {
            $.get(crm_url+'?module=pbx&action=worker&background_process=1');
            setTimeout(runWorker, 57000 + 57000*Math.random());
        }
    }());

}(window.jQuery));