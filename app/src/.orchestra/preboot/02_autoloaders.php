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

require_once ORCHESTRA_HOME.'/lib/symphony/vendor/autoload.php';

// Include all of the extensions autoloaders
foreach ((new \DirectoryIterator(ORCHESTRA_HOME.'/lib/extensions')) as $e) {
    if (true == $e->isDot() || false == $e->isDir()) {
        continue;
    }

    // See if there is a composer.json
    if (false == file_exists($e->getPathname().'/composer.json')) {
        continue;
    }

    // See if the autoload exists
    if (false == file_exists($e->getPathname().'/vendor/autoload.php')) {
        continue;
    }

    require_once $e->getPathname().'/vendor/autoload.php';
}
