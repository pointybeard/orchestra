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

namespace Orchestra\Orchestra\Exceptions;

class SymlinkExistsException extends \Exception
{
    public function __construct(string $name, int $code = 0, \Exception $previous = null)
    {
        parent::__construct("Symbolic link {$name} already exists.", $code, $previous);
    }
}
