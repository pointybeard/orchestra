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

require_once FACE.'/interface.exportablefield.php';
require_once FACE.'/interface.importablefield.php';

require_once EXTENSIONS.'/orchestra/extension.driver.php';

use Ramsey\Uuid\Uuid;

class FieldAttachment extends Field implements ExportableField, ImportableField
{
    private $__destination_original = null;

    protected static $imageMimeTypes = [
        'image/gif',
        'image/jpg',
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->_name = __('Orchestra: Attachment');
        $this->_required = true;

        $this->set('location', 'sidebar');
        $this->set('required', 'no');
    }

    /*-------------------------------------------------------------------------
        Definition:
    -------------------------------------------------------------------------*/

    public function canFilter()
    {
        return true;
    }

    public function canPrePopulate()
    {
        return true;
    }

    public function isSortable()
    {
        return true;
    }

    /*-------------------------------------------------------------------------
        Setup:
    -------------------------------------------------------------------------*/

    public function createTable()
    {
        return Symphony::Database()->query(sprintf(
            'CREATE TABLE IF NOT EXISTS `tbl_entries_data_%d` (
                    `id` int(11) unsigned NOT NULL auto_increment,
                    `entry_id` int(11) unsigned NOT NULL,
                    `file` varchar(255) default NULL,
                    `size` int(11) unsigned NULL,
                    `mimetype` varchar(100) default NULL,
                    `meta` varchar(255) default NULL,
                PRIMARY KEY  (`id`),
                UNIQUE KEY `entry_id` (`entry_id`),
                KEY `file` (`file`),
                KEY `mimetype` (`mimetype`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',
            $this->get('id')
        ));
    }

    /*-------------------------------------------------------------------------
        Utilities:
    -------------------------------------------------------------------------*/

    public function entryDataCleanup($entry_id, $data = null)
    {
        $file_location = $this->getFilePath($data['file']);

        if (is_file($file_location)) {
            General::deleteFile($file_location);
        }

        parent::entryDataCleanup($entry_id);

        return true;
    }

    public static function getMetaInfo($file, $type)
    {
        $meta = [];

        if (!is_readable($file)) {
            return $meta;
        }

        $meta['creation'] = DateTimeObj::get('c', filemtime($file));

        if (General::in_iarray($type, self::$imageMimeTypes) && $array = @getimagesize($file)) {
            $meta['width'] = $array[0];
            $meta['height'] = $array[1];
        }

        return $meta;
    }

    public function getFilePath($filename)
    {
        return $this->get('destination').'/'.$filename;
    }

    /*-------------------------------------------------------------------------
        Settings:
    -------------------------------------------------------------------------*/

    public function displaySettingsPanel(XMLElement &$wrapper, $errors = null)
    {
        parent::displaySettingsPanel($wrapper, $errors);

        $label = Widget::Label(__('Destination Directory'));
        $help = new XMLElement('i', __('absolute path to folder. Must be writable.'));
        $input = Widget::Input('fields['.$this->get('sortorder').'][destination]', $this->get('destination') ? $this->get('destination') : WORKSPACE, 'input');
        $label->appendChild($help);
        $label->appendChild($input);

        if (isset($errors['destination'])) {
            $wrapper->appendChild(Widget::Error($label, $errors['destination']));
        } else {
            $wrapper->appendChild($label);
        }

        // Validation rule
        $this->buildValidationSelect($wrapper, $this->get('validator'), 'fields['.$this->get('sortorder').'][validator]', 'upload', $errors);

        //########## PREPEND DATE STAMP CHECKBOX ###########
        $label = Widget::Label();
        $input = Widget::Input('fields['.$this->get('sortorder').'][prepend_datestamp]', 'yes', 'checkbox');
        if ('yes' == $this->get('prepend_datestamp')) {
            $input->setAttribute('checked', 'checked');
        }
        $label->setValue($input->generate().' '.__('Prepend date stamp to file name'));
        $wrapper->appendChild($label);
        //################################

        // Requirements and table display
        $this->appendStatusFooter($wrapper);
    }

    public function checkFields(array &$errors, $checkForDuplicates = true)
    {
        if (false === is_dir($this->get('destination'))) {
            $errors['destination'] = __('The destination directory, %s, does not exist.', [
                '<code>'.$this->get('destination').'</code>',
            ]);
        } elseif (false === is_writable($this->get('destination').'/')) {
            $errors['destination'] = __('The destination directory is not writable.')
                .' '
                .__('Please check permissions on %s.', [
                    '<code>'.$this->get('destination').'</code>',
                ]);
        }

        parent::checkFields($errors, $checkForDuplicates);
    }

    public function commit()
    {
        if (!parent::commit()) {
            return false;
        }

        $id = $this->get('id');

        if (false === $id) {
            return false;
        }

        return FieldManager::saveSettings($this->get('id'), [
            'destination' => rtrim($this->get('destination'), '/'),
            'validator' => 'custom' == $fields['validator']
                ? null
                : $this->get('validator'),
            'prepend_datestamp' => 'yes' == $this->get('prepend_datestamp')
                ? 'yes'
                : 'no',
        ]);
    }

    /*-------------------------------------------------------------------------
        Publish:
    -------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        if (false === is_dir($this->get('destination').'/')) {
            $flagWithError = __('The destination directory, %s, does not exist.', [
                '<code>'.$this->get('destination').'</code>',
            ]);
        } elseif ($flagWithError && false === is_writable($this->get('destination').'/')) {
            $flagWithError = __('Destination folder is not writable.')
                .' '
                .__('Please check permissions on %s.', [
                    '<code>'.$this->get('destination').'</code>',
                ]);
        }

        $label = Widget::Label($this->get('label'));
        $label->setAttribute('class', 'file');
        if ('yes' != $this->get('required')) {
            $label->appendChild(new XMLElement('i', __('Optional')));
        }

        $span = new XMLElement('span', null, ['class' => 'frame']);

        if (isset($data['file'])) {
            $filename = preg_replace("@^\/+@", '/', $this->get('destination').'/'.basename($data['file']));
            $file = $this->get('destination').'/'.$data['file'];
            if (!is_readable($file)) {
                $flagWithError = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
            }

            $span->appendChild(new XMLElement('span', Widget::Anchor(preg_replace('![^a-z0-9]+!i', '$0&#8203;', $filename), URL.$filename)));
        } else {
            $filename = null;
        }

        $span->appendChild(Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix, $filename, ($filename ? 'hidden' : 'file')));

        $label->appendChild($span);

        if (null != $flagWithError) {
            $wrapper->appendChild(Widget::Error($label, $flagWithError));
        } else {
            $wrapper->appendChild($label);
        }
    }

    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        /*
         * For information about PHPs upload error constants see:
         * @link http://php.net/manual/en/features.file-upload.errors.php
         */
        $message = null;

        if (
            empty($data)
            || (
                is_array($data)
                && isset($data['error'])
                && UPLOAD_ERR_NO_FILE == $data['error']
            )
        ) {
            if ('yes' == $this->get('required')) {
                $message = __('‘%s’ is a required field.', [$this->get('label')]);

                return self::__MISSING_FIELDS__;
            }

            return self::__OK__;
        }

        // Its not an array, so just retain the current data and return
        if (false === is_array($data)) {
            $file = $this->getFilePath(basename($data));
            if (!is_readable($file)) {
                $message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');

                return self::__INVALID_FIELDS__;
            }

            // Ensure that the file still matches the validator and hasn't
            // changed since it was uploaded.
            if (null != $this->get('validator')) {
                $rule = $this->get('validator');

                if (false === General::validateString($file, $rule)) {
                    $message = __('File chosen in ‘%s’ does not match allowable file types for that field.', [
                        $this->get('label'),
                    ]);

                    return self::__INVALID_FIELDS__;
                }
            }

            return self::__OK__;
        }

        if (false === is_dir($this->get('destination').'/')) {
            $message = __('The destination directory, %s, does not exist.', [
                '<code>'.$this->get('destination').'</code>',
            ]);

            return self::__ERROR__;
        } elseif (false === is_writable($this->get('destination').'/')) {
            $message = __('Destination folder is not writable.')
                .' '
                .__('Please check permissions on %s.', [
                    '<code>'.$this->get('destination').'</code>',
                ]);

            return self::__ERROR__;
        }

        if (UPLOAD_ERR_NO_FILE != $data['error'] && UPLOAD_ERR_OK != $data['error']) {
            switch ($data['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s specified by your host.', [$this->get('label'), (is_numeric(ini_get('upload_max_filesize')) ? General::formatFilesize(ini_get('upload_max_filesize')) : ini_get('upload_max_filesize'))]);
                    break;

                case UPLOAD_ERR_FORM_SIZE:
                    $message = __('File chosen in ‘%1$s’ exceeds the maximum allowed upload size of %2$s, specified by Symphony.', [$this->get('label'), General::formatFilesize($_POST['MAX_FILE_SIZE'])]);
                    break;

                case UPLOAD_ERR_PARTIAL:
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = __('File chosen in ‘%s’ was only partially uploaded due to an error.', [$this->get('label')]);
                    break;

                case UPLOAD_ERR_CANT_WRITE:
                    $message = __('Uploading ‘%s’ failed. Could not write temporary file to disk.', [$this->get('label')]);
                    break;

                case UPLOAD_ERR_EXTENSION:
                    $message = __('Uploading ‘%s’ failed. File upload stopped by extension.', [$this->get('label')]);
                    break;
            }

            return self::__ERROR_CUSTOM__;
        }

        // Create a UUID for this asset
        $uuid = Uuid::uuid1()->toString();

        // Generate a new filename
        $data['name'] = self::createDesintationFilenameFromOriginalFilename(
            $data['name']
        );

        if (null != $this->get('validator')) {
            $rule = $this->get('validator');

            if (!General::validateString($data['name'], $rule)) {
                $message = __('File chosen in ‘%s’ does not match allowable file types for that field.', [$this->get('label')]);

                return self::__INVALID_FIELDS__;
            }
        }

        return self::__OK__;
    }

