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

require_once ORCHESTRA_HOME.'/vendor/autoload.php';
require_once ORCHESTRA_HOME.'/lib/symphony/vendor/autoload.php';

// Include all of the extensions autoloaders. We need this sooner than
// the Symphony kernel can provide it.
foreach ((new \DirectoryIterator(ORCHESTRA_HOME.'/lib/extensions')) as $e) {

    // (guard) Not an extension folder
    if (true == $e->isDot() || false == $e->isDir()) {
        continue;
    }

    // (guard) No composer.json
    if (false == file_exists($e->getPathname().'/composer.json')) {
        continue;
    }

    // (guard) No autoload.php
    if (false == file_exists($e->getPathname().'/vendor/autoload.php')) {
        continue;
    }

    require_once $e->getPathname().'/vendor/autoload.php';
}
