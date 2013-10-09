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

class assays extends base {

	public function __construct($mode) {
		$this -> data_structure();
		if(empty($mode)) {
			$mode = 'show';
		}
		parent::__construct($mode);
	}
	
	public function data_structure() { # defines data structure which is fetched from DB and displayed in table
		# Structure goes as follows
		# "name in DB", "print title", "decription", "sortable? (if not set = true)", "db alias (instead of conf.) - for queries"
		$base_structure = array( 
			array ('name', 'Name', 'Name of a molecule', ''),
			array ('smiles', 'SMILES'),
			array ('act_type', 'Activity type'),
			array ('act_operator', 'Operator'),
			array ('act_value', 'Activity Value (Exact)'),
			array ('act_value_lower', 'Activity Value (Lower)'),
			array ('act_value_upper', 'Activity Value (Upper)'),
			array ('comment', 'Comment'),
		);
		
		# hide desired
		foreach ($base_structure as $field) {
			if(!@in_array($field[0], $this -> hide)) {
				$this -> data_structure[] = $field;
			}
		}
		
		# Data for summary page
	}
	
	public function get_field_name($var) {
		foreach($this -> data_structure as $v) {
			if($var == $v[0]) {
				# fallback to conformations if empty
				return !empty($v[1]) ? $v[1] : 'conf.';
			}
		}
	}
	
	public function file_thumbnail($file = null, $size = null) {
		if(empty($file) && !empty($_GET['att_id'])) {
			$query = 'SELECT UNCOMPRESS(file) AS file FROM '.$this -> project.'docking_assays_attachments WHERE id = "'. (int) $_GET['att_id'].'" LIMIT 1;';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			$file = $row['file'];
		}
		if(empty($size) && !empty($_GET['size'])) {
			$size = (int) $_GET['size'];
		}
		else {
			$size = 200;
		}
		if(!empty($file)) {
			$im = new imagick();
			$im->readImageBlob($file);
			$im->setImageIndex(0);
			$im->setImageFormat('png');
			$im->thumbnailImage($size,$size,true);

			if(!empty($_GET['ajax'])) {
				header( "Content-Type: image/png" );
				echo $im;
				exit;
			}
			else {			
				return $im;
			}
		}
		
	}
	
	public function fetch_attachment($file = null, $size = null) {
		if(empty($file) && !empty($_GET['att_id'])) {
			$query = 'SELECT type, UNCOMPRESS(file) AS file FROM '.$this -> project.'docking_assays_attachments WHERE id = "'. (int) $_GET['att_id'].'" LIMIT 1;';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			if(!empty($row['file'])) {
				header( "Content-Type: ".$row['type'] );
				echo $row['file'];
				exit;
			}
		}
		
		
	}
	
	public function show() {
		$sort = !empty($_GET['sort']) ? $this -> Database -> secure_mysql($_GET['sort']) : 'mol_id';
		
		$aid = $this -> Database -> secure_mysql($_GET['aid']);
		if(empty($_GET['aid'])) {
			$query = 'SELECT * FROM '.$this -> project.'docking_assays';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$this -> assays[] = $row;
			}
		}
		else {
			# get assay info
			$query = 'SELECT a.name, a.desc, biotarget.name as biotarget, organism.name as organism, type.name as type_name, target.name as target_name FROM '.$this -> project.'docking_assays AS a 
			LEFT JOIN '.$this -> project.'docking_assays_biotargets as biotarget ON biotarget.id = a.biotarget_id 
			LEFT JOIN '.$this -> project.'docking_assays_organisms as organism ON organism.id = a.organism_id
			LEFT JOIN '.$this -> project.'docking_assays_types as type ON type.id = a.type 
			LEFT JOIN '.$this -> project.'docking_targets as target ON target.id = a.docking_target_id 
			WHERE a.id = '.(int)$_GET['aid'];
			#echo $query.'</br>';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$this -> assay = $row;
			}
			
