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

# base and common functions for all modules
class install {
	
	public function __construct() {
		$mode = $this -> mode = 'install';
		$this -> ${mode}();
	}
	
	public function install() {
		$Database = new Database($_POST['db_user'], $_POST['db_pass'], $_POST['db_host'], $_POST['db_name']);
		if(!empty($_POST)) {
			if(!$Database -> connect_only()) {
				$this -> status = 'db_connection';
			}
			elseif(!empty($_POST['connect']) && !$Database -> selectdb_only()) {
				$this -> status = 'db_select';
			}
			elseif(!empty($_POST['install']) && $Database -> selectdb_only()) {
				$this -> status = 'db_select';
			}
			elseif(!is_dir($_POST['tmp_dir']) || !is_writable($_POST['tmp_dir'])) {
				$this -> status = 'tmp_dir';
			}
			elseif(!empty($_POST['install']) && empty($_POST['pass'])) {
				$this -> status = 'emptypass';
			}
			elseif(!empty($_POST['install']) && !empty($_POST['pass']) && $_POST['pass'] != $_POST['pass_rep']) {
				$this -> status = 'pass';
			}
			else {
				
			
				#everything is ok, so proceed with install
				file_put_contents("config.php", '<?php
$CONFIG[\'temp_dir\']		= \''.$_POST['tmp_dir'].'\';
$CONFIG[\'db_host\']		= \''.(!empty($_POST['db_host']) ? $_POST['db_host'] : 'localhost').'\';
$CONFIG[\'db_user\']		= \''.$_POST['db_user'].'\';
$CONFIG[\'db_pass\']		= \''.$_POST['db_pass'].'\';
$CONFIG[\'db_name\']		= \''.$_POST['db_name'].'\';
$CONFIG[\'db_names\']		= \'utf8\';
?>');
				if(file_exists('config.php')) {
					$this -> status = 'success';
				}
				else {
					$this -> status = 'config';
				}
				
				# init database
				if(!empty($_POST['install'])) {
					$Database -> query('BEGIN;');
					$Database -> query('CREATE DATABASE '.$Database -> secure_mysql($_POST['db_name']));
					if($Database -> selectdb_only()) {
						#get schema
						$query = file_get_contents('db/discus_schema.sql');
						$Database -> multi_query($query);
					
						#add user
						$salt = substr(md5(time()*rand()*mt_rand()),0,8);
						$password = sha1($salt.$_POST['pass']);
						$query = 'INSERT INTO docking_users (`login`, `password`, `salt`, `fullname`, `gid`) VALUES ("'.$Database -> secure_mysql($_POST['login']).'", "'.$password.'", "'.$salt.'", "'.$Database -> secure_mysql($_POST['fullname']).'", 1);';
						$Database -> query($query);
						$Database -> query('COMMIT;');
					}
				}
				
				# recheck if success, delet config if not
				if($this -> status != 'success' && file_exists('config.php')) {
					unlink('config.php');
				}
			}
		}
	}
	
	public function view() {
		if(!empty($this -> mode)) {
			$mode = 'view_'.$this -> mode;
			if(method_exists($this,$mode)) {
				$this -> $mode();
			}
		}
	}
	
	public function view_install() {
		echo '<div class="container form-signin">';
		if($this -> status == 'db_connection') {
			echo '<div class="alert alert-error">Could not connect do database</div>';
		}
		elseif(!empty($_POST['connect']) && $this -> status == 'db_select') {
			echo '<div class="alert alert-error">Selected database does not exists</div>';
		}
		elseif(!empty($_POST['install']) && $this -> status == 'db_select') {
			echo '<div class="alert alert-error">Selected database exists, new one must be created</div>';
		}
		elseif($this -> status == 'tmp_dir') {
			echo '<div class="alert alert-error">Temporary directory is not writable</div>';
		}
		elseif($this -> status == 'config') {
			echo '<div class="alert alert-error">Could not write config.php file</div>';
		}
		elseif($this -> status == 'emptypass') {
			echo '<div class="alert alert-error">New users passwords may not be empty</div>';
		}
		elseif($this -> status == 'pass') {
			echo '<div class="alert alert-error">New users passwords don\'t match</div>';
		}
		
		if($this -> status == 'success') {
			echo '<a href="./index.php" class="btn btn-large btn-success">Success</a>';
		}
		else {
?>

	<div class="accordion" id="accordion2">	
		<div class="accordion-group">
			<div class="accordion-heading"><a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion2" href="#collapseOne"><h2>Install</h2></a></div>
			<div id="collapseOne" class="accordion-body collapse <?=(empty($_POST['connect']) ? 'in' : '')?>">
				<div class="accordion-inner">
					<form method="POST">
					
					<input type="text" name="db_host" value="<?=$_POST['db_host']?>" class="input-block-level" placeholder=" Database Host [default: localhost]"">
					<input type="text" name="db_user" value="<?=$_POST['db_user']?>" class="input-block-level" placeholder=" Database User">
					<input type="password" name="db_pass" value="<?=$_POST['db_pass']?>" class="input-block-level" placeholder="Database Password">
					<input type="text" name="db_name" value="<?=$_POST['db_name']?>" class="input-block-level" placeholder="Database Name">
					<input type="text" name="tmp_dir" value="<?=$_POST['tmp_dir']?>" class="input-block-level" value="" placeholder="Temporary Directory [/tmp, ./tmp]">
					
					</br></br></br>
					
					<input type="text" name="login" value="<?=$_POST['login']?>" class="input-block-level" placeholder="DiSCuS User">
					<input type="text" name="fullname" value="<?=$_POST['fullname']?>" class="input-block-level" placeholder="DiSCuS User Full Name">
					<input type="password" name="pass" value="<?=$_POST['pass']?>" class="input-block-level" placeholder="DiSCuS Password">
					<input type="password" name="pass_rep" value="<?=$_POST['pass_rep']?>" class="input-block-level" placeholder="DiSCuS Password Repeat">
					
					<button class="btn btn-large btn-warning" name="install" type="submit" value=1>Proceed</button>
					</form>
				</div>
			</div>
		</div>
		<div class="accordion-group">
			<div class="accordion-heading"><a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion2" href="#collapseTwo"><h2>Connect</h2></a></div>
			<div id="collapseTwo" class="accordion-body collapse <?=(!empty($_POST['connect']) ? 'in' : '')?>">
				<div class="accordion-inner">
					<form method="POST">
					
					<input type="text" name="db_host" value="<?=$_POST['db_host']?>" class="input-block-level" placeholder=" Database Host [default: localhost]">
					<input type="text" name="db_user" value="<?=$_POST['db_user']?>" class="input-block-level" placeholder=" Database User">
					<input type="password" name="db_pass" value="<?=$_POST['db_pass']?>" class="input-block-level" placeholder="Database Password">
					<input type="text" name="db_name" value="<?=$_POST['db_name']?>" class="input-block-level" placeholder="Database Name">
					<input type="text" name="tmp_dir" value="<?=$_POST['tmp_dir']?>" class="input-block-level" value="" placeholder="Temporary Directory [/tmp, ./tmp]">
					
					<button class="btn btn-large btn-warning" name="connect" type="submit" value=1>Proceed</button>
					</form>
				</div>
			</div>
		</div>
	</div>

<?php
		}
		echo '</div>';
	}
	
}
?>
