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

namespace pointybeard\Orchestra\Orchestra;

class FileByExtensionIterator
{
    public static function fetch(string $path, string $extension): \Iterator
    {
        $it = new \RegexIterator(
            new \IteratorIterator(
                new \DirectoryIterator($path)
            ),
            sprintf('/^.+\.%s/i', preg_quote($extension)),
            \RegexIterator::GET_MATCH
        );

        $result = new \ArrayIterator();
        foreach ($it as $file) {
            $result->append("{$path}/{$file[0]}");
        }

        return $result;
    }
}
