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

use pointybeard\Helpers\Cli\Colour\Colour;
use pointybeard\Helpers\Cli\Input;
use pointybeard\Helpers\Cli\Message\Message;
use pointybeard\Helpers\Foundation\Factory;
use pointybeard\Orchestra\Orchestra\FileByExtensionIterator;
use pointybeard\Symphony\Extensions\Console as Console;

class Seed extends Console\AbstractCommand implements Console\Interfaces\AuthenticatedCommandInterface
{
    use Console\Traits\hasCommandRequiresAuthenticateTrait;

    public function __construct()
    {
        parent::__construct();
        $this
            ->description('console command for running seeders provided by orchestra app)')
            ->version('2.0.0')
            ->example(
                'symphony -t 4141e465 orchestra seed'
            )
            ->support("If you believe you have found a bug, please report it using the GitHub issue tracker at https://github.com/pointybeard/orchestra/issues, or better yet, fork the library and submit a pull request.\r\n\r\nCopyright 2019-2020 Alannah Kearney.\r\n")
        ;
    }

    public function usage(): string
    {
        return 'Usage: symphony [OPTIONS]... orchestra seed';
    }

    public function execute(Input\Interfaces\InputHandlerInterface $input): bool
    {
        Factory\create(
            __NAMESPACE__.'\\SeederFactory',
            '\\pointybeard\\Orchestra\\App\\Seeders\\%s',
            '\\pointybeard\\Orchestra\\Orchestra\\AbstractSeeder'
        );

        $seeders = FileByExtensionIterator::fetch(ORCHESTRA_HOME.'/.orchestra/seeders', 'php');

        $count = 0;
        $total = $seeders->count();

        foreach ($seeders as $seederPath) {
            ++$count;

            $name = pathinfo($seederPath, PATHINFO_FILENAME);

            (new Message("[{$count}/{$total}] {$name} ... "))
                ->foreground(Colour::FG_GREEN)
                ->flags(Message::FLAG_NONE)
                ->display()
            ;

            try {
                SeederFactory::build($name)
                    ->run()
                ;

                (new Message('ok'))
                    ->foreground(Colour::FG_GREEN)
                    ->display()
                ;
            } catch (\Exception $ex) {
                (new Message("failed! returned: {$ex->getMessage()}"))
                    ->foreground(Colour::FG_RED)
                    ->display(STDERR)
                ;
            }
        }

        return true;
    }
}
