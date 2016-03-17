<?php
/**
 *
 * Handles the diagnostic page for the plugin
 *
 */


class FmDiagnostics
{
    public static function setHooks()
    {
        add_action('admin_menu', array(__CLASS__, 'addDiagnosticMenuItem'));
        add_filter('plugin_action_links_' . plugin_basename(dirname(__FILE__).'/woocommerce-fyndiq.php'), array(__CLASS__, 'pluginActionLink'));
    }

    public static function addDiagnosticMenuItem()
    {
        add_submenu_page(null, 'Fyndiq Checker Page', 'Fyndiq', 'manage_options', 'fyndiq-check', array(__CLASS__, 'diagPage'));
    }

    public static function pluginActionLink($links)
    {
        $checkUrl = esc_url(get_admin_url(null, 'admin.php?page=fyndiq-check'));
        $links[] = '<a href="'.$checkUrl.'">'.__('Fyndiq Check', 'fyndiq').'</a>';
        return $links;
    }

    public static function diagPage()
    {
        echo "<h1>".__('Fyndiq Checker Page', 'fyndiq')."</h1>";
        echo "<p>".__('This is a page to check all the important requirements to make the Fyndiq work.', 'fyndiq')."</p>";

        echo "<h2>".__('File Permission', 'fyndiq')."</h2>";
        echo self::probeFilePermissions();

        echo "<h2>".__('Classes', 'fyndiq')."</h2>";
        echo self::probeModuleIntegrity();

        echo "<h2>".__('API Connection', 'fyndiq')."</h2>";
        echo self::probeConnection();

        echo "<h2>".__('Installed Plugins', 'fyndiq')."</h2>";
        echo self::probePlugins();
    }

    private function probeFilePermissions()
    {
        $messages = array();
        $testMessage = time();
        try {
            $fileName = $this->filePath;
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

    private function probeModuleIntegrity()
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
            'FmHelpers'
        );
        try {
            foreach ($checkClasses as $className) {
                if (class_exists($className)) {
                    $messages[] = sprintf(__('Class `%s` is found.', 'fyndiq'), $className);
                    continue;
                }
                $messages[] = sprintf(__('Class `%s` is NOT found.', 'fyndiq'), $className);
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
    private function probeConnection()
    {
        $messages = array();
        try {
            try {
                FmHelpers::callApi('GET', 'settings/');
            } catch (Exception $e) {
                if ($e instanceof FyndiqAPIAuthorizationFailed) {
                    throw new Exception(__('Module is not authorized.', 'fyndiq'));
                }
            }
            $messages[] = __('Connection to Fyndiq successfully tested', 'fyndiq');
            return implode('<br />', $messages);
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return implode('<br />', $messages);
        }
    }

    private function probePlugins()
    {
        $all_plugins = get_plugins();
        $installed_plugin = array();
        foreach ($all_plugins as $plugin) {
            $installed_plugin[] = $plugin['Name'] . ' v. ' . $plugin['Version'];
        }
        return implode('<br />', $installed_plugin);
    }
}
