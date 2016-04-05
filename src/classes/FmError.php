<?php
/**
 * Handles showing errors on admin pages
 *
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmError
{
    public static function setHooks()
    {
        add_action('admin_notices', array(__CLASS__, 'processError'));
    }

    public static function processError()
    {
        if (isset($_REQUEST['fyndiqMessageType'])) {
            return self::renderError(
                urldecode($_REQUEST['fyndiqMessage'])
                urldecode($_REQUEST['fyndiqMessageType']),
            );
        }
    }

    public static function renderError($message, $messageType = 'error')
    {
        $fmOutput = new FyndiqOutput();
        return $fmOutput->output(
            sprintf(
                "<div class='%s'><p>%s</p></div>",
                htmlspecialchars($messageType),
                htmlspecialchars($message)
            )
        );
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
