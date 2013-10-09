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
class base {
	protected $project = null;
	protected $project_id = null;
	protected $per_page = 10;
	protected $result_num = 0;
	protected $page = 1;
	protected $offset = 0;
	
	public function __construct($mode) {
		global $Database, $User;
		include_once '/usr/local/lib/openbabel.php';
		$this -> Database = $Database;
		$this -> get_project_db();
		$this -> User = $User;
		$this -> OBConversion = new OBConversion;
		
		$this -> data_structure();
		
		# check acl
		if($this -> project_id > 0 && !in_array($this -> project_id, $this -> User -> acl())) {
			$mode = 'forbidden';
		}
		
		if(!empty($mode)) {
			$this -> ${mode}();
			$this -> mode = $mode;
		}
		
	}
	
	protected function get_project_db() {
		global $CONFIG;
		$target = $this -> Database -> secure_mysql($_GET['target'] ? $_GET['target'] : $_POST['target']);
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		$this -> project = $CONFIG['db_name'].'.project_'.$project.'_';
		$this -> project_id = $project;
		
		# pagination
		$this -> page = $_GET['page'] ? $_GET['page'] : ($_POST['page'] ? $_POST['page'] : 1);
		if((int)$_GET['pp'] > $this -> per_page) {
			$this -> per_page = (int)$_GET['pp'];
		}	
		$this -> offset = ($this -> page - 1) * $this -> per_page;
	}
	
	# get permanent link
	protected function get_link($substitute = array(), $remove = array(), $leave = array()) { # TODO: leave not yet implemented
		if(!empty($leave)) {
			foreach($leave as $key) {
				if(is_array($_GET[$key]) && $substitute[$key] !== '' && !is_array($substitute[$key])) {
					foreach($_GET[$key] as $n => $v) {
						if(!isset($remove[$key][$n])) {
							$vars[] = urlencode($key.'[]').'='.urlencode($v);
						}
					}
				}
				elseif(!is_array($_GET[$key]) && $_GET[$key] && $substitute[$key] !== '' && !isset($remove[$key])) {
					if($substitute[$key]) {
					$vars[] = urlencode($key).'='.urlencode($substitute[$key]);
					unset($substitute[$key]);
					}
					else {
					$vars[] = urlencode($key).'='.urlencode($_GET[$key]);
					}
				}
			}
		}
		else {
			foreach($_GET as $key => $value) {
				if(is_array($value) && $substitute[$key] !== '' && !is_array($substitute[$key])) {
					foreach($value as $n => $v) {
						if(!isset($remove[$key][$n])) {
							if(is_array($v)) {
								foreach($v as $n2 => $v2) {
									if(!isset($remove[$key][$n][$n2])) {
										$vars[] = urlencode($key.'['.$n.']['.$n2.']').'='.urlencode($v2);
									}
								}
							}
							else {
								$vars[] = urlencode($key.'['.$n.']').'='.urlencode($v);
							}
						}
					}
				}
				elseif(!is_array($value) && $value && $substitute[$key] !== '' && !isset($remove[$key])) {
					if($substitute[$key]) {
						$vars[] = urlencode($key).'='.urlencode($substitute[$key]);
						unset($substitute[$key]);
					}
					else {
						$vars[] = urlencode($key).'='.urlencode($value);
					}
				}
			}
		}
		foreach($substitute as $key => $value) {
			if(is_array($value)) {
				foreach($value as $v) {
					$vars[] = urlencode($key.'[]').'='.urlencode($v);
				}
			}
			elseif(!empty($value) || $value === 0) {
				$vars[] = urlencode($key).'='.urlencode($value);
			}
		}
		#TODO add array filter when rest is ready.
		return 'index.php?'.implode('&', $vars);
	}
	
