<?php

namespace rkphplib;

require_once(__DIR__.'/Database.class.php');

use rkphplib\Exception;


/**
 * Synchronize entries from remote to local database.
 *
 * @author Roland Kujundzic <roland@kujundzic.de>
 */
class DatabaseSync {

/** @var ADatabase $remote_db */
protected $remote_db;

/** @var ADatabase $local_db */
protected $local_db;

/** @var map $fkey_map foreign key map */
protected $fkey_map;


/**
 * Set remote and local database connect string.
 *
 * @param string $remote
 * @param string $local
 */
public function setLocalRemoteDSN($remote, $local) {
    $this->remote_db = Database::getInstance($remote);
    $this->local_db = Database::getInstance($local);
}


/**
 * Example: [ 'user.id' => [ 'car.owner', 'invoice.customer' ], 'car.id' => [ 'car_usage.cid' ], ... ]
 */
public function setForeignKeyMap($fkey_map) {
    $this->fkey_map = $fkey_map;
}


/**
 * Synchronize remote entry where table.column = value.
 *
 * @param string $table
 * @param string $column
 * @param string $value
 */
public function syncEntry($table, $column, $value) {
    $select_qkey = 'select_'.$table.'_'.$column;
    $fkm_id = $table.'.'.$column;
    $table = ADatabase::escape_name($table);
    $column = ADatabase::escape_name($value);
    $select_query = "SELECT * FROM $table WHERE $column='{:=value}'";

    $this->remote_db->setQuery($select_qkey, $select_query);
    $this->local_db->setQuery($select_qkey, $select_query);

    $fkey_map = isset($this->fkey_map[$fkm_id]) ? $this->fkey_map[$fkm_id] : [];

    $this->remote_db->execute($this->remote_db->getQuery($select_qkey, [ 'value' => $value ]));
    while (($row = $this->remote_db->getNextRow())) {
        $lrow = $this->local_db->select($this->local_db->getQuery($select_qkey, [ 'value' => $value ]));

        if (count($lrow) == 1) {

        }
        else {

        }
    }
}


}

