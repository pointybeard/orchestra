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

namespace Orchestra\Orchestra;

use pointybeard\Helpers\Cli\Input;

abstract class AbstractAction implements Interfaces\ActionInterface
{
    public function addActionInputTypesToCollection(Input\InputCollection $collection): Input\InputCollection
    {
        return $collection;
    }
}