	public function data_structure() { # defines data structure which is fetched from DB and displayed in table
		$this -> get_project_db();
		# Structure goes as follows
		# "name in DB", "print title", "decription", "feature type: 0 - both, 1 - molecular, 2 - conformational", "table alias (instead of conf.) - for queries"
		$this -> data_structure = array();
		$base_structure = array( 
			array ('name', 'Compound', 0),
		);
		
		if(!empty($this -> project_id)) {
			$query = 'SELECT field, name, type, prefix FROM '.$this -> project.'docking_properties ORDER BY `type` ASC, `order` ASC';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_row()) {
				$base_structure[] = $row;
			}
		
			# hide desired
			foreach ($base_structure as $field) {
				# perserve only name and 
				if($field[0] == 'name' || !@in_array($field[0], $this -> hide) && (empty($var_used) || in_array($field[0], $var_used))) {
					$this -> data_structure[] = $field;
				}
			}
		}
	}
	
	#function to fetch rest api to an assoc array
	public function fetch_rest($url, $timeout = 1) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$res = curl_exec($ch);
		return json_decode($res, true);
	}
	
	# common OB functions
	public function get_inchikey($mol, $opt = null) {
		$OBConversion = new OBConversion;
		if(!is_object($mol)) {
			$OBMol = new OBMol;
			$OBConversion -> SetInFormat('smi');
			#unify smiles before read
			$OBConversion -> ReadString($OBMol, $mol);
		}
		else {
			$OBMol = new OBMol($mol);
		}
		$OBConversion->SetOutFormat("inchikey");
		if(!empty($opt)) {
			$OBConversion -> SetOptions('T"'.$opt.'"', $OBConversion::OUTOPTIONS);
		}
		return trim($OBConversion->WriteString($OBMol));
	}
	
	public function get_smiles($mol) {
		$OBConversion = new OBConversion;
		$OBConversion->SetOutFormat("smi");
		return $OBConversion->WriteString($mol);
	}
	
	public function gen_2d($string, $format = 'smi') {
		$OBConversion = new OBConversion;
		if(!is_object($string)) {
			$OBMol = new OBMol;
			$OBConversion -> SetInFormat($format);
			#unify smiles before read
			$OBConversion -> ReadString($OBMol, ($format == 'smi' ? $this -> unify_smiles($string) : $string));
		}
		else {
			$OBMol = new OBMol($string);
		}
		
		$OBMol -> DeleteHydrogens();
		
		OBOp_c_Do(OBOp_FindType("gen2d"),$OBMol);
		OBOp_c_Do(OBOp_FindType("genalias"),$OBMol);
		

		$OBConversion -> SetOutFormat("mol");
		return $OBConversion -> WriteString($OBMol);
	}
	
	# Generate 3d coordinates from string or OBMol object
	public function gen_3d($string, $informat = 'smi', $outformat = 'mol2') {
		$OBConversion = new OBConversion;
		if(!is_object($string)) {
			$OBMol = new OBMol;
			$OBConversion -> SetInFormat($informat);
			$OBConversion -> ReadString($OBMol, $string);
		}
		else {
			$OBMol = new OBMol($string);
		}
		OBOp_c_Do(OBOp_FindType("gen3d"),$OBMol);
		if(!is_object($string)) {
			$OBConversion -> SetOutFormat($outformat);
			return $OBConversion -> WriteString($OBMol);
		}
		else {
			return $OBMol;
		}
	}
	
	public function gen_svg($string, $size = 100, $format = 'smi') {
		$OBConversion = new OBConversion;
		if(!is_object($string)) {
			$OBMol = new OBMol;
			$OBConversion -> SetInFormat($format);
			#unify smiles before read
			$OBConversion -> ReadString($OBMol, ($format == 'smi' ? $this -> unify_smiles($string) : $string));
		}
		else {
			$OBMol = new OBMol($string);
		}
		
		# questionable
#		OBOp_c_Do(OBOp_FindType("genalias"),$OBMol);
#		$OBConversion -> SetOptions('A', $OBConversion::OUTOPTIONS);

		$OBMol -> DeleteNonPolarHydrogens();

		OBOp_c_Do(OBOp_FindType("gen2d"),$OBMol);		

		$OBConversion -> SetOutFormat("svg");
		$OBConversion -> SetOptions('C', $OBConversion::OUTOPTIONS);
		$OBConversion -> SetOptions('d', $OBConversion::OUTOPTIONS);
		$OBConversion -> SetOptions('P"'.$size.'"', $OBConversion::OUTOPTIONS);
		return $OBConversion->WriteString($OBMol);
	}
	
	public function unify_smiles($smiles) {
		if(!empty($smiles)) {
			$OBConversion = new OBConversion;
			if(!is_object($smiles)) {
				$OBMol = new OBMol;
				$OBConversion->SetInFormat("smi");
				$OBConversion->ReadString($OBMol, $smiles);
			}
			else {
				$OBMol = new OBMol($smiles);
			}
			$OBConversion->SetOutFormat("smi");
			$OBConversion->AddOption("U");
			$OBConversion->AddOption("n");
			return preg_replace('/[\s\t]/', '', $OBConversion->WriteString($OBMol));
		}
		else {
			return null;
		}
	}
	
	public function inchify_smiles($smiles) {
		if(!empty($smiles)) {
			$OBConversion = new OBConversion;
			if(!is_object($smiles)) {
				$OBMol = new OBMol;
				$OBConversion->SetInFormat("smi");
				$OBConversion->ReadString($OBMol, $smiles);
			}
			else {
				$OBMol = new OBMol($smiles);
			}
			$OBConversion->SetOutFormat("smi");
			$OBConversion->AddOption("I");
			$OBConversion->AddOption("n");
			return preg_replace('/[\s\t]/', '', $OBConversion->WriteString($OBMol));
		}
		else {
			return null;
		}
	}
	
	#list all available plugins
	public function list_plugins() {
		$plugins = array();
		include_once 'plugins/plugin_interface.php';
		$files = preg_replace('/\.php$/', '', preg_grep('/^plugin\_(.+)\.php$/', array_diff(scandir('plugins'), array('.','..','plugin_interface.php'))));
		foreach($files as $file) {
			include_once 'plugins/'.$file.'.php';
			$plugins[] = array(preg_replace('/^plugin\_/', '', $file), $file::$name, $file::$desc);
		}
		
		return $plugins;
	}
	
	# generic view function
	public function view() {
		if(!empty($this -> mode)) {
			$mode = 'view_'.$this -> mode;
			if(method_exists($this,$mode)) {
				$this -> $mode();
			}
		}
	}
	
	public function tmp_file($source, $target = null) {
		global $CONFIG;
		#check if dir exists, and create if not
		if(!is_dir($CONFIG['temp_dir'])) {
			if(!mkdir($CONFIG['temp_dir'], 0755, true)) {
				return false;
			}
		}
		# generate random name for file
		if(empty($target)) {
			$target = md5(time()*rand()*mt_rand());
		}
		if(move_uploaded_file($source, $CONFIG['temp_dir'].'/'.$target)) {
			return $CONFIG['temp_dir'].'/'.$target;
		}
		else {
			return false;
		}
	}
	
	private function tmp_file_thumbnail($file = null, $size = null) {
		global $CONFIG;
		if(empty($file) && !empty($_GET['file_name'])) {
			#BEWARE - escape all weird strings
			$file = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $_GET['file_name']);
		}
		if(empty($size) && !empty($_GET['size'])) {
			$size = (int) $_GET['size'];
		}
		else {
			$size = 200;
		}
		#check if dir exists, and create if not
		if(file_exists($CONFIG['temp_dir'].'/'.$file) && !is_dir($CONFIG['temp_dir'].'/'.$file)) {
			$im = new imagick($CONFIG['temp_dir'].'/'.$file.'[0]');
			$im->setImageFormat('png');
			$im->thumbnailImage($size,$size,true);
			header( "Content-Type: image/png" );			
			echo $im;
		}
		else {
			header('HTTP/1.0 403 Forbidden');
		}
		exit;
	}
	
	public function toolbar() {
		global $CONFIG;

		$toolbar = '';
		
		#select project
		$query = 'SELECT project.id, project.name, project.user_id, owner.login AS owner_name FROM '.$CONFIG['db_prefix'].'.docking_project AS project LEFT JOIN '.$CONFIG['db_prefix'].'.docking_users AS owner ON project.user_id = owner.id '.($this -> User -> gid() != 1 ? ' WHERE project.id IN ('.implode(',', $this -> User -> acl()).')' : '');
		$this -> Database -> query($query);
		while($row = $this -> Database -> fetch_assoc()) {
			$projects[$row['id']] = $row;
		}
		
		$toolbar .= '<div class="btn-group">';
		$toolbar .= '<a class="btn dropdown-toggle '.(empty($_GET['project']) ? 'btn-danger' : '').'" data-toggle="dropdown" href="#">'.(empty($_GET['project']) ? 'Select project' : 'Project: '.$projects[$_GET['project']]['name']).' <span class="caret"></span></a>';
		$toolbar .= '<ul class="dropdown-menu">';
		if(!empty($projects)) {
			foreach($projects as $pid => $p) {
				$selected = $row['id'] == $_GET['project'] ? ' selected' : '';
				$toolbar .= '<li class="'.($pid == $_GET['project'] ? 'active' : '').'"><a href="./index.php?project='.$pid.'">'.($p['user_id'] != $this -> User -> id() ? '<span class="label">'.$p['owner_name'].'</span> ' : ' ').$p['name'].'</a></li>';
			}
		}
		$toolbar .= '<li class="divider"></li>';
		
		$toolbar .= '<li><a data-toggle="modal" data-target="#modal" href="'.$this -> get_link(array('module' => 'data_management', 'mode' => 'new_project', 'ajax' => 1), array(), array('project', 'module')).'">New Project</a></li>';
		
		$toolbar .= '</ul>';
		$toolbar .= '</div>';
		
		# profile editing
		$toolbar .= '<div class="btn-group">';
		$toolbar .= '<a class="btn dropdown-toggle" data-toggle="dropdown" href="#">Profile: '.$this -> User -> login().' <span class="caret"></span></a>';
		$toolbar .= '<ul class="dropdown-menu">';
		
		$toolbar .= '<li><a data-toggle="modal" data-target="#modal" href="'.$this -> get_link(array('module' => 'user_management', 'mode' => 'change_password', 'ajax' => 1), array(), array('project')).'">Change Password</a></li>';
		
		$toolbar .= '</ul>';
		$toolbar .= '</div>';
		
		#admin tools
		if($this -> User -> gid() == 1) {
			$toolbar .= '<div class="btn-group">';
			$toolbar .= '<a class="btn btn-warning dropdown-toggle" data-toggle="dropdown" href="#">Admin tools <span class="caret"></span></a>';
			$toolbar .= '<ul class="dropdown-menu">';
		
			$toolbar .= '<li><a data-toggle="modal" data-target="#modal" href="'.$this -> get_link(array('module' => 'user_management', 'mode' => 'user_add', 'ajax' => 1), array(), array('project')).'">Add user</a></li>';
#			# project updates
#			$query = 'SELECT id FROM '.$CONFIG['db_name'].'.docking_project_dbupdater WHERE `time` > (SELECT db_timestamp FROM '.$CONFIG['db_name'].'.docking_project WHERE id = '.$this -> project_id.' LIMIT 1) ORDER BY time ASC';
#			$this -> Database -> query($query);
#			$num = $this -> Database -> num_rows();
#			$toolbar .= '<li><a data-toggle="modal" data-target="#modal" href="'.$this -> get_link(array('module' => 'data_management', 'mode' => 'update_project', 'ajax' => 1), array(), array('project')).'">Update project <span class="badge '.($num > 0 ? 'badge-important' : '').'">'.$num.'</span></a></li>';
		
			$toolbar .= '</ul>';
			$toolbar .= '</div>';
		}
		
		return $toolbar;
	}
	
	public function pagination() { # show pagination for current search
		echo '<div class="pagination"><ul>';
		$pages = ceil($this -> result_num / $this -> per_page);
		# show prev if necessary
		if($this -> page > 1) {
			echo '<li><a href="'.$this -> get_link(array('page' => $this -> page - 1)).'">&laquo;</a></li>';
		}
		for($i=1; $i <= $pages; $i++) {
			if(abs($this -> page - $i) < 3 || $i == 1 || $i == $pages) {
				if($i == $this -> page) {
					#echo '<b>'.$i.'</b> ';
					echo '<li class="active"><span>'.$i.'</span></li>';
				}
				else {
					echo '<li><a href="'.$this -> get_link(array('page' => $i)).'">'.$i.'</a></li>';
					#echo '<input type="button" value="'.$i.'" onClick="location.href=\''.$this -> get_link(array('page' => $i)).'\'">';
				}
				$dots = false;
			}
			elseif(!$dots) {
				echo '<li class="active"><span>...</span></li>';
				$dots = true;
			}
		}
		# show next if necessary
		if($this -> page < $pages) {
			echo '<li><a href="'.$this -> get_link(array('page' => $this -> page + 1)).'">&raquo;</a></li>';
		}
		
		echo '<li class="dropdown" style="display: block!">';
		echo '<a class="dropdown-toggle" data-toggle="dropdown" href="#">';
		echo 'Per page <span class="caret"></a>';
		echo '<ol class="dropdown-menu">';
		$pages = array(10,25,50,100,200,500);
		foreach($pages as $p) {
			echo '<li class="'.($p == $this -> per_page ? 'active' : '').'"><a href="'.$this -> get_link(array('page' => 1, 'pp' => $p)).'">'.$p.'</a></li>';
		}
		echo '</ol>';
		echo '</li>';
		
		echo '</ul>';
		echo '</div>';
	}
	public function forbidden() {
		header('HTTP/1.0 403 Forbidden');
	}
	
	public function view_forbidden() {
		echo '<div class="alert alert-error">You don\'t have access to that area!</div>';
	}
}
?>
