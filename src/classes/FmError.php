<?php
//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

/**
 * Class FmError - Handles showing errors on admin pages
 */
class FmError
{

    /**CSS Class assigned to red error messages*/
    const CLASS_ERROR = 'error';
    /**CSS Class assigned to green update messages*/
    const CLASS_UPDATED = 'updated';


    /**
     * Sets all WordPress hooks related to the Fields
     *
     * @return bool - Always returns true because add_action() aways returns true
     */
    public static function setHooks()
    {
        add_action('admin_notices', array(__CLASS__, 'processErrorAction'));
    }

    /**
     * Hooked to 'admin_notices' - handles the admin_notices action
     *
     *  @return bool - returns output from renderErrorRaw() on success, otherwise false
     */
    public static function processErrorAction()
    {
        if (isset($_REQUEST['fyndiqMessageType'])) {
            return self::renderError(
                urldecode($_REQUEST['fyndiqMessage']),
                urldecode($_REQUEST['fyndiqMessageType'])
            );
        }
        return false;
    }

    /**
     * Renders notification message and escapes the parameters
     *
     *  @param string       $message     Error message
     *  @param string       $messageType Message class
     *  @param FyndiqOutput $fmOutput    Output class instance
     *
     *  @return bool
     */
    public static function renderError($message, $messageType = self::CLASS_ERROR, $fmOutput = null)
    {
        return FmError::renderErrorRaw(
            htmlspecialchars($message),
            htmlspecialchars($messageType),
            $fmOutput
        );
    }

    /**
     * Renders notification message without escaping the parameters
     *
     * @param string       $message     Error message
     * @param string       $messageType Message class
     * @param FyndiqOutput $fmOutput    Output class instance
     *
     * @return bool
     */
    public static function renderErrorRaw($message, $messageType = self::CLASS_ERROR, $fmOutput = null)
    {
        if (is_null($fmOutput)) {
            $fmOutput = new FyndiqOutput();
        }
        return $fmOutput->output(
            sprintf(
                '<div class="%s"><p>%s</p></div>',
                $messageType,
                $message
            )
        );
    }

    /**
     * Sets error message and type and refreshes the page to show it
     *
     *  @param string $errorMessage Error message
     *  @param string $messageType  Message CSS class
     *
     * @return null
     */
    public static function handleError($errorMessage, $messageType = self::CLASS_ERROR)
    {
        $errorMessage = sprintf("An error occurred: %s", $errorMessage);
        $redirect = add_query_arg(
            array('fyndiqMessageType' => $messageType, 'fyndiqMessage' => urlencode($errorMessage)),
            wp_get_referer()
        );
        wp_redirect($redirect);
        exit;
    }
}
