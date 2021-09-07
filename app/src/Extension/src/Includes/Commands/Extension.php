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

namespace pointybeard\Symphony\Extensions\Console\Commands\Orchestra;

use pointybeard\Helpers\Cli\Input;
use pointybeard\Helpers\Cli\Input\AbstractInputType as Type;
use pointybeard\Symphony\Extensions\Console as Console;

class Extension extends Console\AbstractCommand implements Console\Interfaces\AuthenticatedCommandInterface
{
    use Console\Traits\hasCommandRequiresAuthenticateTrait;

    public const ACTION_INSTALL = 'install';

    public const ACTION_ENABLE = 'enable';

    public const ACTION_DISABLE = 'disable';

    public const ACTION_UPDATE = 'update';

    public const ACTION_UNINSTALL = 'uninstall';

    public function __construct()
    {
        parent::__construct();
        $this
            ->description('console command for manipulating extensions (install, enable, disable, update, uninstall)')
            ->version('1.0.1')
            ->example(
                'symphony -t 4141e465 orchestra extensions install cron'
            )
            ->support("If you believe you have found a bug, please report it using the GitHub issue tracker at https://github.com/pointybeard/orchestra/issues, or better yet, fork the library and submit a pull request.\r\n\r\nCopyright 2019-2020 Alannah Kearney.\r\n")
        ;
    }

    public function usage(): string
    {
        return 'Usage: symphony [OPTIONS]... orchestra extensions ACTION NAME';
    }

    public function init(): void
    {
        parent::init();

        $this
            ->addInputToCollection(
                Input\InputTypeFactory::build('Argument')
                    ->name('action')
                    ->flags(Input\AbstractInputType::FLAG_REQUIRED)
                    ->description('action to run. Can be install, enable, disable, update, or uninstall')
                    ->validator(
                        function (Type $input, Input\AbstractInputHandler $context) {
                            $action = strtolower($context->find('action'));
                            if (!in_array(
                                $action,
                                [
                                    self::ACTION_INSTALL,
                                    self::ACTION_ENABLE,
                                    self::ACTION_DISABLE,
                                    self::ACTION_UPDATE,
                                    self::ACTION_UNINSTALL,
                                ]
                            )) {
                                throw new Console\Exceptions\ConsoleException('action must be install, enable, disable, update, or uninstall.');
                            }

                            return $action;
                        }
                    )
            )
            ->addInputToCollection(
                Input\InputTypeFactory::build('Argument')
                    ->name('name')
                    ->flags(Input\AbstractInputType::FLAG_REQUIRED)
                    ->description('Name of the extension')
                    ->validator(
                        function (Type $input, Input\AbstractInputHandler $context) {
                            $extension = strtolower($context->find('name'));
                            if (true == empty(\ExtensionManager::about($extension))) {
                                throw new Console\Exceptions\ConsoleException("Extension '{$extension}' could not be located");
                            }

                            return $extension;
                        }
                    )
            )
        ;
    }

    public function execute(Input\Interfaces\InputHandlerInterface $input): bool
    {
        $action = $input->find('action');
        $name = $input->find('name');

        call_user_func([__CLASS__, sprintf('do%s', ucfirst($action))], $name);

        return true;
    }

    private function doInstall(string $name): bool
    {
        return $this->doEnable($name);
    }

    private function doEnable(string $name): bool
    {
        \ExtensionManager::enable($name);

        return true;
    }

    private function doDisable(string $name): bool
    {
        \ExtensionManager::disable($name);

        return true;
    }

    private function doUpdate(string $name): bool
    {
        return $this->doEnable($name);
    }

    private function doUninstall(string $name): bool
    {
        \ExtensionManager::uninstall($name);

        return true;
    }
}
