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

class user_management extends base {
	private $pass_changed = 0;
	private $status = 0;
	
	public function change_password() {
		global $CONFIG;
		
		if(!empty($_POST['current_pass']) && !empty($_POST['new_pass']) && $_POST['new_pass'] == $_POST['new_pass_rep']) {
			if($this -> User -> validate($this -> Database -> secure_mysql($_POST['current_pass']))) {
				$query = 'UPDATE '.$CONFIG['db_name'].'.docking_users SET password = SHA1(CONCAT(salt, "'.$this -> Database -> secure_mysql($_POST['new_pass']).'")) WHERE `id` = "'.$this -> User -> id.'" LIMIT 1;';
				#echo $query;
				if($this -> Database -> query($query)) {
					$this -> pass_changed = 1;
				}
			}
			else {
				$this -> pass_changed = -1;
			}
		}
	}
	
	public function user_add() {
		global $CONFIG;
		#allow only admin
		if($this -> User -> gid() == 1 && !empty($_POST['login']) && !empty($_POST['pass']) && $_POST['pass'] == $_POST['pass_rep']) {
			#generate salt
			$salt = substr(md5(time()*rand()*mt_rand()),0,8);
			$password = sha1($salt.$_POST['pass']);
			$query = 'INSERT INTO '.$CONFIG['db_name'].'.docking_users (`login`, `password`, `salt`, `fullname`, `gid`) VALUES ("'.$this -> Database -> secure_mysql($_POST['login']).'", "'.$password.'", "'.$salt.'", "'.$this -> Database -> secure_mysql($_POST['fullname']).'", '.((int)$_POST['gid']).');';
			#echo $query;
			
			if($this -> Database -> query($query)) {
				$uid = $this -> Database -> insert_id();
				#asign user to projects
				if(!empty($_POST['project_asign'])) {
					$query = 'INSERT INTO '.$CONFIG['db_name'].'.docking_project_permitions (`pid`, `uid`) VALUES ('.$this -> Database -> secure_mysql(implode(','.$uid.'),(', $_POST['project_asign'])).','.$uid.');';
					#echo $query;
					$this -> Database -> query($query);
				}
				
				$this -> status = 1;
			}
		}
	}
	
	
	public function user_edit() {
		if($this -> User -> gid() == 1) {
			
		}
	}
	
	public function user_delete() {
		if($this -> User -> gid() == 1) {
			
		}
	}
	
	#####
	
	public function view_change_password() {
		if($this -> pass_changed == 1) {
			echo '<div class="alert alert-success">Password has beed changed.</div>';
		}
		else {
			echo '<form method="POST" action="'.$this -> get_link().'">';
			echo '<div class="control-group '.($this -> pass_changed < 0 ? 'error' : '').'"><div class="controls"><input type="password" name="current_pass" class="input" placeholder="Current Password"></div></div>';
			echo '<div class="control-group"><div class="controls"><input type="password" name="new_pass" class="input" placeholder="New Password"></div></div>';
			echo '<div class="control-group"><div class="controls"><input type="password" name="new_pass_rep" class="input" placeholder="Repeat New Password"></div></div>';
			if(!IS_AJAX) {
				echo '<input type="submit" class="btn" value="Change password">';
			}
			echo '<form>';
			?>
			<script>
			$(function() {
				$('input[name^=new_pass]').change(function() {
					if($('input[name=new_pass]').val().length > 5 && $('input[name=new_pass_rep]').val().length > 5) {
						if($('input[name=new_pass]').val() != $('input[name=new_pass_rep]').val()) {
							$('input[name^=new_pass]').closest('div.control-group').each(function() {
								$(this).removeClass('success');
								$(this).addClass('error');
							});
						}
						else if($('input[name=new_pass]').val() == $('input[name=new_pass_rep]').val()) {
							$('input[name^=new_pass]').closest('div.control-group').each(function() {
								$(this).removeClass('error');
								$(this).addClass('success');
							});
						}
					}
				});
			});
			</script>
			<?php
		}
	}
	
