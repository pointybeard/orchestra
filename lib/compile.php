<?php

declare(strict_types=1);

/*
 * This file is part of the "Orchestra" repository.
 *
 * Copyright 2019-2021 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

$app = realpath(__DIR__.'/../app');
$dest = realpath("{$app}/../build");
$name = 'orchestra.phar';
$shebang = '#!/usr/bin/env php';

function failed($message)
{
    fwrite(STDERR, "ERROR! {$message}".PHP_EOL);
    exit(1);
}

if (false === $app) {
    failed('App directory could not be located.');
}

if (false === $dest) {
    failed('Destination directory does not exist.');
}

try {
    $p = new Phar("{$dest}/{$name}", 0, $name);
    $p->startBuffering();
    $p->buildFromDirectory($app);
    $p->setStub(sprintf('%s%s%s', $shebang, PHP_EOL, $p->createDefaultStub('main.php')));
    $p->stopBuffering();
    $p->compressFiles(Phar::GZ);
} catch (Exception $ex) {
    failed("Unable to create archive {$dest}/{$name}. Returned: {$ex->getMessage()}");
}

echo "{$dest}/{$name} successfully built".PHP_EOL;
exit(0);
