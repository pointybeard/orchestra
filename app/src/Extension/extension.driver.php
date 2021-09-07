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

if (!file_exists(__DIR__.'/vendor/autoload.php')) {
    throw new Exception(sprintf('Could not find composer autoload file %s. Did you run `composer update` in %s?', __DIR__.'/vendor/autoload.php', __DIR__));
}

use pointybeard\Orchestra\Orchestra;
use pointybeard\Symphony\Extended;

// Check if the class already exists before declaring it again.
if (!class_exists('\\Extension_Orchestra')) {
    class Extension_Orchestra extends Extended\AbstractExtension
    {
        private static $credentials;

        public static function credentials()
        {
            if (!(self::$credentials instanceof Orchestra\Credentials)) {
                self::$credentials = new Orchestra\Credentials(
                    Orchestra\FileByExtensionIterator::fetch(ORCHESTRA_HOME.'/credentials', 'json')
                );
            }

            return self::$credentials;
        }
    }
}
