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

namespace Orchestra\Orchestra\Interfaces;

use pointybeard\Helpers\Cli\Input;

interface ActionInterface
{
    public function addActionInputTypesToCollection(Input\InputCollection $collection): Input\InputCollection;

    public function execute(Input\AbstractInputHandler $argv): void;
}
