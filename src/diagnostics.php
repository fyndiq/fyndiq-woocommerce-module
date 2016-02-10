<?php
/**
 *
 * Contains the code to draw the submenu page, add it and execute its functions
 *
 */


/**
 * addFyndiqDiagMenu
 *
 * Adds the Fyndiq diagnostic menu to the admin page
 */
add_action('admin_menu', function () {
    add_submenu_page(null, 'Fyndiq Checker Page', 'Fyndiq', 'manage_options', 'fyndiq-check', function () {
        //'sup dawg I heard you like callbacks so I put a lambda in your lambda so you can callback while you callback
        echo "<h1>" . __('Fyndiq Checker Page', 'fyndiq') . "</h1>";
        echo "<p>" . __('This is a page to check all the important requirements to make the Fyndiq work.', 'fyndiq') . "</p>";

        echo "<h2>" . __('File Permission', 'fyndiq') . "</h2>";
        echo probe_file_permissions();

        echo "<h2>" . __('Classes', 'fyndiq') . "</h2>";
        echo probe_module_integrity();

        echo "<h2>" . __('API Connection', 'fyndiq') . "</h2>";
        echo probe_connection();

        echo "<h2>" . __('Installed Plugins', 'fyndiq') . "</h2>";
        echo probe_plugins();
    }
    );
});

function probe_plugins()
{
    $all_plugins = get_plugins();
    $installed_plugin = array();
    foreach ($all_plugins as $plugin) {
        $installed_plugin[] = $plugin['Name'] . ' v. ' . $plugin['Version'];
    }
    return implode('<br />', $installed_plugin);
}

//TODO: add new classes here
function probe_module_integrity()
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


function probe_file_permissions()
{
    $messages = array();
    $testMessage = time();
    try {
        $fileName = $GLOBALS['filePath'];
        $exists = file_exists($fileName) ?
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
        $content = file_get_contents($tempFileName);
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


function probe_connection()
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
