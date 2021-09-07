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

class CannotClonePackageException extends \Exception
{
    public function __construct(string $repository, string $destination, int $code = 0, \Exception $previous = null)
    {
        parent::__construct("Unable to clone package {$repository}. Destination {$destination} already exists but is not a git repository.", $code, $previous);
    }
}
