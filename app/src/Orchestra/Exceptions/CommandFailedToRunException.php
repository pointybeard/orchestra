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

namespace Orchestra\Orchestra\Exceptions;

class CommandFailedToRunException extends \Exception
{
    private $command;
    private $error;

    public function __construct(string $command, string $error, int $code = 0, \Exception $previous = null)
    {
        $this->command = $command;
        $this->error = $error;
        parent::__construct("Failed to run command {$command}. Returned: {$error}", $code, $previous);
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getError()
    {
        return $this->error;
    }
}
