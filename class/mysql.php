<?php
/********************************************************************

DiSCuS - Database System for Compound Selection
Copyright (C) 2012-2013  Maciej Wojcikowski <maciek@wojcikowski.pl>

This file is part of DiSCuS.

DiSCuS is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

DiSCuS is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with DiSCuS.  If not, see <http://www.gnu.org/licenses/>.

********************************************************************/

class Database {
	private $user;
	private $password;
	private $dbhost;
	private $dbname;
	public $count = 0;
	public $parse_time = 0;
	private $dbconnection = false;

	public function __construct($user, $password, $dbhost, $dbname, $dbnames = false) {
		$this -> user         = $user;
		$this -> password     = $password;
		$this -> dbhost       = $dbhost;
		$this -> dbname       = $dbname;
	}

	public function connect_only() {
		if($this -> dbconnection) {
			return true;
		}

		$this -> dbconnection = @mysqli_connect($this -> dbhost, $this -> user, $this -> password);

		if(!$this -> dbconnection) {
			$this -> _error(connect);
			return false;
		}
		else {
			return true;
		}
	}
	
	public function selectdb_only() {
		$this -> dbselect = mysqli_select_db($this -> dbconnection, $this -> dbname);

		if(!$this -> dbselect) {
			return false;
		}
		else {
			return true;
		}
	}	
	
	private function connect() {
		global $CONFIG;

		if(!$this -> connect_only() || !$this -> selectdb_only()) {
			return false;
		}
		
		if($this -> dbconnection && $this -> dbselect) {
			if(!$dbnames && $CONFIG['db_names']) {
				$dbnames = $CONFIG['db_names'];
			}

			if($dbnames) {
				$this -> query('SET NAMES `'.$dbnames.'`');
			}
			return true;
		}
	}
	
	function secure_mysql($var) {
		if(!$this -> dbconnection) {
			$this -> connect();
		}
		return mysqli_real_escape_string($this -> dbconnection, $var);
	}
	
	public function query($query) {
		global $Timer;
		
		if(empty($query)) {
			$this -> dbquery = null;
			return true;
		}
		
		if(!$this -> dbconnection) {
			$this -> connect();
		}
		$this -> count++;
		$Timer -> start_mysql();
		$this -> dbquery = mysqli_query($this -> dbconnection, $query);
		if(!$this -> dbquery) {
			$this -> _error(query);
			return false;
		}
		if($this -> dbquery) {
			$Timer -> stop_mysql();
			return true;
		}
	}
	
	public function multi_query($query) {
		global $Timer;
		
		if(empty($query)) {
			$this -> dbquery = null;
			return true;
		}
		
		if(!$this -> dbconnection) {
			$this -> connect();
		}
		$this -> count++;
		$Timer -> start_mysql();
		$this -> dbquery = mysqli_multi_query($this -> dbconnection, $query);
		
		#execute
		mysqli_use_result($this -> dbconnection);
		while(mysqli_next_result($this -> dbconnection)) {
			continue;
		}
		
		if(!$this -> dbquery) {
			$this -> _error(query);
			return false;
		}
		if($this -> dbquery) {
			$Timer -> stop_mysql();
			return true;
		}
	}

	public function fetch_row() {
		$array = mysqli_fetch_array($this -> dbquery);
		if($array) {
			return $array;
		}
		else {
			return false;
		}
	}
    
	public function fetch_assoc() {
		$array = mysqli_fetch_assoc($this -> dbquery);
		if($array) {
			return $array;
		}
		else {
			return false;
		}
	}

	public function num_rows() {
		if($this -> dbquery) {
			return mysqli_num_rows($this -> dbquery);
		}
		else {
			return null;
		}
	}
	
	public function affected_rows() {
		return mysqli_affected_rows($this -> dbconnection);
	}
	
	public function insert_id() {
		return mysqli_insert_id($this -> dbconnection);
	}
	
	public function table_status($name, $field = null) {
		$row = mysqli_fetch_array(mysqli_query('SHOW TABLE STATUS LIKE \''.$name.'\''));
		if($field) {
			return $row[$field];
		}
		else {
			return $row;
		}
	}
	
	public function free_result() {
		if(mysqli_free_result($this -> dbquery)) {
			return true;
		}
		else {
			return false;
		}
	}

	private function disconnect() {
		$disconnect = mysqli_close($this -> dbconnection);
		if($disconnect)	{
			return true;
		}
		else {
			$this -> _error(disconnect);
			return false;
		}
	}

	private function _error($type) {
		if($this -> dbconnection) {
			echo 'MySQL said: <i>'.mysqli_error($this -> dbconnection).'</i><br>';
		}
	}
	
	public function __destruct() {
		if($this -> dbconnection) {
			$this -> disconnect();
		}
	}
}
?>
