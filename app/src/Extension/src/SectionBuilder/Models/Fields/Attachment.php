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

namespace pointybeard\Symphony\SectionBuilder\Models\Fields;

use pointybeard\Symphony\SectionBuilder\AbstractField;
use pointybeard\Symphony\SectionBuilder\Interfaces\FieldInterface;

class Attachment extends AbstractField implements FieldInterface
{
    const TYPE = 'attachment';
    const TABLE = 'tbl_fields_attachment';

    public static function getFieldMappings(): \stdClass
    {
        return (object) array_merge((array) parent::getFieldMappings(), [
            'destination' => [
                'name' => 'destination',
                'flags' => self::FLAG_STR,
            ],

            'validator' => [
                'name' => 'validator',
                'flags' => self::FLAG_STR,
            ],
        ]);
    }

    protected static function boolToEnumYesNo(bool $value): string
    {
        return true == $value ? 'yes' : 'no';
    }

    public function getDatabaseReadyData(): array
    {
        return [
            'field_id' => (int) $this->id->value,
            'destination' => (string) $this->destination,
            'validator' => (string) $this->validator,
        ];
    }

    public function getEntriesDataCreateTableSyntax(): string
    {
        return sprintf(
            'CREATE TABLE `tbl_entries_data_%d` (
                    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                    `entry_id` int(11) unsigned NOT NULL,
                    `file` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `size` int(11) unsigned DEFAULT NULL,
                    `mimetype` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
                    `meta` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `entry_id` (`entry_id`),
                KEY `file` (`file`),
                KEY `mimetype` (`mimetype`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',
            (int) $this->id->value
        );
    }
}
