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

class data_management extends base {
	protected $OBConversion = null;
	private $upload_dir = '/tmp/discus_';
	
	public function __construct($mode) {
		global $Database;
		$this -> Database = $Database;
		
		$this -> data_structure();
		
		parent::__construct($mode);
	}
	
	public function data_structure() { # defines data structure which is fetched from DB and displayed in table
		$this -> get_project_db();
		# Structure goes as follows
		# "name in DB", "print title", "decription", "feature type: 0 - both, 1 - molecular, 2 - conformational", "table alias (instead of conf.) - for queries"
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
	
	public function gen_targets() {
		global $CONFIG;
		
		if(count(@array_unique(@array_filter($_GET['var_target']))) > 0) {
			$this -> target = array_unique(@array_filter($_GET['var_target']));
		}
		elseif(count(@array_unique(@array_filter($_GET['target']))) > 0) {
			$this -> target = array_unique(@array_filter($_GET['target']));
		}
		
		$project = (int) $this -> Database -> secure_mysql($_GET['project']);
		if (empty($this -> target) || in_array(-1,$this -> target)) {
			$this -> target = array();
			if($project) {
				$query = 'SELECT id, name FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
				$this -> Database -> query($query);
				while($row = $this -> Database -> fetch_assoc()) {
					$targets[] = 'target_id='.$row['id'];
					$this -> target[] = $row['id'];
					$this -> target_names[$row['id']] = $row['name'];
				}
			}
		}
		// or just get target names
		else { 
			if($project) {
				$query = 'SELECT id, name FROM '.$this -> project.'docking_targets WHERE id IN('.$this -> Database -> secure_mysql(implode(',',$this -> target)).');';
				$this -> Database -> query($query);
				while($row = $this -> Database -> fetch_assoc()) {
					$this -> target_names[$row['id']] = $row['name'];
				}
			}
		}
		
		#append sorting target as first
		if($_GET['sort_target'] > 0) {
			$this -> target = array_unique(array_merge(array((int)$_GET['sort_target']), $this -> target));		
		}
	}
	
	private function parse_file($file, $format = 'mol2', $batch = 1, $batch_size = 100) {
#		if($format == 'mol2') {
#			$format = 'copy';
#		}
		$OBLog = openbabel::obErrorLog_get();
		$OBLog -> StopLogging();

		$OBMol = new OBMol;
		$OBConversion = new OBConversion;
#		$OBConversion -> AddOption('f', $OBConversion::GENOPTIONS, 1);#($batch-1)*$batch_size+1);
#		$OBConversion -> AddOption('l', $OBConversion::GENOPTIONS, 100);#$batch*$batch_size);
		$OBConversion -> SetInFormat($format);
		
		$notatend = $OBConversion -> ReadFile($OBMol, $file);
		while($notatend) {
			#$mols[] = $OBMol;
			$OBConversion -> SetOutFormat('mol2');
			$string_mol2 = $OBConversion -> WriteString($OBMol);
			
			$mols[] = array('name' => trim($OBMol -> GetTitle()), 'mol2' => pack('L', strlen($string_mol2)).gzcompress($string_mol2));
			
#			unset($OBMol);
#			$OBMol = new OBMol;
			$OBMol -> Clear();
			$notatend = $OBConversion -> Read($OBMol);
		}
		
		return $mols;	
	}
	
	public function copy_mol_input($mol) {
		$OBConversion = new OBConversion;
		$OBConversion -> SetOutFormat("smi");
		return $OBConversion -> WriteString($mol);
	}
	
	public function get_mol2($mol) {
		$this -> OBConversion -> SetOutFormat("mol2");
		return $this -> OBConversion -> WriteString($mol);
	}
	
	private function get_docking_scores($file, $just_first = false) {
		if($_POST['file_format'] == 'mol2') {
			$pfile = gzopen($file, 'rb');
			$output = array();
			$i = -1; # molecule counter (negative so first is 0)
			while($line = fgets($pfile)) {
				if(preg_match("/^\#{10}\s+(?P<label>[\w\s]+):\s+(?P<value>[\S]+)/", $line, $l)) {
					if(mb_strtolower($l['label']) == "name") {
						$i++;
					}
					$output[$i][mb_strtolower($l['label'])] = $l['value'];
					#if only one (first) molecule is  needed, then skip
					if($just_first && $i > 0) {
						break;
					}
				}
			}
		}
		elseif($_POST['file_format'] == 'sdf') {
			$OBMol = new OBMol;
			$OBConversion = new OBConversion;
#			$OBConversion -> AddOption('f', $OBConversion::GENOPTIONS, 1);#($batch-1)*$batch_size+1);
#			$OBConversion -> AddOption('l', $OBConversion::GENOPTIONS, 100);#$batch*$batch_size);
			$OBConversion -> SetInFormat('sdf');
			$OBConversion -> SetOptions('P', $OBConversion::OUTOPTIONS);
			$notatend = $OBConversion -> ReadFile($OBMol, $file);
			while($notatend) {
				#while(
				$size = $OBMol -> DataSize();
				$data = $OBMol -> GetData();
				
				for($i = 0; $i<$size;$i++) {
					$v = $data -> get($i);
					# get only valid data
					if(($v -> GetDataType() == openbabel::PairData || $v -> GetDataType() == openbabel::CommentData)
					&& $v -> GetAttribute() != 'MOL Chiral Flag'
					&& $v -> GetAttribute() != 'OpenBabel Symmetry Classes') {
						$o[$v -> GetAttribute()] = $v -> GetValue();
					}
				}
				$output[] = $o;

				if($just_first) {
					return $output;
				}
				#$OBMol = new OBMol;
				$OBMol -> Clear();
				$notatend = $OBConversion -> Read($OBMol);
			}
		}
		return $output;
	}
	
	public function import_form() {
		# ligand file
		if($_FILES["file"]["size"] > 0) {
			#move file
			$this -> upload_file = md5(time()*rand()).'_'.$_FILES["file"]["name"];
			move_uploaded_file($_FILES["file"]["tmp_name"], $this -> upload_dir.$this -> upload_file);
		}
		elseif(!empty($_POST['file_name'])) {
			$this -> upload_file = $_POST['file_name'];
		}
		
		# receptor file
		if($_FILES["target_file"]["size"] > 0) {
			#move file
			$this -> target_file = md5(time()*rand()).'_'.$_FILES["target_file"]["name"];
			move_uploaded_file($_FILES["target_file"]["tmp_name"], $this -> upload_dir.$this -> target_file);
		}
		elseif(!empty($_POST['file_name'])) {
			$this -> target_file = $_POST['target_file_name'];
		}
		
		if(is_file($this -> upload_dir.$this -> upload_file) && filesize($this -> upload_dir.$this -> upload_file) > 0) {
			# get first line to check if mapping is assigned
			$dock = $this -> get_docking_scores($this -> upload_dir.$this -> upload_file, true);
			if(count($dock[0]) - count($_POST['mapping']) < 1) {
				# get all info
				$dock = $this -> get_docking_scores($this -> upload_dir.$this -> upload_file);
				foreach($this -> parse_file($this -> upload_dir.$this -> upload_file, $format = $_POST['file_format']) as $key => $mol) {
					$this -> mols[] = array_merge($mol, array('scores' => $dock[$key])); 
					#$unique_key = $this -> get_inchikey($mol, '/nostereo');
					# get oryginal molecules name
					if($_POST['file_format'] == 'sdf') {
						# GOLD uses | as a separator in names
						#$name = explode('|', $mol -> GetTitle());
						$name = explode('|', $mol['name']);					
						$unique_key = $name[0];
					}
					else {
						#$unique_key = $mol -> GetTitle();
						$unique_key = $mol['name'];
					}
					$this -> unique_mols[$unique_key][] = $key;
				}
			}
		}
	}
	
	function new_field() {
		$this -> get_project_db();
		
		if(!empty($_GET['field_name'])) {
			$field_name = $this -> Database -> secure_mysql(preg_replace(array('/\s+/', '/\-+/'), '_', trim(strtolower($_GET['field_name']))));
			$field_label = $this -> Database -> secure_mysql($_GET['field_name']);
			
			if($_GET['field_type'] == 'conf') {
				$field_type = 2;
				$field_table = 'docking_conformations_properties';
				$field_prefix = 'confprop.';
			}
			elseif($_GET['field_type'] == 'mol') {
				$field_type = 1;
				$field_table = 'docking_molecules_properties';
				$field_prefix = 'confprop.';
			}
			elseif($_GET['field_type'] == 'inter') {
				$field_type = 2;
				$field_table = 'docking_conformations_interactions';
				$field_prefix = 'confint.';
			}
			
			if(!empty($field_type)) {
				$query = 'BEGIN;';
				$this -> Database -> query($query);
			
				$query = 'ALTER TABLE '.$this -> project.$field_table.'  ADD `'.$field_name.'` FLOAT NULL,  ADD INDEX (`'.$field_name.'`);';
				if($this -> Database -> query($query)) {
					# get order number
					$query = 'SELECT `order` FROM '.$this -> project.'docking_properties WHERE `type` = '.$field_type.' ORDER BY `order` DESC LIMIT 1';
					$this -> Database -> query($query);
					$row = $this -> Database -> fetch_assoc();
					$order = $row['order'] + 1;
				
					$query = 'INSERT INTO '.$this -> project.'docking_properties (`id` ,`field` ,`name` ,`prefix` ,`description` ,`type` ,`order` ,`sort_asc`) VALUES (NULL ,  "'.$field_name.'",  "'.$field_label.'",  "'.$field_prefix.'",  "", '.$field_type.',  '.$order.',  1)';
					if($this -> Database -> query($query)) {
						$query = 'COMMIT;';
						$this -> Database -> query($query);
				
						if(!IS_AJAX) {
							echo json_encode(array("field_name"=>$field_name,"field_label"=>$field_label));
							exit;	
						}
					}
					else {
						$query = 'ROLLBACK;';
						$this -> Database -> query($query);
					}
				}
				else {
					$query = 'ROLLBACK;';
					$this -> Database -> query($query);
				}
			}
		}
	}
	
	
	protected function update_project() {
		global $CONFIG;
		
		$this -> get_project_db();
		
		$increment = json_decode(file_get_contents('db/increment_sql.json'));
		
#		foreach($increment as $date => $i) {
#			if()
#		}
		
		if($num > 0) {
			$sql_delta = array();
			$this -> project_update = array();
			$this -> project_update['num'] = $num;
			
			#execute firstincrement
			while($row = $this -> Database -> fetch_row()) {
				$sql_delta[] = $row;
			}
			
			if(!empty($_GET['perform_update'])) {
				foreach($sql_delta as $sql) {
					# begin transaction
					$this -> Database -> query('BEGIN;');
					$query = preg_replace('/\{project_prefix\}/', $this -> project, $sql['sql_statement']);

					if($this -> Database -> multi_query($query)) {
						$query = 'UPDATE '.$CONFIG['db_name'].'.docking_project SET db_timestamp = \''.$sql['time'].'\' WHERE id = '.$this -> project_id.';';
						$this -> Database -> query($query);
						$this -> project_update['timestamp'] = $sql['time'];
						$this -> Database -> query('COMMIT;');
					}
					else {
						echo 'bad';
						$this -> project_update['error'] = $sql;
						$this -> Database -> query('ROLLBACK;');
						break;
					}
				}
				
			}
		}
		
		
	}
	
	protected function new_project() {
		global $CONFIG;
	
		if(!empty($_POST['name'])) {
			$this -> Database -> query('BEGIN;');
			# add new project
			$this -> Database -> query('USE '.$CONFIG['db_name'].';');
			$query = 'INSERT INTO '.$CONFIG['db_name'].'.docking_project (`name`, `desc`, `user_id`) VALUES ("'.$this -> Database -> secure_mysql($_POST['name']).'", "'.$this -> Database -> secure_mysql($_POST['desc']).'", '.$this -> User -> id().');';
			if($this -> Database -> query($query)) {
				$new_project_id = $this -> Database -> insert_id();
				#echo $query;
				# add user permissions if necesary
				if($this -> User -> gid() != 1) {
					$query = 'INSERT INTO '.$CONFIG['db_name'].'.docking_project_permitions (`pid`, `uid`) VALUES ('.$new_project_id.', '.$this -> User -> id().');';
					$this -> Database -> query($query);
				}
				
				$query = preg_replace('/\{project_prefix\}/', 'project_'.$new_project_id.'_', file_get_contents('db/project_schema.sql'));
				
				if($this -> Database -> multi_query($query)) {
					$this -> Database -> query('COMMIT;');
					$this -> status = 1;
				}
				else {
					echo 'bad';
					$this -> project_update['error'] = $sql;
					$this -> Database -> query('ROLLBACK;');
				}
			}
		}
	}
	
	public function subset_edit() {	
	}
	
	#####
	
	public function view_new_project() {
		if($this -> status == 1) {
			echo '<div class="alert alert-success">Project <b>'.$_POST['name'].'</b> has beed created.</div>';
		}
		else {
			echo '<form method="POST" action="'.$this -> get_link().'">';
			echo 'Name';
			echo '<input type="text" name="name" class="input" placeholder="Name">';
			echo '</br>';
			echo 'Description:';
			echo '<textarea name="fullname" rows="3"></textarea>';
			echo '</br>';
			
			if(!IS_AJAX) {
				echo '<input type="submit" class="btn" value="Create project">';
			}
			
			echo '<form>';
		}
	}
	
	public function view_update_project() {
		if($this -> project_update['num'] > 0 && empty($_GET['perform_update'])) {
			
			if(empty($this -> project_update['error'])) {
				echo '<form method="POST" action="'.$this -> get_link(array('perform_update' => 1)).'">';
				echo '<div class="alert alert-error">There '.($this -> project_update['num'] > 1 ? 'are <b>'.$this -> project_update['num'].'</b> updates' : 'is <b>one</b> update').' to your project\'s database. Things might be broken if you use old database schema on newer code base. Please note that aplying changes might take some time on large projects.</br>';
				
				if(!IS_AJAX) {
					echo '<input type="submit" class="btn btn-danger" value="Create project">';
				}
				
				echo '</div>';
				echo '<form>';
			}
			elseif($this -> project_update['num'] > 0 && !empty($_GET['perform_update'])) {
			
				echo '<div class="alert alert-success">Successfully updated project\'s database schema to <b>'.$this -> project_update['timestamp'].'</b>.</div>';
			}
			else {
				echo '<div class="alert alert-error">There was an error with SQL update from <b>'.$this -> project_update['error']['time'].'</b>!</br>';
				echo '<div class="whell">'.$this -> project_update['error']['sql_statement'].'</div>';
				echo '</div>';
			}
			
		}
		else {
			echo '<div class="alert alert-success">There is nothing to do here.</div>';
		}
	}
	
	public function view_import_form() {
		# get project name and use it as table name
		$this -> get_project_db();
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		
		
		$dock = !empty($this -> upload_file) ? $this -> get_docking_scores($this -> upload_dir.$this -> upload_file, true) : array();
		
		
		if(empty($this -> mols) || count($dock[0]) - count($_POST['mapping']) > 0) {
			echo '<form method="POST" enctype="multipart/form-data" action="'.$this -> get_link().'">';
			
			#show targets
			echo '<div class="well well-small form-inline">';
			echo '<h4>Target:</h4>';
			$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
			$this -> Database -> query($query);
			echo '<select id="import-target" name="target_id">';
			echo '<option value="-1">New target</option>';
			if(!$target_total_num) {
				$target_total_num = $this -> Database -> num_rows();# count all targets for future use
			}
			while($row = $this -> Database -> fetch_assoc()) {
				$selected = $row['id'] == $_POST['target_id'] ? ' selected' : '';
				echo '<option value="'.$row['id'].'"'.$selected.'>'.$row['name'].'</option>';
			}
			echo '</select>';
			echo '<input type=text name="target_name" value="'.$_POST['target_name'].'">';
			if(empty($this -> target_file)) {
				echo '<input type="file" name="target_file" id="file" />';			
			}
			else {
				echo '<input type=hidden name="target_file_name" value="'.$this -> target_file.'">';
			}
			echo '</div>';

			echo '<div class="well well-small form-inline">';
			echo '<h4>Ligand subset:</h4>';
			$query = 'SELECT id,name FROM '.$this -> project.'docking_ligand_subset;';
			$this -> Database -> query($query);
			echo '<select id="import-ligand-subset" name="ligand_subset">';
			echo '<option value=-1>New ligand subset</option>';
			
			if($this -> Database -> num_rows() > 0) {
				echo '<option value="">### Ligand Subsets ###</option>';
				while($row = $this -> Database -> fetch_assoc()) {
					$selected = $row['id'] == $_POST['ligand_subset'] ? ' selected' : '';
					echo '<option value="'.$row['id'].'"'.$selected.'>'.$row['name'].'</option>';
				}
			}
			echo '</select>';
			echo '<input type=text name="ligand_subset_name" value="'.$_POST['ligand_subset_name'].'">';
			echo '</div>';
			
			if(empty($this -> upload_file)) {
				echo '<div class="well well-small form-inline">';
				echo '<h4>Docked molecules:</h4>';
				echo '<select class="input-small" name="file_format">';
				echo '<option value="mol2">mol2</option>';
				echo '<option value="sdf">sdf</option>';
				echo '<option value="pdb">pdb</option>';
				echo '<option value="pdbqt">pdbqt</option>';
				echo '</select>';
				echo '<input type="file" name="file" id="file" />';
				echo '</div>';
			}
			else {
				echo '<input type=hidden name="file_name" value="'.$this -> upload_file.'">';
				echo '<input type=hidden name="file_format" value="'.$_POST['file_format'].'">';
				echo '<div class="well well-small form-inline">';
				echo '<h4>Scores mapping:</h4>';
				echo '<table class="table table-striped">';
				echo '<tr class="form-inline">';
				echo '<td>';
				echo 'Label';
				echo '</td>';
				echo '<td>';
				echo 'Sample value';
				echo '</td>';
				echo '<td>';
				echo '<i class="icon-arrow-right"></i>';
				echo '</td>';
				echo '<td>';
				echo 'Mapped field';
				echo '</td>';
				echo '</tr>';
				foreach($dock[0] as $c => $val) {
					echo '<tr class="form-inline">';
					echo '<td>';
					echo '<span class="label label-info">'.$c.'</span>';
					echo '</td>';
					echo '<td>';
					echo '<span class="label">'.substr($val,0,20).'</span>';
					echo '</td>';
					echo '<td>';
					echo '<i class="icon-arrow-right"></i>';
					echo '</td>';
					echo '<td>';
					echo '<select name="mapping['.$c.']" class="input-medium">';
					echo '<option value="">Ignore</option>';
					echo '<option value=-1>New Field</option>';
					echo '<option></option>';
	
					foreach ($this -> data_structure as $field) {
						if($field[2] == 0 || $field[2] == 2) {
							echo '<option value='.$field[0].'>'.$field[0].'</option>';
						}
					}
					echo '</select>';
					
					# prepare input for new field
					echo '<input type="text" class="input-small hide" name="field_name" placeholder="Field Name">';
					echo '<button type="button" name="add_field" class="btn btn-success hide"><i class="icon-plus icon-white"></i></button>';
					echo '<div class="alert alert-block hide">It may take some time, if you modify existing project!</div>';
					echo '<div class="progress progress-striped active hide" style="margin: 0px"><div class="bar" style="width: 100%;"></div></div>';
					echo '</td>';
					echo '</tr>';
				}
				echo '</table>';
				echo '</div>';
				# some jquery to control the form
				?>
				<script>
				$(function() {
					$('select[name^=mapping]').change(function() {
						if($(this).val() == -1) {
							$('input[name=field_name]', $(this).closest('td')).show();
							$('button[name=add_field]', $(this).closest('td')).show();
							$('div.alert', $(this).closest('td')).show();
							
						}
						else {
							$('input[name=field_name]', $(this).closest('td')).hide();
							$('button[name=add_field]', $(this).closest('td')).hide();
							$('div.alert', $(this).closest('td')).hide();
						}
					});
					
					$('button[name=add_field]').click(function() {
						var parent_td = $(this).closest('td');
						// hide select and input, then show progress bar
						$('select[name^=mapping]', parent_td).hide();
						$('input[name=field_name]', parent_td).hide();
						$('button[name=add_field]', parent_td).hide();
						$('div.alert', parent_td).hide();
						$('div.progress', parent_td).show();
						// send ajax
						$.get("<?php echo $this->get_link(array('mode' => 'new_field', 'field_type' => 'conf', 'ajax' => '1'), array(), array('project', 'module'))?>",
							{'field_name' : $('input[name=field_name]', parent_td).val()},
							function(data) {
								if(data.field_name) {
									$('select[name^=mapping]', parent_td).append('<option value=' + data.field_name + '>' + data.field_name + '</option>');
									$('select[name^=mapping]', parent_td).val(data.field_name);
								}
								else {
									alert("error");
									$('select[name^=mapping]', parent_td).val('');
								}
							},
							 "json")
						.fail(function() {
							alert("error");
							$('select[name^=mapping]', parent_td).val('');
						})
						.always(function() {
							// cleanup
							$('select[name^=mapping]', parent_td).show();
							$('div.progress', parent_td).hide();
						});
					});
				});
				</script>
				<?php
			}
			
			if(!IS_AJAX) {
				echo '</br>';
				echo '<input type=submit value="Submit file">';
			}
			echo '</form>';
			
			?>
			<script>
			$(function() {
				$('#import-ligand-subset').change(function() {
					if($(this).val() == -1) {
						$('input[name="ligand_subset_name"]').show()
					}
					else{
						$('input[name="ligand_subset_name"]').hide()
						$('input[name="ligand_subset_name"]').val('')
					}
					
				});
				$('#import-target').change(function() {
					if($(this).val() == -1) {
						$('input[name="target_name"]').show()
						$('input[name="target_file"]').show()
					}
					else{
						$('input[name="target_name"]').hide()
						$('input[name="target_name"]').val('')
						$('input[name="target_file"]').hide()
						$('input[name="target_file"]').val('')
					}
					
				});
				$('#import-ligand-subset').trigger('change');
				$('#import-target').trigger('change');
			});
			</script>
			<?php
			
		}
		else {
			# begin transaction
			$query = 'START TRANSACTION;';
			$this -> Database -> query($query);
			
			$dock = $this -> get_docking_scores($this -> upload_dir.$this -> upload_file);
			
			#get target
			if($_POST['target_id'] < 0 && !empty($_POST['target_name']) && is_file($this -> upload_dir.$this -> upload_file) && filesize($this -> upload_dir.$this -> upload_file) > 0) {
				# create new ligand subset
				$query = 'INSERT INTO '.$this -> project.'docking_targets (`project_id`, `name`, `mol2`) VALUES ('.$project.', "'.$this -> Database -> secure_mysql($_POST['target_name']).'", "'.$this -> Database -> secure_mysql(file_get_contents($this -> upload_dir.$this -> target_file)).'")';
				$this -> Database -> query($query);
				# get new or current ligand_subset's id
				$target_id = $this -> Database -> insert_id();
			}
			else {
				$target_id = (int) $_POST['target_id'];
			}
			
			#get ligand subset
			if($_POST['ligand_subset'] < 0 && !empty($_POST['ligand_subset_name'])) {
				# create new ligand subset
				$query = 'INSERT INTO '.$this -> project.'docking_ligand_subset (`name`) VALUES ("'.$this -> Database -> secure_mysql($_POST['ligand_subset_name']).'")';
				$this -> Database -> query($query);
				# get new or current ligand_subset's id
				$ligand_subset = $this -> Database -> insert_id();
			}
			else {
				$ligand_subset = (int) $_POST['ligand_subset'];
			}
			
			#escape strings in mol2 and names
			$sign = 	array("'");
			$escape = 	array("\'"); 
			
			# insert import file into database, for easier managment, and posible re-processing
			$query = 'INSERT INTO '.$this -> project.'docking_conformations_import (`subset`, `time`, `filename`, `file`) VALUES ('.$ligand_subset.', '.time().', "'.$this -> upload_file.'", COMPRESS("'.$this -> Database -> secure_mysql(file_get_contents($this -> upload_dir.$this -> upload_file)).'"))';
			$this -> Database -> query($query);
			# get new or current ligand_subset's id
			$import_id = $this -> Database -> insert_id();
			#free some memory
			unset($query);
			 
			$mapping = !empty($_POST['mapping']) ? array_filter($_POST['mapping']) : array();
			
			$mol_ids = array();
								
			foreach($this -> unique_mols as $mol_keys) { 
				$mol_name = $this -> mols[$mol_keys[0]]['name'];
#				$smiles = $this -> mols[$mol_keys[0]]['smiles'];				
#				$mol_inchi = $this -> mols[$mol_keys[0]]['inchi'];
				$mol2 = $this -> mols[$mol_keys[0]]['mol2'];

				$query = 'SELECT id FROM '.$this -> project.'docking_molecules WHERE name = "'.$mol_name.'" LIMIT 1;';
				$this -> Database -> query($query);
				$row = $this -> Database -> fetch_assoc();
				$mol_id = $row['id'];
				
				if(empty($mol_id)) {
					$OBMol = new OBMol;
					$OBConversion = new OBConversion;
					$OBConversion -> SetInFormat('mol2');
					$OBConversion -> ReadString($OBMol, gzuncompress(substr($mol2, 4)));
					
					$OBConversion -> SetOutFormat('smi');
					$OBConversion -> AddOption("U");
					$OBConversion -> AddOption("n");
					$smiles = trim($OBConversion -> WriteString($OBMol));
		
					$OBConversion -> SetOutFormat('inchikey');
#					$OBConversion -> SetOptions('T"/nochg/nostereo"', $OBConversion::OUTOPTIONS);
					$inchi = preg_split('/[\r\n\s]+/', trim($OBConversion -> WriteString($OBMol)))[0];
					
					$query = 'INSERT INTO '.$this -> project.'docking_molecules (`name`, `smiles`, `inchikey`, `fp2`, `obmol`, `mol2`) VALUES ("'.$mol_name.'", "'.$smiles.'", "'.$inchi.'", FINGERPRINT2(SMILES_TO_MOLECULE("'.$smiles.'")), MOLECULE_TO_SERIALIZEDOBMOL(SMILES_TO_MOLECULE("'.$smiles.'")), "'.$this -> Database -> secure_mysql($mol2).'");';
					$this -> Database -> query($query);
					# get new molecule's ID, store it for future reference
					$mol_id = $this -> Database -> insert_id();
					
					if(empty($mol_id)) {
						echo '<li>PANIC!! - '.$mol_name.' - '.$mol_inchi.'</br>';
						$panic[] = $mol_name;
					}
					unset($OBConversion);
					unset($OBMol);
				}
				
				if(!empty($mol_id)) {
					$mol_ids[] = $mol_id;
					# upload conformations
					foreach($mol_keys as $k => $key) {
						if(!empty($this -> mols[$key]['scores']["name"])) {
							$conf_name = $this -> mols[$key]['scores']["name"].'_'.($k+1);
						}
						else {
							$conf_name = $this -> mols[$key]['name'].'_'.($k+1);
						}
						
						$mol2 = $this -> mols[$key]['mol2'];
	
						$query = 'INSERT INTO '.$this -> project.'docking_conformations (`mol_id`, `target_id`, `ligand_subset`, `name`, `mol2`, `import_id`) VALUES ("'.$mol_id.'", "'.$target_id.'", "'.$ligand_subset.'", \''.str_replace($sign, $escape, $conf_name).'\', \''. $this -> Database -> secure_mysql($mol2).'\', '.(!empty($import_id) ? $import_id : 'NULL').');';
						$this -> Database -> query($query);
						$conf_id = $this -> Database -> insert_id();
					
						# import conformational properties from docking results
						if(!empty($this -> mols[$key]['scores']) && !empty($mapping) && !empty($conf_id)) {
							$conf = array();
							foreach($mapping as $m => $v) {
								$conf[$v] = $this -> mols[$key]['scores'][$m];
							}
							$query = 'INSERT INTO '.$this -> project.'docking_conformations_properties (`id`, `'.implode('`,`', array_keys($conf)).'`) VALUES ("'.$conf_id.'", "'.implode('","', array_values($conf)).'");';
							$this -> Database -> query($query);
						}
					}
					echo '<li>'.$mol_name.' - '.count($mol_keys).' conformations</li>';
				}
			}
			#upload ligand_subset_members
			$ligand_subset_members = array();
			foreach($mol_ids as $mid) {
				$ligand_subset_members[] = '('.$ligand_subset.','.$mid.')';
			}
			
			if(!empty($ligand_subset_members)) {
				$query = 'INSERT IGNORE INTO '.$this -> project.'docking_ligand_subset_members (`ligand_subset_id`, `mol_id`) VALUES '.implode(',', $ligand_subset_members);
				$this -> Database -> query($query);
			
				# commit transaction
				$query = 'COMMIT;';
				$this -> Database -> query($query);
			}
			
			echo 'Uploded '.count($mol_ids).' molecules. '.count($this -> unique_mols).' - '.count($panic).'</br>';
			
			echo memory_get_usage().'|'.memory_get_peak_usage().'</br>';
			echo memory_get_usage(true).'|'.memory_get_peak_usage(true).'</br>';
			
			# remove files
			if(is_file($this -> upload_dir.$this -> target_file)) {
				unlink($this -> upload_dir.$this -> target_file);
			}
			if(is_file($this -> upload_dir.$this -> upload_file)) {
				unlink($this -> upload_dir.$this -> upload_file);
			}
		}
	}
	
	public function view_subset_edit() {
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
			echo '<td><b>Name</b></td>';
			echo '<td><b>Description</b></td>';
			echo '<td><b>Edit</b></td>';
			echo '<td><b>Delete</b></td>';
			echo '</tr>';
			echo '</thead>';
			
			$projects = array();
			$query = 'SELECT id, name, description FROM '.$this -> project.'docking_ligand_subset';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<tr>';
				echo '<td>'.$row['name'].'</td>';
				echo '<td>'.$row['description'].'</td>';
				echo '<td><a class="query_delete_target btn btn-warning btn-mini"><i class="icon-edit icon-white"></i></a></td>';
				echo '<td><a class="query_delete_target btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></a></td>';
				echo '</tr>';
			}
			
			echo '</table>';
		}
	}
}
?>