    public function createDesintationFilenameFromOriginalFilename($filename)
    {
        // Create a UUID for this asset
        //$uuid = Uuid::uuid1()->toString();

        $filename = Lang::createFilename($filename);

        if ('yes' == $this->get('prepend_datestamp')) {
            $filename = sprintf(
                '%s_%s',
                date('YmdHis'),
                $filename
            );
        }

        // Generate a new filename
        return $filename;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = self::__OK__;

        // No file given, save empty data:
        if (null === $data) {
            return [
                'file' => null,
                'mimetype' => null,
                'size' => null,
                'meta' => null,
            ];
        }

        // Its not an array, so just retain the current data and return:
        if (false === is_array($data)) {
            $file = $this->getFilePath(basename($data));

            $result = [
                'file' => $data,
                'mimetype' => null,
                'size' => null,
                'meta' => null,
            ];

            // Grab the existing entry data to preserve the MIME type and size information
            if (isset($entry_id)) {
                $row = Symphony::Database()->fetchRow(0, sprintf(
                    'SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d',
                    $this->get('id'),
                    $entry_id
                ));

                if (false === empty($row)) {
                    $result = $row;
                }
            }

            // Found the file, add any missing meta information:
            if (is_readable($file)) {
                if (empty($result['mimetype'])) {
                    $result['mimetype'] = General::getMimeType($file);
                }

                if (empty($result['size'])) {
                    $result['size'] = filesize($file);
                }

                if (empty($result['meta'])) {
                    $result['meta'] = serialize(self::getMetaInfo($file, $result['mimetype']));
                }
            }

            // The file was not found, or is unreadable:
            else {
                $message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
                $status = self::__INVALID_FIELDS__;
            }

            return $result;
        }

        if ($simulate && null === $entry_id) {
            return $data;
        }

        // Check to see if the entry already has a file associated with it:
        if (false === (null === $entry_id)) {
            $row = Symphony::Database()->fetchRow(0, sprintf(
                'SELECT * FROM `tbl_entries_data_%s` WHERE `entry_id` = %d LIMIT 1',
                $this->get('id'),
                $entry_id
            ));

            $existing_file = isset($row['file'])
                ? $this->getFilePath($row['file'])
                : null;

            // File was removed:
            if (
                UPLOAD_ERR_NO_FILE == $data['error']
                && null !== $existing_file
                && is_file($existing_file)
            ) {
                General::deleteFile($existing_file);
            }
        }

        // Do not continue on upload error:
        if (UPLOAD_ERR_NO_FILE == $data['error'] || UPLOAD_ERR_OK != $data['error']) {
            return false;
        }

        // Where to upload the new file?
        $abs_path = '/'.trim($this->get('destination'), '/');
        //$rel_path = str_replace('/workspace', '', $this->get('destination'));

        // Generate a new filename
        $data['name'] = self::createDesintationFilenameFromOriginalFilename(
            $data['name']
        );

        // If a file already exists, then rename the file being uploaded by
        // adding `_1` to the filename. If `_1` already exists, the logic
        // will keep adding 1 until a filename is available (#672)
        if (file_exists($abs_path.'/'.$data['name'])) {
            $extension = General::getExtension($data['name']);
            $new_file = substr($abs_path.'/'.$data['name'], 0, -1 - strlen($extension));
            $renamed_file = $new_file;
            $count = 1;

            do {
                $renamed_file = $new_file.'_'.$count.'.'.$extension;
                ++$count;
            } while (file_exists($renamed_file));

            // Extract the name filename from `$renamed_file`.
            $data['name'] = str_replace($abs_path.'/', '', $renamed_file);
        }

        $file = $this->getFilePath($data['name']);

        // Attempt to upload the file:
        $uploaded = General::uploadFile(
            $abs_path,
            $data['name'],
            $data['tmp_name'],
            Symphony::Configuration()->get('write_mode', 'file')
        );

        if (false === $uploaded) {
            $message = __(
                'There was an error while trying to upload the file %1$s to the target directory %2$s.',
                [
                    '<code>'.$data['name'].'</code>',
                    '<code>'.$abs_path.'</code>',
                ]
            );
            $status = self::__ERROR_CUSTOM__;

            return false;
        }

        // File has been replaced:
        if (
            isset($existing_file)
            && $existing_file !== $file
            && is_file($existing_file)
        ) {
            General::deleteFile($existing_file);
        }

        // Get the mimetype, don't trust the browser. RE: #1609
        $data['type'] = General::getMimeType($file);

        return [
            'file' => basename($file),
            'size' => $data['size'],
            'mimetype' => $data['type'],
            'meta' => serialize(self::getMetaInfo($file, $data['type'])),
        ];
    }

