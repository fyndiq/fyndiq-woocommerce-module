<?php

/**
 * Handles showing errors on admin pages
 *
 */
class FmError
{
    static public function setHooks()
    {
        add_action('admin_notices', array(__CLASS__, 'renderError'));
    }

    public static function renderError()
    {
        if (isset($_REQUEST['fyndiqMessageType'])) {
            echo sprintf(
                "<div class='%s'><p>%s</p></div>",
                htmlspecialchars(urldecode($_REQUEST['fyndiqMessageType'])),
                htmlspecialchars(urldecode($_REQUEST['fyndiqMessage']))
            );
        }
    }

    public static function handleError($errorMessage)
    {
        $errorMessage = sprintf("An error occurred: %s", $errorMessage);
        $redirect = add_query_arg(
            array('fyndiqMessageType' => 'error', 'fyndiqMessage' => urlencode($errorMessage)),
            wp_get_referer()
        );
        wp_redirect($redirect);
        exit;
    }
}
