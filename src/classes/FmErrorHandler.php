<?php

/**
 * Handles showing errors on admin pages
 *
 */
class FmError
{
    protected $error;

    public function __construct()
    {
        $this->error = new FmErrorHandler();
        set_error_handler(array( $this->error, 'handleError' ), E_USER_NOTICE);

        add_action('admin_notices', function () {

            if (isset($_REQUEST['fyndiqMessageType'])) {
                echo "<div class='" . htmlspecialchars($_REQUEST['fyndiqMessageType']). "'><p>" .  htmlspecialchars($_REQUEST['fyndiqMessage']) . "</p></div>";
            }
        });

        add_action('wp_loaded', function ()
        {
            new FmError();
        });
    }
}

class FmErrorHandler
{
    public function handleError($errorNumber, $errorMessage)
    {
        $errorMessage = urlencode($errorMessage);
        wp_redirect($_REQUEST['_wp_http_referer'] . '&fyndiqMessage=' . $errorMessage . '&fyndiqMessageType=error');
        exit;
    }
}
