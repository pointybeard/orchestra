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

/*
 * index.php was removed from the essentials branch of pointybeard/symphonycms
 * so we need to add it back in. This file is symlink'd into the /www/*
 * directories
 */

// Find out where we are:
define('DOCROOT', realpath(__DIR__.'/../lib/symphony'));

// Propagate this change to all executables:
chdir(DOCROOT);

// Include autoloader:
require_once DOCROOT.'/vendor/autoload.php';

// Include the boot script:
require_once DOCROOT.'/symphony/lib/boot/bundle.php';

// Begin Symphony proper:
symphony($_GET['mode'] ?? null);