			# get assay attachments
			$query = 'SELECT id, name, type, UNCOMPRESS(file) AS file FROM '.$this -> project.'docking_assays_attachments AS assay WHERE assay_id = "'. $aid.'";';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$this -> attachments[] = $row;
			}
			
			# get assay size
			$query = 'SELECT count(*) as c FROM '.$this -> project.'docking_assays_data AS assay LEFT JOIN '.$this -> project.'docking_molecules AS  mol ON mol.id = assay.mol_id WHERE assay_id = "'. $aid.'";';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			$this -> result_num = $row[0];
			
			# get molecules for that page  
			$query = 'SELECT mol_id, name AS mol_name, smiles AS smi, assay.* FROM '.$this -> project.'docking_assays_data AS assay LEFT JOIN '.$this -> project.'docking_molecules AS  mol ON mol.id = assay.mol_id WHERE assay_id = "'. $aid.'" ORDER BY ISNULL('.$sort.'), '.$sort.' '.($_GET['sort_type'] == 'desc' ? 'DESC' : 'ASC').' LIMIT '.$this -> offset.','.$this -> per_page.';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$this -> mols[] = $row;
			}
		}	
	}
	
	public function add() {
	$this -> get_project_db();
		if($_FILES["csv_file"]["size"] > 0) {
			$this -> csv_file = file_get_contents($_FILES["csv_file"]["tmp_name"]);
		}
		elseif(!empty($_POST['csv'])) {
			$this -> csv_file = base64_decode($_POST['csv']);
		}
		
		if(!empty($this -> csv_file)) {
			$lines = explode("\n", $this -> csv_file);
			$csv = array();
			
			$delimiter = !empty($_POST['delimiter']) ? $_POST['delimiter'] : ';';
			$enclosure = !empty($_POST['enclosure']) ? $_POST['enclosure'] : '"';
			
			#autodetect delimiter
			$possible_delimiters = array(';', "\t", ',', ' ');
			if($delimiter == 'auto') {
				foreach($possible_delimiters as $d) {
					if(count(str_getcsv($lines[0], $d, $enclosure)) > 1) {
					$delimiter = $d;
					break;	
					}
				}
			}
			
			#fix tab delimiter
			if($delimiter == '\t') {
				$delimiter = "\t";
			}
			foreach($lines as $line) {
				if(!empty($line)) {
					$csv[] = str_getcsv($line, $delimiter, $enclosure);
				}
			}
			$this -> mols = $csv;
			$this -> csv_opt = array('delimiter' => $delimiter, 'enclousure' => $enclosure);
		}
		
		# parse attachments
		if(!empty($_POST['attachment'])) {
			$this -> attachments = $_POST['attachment'];
		}
		if(!empty($_FILES['attachment']['name'])) {
			foreach($_FILES['attachment']['name'] as $key => $name) {
				if($_FILES["attachment"]["size"][$key] > 0) {
					$this -> attachments[] = array($name, $this -> tmp_file($_FILES["attachment"]["tmp_name"][$key]), $_FILES["attachment"]["type"][$key]);
				}
			}
		}
		
		if(!empty($this -> mols)) {
			if(!empty($_POST['type'])) {
				$matchfield_key = array_search($_POST['matchfield'], $_POST['type']);	
				if(!empty($matchfield_key) || $matchfield_key === 0) {
					$this -> mols_match = array();
					
					$first = $_POST['skip_first_line'] ? false : true;
					foreach($this -> mols as $mol) {
						if(!$first) {
							$first = true;
							continue;
						}
						# test matching molecules
						$field = $this -> Database -> secure_mysql($_POST['matchfield']);
						$match = $this -> Database -> secure_mysql($mol[$matchfield_key]);
						# normalize smiles?
	#					if($_POST['matchfield'] == 'smiles') {
	#						$match = $this -> unify_smiles($match);
	#					}
						
	
						$query = 'SELECT `id` AS `mol_id` FROM '.$this -> project.'docking_molecules WHERE `'.$field.'` = \''.$match.'\';';
						#echo $query.'</br>';
						$this -> Database -> query($query);
						$row = $this -> Database -> fetch_row();
						$mol_id = $row['mol_id'];
						
						if(empty($mol_id) && !empty($_POST['commit']) && !empty($mol[array_search('name', $_POST['type'])]) && !empty($mol[array_search('smiles', $_POST['type'])])) {
							$smiles = $this -> Database -> secure_mysql($mol[array_search('smiles', $_POST['type'])]);	
							
							$query = 'INSERT INTO '.$this -> project.'docking_molecules (`name`, `smiles`, `inchikey`, `fp2`, `obmol`) VALUES ("'.$this -> Database -> secure_mysql($mol[array_search('name', $_POST['type'])]).'", "'.$smiles.'", "'.$this -> get_inchikey($smiles, '/nochg/nostereo').'", FINGERPRINT2(SMILES_TO_MOLECULE("'.$smiles.'")), MOLECULE_TO_SERIALIZEDOBMOL(SMILES_TO_MOLECULE("'.$smiles.'")));';
							#echo $query.'</br>';
							$this -> Database -> query($query);
							$mol_id = $this -> Database -> insert_id();
						}
						
						if(!empty($mol_id)) {
							$this -> mols_match[$mol[$matchfield_key]] = $mol_id;
							
							if(!empty($_POST['commit'])) {
								# generate SQL for data import activities
								$act_type_field = array_search('act_type', $_POST['type']);
								$act_operator_field = array_search('act_operator', $_POST['type']);
								$act_value_field = array_search('act_value', $_POST['type']);
								$act_value_lower_field = array_search('act_value_lower', $_POST['type']);
								$act_value_upper_field = array_search('act_value_upper', $_POST['type']);
								$comment_field = array_search('comment', $_POST['type']);
								
								if($mol[$act_operator_field] == '<') {
									$act_operator = -1;	
								}
								elseif($mol[$act_operator_field] == '>') {
									$act_operator = 1;	
								}
								else {
									$act_operator = 0;	
								}
								
								if($act_value_field !== false || $act_value_lower_field !== false && $act_value_upper_field !== false) {
									#create new asssay and get it's
									if(empty($assay_id)) {
										$assay_name = $this -> Database -> secure_mysql($_POST['assay_name']);
										$assay_desc = str_replace('\'', '\\\'', $this -> Database -> secure_mysql($_POST['assay_desc']));
										$assay_type = $this -> Database -> secure_mysql($_POST['assay_type']);
										$organism_id = $this -> Database -> secure_mysql($_POST['organism_id']);
										$biotarget_id = $this -> Database -> secure_mysql($_POST['biotarget_id']);
										$doscking_target_id = $this -> Database -> secure_mysql($_POST['docking_target_id']);
								
										# insert new values entered by user
										if($assay_type <= 0) {
											$query = 'INSERT INTO '.$this -> project.'docking_assays_types (`name`) VALUES (\''.$this -> Database -> secure_mysql($_POST['assay_type_name']).'\');';
											#echo $query.'</br>';
											$this -> Database -> query($query);
											$assay_type = $this -> Database -> insert_id();
										}
										if($biotarget_id <= 0) {
											$query = 'INSERT INTO '.$this -> project.'docking_assays_biotargets (`name`) VALUES (\''.$this -> Database -> secure_mysql($_POST['biotarget_name']).'\');';
											#echo $query.'</br>';
											$this -> Database -> query($query);
											$biotarget_id = $this -> Database -> insert_id();
										}
										if($organism_id <= 0) {
											$query = 'INSERT INTO '.$this -> project.'docking_assays_organisms (`name`) VALUES (\''.$this -> Database -> secure_mysql($_POST['organism_name']).'\');';
											#echo $query.'</br>';
											$this -> Database -> query($query);
											$organism_id = $this -> Database -> insert_id();
										}
								
										$query = 'INSERT INTO '.$this -> project.'docking_assays (`name`, `desc`, `docking_target_id`, `organism_id`, `biotarget_id`, `type`, `csv_file`) VALUES (\''.$assay_name.'\', \''.$assay_desc.'\', \''.$doscking_target_id.'\', \''.$organism_id.'\', \''.$biotarget_id.'\', \''.$assay_type.'\', COMPRESS(\''.$this -> Database -> secure_mysql($_POST['csv']).'\'));';
										#echo $query.'</br>';
										$this -> Database -> query($query);
										$assay_id = $this -> Database -> insert_id();
										
										# insert assays attachments
										foreach($this -> attachments as $attachment) {
											$query = 'INSERT INTO '.$this -> project.'docking_assays_attachments (`assay_id`, `name`, `type`, `file`) VALUES (\''.$assay_id.'\', \''.$attachment[0].'\', \''.$attachment[2].'\', COMPRESS(\''.$this -> Database -> secure_mysql(file_get_contents($attachment[1])).'\'));';
											#echo $query.'</br>';
											$this -> Database -> query($query);
										}
									}
								
									$query = 'INSERT INTO  '.$this -> project.'docking_assays_data (`assay_id`, `mol_id`, `act_type`, `act_operator`, `act_value`, `act_value_lower`, `act_value_upper`, `comment`) VALUES (\''.$assay_id.'\', \''.$mol_id.'\',  \''.($act_type_field !== false ? $mol[$act_type_field] : '').'\',  \''.$act_operator.'\', '.($act_value_field !== false && !empty($mol[$act_value_field]) ? '\''.str_replace(',', '.', $mol[$act_value_field]).'\'' : 'NULL').','.($act_value_lower_field !== false && !empty($mol[$act_value_lower_field]) ? '\''.str_replace(',', '.', $mol[$act_value_lower_field]).'\'' : 'NULL').','.($act_value_upper_field !== false && !empty($mol[$act_value_upper_field]) ? '\''.str_replace(',', '.', $mol[$act_value_upper_field]).'\'' : 'NULL').', \''.($comment_field !== false ? $mol[$comment_field] : '').'\');';
									#echo $query.'</br>';
									if($this -> Database -> query($query)) {
										#count added rows
										$this -> result_num++;
									}
								}
							}
						}
					}
				}
			}
			
		}
	}
	
	##############################
	
	public function view_show() {
		if(empty($_GET['aid'])) {
			echo '<a href="'.$this->get_link(array('mode' => 'add')).'" class="btn btn-success btn-mini"><i class="icon-plus icon-white"></i> Add new assay</a></br></br>';
			if(!empty($this->assays)) {
				echo '<div style="width: auto; margin: 0 auto;">';
				echo '<table class="molecules">';
				$n = 1;
				echo '<tr>';
					echo '<th>#</td>';
					echo '<th>Assay Name</td>';
					echo '</tr>';
				foreach($this->assays as $assay) {
					echo '<tr>';
					echo '<td><a href="'.$this->get_link(array('mode' => 'show', 'aid' => $assay['id'])).'">#'.$n.'</a></td>';
					echo '<td><a href="'.$this->get_link(array('mode' => 'show', 'aid' => $assay['id'])).'">'.$assay['name'].'</a></td>';
					echo '</tr>';
					$n++;
				}
				echo '</table>';
				echo '</div>';
			}
		}
		else {
			
			
			
			echo '<table class="molecules">';
		
			echo '<tr>';
			echo '<td>Assay Name</td>';
			echo '<td>'.$this->assay['name'].'</td>';
			echo '</tr>';
		
			echo '<tr>';
			echo '<td>Type</td>';
			echo '<td>'.$this->assay['type_name'].'</td>';
			echo '</tr>';
		
			echo '<tr>';
			echo '<td>Description</td>';
			echo '<td>'.$this->assay['desc'].'</td>';
			echo '</tr>';
		
			echo '<tr>';
			echo '<td>Biotarget</td>';
			echo '<td>'.$this->assay['biotarget'].'</td>';
			echo '</tr>';
		
			echo '<tr>';
			echo '<td>Docking target</td>';
			echo '<td>'.$this->assay['target_name'].'</td>';
			echo '</tr>';
		
			echo '<tr>';
			echo '<td>Organism</td>';
			echo '<td>'.$this->assay['organism'].'</td>';
			echo '</tr>';
		
			echo '</table>';
		
			#show attachments
			if(!empty($this -> attachments)) {
				echo '<ul>';
				foreach($this -> attachments as $attachment) {
					echo '<li class="media"><a href="'.$this -> get_link(array('mode' => 'fetch_attachment', 'att_id' => $attachment['id']), array(), array('project', 'module')).'"><img class="polaroid" src="'.$this -> get_link(array('mode' => 'file_thumbnail', 'att_id' => $attachment['id'], 'ajax' => 1), array(), array('project', 'module')).'">';
					echo $attachment['name'].'</a>';
					echo '</li>';
				}
				echo '</ul>';
			}
		
			# show pages
			$this -> pagination();	
			# show table
			echo '<table class="molecules"><tr>';
			# get opposite sorting type
			$sort = $_GET['sort'] ? $_GET['sort'] : $_POST['sort'];
			$sort_type = $_GET['sort_type'] ? $_GET['sort_type'] : $_POST['sort_type'];
			
			foreach ($this -> data_structure as $field) {
				if (!@in_array($field[0], $this -> hide)) {				
	
					if ($sort ==  $field[0]) {
						switch($sort_type) {
							case 'asc':
							$sub = array('sort' => $field[0], 'sort_type' => 'desc', 'page' => 1);
							$arrow = '<img src="images/arrow_up.png">';
							break;
							case 'desc':
							$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
							$arrow = '<img src="images/arrow_down.png">';
							break;
						}
					}
					else {
						$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
						$arrow = '';
		
					}
	
	
	
					#to remove?
					# Ad colspan to name 
					if ($field[0] == name) {
						# index field
						echo '<th>#</th>';
						echo '<th><input type="checkbox" onClick="toggleCheckboxes(\'mol_ids[]\', this);"/></th><th>';
					}
					else {
						echo '<th>';
					}			
					echo '<a href="'.$this -> get_link($sub).'" onClick="loading();">'.$field[1].' '.$arrow.'</a></th>';
					echo '</th>';
				}
			}
			echo '</tr>';
			# Print data
			$n = 0; # even or odd
			foreach ($this -> mols as $mol) {
				$style = ($n&1) ? 'odd' : 'even';
				if(is_array($mol)) { # display only existing conformers
					echo '<tr class="'.$style.'" onMouseOver="mouseEventClass('.$mol['mol_id'].', \'hovered\')" onMouseOut="mouseEventClass('.$mol['mol_id'].', \''.$style.'\')"  onClick="toggleSelectionOnRow('.$mol['mol_id'].');">';
					foreach ($this -> data_structure as $field) {
						if ($field[0] == 'name' ) {
							# index field
							echo '<td id="mol-num-'.$mol['mol_id'].'" class="normal">'.((($this -> page - 1) * $this -> per_page)+$n+1).'</td>';
							# show checkbox
							echo '<td id="mol-toggle-'.$mol['mol_id'].'"  class="normal"><input type="checkbox" name="mol_ids[]" value="'.$mol['mol_id'].'" /></td>';
							$img_size_prev = 100; # small IMG size
							$img_size_big = 300;
							echo '<td id="mol-image-'.$mol['mol_id'].'">';
							echo '<a href="'.$this -> get_link(array('module' => 'molecules', 'mode' => 'molecule', 'mol_id' => $mol['mol_id']), array(), array('project', 'module')).'">';
							echo $mol['mol_name'].'</br>';
							echo '<img src="openbabel_ajax.php?smiles='.rawurlencode($mol['smi']).'&output=svg" class="mol" width="'.$img_size_prev.'" height="'.$img_size_prev.'"/></a>';
							echo '</a>';
						
							echo '</td>';
						}
						elseif ($field[0] == 'act_operator' ) {
							switch($mol[$field[0]]) {
								case -1:
								$operator = '<';
								break;
								case -0:
								$operator = '=';
								break;
								case 1:
								$operator = '>';
								break;
							}
							echo '<td>'.$operator.'</td>';
						}
						elseif (@in_array($field[0], $this -> hide)) {
							echo '';
						}
						// round numeric fields 
						elseif (!empty($field[2])) {
							if($sort == $field[0] && ($tid == $_GET['sort_target'] || $top == $tid)) {
								echo '<td><b>'.round($mol[$field[0]], 2).'</b></td>';								
							}
							else {
								echo '<td>'.round($mol[$field[0]], 2).'</td>';
							}
						}
						else {
							echo '<td>';
							if($sort == $field[0] && ($tid == $_GET['sort_target'] || $top == $tid)) {
								echo '<b>'.$mol[$field[0]].'</b>';
							}
							else {
								echo $mol[$field[0]];
							}
							echo '</td>';
						}
					}
					echo '</tr>';
				}
				$n++; #increase odd/even counter
				$namefield = false;
			}
			echo '</tr></table>';	
			# show pages
			$this -> pagination();
		}
	}
	
	public function view_add() {

		if(!empty($this -> result_num)) {
			echo 'Data for '.$this -> result_num.' molecules was updated. <a href="'.$this->get_link(array(), array(),array('project', 'module')).'">Go to arrays list.</a>';
		}
		else {
			echo '<form method="POST" enctype="multipart/form-data" action="'.$this->get_link().'">';
			#draw table with CSV file to assign fields
			
			
			echo '<input type="hidden" name="delimiter" value="'.$this->csv_opt['delimiter'].'">';
			echo '<input type="hidden" name="enclosure" value="'.$this->csv_opt['enclosure'].'">';
			
			echo 'Assay Name: ';
			echo '<input type="text" name="assay_name" value="'.$_POST['assay_name'].'">';
			echo '</br>';
			
			echo 'Assay Description:</br>';
			echo '<textarea name="assay_desc" rows="4" cols="50">'.$_POST['assay_desc'].'</textarea>';
			echo '</br>';
			
			# select assay type
			$query = 'SELECT id, name FROM '.$this -> project.'docking_assays_types';
			#echo $query;
			$this -> Database -> query($query);
			echo 'Assay Type:';
			echo '<select name="assay_type">';
			echo '<option value="-1">New</option>'; # empty option to force selection
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<option value='.$row['id'].' '.($row['id'] == $_POST['assay_type'] ? 'selected' : '').'>'.$row['name'].'</option>';
			}
			echo '</select>';
			echo '<input type="text" class="hide" name="assay_type_name" value="'.$_POST['assay_type_name'].'">';
			echo '</br>';
			
			# select Biotarget
			$query = 'SELECT id, name FROM '.$this -> project.'docking_assays_biotargets';
			#echo $query;
			$this -> Database -> query($query);
			echo 'Assay Target:';
			echo '<select name="biotarget_id">';
			echo '<option value="-1">New</option>'; # empty option to force selection
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<option value='.$row['id'].' '.($row['id'] == $_POST['biotarget_id'] ? 'selected' : '').'>'.$row['name'].'</option>';
			}
			echo '</select>';
			echo '<input type="text" class="hide" name="biotarget_name" value="'.$_POST['biotarget_name'].'">';
			echo '</br>';
			
			# select organism name
			$query = 'SELECT id, name FROM '.$this -> project.'docking_assays_organisms';
			#echo $query;
			$this -> Database -> query($query);
			echo 'Organism Name:';
			echo '<select name="organism_id">';
			echo '<option value="-1">New</option>'; # empty option to force selection
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<option value='.$row['id'].' '.($row['id'] == $_POST['organism_id'] ? 'selected' : '').'>'.$row['name'].'</option>';
			}
			echo '</select>';
			echo '<input type="text" class="hide" name="organism_name" value="'.$_POST['organism_name'].'">';
			echo '</br>';
			
			# select docking target
			$query = 'SELECT id, name FROM '.$this -> project.'docking_targets';
			#echo $query;
			$this -> Database -> query($query);
			echo 'Docking Target:';
			echo '<select name="docking_target_id">';
			echo '<option></option>'; # empty option to force selection
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<option value='.$row['id'].' '.($row['id'] == $_POST['biotarget_id'] ? 'selected' : '').'>'.$row['name'].'</option>';
			}
			echo '</select>';
			echo '</br>';
			
			
			?>
			<script>
			$(function() {
				$('select').change(function() {
					if($(this).val() == -1) {
						$('input[name='+$(this).attr('name').replace('_id','')+'_name]').show()
					}
					else{
						$('input[name='+$(this).attr('name').replace('_id','')+'_name]').hide()
						$('input[name='+$(this).attr('name').replace('_id','')+'_name]').val('')
					}
				});
				
			});
			</script>
			<?php
			
			
			echo '</br>';
			echo '</br>';
			
			echo '<div class="well">';
			echo 'Attachments:</br>';
			if(!empty($this -> attachments)) {
				echo '<ul>';
				foreach($this -> attachments as $key => $attachment) {
					echo '<li>';
					echo '<input type="hidden" name="attachment['.$key.'][0]" value="'.$attachment[0].'">';
					echo '<input type="hidden" name="attachment['.$key.'][1]" value="'.$attachment[1].'">';
					echo '<input type="hidden" name="attachment['.$key.'][2]" value="'.$attachment[2].'">';
					echo $attachment[0].' <button type="button" class="delete_attachment btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></button></br><img src="'.$this -> get_link(array('mode' => 'tmp_file_thumbnail', 'file_name' => basename($attachment[1])), array(), array('project', 'module')).'" class="img-polaroid"></li>';
				}
			}
			echo '<li>';
			echo '<input type="file" name="attachment[]" /> <button type="button" class="delete_attachment btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></button>';
			echo '</li>';
			echo '</ul>';
			echo '</br>';
			echo '</br>';
			echo '<button id="add_attachment" class="btn btn-success" type="button"><i class="icon-file icon-white"></i> Add attachment</button>';
			
			?>
			<script>
				$(function() {
					$('#add_attachment').click(function() {
						var li = $('input[name^=attachment][type=file]').last().closest('li');
						li.after(li.clone(true,true));
					});
					
					$('button.delete_attachment').click(function() {
						if($('input[name^=attachment][type=file]').length > 1 || $('input[type=hidden]', $(this).closest('li')).length > 0) {
							$(this).closest('li').remove();
						}
					});
				});
			</script>
			<?php
			
			echo '</div>';
			
			if(empty($this -> csv_file)) {
				echo 'Import file:';
				echo '<input type="file" name="csv_file" id="csv_file" />';
				echo '<input type=submit class="btn" value="Submit file">';
				echo '</br>';
			}
			else {
				echo '<input type="hidden" name="csv" value="'.base64_encode($this -> csv_file).'">';
			}
			echo 'Delimiter: ';
			echo '<select name="delimiter"><option value="auto">Autodetect</option><option value=";">Semicolon - ;</option><option value=",">Coma - ,</option><option value=" ">Space - " "</option><option value="\t">Tab - "\t"</option></select>';
			echo 'Enclosure: ';
			echo '<select name="enclosure"><option value=\'"\'>"</option></select>';
			echo '</br>';
			
			# options to mach molecules by givven field
			echo 'Match molecules by: ';
			echo '	<select name="matchfield">
					<option value="name" '.($_POST['matchfield'] == 'name' ? 'selected' : '').'>Name</option>
					<option value="smiles" '.($_POST['matchfield'] == 'smiles' ? 'selected' : '').'>SMILES</option>
				</select>';
			echo '<input type="submit" class="btn" value="Apply">';	
			echo '</br>';
			echo '</br>';
			
			echo 'Draw SMILES <input type="checkbox" name="draw_smiles" '.($_POST['draw_smiles'] ? 'checked' : '').' onChange="this.form.submit()">';
			echo '</br>';
			echo 'First line contains headers (skip first line): <input type="checkbox" name="skip_first_line" '.($_POST['skip_first_line'] ? 'checked' : '').'>';
			echo '</br>';
