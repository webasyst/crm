<?

class crmReminderGetUpcomingController extends waJsonController
{
    public function execute()
    {
        // Deprecated. This controller is here to avoid 404 errors 
        // in case JS still tries to access it via old url.
        // !!! TODO: remove
        $this->response = array();
    }
}