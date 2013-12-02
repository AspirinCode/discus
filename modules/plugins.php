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

class plugins extends base {
	
	public function compute() {
		$this -> plugin_name = $_GET['plugin_name']; # need to escape directories
		if($_POST['confirm'] && !empty($this -> plugin_name)) {
			$this -> plugin_class_name = 'plugin_'.$this -> plugin_name;
			$this -> plugin = new $this -> plugin_class_name();
		}
	}
	
	public function view_compute() {
		$plugin_class_name = $this -> plugin_class_name;
		
		if($_POST['confirm']) {
			# check if all molecular fields are present in DB
			if(is_array($plugin_class_name::$result['mol'])) {
				foreach($plugin_class_name::$result['mol'] as $c => $f) {
					if(!empty($f['field_label'])) {
						$query = 'SELECT *  FROM '.$this -> project.'docking_properties WHERE `field` = "'.$f['field_name'].'" AND `type` = 1 LIMIT 1;';
						$this -> Database -> query($query);
						#create field if necessary
						if($this -> Database -> num_rows() == 0) {
							# get field order
							$query = 'SELECT `order` FROM '.$this -> project.'docking_properties WHERE `type` = 1 ORDER BY `order` DESC LIMIT 1';
							$this -> Database -> query($query);
							$row = $this -> Database -> fetch_assoc();
							$order = $row['order'] + 1;
					
							$query = 'ALTER TABLE '.$this -> project.'docking_molecules_properties  ADD `'.$f['field_name'].'` FLOAT NULL,  ADD INDEX (`'.$f['field_name'].'`);';
							$this -> Database -> query($query);
							
							$query = 'INSERT INTO '.$this -> project.'docking_properties (`id` ,`field` ,`name` ,`prefix` ,`description` ,`type` ,`order` ,`sort_asc`) VALUES (NULL ,  "'.$f['field_name'].'",  "'.$f['field_label'].'",  "'.$f['prefix'].'",  "", 1,  '.$order.',  1)';
							$this -> Database -> query($query);
						}
					}
				}
			}
			# check if all conformational fields are present in DB
			if(is_array($plugin_class_name::$result['conf'])) {
				foreach($plugin_class_name::$result['conf'] as $c => $f) {
					if(!empty($f['field_label'])) {
						$query = 'SELECT *  FROM '.$this -> project.'docking_properties WHERE `field` = "'.$f['field_name'].'" AND `type` = 2 LIMIT 1;';
						$this -> Database -> query($query);
						#create field if necessary
						if($this -> Database -> num_rows() == 0) {
							# get field order
							$query = 'SELECT `order` FROM '.$this -> project.'docking_properties WHERE `type` = 2 ORDER BY `order` DESC LIMIT 1';
							$this -> Database -> query($query);
							$row = $this -> Database -> fetch_assoc();
							$order = $row['order'] + 1;
					
							$query = 'ALTER TABLE '.$this -> project.'docking_conformations_properties  ADD `'.$f['field_name'].'` FLOAT NULL,  ADD INDEX (`'.$f['field_name'].'`);';
							$this -> Database -> query($query);
							
							$query = 'INSERT INTO '.$this -> project.'docking_properties (`id` ,`field` ,`name` ,`prefix` ,`description` ,`type` ,`order` ,`sort_asc`) VALUES (NULL ,  "'.$f['field_name'].'",  "'.$f['field_label'].'",  "'.$f['prefix'].'",  "", 2,  '.$order.',  1)';
							$this -> Database -> query($query);
						}
					}
				}
			}
		
			# generate input for plugin
			$ligands = null;
			$receptor = null;
			
			if($plugin_class_name::$input['ligands'] != 'pointer') {
				$ligand_subset = (int) $_GET['ligand-subset'];
				$target_id = (int) $_GET['target_id'];
				
				
				# setup converters
				$OBConversion = new OBConversion;
				
				# get limits for current batch
				$batch = (int) $_POST['batch'];
				$batch_size = (int) $this -> plugin -> batch_size;
				
				# get ligands
				$OBConversion->SetInAndOutFormats("mol2", $plugin_class_name::$input['ligands']);
				$query = 'SELECT conf.id, conf.mol_id, UNCOMPRESS(conf.mol2) AS mol2 FROM '.$this -> project.'docking_conformations AS conf WHERE mol2 IS NOT NULL AND conf.target_id = '.$target_id.(!empty($ligand_subset) ? ' AND conf.ligand_subset = '.$ligand_subset : '').(empty($plugin_class_name::$result['conf']) ? ' GROUP BY conf.mol_id' : '').($batch_size > 0 && !empty($batch) ? ' LIMIT '.(($batch-1)*$batch_size).','.((($batch)*$batch_size)) : '').';';
				#echo $query.'</br>';
				$this -> Database -> query($query);
				while($row = $this -> Database -> fetch_assoc()) {
					# check if object can be reused
					if(strtolower($plugin_class_name::$input['ligands']) == 'obmol') {
						$OBMol = new OBMol;
					}
					else {
						if(!is_object($OBMol)) {
							$OBMol = new OBMol;
						}
					}
					$OBConversion->ReadString($OBMol, $row['mol2']);
					
					#change the name
					$OBMol -> SetTitle($row['id'].'|'.$row['mol_id']);
					
					if(strtolower($plugin_class_name::$input['ligands']) == 'obmol') {
						$ligands[$row['id'].'|'.$row['mol_id']] = $OBMol;
					}
					else {
						$ligands[$row['id'].'|'.$row['mol_id']] = $OBConversion->WriteString($OBMol);
					}
				}
				
				# get receptor
				$OBConversion->SetInAndOutFormats("mol2", $plugin_class_name::$input['receptor']);
				if(!empty($plugin_class_name::$input['receptor']) && !empty($target_id)) {
					$query = 'SELECT mol2 FROM '.$this -> project.'docking_targets AS conf WHERE mol2 IS NOT NULL AND id = '.$target_id.' LIMIT 1;';
					#echo $query.'</br>';
					$this -> Database -> query($query);
					$row = $this -> Database -> fetch_assoc();
					if(strtolower($plugin_class_name::$input['receptor']) == 'mol2') {
						$receptor = $row['mol2'];
					}
					elseif(strtolower($plugin_class_name::$input['receptor']) == 'obmol') {
						$rec = new OBMol;
						$OBConversion->ReadString($rec, $row['mol2']);
						$receptor = $rec;
					}
					else {
						$rec = new OBMol;
						$OBConversion->ReadString($rec, $row['mol2']);
						$receptor = $OBConversion->WriteString($rec);
						# destroy object
						unset($rec);
					}
				}
			}
			else {
				if(!empty($_GET['target_id'])) {
					$ligands['target_id'] = (int) $_GET['target_id'];
				}
				if(!empty($_GET['ligand_subset'])) {
					$ligands['ligand_subset'] = (int) $_GET['ligand_subset'];
				}
			}
			
			if(!empty($ligands)) {
				# compute plugin
				$results = $this -> plugin -> compute($ligands, $receptor);
			
				# upload plugin results to DB
				if(!empty($results)) {
					# begin transaction
					$query = 'BEGIN;';
					$this -> Database -> query($query);
				
					# iterate through results
					$conf_sql = array();
					$mol = array();
					foreach($results as $key => $values) {
						$tmp = explode('|', $key);
						$conf_id = $tmp[0];
						$mol_id = $tmp[1];
					
						$out = array();
						$out[] = '"'.$conf_id.'"';
						foreach($plugin_class_name::$result['conf'] as $f) {
							$out[] = '"'.$values[$f['field_name']].'"';
						}
						$conf_sql[] = '('.implode(',', $out).')';
						# extract molecular
						foreach($plugin_class_name::$result['mol'] as $f) {
							$mol[$mol_id][$f['field_name']] = $values[$f['field_name']];
						}
					}
				
					# get conf fileds list
					if(!empty($plugin_class_name::$result['conf'])) {
						$fields = array();
						$update_fields = array();
						$fields[] = '`id`';
						foreach($plugin_class_name::$result['conf'] as $f) {
							$fields[] = '`'.$f['field_name'].'`';
							$update_fields[] = '`'.$f['field_name'].'` = VALUES(`'.$f['field_name'].'`)';
						}
				
						# upload conformational
						$query = 'INSERT INTO '.$this -> project.'docking_conformations_properties ('.implode(',',  $fields).') VALUES '.implode(',',  $conf_sql).' ON DUPLICATE KEY UPDATE '.implode(',',  $update_fields).';';
						#echo $query.'</br>';
						$this -> Database -> query($query);
					}
				
					# iterate through mol
					$mol_sql = array();
					foreach($mol as $mol_id => $values) {
						$out = array();
						$out[] = '"'.$mol_id.'"';
						foreach($plugin_class_name::$result['mol'] as $f) {
							$out[] = '"'.$values[$f['field_name']].'"';
						}
						$mol_sql[] = '('.implode(',', $out).')';
					}
				
					# get mol fileds list
					if(!empty($plugin_class_name::$result['mol'])) {
						$fields = array();
						$update_fields = array();
						$fields[] = '`id`';
						foreach($plugin_class_name::$result['mol'] as $f) {
							$fields[] = '`'.$f['field_name'].'`';
							$update_fields[] = '`'.$f['field_name'].'` = VALUES(`'.$f['field_name'].'`)';
						}
				
						# upload molecular
						$query = 'INSERT INTO '.$this -> project.'docking_molecules_properties ('.implode(',',  $fields).') VALUES '.implode(',',  $mol_sql).' ON DUPLICATE KEY UPDATE '.implode(',',  $update_fields).';';
						#echo $query.'</br>';
						$this -> Database -> query($query);
					}
				
					$query = 'COMMIT;';
					$this -> Database -> query($query);
					if(!empty($batch_size)) {
						echo '<form id="plugin_form" method="POST" action="'.$this -> get_link().'">';
						echo '<div class="alert">Completed '.($batch*$batch_size).'</div>';
						echo '<input type="hidden" name="batch" value="'.($batch+1).'">';
						echo '<input type="hidden" name="timeout" value="1000">'; #timeout for autoreload [in ms]
						echo '<input type="hidden" name="confirm" value="1">';
						if(!IS_AJAX) {
							echo '<button class="btn btn-warning">Continue next step</button>';
							?>
							<script>
							$(function() {
								setTimeout(function() {
									$('#plugin_form').submit()
								}, 3000);
							});
							</script>
							<?php
						}
						echo '</form>';
					}
					else {
						echo '<div class="alert alert-success">Plugin execution completed successfully</div>';
					}
				}
			}
			else {
				echo '<div class="alert alert-success">Plugin execution completed successfully</div>';
			}
		}
		else {
			echo '<form method="POST" action="'.$this -> get_link().'">';
			echo '<div class="alert">Confirm plugin execution</div>';
			echo '<input type="hidden" name="batch" value="1">';
			echo '<input type="hidden" name="confirm" value="1">';
			if(!IS_AJAX) {
				echo '<button class="btn btn-warning">Confirm</button>';
			}
			echo '</form>';
		}
		
	}
}
?>
