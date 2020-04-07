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

include 'vendor/autoload.php';

use Orchestra\Functions\Orchestra;
use pointybeard\Helpers\Cli\Colour\Colour;
use pointybeard\Helpers\Cli\Input;
use pointybeard\Helpers\Cli\Prompt\Prompt;
use pointybeard\Helpers\Foundation\Factory;
use pointybeard\Helpers\Functions\Cli;

const ORCHESTRA_VERSION = '1.0.0';
const ORCHESTRA_VERSION_ID = '10000';

/************\
| Pre-flight checks
************/

// Bash
if (false == Cli\can_invoke_bash()) {
    Orchestra\output('Unable to invoke bash from PHP. Build cannot proceed if unable to run commands with proc_open. See https://www.php.net/manual/en/function.proc-open.php', Orchestra\OUTPUT_ERROR);
}

// composer and git installed
try {
    Orchestra\which('git');
    Orchestra\which('composer');
} catch (\Exception $ex) {
    Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
}

/************\
| Define what we are expecting to get from the command line
************/
$collection = (new Input\InputCollection())
    ->add(
        Input\InputTypeFactory::build('LongOption')
            ->name('help')
            ->short('h')
            ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
            ->description('print this help')
    )
    ->add(
        Input\InputTypeFactory::build('LongOption')
            ->name('self-update')
            ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
            ->description("checks for any updates to orchestra's libraries. Note this can be run without any other options or arguments. i.e. `orchestra --self-update`")
            ->default(false)
    )
    ->add(
        Input\InputTypeFactory::build('IncrementingFlag')
            ->name('v')
            ->flags(Input\AbstractInputType::FLAG_OPTIONAL | Input\AbstractInputType::FLAG_TYPE_INCREMENTING)
            ->description('verbosity level. -v (errors only), -vv (warnings and errors), -vvv (everything).')
            ->validator(new Input\Validator(
                function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                    // Make sure verbosity level never goes above 3
                    return min(3, (int) $context->find('v'));
                }
            ))
    )
    ->add(
        Input\InputTypeFactory::build('LongOption')
            ->name('assume-yes')
            ->short('y')
            ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
            ->description('Automatic "yes" to prompts; assume "yes" as answer to all prompts and run non-interactively.')
            ->validator(new Input\Validator(
                function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                    if (true == $context->find('assume-no') || true == $context->find('assume-skip')) {
                        throw new Exception('does not make sense to specific --assume-yes, --assume-no, and/or --assume-skip at the same time.');
                    }

                    return true;
                }
            ))
            ->default(false)
    )
    ->add(
        Input\InputTypeFactory::build('LongOption')
            ->name('assume-no')
            ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
            ->description('Automatic "no" to all prompts.')
            ->validator(new Input\Validator(
                function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                    if (true == $context->find('assume-yes') || true == $context->find('assume-skip')) {
                        throw new Exception('does not make sense to specific --assume-yes, --assume-no, and/or --assume-skip at the same time.');
                    }

                    return true;
                }
            ))
            ->default(false)
    )
    ->add(
        Input\InputTypeFactory::build('LongOption')
            ->name('assume-skip')
            ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
            ->description('Automatic "skip" to all prompts.')
            ->validator(new Input\Validator(
                function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                    if (true == $context->find('assume-yes') || true == $context->find('assume-no')) {
                        throw new Exception('does not make sense to specific --assume-yes, --assume-no, and/or --assume-skip at the same time.');
                    }

                    return true;
                }
            ))
            ->default(false)
    )
    ->add(
        Input\InputTypeFactory::build('LongOption')
            ->name('working-directory')
            ->flags(Input\AbstractInputType::FLAG_VALUE_REQUIRED)
            ->description('Set the project directory. Defaults to CWD.')
            ->validator(new Input\Validator(
                function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                    $workingDirectory = $context->find('working-directory');
                    if ($workingDirectory != getcwd() && false == is_dir($workingDirectory)) {
                        throw new Exception('--working-directory is not a valid directory.');
                    }

                    return $workingDirectory;
                }
            ))
            ->default(getcwd())
    )
;

