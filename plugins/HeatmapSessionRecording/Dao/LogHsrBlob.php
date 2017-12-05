<?php
/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */
namespace Piwik\Plugins\HeatmapSessionRecording\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;

class LogHsrBlob
{
    private $table = 'log_hsr_blob';
    private $tablePrefixed = '';

    /**
     * @var Db|Db\AdapterInterface|\Piwik\Tracker\Db
     */
    private $db;

    public function __construct()
    {
        $this->tablePrefixed = Common::prefixTable($this->table);
        $this->db = Db::get();
    }

    public function install()
    {
        DbHelper::createTable($this->table, "
                  `idhsrblob` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `hash` INT(10) UNSIGNED NOT NULL,
                  `compressed` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                  `value` MEDIUMBLOB NULL DEFAULT NULL,
                  PRIMARY KEY (`idhsrblob`),
                  INDEX (`hash`)");

        // we always build the hash on the raw text for simplicity
    }

    public function uninstall()
    {
        Db::query(sprintf('DROP TABLE IF EXISTS `%s`', $this->tablePrefixed));
    }

    public function findEntry($textHash, $text, $textCompressed)
    {
        $sql = sprintf('SELECT idhsrblob FROM %s WHERE `hash` = ? and (`value` = ? or `value` = ?) LIMIT 1', $this->tablePrefixed);
        $id = $this->db->fetchOne($sql, array($textHash, $text, $textCompressed));

        return $id;
    }

    public function createEntry($textHash, $text, $isCompressed)
    {
        $sql = sprintf('INSERT INTO %s (`hash`, `compressed`, `value`) VALUES(?,?,?) ', $this->tablePrefixed);
        $this->db->query($sql, array($textHash, (int) $isCompressed, $text));

        return $this->db->lastInsertId();
    }

    public function record($text)
    {
        if ($text === null || $text === false) {
            return null;
        }

        $textHash = abs(crc32($text));
        $textCompressed = $this->compress($text);

        $id = $this->findEntry($textHash, $text, $textCompressed);

        if (!empty($id)) {
            return $id;
        }

        $isCompressed = 0;
        if ($text !== $textCompressed && strlen($textCompressed) < strlen($text)) {
            // detect if it is more efficient to store compressed or raw text
            $text = $textCompressed;
            $isCompressed = 1;
        }

        return $this->createEntry($textHash, $text, $isCompressed);
    }

    public function deleteUnusedBlobEntries()
    {
        $sql = sprintf('DELETE hsrblob
            FROM %s hsrblob
            LEFT JOIN %s hsrevent on hsrblob.idhsrblob = hsrevent.idhsrblob
            WHERE hsrevent.idloghsr is null', Common::prefixTable('log_hsr_blob'), Common::prefixTable('log_hsr_event'));

        Db::query($sql);
    }

    public function getAllRecords()
    {
        $blobs = $this->db->fetchAll('SELECT * FROM ' . $this->tablePrefixed);
        return $this->enrichRecords($blobs);
    }

    private function enrichRecords($blobs)
    {
        if (!empty($blobs)) {
            foreach ($blobs as $index => &$blob) {
                if (!empty($blob['compressed'])) {
                    $blob['value'] = $this->uncompress($blob['value']);
                }
            }
        }

        return $blobs;
    }

    private function compress($data)
    {
        if (!empty($data)) {
            return gzcompress($data);
        }

        return $data;
    }

    private function uncompress($data)
    {
        if (!empty($data)) {
            return gzuncompress($data);
        }

        return $data;
    }
}

