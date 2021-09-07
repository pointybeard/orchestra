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

namespace Orchestra\Orchestra\Actions;

use Orchestra\Functions\Orchestra;
use Orchestra\Orchestra\AbstractAction;
use pointybeard\Helpers\Cli\Input;
use pointybeard\Helpers\Functions\Json;

class Init extends AbstractAction
{
    public function addActionInputTypesToCollection(Input\InputCollection $collection): Input\InputCollection
    {
        $collection
            ->add(
                Input\InputTypeFactory::build('Argument')
                    ->name('name')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL | Input\AbstractInputType::FLAG_VALUE_REQUIRED)
                    ->description('The name of the project being built. Default is name of current directory.')
                    ->default(basename(__WORKING_DIR__))
            )
        ;

        return $collection;
    }

    public function execute(Input\AbstractInputHandler $argv): void
    {
        if (true == file_exists(__WORKING_DIR__.'/.orchestra') || true == file_exists(__WORKING_DIR__.'/lib/extensions/orchestra')) {
            Orchestra\output("Unable to initialise Orchestra project. Returned: It looks like there is already an Orchstra project here. Try using 'build' or 'update' instead or remove the exists .orchestra directory.", Orchestra\OUTPUT_ERROR);
        }

        Orchestra\output('Building config ...', Orchestra\OUTPUT_HEADING);
        Orchestra\copyInternalPharDirectoryToDestination(__DIR__.'/../../.orchestra', __WORKING_DIR__);

        // Make the project JSON file in .orchestra
        file_put_contents(
            __WORKING_DIR__.'/.orchestra/project',
            json_encode(
                [
                    'name' => $argv->find('name'),
                    'created' => date('c'),
                    'orchestra-version-id' => ORCHESTRA_VERSION_ID,
                    'last-built' => null,
                ],
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
            )
        );
    }
}
