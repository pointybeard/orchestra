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

namespace pointybeard\Orchestra\Orchestra;

use pointybeard\Helpers\Functions\Json;

class Credentials
{
    private $credentials = [];

    public function __construct(\Iterator $credentials)
    {
        foreach ($credentials as $c) {
            $this->loadCredentialsFromFile($c);
        }
    }

    public function loadCredentialsFromFile(string $path): void
    {
        $this->credentials[pathinfo($path, PATHINFO_FILENAME)] = Json\json_decode_file($path);
    }

    public function __call($name, $arguments)
    {
        return $this->credentials[$name];
    }
}
