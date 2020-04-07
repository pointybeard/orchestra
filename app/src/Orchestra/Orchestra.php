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

namespace Orchestra\Functions\Orchestra;

use Orchestra\Orchestra\Exceptions;
use pointybeard\Helpers\Cli\Colour\Colour;
use pointybeard\Helpers\Cli\Message\Message;
use pointybeard\Helpers\Cli\Prompt\Prompt;
use pointybeard\Helpers\Functions\Cli;
use pointybeard\Helpers\Functions\Files;
use pointybeard\Helpers\Functions\Flags;

const OUTPUT_HEADING = 1;
const OUTPUT_ERROR = 2;
const OUTPUT_WARNING = 3;
const OUTPUT_NOTICE = 4;
const OUTPUT_INFO = 5;
const OUTPUT_SUCCESS = 6;

const FLAGS_SKIP_COMPOSER = 0x001;
const FLAGS_SKIP_GIT_RESET = 0x002;
const FLAGS_GIT_SHALLOW = 0x004;
const FLAGS_PROMPT_ASSUME_YES = 0x008;
const FLAGS_PROMPT_ASSUME_NO = 0x010;
const FLAGS_PROMPT_ASSUME_SKIP = 0x100;
const FLAGS_YES = 0x020;
const FLAGS_NO = 0x040;
const FLAGS_SKIP = 0x080;

/*
 * Facilitates copying of directory contents that are held inside the PHAR
 * archive out to an external directory.
 *
 * @param string $source                   Path to internal directory
 * @param string $destinationDirectory     Path to the destination directory
 * @param string $destinationDirectoryName Name of the folder within
 *                                          $destinationDirectory that contents
 *                                          should be copied to. Optional.
 *                                          default is the name of the source
 *                                          directory
 */
if (!function_exists(__NAMESPACE__.'\copyInternalPharDirectoryToDestination')) {
    function copyInternalPharDirectoryToDestination(string $source, string $destinationDirectory, ?string $destinationDirectoryName = null): void
    {
        $home = dirname($source);
        $directory = basename($source);

        if (null == $destinationDirectoryName) {
            $destinationDirectoryName = $directory;
        }

        $destination = sprintf('%s/%s', rtrim($destinationDirectory, '/'), trim($destinationDirectoryName, '/'));

        // Make sure the root destination folder exists
        try {
            Files\realise_directory($destination, Files\FLAG_FORCE);
        } catch (Files\Exceptions\Directory\CreationFailedException $ex) {
            Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            $relative = preg_replace('@'.preg_quote($source)."\/@", '', $item->getPathname());
            $target = "{$destination}/{$relative}";

            // It's a directory
            if ($item->isDir()) {
                try {
                    Files\realise_directory($target, Files\FLAG_FORCE);
                } catch (Files\Exceptions\Directory\CreationFailedException $ex) {
                    Orchestra\output($ex->getMessage(), Orchestra\OUTPUT_ERROR);
                }

                // It's a file
            } else {
                copy($it->getPathname(), $target);
            }
        }
    }
}

/*
 * Uses proc_open to run a bash command.
 *
 * @param string $command the full bash command to run
 * @param string $stdout  optional reference to capture output from STDOUT
 * @param string $stderr  optional reference to capture output from STDERR
 *
 * @throws Exception
 */
if (!function_exists(__NAMESPACE__.'\__runCommand')) {
    function __runCommand(string $command, string &$stdout = null, string &$stderr = null): void
    {
        $pipes = null;
        $return = null;

        $proc = proc_open(
            $command,
            [
            ['pipe', 'r'], // STDIN
            ['pipe', 'w'], // STDOUT
            ['pipe', 'w'], // STDERR
        ],
            $pipes,
            getcwd(),
            null
        );

        if (true == is_resource($proc)) {
            $stdout = trim(stream_get_contents($pipes[1]));
            $stderr = trim(stream_get_contents($pipes[2]));

            $return = proc_close($proc);

            if (1 == $return) {
                // There was some kind of error. Throw an exception.
                throw new \Exception($stderr);
            }
        } else {
            throw new \Exception('proc_open() returned FALSE');
        }
    }
}