/************\
| Bind the collection just so we can check if --help was set
************/
try {
    $argv = Input\InputHandlerFactory::build(
        'Argv',
        $collection,
        Input\AbstractInputHandler::FLAG_VALIDATION_SKIP_UNRECOGNISED
    );
} catch (\Exception $ex) {
    Orchestra\output('A problem was encountered while attempting to process arguments and options. '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
}

$displayManpage = $argv->find('help');

/************\
| Now add the rest of the inputs to the collection so they show up in the help text
************/
$collection
    ->add(
        Input\InputTypeFactory::build('Argument')
            ->name('action')
            ->flags(Input\AbstractInputType::FLAG_REQUIRED)
            ->description("The name of the action to perform. Available actions are: build, init, and update. Note that the update action the same as using '--skip-create-author --skip-seeders --skip-import-sections --database-skip-import-structure --database-skip-import-data --run-migrations' on the build action.")
            ->validator(new Input\Validator(
                function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                    if (false == class_exists(__NAMESPACE__.'\\ActionFactory')) {
                        Factory\create(
                            __NAMESPACE__.'\\ActionFactory',
                            '\\Orchestra\\Orchestra\\Actions\\%s',
                            '\\Orchestra\\Orchestra\\AbstractAction'
                        );
                    }

                    try {
                        return ActionFactory::build(ucfirst($context->find('action')));
                    } catch (Factory\Exceptions\UnableToInstanciateConcreteClassException $ex) {
                        throw new \Exception('Invalid action specified.');
                    }
                }
            ))
    )
;

if (true == $displayManpage) {
    echo Cli\manpage(
        basename(__FILE__),
        ORCHESTRA_VERSION,
        'Orchestra is a metapackage for scaffolding and rapidly deploying Symphony CMS builds.',
        $collection,
        Colour::FG_GREEN,
        Colour::FG_WHITE,
        [
            'Examples' => 'orchestra build',
            'Support' => "If you believe you have found a bug, please report it using the GitHub issue tracker at https://github.com/pointybeard/orchestra/issues, or better yet, fork the library and submit a pull request.\r\n\r\nCopyright 2019-2020 Alannah Kearney. See https://github.com/pointybeard/orchestra/blob/master/LICENCE for software licence information.\r\n",
        ]
    );
    exit;
}

/************\
| Rebind the collection so we can validate all input
************/
try {
    $argv = Input\InputHandlerFactory::build(
        'Argv',
        $collection,
        Input\AbstractInputHandler::FLAG_VALIDATION_SKIP_UNRECOGNISED
    );
} catch (\Exception $ex) {
    Orchestra\output('A problem was encountered while attempting to process arguments and options. '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
}

/************\
| SET THE PROMPT_FLAGS CONSTANT
| This is set up so we can easily add extra flags later if needed
************/
$promptFlags = null;
if (true == $argv->find('assume-yes')) {
    $promptFlags = $promptFlags | Orchestra\FLAGS_PROMPT_ASSUME_YES;
} elseif (true == $argv->find('assume-no')) {
    $promptFlags = $promptFlags | Orchestra\FLAGS_PROMPT_ASSUME_NO;
} elseif (true == $argv->find('assume-skip')) {
    $promptFlags = $promptFlags | Orchestra\FLAGS_PROMPT_ASSUME_SKIP;
}
define('ORCHESTRA_PROMPT_FLAGS', $promptFlags);

/************\
| Run the self update proceedure
************/
if (true == $argv->find('self-update')) {
    // @TODO: Not 100% sure what this will look like yet
    // Probably going to be cloning orchestra repo to temp folder
    // then running make && make install and exiting.
    exit(0);
}

/************\
| Warn the user about running as root
************/
if (true == Cli\is_su()) {
    Orchestra\output('It is not a good idea to run this script as root. Consider using a non-root user.', Orchestra\OUTPUT_WARNING);
    Orchestra\ask_to_proceed();
}

/************\
| Set up the correct __WORKING_DIR__
************/
define('__WORKING_DIR__', $argv->find('working-directory'));

/************\
| Bootstrap is complete; now run the action
************/
$action = $argv->find('action');

$collection = $action->addActionInputTypesToCollection($collection);

try {
    $argv = Input\InputHandlerFactory::build(
        'Argv',
        $collection,
        Input\AbstractInputHandler::FLAG_VALIDATION_SKIP_UNRECOGNISED
    );
} catch (\Exception $ex) {
    Orchestra\output('A problem was encountered while attempting to process arguments and options. '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
}

$action->execute($argv);
