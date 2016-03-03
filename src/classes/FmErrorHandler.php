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

    static function enforceTypeSafety(&$variable, $type)
    {
        if (gettype($variable) !== $type) {
            throw new Exception(sprintf(
                'Error - variable %s has incorrect type of %s. Type should be %s.',
                var_export($variable, true),
                gettype($variable),
                $type
            ));
        }
        return $variable;
    }
}