/*
 *  Abstract __runCommand() and use a custom exception class.
 *
 * @param string $command the full bash command to run
 * @param string $stdout  optional reference to capture output from STDOUT
 * @param string $stderr  optional reference to capture output from STDERR
 *
 * @throws Orchestra\Exceptions\CommandFailedToRunException
 */
if (!function_exists(__NAMESPACE__.'\runCommand')) {
    function runCommand(string $command, string &$stdout = null, string &$stderr = null): void
    {
        try {
            __runCommand($command, $stdout, $stderr);
        } catch (\Exception $ex) {
            throw new Exceptions\CommandFailedToRunException($command, $ex->getMessage());
        }
    }
}

if (!function_exists(__NAMESPACE__.'\which')) {
    function which(string $prog): ?string
    {
        try {
            __runCommand("which {$prog}", $output);
        } catch (\Exception $ex) {
            $output = null;
        }

        return $output;
    }
}

if (!function_exists(__NAMESPACE__.'\randomString')) {
    function randomString(int $length, ?string $limit = "@[^a-zA-Z0-9.,\@#$:;<>\"'+=&|?-]@")
    {
        $randomString = base64_encode(random_bytes($length * 2));
        $randomString = preg_replace($limit, '', $randomString);

        return substr($randomString, 0, $length);
    }
}

if (!function_exists(__NAMESPACE__.'\doesDatabaseTableExist')) {
    function doesDatabaseTableExist($table, \StdClass $credentials): bool
    {
        $table = trim($table);
        $result = null;

        try {
            runCommand(sprintf(
                'mysql \
                --user="%s" \
                --password="%s" \
                --host="%s" \
                --port="%s" \
                --database="%s" \
                -e "show tables;" | grep -cim1 "%s"',
                $credentials->user,
                $credentials->password,
                $credentials->host,
                $credentials->port,
                $credentials->db,
                $table
            ), $output, $error);
        } catch (Exceptions\CommandFailedToRunException $ex) {
            // The grep command will return a status code of 1 when where is no match.
            // The problem with this is that a status code > 0 means there was an error.
            // So, instead, we'll use the flags "-cim1" to have grep output 0 when there
            // is no match and 1 when there is a match. This way we can compare the exit
            // code against the contents of $output to determine if there was actally an
            // error. i.e. if length of $output is greater than 1 and $error is empty,
            // then we should assume this is not and error but rather "no match found".
            if (false == (strlen(trim($output)) > 0 && strlen(trim($error)) <= 0)) {
                throw $ex;
            }
        }

        return (int) $output > 0;
    }
}

if (!function_exists(__NAMESPACE__.'\dropAllTablesFromDatabase')) {
    function dropAllTablesFromDatabase(\StdClass $credentials): void
    {
        runCommand(sprintf(
            '
        mysqldump \
            -B %s \
            --user="%s" \
            --password="%s" \
            --host="%s" \
            --port="%s" \
            --add-drop-table \
            --no-data | \
        grep -e \'^DROP \| FOREIGN_KEY_CHECKS\|USE\' | \
        mysql --user="%2$s" --password="%3$s" --host="%4$s" --port="%5$s"
        ',
            $credentials->db,
            $credentials->user,
            $credentials->password,
            $credentials->host,
            $credentials->port
        ));
    }
}

if (!function_exists(__NAMESPACE__.'\importSqlFromFile')) {
    function importSqlFromFile(string $path, \StdClass $credentials): void
    {
        runCommand(sprintf(
            '
        mysql \
        --user="%s" \
        --password="%s" \
        --host="%s" \
        --port="%s" \
        --database="%s" < %s',
            $credentials->user,
            $credentials->password,
            $credentials->host,
            $credentials->port,
            $credentials->db,
            $path
        ));
    }
}

if (!function_exists(__NAMESPACE__.'\importSQL')) {
    function importSQL(string $sql, \StdClass $credentials)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'Orchestra_');

        file_put_contents($tmpFile, $sql);

        importSqlFromFile($tmpFile, $credentials);

        // Silently delete the tmp file. It's okay if it fails, no need to make a deal out of it.
        @unlink($tmpFile);

        return true;
    }
}

