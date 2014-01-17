<?php
include_once 'class/mysql.php';
include_once 'class/timer.php';
//Marking time of parsing start
$Timer = new Timer;
include_once 'config.php';
include_once 'class/login.php';

//Starting MySQL class
$Database = new Database($CONFIG['db_user'], $CONFIG['db_pass'], $CONFIG['db_host'], $CONFIG['db_name']);
//log in
$User = new User;

if($User -> logged() && $User -> gid() == 1) {
	# pull from git
	echo shell_exec("`which git` pull 2>&1");
}
else {
	echo 'forbidden!';
}
?>
