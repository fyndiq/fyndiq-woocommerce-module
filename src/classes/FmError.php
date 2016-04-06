<?php
/**
 * Handles showing errors on admin pages
 *
 */

//Boilerplate security. Doesn't allow this file to be directly executed by the browser.
defined('ABSPATH') || exit;

class FmError
{

    const CLASS_ERROR = 'error';
    const CLASS_UPDATED = 'updated';


    /**
     * setHooks sets the error handling hooks
     */
    public static function setHooks()
    {
        add_action('admin_notices', array(__CLASS__, 'processErrorAction'));
    }

    /**
     * processErrorAction handles the admin_notices action
     * @return bool
     */
    public static function processErrorAction()
    {
        if (isset($_REQUEST['fyndiqMessageType'])) {
            return self::renderError(
                urldecode($_REQUEST['fyndiqMessage']),
                urldecode($_REQUEST['fyndiqMessageType'])
            );
        }
    }

    /**
     * renderError renders notification message and escapes the parameters
     * @param  string $message error message
     * @param  string $messageType message class
     * @param  FmOutput $fmOutput
     * @return bool
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
     * renderErrorRaw renders notification message without escaping the parameters
     * @param  string $message error message
     * @param  string $messageType message class
     * @param  FmOutput $fmOutput
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
     * [handleError sets error message and type and refreshes the page to show it
     * @param  string $errorMessage error message
     * @param  sting $messageType message class
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
