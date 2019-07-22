<?php
/* Copyright (C) 2019 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!class_exists('SeedObject'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call or for session timeout on our module page
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class showcategory extends SeedObject
{


    public $table_element = 'c_show_category';

    public $element = 'Show category';

    public $isextrafieldmanaged = 0;


    public $fields = array(
        'label' => array('type' => 'varchar(255)', 'label' => 'Label'),
        'default_price' => array('type' => 'double(24,8)', 'label' => 'Default Price'),
        'active' => array('type' => 'int', 'label' => 'Active'),
    );

    public $label;

    public $default_price;

    public $active;


    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        $this->init();

        $this->entity = $conf->entity;
    }

    public function fetchAll($sortorder='', $sortfield='', $limit=0, $offset=0, array $filter=array(), $filtermode='AND')
    {
        global $conf;

        dol_syslog(__METHOD__, LOG_DEBUG);

        $records=array();

        $sql = 'SELECT';
        $sql .= ' t.rowid, t.label';
        // TODO Get all fields
        $sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element. ' as t';
        $sql .= ' WHERE 1=1';
        // Manage filter
        $sqlwhere = array();
        if (count($filter) > 0) {

            foreach ($filter as $key => $value) {
                if ($key=='t.rowid') {
                    $sqlwhere[] = $key . '='. $value;
                }
                elseif (strpos($key,'date') !== false) {
                    $sqlwhere[] = $key.' = \''.$this->db->idate($value).'\'';
                }
                elseif ($key=='customsql') {
                    $sqlwhere[] = $value;
                }
                else {
                    $sqlwhere[] = $key . ' LIKE \'%' . $this->db->escape($value) . '%\'';
                }
            }
        }
        if (count($sqlwhere) > 0) {
            $sql .= ' AND (' . implode(' '.$filtermode.' ', $sqlwhere).')';
        }

        if (!empty($sortfield)) {
            $sql .= $this->db->order($sortfield, $sortorder);
        }
        if (!empty($limit)) {
            $sql .=  ' ' . $this->db->plimit($limit, $offset);
        }
        $resql = $this->db->query($sql);
        if ($resql) {

            $num = $this->db->num_rows($resql);

            while ($obj = $this->db->fetch_object($resql))
            {
                $record = new self($this->db);

                $record->id = $obj->rowid;
                $record->label = $obj->label;
                // TODO Get other fields

                //var_dump($record->id);
                $records[$record->id] = $record;
            }
            $this->db->free($resql);

            return $records;

        } else {
            $this->errors[] = 'Error ' . $this->db->lasterror();
            dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

            return -1;
        }
    }
}