if (!function_exists(__NAMESPACE__.'\isComposable')) {
    function isComposable(string $path): bool
    {
        if (false == $path) {
            throw new Exceptions\DirectoryDoesNotExistException($path);
        }

        return file_exists("{$path}/composer.json");
    }
}

if (!function_exists(__NAMESPACE__.'\composerRunOnDirectory')) {
    function composerRunOnDirectory(string $path, bool $includeDev = false, ?string $flags = '-v --no-ansi --no-interaction --no-progress --no-scripts --optimize-autoloader --prefer-dist --no-cache'): void
    {
        $path = rtrim($path, '/');

        if (false == isComposable($path)) {
            throw new Exceptions\DirectoryNotComposableException($path);
        }

        runCommand(sprintf(
            '%s update %s --working-dir="%s" %s',
            which('composer'),
            false == $includeDev
            ? '--no-dev'
            : '--dev',
            $path,
            $flags
        ));
    }
}

if (!function_exists(__NAMESPACE__.'\gitResetPackage')) {
    function gitResetPackage(string $path, string $branch): void
    {
        $path = rtrim($path, '/');

        if (false == is_dir($path)) {
            throw new Exceptions\DirectoryDoesNotExistException($path);
        } elseif (false == file_exists("{$path}/.git/config")) {
            throw new Exceptions\DirectoryNotGitRepositoryException($path);
        }

        runCommand(sprintf(
            '
            %s --git-dir="%s/.git/config" reset --hard %s && \
            %1$s --git-dir="%2$s/.git/config" pull origin %3$s',
            which('git'),
            $path,
            $branch
        ));
    }
}

if (!function_exists(__NAMESPACE__.'\gitCloneRepository')) {
    function gitCloneRepository(string $url, string $destination, string $branch = 'master', string $flags = null): void
    {
        if (true == is_dir($destination)) {
            if (true == is_dir("{$destination}/.git")) {
                throw new Exceptions\PackageExistsException($url, $destination);
            } else {
                throw new Exceptions\CannotClonePackageException($url, $destination);
            }
        }

        // git clone -b <branch> --single-branch <url> --depth <number of commits>
        runCommand(sprintf(
            '%s clone %s -b %s %s %s',
            which('git'),
            $flags,
            $branch,
            $url,
            $destination
        ));
    }
}

if (!function_exists(__NAMESPACE__.'\cleanup')) {
    function cleanup(string $path): void
    {
        if (false == file_exists($path)) {
            return;
        }
        runCommand("rm --recursive --force {$path}");
    }
}

if (!function_exists(__NAMESPACE__.'\getDirnameFromGitRepositoryUrl')) {
    function getDirnameFromGitRepositoryUrl(string $url): string
    {
        if (false == preg_match("/\/([^\/]+)\.git$/i", $url, $match)) {
            throw new Exception("URL {$url} does not appear to be a valid git repository");
        }

        return $match[1];
    }
}

if (!function_exists(__NAMESPACE__.'\output')) {
    function output(string $message, ?int $type = OUTPUT_INFO, ?int $flags = Message::FLAG_APPEND_NEWLINE): void
    {
        $output = (new Message())
        ->message($message)
        ->flags($flags)
        ->foreground(Colour::FG_DEFAULT)
        ->background(Colour::BG_DEFAULT)
    ;

        switch ($type) {
        case OUTPUT_ERROR:
            Cli\display_error_and_exit($message, 'CRITICAL ERROR!');
            break;

        case OUTPUT_WARNING:
            $output
                ->message("WARNING! {$message}")
                ->foreground(Colour::FG_RED)
            ;
            break;

        case OUTPUT_NOTICE:
            $output->foreground(Colour::FG_YELLOW);
            break;

        case OUTPUT_SUCCESS:
            $output->foreground(Colour::FG_GREEN);
            break;

        case OUTPUT_HEADING:
            $output
                ->foreground(Colour::FG_WHITE)
                ->background(Colour::BG_BLUE)
            ;
            break;

        default:
        case OUTPUT_INFO:
            break;
    }

        $output->display();
    }
}

