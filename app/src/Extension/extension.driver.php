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

use pointybeard\Orchestra\Orchestra;

// Check if the class already exists before declaring it again.
if (!class_exists('\\Extension_Orchestra')) {
    class Extension_Orchestra extends Extension
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

        public static function init()
        {
        }

        public function install()
        {
            // Symlink the Attachment field for Section Builder

            return true;
        }

        public function update($previousVersion = false): bool
        {
            return $this->install();
        }

        public function enable(): bool
        {
            return $this->install();
        }
    }
}