    /*-------------------------------------------------------------------------
        Output:
    -------------------------------------------------------------------------*/

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        // It is possible an array of NULL data will be passed in. Check for this.
        if (!is_array($data) || !isset($data['file']) || null === $data['file']) {
            return;
        }

        $file = $this->getFilePath($data['file']);
        $filesize = (is_readable($file)) ? filesize($file) : null;
        $item = new XMLElement($this->get('element_name'));
        $item->setAttributeArray([
            'size' => null !== $filesize ? General::formatFilesize($filesize) : 'unknown',
            'bytes' => null !== $filesize ? $filesize : 'unknown',
            'path' => General::sanitize(
                str_replace(WORKSPACE, null, dirname($file))
            ),
            'type' => $data['mimetype'],
            'assets-destination-folder' => $this->get('destination'),
        ]);

        $item->appendChild(new XMLElement('filename', General::sanitize(basename($file))));

        $m = unserialize($data['meta']);

        if (is_array($m) && !empty($m)) {
            $item->appendChild(new XMLElement('meta', null, $m));
        }

        $wrapper->appendChild($item);
    }

    public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
    {
        if (false === isset($data['file']) || !$file = $data['file']) {
            if ($link) {
                return parent::prepareTableValue(null, $link, $entry_id);
            } else {
                return parent::prepareTableValue(null, $link, $entry_id);
            }
        }

        if ($link) {
            $link->setValue(basename($file));
            $link->setAttribute('data-path', $this->get('destination'));

            return $link->generate();
        } else {
            $link = Widget::Anchor(basename($file), URL.$this->get('destination').'/'.$file);
            $link->setAttribute('data-path', $this->get('destination'));

            return $link->generate();
        }
    }

    public function prepareAssociationsDrawerXMLElement(Entry $e, array $parent_association, $prepopulate = '')
    {
        $li = parent::prepareAssociationsDrawerXMLElement($e, $parent_association, $prepopulate);
        $a = $li->getChild(0);
        $a->setAttribute('data-path', $this->get('destination'));

        return $li;
    }

    /*-------------------------------------------------------------------------
        Import:
    -------------------------------------------------------------------------*/

    public function getImportModes()
    {
        return [
            'getValue' => ImportableField::STRING_VALUE,
            'getPostdata' => ImportableField::ARRAY_VALUE,
        ];
    }

    public function prepareImportValue($data, $mode, $entry_id = null)
    {
        $message = $status = null;
        $modes = (object) $this->getImportModes();

        if ($mode === $modes->getValue) {
            return $data;
        } elseif ($mode === $modes->getPostdata) {
            return $this->processRawFieldData($data, $status, $message, true, $entry_id);
        }

        return;
    }

    /*-------------------------------------------------------------------------
        Export:
    -------------------------------------------------------------------------*/

    /**
     * Return a list of supported export modes for use with `prepareExportValue`.
     *
     * @return array
     */
    public function getExportModes()
    {
        return [
            'getFilename' => ExportableField::VALUE,
            'getObject' => ExportableField::OBJECT,
            'getPostdata' => ExportableField::POSTDATA,
        ];
    }

    /**
     * Give the field some data and ask it to return a value using one of many
     * possible modes.
     *
     * @param mixed $data
     * @param int   $mode
     * @param int   $entry_id
     *
     * @return array|string|null
     */
    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object) $this->getExportModes();

        $filepath = $this->getFilePath($data['file']);

        // No file, or the file that the entry is meant to have no
        // longer exists.
        if (!isset($data['file']) || !is_file($filepath)) {
            return;
        }

        if ($mode === $modes->getFilename) {
            return $data['file'];
        }

        if ($mode === $modes->getObject) {
            $object = (object) $data;

            if (isset($object->meta)) {
                $object->meta = unserialize($object->meta);
            }

            return $object;
        }

        if ($mode === $modes->getPostdata) {
            return $data['file'];
        }
    }

    /*-------------------------------------------------------------------------
        Filtering:
    -------------------------------------------------------------------------*/

    public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false)
    {
        $field_id = $this->get('id');

        if (preg_match('/^mimetype:/', $data[0])) {
            $data[0] = str_replace('mimetype:', '', $data[0]);
            $column = 'mimetype';
        } elseif (preg_match('/^size:/', $data[0])) {
            $data[0] = str_replace('size:', '', $data[0]);
            $column = 'size';
        } else {
            $column = 'file';
        }

        if (self::isFilterRegex($data[0])) {
            $this->buildRegexSQL($data[0], [$column], $joins, $where);
        } elseif ($andOperation) {
            foreach ($data as $value) {
                ++$this->_key;
                $value = $this->cleanValue($value);
                $joins .= "
                    LEFT JOIN
                        `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                        ON (e.id = t{$field_id}_{$this->_key}.entry_id)
                ";
                $where .= "
                    AND t{$field_id}_{$this->_key}.{$column} = '{$value}'
                ";
            }
        } else {
            if (!is_array($data)) {
                $data = [$data];
            }

            foreach ($data as &$value) {
                $value = $this->cleanValue($value);
            }

            ++$this->_key;
            $data = implode("', '", $data);
            $joins .= "
                LEFT JOIN
                    `tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
                    ON (e.id = t{$field_id}_{$this->_key}.entry_id)
            ";
            $where .= "
                AND t{$field_id}_{$this->_key}.{$column} IN ('{$data}')
            ";
        }

        return true;
    }

    /*-------------------------------------------------------------------------
        Sorting:
    -------------------------------------------------------------------------*/

    public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC')
    {
        if (in_array(strtolower($order), ['random', 'rand'])) {
            $sort = 'ORDER BY RAND()';
        } else {
            $sort = sprintf(
                'ORDER BY (
                    SELECT %s
                    FROM tbl_entries_data_%d AS `ed`
                    WHERE entry_id = e.id
                ) %s',
                '`ed`.file',
                $this->get('id'),
                $order
            );
        }
    }

    /*-------------------------------------------------------------------------
        Events:
    -------------------------------------------------------------------------*/

    public function getExampleFormMarkup()
    {
        $label = Widget::Label($this->get('label'));
        $label->appendChild(Widget::Input('fields['.$this->get('element_name').']', null, 'file'));

        return $label;
    }
}
