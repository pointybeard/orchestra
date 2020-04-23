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

namespace Orchestra\Orchestra\Actions;

use Orchestra\Functions\Orchestra;
use Orchestra\Orchestra\AbstractAction;
use Orchestra\Orchestra\Exceptions;
use pointybeard\Helpers\Cli\Colour\Colour;
use pointybeard\Helpers\Cli\Input;
use pointybeard\Helpers\Cli\Message\Message;
use pointybeard\Helpers\Cli\ProgressBar;
use pointybeard\Helpers\Cli\Prompt\Prompt;
use pointybeard\Helpers\Functions\Files;
use pointybeard\Helpers\Functions\Json;
use SymphonyPDO;

class Build extends AbstractAction
{
    public function addActionInputTypesToCollection(Input\InputCollection $collection): Input\InputCollection
    {
        $collection
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-composer')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips running composer update on composable packages')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-git-reset')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips resetting and updating git packages that already exist')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-create-author')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips creating the admin author. Use this to keep existing authors intact. Note, skipping author creation means no author token is available. Orchestra will attempt to locate one by looking for existing authors. If none can be found, and error will be thrown.')
                    ->validator(new Input\Validator(
                        function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                            if (true == $context->find('database-drop-tables')) {
                                Orchestra\output('--database-drop-tables is set. Ignoring use of --skip-create-author.', Orchestra\OUTPUT_WARNING);

                                return false;
                            }

                            return true;
                        }
                    ))
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-enable-extensions')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips enabling of any extensions.')
                    ->validator(new Input\Validator(
                        function (Input\AbstractInputType $input, Input\AbstractInputHandler $context) {
                            if (true == $context->find('database-drop-tables')) {
                                Orchestra\output('--database-drop-tables is set. Ignoring use of --skip-enable-extensions.', Orchestra\OUTPUT_WARNING);

                                return false;
                            }

                            return true;
                        }
                    ))
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-seeders')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips running database seeders.')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-postbuild')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips running the post build script.')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('skip-import-sections')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips importing sections.json')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('database-drop-tables')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Remove all tables from target database before importing SQL or building sections. WARNING: This is highly destructive so be sure to backup existing data first. Recommened using --database-create-backup as well')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('database-create-backup')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL | Input\AbstractInputType::FLAG_VALUE_REQUIRED)
                    ->description('Prior to commencing work, an entire backup of your current database will be made and saved to the location specified. HIGHLY RECOMMENDED if using --database-drop-tables')
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('database-skip-import-structure')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips importing structure.sql')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('database-skip-import-data')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Skips importing data.sql')
                    ->default(false)
            )
            ->add(
                Input\InputTypeFactory::build('LongOption')
                    ->name('run-migrations')
                    ->flags(Input\AbstractInputType::FLAG_OPTIONAL)
                    ->description('Tells Orchestra to run migrations scripts. Should not be used with --database-drop-tables. Note: Migrations are run AFTER seeders. Consider using --skip-seeders if you need to run migrations')
                    ->default(false)
            )
        ;

        return $collection;
    }

    public function execute(Input\AbstractInputHandler $argv): void
    {
        /************\
        | Make sure this is an orchestra project
        ************/
        if (false == is_dir(__WORKING_DIR__.'/.orchestra') || false == file_exists(__WORKING_DIR__.'/.orchestra/build.json')) {
            Orchestra\output('This does not look like an Orchestra project. Use --working-directory to set path to your project. Exiting.', Orchestra\OUTPUT_ERROR);
            exit;
        }

        /************\
        | Make sure composer has been run on __WORKING_DIR__
        ************/
        if (true == Orchestra\isComposable(__WORKING_DIR__) && false == is_dir(__WORKING_DIR__.'/vendor')) {
            Orchestra\output('Looks like composer packages need to be installed', Orchestra\OUTPUT_NOTICE);
            Orchestra\composerRunOnDirectory(__WORKING_DIR__);
        }

        /************\
        | Load .orchestra/build.json
        ************/
        Orchestra\output('Loading build config...', Orchestra\OUTPUT_HEADING);

        try {
            $build = Json\json_decode_file(__WORKING_DIR__.'/.orchestra/build.json');
        } catch (\JsonException $ex) {
            Orchestra\output('Unable to load required build configuration. Check .orchestra/build.json. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
        }

        /************\
        | Load .orchestra/config.default.json
        ************/
        Orchestra\output('Loading default config...', Orchestra\OUTPUT_HEADING);

        try {
            $config = Json\json_decode_file(__WORKING_DIR__.'/.orchestra/config.default.json');
        } catch (\JsonException $ex) {
            Orchestra\output('Unable to load required default configuration. Check .orchestra/config.default.json. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
        }

        /************\
        | Merge the config from build.json with config.json. Check for null values
        | and throw an error if any are found
        /************/
        foreach ($build->config as $group => $values) {
            foreach ($values as $key => $value) {
                $config->$group->$key = $value;
            }
        }

        try {
            foreach ($config as $group => $values) {
                foreach ($values as $key => $value) {
                    if (null == $value) {
                        // THis will not be null if --assume-yes, --assume-no,
                        // or --assume-skip are set in which case the user
                        // is attempting a non-interactive build. Do not display
                        // a prompt, just throw an exception to trigger the
                        // build to fail.
                        if (null != ORCHESTRA_PROMPT_FLAGS) {
                            throw new Exceptions\MissingConfigException("{$group}->{$key}");
                        }

                        Orchestra\output("Missing configiration value for {$group}->{$key}", Orchestra\OUTPUT_WARNING);
                        $config->$group->$key = (new Prompt('Enter value'))
                            ->validator(function ($input) {
                                if (strlen(trim($input)) <= 0) {
                                    (new Message())
                                        ->message('A value must be provided.')
                                        ->foreground(Colour::FG_YELLOW)
                                        ->display()
                                    ;

                                    return false;
                                }

                                return true;
                            })
                            ->display()
                        ;
                    }
                }
            }
        } catch (Exceptions\MissingConfigException $ex) {
            Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
        }

        /************\
        | Test the database connection
        ************/
        Orchestra\output('Establishing database connection...', Orchestra\OUTPUT_HEADING);

        try {
            $db = SymphonyPDO\Loader::instance($config->database);
            if (false == ($db instanceof \SymphonyPDO\Lib\Database)) {
                throw new \Exception('SymphonyPDO Loader::instance() did not return a valid Database object!');
            } elseif (false == $db->connected()) {
                throw new \Exception('Database object is connection is not an instance of PDO!');
            }
        } catch (\Exception $ex) {
            Orchestra\output('Unable to connect to database. Check credentials in build.json. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
        }

        /************\
        | Remove all existing tables if requested
        \************/
        $databaseWereTablesDropped = false;
        if (true === $argv->find('database-drop-tables')) {
            Orchestra\output('Dropping existing tables from database...', Orchestra\OUTPUT_HEADING);
            Orchestra\output('Using --database-drop-tables is highly destructive and will delete ALL data in the database. It is recommended to use --database-create-backup.', Orchestra\OUTPUT_WARNING);

            if (Orchestra\FLAGS_YES == Orchestra\ask_to_proceed(ORCHESTRA_PROMPT_FLAGS, 'Do you want to continue anyway %s?')) {
                try {
                    Orchestra\dropAllTablesFromDatabase($config->database);
                    $databaseWereTablesDropped = true;
                } catch (Exceptions\CommandFailedToRunException $ex) {
                    Orchestra\output('Attempt to drop all tables failed. Returned: '.$ex->getError(), Orchestra\OUTPUT_ERROR);
                } catch (\Exception $ex) {
                    Orchestra\output('Attempt to drop all tables failed. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
                }
            } else {
                Orchestra\output('skipping', Orchestra\OUTPUT_NOTICE);
            }
        }

        /************\
        | Build database by running database.sql
        \************/
        if (true == file_exists(__WORKING_DIR__.'/.orchestra/structure.sql')) {
            Orchestra\output('Importing database structure...', Orchestra\OUTPUT_HEADING);

            $answer = Orchestra\FLAGS_YES;

            if ((false == $databaseWereTablesDropped || false === $argv->find('database-drop-tables')) && true == Orchestra\doesDatabaseTableExist('tbl_authors', $config->database)) {
                Orchestra\output("Unable to import structure.sql. It looks like there are existing Symphony CMS tables in database '{$config->database->db}'. Hint: Use --database-drop-tables to clear the database.", Orchestra\OUTPUT_WARNING);
                $answer = Orchestra\ask_to_proceed(ORCHESTRA_PROMPT_FLAGS, 'Do you want to continue anyway %s?');
            }

            if (Orchestra\FLAGS_YES == $answer) {
                try {
                    Orchestra\importSqlFromFile(__WORKING_DIR__.'/.orchestra/structure.sql', $config->database);
                } catch (Exceptions\CommandFailedToRunException $ex) {
                    Orchestra\output('Unable to import database structure. Returned: '.$ex->getError(), Orchestra\OUTPUT_ERROR);
                } catch (\Exception $ex) {
                    Orchestra\output('Unable to import database structure. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
                }
            } else {
                Orchestra\output('skipping', Orchestra\OUTPUT_NOTICE);
            }
        }

        /************\
        | Run data.sql if it exists
        \************/
        if (true == file_exists(__WORKING_DIR__.'/.orchestra/data.sql')) {
            Orchestra\output('Importing database data...', Orchestra\OUTPUT_HEADING);

            $answer = Orchestra\FLAGS_YES;

            if ((false == $databaseWereTablesDropped || false === $argv->find('database-drop-tables')) && true == Orchestra\doesDatabaseTableExist('tbl_authors', $config->database)) {
                Orchestra\output("Unable to import data.sql. It looks like there are existing Symphony CMS tables in database '{$config->database->db}'. Hint: Use --database-drop-tables to clear the database.", Orchestra\OUTPUT_WARNING);
                $answer = Orchestra\ask_to_proceed(ORCHESTRA_PROMPT_FLAGS, 'Do you want to continue anyway %s?');
            }

            if (Orchestra\FLAGS_YES == $answer) {
                try {
                    Orchestra\importSqlFromFile(__WORKING_DIR__.'/.orchestra/data.sql', $config->database);
                } catch (Exceptions\CommandFailedToRunException $ex) {
                    Orchestra\output('Unable to import database data. Returned: '.$ex->getError(), Orchestra\OUTPUT_ERROR);
                } catch (\Exception $ex) {
                    Orchestra\output('Unable to import database data. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
                }
            } else {
                Orchestra\output('skipping', Orchestra\OUTPUT_NOTICE);
            }
        }

        /************\
        | Build out paths specified
        \************/
        Orchestra\output('Building directory paths...', Orchestra\OUTPUT_HEADING);
        foreach ($build->paths as $ii => $p) {
            Orchestra\output(sprintf(
                '[%d/%d] %s ...',
                $ii + 1,
                count($build->paths),
                $p
            ), Orchestra\OUTPUT_INFO, null);

            try {
                Files\realise_directory(__WORKING_DIR__."/{$p}");
                Orchestra\output('done', Orchestra\OUTPUT_SUCCESS);
            } catch (Files\Exceptions\Directory\AlreadyExistsException $ex) {
                Orchestra\output('exists', Orchestra\OUTPUT_NOTICE);
            } catch (Files\Exceptions\Directory\CreationFailedException $ex) {
                Orchestra\output('Failed!', Orchestra\OUTPUT_WARNING);
                Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
            }
        }

        /************\
        | Install the Orchestra companion extension to /lib/extensions
        ************/
        Orchestra\output('Installing Orchestra extension ...', Orchestra\OUTPUT_HEADING);
        Orchestra\copyInternalPharDirectoryToDestination(__DIR__.'/../../Extension', __WORKING_DIR__.'/lib/extensions', 'orchestra');

        // Run composer on lib/extensions/orchestra
        if (false == $argv->find('skip-composer') || false == is_dir(__WORKING_DIR__.'/lib/extensions/orchestra/vendor')) {
            Orchestra\composerRunOnDirectory(__WORKING_DIR__.'/lib/extensions/orchestra');
        }

        /************\
        | Download required libraries and extensions
        \************/
        $flags = null;

        if (true == $argv->find('skip-composer')) {
            $flags = $flags | Orchestra\FLAGS_SKIP_COMPOSER;
        }

        if (true == $argv->find('skip-git-reset')) {
            $flags = $flags | Orchestra\FLAGS_SKIP_GIT_RESET;
        }

        Orchestra\output('Installing libraries...', Orchestra\OUTPUT_HEADING);
        foreach ($build->libraries as $ii => $l) {
            Orchestra\output(sprintf(
                '[%d/%d] %s ...',
                $ii + 1,
                count($build->libraries),
                Orchestra\getDirnameFromGitRepositoryUrl($l->repository->url)
            ), Orchestra\OUTPUT_INFO, null);

            try {
                $l->cleanup = $l->cleanup ?? [];
                Orchestra\installLibrary($l, $flags);
            } catch (\Exception $ex) {
                Orchestra\output('Failed!', Orchestra\OUTPUT_WARNING);
                Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
            }
        }

        Orchestra\output('Installing extensions...', Orchestra\OUTPUT_HEADING);
        foreach ($build->extensions as $ii => $e) {
            $e->name = $e->name ?? Orchestra\getDirnameFromGitRepositoryUrl($e->repository->url);

            Orchestra\output(sprintf(
                '[%d/%d] %s ...',
                $ii + 1,
                count($build->extensions),
                $e->name
            ), Orchestra\OUTPUT_INFO, null);

            try {
                $e->cleanup = $e->cleanup ?? [];
                Orchestra\installExtension($e, $flags);
            } catch (\Exception $ex) {
                Orchestra\output('Failed!', Orchestra\OUTPUT_WARNING);
                Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
            }
        }

        /************\
        | Create admin user
        \************/
        Orchestra\output('Generating admin author...', Orchestra\OUTPUT_HEADING);
        require_once __WORKING_DIR__.'/lib/symphony/symphony/lib/toolkit/class.cryptography.php';
        require_once __WORKING_DIR__.'/lib/symphony/symphony/lib/toolkit/cryptography/class.pbkdf2.php';
        require_once __WORKING_DIR__.'/lib/symphony/symphony/lib/toolkit/class.general.php';
        require_once __WORKING_DIR__.'/lib/symphony/symphony/lib/toolkit/class.author.php';

        $adminPasswordPlain = null;
        if (true !== $argv->find('skip-create-author')) {
            $adminPasswordPlain = Orchestra\randomString(12);
            $author = new \Author();
            $author->set('username', $build->author->username);
            $author->set('password', \Cryptography::hash($adminPasswordPlain));
            $author->set('email', $build->author->email);
            $authorToken = $author->createAuthToken();

            Orchestra\importSQL(
                sprintf(
                    "
                    TRUNCATE TABLE `tbl_authors`;
                    INSERT INTO `tbl_authors`
                        (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `last_seen`, `user_type`, `primary`, `default_area`, `auth_token_active`, `language`)
                    VALUES
                        (NULL,'%s','%s','Administration','User','%s', NOW(),'developer','yes',NULL,'yes',NULL)
                    ;",
                    $author->get('username'),
                    $author->get('password'),
                    $author->get('email'),
                ),
                $config->database
            );
        } else {
            Orchestra\output('Skipping! --skip-create-author is set.', Orchestra\OUTPUT_INFO);

            try {
                $query = SymphonyPDO\Loader::instance()->query(
                    "SELECT `username`, `password` FROM `tbl_authors` WHERE `primary` = 'yes' ORDER BY `id` ASC LIMIT 1;"
                );
                if (false == $row = $query->fetchObject()) {
                    throw new Exception('Could not locate primary author. Are there any authors in the database?');
                }
            } catch (Exception $ex) {
                Orchestra\output('Unable to find author token from existing author data. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
            }

            $author = new \Author();
            $author->set('username', $row->username);
            $author->set('password', $row->password);
            $authorToken = $author->createAuthToken();
        }

        /************\
        | Write config
        \************/
        Orchestra\output('Writing config to disk...', Orchestra\OUTPUT_HEADING);
        file_put_contents(
            __WORKING_DIR__.'/var/manifest/config.json',
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        /************\
        | Create required symbolic links
        \************/
        Orchestra\output('Generating Symbolic links...', Orchestra\OUTPUT_HEADING);
        $cwd = getcwd();
        foreach ($build->{'symbolic-links'} as $ii => $link) {
            try {
                $link->name = $link->name ?? basename($link->src);
                $link->dest = ltrim($link->dest, '/');
                $path = sprintf('%s/%s', __WORKING_DIR__, $link->dest);

                chdir($path);

                Orchestra\output(sprintf(
                    '[%d/%d] %s/%s => %s ...',
                    $ii + 1,
                    count($build->{'symbolic-links'}),
                    $link->dest,
                    $link->name,
                    preg_replace('@^'.preg_quote(__WORKING_DIR__)."\/?@i", '', realpath($link->src))
                ), Orchestra\OUTPUT_INFO, null);

                if (false == file_exists($path)) {
                    throw new Exceptions\SymlinkTargetMissingException("The destination for the symlink, {$path}, does not exist.");
                }

                Files\create_symbolic_link($link->src, $link->name);
                Orchestra\output('done', Orchestra\OUTPUT_SUCCESS);
            } catch (Files\Exceptions\Symlink\DestinationExistsException $ex) {
                Orchestra\output('exists', Orchestra\OUTPUT_NOTICE);
            } catch (Files\Exceptions\Symlink\TargetMissingException | Files\Exceptions\Symlink\CreationFailedException $ex) {
                Orchestra\output('Failed!', Orchestra\OUTPUT_WARNING);
                Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_WARNING);
            }
        }
        chdir($cwd);

        /************\
        | Enable extensions
        \************/
        Orchestra\output('Enabling extensions...', Orchestra\OUTPUT_HEADING);
        if (true !== $argv->find('skip-enable-extensions')) {
            if (count($build->extensions) > 0) {
                $progress = (new ProgressBar\ProgressBar(count($build->extensions)))
                    ->length(30)
                    ->foreground(Colour::FG_GREEN)
                    ->background(Colour::BG_DEFAULT)
                    ->format('{{PROGRESS_BAR}} {{COMPLETED}}/{{TOTAL}}')
                ;

                foreach ($build->extensions as $e) {
                    $progress->advance();

                    $e->install = $e->install ?? true;

                    if (false == $e->install) {
                        continue;
                    }

                    $command = sprintf(
                        '
SYMPHONY_DOCROOT=%s/lib/symphony \
symphony_enable_preboot=1 \
symphony_preboot_config=%1$s/var/manifest/preboot.json \
%1$s/lib/symphony/extensions/console/bin/symphony \
orchestra extension install %s --token=%s',
                        __WORKING_DIR__,
                        preg_replace('@^extensions/@i', '', $e->name ?? Orchestra\getDirnameFromGitRepositoryUrl($e->repository->url)),
                        $authorToken
                    );

                    if (3 == $argv->find('v')) {
                        Orchestra\output('[DEBUG] Running Command: '.PHP_EOL.$command.PHP_EOL, Orchestra\OUTPUT_NOTICE);
                    }

                    Orchestra\runCommand($command);
                }

                echo PHP_EOL;
            } else {
                Orchestra\output('Skipping! Build has no extensions defined.', Orchestra\OUTPUT_NOTICE);
            }
        } else {
            Orchestra\output('Skipping! --skip-enable-extensions is set.', Orchestra\OUTPUT_NOTICE);
        }

        /************\
        | Build sections by using sections.json
        \************/
        Orchestra\output('Importing sections...', Orchestra\OUTPUT_HEADING);
        if (true == file_exists(__WORKING_DIR__.'/.orchestra/sections.json')) {
            $command = sprintf(
                "
%s/lib/section-builder/bin/import \
-j %1\$s/.orchestra/sections.json \
--symphony=%1\$s/lib/symphony \
--manifest=%1\$s/var/manifest",
                __WORKING_DIR__
            );

            if (3 == $argv->find('v')) {
                Orchestra\output('[DEBUG] Running Command: '.PHP_EOL.$command.PHP_EOL, Orchestra\OUTPUT_NOTICE);
            }

            Orchestra\runCommand($command);
        } else {
            Orchestra\output('Skipping! .orchestra/sections.json not found.', Orchestra\OUTPUT_NOTICE);
        }

        /************\
        | Run seeders
        \************/
        Orchestra\output('Running seeders...', Orchestra\OUTPUT_HEADING);
        if (true !== $argv->find('skip-seeders')) {
            try {
                $command = sprintf(
                    '
SYMPHONY_DOCROOT=%s/lib/symphony \
symphony_enable_preboot=1 \
symphony_preboot_config=%1$s/var/manifest/preboot.json \
%1$s/lib/symphony/extensions/console/bin/symphony \
--token=%s orchestra seed',
                    __WORKING_DIR__,
                    $authorToken,
                );

                if (3 == $argv->find('v')) {
                    Orchestra\output('[DEBUG] Running Command: '.PHP_EOL.$command.PHP_EOL, Orchestra\OUTPUT_NOTICE);
                }

                Orchestra\runCommand($command);
            } catch (Exceptions\CommandFailedToRunException $ex) {
                Orchestra\output('Failed to run seeders. Returned: '.$ex->getError(), Orchestra\OUTPUT_ERROR);
            } catch (\Exception $ex) {
                Orchestra\output('Failed to run seeders. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
            }
        } else {
            Orchestra\output('Skipping! --skip-seeders is set.', Orchestra\OUTPUT_NOTICE);
        }

        /************\
        | Run Migrations
        \************/
        Orchestra\output('Running Migrations...', Orchestra\OUTPUT_HEADING);
        if (true == $argv->find('run-migrations')) {
            try {
                $command = sprintf(
                    '
SYMPHONY_DOCROOT=%s/lib/symphony \
symphony_enable_preboot=1 \
symphony_preboot_config=%1$s/var/manifest/preboot.json \
%1$s/lib/symphony/extensions/console/bin/symphony \
--token=%s orchestra seed',
                    __WORKING_DIR__,
                    $authorToken,
                );

                if (3 == $argv->find('v')) {
                    Orchestra\output('[DEBUG] Running Command: '.PHP_EOL.$command.PHP_EOL, Orchestra\OUTPUT_NOTICE);
                }

                Orchestra\runCommand($command);
            } catch (Exceptions\CommandFailedToRunException $ex) {
                Orchestra\output('Failed to run migrations. Returned: '.$ex->getError(), Orchestra\OUTPUT_ERROR);
            } catch (\Exception $ex) {
                Orchestra\output('Failed to run migrations. Returned: '.$ex->getMessage(), Orchestra\OUTPUT_ERROR);
            }
        } else {
            Orchestra\output('Skipping! --run-migrations is not set.', Orchestra\OUTPUT_NOTICE);
        }

        /************\
        | Run Post Build Script
        \************/
        Orchestra\output('Running Post Built Script...', Orchestra\OUTPUT_HEADING);
        if (true !== $argv->find('skip-postbuild') && true == file_exists(__WORKING_DIR__.'/.orchestra/postbuild.php')) {
            include __WORKING_DIR__.'/.orchestra/postbuild.php';
        } else {
            Orchestra\output('Skipping! --skip-postbuild is set.', Orchestra\OUTPUT_NOTICE);
        }

        /************\
        | Finish up
        \************/
        Orchestra\output('Build complete.'.PHP_EOL, Orchestra\OUTPUT_INFO);

        if (null !== $adminPasswordPlain) {
            Orchestra\output("Login to control panel with username {$build->author->username} and password {$adminPasswordPlain}".PHP_EOL, Orchestra\OUTPUT_INFO);
        } else {
            Orchestra\output('Login credentials have not changed.', Orchestra\OUTPUT_NOTICE);
        }

        $c = sprintf(
            '
    #!/usr/bin/env bash
    SYMPHONY_DOCROOT="%s/lib/symphony" symphony_enable_preboot=1 symphony_preboot_config="%1$s/var/manifest/preboot.json" %1$s/lib/symphony/extensions/console/bin/symphony orchestra -t %s extension "\$@"',
            __WORKING_DIR__,
            $authorToken
        );

        Orchestra\runCommand(sprintf('echo "%s" > %s/bin/extension', $c, __WORKING_DIR__));
        Orchestra\runCommand(sprintf('chmod +x %s/bin/extension', __WORKING_DIR__));

        Orchestra\output('Helper commands created in bin/:', Orchestra\OUTPUT_INFO);
        Orchestra\output('extension: enable, disable, install, and uninstall extensions'.PHP_EOL, Orchestra\OUTPUT_INFO);
    }
}
