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

        public function install()
        {
            parent::install();

            return \Symphony::Database()->query(
                "CREATE TABLE `tbl_fields_attachment` (
                  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                  `field_id` int(11) unsigned NOT NULL,
                  `destination` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `validator` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                  `prepend_datestamp` enum('yes','no') NOT NULL default 'no',
                  PRIMARY KEY (`id`),
                  KEY `field_id` (`field_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );
        }
    }
}