#			echo '<input type="submit" value="Preview changes">';
			
			echo '<input type="hidden" name="commit" value="0">';
			echo '<input type="submit" class="btn btn-large" value="Apply">';	
			echo '<input type="button" class="btn btn-large btn-warning" value="Save changes" onClick="if(confirm(\'Commit data to DiSCuS? Duplicates will be overwritten!\')){this.form.elements[\'commit\'].value=\'1\',this.form.submit()}">';
			echo '</br>';
			echo '</br>';
		}
		
		

		if(empty($this -> result_num) && !empty($this -> mols)) {
			
			# show table
			echo '<table class="molecules"><tr>';
			# get opposite sorting type
			$sort = $_GET['sort'] ? $_GET['sort'] : $_POST['sort'];
			$sort_type = $_GET['sort_type'] ? $_GET['sort_type'] : $_POST['sort_type'];
			echo '<th>#</th>';
			echo '<th>Match status</th>';
			$c = count($this -> mols[0]);
			for($i=0;$i<$c;$i++) {
				echo '<th>';
				if(empty($_POST['type'][$i])) {
					echo '	<select name="type[]" onChange="this.form.submit()"><option value=""></option>';
					foreach($this -> data_structure as $field) {
						if(!in_array($field[0], $_POST['type'])) {
							echo '<option value="'.$field[0].'">'.$field[1].'</option>';
						}
					}
					echo '</select>';
				}
				else {
					echo '<input type="hidden" name="type['.$i.']" value="'.$_POST['type'][$i].'">';
					echo $this -> get_field_name($_POST['type'][$i]);
					
				}
				if(!empty($_POST['type'][$i])) {
					echo ' <button class="btn btn-danger btn-mini" onClick="this.form.elements[\'type['.$i.']\'].value=\'\';this.form.submit()"><i class="icon-trash icon-white"></i></button>';
				}
				echo '</th>';
			}
			echo '</tr>';
			# Print data
			$n = 0; # even or odd
			$matchfield_key = !empty($_POST['type']) ? array_search($_POST['matchfield'], $_POST['type']) : false;
			#skip first line
			$first = $_POST['skip_first_line'] ? false : true;
			foreach ($this -> mols as $row) {
				if(!$first) {
					$first = true;
					continue;
				}				
				$style = ($n&1) ? 'odd' : 'even';
				echo '<tr class="'.$style.'">';
				echo '<td>'.($n+1).'</td>';
				#show match status
				echo '<td>';
				if(!empty($this -> mols_match[$row[$matchfield_key]])) {
					echo '<span class="label label-success">Match found <a href="'.$this -> get_link(array('module' => 'molecules', 'mode' => 'molecule', 'mol_id' => $this -> mols_match[$row[$matchfield_key]]), array(), array('project', 'module')).'"><i class="icon-search icon-white"></i></a></span>';
				}
				#if machfield is not assigned then dont fail
				elseif($matchfield_key === false ) {
					echo '-';
				}
				elseif(array_search('name', $_POST['type']) !== false && array_search('smiles', $_POST['type']) !== false) {
					echo '<span class="label label-warning">New molecule</span>';
				}
				else {
					echo '<span class="label label-important">Fail</span>';
				}
				echo '</td>';
				foreach ($row as $key => $value) {
					if($_POST['type'][$key] == 'smiles' && $_POST['draw_smiles']) {
					#draw SVG
						$img_size_prev = 100;
						echo '<td>';
						echo '<img src="openbabel_ajax.php?smiles='.rawurlencode($value).'&output=svg" class="mol" width="'.$img_size_prev.'" height="'.$img_size_prev.'"/></td>';
					}
					else {
						echo '<td>'.$value.'</td>';
					}
				}
				echo '</tr>';
				$n++; #increase odd/even counter
				$namefield = false;
			}
			echo '</tr></table>';
			echo '</form>';

		}
	}

}
?>
