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

class DirectoryNotGitRepositoryException extends \Exception
{
    public function __construct(string $path, int $code = 0, \Exception $previous = null)
    {
        parent::__construct("Directory {$path} is not a git repository.", $code, $previous);
    }
}