if (!function_exists(__NAMESPACE__.'\installExtension')) {
    function installExtension(\StdClass $package, int $flags = null): void
    {
        if (true == isset($package->repository)) {
            $package->name = $package->name ?? Orchestra\getDirnameFromGitRepositoryUrl($package->repository->url);
            $package->repository->target = $package->repository->target ?? getDirnameFromGitRepositoryUrl($package->repository->url);
            $package->repository->target = 'extensions/'.ltrim($package->repository->target, '/');
        }

        installLibrary($package, $flags);
    }
}

if (!function_exists(__NAMESPACE__.'\installLibrary')) {
    function installLibrary(\StdClass $package, int $flags = null): void
    {
        if (true == isset($package->repository)) {
            $package->repository->flags = null;
            $package->repository->target = $package->repository->target ?? getDirnameFromGitRepositoryUrl($package->repository->url);

            if (Flags\is_flag_set($flags, FLAGS_GIT_SHALLOW)) {
                $package->repository->flags = '--single-branch --depth=1';
            }

            try {
                $package->repository->branch = $package->repository->branch ?? 'master';

                gitCloneRepository(
                    $package->repository->url,
                    __WORKING_DIR__.'/lib/'.ltrim($package->repository->target, '/'),
                    $package->repository->branch,
                    $package->repository->flags
                );
                output('done', OUTPUT_SUCCESS);
            } catch (Exceptions\PackageExistsException $ex) {
                output('exists.', OUTPUT_NOTICE);
                if (false == Flags\is_flag_set($flags, FLAGS_SKIP_GIT_RESET)) {
                    output("Resetting and updating to {$package->repository->branch}", OUTPUT_INFO);
                    gitResetPackage(__WORKING_DIR__.'/lib/'.ltrim($package->repository->target, '/'), $package->repository->branch);
                }
            }
        } else {
            output('not a git repo', OUTPUT_NOTICE);
        }

        $target = __WORKING_DIR__.'/lib/'.ltrim($package->repository->target ?? $package->name, '/');

        if (false == Flags\is_flag_set($flags, FLAGS_SKIP_COMPOSER) && true == isComposable($target)) {
            try {
                output('Updating composer packages ... ', OUTPUT_NOTICE);
                composerRunOnDirectory($target);
            } catch (Exceptions\DirectoryNotComposableException $ex) {
                // Not composable. That's okay, keep going.
            } finally {
                output('done', OUTPUT_SUCCESS);
            }
        }

        if (false != is_array($package->cleanup)) {
            foreach ($package->cleanup as $path) {
                output("Cleanup: removing {$path}", OUTPUT_NOTICE);
                $path = "{$target}/{$path}";
                cleanup($path);
            }
        }
    }
}

if (!function_exists(__NAMESPACE__.'\ask_to_proceed')) {
    function ask_to_proceed(?int $flags = null, string $prompt = 'Continue with installation %s?', string $affirmative = '@^y(es)?$@i', string $negative = '@^no?$@i', string $skip = '@^s(kip)?$@i'): int
    {
        if (true == Flags\is_flag_set($flags, FLAGS_PROMPT_ASSUME_YES)) {
            output('--assume-yes has been set. Continuing.', OUTPUT_NOTICE);

            return FLAGS_YES;
        } elseif (true == Flags\is_flag_set($flags, FLAGS_PROMPT_ASSUME_NO)) {
            output('--assume-no has been set. Exiting.', OUTPUT_NOTICE);
            exit(1);
        } elseif (true == Flags\is_flag_set($flags, FLAGS_PROMPT_ASSUME_SKIP)) {
            output('--assume-skip has been set. Skipping.', OUTPUT_NOTICE);

            return FLAGS_SKIP;
        }

        while (true) {
            $proceed = (new Prompt(sprintf($prompt, '(y=yes, n=no, s=skip)')))
                ->display()
            ;

            if (preg_match($negative, $proceed)) {
                output('Execution termined by user', OUTPUT_NOTICE);
                exit(1);
            } elseif (preg_match($skip, $proceed)) {
                return FLAGS_SKIP;
            } elseif (preg_match($affirmative, $proceed)) {
                return FLAGS_YES;
            }

            output('Please enter a valid response', OUTPUT_WARNING);
        }
    }
}