	public function view_user_add() {
		# allow only admin
		if($this -> User -> gid() != 1) {
			$this -> view_forbidden();
			exit;
		}
		if($this -> status == 1) {
			echo '<div class="alert alert-success">User <b>'.$_POST['login'].'</b> has beed added.</div>';
		}
		else {
			echo '<form method="POST" action="'.$this -> get_link().'">';
			echo 'User login:';
			echo '<input type="text" name="login" class="input" placeholder="Login">';
			echo '</br>';
			echo 'User full name:';
			echo '<input type="text" name="fullname" class="input" placeholder="Full Name">';
			echo '</br>';
			echo '<div class="control-group"><div class="controls"><input type="password" name="pass" class="input" placeholder="Password"></div></div>';
			echo '<div class="control-group"><div class="controls"><input type="password" name="pass_rep" class="input" placeholder="Repeat Password"></div></div>';
			
			echo 'Group:</br>';
			echo '<select name="gid">';
			echo '<option value=0>User</option>';
			echo '<option value=1>Admin</option>';
			echo '</select>';
			echo '</br>';
			echo '</br>';
			
			echo 'Asign user to following projects:</br>';
			echo '<select multiple="multiple" name="project_asign[]">';
			$query = 'SELECT project.id, project.name, project.user_id, owner.login AS owner_name FROM '.$CONFIG['db_prefix'].'.docking_project AS project LEFT JOIN '.$CONFIG['db_prefix'].'.docking_users AS owner ON project.user_id = owner.id '.($this -> User -> gid() != 1 ? ' WHERE project.id IN ('.implode(',', $this -> User -> acl()).')' : '');
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<option value='.$row['id'].'>'.$row['name'].($row['user_id'] != $this -> User -> id() ? ' ['.$row['owner_name'].']' : '').'</option>';
			}
			echo '</select>';
			echo '</br>';
			
			if(!IS_AJAX) {
				echo '<input type="submit" class="btn" value="Add user">';
			}
			
			echo '<form>';
			
			?>
			<script>
			$(function() {
				$('input[name^=pass]').change(function() {
					if($('input[name=pass]').val().length > 5 && $('input[name=pass_rep]').val().length > 5) {
						if($('input[name=pass]').val() != $('input[name=pass_rep]').val()) {
							$('input[name^=new_pass]').closest('div.control-group').each(function() {
								$(this).removeClass('success');
								$(this).addClass('error');
							});
						}
						else if($('input[name=pass]').val() == $('input[name=pass_rep]').val()) {
							$('input[name^=pass]').closest('div.control-group').each(function() {
								$(this).removeClass('error');
								$(this).addClass('success');
							});
						}
					}
				});
			});
			</script>
			<?php
		}
	}
	
	public function view_user_edit() {
		global $CONFIG;
		# allow only admin
		if($this -> User -> gid() != 1) {
			$this -> view_forbidden();
			exit;
		}
		
		if($this -> User -> gid() == 1) {
			echo '<table class="table table-striped table-hover">';
			# header
			echo '<thead>';
			echo '<tr>';
			echo '<td><b>User</b></td>';
			echo '<td><b>Full Name</b></td>';
			echo '<td><b>Group</b></td>';
			echo '<td><b>Own Projects</b></td>';
			echo '<td><b>Shared Projects</b></td>';
			echo '<td><b>Edit</b></td>';
			echo '<td><b>Delete</b></td>';
			echo '</tr>';
			echo '</thead>';
			
			# get projects 
			$projects = array();
			$query = 'SELECT p.id, p.user_id, p.name, GROUP_CONCAT(perm.uid) AS users  FROM '.$CONFIG['db_prefix'].'.docking_project AS p LEFT JOIN '.$CONFIG['db_prefix'].'.docking_project_permitions AS perm ON p.id=perm.pid GROUP BY p.id';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$p = $row;
				$p['users'] = explode(',', $row['users']);
				$projects[] = $p;
			}
			
			# draw table with users
			$query = 'SELECT * FROM '.$CONFIG['db_prefix'].'.docking_users';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<tr>';
				echo '<td>'.$row['login'].'</td>';
				echo '<td>'.$row['fullname'].'</td>';
				if($row['gid'] == 1) {
					echo '<td><span class="label label-important">Admin</span></td>';
				}
				else {
					echo '<td><span class="label label-info">User</span></td>';
				}
				
				echo '<td>';
				foreach($projects as $p) {
					if($row['id'] == $p['user_id']) {
						echo '<span class="label">'.$p['name'].'</span> ';
					}
				}
				echo '</td>';
				
				if($row['gid'] == 1) {
					echo '<td><span class="label label-success">All</span></td>';
				}
				else {
					echo '<td>';
					foreach($projects as $p) {
						if($row['id'] != $p['user_id'] && in_array($row['id'], $p['users'])) {
							echo '<span class="label">'.$p['name'].'</span> ';
						}
					}
					echo '</td>';
				}
				
				echo '<td><a class="query_delete_target btn btn-warning btn-mini"><i class="icon-edit icon-white"></i></a></td>';
				echo '<td><a class="query_delete_target btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></a></td>';
				echo '</tr>';
			}
			
			echo '</table>';
		}
	}
	
	public function view_user_delete() {
		# allow only admin
		if($this -> User -> gid() != 1) {
			$this -> view_forbidden();
			exit;
		}
		
		if($this -> User -> gid() == 1) {
			if(empty($_POST['confirm'])) {
				echo '<form method="POST" action="'.$this -> get_link().'">';
				echo '<div class="alert alert-error">';
				echo '<b>LAST WARNING!</b> Do you want to delete user <b></b>? This action cannot be reverted!</br>';
				echo '<input type="hidden" name="confirm" value="1">';
				if(!IS_AJAX) {
					echo '<button class="btn btn-danger">Confirm</button>';
				}
				echo '</div>';
				echo '</form>';

			}
			else {
				echo 'kill';
			}
		}
	}
}
?>
