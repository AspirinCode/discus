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

class User {
	public $logged = false;
	private $salt = null;
	private $password = null;
	
	public $id = null;
	public $gid = null;
	public $login = null;
	public $acl = array();
	
	function __construct() {
		global $CONFIG, $Database;
		
		$this -> Database = $Database;
		
		session_start();
		
		if(!empty($_POST['login']) && !empty($_POST['password'])) {
			$this -> login		= $this -> Database -> secure_mysql($_POST['login']);
			$this -> password	= $_POST['password'];
			$this -> id		= null;
		}
		elseif((!empty($_SESSION['id']) && !empty($_SESSION['password_hash'])) || !empty($_COOKIE['data'])) {
			if(isset($_COOKIE['data'])) {
				$cookie = explode(':', base64_decode($_COOKIE['data']));
			}
	
			$this -> id		= !empty($_SESSION['id']) ? (int) $_SESSION['id'] : (int) $cookie[0];
			$this -> password_hash	= !empty($_SESSION['password_hash']) ? $_SESSION['password_hash'] : $cookie[1];
			$this -> password	= null;
		}
		else {
			$this -> login		= null;
			$this -> password	= null;
			$this -> password_hash	= null;
			$this -> id		= null;
		}

		if(!empty($_GET['logout'])) {
			session_unset();
			session_destroy();
			$_SESSION = array();
	
			setcookie('data', '', time() - 7200);

			header('Location: '.$_SERVER['HTTP_REFERER']);
				exit;
		}
		else {
			$this -> validate();
		}
	
		if($this -> logged === true) {
			// Cache privs
		
		}
		else {
			$this -> login		= null;
			$this -> password	= null;
			$this -> password_hash	= null;
			$this -> id		= null;
		}
	}
	
	public function id() {
		return $this -> id;
	}
	
	public function gid() {
		return $this -> gid;
	}
	
	public function login() {
		return $this -> login;
	}
	
	public function acl() {
		return $this -> acl;
	}
	
	public function logged() {
		return $this -> logged;
	}
	
	function validate($pass = null) {
		global $Database, $CONFIG;

		if($this -> id) {
			$vars = '`id` = "'.$this -> id.'"';
		}
		elseif($this -> login) {
			$vars = '`login` = "'.$this -> login.'"';
		}
		else {
			return false;
		}
	
		$query = 'SELECT id, gid, login, password, salt, GROUP_CONCAT(perm.pid) AS acl FROM '.$CONFIG['db_name'].'.docking_users AS user LEFT JOIN '.$CONFIG['db_name'].'.docking_project_permitions AS perm ON user.id = perm.uid WHERE '.$vars.'  GROUP BY user.id LIMIT 1';
		$this -> Database -> query($query);
		
		if($this -> Database -> num_rows() == 1) {		
			$row = $Database -> fetch_row();
			if($pass === null && (!empty($this -> password_hash) && $this -> password_hash == sha1($row['password']) || !empty($this -> password) && sha1($row['salt'].$this -> password) == $row['password']) || !empty($pass) && sha1($row['salt'].$pass) == $row['password']) {
				# modify only when validating on the begining, usage of $pass just check password somwhere inside code.
				if($pass === null) {
					if($_POST['remember']) {
						setcookie('data', base64_encode($row['id'].':'.sha1($row['password'])), time() + 3600 * 24 * 30);
					}
					elseif(!$_COOKIE['data']) {
						$_SESSION['id']			= $row['id'];
						$_SESSION['password_hash']	= sha1($row['password']);
						$_SESSION['login']		= $row['login'];
					}
			
					$this -> login	= $row['login'];
					$this -> id	= $row['id'];
					$this -> salt	= $row['salt'];
					$this -> logged	= true;
					$this -> gid	= $row['gid'];
					# create 
					if($this -> gid == 1) {
						$query = 'SELECT id FROM '.$CONFIG['db_name'].'.docking_project';
						$this -> Database -> query($query);
						while($row = $Database -> fetch_row()) {
							$this -> acl[] = $row['id'];
						}
					}
					else {
						$this -> acl = array_filter(explode(',', $row['acl']));
					}
				}		
				return true;
			}
		}
		if($pass === null) {
			# destroy login data
			$_SESSION = array();
			setcookie('data', '', time() - 7200);
		}
		return false;
	}
}
?>
