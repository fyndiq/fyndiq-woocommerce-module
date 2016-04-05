<?php
/**
 *
 * Handles the diagnostic page for the plugin
 *
 */


class FmDiagnostics
{
    /**
     * This registers the various hooks with WordPress
     */
    public static function setHooks()
    {
        add_action('admin_menu', array(get_called_class(), 'addDiagnosticMenuItem'));
        $basename =  plugin_basename(plugin_dir_path(dirname(__FILE__)) . 'woocommerce-fyndiq.php');
        return add_filter('plugin_action_links_' . $basename, array(get_called_class(), 'pluginActionLink'));
    }

    /**
     * Adds the diagnostic page as a menu item
     */
    public static function addDiagnosticMenuItem()
    {
        return add_submenu_page('tools.php', 'Fyndiq Checker Page', 'Fyndiq', 'manage_options', 'fyndiq-check', array(get_called_class(), 'diagPage'));
    }

    /**
     * Filter function that adds an action link in the WordPress plugin page for the diagnostic page
     *
     * @param $links - the existing action link array
     * @return array - $links, with an added action link
     */
    public static function pluginActionLink($links)
    {
        $checkUrl = esc_url(get_admin_url(null, 'admin.php?page=fyndiq-check'));
        $links[] = '<a href="' . $checkUrl . '">' . __('Fyndiq Check', 'fyndiq') . '</a>';
        return $links;
    }

    /**
     * Outputs the raw HTML for the diagnostic page's body
     */
    public static function diagPage()
    {
        $fmOutput = new FyndiqOutput();
        $fmOutput->output("<h1>" . __('Fyndiq Integration Diagnostic Page', 'fyndiq') . "</h1>");
        $fmOutput->output("<p>" . __('This page contains diagnostic information that may be useful in the 
        event that the Fyndiq WooCommerce integration plugin runs in to problems.', 'fyndiq') . "</p>");

        $fmOutput->output("<h2>" . __('File Permissions', 'fyndiq') . "</h2>");
        $fmOutput->output(self::probeFilePermissions());

        $fmOutput->output("<h2>" . __('Classes', 'fyndiq') . "</h2>");
        $fmOutput->output(self::probeModuleIntegrity());

        $fmOutput->output("<h2>" . __('API Connection', 'fyndiq') . "</h2>");
        $fmOutput->output(self::probeConnection());

        $fmOutput->output("<h2>" . __('Installed Plugins', 'fyndiq') . "</h2>");
        $fmOutput->output(self::probePlugins());
    }

    /**
     * Checks that the feed can be successfully written to and read back
     *
     * @return string - HTML output of log data from the function
     */
    private static function probeFilePermissions()
    {
        //This needs to be two-step to ensure compatibility with < PHP5.5
        $uploadDir = wp_upload_dir();
        $fileName = $uploadDir['basedir'] . '/fyndiq-feed.csv';

        $messages = array();
        $testMessage = time();
        try {
            $exists =  file_exists($fileName) ?
                __('exists', 'fyndiq') :
                __('does not exist', 'fyndiq');
            $messages[] = sprintf(__('Feed file name: `%s` (%s)', 'fyndiq'), $fileName, $exists);
            $tempFileName = FyndiqUtils::getTempFilename(dirname($fileName));
            if (dirname($tempFileName) !== dirname($fileName)) {
                throw new Exception(sprintf(
                    __('Cannot create file. Please make sure that the server can create new files in `%s`', 'fyndiq'),
                    dirname($fileName)
                ));
            }
            $messages[] = sprintf(__('Trying to create temporary file: `%s`', 'fyndiq'), $tempFileName);
            $file = fopen($tempFileName, 'w+');
            if (!$file) {
                throw new Exception(sprintf(__('Cannot create file: `%s`', 'fyndiq'), $tempFileName));
            }
            fwrite($file, $testMessage);
            fclose($file);
            if ($testMessage == file_get_contents($tempFileName)) {
                $messages[] = sprintf(__('File `%s` successfully read.', 'fyndiq'), $tempFileName);
            }
            FyndiqUtils::deleteFile($tempFileName);
            $messages[] = sprintf(__('Successfully deleted temp file `%s`', 'fyndiq'), $tempFileName);
            return implode('<br />', $messages);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return implode('<br />', $messages);
        }
    }

    /**
     * Checks that all of the classes that we expect to be loaded are
     *
     * @return string - HTML output of log data from the function
     */
    private static function probeModuleIntegrity()
    {
        $messages = array();
        $missing = array();
        $checkClasses = array(
            'FyndiqAPI',
            'FyndiqAPICall',
            'FyndiqCSVFeedWriter',
            'FyndiqFeedWriter',
            'FyndiqOutput',
            'FyndiqPaginatedFetch',
            'FyndiqUtils',
            'FmHelpers',
            'FmDiagnostics',
            'FmError',
            'FmExport',
            'FmField',
            'FmSettings',
            'FmUpdate',
            'FmOrder',
            'FmOrderFetch',
            'FmPost',
            'FmProduct',
            'TGM_Plugin_Activation',
        );
        try {
            foreach ($checkClasses as $className) {
                if (class_exists($className)) {
                    $messages[] = sprintf(__('Class `%s` is found.', 'fyndiq'), $className);
                    continue;
                }
                $messages[] = sprintf(__('Class `%s` is <strong>NOT</strong> found.', 'fyndiq'), $className);
            }
            if ($missing) {
                throw new Exception(sprintf(
                    __('Required classes `%s` are missing.', 'fyndiq'),
                    implode(',', $missing)
                ));
            }
            return implode('<br />', $messages);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return implode('<br />', $messages);
        }
    }

    /**
     * Checks whether the plugin has successfully connected/authenticated to/with the Fyndiq backend
     *
     * @return string - HTML output of log data from the function
     * @throws Exception FyndiqAPIAuthorizationFailed
     */
    private static function probeConnection()
    {
        $messages = array();
        try {
            FmHelpers::callApi('GET', 'settings/');
        } catch (Exception $e) {
            if ($e instanceof FyndiqAPIAuthorizationFailed) {
                throw new Exception(__('Module is not authorized.', 'fyndiq'));
            }
        }
        $messages[] = __('Successfully connected to the Fyndiq API', 'fyndiq');
        return implode('<br />', $messages);
    }

    /**
     * Gets a list of the installed plugins and their version
     *
     * @return string - HTML output of log data from the function
     */
    private static function probePlugins()
    {
        $all_plugins = get_plugins();
        $installed_plugin = array();
        foreach ($all_plugins as $plugin) {
            $installed_plugin[] = $plugin['Name'] . ' v. ' . $plugin['Version'];
        }
        return implode('<br />', $installed_plugin);
    }
}
