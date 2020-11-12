<?php

declare(strict_types=1);

/*
 * This file is part of the "Orchestra" repository.
 *
 * Copyright 2019-2020 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

use Symphony\Symphony;

const ORCHESTRA_VERSION = '1.0.0';
const ORCHESTRA_VERSION_ID = '10100';

if ('symphony.console' == getenv('HTTP_HOST')) {
    putenv('orchestra.mode=console');
}

$defaults = '{
    "define.httpenv": 1,
    "define.manifest": 1,
    "define.workspace": 1
}';

foreach (json_decode($defaults, true) as $name => $value) {
    if (false == getenv("orchestra.{$name}")) {
        putenv("orchestra.{$name}={$value}");
    }
}

Symphony\define_from_env('ORCHESTRA_HOME', realpath(dirname(__FILE__).'/../../'));
Symphony\define_from_env('SYMPHONY_DOCROOT', ORCHESTRA_HOME.'/lib/symphony');

// Orchestra supports 3 modes: administration, frontend, and console. There
// is no default value so developers must define it themselves.
try {
    Symphony\define_from_env('orchestra.mode', null, 'ORCHESTRA_MODE');
    // Make sure the Orchestra mode is valid.
    if (false == in_array(ORCHESTRA_MODE, ['administration', 'frontend', 'console'])) {
        throw new \Exception(sprintf('%s is not a valid site mode. Must be administration, frontend, or console', ORCHESTRA_MODE));
    }
} catch (\Exception $ex) {
    throw new \Exception('No valid Orchestra site mode was set. Returned: '.$ex->getMessage());
}

// If the site mode is administration or frontend, and orchestra.define.httpenv
// is enabled, the SYMPHONY_URL define is set before Symphony gets the
// opportunity, avoiding the need for a hack in lib/boot/bundle.php.
//
// Note that setting orchestra.defineHttpEnv to anything other than 1 will
// require developers handle this next section themselves if they want a
// functioning admin or frontend.
if (in_array(ORCHESTRA_MODE, ['administration', 'frontend']) && 1 == getenv('orchestra.define.httpenv')) {
    // __SECURE__, DOMAIN, and URL are direct duplicates from Symphony's
    // lib/boot/defines.php, but we need them created earlier for setting
    // SYMPHONY_URL correctly

    // __SECURE__
    define(
        '__SECURE__',
        ('on' == getenv('HTTPS') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' == $_SERVER['HTTP_X_FORWARDED_PROTO']))
    );

    // DOMAIN
    define('DOMAIN', preg_replace(
        ['@/{2,}@', '@/+$@'], //Look for multiple forward slashes and trailing slashes
        ['/', ''], //Replace multiple with a single, and the trailing with NULL
        sprintf('%s/%s', $_SERVER['HTTP_HOST'], dirname($_SERVER['PHP_SELF']))
    ));

    // URL
    define('URL', sprintf(
        'http%s://%s',
        __SECURE__ ? 's' : '',
        DOMAIN
    ));

    // SYMPHONY_URL
    // This needs to be set here in order prevent the admin path value from
    // being added to the end, making way for using a subdomain to access the
    // administration instead.
    define('SYMPHONY_URL', sprintf('%s', URL));
}

// Set path to WORKSPACE
if (1 == getenv('orchestra.define.workspace')) {
    $workspace = ORCHESTRA_HOME.'/var/workspace';

    // Check to ensure the workspace folder looks okay
    if (false == is_dir($workspace)) {
        throw new Exception(sprintf('Workspace folder "%s" does not exist.', $workspace));
    }
    define('WORKSPACE', $workspace);
}

// Set path to MANIFEST
if (1 == getenv('orchestra.define.manifest')) {
    $manifest = ORCHESTRA_HOME.'/var/manifest';

    if (false == is_readable($manifest.'/config.json')) {
        throw new Exception(sprintf('Configuration file "%s/config.json" could not be read. Check it exists.', $manifest));
    }

    define('MANIFEST', $manifest);
    define('CONFIG', $manifest.'/config.json');
}
