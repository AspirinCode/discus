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

include_once 'class/mysql.php';
include_once 'class/timer.php';
//Marking time of parsing start
$Timer = new Timer;

// enable autoload of controllers
spl_autoload_register(function ($module) {
	#include 'modules/base.php';
	if(file_exists('modules/'.$module.'.php')) {
		include_once 'modules/'.$module.'.php';
	}
	elseif(file_exists('plugins/'.$module.'.php')) {
		include_once 'plugins/'.$module.'.php';
	}
});

//Including config and classes vital for script
if(file_exists('config.php')) {
	define('IN_DISCUS', true);
	# define ajax for future reference
	define('IS_AJAX', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
	
	include_once 'config.php';
	include_once 'class/login.php';
	
	//Starting MySQL class
	$Database = new Database($CONFIG['db_user'], $CONFIG['db_pass'], $CONFIG['db_host'], $CONFIG['db_name']);
	//log in
	$User = new User;

	$module = $_GET['module'];	
	$mode = $_GET['mode'];


	if($User -> logged()) {
		if(empty($module)) {
			$module = 'molecules';
		}
		$mod = new $module($mode);
	
		#check if mode wasn't changed internally
		if($mode != $mod -> mode) { 
			$mode = $mod -> mode;
		}
	
		# for ajax
		if(IS_AJAX) {
			$view = 'view_'.$mode;
			$mod -> $view();
			exit;
		}
	}
}
else {
	define('IN_DISCUS', false);
	$module = 'install';
	$mod = new $module();
	$mode = $mod -> mode;

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="pragma" content="no-cache">
	<meta http-equiv="cache-control" content="no-cache">
	<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
	<title>DiSCuS</title>
	
	
	<link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
	<link href="style.css" type="text/css" rel="stylesheet">

	
	<script type="text/javascript" src="common.js"></script>

	
	<script type="text/javascript" src="//www.google.com/jsapi"></script>
	

	<script type="text/javascript" src="jsmol/JSmol.min.js"></script>
	<script type="text/javascript" src="jsmol/js/JSmolThree.js"></script>
	<script type="text/javascript" src="jsmol/js/JSmolGLmol.js"></script>
	
	<script type="text/javascript" language="javascript" src="jsme/jsme/jsme.nocache.js"></script>

<script>    
	//this function will be started after the JavaScriptApplet code has been loaded
	function drawJsme() {
		if($('#smiles').val() != "") {
	    		var startingStructure = convert_smi2sdf($('#smiles').val());
	    	}
	    	else {
		    	var startingStructure = "";
	    	}
	    	
	    	//Instantiate a new JSME:
	    	//arguments: HTML id, width, height (must be string not number!)
	    	
	    	if($('#jsme-container:visible').length > 0) {
		     	jsmeApplet = new JSApplet.JSME("jsme-container", "500px", "400px", {
		     		//optional parameters
		     		"options" : "query,hydrogens,removehs,noreaction,oldlook"
		     	});
			if(jsmeApplet) {
			    	document.JSME = jsmeApplet;
			    	//notify on changes
			    	document.JSME.setNotifyStructuralChangeJSfunction('JSME_update');
			    	document.JSME.setMolecularAreaAntiAlias(true);
			}
		}
	}
	function jsmeOnLoad() {
	     	drawJsme()
	}
</script>
</head>
<body>
<div id="loading"><div id="processing">Processing, please wait...</br><div class="progress progress-success progress-striped active"><div class="bar" style="width: 100%;"></div></div></div></div>

<div id="modal" class="modal hide fade">
  <div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h3>Modal header</h3>
  </div>
  <div class="modal-body">
  </div>
  <div class="modal-footer">
    <button class="btn btn-large btn-success">Submit</button>
  </div>
</div>
<script>
$(function() {
	$('#modal > .modal-footer > button').click(function() {
		var button = $(this);
		if($('#modal > .modal-body > form').length > 0) {
			if(!$(this).hasClass('disabled')) {
				$('#modal > .modal-body > form').ajaxSubmit( {
					target : $('#modal > .modal-body'),
					success: function() {
						if($('#modal > .modal-body > form').length > 0) {
							button.removeClass('disabled').addClass('btn-success').html('Submit');
						}
						else {
							button.hide()
						}
					}
				});
				// add progress bar
				$('#modal > .modal-body').html('<div class="progress progress-striped active"><div class="bar" style="width: 100%;"></div></div>');
			
			}
			$(this).addClass('disabled').removeClass('btn-success').html('loading...');
		}
		else {
			$('#modal').modal('hide');
		}
	});
	
	//modal header modification
	$('a[data-toggle="modal"]').click(function() {
		$('#modal > .modal-header > h3').text($(this).text());
		$('#modal > .modal-footer > button').addClass('btn-success').removeClass('disabled').html('Submit').show();
	});
	// destroy modal when hidden
	$('body').on('hidden', '.modal', function () {
	  $(this).removeData('modal');
	});
});
</script>

<div id="header">
	<div id="header-logo">
		<h1><a href="./index.php<?=(!empty($_GET['project']) ? '?project='.$_GET['project'] : '')?>">DiSCuS</a></h1><a href="./index.php?project=<?=$_GET['project']?>">Database System for Compounds Selection</a>
	</div>
	<div id="toolbar">
<?php
if(IN_DISCUS && is_object($User) && $User -> logged()) {
	if(method_exists($mod, 'toolbar')){
		echo $mod -> toolbar();
	}
	echo ' <a href="./index.php?logout=1" class="btn btn-danger" type="button">Logout <i class="icon-off icon-white"></i></a>';
}
?>
	</div>
</div>
<?php
if(IN_DISCUS && !$User -> logged()) {
	?>
<div class="container">
	<form class="form-signin" method="POST">
		<h2 class="form-signin-heading">Please sign in</h2>
		<input type="text" name="login" class="input-block-level" placeholder="User name">
		<input type="password" name="password" class="input-block-level" placeholder="Password">
		<label class="checkbox"><input type="checkbox" name="remember"> Remember me</label>
		<button class="btn btn-large" type="submit">Sign in</button>
	</form>
</div>
	<?php
}
elseif(!IN_DISCUS || IN_DISCUS && $User -> logged() && $module) {
	$mod -> view();
}
# show times
if(is_object($Timer)) {
?>
<div id="footer">Execution time: <?php  echo $Timer -> overallTimer(); ?> s</br>MySQL queries: <?php echo $Timer -> mysqlTimer()?> s</br>PHP execution: <?php echo round($Timer -> overallTimer() - $Timer -> mysqlTimer(), 5);?> s</div>
<?php
}
?>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script src="//code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
<script src="jquery/jquery.form.min.js"></script>
<script type="text/javascript" src="common_jquery.js"></script>
<script src="bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
