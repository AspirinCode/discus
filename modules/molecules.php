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

class molecules extends base {
	private $mols = array(); # contains molecular information, connects model -> viewer
	private $hide = array();
	public $mode = null;
	private $target = array();
	private $rarget_names = array();
	private $sorting = array();
	private $num = 0;
	private $filename = null; #placeholder for passing some filenames
	private $comp = array (
				'eq' => '=',
				'le' => '<=',
				'lt' => '<',
				'ge' => '>=',
				'gt' => '>',
				'like' => 'LIKE',
			); # how to translate logic signs
	

	public function __construct($mode) {
		global $Database;
		$this -> Database = $Database;
		
		#define some global variables			`name`	`label`	`has_precision_mode`(bool)
		$this -> interaction_types = array(	array('hbond_donor', 'H-bond Donors' , 	1),
							array('hbond_acceptor', 'H-bond Acceptors', 1),
							array('salt_bridges', 'Salt Bridges', 0),
							array('hydrophobic_contacts', 'Hydrophobic contacts', 0),
							array('pi_stacking', 'Pi Stacking', 0),
							array('pi_cation', 'Pi-Cation', 0),
							array('metal_coordination', 'Metal Coordination', 1)
							);
		
		if(empty($mode)) {
			$mode = 'form';
		}			
		parent::__construct($mode);
	}
	
	public function gen_targets() {
		global $CONFIG;
		
		if(count(@array_unique(@array_filter($_GET['var_target']))) > 0) {
			foreach(array_unique(@array_filter($_GET['var_target'])) as $key => $t) {
				if($_GET['var_target_logic'][$key] != 'BUTNOT') {
					$this -> target[] = $t;
				}
			}
		}
		elseif(count(@array_unique(@array_filter($_GET['target']))) > 0) {
			$this -> target = array_unique(@array_filter($_GET['target']));
		}
		$project = (int) $this -> Database -> secure_mysql($_GET['project']);
		if(empty($this -> target) || in_array(-1,$this -> target)) {
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
	
	# Generate vars for SQL query. It consists of all query terms with or without targets. Subsets are not speciefied since it needs join.
	public function gen_vars($gen_targets = true, $prefix = '') {
		if(!empty($_GET['var_target'])) {
			foreach($_GET['var_target'] as $t_key => $t) {
				$target_id = (int) $t;
				$var .= $this -> get_target_vars($t_key, $prefix);
			}
		}
		elseif (!empty($_GET['var'])) {
			foreach($_GET['var'] as $t_key => $t) {
				$var .= $this -> get_target_vars($t_key, $prefix);
			}
		}

		# remove first logic operator
		return preg_replace('/^ (AND|OR)/', '', $var);
	}
	
	#Generate SQL vars for certain target
	public function get_target_vars($t_key, $prefix = '') {
		$var_target = '';
		$target_id = (int) $_GET['var_target'][$t_key];
		# generate terms for target
		if(!empty($_GET['var'][$t_key])) {
			foreach ($_GET['var'][$t_key] as $key => $value) {
				if(!empty($_GET['var'][$t_key][$key]) && (!empty($_GET['var_val'][$t_key][$key]) || $_GET['var_val'][$t_key][$key] === "0" || $_GET['var_val'][$t_key][$key] === 0)) {
					if($_GET['var'][$t_key][$key] == 'name' || $_GET['var_comp'][$t_key][$key] == 'like') {
						$var_tmp = $this -> get_var_prefix($value).$this -> Database -> secure_mysql($_GET['var'][$t_key][$key]).' REGEXP "'.(preg_match('/\^/', $_GET['var_val'][$t_key][$key]) ? '' : '.*').$this -> Database -> secure_mysql($_GET['var_val'][$t_key][$key]).(preg_match('/\$/', $_GET['var_val'][$t_key][$key]) ? '' : '.*').'"';
					}
					else {
						$var_tmp = $this -> get_var_prefix($value).$this -> Database -> secure_mysql($_GET['var'][$t_key][$key]).$this -> comp[$_GET['var_comp'][$t_key][$key]].$this -> Database -> secure_mysql($_GET['var_val'][$t_key][$key]).' AND  '.$this -> get_var_prefix($value).$this -> Database -> secure_mysql($_GET['var'][$t_key][$key]).' IS NOT NULL';
					}
				}
				if(!empty($var_tmp)) {
					if($_GET['var_logic'][$t_key][$key] == 'BUTNOT') {
						$var_target .= ' AND NOT('.$var_tmp.')';
					}
					elseif($_GET['var_logic'][$t_key][$key] == 'AND') {
						$var_target .= ' AND '.$var_tmp.'';
					}
					elseif($_GET['var_logic'][$t_key][$key] == 'OR') {
						$var_target .= ' OR '.$var_tmp.'';
					}
				}
			}
		}
	
		# check if target term is necessary
		if(!empty($target_id) && $target_id > 0) {
			$var_target .= (!empty($var_target) ? ' AND ' : '').$prefix.'target_id = '.$target_id;
		}
	
		
		if(!empty($var_target)) {
			# remove first logic operator
			$var_target = preg_replace('/^ (AND|OR)/', '', $var_target);
			
#			# include target logic
#			if($_GET['var_target_logic'][$t_key] == 'BUTNOT') {
#				$var_target .= ' OR ('.$var_target.')';
#			}
#			elseif($_GET['var_target_logic'][$t_key] == 'AND') {
#				$var_target .= ' OR ('.$var_target.')';
#			}
#			elseif($_GET['var_target_logic'][$t_key] == 'OR') {
#				$var_target .= ' OR ('.$var_target.')';
#			}
			#fallback - target logic is in other place
			$var_target = ' OR ('.$var_target.')';
		}
		
		return $var_target;
	}
	
	public function gen_sorting($prefix = '') {
		$this -> sorting = array();
		switch ($_GET['sort_type']) {
			case 'asc':
			$sort_asc = $this -> Database -> secure_mysql($_GET['sort']);
			break;
			case 'desc':
			$sort_desc = $this -> Database -> secure_mysql($_GET['sort']);
			break;
		}
		
		if(!$sort_asc && !$sort_desc) {
			$sort_asc = 'name';
			$prefix = 'conf.';
		}
		
		
		if($sort_asc) {
			$this -> sorting['sort'] = 'ISNULL('.$sort_asc.'), '.$sort_asc.' ASC';
			$this -> sorting['sort_field'] = $sort_asc;
			$this -> sorting['sort_group'] = 'ISNULL(MIN('.$sort_asc.')), MIN('.$sort_asc.') ASC';
			$this -> sorting['sort_group_field'] = 'MIN('.$sort_asc.')';
			if(!empty($prefix)) {
				$this -> sorting['prefix_sort'] = 'ISNULL('.$prefix.$sort_asc.'), '.$prefix.$sort_asc.' ASC';
				$this -> sorting['prefix_sort_field'] = $prefix.$sort_asc;
				$this -> sorting['prefix_sort_group'] = 'ISNULL(MIN('.$prefix.$sort_asc.')), MIN('.$prefix.$sort_asc.') ASC';
				$this -> sorting['prefix_sort_group_field'] = 'MIN('.$prefix.$sort_asc.')';
			}
		}
		elseif($sort_desc) {
			$this -> sorting['sort'] = 'ISNULL('.$sort_desc.'), '.$sort_desc.' DESC';
			$this -> sorting['sort_field'] = $sort_desc;
			$this -> sorting['sort_group'] = 'ISNULL(MAX('.$sort_desc.')), MAX('.$sort_desc.') DESC';
			$this -> sorting['sort_group_field'] = 'MAX('.$sort_desc.')';
			if(!empty($prefix)) {
				$this -> sorting['prefix_sort'] = 'ISNULL('.$prefix.$sort_desc.'), '.$prefix.$sort_desc.' DESC';
				$this -> sorting['prefix_sort_field'] = $prefix.$sort_desc;
				$this -> sorting['prefix_sort_group'] = 'ISNULL(MAX('.$prefix.$sort_desc.')), MAX('.$prefix.$sort_desc.') DESC';
				$this -> sorting['prefix_sort_group_field'] = 'MAX('.$prefix.$sort_desc.')';
			}
		}
		
		if($sort_asc == "name" || $sort_desc == "name") {
			if($sort_desc) {
				$type = 'DESC';
			}
			else {
				$type = 'ASC';
			}
			$this -> sorting['sort'] = 'LENGTH(name) '.$type.', name '.$type;
			$this -> sorting['sort_group'] = 'LENGTH(MAX(name)) '.$type.', MAX(name) '.$type;
			if(!empty($prefix)) {
				$this -> sorting['prefix_sort'] = 'LENGTH('.$prefix.'name) '.$type.', '.$prefix.'name '.$type;
				$this -> sorting['prefix_sort_group'] = 'LENGTH(MAX('.$prefix.'name)) '.$type.', MAX('.$prefix.'name) '.$type;
			}
		}
		
		if(!empty($_GET['sort_target'])) {
			$this -> sorting['target_id'] = (int) $_GET['sort_target'];
		}
	}
	
	
	public function search_sql($disable_mol_grouping = false) { # carry out the searching from your request
		global $CONFIG;
		# get project name and use it as table name
		$this -> get_project_db();
		
		$this -> hide = $this -> Database -> secure_mysql($_GET['hide'] ? $_GET['hide'] : $_POST['hide']);
		# prepare targets
		$this -> gen_targets();
		$this -> gen_sorting();
		
		#check for grouping
		if(!empty($_GET['disable_mol_grouping'])) {
			$disable_mol_grouping = true;
		}
		
		# add mol_id for future reference
		$fields[] = 'conf.mol_id';
		# add target_id for future reference
		$fields[] = 'target.name as target_name';
		# set what fields to sellect
		foreach ($this -> data_structure as $field) { 
			if(!@in_array($field[0], $this -> hide)) {
				$fields[] = ( $field[3] ? $field[3] : 'conf.' ).$field[0];
			}
		}
		
		
			
		# switch between modes
		if($_GET['similarity_mol_id'] || $_GET['smiles']) {
			
			$similarity_mol_id = (int) $_GET['similarity_mol_id'];
			$similarity_smiles = $this -> Database -> secure_mysql($_GET['smiles']);
			$smarts = $this -> Database -> secure_mysql($_GET['smiles']);
			$smarts_rep = !empty($_GET['smarts_rep']) ? (int) $_GET['smarts_rep'] : 1;
			
			# Multi-target "OR"
			#$vars = $this -> gen_vars(true);
			
			# get subset
			if(!empty($_GET['subset'])) {
				$subset_tmp = explode('-', $_GET['subset']);
				if($subset_tmp[0] == 'ligand') {
					$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
				elseif($subset_tmp[0] == 'user') {
					$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
			}
		
			if(!empty($ligand_subset)) {
				#$ligand_subset_join = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = mol.id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
				$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
			}
			if(!empty($user_subset)) {
				$subset_join = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = mol.id AND user_subset.user_subset_id = '.$user_subset;
			}
			
			
			if($_GET['similarity_mol_id'] || $_GET['similarity'] != 'sub') {
				# get query mol
				if(!empty($similarity_mol_id)) {
					$query = 'SELECT FINGERPRINT(SMILES_TO_MOLECULE(smiles), "FP2") as fp FROM '.$this -> project.'docking_molecules WHERE id = '.$similarity_mol_id.' LIMIT 1;';
				}
				elseif(!empty($similarity_smiles)) {
					$query = 'SELECT FINGERPRINT(SMILES_TO_MOLECULE("'.$similarity_smiles.'"), "FP2") as fp;';
				}
				else {
					$query = '';
				}
				#echo $query.'</br>';
				$this -> Database -> query($query);
				$row = $this -> Database -> fetch_assoc();
				$query_fp = $row['fp'];
				$tanimoto_cutoff = (float) $_GET['similarity'];
			
				# Query for all data to let them be cached
				$query = 'SELECT conf.id, mol.id as mol_id, TANIMOTO("'.str_replace('"', '\"', $query_fp).'", fp2) as t FROM '.$this -> project.'docking_molecules as mol '.(!$disable_mol_grouping ? 'LEFT' : 'RIGHT').' JOIN '.$this -> project.'docking_conformations as conf ON mol.id = conf.mol_id '.$ligand_subset_join.' '.$subset_join.' WHERE conf.target_id IN ('.implode(',',$this -> target).') AND mol.fp2 IS NOT NULL '.( !empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '').' '.(!$disable_mol_grouping ? 'GROUP BY conf.mol_id' : '').' HAVING t > '.$tanimoto_cutoff.' ORDER BY t DESC';
			}
			elseif($_GET['similarity'] == 'sub') {
				$query = 'SELECT conf.id, mol.id as mol_id, SUBSTRUCT_COUNT("'.$smarts.'", obmol) as c FROM '.$this -> project.'docking_molecules as mol '.(!$disable_mol_grouping ? 'LEFT' : 'RIGHT').' JOIN '.$this -> project.'docking_conformations as conf ON mol.id = conf.mol_id '.$ligand_subset_join.' '.$subset_join.' WHERE SUBSTRUCT_COUNT("'.$smarts.'", obmol) >= '.$smarts_rep.' '.( !empty($sql_var) ? ' AND '.implode('AND ', $sql_var) : '').' '.(!$disable_mol_grouping ? 'GROUP BY conf.mol_id' : '').' ORDER BY c DESC, mol.name ASC';
			}
			
		}
		# do the normal search
		else {
			# get subset
			if(!empty($_GET['subset'])) {
				$subset_tmp = explode('-', $_GET['subset']);
				if($subset_tmp[0] == 'ligand') {
					$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
				elseif($subset_tmp[0] == 'user') {
					$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
			}
		
			if(!empty($ligand_subset)) {
				$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
#				$sql_join[] = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
			}
			if(!empty($user_subset)) {
				$sql_join[] = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
			}
			
			# get query terms
			$tmp_vars = $this -> gen_vars(true, 'conf.');
			if(!empty($tmp_vars)) {
				$sql_var[] = '('.$tmp_vars.')';
			}
			
			# figure out how to sort
			if(!empty($_GET['sort_target']) && !$disable_mol_grouping) {
				$this -> gen_sorting('sort.');
				if($this -> get_var_prefix($_GET['sort']) == 'molprop.') {
					$sql_join[] = 'JOIN '.$this -> project.'docking_molecules_properties AS sort ON sort.id = conf.mol_id';
				}
				elseif($this -> get_var_prefix($_GET['sort']) == 'confprop.') {
					$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_properties AS sort ON sort.id = conf.id AND conf.target_id = '.(int) $_GET['sort_target'];
					#$sql_var[] = 'conf.target_id = '.(int) $_GET['sort_target'];
				}
				elseif($this -> get_var_prefix($_GET['sort']) == 'confint.') {
					$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_interactions AS sort ON sort.id = conf.id AND conf.target_id = '.(int) $_GET['sort_target'];
					#$sql_var[] = 'conf.target_id = '.(int) $_GET['sort_target'];
				}
				else {
					$sql_join[] = 'JOIN '.$this -> project.'docking_conformations AS sort ON sort.id = conf.id AND sort.target_id = '.(int) $_GET['sort_target'];
					#$sql_var[] = 'sort.'.$this -> Database -> secure_mysql($_GET['sort']).' IS NOT NULL';
				}
			}
			else {
				
				$this -> gen_sorting($this -> get_var_prefix($_GET['sort']));
			}
			
			if(!empty($sql)) {
				$sql_var[] = '('.implode(' OR ',$sql).')';
			}
			
			# evaluate logic between targets if any
			if(count($_GET['var_target']) > 1) {
				$var_target = '';
				foreach($_GET['var_target'] as $t_key => $t) {
					if($t > 0) {
						if($_GET['var_target_logic'][$t_key] == 'BUTNOT') {
							$var_target .= ' AND FIND_IN_SET('.(int)$t.', GROUP_CONCAT(DISTINCT conf.target_id)) = 0';
						}
						elseif($_GET['var_target_logic'][$t_key] == 'AND') {
							$var_target .= ' AND FIND_IN_SET('.(int)$t.', GROUP_CONCAT(DISTINCT conf.target_id))';
						}
						elseif($_GET['var_target_logic'][$t_key] == 'OR') {
							$var_target .= ' OR FIND_IN_SET('.(int)$t.', GROUP_CONCAT(DISTINCT conf.target_id))';
						}
					} 
					
				}
				if(!empty($var_target)) {
					# remove first logic operator
					$var_target = preg_replace('/^ (AND|OR)/', '', $var_target);
					$having_join[] = $var_target;
				}
			}
			
			# figure out if wee need joins
			if(!empty($_GET['var'])) {
				foreach($_GET['var'] as $vars) {
					foreach($vars as $var) {
						$prefixes[] = $this -> get_var_prefix($var);
					}
				}
			}
			$prefixes[] = $this -> get_var_prefix($_GET['sort']);
			$prefixes = array_unique($prefixes);
			if(in_array('molprop.',$prefixes)) {
				$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id';
			}
			if(in_array('confprop.',$prefixes)) {
				$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id';
			}
			if(in_array('confint.',$prefixes)) {
				$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id';
			}
			
			# remove empty vars
			#$sql_var = array_filter($sql_var);
			# check if conf's souuld be grouped - defeault is to group them
			if($disable_mol_grouping && count($_GET['var_target']) <= 1) {
				$query = 'SELECT conf.id, conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.(!empty($sql_join) ? implode(' ', $sql_join) : '').' '.( !empty($sql_var) ? 'WHERE '.implode(' AND ', $sql_var) : '').' ORDER BY '.$this -> sorting['prefix_sort'];
			}
			#catch disable grouping for multitarget
			elseif($disable_mol_grouping) {
				$query = 'SELECT conf.id, conf.mol_id FROM '.$this -> project.'docking_conformations AS conf JOIN (SELECT SUBSTRING_INDEX(GROUP_CONCAT(conf.id ORDER BY '.$this -> sorting['prefix_sort'].'), ",", 1) AS id, conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.(!empty($sql_join) ? implode(' ', $sql_join) : '').' '.( !empty($sql_var) ? 'WHERE '.implode(' AND ', $sql_var) : '').'  GROUP BY conf.mol_id '.(!empty($having_join) ? 'HAVING '.implode(' ', $having_join) : '').' ORDER BY '.$this -> sorting['prefix_sort_group'].') as temp USING (mol_id) ORDER BY '.$this -> sorting['prefix_sort'];
			}
			else {
				$query = 'SELECT SUBSTRING_INDEX(GROUP_CONCAT(conf.id ORDER BY '.$this -> sorting['prefix_sort'].'), ",", 1) AS id, conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.(!empty($sql_join) ? implode(' ', $sql_join) : '').' '.( !empty($sql_var) ? 'WHERE '.implode(' AND ', $sql_var) : '').'  GROUP BY conf.mol_id '.(!empty($having_join) ? 'HAVING '.implode(' ', $having_join) : '').' ORDER BY '.$this -> sorting['prefix_sort_group'];
			}
		}
		
		if($_GET['limit'] > 0) {
			$query = $query.' LIMIT '.((int) $_GET['limit']);
		}
		
		return $query;
	}
	
	public function cheminformatics() {
		$this -> search();
	}
	
	public function search() { # carry out the searching from your request
		#query generation is moved out to another function to capture all ids from search
		$query = $this -> search_sql();
		#echo $query.'</br>';
		if(!empty($query)) {
			$this -> Database -> query($query);
			$n = 0;
			while($row = $this -> Database -> fetch_assoc()) {
				if(!empty($row['mol_id'])) {
					if($n >= $this -> offset && $n < $this -> offset + $this -> per_page) {
						$mol_ids[] = $row['mol_id'];
						$conf_ids[] = $row['id'];
						#catch extra column for tanimoto or substructure search
						if(!empty($row['t'])) {
							$this -> tanimoto[$row['mol_id']] = $row['t'];
						}
						elseif(!empty($row['c'])) {
							$this -> smarts_rep[$row['mol_id']] = $row['c'];
						}
					}
					elseif($n > $this -> offset + $this -> per_page) {
						break;
					}
					$n++;
				}
			}
			$this -> result_num = $this -> Database -> num_rows();
			$this -> Database -> free_result();
		
			# add conf_id and mol_id for future reference
			$fields[] = 'conf.id AS conf_id';
			$fields[] = 'conf.mol_id';
			
			# add mol2 to display in JSmol
			$fields[] = 'UNCOMPRESS(conf.mol2) AS mol2';
			
			# add target_id for future reference
			$fields[] = 'target.name as target_name';
		
			# set what fields to sellect
			foreach ($this -> data_structure as $field) { 
				#check if needs to be hidden and query vars
				if(!@in_array($field[0], $this -> hide)) {
					$fields[] = ( $field[3] ? $field[3] : 'conf.' ).$field[0];
				}
			}		
			# generate new vars
			if($tmp_vars = $this -> gen_vars(true, 'conf.')) {
				$sql_var[] = '('.$tmp_vars.')';
			}
			# Add fields for future usage
			$fields[] = 'mol.name as mol_name';
			$fields[] = 'mol.smiles as smi';		
		
			$this -> gen_sorting($this -> get_var_prefix($_GET['sort']));	
			# get subset
			if(!empty($_GET['subset'])) {
				$subset_tmp = explode('-', $_GET['subset']);
				if($subset_tmp[0] == 'ligand') {
					$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
				elseif($subset_tmp[0] == 'user') {
					$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
			}

			if(!empty($ligand_subset)) {
				$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
				#$sql_join[] = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
			}
			if(!empty($user_subset)) {
				$sql_join[] = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
			}
		
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_molecules as mol ON conf.mol_id = mol.id';
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id';
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id';
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id';
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_targets as target ON conf.target_id = target.id';
	
		
			if(!empty($mol_ids)) {
				$n = 0;
				foreach($mol_ids as $mid_key => $mid) {
					$mol = null;
					# Do it for every target
					if(empty($_GET['disable_mol_grouping']) && count($this -> target) > 1) {
						foreach($this -> target as $tid) {
							# copy sql vars localy
							$sql_var_tmp = $sql_var;
							$sql_var_tmp[] = 'conf.target_id = "'.$this -> Database -> secure_mysql($tid).'"';
							$sql_var_tmp[] = 'conf.mol_id = "'.$mid.'"';
							$query = '	SELECT '.implode(', ', $fields).' FROM '.$this -> project.'docking_conformations as conf '.implode(' ', $sql_join).' WHERE '.implode(' AND ', array_filter($sql_var_tmp)).' ORDER BY '.$this -> sorting['prefix_sort'].' LIMIT 1;';
							#echo $query.'</br>';
							$this -> Database -> query($query);
							$row = $this -> Database -> fetch_assoc();
							if (is_array($row)) {
								$mol[$tid] = $row;
							}
						}
					}
					else {
						$fields[] = 'conf.target_id'; # we need target_id since we dont iterate through them
						$query = '	SELECT '.implode(', ', $fields).' FROM '.$this -> project.'docking_conformations as conf 
								LEFT JOIN '.$this -> project.'docking_molecules as mol ON conf.mol_id = mol.id
								LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id
								LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id
								LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id
								LEFT JOIN '.$this -> project.'docking_targets as target ON conf.target_id = target.id
								WHERE conf.id = '.$conf_ids[$mid_key].' LIMIT 1;';
						#echo $query.'</br>';
						$this -> Database -> query($query);
						$row = $this -> Database -> fetch_assoc();
						if (is_array($row)) {
							$mol[$row['target_id']] = $row;
						}
					}
					$this -> mols[] = $mol;
					$n++;
				}
			
				# we need activity data for unique molecules
				foreach(array_unique($mol_ids) as $mid_key => $mid) {
					# Get activity data
					$query = 'SELECT * FROM '.$this -> project.'docking_assays_data AS data LEFT JOIN '.$this -> project.'docking_assays AS assay ON data.assay_id = assay.id WHERE `mol_id` = "'.$mid.'"';
					#echo $query.'</br>';
					$this -> Database -> query($query);
					while($row = $this -> Database -> fetch_assoc()) {
						$this -> mol_act[(in_array($row['docking_target_id'], $this -> target) ? $row['docking_target_id'] : 0)][$mid][] = $row;
					}
				}
				# Clear some memory
				$this -> Database -> free_result();
			}
			#print_r($this -> mols);
		}
	}
	
	public function download_csv() {
		# generate table like in query view
		$this -> search();
		
		header("Content-Type: application/force-download");
		header('Content-Disposition: attachment; filename="query-'.md5($this -> get_link()).'.csv"');
		
		#print csv
		# headers
		foreach ($this -> data_structure as $field) {
			if (!@in_array($field[0], $this -> hide)) {				
				#to remove?
				# Ad colspan to name 
				if ($field[0] == name) {
					# index field
					echo "\"#\"\t";
				}		
				echo "\"".$field[1]."\"\t";
				if ($field[0] == name) {
					# show tanimoto or smarts rep
					if(is_array($this -> tanimoto)) {
						echo "\"Tanimoto\"\t";
					}
					elseif(is_array($this -> smarts_rep)) {
						echo "\"# of SMARTS\"\t";
					}
					echo "\"Target\"\t";
				}
			}
		}
		echo "\n";
		# Print data
		$n = 0; # even or odd
		foreach ($this -> mols as $mol) {
			$style = ($n&1) ? 'odd' : 'even';
	
			#get highest value for sorting when no target for sorting specified
			foreach($this -> target as $tid) {
				if(is_array($mol[$tid])) { # display only existing conformers
					foreach ($this -> data_structure as $field) {
						if ($field[0] == 'name' ) {

							# index field
							echo ((($this -> page - 1) * $this -> per_page)+$n+1)."\t";
							echo "\"".$mol[$tid]['mol_name']."\"\t";
								
							# show tanimoto 
							if(is_array($this -> tanimoto)) {
								echo str_replace('.', ',', round($this -> tanimoto[$mol[$tid]['mol_id']],3))."\t";
							}
							elseif(is_array($this -> smarts_rep)) {
								echo str_replace('.', ',', round($this -> smarts_rep[$mol[$tid]['mol_id']],3))."\t";
							}

							echo "\"".$mol[$tid]['target_name']."\"\t";
						}
						elseif (@in_array($field[0], $this -> hide)) {
							echo '';
						}
						// round numeric fields 
						elseif (!empty($field[2])) {
							if($sort == $field[0] && ($tid == $_GET['sort_target'] || $top == $tid)) {
								echo str_replace('.', ',', round($mol[$tid][$field[0]], 2))."\t";								
							}
							else {
								echo str_replace('.', ',', round($mol[$tid][$field[0]], 2))."\t";
							}
						}
						else {
							echo $mol[$tid][$field[0]]."\t";
						}
					}
				}
				echo "\n";
			}
			$n++; #increase odd/even counter

		}
		
		exit;
	}
	
	
	public function download_assessment_csv() {
		# generate table like in query view
		$this -> data_assessment();
		
		header("Content-Type: application/force-download");
		header('Content-Disposition: attachment; filename="assessment-'.md5($this -> get_link()).'.csv"');
		
		#print csv
		# headers
		foreach ($this -> data_structure as $field) {
			if (!@in_array($field[0], $this -> hide) && @in_array($field[0], $_GET['scores']) || $field[0] == 'name') {			
				#to remove?
				# Ad colspan to name 
				if ($field[0] == name) {
					# index field
					echo "\"#\"\t";
				}		
				echo "\"".$field[1]."\"\t";
				if ($field[0] == name) {
					# rank score field
					echo "\"RankScore\"\t";
				}
			}
		}
		echo "\n";
		# Print data
		$n = 0; # even or odd
		foreach ($this -> mols as $mol) {
			$style = ($n&1) ? 'odd' : 'even';
	
			#get highest value for sorting when no target for sorting specified
			foreach($this -> target as $tid) {
				if(is_array($mol[$tid])) { # display only existing conformers
					foreach ($this -> data_structure as $field) {
						if ($field[0] == 'name' ) {

							# index field
							echo ((($this -> page - 1) * $this -> per_page)+$n+1)."\t";
							echo "\"".$mol[$tid]['mol_name']."\"\t";
								
							# show tanimoto 
							if(is_array($this -> tanimoto)) {
								echo str_replace('.', ',', round($this -> tanimoto[$mol[$tid]['mol_id']],3))."\t";
							}
							elseif(is_array($this -> smarts_rep)) {
								echo str_replace('.', ',', round($this -> smarts_rep[$mol[$tid]['mol_id']],3))."\t";
							}

							echo "\"".$mol[$tid]['target_name']."\"\t";
						}
						elseif (@in_array($field[0], $this -> hide)) {
							echo '';
						}
						// round numeric fields 
						elseif (!empty($field[2])) {
							if($sort == $field[0] && ($tid == $_GET['sort_target'] || $top == $tid)) {
								echo str_replace('.', ',', round($mol[$tid][$field[0]], 2))."\t";								
							}
							else {
								echo str_replace('.', ',', round($mol[$tid][$field[0]], 2))."\t";
							}
						}
						else {
							echo $mol[$tid][$field[0]]."\t";
						}
					}
				}
				echo "\n";
			}
			$n++; #increase odd/even counter

		}
		
		
		foreach ($this -> mols as $mol) {
			$style = ($num&1) ? 'odd' : 'even';
			if(is_array($mol)) { # display only existing conformers
				foreach ($this -> data_structure as $field) {
					if ($field[0] == 'name' ) {
						# index field
						echo ((($this -> page - 1) * $this -> per_page)+$num+1)."\t";
						echo "\"".$mol['name']."\"\t";

						echo round($mol['rank_score'],4)."\t"; 
					}
					elseif (@in_array($field[0], $this -> hide)) {
						echo '';
					}
					elseif ( @in_array($field[0], $_GET['scores'])){
						echo (!empty($mol[$field[0]]) ? $mol[$field[0]] : '&#8734;')."\t";
					}
				}
				echo "\n";
				$num++;
			}
		}
	
		
		exit;
	}
	
	public function get_var_prefix($var) {
		foreach($this -> data_structure as $v) {
			if($var == $v[0]) {
				# fallback to conformations if empty
				return !empty($v[3]) ? $v[3] : 'conf.';
			}
		}
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
	
	# get an array of mol2's
	public function get_molecules_mol2() {
		$this -> get_project_db();
		$mol_id = $this -> Database -> secure_mysql($_GET['mol_id'] ? $_GET['mol_id'] : $_POST['mol_id']);
		$target_id = $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
		
		$sql_join = array();
		# get subset
		if(!empty($_GET['subset'])) {
			$subset_tmp = explode('-', $_GET['subset']);
			if($subset_tmp[0] == 'ligand') {
				$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}
	
		if(!empty($ligand_subset)) {
			$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
			#$sql_join[] = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
		}
		if(!empty($user_subset)) {
			$sql_join[] = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
		}
		
		
		if($_GET['append_target']) {
			$this -> download_target(false);
		}
		
		# get list of fields
		foreach ($this -> data_structure as $field) { 
			if(!@in_array($field[0], $this -> hide)) {
				$fields[] = (!empty($field[3]) ? $field[3] : 'conf.').$field[0];
			}
		}
		
		# resolve sorting
		$this -> gen_sorting();
		
		# Check if multi-molecule mode is necessary
		if(count($_GET['mol_ids']) == 1) { 
			$mol_id = (int) $this -> Database -> secure_mysql($_GET['mol_ids'][0]);
		}
		
		if($mol_id > 0 && $target_id > 0) {
			# get molecule name
			$query = 'SELECT name FROM '.$this -> project.'docking_molecules WHERE id="'.$mol_id.'";';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			$name = $row[0];
		
			# download mol2's
			$query = 'SELECT conf.id, name, UNCOMPRESS(conf.mol2) as mol2, '.implode(',',$fields).'  FROM '.$this -> project.'docking_conformations AS conf '.implode(' ',$sql_join).' LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id WHERE conf.mol_id = "'.$mol_id.'" AND conf.target_id = "'.$target_id.'" '.(!empty($sql_var) ? 'AND '.implode(' AND ',$sql_var) : '').' ORDER BY '.$this -> sorting['sort'].';';	
			$this -> Database -> query($query);
			#echo $query;
			while($row = $this -> Database -> fetch_assoc()) {
				$this -> mols[$row['id']] = $row;
			}
		}
		elseif(count($_GET['mol_ids']) > 1 && $target_id > 0) {
			#secure input
			foreach($_GET['mol_ids'] as $mol) {
				$mol_ids[] = (int) $this -> Database -> secure_mysql($mol);
			}		
			# download mol2's
			foreach($mol_ids as $mol_id) {
				$query = 'SELECT conf.id, name, UNCOMPRESS(conf.mol2) as mol2, '.implode(',',$fields).'  FROM '.$this -> project.'docking_conformations AS conf '.implode(' ',$sql_join).' LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id WHERE conf.mol_id = "'.$mol_id.'" AND conf.target_id = "'.$target_id.'" '.(!empty($sql_var) ? 'AND '.implode(' AND ',$sql_var) : '').' ORDER BY '.$this -> sorting['sort'].';';	
				$this -> Database -> query($query);
				while($row = $this -> Database -> fetch_assoc()) {
					$this -> mols[$row['id']] = $row;
				}
			}
			$name = 'multimol-'.count($mol_ids).'-'.time();
		}
		elseif(count($_GET['conf_id']) > 0) {
			foreach($_GET['conf_id'] as $conf) {
				$conf_id[] = (int) $this -> Database -> secure_mysql($conf);
			}
			
			# get molecule name (if one), else name multi-
			$query = 'SELECT DISTINCT mol.name FROM '.$this -> project.'docking_conformations AS conf '.implode(' ',$sql_join).' LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id LEFT JOIN '.$this -> project.'docking_molecules AS mol ON mol.id = conf.mol_id WHERE conf.id IN ('.(implode(',', $conf_id)).');';
			$this -> Database -> query($query);
			$num = $this -> Database -> num_rows();
			if($num = 1) {
				$row = $this -> Database -> fetch_row();
				$name = $row[0];
			}
			elseif($num > 1) {
				$name = 'multimol-'.$num.'-'.time();
			}
			
			# download mol2's
			foreach($conf_id as $conf) {
				$query = 'SELECT conf.id, name, UNCOMPRESS(conf.mol2) as mol2, '.implode(',',$fields).'  FROM '.$this -> project.'docking_conformations AS conf '.implode(' ',$sql_join).' LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id WHERE conf.id = "'.$conf.'" LIMIT 1;';	
				$this -> Database -> query($query);
				$row = $this -> Database -> fetch_row();
				$this -> mols[$row['id']] = $row;
			}
		}
		else {
			$query = 'SELECT conf.id, UNCOMPRESS(conf.mol2) as mol2 FROM ('.$this -> search_sql(true).') as temp JOIN '.$this -> project.'docking_conformations AS conf ON conf.id = temp.id WHERE conf.target_id = '.$target_id.(!empty($sql_var) ? ' AND '.implode(' AND ',$sql_var) : '');
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$this -> mols[$row['id']] = $row;
			}
		}
		
		# Do the natural sort
#		if($_GET['sort'] == 'name' && $_GET['sort_type'] == "asc" || empty($_GET['sort'])) {
#			uksort($this -> mols, 'strnatcasecmp');
#		}
#		elseif($_GET['sort'] == 'name' && $_GET['sort_type'] == "desc") {
#			uksort($this -> mols, 'strnatcasecmp');
#			$this -> mols = array_reverse($this -> mols);
#		}
		$this -> filename = $name;
	}
	
	
	public function download_molecule() {
		global $CONFIG;	
		$this -> get_molecules_mol2();
		#exit; //suppress further output	
		
		# choose type of download: mol2 or chimera session
		if(empty($_GET['format']) || $_GET['format'] == "mol2") {
			foreach($this -> mols as $mol) {
				if(!$headers) { # hack not to send empty file
					header("Content-Type: application/force-download");
					header('Content-Disposition: attachment; filename="'.$this -> filename.'.mol2"');
					$headers = true;
				}
				echo $mol['mol2'];
			}
		}
		elseif($_GET['format'] == "chimera") {
		
			# get project name and use it as table name
			$this -> get_project_db();
		
			$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
			
			#save ligands file
			$lig = '';
			$this -> get_molecules_mol2();
			foreach($this -> mols as $mol) {
				#inject some scores and stuff
				$lig .= "\n";
				$lig .= sprintf("########## %12s: %20s\n", 'Name', $mol['name']);
				foreach ($this -> data_structure as $field) { 
					if(!@in_array($field[0], $this -> hide)) {
						$lig .= sprintf("########## %12s: %20s\n", $field[1], $mol[$field[0]]);
					}
				}
				$lig .= "\n";
				$lig .= $mol['mol2'];
			}
		
			#send .py session
			header("Content-Type: application/force-download");
			header('Content-Disposition: attachment; filename="chimera-session-'.$this -> filename.'.py"');
			#echo file_get_contents($CONFIG['temp_dir'].'/'.$rand.'/session.py');
			
			# import chimera essentials
			echo "from FindHBond import findHBonds\n";
			echo "from ViewDock import ViewDock\n";
			echo "from chimera import runCommand\n";
			echo "from OpenSave import osTemporaryFile\n";
			
			echo "import base64\n";
			echo "from zlib import decompress\n";
			
			echo "receptor_mol2 = '".base64_encode(gzcompress($this -> get_target_mol2($target_id)))."'\n";
			echo "ligand_mol2 = '".base64_encode(gzcompress($lig))."'\n";

			# write receptor file
			echo "receptor_file = osTemporaryFile(prefix='rec_', suffix='.mol2')\n";
			echo "f = open(receptor_file, 'w')\n";
			echo "f.write(decompress(base64.b64decode(receptor_mol2)))\n";
			echo "f.close()\n";
			# write ligand file
			echo "ligand_file = osTemporaryFile(prefix='lig_', suffix='.mol2')\n";
			echo "f = open(ligand_file, 'w')\n";
			echo "f.write(decompress(base64.b64decode(ligand_mol2)))\n";
			echo "f.close()\n";
			
			# clean session
			echo "runCommand(\"close session\")\n";
			
			# open models
			echo "runCommand(\"open %s\" % receptor_file)\n";
			echo "runCommand(\"preset apply interactive 2\")\n";
			
			# open ligands in ViewDock
			echo "viewdock = ViewDock(ligand_file, \"Dock 4, 5 or 6\")\n";

			# show only Active site
			echo "runCommand(\"select #1 zr<10\")\n";
			echo "runCommand(\"select invert\")\n";
			echo "runCommand(\"~display sel\")\n";
			
			# find hbonds
			echo "runCommand(\"hbonds intramodel false distSlop 0.8 angleSlop 40 cacheDA true\")\n";
			echo "viewdock.results.addColumn(\"HBonds\")\n";
			
		}
		else {
			# prepare OpenBabel objects to read in molecule
			$OBConversion = new OBConversion;
			$OBConversion->SetInAndOutFormats("mol2", $_GET['format']);
			foreach($this -> mols as $mol) {
				if(!$headers) { # hack not to send empty file
					header("Content-Type: application/force-download");
					header('Content-Disposition: attachment; filename="'.$this -> filename.'.'.$_GET['format'].'"');
					$headers = true;
				}
				$OBMol = new OBMol;
				$OBConversion->ReadString($OBMol, $mol['mol2']);
				echo $OBConversion->WriteString($OBMol);
			}
		}
		exit; //suppress further output	
	}
	
	public function download_target($end = true) {
		# get target structure
		$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
		$query = 'SELECT name, mol2 FROM '.$this -> project.'docking_targets WHERE id = '.$target_id.';';
		$this -> Database -> query($query);
		$row = $this -> Database -> fetch_row();
		echo $row[1];
		if($end) {
			exit;		
		}
	}
	
	public function get_target_mol2($tid) {
		# get target structure
		$target_id = (int) $this -> Database -> secure_mysql($tid);
		$query = 'SELECT name, mol2 FROM '.$this -> project.'docking_targets WHERE id = '.$target_id.';';
		$this -> Database -> query($query);
		$row = $this -> Database -> fetch_row();
		return $row[1];
	}
	
	public function molecule() {
		# get project name and use it as table name
		$this -> get_project_db();
		
		$mol_id = $this -> Database -> secure_mysql($_GET['mol_id'] ? $_GET['mol_id'] : $_POST['mol_id']);
		# donwload molecule
		$query = 'SELECT name, smiles  FROM '.$this -> project.'docking_molecules WHERE id = "'.$mol_id.'" LIMIT 1;';	
		$this -> Database -> query($query);
		#echo $query;
		$mol = $this -> Database -> fetch_assoc();
		$smiles = $this -> unify_smiles($mol['smiles']);
		# prepare OpenBabel objects to read in molecule
		$OBMol = new OBMol;
		$OBConversion = new OBConversion;
		$OBConversion->SetInFormat("smi");
		$OBConversion->ReadString($OBMol, $smiles);
		
		# gen inchi
		$OBConversion->SetOutFormat("inchi");
		$inchi = trim($OBConversion->WriteString($OBMol));
		
		# gen inchi-key 
		$OBConversion->SetOutFormat("inchikey");
		$inchi_key = trim($OBConversion->WriteString($OBMol));
		
		# calculate logp
		$calc = OBDescriptor::FindType("logP");
		$logp = $calc -> Predict($OBMol);
		
		# calculate HBD
		$calc = OBDescriptor::FindType("HBD");
		$h_donors = $calc -> Predict($OBMol);
		
		# calculate HBA
		$calc = OBDescriptor::FindType("HBA2");
		$h_acceptors = $calc -> Predict($OBMol);
		
		# export data to view
		$this -> mol = array( 	'properties' => array(	'name' => $mol['name'],
								'smiles' => $mol['smiles'],
								'smiles_unified' => $smiles,
								'inchi' => $inchi,
								'inchi_key' => $inchi_key,
								'h_donors' => $h_donors ,
								'h_acceprots' => $h_acceptors ,
								'logp' => $logp,
								),
					'smiles' => $mol['smiles'],
					);
		
		
		global $CONFIG;
		# get project name and use it as table name
		$this -> get_project_db();
		
		#Get conformations		
		$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
		$mol_id = (int) $this -> Database -> secure_mysql($_GET['mol_id'] ? $_GET['mol_id'] : $_POST['mol_id']);
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		
		$this -> hide = $this -> Database -> secure_mysql($_GET['hide'] ? $_GET['hide'] : $_POST['hide']);
		$sort_prefix = $this -> get_var_prefix($_GET['sort']);
		$this -> gen_sorting(!empty($sort_prefix) ? $sort_prefix : 'conf.');
		
		$query = 'SELECT COUNT(*) AS c, ligand_subset, target_id, t.name AS target_name, l.name AS ldb_name FROM '.$this -> project.'docking_conformations AS conf LEFT JOIN '.$this -> project.'docking_targets AS t ON conf.target_id = t.id LEFT JOIN '.$this -> project.'docking_ligand_subset AS l ON conf.ligand_subset = l.id WHERE mol_id = "'.$mol_id.'" GROUP BY target_id, ligand_subset';
		#echo $query.'</br>';
		$this -> Database -> query($query);
		while ($row = $this -> Database -> fetch_assoc()) {
			$this -> targets[$row['target_id']][] = $row;
		}
		
		# add id and mol_id for future reference
		$fields[] = 'conf.id';
		$fields[] = 'conf.mol_id';
		$fields[] = 'UNCOMPRESS(conf.mol2) as mol2';
		# set what fields to sellect
		foreach ($this -> data_structure as $field) { 
			if(!@in_array($field[0], $this -> hide)) {
				$fields[] = ( $field[3] ? $field[3] : 'conf.' ).$field[0];
			}
		}
		
		# gen vars
		#$vars = $this -> gen_vars();
		
		# get subset
		if(!empty($_GET['subset'])) {
			$subset_tmp = explode('-', $_GET['subset']);
			if($subset_tmp[0] == 'ligand') {
				$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}

		# add interacion fields
		foreach($this -> interaction_types as $int) {
			array_push($fields, $int[0]);
			if(!empty($int[2])) {
				array_push($fields, $int[0].'_crude');
			}
		}
		$mol_vars = empty($vars) ? ' WHERE conf.target_id = "'.$this -> Database -> secure_mysql($target_id).'" AND conf.mol_id = "'.$mol_id.'" '.(!empty($ligand_subset) ? ' AND conf.ligand_subset = '.$ligand_subset : '') : $vars.' AND conf.target_id = "'.$this -> Database -> secure_mysql($target_id).'" AND conf.mol_id = "'.$mol_id.'" '.(!empty($ligand_subset) ? ' AND conf.ligand_subset = '.$ligand_subset : '');
		$query = '	SELECT '.implode(', ', $fields).' FROM '.$this -> project.'docking_conformations as conf 
				LEFT JOIN '.$this -> project.'docking_molecules as mol ON conf.mol_id = mol.id
				LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id
				LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id
				LEFT JOIN '.$this -> project.'docking_conformations_interactions as confint ON conf.id = confint.id
				LEFT JOIN '.$this -> project.'docking_targets as target ON conf.target_id = target.id
				'.$mol_vars.' ORDER BY '.$this -> sorting['prefix_sort'].';';
		#echo $query.'</br>';
		$this -> Database -> query($query);
		$this -> result_num = $this -> Database -> num_rows();
		while ($row = $this -> Database -> fetch_assoc()) {
			if(is_array($row)) {
				#get query profile and build binding profile
				$binding_profile = array();
				foreach($this -> interaction_types as $int) {
					$row[$int[0]] = array_filter(explode('|', $row[$int[0]]));
					if(!empty($row[$int[0]])) {
						foreach($row[$int[0]] as $res) {
							preg_match('/(\d+)$/', $res, $matches);
							$binding_profile[$matches[1]]['precise'][] = array($res, $int[0]);
						}
					}
					
					$row[$int[0].'_crude'] = array_filter(explode('|', $row[$int[0].'_crude']));
					if(!empty($row[$int[0].'_crude'])) {
						foreach($row[$int[0].'_crude'] as $res) {
							preg_match('/(\d+)$/', $res, $matches);
							$binding_profile[$matches[1]]['crude'][] = array($res, $int[0]);
						}
					}
				}
				
				$row['binding_profile'] = $binding_profile;
				
				# build global binding profile
				foreach($binding_profile as $rid => $residue) {
					if(count($residue['precise']) > count($this -> binding_profile[$rid]['precise'])) {
						$this -> binding_profile[$rid]['precise'] = $residue['precise'];
					}
					if(count($residue['crude']) > count($this -> binding_profile[$rid]['crude'])) {
						$this -> binding_profile[$rid]['crude'] = $residue['crude'];
					}
				}
				$this -> mols[] = $row;
			}
		}
		if(!empty($this -> binding_profile)) {
			uksort($this -> binding_profile, "strnatcmp");
		}
		# Do the natural sort
		if($_GET['sort'] == 'name' && $_GET['sort_type'] == "asc" || empty($_GET['sort'])) {
			uksort($this -> mols, 'strnatcasecmp');
		}
		elseif($_GET['sort'] == 'name' && $_GET['sort_type'] == "desc") {
			uksort($this -> mols, 'strnatcasecmp');
			$this -> mols = array_reverse($this -> mols);
		}
		
		# set viewer
		$viewers = array(	'jsmol' => 'HTML5',
						'jsmol-webgl' => 'WEBGL HTML5', 
						'jmol' => 'JAVA',
						);
		if(!empty($_GET['viewer'])) {
			if(!empty($viewers[$_GET['viewer']])) {
				$viewer = $viewers[$_GET['viewer']];
			}
			else {
				$viewer = 'HTML5'; #Use HTML5 JSmol as default
			}
			setcookie('viewer', $viewer, time()+3600*24*365);
		}
		
		
#		#### test of scoring		
#		if(!empty($this -> mols)) {
#			putenv("OMP_NUM_THREADS=2");

#			echo getenv('OMP_NUM_THREADS'), '<br>';
#			$start = microtime(true);
#			$OBConversion = new OBConversion;
#			$OBConversion -> SetInFormat("mol2");
#		
#			$receptor = new OBMol();
#			$ligand = new OBMol();
#		
#			$OBConversion -> ReadString($receptor, $this -> get_target_mol2($target_id));
#			$OBConversion -> ReadString($ligand, $this -> mols[0]['mol2']);

#			$receptor_num = $receptor -> NumAtoms();
#			$ligand_num = $ligand -> NumAtoms();

#			#generate constraints
#			#$obconstraint = new OBFFConstraints();
#		
#			#foreach(range(1, $receptor_num) as $idx) {
#			#	#$atom = $receptor -> GetAtom($idx);
#			#	$obconstraint -> AddAtomConstraint($idx);
#			#}
#		
#			$complex = $receptor;
#			$complex -> add($ligand);

#			#generate interacions
#			$ligand_bit = new OBBitVec($receptor_num + $ligand_num);
#			$receptor_bit = new OBBitVec($receptor_num + $ligand_num);
#			$ligand_bit -> SetRangeOn($receptor_num + 1, $receptor_num + $ligand_num);
#			$receptor_bit -> SetRangeOn(1, $receptor_num);
#		
#			$ff = OBForceField_FindForceField('mmff94');
#			#$ff2 = OBForceField::FindForceField('mmff94');
#			OBForceField_AddIntraGroup($ff,$ligand_bit); # bonded interactions in the ligand
#			OBForceField_AddInterGroup($ff,$ligand_bit); # non-bonded between ligand-ligand atoms
#			OBForceField_AddInterGroups($ff,$ligand_bit, $receptor_bit); # non-bonded between ligand and pocket atoms

#			$success = OBForceField_Setup($ff, $complex);#, $obconstraint);

#			$pre_vdw = OBForceField_E_VDW($ff, 0);
#			$pre_ele = OBForceField_E_Electrostatic($ff, 0);
#			echo $ligand -> GetTitle(), '<br>', $pre_vdw, '<br>', $pre_ele, '<br>';
#			echo round(microtime(true) - $start, 4), '<br>';
#		
#			# next ligand
#			foreach($this -> mols as $mol) {
#				$start = microtime(true);
#				$OBConversion -> ReadString($ligand, $mol['mol2']);
#				foreach(range(1, $ligand_num) as $idx) {
#					$atom = $ligand -> GetAtom($idx);
#					$complex_atom = $complex -> GetAtom($receptor_num + $idx);
#					$complex_atom -> SetVector($atom -> GetVector());
#				}
#				OBForceField_SetCoordinates($ff, $complex);
#				$pre_vdw = OBForceField_E_VDW($ff, 0);
#				$pre_ele = OBForceField_E_Electrostatic($ff, 0);
#				echo $ligand -> GetTitle(), '<br>', $pre_vdw, '<br>', $pre_ele, '<br>';
##				OBForceField_MolecularDynamicsTakeNSteps($ff, 10, 400);
#				OBForceField_ConjugateGradients($ff, 500, 0.05);
#				$post_vdw = OBForceField_E_VDW($ff, 0);
#				$post_ele = OBForceField_E_Electrostatic($ff, 0);
#				echo $ligand -> GetTitle(), '<br>', $post_vdw, '<br>', $post_ele, '<br>';
#				echo round(microtime(true) - $start, 4), '<br>';
#			}
#		}
##		exit;	
	}
	
	public function interactions() {
		$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
		$query_id = (int) $this -> Database -> secure_mysql($_GET['query_id'] ? $_GET['query_id'] : $_POST['query_id']);
		
		$sort = $this -> Database -> secure_mysql($_GET['sort']);
		$sort_type = $this -> Database -> secure_mysql($_GET['sort_type']);
		
		$query = 'SELECT name, mol2 FROM '.$this -> project.'docking_targets WHERE id = '.$target_id.';';
		$this -> Database -> query($query);
		$row = $this -> Database -> fetch_row();
		$this -> targets = $row;
		
		# add id and mol_id for future reference
		$fields[] = 'conf.id';
		$fields[] = 'conf.mol_id';
		$fields[] = 'UNCOMPRESS(conf.mol2) as mol2';
		# set what fields to sellect
		foreach ($this -> data_structure as $field) { 
			if(!@in_array($field[0], $this -> hide)) {
				$fields[] = ( $field[3] ? $field[3] : 'conf.' ).$field[0];
			}
		}
		
		# get subset
		if(!empty($_GET['subset'])) {
			$subset_tmp = explode('-', $_GET['subset']);
			if($subset_tmp[0] == 'ligand') {
				$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}
	
		if(!empty($ligand_subset)) {
			$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
			#$sql_join[] = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
		}
		if(!empty($user_subset)) {
			$sql_join[] = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
		}
		
		# add interacion fields
		foreach($this -> interaction_types as $int) {
			array_push($fields, $int[0]);
			if(!empty($int[2]) && $_GET['int_mode'][$int[0]] != 'precise') {
				array_push($fields, $int[0].'_crude');
			}
		}
		
		$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id';
		$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id';
		$sql_join[] = 'JOIN '.$this -> project.'docking_conformations_interactions AS confint ON conf.id = confint.id';
		
		
		if(!empty($query_id)) {
			# get query information
			$query = 'SELECT '.implode(', ', $fields).' FROM '.$this -> project.'docking_conformations AS conf '.implode(' ', $sql_join).' WHERE conf.id = "'.$query_id.'";';
			$this -> Database -> query($query);
			#echo $query.'</br>';
			$row = $this -> Database -> fetch_row();
			
			#build binding profile
			$binding_profile = array();
			foreach($this -> interaction_types as $int) {
				if(!empty($row[$int[0]])) {
					$row[$int[0]] = array_filter(explode('|', $row[$int[0]]));
					foreach($row[$int[0]] as $res) {
						preg_match('/(\d+)$/', $res, $matches);
						$binding_profile[$matches[1]]['precise'][] = array($res, $int[0]);
					}
				}
				if($_GET['int_mode'][$int[0]] != 'precise' && !empty($row[$int[0].'_crude'])) {
					$row[$int[0].'_crude'] = array_filter(explode('|', $row[$int[0].'_crude']));
					foreach($row[$int[0].'_crude'] as $res) {
						preg_match('/(\d+)$/', $res, $matches);
						$binding_profile[$matches[1]]['crude'][] = array($res, $int[0]);
					}
				}
			}
			$row['binding_profile'] = $binding_profile;
			
			#pass binding profile query
			$this -> binding_profile_query = $row;
			
			if(array_filter($row)) {
				$this -> mols[] = $row;
			}
		}

		foreach($this -> interaction_types as $int) {
			if(!empty($_GET[$int[0].'_residues'])) {
				$this -> binding_profile_query[$int[0]] = array_filter($_GET[$int[0].'_residues']);
				foreach($this -> binding_profile_query[$int[0]] as $res) {
					preg_match('/(\d+)$/', $res, $matches);
					$binding_profile[$matches[1]][] = array($res, $int[0]);
				}
			}
		}
		$this -> binding_profile = $binding_profile;
		
		
		# form score and matches for fulltext search
		foreach($this -> interaction_types as $int) {
			if(!empty($this -> binding_profile_query[$int[0]])) {
				if(empty($int[2]) || $_GET['int_mode'][$int[0]] == 'precise') {
					foreach($this -> binding_profile_query[$int[0]] as $res) {
						$match_sql[] = '`'.$int[0].'` REGEXP \'[[:<:]]'.$res.'[[:>:]]\'';
						$match_count_sql[] = '(LENGTH(`'.$int[0].'`) - LENGTH(REPLACE(`'.$int[0].'`, \''.$res.'\', \'\')))/LENGTH(\''.$res.'\')';
					}
					#$match_sql[] = 'MATCH(`'.$int[0].'`) AGAINST("'.implode(' +', array_filter($this -> binding_profile_query[$int[0]])).'")';
				}
				else {
					foreach($this -> binding_profile_query[$int[0]] as $res) {
						$match_sql[] = '`'.$int[0].'` REGEXP \'[[:<:]]'.$res.'[[:>:]]\'';
						$match_sql[] = '`'.$int[0].'_crude` REGEXP \'[[:<:]]'.$res.'[[:>:]]\'';
						$match_count_sql[] = '(LENGTH(`'.$int[0].'`) - LENGTH(REPLACE(`'.$int[0].'`, \''.$res.'\', \'\')))/LENGTH(\''.$res.'\')';
						$match_count_sql[] = '(LENGTH(`'.$int[0].'_crude`) - LENGTH(REPLACE(`'.$int[0].'_crude`, \''.$res.'\', \'\')))/LENGTH(\''.$res.'\')';
					}
					#$match_sql[] = 'MATCH(`'.$int[0].'`, `'.$int[0].'_crude`) AGAINST("'.implode(' +', array_filter($this -> binding_profile_query[$int[0]])).'")';
				}
			}
		}
		
		
		if(!empty($match_sql)) {
			# get all simmilar binders
			$query = 'SELECT COUNT(conf.id) FROM '.$this -> project.'docking_conformations AS conf '.implode(' ', $sql_join).' WHERE mol2 IS NOT NULL '.(!empty($query_id) ? 'AND conf.id != "'.$query_id.'"' : '' ).' AND ('.implode(' OR ', $match_sql).') '.(!empty($sql_var) ? 'AND '.implode('AND', $sql_var) : '').';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$this -> result_num = $this -> Database -> fetch_row()[0];
			
			# get similar binders
			$query = 'SELECT '.implode(', ', $fields).', '.implode('+',$match_count_sql).' AS score FROM '.$this -> project.'docking_conformations AS conf '.implode(' ', $sql_join).' WHERE mol2 IS NOT NULL '.(!empty($query_id) ? 'AND conf.id != "'.$query_id.'"' : '' ).' AND ('.implode(' OR ', $match_sql).') '.(!empty($sql_var) ? 'AND '.implode('AND', $sql_var) : '').' HAVING score > 0 ORDER BY '.(!empty($sort) ? $sort.' '.$sort_type : 'score DESC').' LIMIT '.(($this -> page - 1) * $this -> per_page).','.($this -> per_page).';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			while ($row = $this -> Database -> fetch_assoc()) {
				#build binding profile
				$binding_profile = array();
				foreach($this -> interaction_types as $int) {
					if(!empty($row[$int[0]])) {
						$row[$int[0]] = array_filter(explode('|', $row[$int[0]]));
						foreach($row[$int[0]] as $res) {
							preg_match('/(\d+)$/', $res, $matches);
							$binding_profile[$matches[1]]['precise'][] = array($res, $int[0]);
						}
					}
					if($_GET['int_mode'][$int[0]] == 'crude' && !empty($row[$int[0].'_crude'])) {
						$row[$int[0].'_crude'] = array_filter(explode('|', $row[$int[0].'_crude']));
						foreach($row[$int[0].'_crude'] as $res) {
							preg_match('/(\d+)$/', $res, $matches);
							$binding_profile[$matches[1]]['crude'][] = array($res, $int[0]);
						}
					}
				}
				
				$row['binding_profile'] = $binding_profile;
				
				# build global binding profile
				foreach($binding_profile as $rid => $residue) {
					if(count($residue['precise']) > count($this -> binding_profile[$rid]['precise'])) {
						$this -> binding_profile[$rid]['precise'] = $residue['precise'];
					}
					if(count($residue['crude']) > count($this -> binding_profile[$rid]['crude'])) {
						$this -> binding_profile[$rid]['crude'] = $residue['crude'];
					}
				}
				
				$this -> mols[] = $row;
			}
			uksort($this -> binding_profile, "strnatcmp");
		}
	}
	
	public function subset_add() {
		# get project name and use it as table name
		$this -> get_project_db();
		$user_subset = (int) (!empty($_POST['subset_id_add']) ? $_POST['subset_id_add'] : (!empty($_GET['subset_id_add']) ? $_GET['subset_id_add']: null));
		if(!empty($user_subset)) {	
			$query = 'INSERT IGNORE INTO '.$this -> project.'docking_user_subset_members (user_subset_id, conf_id) SELECT '.($user_subset).', id FROM ('.$this -> search_sql(true).') as temp;';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$this -> num = $this -> Database -> affected_rows();
		}
	}
	
	public function optimize_weights($rank_fields, $weights = null) {
		$this -> get_project_db();

		if(empty($weights)) {
			$weights = $_GET['scores_weight'];
		}
		
		if(!is_array($rank_fields)) {
			$rank_fields = array($rank_fields);
		}
		
		
		$w = array(0,0.1,0.2,0.3,0.4,0.5,0.6,0.7,0.8,0.9,1);
		
		#get target
		$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
		if(!empty($target_id)) {
			$sql_var[] = "target_id = ".$target_id;
		}
		# get subset
		if(!empty($_GET['subset'])) {
			$subset_tmp = explode('-', $_GET['subset']);
			if($subset_tmp[0] == 'ligand') {
				$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}
	
		if(!empty($ligand_subset)) {
			#$ligand_subset_join = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
			$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
		}
		if(!empty($user_subset)) {
			$subset_join = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
		}
			

		if(preg_match('/%/', $_GET['rankscore_cutoff'])) { # get percentage
			$query = 'SELECT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' WHERE '.(!empty($sql_var) ? implode(' AND ', $sql_var) : '').';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$this -> rankscore_cutoff = round($this -> Database -> num_rows() * (int)$_GET['rankscore_cutoff'] / 100);
			$rankscore_cutoff = $this -> rankscore_cutoff;
		}
		else {
			$this -> rankscore_cutoff = $_GET['rankscore_cutoff'] ? (int) $this -> Database -> secure_mysql($_GET['rankscore_cutoff']) : 10000;
			$rankscore_cutoff = $this -> rankscore_cutoff;
		}
	
	
	
		foreach ($rank_fields as $field) {
			if(in_array($field, array('h_bonds', 'h_acceptors', 'h_donors', 'salt_bridge', 'arg_clust', 'cscore'))) {
				$order = 'ISNULL('.$field.') ASC, '.$field.' DESC';
			}
			else {
				$order = 'ISNULL('.$field.') ASC, '.$field.' ASC';
			}
			$query = 'SELECT '.$field.', conf.mol_id, conf.name FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' WHERE '.$field.' IS NOT NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '').' ORDER BY '.$order.' LIMIT '.$rankscore_cutoff.';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$n = 1; # ordinal number for rows
			$prev = null; # placeholder for previous value to check if it changed
			while ($row = $this -> Database -> fetch_assoc()) {
				foreach($w as $weight) {
					if(empty($fields[$field]["$weight"][$row['mol_id']])) {
						$fields[$field]["$weight"][$row['mol_id']] = $n;
						$mols[$row['mol_id']]['name'] = $row['name'];
						# add mol to the list
						$mols[$row['mol_id']][$field] = $n;
						#add contribution to rank_score
						if($_GET['cumulative'] == 0) {
							$partial_rank_score[$field]["$weight"][$row['mol_id']] += $weight/$n;
						}
					}
					#cumulative ranking seams to work better					
					if($_GET['cumulative'] != 0 ) {
						$partial_rank_score[$field]["$weight"][$row['mol_id']] += $weight/$n;
					}
					#increase counter only if value changed
					if($prev != $row[$field]) {
						$n++;
						$prev = $row[$field];
					}
				}
			}
			# Clear some memory
			$this -> Database -> free_result();
		}
		
		$act = array();
		$noa = array();
		# get actives and non-actives
		$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' LEFT JOIN '.$this -> project.'docking_assays_data AS act ON act.mol_id = conf.mol_id WHERE act.act_value IS NOT NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '');
		#echo $query.'</br>';
		$this -> Database -> query($query);
		while($row = $this -> Database -> fetch_assoc()) {
			$act[] = $row['mol_id'];
		}
		$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' LEFT JOIN '.$this -> project.'docking_assays_data AS act ON act.mol_id = conf.mol_id WHERE act.act_value IS NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '');
		#echo $query.'</br>';
		$this -> Database -> query($query);
		while($row = $this -> Database -> fetch_assoc()) {
			$noa[] = $row['mol_id'];
		}
		$num_act = count($act);
		$num_noa = count($noa);
			
		
		$count_act = 0;
		$count_noa = 0;
		
		# calculate exhaustive rank scores
		# define exhaustive weight function
		function array_each_append($input_array,$append) {
			$array = array();
			$output_array = array();
			#convert elements to be arrays
			foreach($input_array as $element) {
				if(!is_array($element)) {
					$array[] = array($element);
				}
				else {
					$array[] = $element;
				}
			}
			foreach(array_keys($array) as $k) {
				if(is_array($append)) {
					foreach(array_keys($append) as $append_k) {
						$output_array[] = array_merge($array[$k], array($append[$append_k]));
					}
				}
				else {
					$output_array[] = array_merge($array[$k], array($append));
				}
			}
			return $output_array;
		}
		
		foreach($rank_fields as $field) {
			if(empty($weights_grid)) {
				$weights_grid = $w;
			}
			else {
				$weights_grid = array_each_append($weights_grid, $w);
			}
		}
		#compute weighted rankscores
		foreach($weights_grid as $k => $weights) {
			foreach($mols as $mol_id) {
				foreach($rank_fields as $i => $field) {
					echo $weights[$i];
					$rank_score[$k][$mol_id] += $partial_rank_score[$field]["$weights[$i]"][$mol_id];
				}
			}
				
			#sort by scores
			arsort($rank_score[$k]);
		
			$chart = array();
			foreach($rank_score[$k] as $mol => $score) {
				# generate data for ROCs chart
				if(in_array($mol, $act)) {
					$count_act++;
					 
				}
				elseif(in_array($mol, $noa)) {
					$count_noa++;
				}
		
				if($last['act'] < $count_act && $last['noa'] < $count_noa) {
					$chart[] = array($count_noa/$num_noa, $count_act/$num_act);
					$last['act'] = $count_act;
					$last['noa'] = $count_noa;
				}
			}
		
			#count AUC
			if(!empty($chart)) {
				$auc = 0;
				foreach($chart as $v) {
					if(!$last) {
						$last = array(0,0);
					}
					$auc[$k] += 0.5*($v[1]+$last[1])*abs($v[0]-$last[0]);
					$last = $v;
				}
				#add AUC for (1,1) point
				if($last[0] != 1 || $last[1] != 1) {
					$auc[$k] += 0.5*(1+$last[1])*abs(1-$last[0]);
				}
			}
			
		}
		
		# get best auc
		#arsort($auc);
		print_r($auc);
		
		return round($auc, 4);
	}
	
	public function calculate_auc($rank_fields, $weights = null) {
		$this -> get_project_db();

		if(empty($weights)) {
			$weights = $_GET['scores_weight'];
		}
		
		if(!is_array($rank_fields)) {
			$rank_fields = array($rank_fields);
		}
		
		#get target
		if(!empty($_GET['target_id']) && is_array($_GET['target_id'])) {
			foreach($_GET['target_id'] as $tid) {
				if(!empty($tid)) {
					$target_id[] = (int) $this -> Database -> secure_mysql($tid);
				}
			}
			if(!empty($target_id)) {
				$sql_var[] = "(target_id = ".implode(' OR target_id = ', $target_id).')';
			}
		}
		# get subset
		if(!empty($_GET['subset'])) {
			$subset_tmp = explode('-', $_GET['subset']);
			if($subset_tmp[0] == 'ligand') {
				$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}
	
		if(!empty($ligand_subset)) {
			$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
			#$ligand_subset_join = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
		}
		if(!empty($user_subset)) {
			$subset_join = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
		}
			

		if(preg_match('/%/', $_GET['rankscore_cutoff'])) { # get percentage
			$query = 'SELECT conf.mol_id FROM `'.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' WHERE '.(!empty($sql_var) ? implode(' AND ', $sql_var) : '').';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$this -> rankscore_cutoff = round($this -> Database -> num_rows() * (int)$_GET['rankscore_cutoff'] / 100);
			$rankscore_cutoff = $this -> rankscore_cutoff;
		}
		else {
			$this -> rankscore_cutoff = $_GET['rankscore_cutoff'] ? (int) $this -> Database -> secure_mysql($_GET['rankscore_cutoff']) : 10000;
			$rankscore_cutoff = $this -> rankscore_cutoff;
		}
	
	
	
		foreach ($rank_fields as $field) {
			if(in_array($field, array('h_bonds', 'h_acceptors', 'h_donors', 'salt_bridge', 'arg_clust', 'cscore'))) {
				$order = 'ISNULL('.$field.') ASC, '.$field.' DESC';
			}
			else {
				$order = 'ISNULL('.$field.') ASC, '.$field.' ASC';
			}
			$query = 'SELECT '.$field.', conf.mol_id, conf.name FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' WHERE '.$field.' IS NOT NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '').' ORDER BY '.$order.' LIMIT '.$rankscore_cutoff.';';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			$n = 1; # ordinal number for rows
			$prev = null; # placeholder for previous value to check if it changed
			while ($row = $this -> Database -> fetch_assoc()) {
				if(empty($fields[$field][$row['mol_id']])) {
					$fields[$field][$row['mol_id']] = $n;
					$mols[$row['mol_id']]['name'] = $row['name'];
					# add mol to the list
					$mols[$row['mol_id']][$field] = $n;
					#add contribution to rank_score
					if($_GET['cumulative'] == 0) {
						$rank_score[$row['mol_id']] += (!empty($weights[$field]) || $weights[$field] === 0  ? $weights[$field] : 1)/$n;
					}
				}
				#cumulative ranking seams to work better					
				if($_GET['cumulative'] != 0 ) {
					$rank_score[$row['mol_id']] += (!empty($weights[$field]) || $weights[$field] === 0 ? $weights[$field] : 1)/$n;
				}
				#increase counter only if value changed
				if($prev != $row[$field]) {
					$n++;
					$prev = $row[$field];
				}
			}
			# Clear some memory
			$this -> Database -> free_result();
		
			# escape if empty
#			if(empty($rank_score)) {
#				return false;
#			}
		
			#get sum of weights for normalization
			$sum_of_weights += !empty($weights[$field]) || $weights[$field] === 0 ? abs($weights[$field]) : 1;
		}
		
		$act = array();
		$noa = array();
		# get actives and non-actives
		$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' LEFT JOIN '.$this -> project.'docking_assays_data AS act ON act.mol_id = conf.mol_id WHERE act.act_value IS NOT NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '');
		#echo $query.'</br>';
		$this -> Database -> query($query);
		while($row = $this -> Database -> fetch_assoc()) {
			$act[] = $row['mol_id'];
		}
		$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$subset_join.' '.$ligand_subset_join.' LEFT JOIN '.$this -> project.'docking_assays_data AS act ON act.mol_id = conf.mol_id WHERE act.act_value IS NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '');
		#echo $query.'</br>';
		$this -> Database -> query($query);
		while($row = $this -> Database -> fetch_assoc()) {
			$noa[] = $row['mol_id'];
		}
		$num_act = count($act);
		$num_noa = count($noa);
			
		
		$count_act = 0;
		$count_noa = 0;
		
		#sort by scores
		arsort($rank_score);
		
		#apply limits
		if(!empty($_GET['limit'])) {
			$rank_score = array_slice($rank_score, 0, (int) $_GET['limit'], true);
		}
		
		foreach($rank_score as $mol => $score) {
			# generate data for ROCs chart
			if(in_array($mol, $act)) {
				$count_act++;
				 
			}
			elseif(in_array($mol, $noa)) {
				$count_noa++;
			}
		
			if($last['act'] < $count_act && $last['noa'] < $count_noa) {
				$chart[] = array($count_noa/$num_noa, $count_act/$num_act);
				$last['act'] = $count_act;
				$last['noa'] = $count_noa;
			}
		}
		
		#count AUC
		if(!empty($chart)) {
			$auc = 0;
			foreach($chart as $v) {
				if(!$last) {
					$last = array(0,0);
				}
				$auc += 0.5*($v[1]+$last[1])*abs($v[0]-$last[0]);
				$last = $v;
			}
			#add AUC for (1,1) point
			if($last[0] != 1 || $last[1] != 1) {
				$auc += 0.5*(1+$last[1])*abs(1-$last[0]);
			}
		}
		#$this -> draw_rocs_chart($chart);
		
		return round($auc, 4);
	}
	
	public function data_assessment() {
		$this -> get_project_db();

		# fields for which we calculate rankscore, second parameter is to determine whether parameter should be sorted DESC
		$rank_fields = $_GET['scores'];

		$weights = $_GET['scores_weight'];
		
		#get target
		if(!empty($_GET['target_id']) && is_array($_GET['target_id'])) {
			foreach($_GET['target_id'] as $tid) {
				if(!empty($tid)) {
					$target_id[] = (int) $this -> Database -> secure_mysql($tid);
				}
			}
			if(!empty($target_id)) {
				$sql_var[] = $active_sql_var[] = $inactive_sql_var[] = "(target_id = ".implode(' OR target_id = ', $target_id).')';
			}
		}
		# get active subset
		if(!empty($_GET['active_subset'])) {
			$subset_tmp = explode('-', $_GET['active_subset']);
			if($subset_tmp[0] == 'ligand') {
				$active_ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$active_subset_id = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}
				
		if(!empty($active_ligand_subset)) {
			$active_sql_var[] = 'ligand_subset='.$active_ligand_subset;
		}
		if(!empty($active_subset_id)) {
			$active_subset_join = 'JOIN '.$this -> project.'docking_user_subset_members AS active_subset ON active_subset.conf_id = conf.id AND active_subset.user_subset_id = '.$active_subset_id;
		}
		
		
		# get inactive subset (treat query as inactive)
		if(!empty($_GET['inactive_subset']) || !empty($_GET['query_subset'])) {
			if(!empty($_GET['inactive_subset'])) {
				$subset_tmp = explode('-', $_GET['inactive_subset']);
			}
			elseif(!empty($_GET['query_subset'])) {
				$subset_tmp = explode('-', $_GET['query_subset']);
			}
			if($subset_tmp[0] == 'ligand') {
				$inactive_ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
			elseif($subset_tmp[0] == 'user') {
				$inactive_subset_id = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
			}
		}
		
		if(!empty($inactive_ligand_subset)) {
			$inactive_sql_var[] = 'ligand_subset='.$inactive_ligand_subset;
		}
		if(!empty($inactive_subset_id)) {
			$inactive_subset_join = 'JOIN '.$this -> project.'docking_user_subset_members AS inactive_subset ON inactive_subset.conf_id = conf.id AND inactive_subset.user_subset_id = '.$inactive_subset_id;
		}

		# create common join if possible
		if(!empty($active_ligand_subset) || !empty($inactive_ligand_subset)) {
			$common_var[] = 'ligand_subset IN ('.implode(',', array_filter(array($active_ligand_subset, $inactive_ligand_subset))).')';
		}
		if(!empty($active_subset_id) || !empty($inactive_subset_id)) {
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id IN ('.implode(',', array_filter(array($active_subset_id,$inactive_subset_id))).')';
			$common_var[] = 'user_subset.user_subset_id IS NOT NULL';
		}
		if(!empty($common_var)) {
			$sql_var[] = '('.implode(' OR ', $common_var).')';
		}
		
		$sql_join[] = 'JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id';
		
		$sum_of_weights = 0;
		# get molecules ranked by each feature
		if(is_array($rank_fields)) {
			foreach ($rank_fields as $field) {
				if(in_array($field, array('h_bonds', 'h_acceptors', 'h_donors', 'salt_bridge', 'arg_clust', 'cscore', 'rf_score'))) {
					$order = 'ISNULL('.$field.') ASC, '.$field.' DESC';
				}
				else {
					$order = 'ISNULL('.$field.') ASC, '.$field.' ASC';
				}
				$query = 'SELECT '.$field.', conf.mol_id, conf.name FROM '.$this -> project.'docking_conformations AS conf '.(!empty($sql_join) ? implode(' ', $sql_join) : '').' WHERE '.$field.' IS NOT NULL '.(!empty($sql_var) ? 'AND '.implode(' AND ', $sql_var) : '').' ORDER BY '.$order.';';
				#echo $query.'</br>';
				$this -> Database -> query($query);
				$n = 1; # ordinal number for rows
				$prev = null; # placeholder for previous value to check if it changed
				while ($row = $this -> Database -> fetch_assoc()) {
					if(empty($fields[$field][$row['mol_id']])) {
						$fields[$field][$row['mol_id']] = $n;
						$mols[$row['mol_id']]['name'] = $row['name'];
						# add mol to the list
						$mols[$row['mol_id']][$field] = $n;
						#add contribution to rank_score
						if($_GET['cumulative'] == 0) {
							$rank_score[$row['mol_id']] += (!empty($weights[$field]) || $weights[$field] === 0  ? $weights[$field] : 1)/$n;
						}
					}
					#cumulative ranking seams to work better					
					if($_GET['cumulative'] != 0 ) {
						$rank_score[$row['mol_id']] += (!empty($weights[$field]) || $weights[$field] === 0 ? $weights[$field] : 1)/$n;
					}
					#increase counter only if value changed
					if($prev != $row[$field]) {
						$n++;
						$prev = $row[$field];
					}
				}
				# Clear some memory
				$this -> Database -> free_result();
				#get sum of weights for normalization
				$sum_of_weights += !empty($weights[$field]) || $weights[$field] === 0 ? abs($weights[$field]) : 1;
			}
			
			$act = array();
			$noa = array();
			if(!empty($_GET['active_subset'])) {
				# get actives and non-actives
				$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$active_subset_join.' '.$active_ligand_subset_join.(!empty($active_sql_var) ? ' WHERE '.implode(' AND ', $active_sql_var) : '');
			}
			else {
				# get actives and non-actives
				$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$inactive_subset_join.' '.$inactive_ligand_subset_join.' JOIN '.$this -> project.'docking_assays_data AS act ON act.mol_id = conf.mol_id WHERE act.act_value IS NOT NULL '.(!empty($inactive_sql_var) ? 'AND '.implode(' AND ', $inactive_sql_var) : '');
			}
			#echo $query.'</br>';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$act[] = $row['mol_id'];
			}
			if(!empty($_GET['active_subset'])) {
				$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$inactive_subset_join.' '.$inactive_ligand_subset_join.(!empty($inactive_sql_var) ? ' WHERE '.implode(' AND ', $inactive_sql_var) : '');
				
			}
			else {
				$query = 'SELECT DISTINCT conf.mol_id FROM '.$this -> project.'docking_conformations AS conf '.$inactive_subset_join.' '.$inactive_ligand_subset_join.' LEFT JOIN '.$this -> project.'docking_assays_data AS act ON act.mol_id = conf.mol_id WHERE act.act_value IS NULL '.(!empty($inactive_sql_var) ? 'AND '.implode(' AND ', $inactive_sql_var) : '');
			}
			#echo $query.'</br>';
			$this -> Database -> query($query);
			while($row = $this -> Database -> fetch_assoc()) {
				$noa[] = $row['mol_id'];
			}
			$num_act = count($act);
			$num_noa = count($noa);
			
			if(!empty($rank_score)) {
					
				#sort by scores
				arsort($rank_score);
				
				#apply limits
				if(!empty($_GET['limit'])) {
					$rank_score = array_slice($rank_score, 0, (int) $_GET['limit'], true);
				}
			
				$count_act = 0;
				$count_noa = 0;
			
				#normalize scores
				foreach($rank_score as $mol => $score) {
					# generate data for ROCs chart
					if(in_array($mol, $act)) {
						$count_act++;
						 
					}
					elseif(in_array($mol, $noa)) {
						$count_noa++;
					}
				
					if($last['act'] < $count_act && $last['noa'] < $count_noa) {
						$this -> chart[] = array($count_noa/$num_noa, $count_act/$num_act);
						$last['act'] = $count_act;
						$last['noa'] = $count_noa;
					}
				
					$rank_score[$mol] = $score / $sum_of_weights;
				}

				#pass scores
				$this -> rank_score = $rank_score;
		
				#get mols sorted by rank score
				$mols_sorted = array_keys($rank_score);
				
				#$this -> optimize_weights($rank_fields);
				
				# some simple GA
				
				#create initial generation
#				for($i=0;$i<$ga_size;$i++) {
#					foreach ($rank_fields as $field) {
#						$ga_weights[$i][$field] = $w[array_rand($w)];
#					}
#					# score initial
#					$auc = $this -> calculate_auc($rank_fields, $ga_weights[$i]);
#					$ga_population["$auc"] = $ga_weights[$i];
#				}
#				krsort($ga_population);
				
				# conserve best solutions
				
#				# evolve
#				for($g=0;$g<$ga_num;$g++) {
#					for($i=0;$i<$ga_size;$i++) {
#						if($i=0;)
#						foreach ($rank_fields as $field) {
#							$ga_weights[$i][$field] = mt_rand() / mt_getrandmax();
#						}
#						# score initial
#						$auc = $this -> calculate_auc($rank_fields, $ga_weights[$i]);
#						$ga_population["$auc"] = $ga_weights[$i];
#					}	
#				}
				print_r($ga_population);
		
				#get molecules data
				$first = ($this -> page - 1) * $this -> per_page;
				$last = $this -> page * $this -> per_page;
				for($i=$first;$i<$last;$i++){
					if(empty($mols_sorted[$i])) {
						break;
					}
					$mol = array();
					foreach($rank_fields as $f) {
						$mol[$fields[0]] = $fields[$fields[0]][$mols_sorted[$i]];
					}
					$mol['rank_score'] = $rank_score[$mols_sorted[$i]];
					$mol = array_merge($mol, $mols[$mols_sorted[$i]]);
			
					#get name and smiles
					$query = 'SELECT name, smiles FROM '.$this -> project.'docking_molecules WHERE id = '.$mols_sorted[$i].' LIMIT 1';
					#echo $query.'</br>';
					$this -> Database -> query($query);
					$row = $this -> Database -> fetch_row();
					$mol['name'] = $row['name'];
					$mol['smi'] = $row['smiles'];
					$mol['mol_id'] = $mols_sorted[$i];
					$this -> mols[] = $mol;
				}
				$this -> result_num = count($mols_sorted);
			}
		}
	}
	
	public function data_charts() {
		$this -> get_project_db();
		

		#get group size
		$group_size = (int) $this -> Database -> secure_mysql($_GET['group_size'] ? $_GET['group_size'] : 1);
		
		$series = count($_GET['series_subset']) > 1 ? count($_GET['series_subset']) : 1;
		
		#get score
		$score = $this -> Database -> secure_mysql($_GET['score'] ? $_GET['score'] : $_POST['score']);
		
		if(!empty($score)) {
			if($group_size > 1) {
				$group_field = 'floor('.$score.'/'.$group_size.')*'.$group_size;
			}
			else {
				$group_field = 'floor('.$score.')';
			}
			
			for($i=0;$i<$series;$i++) {
				$sql_var = array();
				$sql_join = array();
				
				#get target
				$target_id = (int) $this -> Database -> secure_mysql($_GET['series_target'][$i]);
				if(!empty($target_id)) {
					$sql_var[] = "target_id = ".$target_id;
				}
				# get subset
				if(!empty($_GET['series_subset'][$i])) {
					$subset_tmp = explode('-', $_GET['series_subset'][$i]);
					if($subset_tmp[0] == 'ligand') {
						$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
					}
					elseif($subset_tmp[0] == 'user') {
						$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
					}
				}
	
				if(!empty($ligand_subset)) {
					#$sql_join[] = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
					$sql_var[] = 'ligand_subset = '.$ligand_subset;
				}
				if(!empty($user_subset)) {
					$sql_join[] = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
				}
				
				$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_molecules_properties as molprop ON conf.mol_id = molprop.id';
				$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations_properties as confprop ON conf.id = confprop.id';
				
				if($_GET['normalize']) {
					$query = 'SELECT count(*) AS c  FROM '.$this -> project.'docking_conformations '.(!empty($sql_var) ? ' WHERE '.implode(' AND ', $sql_var) : '');
					#echo $query.'</br>';
					$this -> Database -> query($query);
					$row = $this -> Database -> fetch_assoc();
					$num = $row['c'];
				}
				
				$query = 'SELECT '.$group_field.' AS score, count(*) AS c  FROM '.$this -> project.'docking_conformations AS conf '.implode(' ', $sql_join).' '.(!empty($sql_var) ? 'WHERE '.implode(' AND ', $sql_var):'').' GROUP BY '.$group_field.' ORDER BY '.$group_field;
				#echo $query.'</br>';
				$this -> Database -> query($query);
				while($row = $this -> Database -> fetch_assoc()) {
					$this -> chart[$row['score']][$i] = $_GET['normalize'] ? round($row['c']/$num, 5)*100 : $row['c'];
				}
			}
			if(is_array($this -> chart)) {
				ksort($this -> chart);
			}
		}
	}
	
	public function import_csv() {
		$this -> get_project_db();
		if($_FILES["csv_file"]["size"] > 0) {
			$lines = explode("\n", file_get_contents($_FILES["csv_file"]["tmp_name"]));
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
		}
		elseif(!empty($_POST['csv'])) {
			$this -> mols = unserialize(base64_decode($_POST['csv']));
		}
		if(!empty($this -> mols)) {
			if(!empty($_POST['type'])) {
				$matchfield_key = array_search($_POST['matchfield'], $_POST['type']);	
				if(!empty($matchfield_key) || $matchfield_key === 0) {
					$this -> mols_match = array();
					if(!empty($_POST['commit'])) {
						#create custom fields
						foreach($_POST['type'] as $key => $field) {
							if($field == 'new') {
								$field_name = $this -> Database -> secure_mysql($_POST['custom_name'][$key]);
								$field_type = $this -> Database -> secure_mysql($_POST['custom_type'][$key]);
#								echo 'INSERT INTO '.$this -> project.'docking_molecules_custom_fields (`name`, `type`) VALUES (\''.$field_name.'\', \''.$field_type.'\');</br>';
								#switch fieldtypes
#								$field_id = $this -> Database -> insert_id();
#								echo 'ALTER TABLE  '.$this -> project.'docking_molecules_custom_data ADD  `field_'.$field_id.'` FLOAT NOT NULL, ADD INDEX (`field_'.$field_id.'`);</br>';
							}
						}
					}
					foreach($this -> mols as $mol) {
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
						if(!empty($row['mol_id'])) {
							$this -> mols_match[$mol[$matchfield_key]] = $row['mol_id'];
						
							if(!empty($_POST['commit'])) {
								# generate SQL for data import activities
								$act_field = array_search('act', $_POST['type']);
								$act_type_field = array_search('act_type', $_POST['type']);
								if(!empty($act_field) || $act_field === 0) {
									$query = 'INSERT INTO  '.$this -> project.'docking_molecules_act (`mol_id`, `act`, `type`) VALUES (\''.$row['mol_id'].'\',  \''.(!empty($mol[$act_field]) ? str_replace(',','.',$mol[$act_field]) : -1).'\',  \''.(!empty($act_type_field) ? $mol[$act_type_field] : '').'\') ON DUPLICATE KEY UPDATE `act` = \''.(!empty($mol[$act_field]) ? str_replace(',','.',$mol[$act_field]) : -1).'\',  `type` = \''.(!empty($act_type_field) ? $mol[$act_type_field] : '').'\';';
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
	
	#placeholders
	
	public function form() {
		return true;
	}
	
	# HTML goes below
	
	public function view_subset_add() {
		echo 'You have added '.$this -> num.' conformations to user subset. <a href="'.$this -> get_link(array('mode' => 'search')).'">Go back to your query</a>.';
	}
	
	public function draw_rocs_chart($chart = null) {
		if(empty($chart) && !empty($this -> chart)) {
			$chart = $this -> chart;
		}
		if(!empty($chart)) {
			#count AUC
			$auc = 0;
			foreach($chart as $v) {
				if(!$last) {
					$last = array(0,0);
				}
				$auc += 0.5*($v[1]+$last[1])*abs($v[0]-$last[0]);
				$last = $v;
			}
			#add AUC for (1,1) point
			if($last[0] != 1 || $last[1] != 1) {
				$auc += 0.5*(1+$last[1])*abs(1-$last[0]);
			}
			
			echo "AUC: ".round($auc,4);
			echo '<script type="text/javascript">
	      			google.load("visualization", "1", {packages:["corechart", "controls"]});
	      			google.setOnLoadCallback(drawChart);
	      			function drawChart() {
	      				var data = google.visualization.arrayToDataTable([
	      					[\'TPF\', \'mean\', \'FPF\'],';
	      		echo '[0, 0, 0],';
	      		foreach($chart as $v) {
	      			echo '['.round($v[0],3).', '.round($v[0],3).', '.round($v[1],3).'],';
	      		}
	      		echo '[1, 1, 1],';
	      		echo '		]);
					
		      			var options = {
							title: \'ROC curve\',
							pointSize: 2,
						}
					
					var chart = new google.visualization.LineChart(document.getElementById(\'chart_div\'));
        				chart.draw(data, options);
	      			}
				</script>
				<div id="chart_div" style="margin: 10px auto; width: 500px; height: 500px;"></div>';
	      	}
	}
	
	function visualizer($target, $ligand) {
		#JSmol
		echo '	
			<script type="text/javascript">
			var jmolApplet0; // set up in HTML table, below

			jmol_isReady = function(applet) {		
				var jmolcmds = [
				//"wireframe only",
				"rec = \\"'.str_replace(array("\n", "\r"), array('\n', ''), $target).'\\"",
				"load \\"@rec\\"",

				"lig = \\"'.str_replace(array("\n", "\r"), array('\n', ''), $ligand).'\\"",
				"load APPEND \\"@lig\\"",
				"set showMultipleBonds off; cpk off; set selectAllModels TRUE;",
				"set backgroundModel 1.1;",
				"wireframe only",
				"select 1.1 and (water or manganese or calcium or magnesium or sodium or potassium) ;wireframe reset;spacefill reset",
				//"color TRANSLUCENT 0.5",
				"select 2.",
				"wireframe 50",
				"color opaque",
				"select none",
				"center 2.1;",
				//"display within(groups,within(10,TRUE,*/2))",
				"zoom 500;",
				"set frank off",
				"set showhydrogens false",
				];
				Jmol.script(jmolApplet0,jmolcmds.join("; "));
			
			}
			';
		
#				//Check WebGL
#				try { gl = canvas.getContext("webgl"); }
#	    			catch (x) { gl = null; }
#				if (gl == null) {
#					try { gl = canvas.getContext("experimental-webgl"); experimental = true; }
#					catch (x) { gl = null; }
#				}
#			
#				if (navigator.javaEnabled()){
#					var use = "JAVA";

#				}
#				else if(gl) {
#					var use = "WEBGL HTML5";
#				}
#				else {
#					var use = "HTML5";
#				}
		
		# Switch between Viewers
		$viewers = array(	'jsmol' => 'HTML5',
					'jsmol-webgl' => 'WEBGL', 
					'jmol' => 'JAVA',
					);
		$viewers_names = array(	'jsmol' => 'JSmol (HTML5)',
					'jsmol-webgl' => 'JSmol (WebGL)', 
					'jmol' => 'Jmol (Oracle Java)',
					);
		if(!empty($_COOKIE['viewer']) && empty($_GET['viewer'])) {
			echo 'var use = "'.$_COOKIE['viewer'].'";';
		}
		elseif(!empty($_GET['viewer'])) {
			echo 'var use = "'.$viewers[$_GET['viewer']].'";';
		}	
		else {
			echo 'var use = "HTML5";';
		}
		
		echo '
			var useSignedApplet = false;
		
			var Info = {
				width: "100%",
				height: $(window).height()*0.8,
				color: "black",
				serverURL: "http://chemapps.stolaf.edu/jmol/jsmol/jsmol.php",
				use: use,
				jarPath: "jsmol/java",
				jarFile: (useSignedApplet ? "JmolAppletSigned.jar" : "JmolApplet.jar"),
				isSigned: useSignedApplet,
				j2sPath: "jsmol/j2s",
				readyFunction: jmol_isReady,
				console: "none", // default will be jmolApplet0_infodiv
			}
			</script>
			<script>
			jmolApplet0 = Jmol.getApplet("jmolApplet0", Info);
			
			</script>';

		echo '<div id="jmol-controls">';
	
		echo '<input class="btn btn" type=button onclick=\'showPrevConformation()\' value=" << Previous Conformation " />';
		echo '<input class="btn btn" type=button onclick=\'showNextConformation()\' value=" Next Conformation >> " />';
		echo '</br>';
		echo '</br>';
	
		echo '<a class="btn btn-mini" href="javascript:Jmol.script(jmolApplet0,\'set antialiasDisplay false\')">faster</a> <a class="btn btn-mini" href="javascript:Jmol.script(jmolApplet0,\'set antialiasDisplay true\')">sharper</a>';
		echo '</br>';
	
		echo '<a class="btn btn-mini" href="javascript:Jmol.script(jmolApplet0,\'set showhydrogens true\')">show H</a> <a class="btn btn-mini" href="javascript:Jmol.script(jmolApplet0,\'set showhydrogens false\')">hide H</a>';
		echo '</br>';
		
		#switch between viewers
		echo 'Select viewer: ';
		echo '<select name="viewer" onchange="window.location=this.value;loading();">';
		foreach($viewers as $v => $value) {
			echo '<option value="'.$this -> get_link(array('viewer' => $v)).'" '.($value == $_COOKIE['viewer'] || $v == $_GET['viewer'] ? 'selected' : '').'>'.$viewers_names[$v].'</option>';
		}
		echo '</select>';
		echo '</div>';
	}
	
	public function view_rocs() {
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		$this -> get_project_db();
		$series = count($_GET['series_subset']) > 1 ? count($_GET['series_subset']) : 1;
		
		$this -> view_form();
		
		$this -> draw_rocs_chart();
	}
	
	public function view_form_data_charts() {
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		$this -> get_project_db();
		
		echo '<form name="scores-form" method=GET>';
		echo '<input type="hidden" name="module" value="molecules" />';
		echo '<input type="hidden" name="mode" value="data_charts" />';
		echo '<input type="hidden" name="project" value="'.$_GET['project'].'" />';
		
		echo 'Select data to draw: <select name="score">';
		foreach ($this -> data_structure as $field) {
			if($field[0] != 'name') {
				$selected = $_GET['score'] == $field[0] ? ' selected' : '';
				echo '<option value="'.$field[0].'"'.$selected.'>'.$field[0].'</option></br>';
			}
		}
		echo '</select>';
		echo '</br>';
		
		echo 'Data series:';
		echo '</br>';
		
		$series = count($_GET['series_subset']) > 1 ? count($_GET['series_subset']) : 1;
		echo '<ul id="series-container">';
		for($i=0;$i<$series;$i++) {
			echo '<li class="series form-horizontal">';
			# show ligand and user subsets
			echo 'Subset: ';
			$this -> view_subsets_selection('series_subset', $i);
		
			#show targets
			echo ' Target: ';
			$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
			$this -> Database -> query($query);
			echo '<select name="series_target[]">';
			echo '<option value="">Any Target</option>'; # empty option to force selection
			if(!$target_total_num) {
				$target_total_num = $this -> Database -> num_rows();# count all targets for future use
			}
			while($row = $this -> Database -> fetch_assoc()) {
				$selected = $row['id'] == $_GET['series_target'][$i] ? ' selected' : '';
				echo '<option value="'.$row['id'].'"'.$selected.'>'.$row['name'].'</option>';
			}
			echo '</select>';
			echo '<button type="button" class="delete-series btn btn-mini btn-danger"><i class="icon-trash icon-white"></i></button>';
			echo '</li>';
		}
		echo '</ul>';
		echo '<button type="button" class="btn btn-mini btn-success" id="add-series"><i class="icon-plus icon-white"></i> Add series</button>';
		echo '</br>';
		echo '</br>';
		
		# show group size
		echo '<div class="form-horizontal">';
		echo 'Data bin size: ';
		echo '<input type="text"class="input-mini" name="group_size" value="'.((int) $_GET['group_size'] > 1 ? (int) $_GET['group_size'] : 1).'"/>';
		echo '</div>';
		
		echo '<label class="checkbox"><input type="checkbox" name="normalize" value=1 '.($_GET['normalize'] ? 'checked' : '').'/>Normalize data (return % population in groups)</label>';
		
		
		
		echo '<input type="submit" class="btn btn-large" value="View chart">';
		echo '</form>';
	}
	
	public function view_data_charts() {
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		$this -> get_project_db();
		$series = count($_GET['series_subset']) > 1 ? count($_GET['series_subset']) : 1;
		
		$this -> view_form();
		
		if(!empty($this -> chart)) {
			echo '	<script type="text/javascript">
	      			google.load("visualization", "1", {packages:["corechart", "controls"]});
	      			google.setOnLoadCallback(drawChart);
	      			function drawChart() {
	      				var data = google.visualization.arrayToDataTable([
	      					[';
	      		echo '\''.$_GET['score'].'\',';
	      		for($i=0;$i<$series;$i++) {
      				if(!empty($y[$i]) || $y[$i] == 0 ) {
      					echo '\'#'.($i+1).' '.$_GET['score'].' population'.( $_GET['series_target'][$i] > 0 ? ' in ' : '').'\',';
      				}
      			}	
	      		echo '],';
	      		foreach($this -> chart as $x => $y) {
	      			echo '['.$x.', ';
	      			for($i=0;$i<$series;$i++) {
	      				if(!empty($y[$i]) || $y[$i] == 0 ) {
	      					echo (!empty($y[$i]) ? $y[$i] : 0).', ';
	      				}
	      			}	      			
	      			echo '],';
	      		}	
	      		echo '		]);
						
					// Create a range slider, passing some options
					var rangeSlider = new google.visualization.ControlWrapper({
						\'controlType\': \'NumberRangeFilter\',
						\'containerId\': \'filter_div\',
						\'options\': {
						\'filterColumnLabel\': \''.$_GET['score'].'\',
						\'width\' : \'700px\',
						}
					});
					
					// Define a pie chart
					var chart = new google.visualization.ChartWrapper({
						\'chartType\': \'LineChart\',
						\'containerId\': \'chart_div\',
						\'options\': {
							title: \''.$_GET['score'].' Chart\',
							curveType: "function",
							pointSize: 2,
						}
					});
					
		      			
					
					var dashboard = new google.visualization.Dashboard(document.getElementById(\'dashboard_div\'));
					dashboard.bind(rangeSlider, chart);
					// Draw the dashboard.
					dashboard.draw(data);
	      			}
				</script>
				<div class="well" style=" width:80%; margin: 0 auto;">
				<div id="dashboard_div">
					<div id="filter_div"></div>
					<div id="chart_div" style="margin: 10px auto; width: 90%; height: 500px;"></div>
				</div>
				</div>
	      			';
	      	}
	
	}
	
	public function view_form_import_csv() {
		if(empty($this -> mols) || $_GET['mode'] != 'import_csv') {
			echo '<form method="POST" enctype="multipart/form-data" action="'.$this->get_link(array('mode' => 'import_csv'), array(),array('project', 'module', 'mode')).'">';
			echo 'CSV:';
			echo 'Import file:';
			echo '<input type="file" name="csv_file" id="csv_file" />';
			echo '<input type=submit value="Submit file">';
			echo '</br>';
			echo 'Delimiter: ';
			echo '<select name="delimiter"><option value="auto">Autodetect</option><option value=";">Semicolon - ;</option><option value=",">Coma - ,</option><option value=" ">Space - " "</option><option value="\t">Tab - "\t"</option></select>';
			echo 'Enclosure: ';
			echo '<select name="enclosure"><option value=\'"\'>"</option></select>';
			echo '</form>';
		}
		elseif(!empty($this -> result_num) && $_GET['mode'] == 'import_csv') {
			echo 'Data for '.$this -> result_num.' molecules was updated. <a href="'.$this->get_link(array('mode' => 'import_csv'), array(),array('project', 'module', 'mode')).'">Reset form.</a>';
		}
		else {
			echo '<form method="POST" enctype="multipart/form-data" action="'.$this->get_link().'">';
			#draw table with CSV file to assign fields
			echo $_POST['csv_name'].'</br>';
			echo '<input type="hidden" name="csv" value="'.base64_encode(serialize($this -> mols)).'">';
			
			# options to mach molecules by givven field
			echo 'Match molecules by: ';
			echo '	<select name="matchfield">
					<option value="name" '.($_POST['matchfield'] == 'name' ? 'selected' : '').'>Name</option>
					<option value="smiles" '.($_POST['matchfield'] == 'smiles' ? 'selected' : '').'>SMILES</option>
				</select>';
			echo '<input type="submit" value="Apply">';	
			echo '</br>';
			
			echo 'Draw SMILES <input type="checkbox" name="draw_smiles" '.($_POST['draw_smiles'] ? 'checked' : '').' onChange="this.form.submit()">';
			echo '</br>';
#			echo '<input type="submit" value="Preview changes">';
			
			echo '<input type="hidden" name="commit" value="0">';
			echo '<input type="button" value="Commit changes" onClick="if(confirm(\'Commit data to DiSCuS? Duplicates will be overwritten!\')){this.form.elements[\'commit\'].value=\'1\',this.form.submit()}">';
		}
	}
	
	public function view_import_csv() {
		$this -> view_form();

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
					echo '	<select name="type[]" onChange="this.form.submit()">
							<option value=""></option>
							<option value="new">New field</option>
							<option value="smiles">SMILES</option>
							<option value="name">Name</option>
							<option value="act">Activity</option>
							<option value="act_type">Activity Type</option>
						</select>';
				}
				elseif($_POST['type'][$i] == 'new') {
					echo '<input type="hidden" name="type['.$i.']" value="'.$_POST['type'][$i].'">';
					echo '<input type="text" name="custom_name['.$i.']" value="'.$_POST['custom_name'][$i].'">';
					echo '	<select name="custom_type['.$i.']">
							<option value="int" '.($_POST['custom_type'][$i] == 'int' ? 'selected' : '').'>Integer</option>
							<option value="float" '.($_POST['custom_type'][$i] == 'float' ? 'selected' : '').'>Float</option>
							<option value="varchar" '.($_POST['custom_type'][$i] == 'varchar' ? 'selected' : '').'>Text</option>
						</select>';
					echo '<input type="submit" value="Apply">';
				}
				else {
					echo '<input type="hidden" name="type['.$i.']" value="'.$_POST['type'][$i].'">';
					echo $_POST['type'][$i];
					
				}
				if(!empty($_POST['type'][$i])) {
					echo ' <input type="button" value="X" onClick="this.form.elements[\'type['.$i.']\'].value=\'\';this.form.submit()">';
				}
				echo '</th>';
			}
			echo '</tr>';
			# Print data
			$n = 0; # even or odd
			$matchfield_key = !empty($_POST['type']) ? array_search($_POST['matchfield'], $_POST['type']) : false;
			foreach ($this -> mols as $row) {
				$style = ($n&1) ? 'odd' : 'even';
				echo '<tr class="'.$style.'">';
				echo '<td>'.($n+1).'</td>';
				#show match status
				echo '<td>';
				if(!empty($this -> mols_match[$row[$matchfield_key]])) {
					echo '<font color="green">OK</font>';
				}
				#if machfield is not assigned then dont fail
				elseif($matchfield_key === false ) {
					echo '-';
				}
				else {
					echo '<font color="red">Fail</font>';
				}
				echo '</td>';
				foreach ($row as $key => $value) {
					if($_POST['type'][$key] == 'smiles' && $_POST['draw_smiles']) {
					#draw SVG
						echo '<td><img src="openbabel_ajax.php?smiles='.rawurlencode($value).'&output=svg" width="100" height="100"/></td>';
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
#			echo '<input type=submit value="Submit changes">';
			echo '<input type="button" value="Commit changes" onClick="if(confirm(\'Commit data to DiSCuS? Duplicates will be overwritten!\')){this.form.elements[\'commit\'].value=\'1\',this.form.submit()}">';
			echo '</form>';

		}		
		
	}
	
	public function view_form_data_assessment() {
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		$this -> get_project_db();
		
		echo '<form name="scores-form" method=GET>';
		echo '<input type="hidden" name="module" value="molecules" />';
		echo '<input type="hidden" name="mode" value="data_assessment" />';
		echo '<input type="hidden" name="project" value="'.$_GET['project'].'" />';
		echo '<input type="hidden" name="pp" value="'.$_GET['pp'].'" />';
		
		echo '<div class="rankscore_block">';
		
		
		
		
		?>
		<div class="accordion" id="accordion-rankscore">
		  <div class="accordion-group">
		    <div class="accordion-heading">
		      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-rankscore" href="#accordion-rankscore-one">
			Compute AUC/ROC
		      </a>
		    </div>
		    <div id="accordion-rankscore-one" class="accordion-body <?php if(!empty($_GET[inactive_subset]) || !empty($_GET[active_subset])) {echo 'in';} else {echo 'collapse';}?>">
		      <div class="accordion-inner">
		<?php
		# show ligand and user subsets
		echo 'Inactive subset: ';
		$this -> view_subsets_selection('inactive_subset');
		echo '</br>';
		
		echo 'Active subset: ';
		$this -> view_subsets_selection('active_subset');
		echo '</br>';
		?>
		   </div>
		    </div>
		  </div>
		  <div class="accordion-group">
		    <div class="accordion-heading">
		      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-rankscore" href="#accordion-rankscore-two">
			Manual RankScore
		      </a>
		    </div>
		    <div id="accordion-rankscore-two" class="accordion-body <?php if(!empty($_GET[query_subset])) {echo 'in';} else {echo 'collapse';}?>">
		      <div class="accordion-inner">
		<?php
		# show ligand and user subsets
		echo 'Query subset: ';
		$this -> view_subsets_selection('query_subset');
		echo '</br>';
		?>
		      </div>
		    </div>
		  </div>
		</div>
		<?php

		echo 'Targets:';
		$min_targets = 1;
		$targets = count($_GET['target_id']) > $min_targets ? count($_GET['target_id']) : $min_targets;
		echo '<ul class="container_target">';
		for($t=0;$t<$targets;$t++) {
			echo '<li class="form-horizontal">';
			
			$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
			$this -> Database -> query($query);
			echo '<select name="target_id[]">';
			#echo '<option value=""></option>'; # empty option to force selection
			echo '<option value="">Any Target</option>'; # empty option to force selection
			if(!$target_total_num) {
				$target_total_num = $this -> Database -> num_rows();# count all targets for future use
			}
			while($row = $this -> Database -> fetch_assoc()) {
				$selected = $row['id'] == $_GET['target_id'][$t] ? ' selected' : '';
				echo '<option value='.$row['id'].$selected.'>'.$row['name'].'</option>';
			}
			echo '</select>';
		
			#remove target
			echo '<button type="button" class="rankscore_delete_target btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></button>';

			echo '</li>';
		}
		echo '</ul>';
		echo '<button type="button" class="rankscore_add_target btn btn-success btn-mini"><i class="icon-plus icon-white"></i> Add target</button>';
		echo '</br>';
		echo '</br>';
		
		# choose cumulative mode or best conformation
#		echo 'Rank Mode: ';
#		echo '<select name="cumulative">';
#		echo '<option value="1"'.($_GET['cumulative'] != 0 ? ' selected' : '').'>Cumulative</option>';
#		echo '<option value="0"'.($_GET['cumulative'] == 0 ? ' selected' : '').'>Best conformer</option>';
#		echo '</select>';
		
		echo '</br>';
		echo '</br>';
		
		echo '<input type="submit" value="Submit data assessment" class="btn btn-large">';
		
#		echo '</br>';
#		echo '</br>';	
#		
#		#limit
#		echo 'Limit results: ';
#		echo '<input type="text" name="limit" class="input-mini" value="'.((int) $_GET['limit']).'"/> (0 = unlimited)';

			
		
		
		echo '</div>';
		echo '<div class="rankscore_block">';
		
		if((!empty($_GET[inactive_subset]) || !empty($_GET[active_subset]) || !empty($_GET[query_subset])) && count($_GET['scores']) == 0) {
			echo '<div class="alert alert-error">You must select at least one feature to compute ROC/RankScore!</div>';
		}
		
		# show scores
		echo '<table class="table table-striped table-condensed">';
		foreach ($this -> data_structure as $field) {
			if($field[0] != 'name' && ($field[2] == 0 || $field[2] == 2)) {
			echo '<tr>';
				$selected = is_array($_GET['scores']) && in_array($field[0], $_GET['scores']) ? 'checked' : '';
				echo '<td><label class="checkbox"><input type="checkbox" name="scores[]" value="'.$field[0].'"'.$selected.'>'.$field[1].'</label></td>';
				#echo '<td>Weight: <input class="input-mini" type="text" name="scores_weight['.$field[0].']" size="1" value="'.( !empty($_GET['scores_weight'][$field[0]]) ? $_GET['scores_weight'][$field[0]] : 1 ).'"></td><td>AUC: </td>';#'.$this -> calculate_auc($field[0]).'</td>';
			echo '</tr>';
			}
		}
		echo '</table>';
		echo '<input type="button" name="selall-scores[]" value="Select / Deselect all" class="btn btn-mini"><input type="button" name="invert-scores[]" value="Invert selection" class="btn btn-mini">';
		echo '</form>';
		echo '</div>';
		echo '<div class="rankscore_block">';
		if($_GET['mode'] == 'data_assessment') {
			$this -> draw_rocs_chart();
		}
		echo '</div>';
		echo '<div style="clear: both;"></div>';
	}
	
	public function view_data_assessment() {
		$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		$this -> get_project_db();
		
		$this -> view_form();

		if(!empty($this -> mols)) {
			echo '</br>';
			echo '</br>';
			
			# print short summary
			echo 'Data assesment includes '.$this -> result_num.' molecules.<br>';
			# show pages
			$this -> pagination();
			# Open Selection form
			echo '<form name="selection-form" method=GET>';
			echo '<input type="hidden" name="project" value="'.$project.'" />';
			echo '<input type="hidden" name="mode" value="download_molecule" />';
			echo '<input type="hidden" name="format" value="'.$_GET['format'].'" />';
									
			# show table
			echo '<table class="molecules conformations"><thead><tr>';
			# get opposite sorting type
			$sort = $_GET['sort'] ? $_GET['sort'] : $_POST['sort'];
			$sort_type = $_GET['sort_type'] ? $_GET['sort_type'] : $_POST['sort_type'];
			foreach ($this -> data_structure as $field) {
				if (!@in_array($field[0], $this -> hide) && @in_array($field[0], $_GET['scores']) || $field[0] == 'name') {
					#show arrow when sorting by
					if ($sort ==  $field[0]) {
						switch($sort_type) {
							case 'asc':
							$sub = array('sort' => $field[0], 'sort_type' => 'desc', 'page' => 1);
							$arrow = '<i class="icon-arrow-up"></i>';
							break;
							case 'desc':
							$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
							$arrow = '<i class="icon-arrow-down"></i>';
							break;
						}
					}
					else {
						$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
						$arrow = '';
				
					}
				
					#to remove?
					# Ad colspan to name 
					if ($field[0] == 'name') {
						# index field
						echo '<th>#</th>';
						echo '<th class="selection"><input type="checkbox" name="selall-conf_id[]"/></th><th class="name">';
					}
					else {
						echo '<th>';
					}			
					echo '<a href="'.$this -> get_link($sub).'">'.$field[1].' '.$arrow.'</a></th>';
					if ($field[0] == name) {
						echo '</th><th class="action">Rank Score</th>';
					}
					else {
						echo '</th>';
					}
				}
			}
			echo '</tr></thead>';
			echo '<tbody>';
			# Print data
			$num = 1;
			foreach ($this -> mols as $mol) {
				$style = ($num&1) ? 'odd' : 'even';
				if(is_array($mol)) { # display only existing conformers
					echo '<tr id="row-conf-'.$num.'" class="'.$style.'">';
					foreach ($this -> data_structure as $field) {
						if ($field[0] == 'name' ) {
							# index field
							echo '<td id="mol-num-'.$mol[$tid]['mol_id'].'" class="normal">'.((($this -> page - 1) * $this -> per_page)+$num).'</td>';
							# show checkbox
							echo '<td class="selection"><input type="checkbox" name="conf_id[]" name="selall-conf_id[]" value="'.$mol['id'].'"/></td>';
							$img_size_prev = 100; # small IMG size
							$img_size_big = 300;
							echo '<td>'.$mol['name'].'</br>';
							echo '<img src="openbabel_ajax.php?smiles='.rawurlencode($mol['smi']).'&output=svg" class="mol" width="'.$img_size_prev.'" height="'.$img_size_prev.'"/></a>';
							echo '</td>';
							echo '<td class="name">'.round($mol['rank_score'],4).'</td>'; 
						}
						elseif (@in_array($field[0], $this -> hide)) {
							echo '';
						}
						elseif ( @in_array($field[0], $_GET['scores'])){
							echo '<td>'.(!empty($mol[$field[0]]) ? $mol[$field[0]] : '&#8734;').'</td>';
						}
					}
					echo '</tr>';
					$num++;
				}
			}
			echo '</tr></tbody></table>';
			# Close selection form
			echo '</form>';
			# show pages
			$this -> pagination();
		}
	}
	
	public function view_molecule() {
		
		$this -> view_form();
		
		echo '<div id="content-wrapper">';
		
		echo '<ul class="nav nav-tabs" id="moltab">';
  		echo '<li class="active"><a href="#tab-properities" data-toggle="tab">Properietes</a></li>';
  		
  		#count all
  		$conf_num = 0;
  		if(!empty($this -> targets)) {
			foreach($this -> targets as $tid => $ldb) {
				foreach($ldb as $ligand_subset) {
					$conf_num += $ligand_subset['c'];
				}
	  		}
  		}
  		echo '<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Docked Conformers <span class="badge '.($conf_num > 0 ? 'badge-info' : 'badge-important').'">'.$conf_num.'</span> <b class="caret"></b></a>';
		echo '<ul class="dropdown-menu">';
			if(!empty($this -> targets)) {
				foreach($this -> targets as $tid => $ldb) {
					# get sum
					$conf_num = 0;
					foreach($ldb as $ligand_subset) {
						$conf_num += $ligand_subset['c'];
					}
					echo '<li class="dropdown-submenu '.($_GET['target_id'] == $tid ? 'active' : '').'"><a href="#"><span class="badge badge-info">'.$conf_num.'</span> '.$ldb[0]['target_name'].'</a>';
					echo '<ul class="dropdown-menu">';
					foreach($ldb as $ligand_subset) {
						echo '<li class="'.($_GET['target_id'] == $tid && $_GET['subset'] == 'ligand-'.$ligand_subset['ligand_subset'] ? 'active' : '').'"><a href="'.$this -> get_link(array('target_id' => $tid, 'subset' => 'ligand-'.$ligand_subset['ligand_subset'])).'"><span class="badge badge-info">'.$ligand_subset['c'].'</span> '.$ligand_subset['ldb_name'].'</a>';
					}
					echo '</ul>';
					echo '</li>';
				}
			}
		echo '</ul>';
		echo '</li>';

  		echo '<li><a href="#tab-annotations" data-toggle="tab">Annotations <span class="badge badge-important">0</span></a></li>';
  		echo '<li class="disabled"><a href="#tab-bioactivity" data-toggle="tab">Biactivity</a></li>';
		echo '</ul>';
		
		echo '<div class="tab-content">';
		echo '<div id="tab-properities"  class="tab-pane '.(empty($_GET['target_id']) || empty($_GET['subset']) ? 'active' : '').'">';
		
		echo '<div id="summary-image">';
		
		echo '<img src="openbabel_ajax.php?smiles='.rawurlencode($this -> mol['smiles']).'&output=svg" width="250" height="250"/>';
		
		echo '</div>';
		
		$key_input = array('smiles', 'smiles_unified','inchi','inchi_key');
		
		echo '<dl class="dl-horizontal">';
		foreach($this -> mol['properties'] as $key => $value) {
			if(in_array($key, $key_input)) {
				echo '<dt><b>'.$key.'</b>:</dt><dd><input type=text value="'.$value.'" class="input-large"></dd>';
			}
			else {
				echo '<dt><b>'.$key.'</b>:</dt><dd>'.$value.'</dd>';
			}
		}
		echo '</dl>';
		echo '</div>';
		
		echo '<div id="tab-annotations"  class="tab-pane">';
		# use jQuery to fetch from unichem
		?>
		<script>
		$(function() {
			$('a[href="#tab-annotations"]').children('span').map(function() {
				$(this).toggleClass('badge-important');
				$(this).text('Searching');
			});
			$.getJSON("./unichem_json.php?inchikey=<?=$this -> mol['properties']['inchi_key']?>",function(data) { 
				if(data['error'] == undefined) {
					$.each(data, function(i, item) {
						$('#links_out').append('<li><a href="' + item.base_id_url + item.src_compound_id[0] + '" target=_blank><span class="label">' + item.name_label + '</span> ' + item.src_compound_id[0] + '</a></li>');
					});
				}
				if($('#links_out > li').length > 0) {
				 	$('a[href="#tab-annotations"]').children('span').map(function() {
						$(this).addClass('badge-info');
						$(this).text($('#links_out > li').length);
					});
				 }
				 else {
				 	$('a[href="#tab-annotations"]').children('span').map(function() {
						$(this).addClass('badge-important');
						$(this).text('0');
					});
				 }
			 });
		});
		</script>
		<?php
		
		
		echo 'Links out:<ul id="links_out">';
		
#		#use unichem			
#		$unichem = $this -> fetch_rest('https://www.ebi.ac.uk/unichem/rest/verbose_inchikey/'.$this -> mol['properties']['inchi_key'],3);
#		
#		echo 'https://www.ebi.ac.uk/unichem/rest/verbose_inchikey/'.$this -> mol['properties']['inchi_key'];
##		print_r($unichem);
#		
#		if(!empty($unichem) && empty($unichem['error'])) {
#			foreach($unichem as $u) {
#				switch($u['src_id']) {
#					case 1: # Chembl
#					$chembl_id = $u['src_compound_id'][0];
#					echo '<li><a href="'.$u['base_id_url'].$chembl_id.'" target=_blank>Chembl ID: '.$chembl_id.'</a>';
#					break;
#					
#					case 9: # ZINC
#					$zinc_id = $u['src_compound_id'][0];
#					echo '<li><a href="http://zinc.docking.org/substance/'.preg_replace('/^ZINC[0]+/', '', $zinc_id).'" target=_blank>ZINC ID: '.$zinc_id.'</a>';
#					break;
#					
##					case 13: #Patent
##					echo '<li><a href="http://www.google.com/patents/'.$u['src_compound_id'].'?cl=en" target=_blank>Patent ID: '.$u['src_compound_id'].'</a>';
##					break;
#					default:
#					if($u['base_id_url_available'] == 1) {
#						echo '<li><a href="'.$u['base_id_url'].$u['src_compound_id'][0].'" target=_blank>'.$u['name'].' ID: '.$u['src_compound_id'][0].'</a>';
#					}
#					break;
#				}
#			}
#		}
#		
#		# get CHEMBLID
#		if(empty($chembl_id)) {
#			$chembl = $this -> fetch_rest('https://www.ebi.ac.uk/chemblws/compounds/stdinchikey/'.$this -> mol['properties']['inchi_key'].'.json');
#			if(!empty($chembl)) {
#				$chembl_id = $chembl['compound']['chemblId'];
#			}
#			if(!empty($chembl_id)) {
#				echo '<li><a href="https://www.ebi.ac.uk/chembldb/compound/inspect/'.$chembl_id.'" target=_blank>Chembl ID: '.$chembl_id.'</a>';
#			}
#			else {
#				echo '<li><a href="https://www.ebi.ac.uk/chembl/compound/inchikey/'.$this -> mol['properties']['inchi_key'].'" target=_blank>Chembl</a>';
#			}
#		}
#		
#		# get PubChem CID
#		$pubchem = $this -> fetch_rest('http://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/inchikey/'.$this -> mol['properties']['inchi_key'].'/cids/JSON');
#		if(!empty($pubchem)) {
#			$pubchem_cid = $pubchem['IdentifierList']['CID'][0];
#		}
#		if(!empty($pubchem_cid)) {
#			echo '<li><a href="https://pubchem.ncbi.nlm.nih.gov/summary/summary.cgi?cid='.$pubchem_cid.'" target=_blank>PubChem CID: '.$pubchem_cid.'</a>';
#			echo '<li><a href="https://pubchem.ncbi.nlm.nih.gov/assay/assay.cgi?cid='.$pubchem_cid.'" target=_blank>PubChem BioAssays</a>';
#			
#		}
#		else {
#			echo '<li><a href="http://www.ncbi.nlm.nih.gov/sites/entrez?cmd=search&db=pccompound&term=%22'.$this -> mol['properties']['inchi_key'].'%22[InChIKey]" target=_blank>Pubchem</a>';
#		}
		
		echo '</ul></br>';
		echo '</div>';
		
		echo '</div>';
		echo '</div>';
		
		if(!empty($this -> mols)) {
			$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
			$mol_id = (int) $this -> Database -> secure_mysql($_GET['mol_id'] ? $_GET['mol_id'] : $_POST['mol_id']);
			$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		
			# get target structure
			$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
			$query = 'SELECT mol2 FROM '.$this -> project.'docking_targets WHERE id = '.$target_id.';';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			$target_mol2 = $row[0];
		
			foreach ($this -> mols as $mol) {
				$mol2 .= $mol['mol2'];
			}
			
			# add Jmol ancor
			echo '<a id="3d"></a>';
			
			# Init jmol
			# Show Jmol window with receptor
			echo '<div class="container-fluid">';
			echo '<div class="row-fluid">';
			echo '<div class="span5">';
			echo '<div id="jmol-wrapper">';
			$this -> visualizer($target_mol2, $mol2);			
			echo '</div>'; # jmol-wrapper
			echo '</div>';
			#Show conformation table
			echo '<div id="conformation-table-wrapper" class="molecules">';
			# Open Selection form
			echo '<form name="selection-form" method=GET>';
			echo '<input type="hidden" name="mode" value="download_molecule" />';
			echo '<input type="hidden" name="project" value="'.$project.'" />';
			echo '<input type="hidden" name="format" value="'.$_GET['format'].'" />';							
			# show table
			echo '<table id="conformation-table" class="molecules conformations" cellspacing="0"><thead><tr>';
			# get opposite sorting type
			$sort = $_GET['sort'] ? $_GET['sort'] : $_POST['sort'];
			$sort_type = $_GET['sort_type'] ? $_GET['sort_type'] : $_POST['sort_type'];
			foreach ($this -> data_structure as $field) {
				if ($field[2] == 0 || $field[2] == 2) {
					#show arrow when sorting by
					if ($sort ==  $field[0]) {
						switch($sort_type) {
							case 'asc':
							$sub = array('sort' => $field[0], 'sort_type' => 'desc', 'page' => 1);
							$arrow = '<i class="icon-arrow-up"></i>';
							break;
							case 'desc':
							$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
							$arrow = '<i class="icon-arrow-down"></i>';
							break;
						}
					}
					else {
						$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
						$arrow = '';
				
					}
				
					#to remove?
					# Ad colspan to name 
					if ($field[0] == 'name') {
						echo '<th class="selection"><input type="checkbox" name="selall-conf_id[]"/></th><th>';
					}
					else {
						echo '<th>';
					}			
					echo '<a href="'.$this -> get_link($sub).'">'.$field[1].' '.$arrow.'</a></th>';
					if ($field[0] == name) {
						echo '</th><th class="action">Binding profile <div class="badge badge-inverse binding_profile_info"><i class="icon-info-sign icon-white"></i></div>
						<div class="badge badge-warning"><input name="crude-checkbox" type="checkbox"> precise only</div>
						<script>
						$(function() {
							$(".binding_profile_info").popover({trigger: "hover", html : "true", placement: "bottom", title : "Color mapping", content: \'';
						foreach($this -> interaction_types as $int) {
							echo '<div class="label '.$int[0].'" style="width:95%">'.$int[1].'</div></br>';
						}
						
						echo '\'});
						});
						</script>
						</th>';
					}
					else {
						echo '</th>';
					}
				}
			}
			echo '</tr></thead>';
			echo '<tbody>';
			# Print data
			$num = 1;
			foreach ($this -> mols as $mol) {
				$style = ($num&1) ? 'odd' : 'even';
				if(is_array($mol)) { # display only existing conformers
					echo '<tr id="row-conf-'.$num.'" class="'.$style.'" onclick="showClickedConformation('.$num.')">';
					foreach ($this -> data_structure as $field) {
						if ($field[2] == 0 || $field[2] == 2) {
							if ($field[0] == 'name' ) {
								# show checkbox
								echo '<td class="selection"><input type="checkbox" name="conf_id[]" value="'.$mol['id'].'" /></td>';
								$img_size_prev = 100; # small IMG size
								$img_size_big = 300;
								echo '<td class="name">'.$mol['name'].'</td>';
								echo '<td>';
								#build binding profile
								$binding_profile = $mol['binding_profile'];
								
								if(!empty($this -> binding_profile) && !empty($binding_profile)) {
									echo '<div class="binding_profile spacer"></div>';
									foreach($this -> binding_profile as $rid => $res) {
										echo '<div id="'.$rid.'" class="binding_profile_residue">';
										if(!empty($res['precise'])) {
											foreach($res['precise'] as $inter_id => $interaction) {
												if(!empty($binding_profile[$rid]['precise'][$inter_id])) {
													echo '<div class="binding_profile interaction '.$binding_profile[$rid]['precise'][$inter_id][1].'"></div>';
												}
												else {
													echo '<div class="binding_profile"></div>';
												}
											}
										}
										if(!empty($res['crude'])) {
											foreach($res['crude'] as $inter_id => $interaction) {
												if(!empty($binding_profile[$rid]['crude'][$inter_id])) {
													echo '<div class="binding_profile interaction '.$binding_profile[$rid]['crude'][$inter_id][1].' crude"></div>';
												}
												else {
													echo '<div class="binding_profile crude"></div>';
												}
											}
										}
										echo '</div>';
										#add spacer
										echo '<div class="binding_profile spacer"></div>';
									}
									echo '<button type="button" class="binding_profile_preview btn btn-mini"><i class="icon-search"></i></button>';
									echo '<a href="'.$this -> get_link(array('mode' => 'interactions', 'query_id' => $mol['id'])).'" class="btn btn-mini">Find Similar</a>';
								}
								else {
									echo '<span class="label label-warning">No interactions found</span>';
								}
								echo '</td>';
							
							}
							elseif (@in_array($field[0], $this -> hide)) {
								echo '';
							}
							// round numeric fields 
							elseif (in_array($field[0], array('d_score', 'pmf_score', 'gscore', 'chemscore'))) {
								echo '<td>'.round($mol[$field[0]], 2).'</td>';
							}
							else {
								echo '<td>'.$mol[$field[0]].'</td>';
							}
						}
					}
					echo '</tr>';
					$num++;
				}
			}
			echo '</tr></tbody></table>';
			# Close selection form
			echo '</form>';
			# highlight first conformation
			echo '<script>
			var start_conformation = window.location.hash.split(\'-\')[1]
			if(!start_conformation) {
				start_conformation = 1;
			}
			showClickedConformation(start_conformation);max_id='.($num-1).'</script>';
#			echo '
#			<script src="jquery/jquery.tablescroll.js" type="text/javascript"></script>
#			<script>
#			/*<![CDATA[*/

#			jQuery(document).ready(function($)
#			{
#				$(\'#conformation-table\').tableScroll({height: $(window).height()*0.8, containerClass:\'conformation-table-wrapper\'});
#	
#			});

#			/*]]>*/
#			</script>';
			echo '</div>';
			echo '</div>';
			echo '</div>'; #END wrapper
		}
	}
	
	private function view_subsets_selection($select_name = 'subset', $i = null, $onchange = '') {
		global $CONFIG;
		$project = (int) $this -> Database -> secure_mysql($_GET['project']);
		
		# show ligand and user subsets
		$this -> get_project_db();
		$query = 'SELECT id,name FROM '.$this -> project.'docking_ligand_subset;';
		$this -> Database -> query($query);
		echo '<select name="'.$select_name.(!empty($i) || $i === 0 ? '[]' : '').'"'.(!empty($onchange) ? ' onChange="'.$onchange.'"' : '').'>';
		echo '<option></option>'; # empty option to force selection
		if($this -> Database -> num_rows() > 0) {
			echo '<option value="">### Ligand Subsets ###</option>';
			while($row = $this -> Database -> fetch_assoc()) {
				if(empty($i) && $i !== 0) {
					$selected = 'ligand-'.$row['id'] == $_GET[$select_name] ? ' selected' : '';
				}
				else {
					$selected = 'ligand-'.$row['id'] == $_GET[$select_name][$i] ? ' selected' : '';
				}
				echo '<option value="ligand-'.$row['id'].'"'.$selected.'>'.$row['name'].'</option>';
			}
		}
		
		
		$query = 'SELECT subset.id, subset.name, user.id AS owner_id, user.login AS owner FROM '.$this -> project.'docking_user_subset AS subset LEFT JOIN '.$CONFIG['db_name'].'.docking_users AS user ON user.id = subset.user_id';
		$this -> Database -> query($query);
		if($this -> Database -> num_rows() > 0) {
			echo '<option></option>'; 
			echo '<option value="">### User Subsets ###</option>';
			while($row = $this -> Database -> fetch_assoc()) {
				if(empty($i) && $i !== 0) {
					$selected = 'user-'.$row['id'] == $_GET[$select_name] ? ' selected' : '';
				}
				else {
					$selected = 'user-'.$row['id'] == $_GET[$select_name][$i] ? ' selected' : '';
				}
				echo '<option value="user-'.$row['id'].'"'.$selected.'>'.$row['name'].(!empty($row['owner']) && $row['owner_id'] != $this -> User -> id() ? "\t[".$row['owner'].']' : '').'</option>';
			}
		}
		echo '</select>';
	}
	
#	private function view_targets_selection($select_name = 'target') {
#		$project = (int) $this -> Database -> secure_mysql($_GET['project']);
#		$this -> get_project_db();
#		
#		#show targets
#		$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
#		$this -> Database -> query($query);
#		echo '<select name="'.$select_name.'">';
#		echo '<option value="">Any Target</option>'; # empty option to force selection
#		if(!$target_total_num) {
#			$target_total_num = $this -> Database -> num_rows();# count all targets for future use
#		}
#		while($row = $this -> Database -> fetch_assoc()) {
#			$selected = $row['id'] == $_GET[$select_name] ? ' selected' : '';
#			echo '<option value='.$row['id'].$selected.'>'.$row['name'].'</option>';
#		}
#		echo '</select>';
#	}
#	
	public function view_interactions() {
		$this -> view_form();
		
		if(!empty($this -> mols)) {
			# show pages
			$this -> pagination();
			
			$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
			$mol_id = (int) $this -> Database -> secure_mysql($_GET['mol_id'] ? $_GET['mol_id'] : $_POST['mol_id']);
			$project = (int) $this -> Database -> secure_mysql($_GET['project'] ? $_GET['project'] : $_POST['project']);
		
			# get target structure
			$target_id = (int) $this -> Database -> secure_mysql($_GET['target_id'] ? $_GET['target_id'] : $_POST['target_id']);
			$query = 'SELECT mol2 FROM '.$this -> project.'docking_targets WHERE id = '.$target_id.';';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			$target_mol2 = $row[0];
		
			foreach ($this -> mols as $mol) {
				$mol2 .= $mol['mol2'];
			}
			
			# add Jmol ancor
			echo '<a id="3d"></a>';
			
			# Init jmol
			# Show Jmol window with receptor
			echo '<div class="container-fluid">';
			echo '<div class="row-fluid">';
			echo '<div class="span5">';
			echo '<div id="jmol-wrapper">';
			$this -> visualizer($target_mol2, $mol2);
			echo '</div>'; # jmol-wrapper
			echo '</div>';
		
			#Show conformation table
			#echo '<div class="span7" style="overflow-x: scroll">';
			echo '<div id="conformation-table-wrapper" class="molecules">';
			# Open Selection form
			echo '<form name="selection-form" method=GET>';
			echo '<input type="hidden" name="mode" value="download_molecule" />';
			echo '<input type="hidden" name="project" value="'.$project.'" />';
			echo '<input type="hidden" name="format" value="'.$_GET['format'].'" />';							
			# show table
			echo '<table id="conformation-table" class="molecules conformations" cellspacing="0"><thead><tr>';
			# get opposite sorting type
			$sort = $_GET['sort'] ? $_GET['sort'] : $_POST['sort'];
			$sort_type = $_GET['sort_type'] ? $_GET['sort_type'] : $_POST['sort_type'];
			foreach ($this -> data_structure as $field) {
				if ($field[2] == 0 || $field[2] == 2) {
					#show arrow when sorting by
					if ($sort ==  $field[0]) {
						switch($sort_type) {
							case 'asc':
							$sub = array('sort' => $field[0], 'sort_type' => 'desc', 'page' => 1);
							$arrow = '<i class="icon-arrow-up"></i>';
							break;
							case 'desc':
							$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
							$arrow = '<i class="icon-arrow-down"></i>';
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
						echo '<th class="selection"><input type="checkbox" name="selall-conf_id[]"/></th><th>';
					}
					else {
						echo '<th>';
					}			
					echo '<a href="'.$this -> get_link($sub).'">'.$field[1].' '.$arrow.'</a></th>';
					if ($field[0] == name) {
						echo '</th><th class="action"><a href="'.$this -> get_link(array('sort' => 'score', 'sort_type' => 'desc', 'page' => 1)).'">Binding profile</a> <div class="badge badge-inverse binding_profile_info"><i class="icon-info-sign icon-white"></i></div>';
						# show the crude/precise switch only if needed
						if(in_array('crude', (array) $_GET['int_mode'])) {
							echo '<div class="badge badge-warning"><input name="crude-checkbox" type="checkbox"> precise only</div>';
						}
						echo '<script>
						$(function() {
							$(".binding_profile_info").popover({trigger: "hover", html : "true", placement: "bottom", title : "Color mapping", content: \'';
						foreach($this -> interaction_types as $int) {
							echo '<div class="label '.$int[0].'" style="width:95%">'.$int[1].'</div></br>';
						}
						
						echo '\'});
						});
						</script>
						</th>';
					}
					else {
						echo '</th>';
					}
				}
			}
			echo '</tr></thead>';
			echo '<tbody>';
			# Print data
			$num = 1;
			foreach ($this -> mols as $mol) {
				$style = ($num&1) ? 'odd' : 'even';
				if(is_array($mol)) { # display only existing conformers
					echo '<tr id="row-conf-'.$num.'" class="'.$style.'" onclick="showClickedConformation('.$num.')">';
					foreach ($this -> data_structure as $field) {
						if ($field[2] == 0 || $field[2] == 2) {
							if ($field[0] == 'name' ) {
								# show checkbox
								echo '<td class="selection"><input type="checkbox" name="conf_id[]" value="'.$mol['id'].'" /></td>';
								$img_size_prev = 100; # small IMG size
								$img_size_big = 300;
								echo '<td class="name"><a href="'.$this -> get_link(array('mode' => 'molecule', 'mol_id' => $mol['mol_id']), array(), array('project', 'module')).'">'.$mol['name'].'</a></td>';
								echo '<td>';
								
								#build binding profile
								$binding_profile = $mol['binding_profile'];
								
								if(!empty($this -> binding_profile) && !empty($binding_profile)) {
									echo '<div class="binding_profile spacer"></div>';
									foreach($this -> binding_profile as $rid => $res) {
										echo '<div id="'.$rid.'" class="binding_profile_residue">';
										if(!empty($res['precise'])) {
											foreach($res['precise'] as $inter_id => $interaction) {
												if(!empty($binding_profile[$rid]['precise'][$inter_id])) {
													echo '<div class="binding_profile interaction '.$binding_profile[$rid]['precise'][$inter_id][1].'"></div>';
												}
												else {
													echo '<div class="binding_profile"></div>';
												}
											}
										}
										if(!empty($res['crude'])) {
											foreach($res['crude'] as $inter_id => $interaction) {
												if(!empty($binding_profile[$rid]['crude'][$inter_id])) {
													echo '<div class="binding_profile interaction '.$binding_profile[$rid]['crude'][$inter_id][1].' crude"></div>';
												}
												else {
													echo '<div class="binding_profile crude"></div>';
												}
											}
										}
										echo '</div>';
										#add spacer
										echo '<div class="binding_profile spacer"></div>';
									}
									echo '<button type="button" class="binding_profile_preview btn btn-mini"><i class="icon-search"></i></button>';
									echo '<a href="'.$this -> get_link(array('mode' => 'interactions', 'query_id' => $mol['id'])).'" class="btn btn-mini">Find Similar</a>';
								}
								else {
									echo '<span class="label label-warning">No interactions found</span>';
								}
								echo '</td>';
							}
							elseif (@in_array($field[0], $this -> hide)) {
								echo '';
							}
							// round numeric fields 
							elseif (in_array($field[0], array('d_score', 'pmf_score', 'gscore', 'chemscore'))) {
								echo '<td>'.round($mol[$field[0]], 2).'</td>';
							}
							else {
								echo '<td>'.$mol[$field[0]].'</td>';
							}
						}
					}
					echo '</tr>';
					$num++;
				}
			}
			echo '</tr></tbody></table>';
			# Close selection form
			echo '</form>';
			# highlight first conformation
			echo '<script>showClickedConformation(1);max_id='.($num-1).'</script>';
#			echo '
#			<script src="jquery/jquery.tablescroll.js" type="text/javascript"></script>
#			<script>
#			/*<![CDATA[*/

#			jQuery(document).ready(function($)
#			{
#				$(\'#conformation-table\').tableScroll({height: $(window).height()*0.8, containerClass:\'conformation-table-wrapper\'});
#	
#			});

#			/*]]>*/
#			</script>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo '</div>'; #END wrapper	
		
		}
	}
	
	public function view_form_cheminformatics() {
		$project = $this -> Database -> secure_mysql($_GET['project']);
		$target = $this -> Database -> secure_mysql($_GET['target']);
		
		echo '<form method="GET" name="similarity">';
		echo '<input type="hidden" name="module" value="molecules" />';
		echo '<input type="hidden" name="mode" value="cheminformatics" />';
		echo '<input type="hidden" name="project" value="'.$_GET['project'].'" />';
		
		# show ligand and user subsets
		echo 'Subsets: ';
		$this -> view_subsets_selection();
		?>
		<div class="accordion" id="accordion-cheminformatics">
		  <div class="accordion-group">
		    <div class="accordion-heading">
		      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-cheminformatics" href="#collapseOne">
			Similarity
		      </a>
		    </div>
		    <div id="collapseOne" class="accordion-body collapse <?php if(!empty($_GET['smiles'])) { echo 'in';} else { echo '';}?>">
		      <div class="accordion-inner">
		<?php
		    
		
		
		
		# show JSME
		echo '<table><tr><td id="jsme-container"></td></tr></table>';
		echo 'SMILES/SMARTS: <input type="text" id="smiles" name="smiles" value="'.$_GET['smiles'].'" size=50/>';
		echo ' <input class="btn" id="smiles-clean" type="button" value="Clean & redraw"> (SMARTS are not supported)';
		echo '</br>';
		echo '</br>';
		$sim = array('sub' => 'Substructure', '0.95' => "95%", '0.9' => '90%', '0.8' => '80%', '0.7' => '70%', '0.6' => '60%', '0.5' => '50%');
		echo 'Similarity <select type="text" name="similarity" class="input-small">';
		foreach($sim as $s => $s_lab) {
			echo '<option value="'.$s.'" '.($_GET['similarity'] == $s ? 'selected' : '').'>'.$s_lab.'</option>';
		}
		echo '</select>';
		
		echo '</br>';

		?>
		   </div>
		    </div>
		  </div>
		  <div class="accordion-group">
		    <div class="accordion-heading">
		      <a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion-cheminformatics" href="#collapseTwo">
			Molecule features
		      </a>
		    </div>
		    <div id="collapseTwo" class="accordion-body collapse <?php if(!empty($_GET['var'])) { echo 'in';} else { echo '';}?>">
		      <div class="accordion-inner">
		<?php
		# show search criteria
		$min_fields = 1;
		$fields = count($_GET['var'][0]) > $min_fields && $_GET['mode'] == 'cheminformatics' ? count($_GET['var'][0]) : $min_fields;
		echo '<div class="container_var">';
		for($i=0;$i<$fields;$i++) {
			
			echo '<div class="query_var form-horizontal">';
			# show logic operator
			echo '<select name="var_logic[0][]"'.($i == 0 ? ' style="visibility:hidden"' : '').' class="input-small">';
			echo '<option value="AND"'.(($_GET['var_logic'][0][$i] == 'AND' || empty($_GET['var_logic'][0][$i]))  && $_GET['mode'] == 'cheminformatics' ? ' selected' : '').'>AND</option>';
			echo '<option value="OR"'.($_GET['var_logic'][0][$i] == 'OR' && $_GET['mode'] == 'cheminformatics' ? ' selected' : '').'>OR</option>';
			echo '<option value="BUTNOT"'.($_GET['var_logic'][0][$i] == 'BUTNOT' && $_GET['mode'] == 'cheminformatics' ? ' selected' : '').'>BUT NOT</option>';
			echo '</select>';


			echo '<select name="var[0][]" class="input-medium">';
			echo '<option></option>'; # empty option to force selection
	
			foreach ($this -> data_structure as $field) {
				if($field[2] == 0 || $field[2] == 1) {
					$selected = $field[0] == $_GET['var'][0][$i] && $_GET['mode'] == 'cheminformatics' ? ' selected' : '';
					echo '<option value='.$field[0].$selected.'>'.$field[0].'</option>';
				}
			}
			echo '</select>';
		
			#show compare options
			echo '<select name="var_comp[0][]" class="input-mini">';
			foreach ($this -> comp as $key => $value) {
				$selected = $key == $_GET['var_comp'][0][$i] && $_GET['mode'] == 'cheminformatics' ? ' selected' : '';
				echo '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';
			}
			echo '</select>';
			#show input
			echo '<input type="text" name="var_val[0][]" value="'.($_GET['mode'] == 'cheminformatics' ? $_GET['var_val'][0][$i] : '').'" class="input-mini"/>';
		
			# remove var?
			echo '<button type="button" class="query_add_var btn btn-success btn-mini"><i class="icon-plus icon-white" ></i></button>';
			echo '<button type="button" class="query_delete_var btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></button>';
		
			echo '</div>';
		}
		echo '</div>';
		?>
		      </div>
		    </div>
		  </div>
		</div>
		<?php
		$pages = array(10,25,50,100,200);
		
		# sort by options
		echo '<div class="form-horizontal input-prepend input-append">';

		echo '<span class="add-on">Sort by</span>';
		echo '<select name="sort" id="appendedPrependedInput" class="input-medium">';
	
		foreach ($this -> data_structure as $field) {
			if($field[2] == 0 || $field[2] == 1) {
				$selected = $field[0] == $_GET['sort'] ? ' selected' : '';
				echo '<option value='.$field[0].$selected.'>'.$field[0].'</option>';
			}
		}
		echo '</select>';
		
		echo '<select name="sort_type" class="input-small"><option value="asc" '.($_GET['sort_type'] == 'asc' ? 'selected' : '').'>Ascending</option><option value="desc" '.($_GET['sort_type'] == 'desc' ? 'selected' : '').'>Descending</option></select>';
		
		echo '<span class="add-on"></span>';
		echo '</div>';
		echo '</br>';
		
		#limit
		echo '<div class="input-prepend">';
		echo '<span class="add-on">Limit results</span>';
		echo '<input type="text" name="limit" class="input-mini" value="'.((int) $_GET['limit']).'"/>';
		echo '</div>';
		echo '</br>';
		echo '</br>';
		
		echo '<button class="btn btn-large" type="submit">View results</button>';
		
		echo '</form>';
	}
	
	public function view_form_interactions() {
		$project = $this -> Database -> secure_mysql($_GET['project']);
		$target_id = (int) $_GET['target_id'];
		$this -> get_project_db();
		
		echo '<form method="GET">';
		echo '<input type="hidden" name="project" value="'.$_GET['project'].'" />';
		echo '<input type="hidden" name="module" value="molecules" />';
		echo '<input type="hidden" name="mode" value="interactions" />';
		
		# show ligand and user subsets
		echo 'Subsets: ';
		$this -> view_subsets_selection('subset', null, $onchange = 'loading();this.form.submit()');
		echo '</br>';
		
		echo 'Target: ';
		#show targets
		$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
		$this -> Database -> query($query);
		echo '<select name="target_id" onChange="loading();this.form.submit()">';
		echo '<option value=""></option>'; # empty option to force selection
		#echo '<option value="-1">Any Target</option>'; # empty option to force selection
		while($row = $this -> Database -> fetch_assoc()) {
			$selected = $row['id'] == $_GET['target_id'] ? ' selected' : '';
			echo '<option value='.$row['id'].$selected.'>'.$row['name'].'</option>';
		}
		echo '</select>';
		echo '</br>';
		
		if(!is_array($_GET['target_id']) && !empty($target_id)) {
			
			$sql_join[] = 'LEFT JOIN '.$this -> project.'docking_conformations as conf ON interactions.id = conf.id';
			# get subset
			if(!empty($_GET['subset'])) {
				$subset_tmp = explode('-', $_GET['subset']);
				if($subset_tmp[0] == 'ligand') {
					$ligand_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
				elseif($subset_tmp[0] == 'user') {
					$user_subset = (int) $this -> Database -> secure_mysql($subset_tmp[1]);
				}
			}
	
			if(!empty($ligand_subset)) {
				$sql_var[] = 'conf.ligand_subset = '.$ligand_subset;
				#$sql_join[] = 'JOIN '.$this -> project.'docking_ligand_subset_members AS ligand_subset ON ligand_subset.mol_id = conf.mol_id AND ligand_subset.ligand_subset_id = '.$ligand_subset;
			}
			if(!empty($user_subset)) {
				$sql_join[] = 'JOIN '.$this -> project.'docking_user_subset_members AS user_subset ON user_subset.conf_id = conf.id AND user_subset.user_subset_id = '.$user_subset;
			}
			
			
			
			$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project.' AND id ='.$target_id.' LIMIT 1;';
			$this -> Database -> query($query);
			$row = $this -> Database -> fetch_row();
			# read target's mol2 to list residues
			$OBConversion = new OBConversion;
			$OBConversion -> SetInFormat('mol2');
			$target_mol = new OBMol;
			$OBConversion -> ReadString($target_mol, $row['mol2']);
#			foreach(range(1, $target_mol -> NumResidues()) as $res_id) {
#				$residue = $target_mol -> GetResidue($res_id);
#				if(!empty($residue)) {
#					$terget_residues[] = substr($residue -> GetName(),0,3).$res_id;
#				}
#			}
			
			# function to sort residues
			function comp_res($a, $b) {
				return (substr($a,3) > substr($b,3)) ? +1 : -1;
			}
			
			foreach($this -> interaction_types as $int) {
				echo '<div class="interaction_container">';
				echo $int[1].':';
				echo '</br>';
				$residue_list = array();
				$f = array();
				array_push($f, $int[0]);
				if(!empty($int[2])) {
					array_push($f, $int[0].'_crude');
				}
				$query = 'SELECT DISTINCT '.implode(',', $f).' FROM '.$this -> project.'docking_conformations_interactions AS interactions '.implode(' ', $sql_join).' WHERE target_id = '.$target_id.' AND '.$int[0].' != "";';	
				$this -> Database -> query($query);
				#echo $query;
				while($row = $this -> Database -> fetch_assoc()) {
					$residue_list = array_unique(array_filter(array_merge($residue_list, explode('|', $row[$int[0]].'|'.$row[$int[0].'_crude']))));
				}
				usort($residue_list, 'comp_res');
				echo '<select multiple size=10 class="input-small" name="'.$int[0].'_residues[]">';
				foreach($residue_list as $res) {
					$selected = @in_array($res,$this -> binding_profile_query[$int[0]]) || @in_array($res,$this -> binding_profile_query[$int[0].'_crude']) ? ' selected' : '';
					echo '<option value="'.$res.'"'.$selected.'>'.$res.'</option>';
				}
				echo '</select>';
				if(!empty($int[2])) {
					echo '</br>';
					echo '<select class="input-small" name="int_mode['.$int[0].']">';
					echo '<option value="crude" '.($_GET['int_mode'][$int[0]] == 'crude' ? 'selected' : '').'>Crude</option>';
					echo '<option value="precise" '.($_GET['int_mode'][$int[0]] == 'precise' ? 'selected' : '').'>Precise</option>';
					echo '</select>';			
				}
				echo '</div>';
			}
			
			echo '<div class="clearfix" /></div>';
			
			echo '<div id="interactions-query-well" class="well well-small"></div>';
			?>
			<script>
			$(function() {
				
				
				$('div.interaction_container > select').change(function() {
					$('#interactions-query-well').empty()
					$('div.interaction_container > select[name$=\'_residues[]\'] > option:selected').each(function() {
						$('#interactions-query-well').append('<div class="label ' + $(this).parent().attr('name').replace('_residues[]','') + '" style="margin: 5px;">' + $(this).val() + '</div>')
					});
					
					// attach click event every time, or it wouldnt work
					$('#interactions-query-well > div').click(function() {
						res = $(this).text().replace("×", '');
						sel = $(this).attr('class').split(/\s+/)[1];
						
					
						$('div.interaction_container > select[name^="' + sel + '"] > option[value="' + res + '"]').attr('selected', false);
						$('div.interaction_container > select[name^="' + sel + '"]').trigger('focus')
						$('div.interaction_container > select[name^="' + sel + '"]').trigger('change');
					
						$(this).remove();
					});
				});
				$('div.interaction_container > select').first().trigger('change');
			});
			</script>
			<?php
			
			echo '<div class="alert alert-info">Click on residue label to remove from query</div>';
			echo '<div class="well well-small">';
			echo 'Color mapping:';
			foreach($this -> interaction_types as $int) {
				echo "<div class=\"label ".$int[0]."\">".$int[1]."</div>\n";
			}
			echo '</div>';
			
			echo '</br>';
			echo '</br>';
			
			#limit
			echo '<div class="input-prepend">';
			echo '<span class="add-on">Limit results</span>';
			echo '<input type="text" name="limit" class="input-mini" value="'.((int) $_GET['limit']).'"/>';
			echo '</div>';
			echo '</br>';
			echo '</br>';
			
			echo '<input type="submit" class="btn btn-large" value="View results" onClick="loading();" />';
			
		}
		#print_r($terget_residues);
		echo '</form>';
		
	}
	
	public function view_form_subsets() {
		if($this -> result_num > 0) {
			$query = 'SELECT * FROM '.$this -> project.'docking_user_subset';
			$this -> Database -> query($query);
			if($this -> Database -> num_rows() > 0) {
				echo '<form method="POST" action="'.$this -> get_link(array('mode' => 'subset_add')).'">';
				echo '<input type="submit" value="Add query to subset:">';
				echo '<select name="subset_id_add">';
				echo '<option></option>'; # empty option to force selection
				while($row = $this -> Database -> fetch_assoc()) {
					echo '<option value='.$row['id'].'>'.$row['name'].'</option>';
				}
				echo '</select>';
				echo '</form>';
			}
		
			echo '<input type="button" value="Create subset from query" onClick="create_subset(\''.$this -> get_link(array('module' => 'subsets','mode' => 'create', 'subset_name' => '')).'\')">';
		}
		else {
			echo 'There are no molecules.';
		}
	}
	
	public function view_form_downloads() {
		if($this -> result_num > 0) {
			echo '<input type="button" value="Download selected molecules" onClick="document.forms[\'selection-form\'].submit();">';
			if(count($this -> target) > 1) {
				echo ' for : <select name="target[]" onchange="document.forms[\'selection-form\'].target_id.value=this.value;">';
				foreach($this -> target as $tid) {
					echo '<option value='.$tid.'>'.$this -> target_names[$tid].'</option>';
				}
				echo '</select>';
			}
			echo ' as ';
			echo '<select name="target[]" onchange="document.forms[\'selection-form\'].type.value=this.value;">';
			echo '<option value="mol2">Mol2 file</option>';
			echo '<option value="chimera">Chimera Session</option>';
			echo '</select>';

			echo '</br>';
		
			#switch between CSV download modes
			switch($_GET['mode']) {
				case 'data_assessment':
				$csv = array('mode' => 'download_assessment_csv');
				break;
				default:
				$csv = array('mode' => 'download_csv');
				break;
			}
			if(!empty($csv)) {
				echo '<input type="button" value="Download as CSV" onClick="location.href=\''.$this -> get_link($csv).'\'"">';
			}
		}
		else {
			echo 'There are no molecules.';
		}
	}
	
	public function view_form_query() {	
		#show varsstr
		$project = $this -> Database -> secure_mysql($_GET['project']);
		$target = $this -> Database -> secure_mysql($_GET['target']);
		$this -> get_project_db();
		
		echo '<form method="GET">';
		echo '<input type="hidden" name="module" value="molecules" />';
		echo '<input type="hidden" name="mode" value="search" />';
		echo '<input type="hidden" name="project" value="'.$_GET['project'].'" />';
		
		# show ligand and user subsets
		echo 'Subsets: ';
		$this -> view_subsets_selection();

		#show targets
		# show search criteria
		$min_targets = 1;
		$targets = count($_GET['var_target']) > $min_targets && $_GET['mode'] == 'search' ? count($_GET['var_target']) : $min_targets;
		for($t=0;$t<$targets;$t++) {
			echo '<div class="form-horizontal container_target">';
			echo '<div class="form-horizontal">';
			# show logic operator
			echo '<select name="var_target_logic[]"'.($t == 0 ? ' style="visibility:hidden"' : '').' class="input-small">';
			echo '<option value="AND"'.($_GET['var_target_logic'][$t] == 'AND'  && $_GET['mode'] == 'search' ? ' selected' : '').'>AND</option>';
			echo '<option value="OR"'.($_GET['var_target_logic'][$t] == 'OR'  && $_GET['mode'] == 'search' ? ' selected' : '').'>OR</option>';
			echo '<option value="BUTNOT"'.($_GET['var_target_logic'][$t] == 'BUTNOT'  && $_GET['mode'] == 'search' ? ' selected' : '').'>BUT NOT</option>';
			echo '</select>';
			
#			echo 'Target: ';
			$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
			$this -> Database -> query($query);
			echo '<select name="var_target[]">';
			#echo '<option value=""></option>'; # empty option to force selection
			echo '<option value="-1">Any Target</option>'; # empty option to force selection
			if(!$target_total_num) {
				$target_total_num = $this -> Database -> num_rows();# count all targets for future use
			}
			while($row = $this -> Database -> fetch_assoc()) {
				$selected = $row['id'] == $_GET['var_target'][$t] && $_GET['mode'] == 'search' ? ' selected' : '';
				echo '<option value='.$row['id'].$selected.'>'.$row['name'].'</option>';
			}
			echo '</select>';
		
			#remove target
			echo '<button type="button" class="query_delete_target btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></button>';
			echo '</div>';
			echo '<div class="well well-small">';

			# show search criteria
			$min_fields = 1;
			$fields = count($_GET['var'][$t]) > $min_fields && $_GET['mode'] == 'search' ? count($_GET['var'][$t]) : $min_fields;
			echo '<div class="container_var">';
			for($i=0;$i<$fields;$i++) {
				
				echo '<div class="query_var form-horizontal">';
				# show logic operator
				echo '<select name="var_logic['.$t.'][]"'.($i == 0 ? ' style="visibility:hidden"' : '').' class="input-small">';
				echo '<option value="AND"'.(($_GET['var_logic'][$t][$i] == 'AND' || empty($_GET['var_logic'][$t][$i])) && $_GET['mode'] == 'search' ? ' selected' : '').'>AND</option>';
				echo '<option value="OR"'.($_GET['var_logic'][$t][$i] == 'OR' && $_GET['mode'] == 'search' ? ' selected' : '').'>OR</option>';
				echo '<option value="BUTNOT"'.($_GET['var_logic'][$t][$i] == 'BUTNOT' && $_GET['mode'] == 'search' ? ' selected' : '').'>BUT NOT</option>';
				echo '</select>';


				echo '<select name="var['.$t.'][]" class="input-medium">';
				echo '<option></option>'; # empty option to force selection
		
				foreach ($this -> data_structure as $field) {
					if($field[2] == 0 || $field[2] == 2) {
						$selected = $field[0] == $_GET['var'][$t][$i] && $_GET['mode'] == 'search' ? ' selected' : '';
						echo '<option value='.$field[0].$selected.'>'.$field[0].'</option>';
					}
				}
				echo '</select>';
			
				#show compare options
				echo '<select name="var_comp['.$t.'][]" class="input-mini">';
				foreach ($this -> comp as $key => $value) {
					$selected = $key == $_GET['var_comp'][$t][$i] && $_GET['mode'] == 'search' ? ' selected' : '';
					echo '<option value="'.$key.'"'.$selected.'>'.$value.'</option>';
				}
				echo '</select>';
				#show input
				echo '<input type="text" name="var_val['.$t.'][]" value="'.( $_GET['mode'] == 'search' ? $_GET['var_val'][$t][$i] : '').'" class="input-mini"/>';
			
				# remove var?
				echo '<button type="button" class="query_add_var btn btn-success btn-mini"><i class="icon-plus icon-white" ></i></button>';
				echo '<button type="button" class="query_delete_var btn btn-danger btn-mini"><i class="icon-trash icon-white"></i></button>';
			
				echo '</div>';
			}
			echo '</div>';
			
			echo '</div>';

			echo '</div>';
		}
		
		echo '<button type="button" class="query_add_target btn btn-success btn-mini"><i class="icon-plus icon-white"></i> Add target</button>';
		
		echo '</br>';
		echo '</br>';
		
		echo '<label class="checkbox"><input type="checkbox" name="disable_mol_grouping" value="1" '.(!empty($_GET['disable_mol_grouping']) ? 'checked': '').'> Disable conformation grouping</label>';
		
		echo '</br>';
		
		# sort by options
		echo '<div class="form-horizontal input-prepend input-append">';

		echo '<span class="add-on">Sort by</span>';
		echo '<select name="sort" id="appendedPrependedInput" class="input-medium">';
	
		foreach ($this -> data_structure as $field) {
			if($field[2] == 0 || $field[2] == 2) {
				$selected = $field[0] == $_GET['sort'] ? ' selected' : '';
				echo '<option value='.$field[0].$selected.'>'.$field[0].'</option>';
			}
		}
		echo '</select>';
		
		echo '<select name="sort_type" class="input-small"><option value="asc" '.($_GET['sort_type'] == 'asc' ? 'selected' : '').'>Ascending</option><option value="desc" '.($_GET['sort_type'] == 'desc' ? 'selected' : '').'>Descending</option></select>';
		
		#show targets
		$query = 'SELECT * FROM '.$this -> project.'docking_targets WHERE project_id = '.$project;
		$this -> Database -> query($query);
		echo '<span class="add-on">in target</span>';
		echo '<select name="sort_target" class="input-medium">';
		echo '<option value="">Any Target</option>'; # empty option to force selection
		if(!$target_total_num) {
			$target_total_num = $this -> Database -> num_rows();# count all targets for future use
		}
		while($row = $this -> Database -> fetch_assoc()) {
			$selected = $row['id'] == $_GET['sort_target'] ? ' selected' : '';
			echo '<option value='.$row['id'].$selected.'>'.$row['name'].'</option>';
		}
		echo '</select>';
		echo '<span class="add-on"></span>';
		echo '</div>';
		
		echo '</br>';
		
		#limit
		echo '<div class="input-prepend">';
		echo '<span class="add-on">Limit results</span>';
		echo '<input type="text" name="limit" class="input-mini" value="'.((int) $_GET['limit']).'"/>';
		echo '</div>';
		echo '</br>';
		echo '</br>';
		
		echo '<input class="btn btn-large" type="submit" value="View results" onClick="loading();" />';
		echo '</form>';
	}
	
	public function view_form_overview() {
		# show mixed summary table
		$summary = array();
		$counts = array();
		$query = 'SELECT target.name AS target_name, target.id AS tid, subset.name AS subset_name, subset.id AS sid, COUNT(DISTINCT subset.id) AS rowspan, COUNT(DISTINCT conf.mol_id) AS mol_num, COUNT(conf.id) AS conf_num FROM '.$this -> project.'docking_conformations AS conf 
		LEFT JOIN '.$this -> project.'docking_targets AS target ON conf.target_id = target.id 
		LEFT JOIN '.$this -> project.'docking_ligand_subset AS subset ON conf.ligand_subset = subset.id 
		GROUP BY target_id, ligand_subset;';
		$this -> Database -> query($query);
		#echo $query;
		$n=1;
		while($row = $this -> Database -> fetch_assoc()) {
			$summary[$row['tid']][] = $row;
			$counts[$row['tid']]['subset_num']++;
		}
		
		if(!empty($summary)) {
			# show target summary table
			echo '<table class="table table-striped table-hover">';
			echo '<tr>';
			echo '<th>#</th>';
			echo '<th>Receptor</th>';
			echo '<th>Subset</th>';
			echo '<th># of ligands</th>';
			echo '<th># of conformations</th>';
			echo '<th>Computation</th>';
			echo '</tr>';
			$query = 'SELECT target.id AS tid, target.name, COUNT(DISTINCT conf.mol_id) AS mol_num, COUNT(conf.id) AS conf_num  FROM '.$this -> project.'docking_conformations AS conf JOIN '.$this -> project.'docking_targets AS target ON conf.target_id = target.id GROUP BY target_id;';
			$this -> Database -> query($query);
			#echo $query;
			$n=1;
		
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<tr>';
				echo '<td>'.$n++.'</td>'; #rowspan="'.($counts[$row['tid']]['subset_num']+1).'"
				echo '<td><a href="'.$this->get_link(array('module' => 'molecules', 'mode' => 'search', 'var_target[]' => $row['tid']), array(), array('project')).'">'.$row['name'].'</a></td>';
				echo '<td><a href="'.$this->get_link(array('module' => 'molecules', 'mode' => 'search', 'var_target[]' => $row['tid']), array(), array('project')).'">All subsets</a> <button class="btn btn-mini btn-inverse overview-subsets"><i class="icon-list icon-white"></i> more</button></td>';
				echo '<td><span class="badge badge-info">'.number_format($row['mol_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></td>';
				echo '<td><span class="badge">'.number_format($row['conf_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></td>';
			
				echo '<td>';
				echo '<div class="btn-group">';
				echo '<button class="btn btn-warning dropdown-toggle" data-toggle="dropdown">Compute <span class="caret"></span></button>';
				echo '<ul class="dropdown-menu">';
				$plugins = $this -> list_plugins();
				foreach($plugins as $plugin) {
					echo '<li><a data-toggle="modal" data-target="#modal"  href="'.$this -> get_link(array('module' => 'plugins', 'mode' => 'compute', 'plugin_name' => $plugin[0], 'target_id' => $row['tid'], 'ajax' => 1), array(), array('project')).'">'.$plugin[1].'</a></li>';
				}
				echo '</ul>';
				echo '</div>';
				echo '</td>';

				echo '</tr>';
			
			
				#show per subset rows
				foreach($summary[$row['tid']] as $key => $row) {
					echo '<tr class="hide">';
					echo '<td><a href="'.$this->get_link(array('module' => 'molecules', 'mode' => 'search', 'var_target[]' => $row['tid'], 'subset' => 'ligand-'.$row['sid']), array(), array('project')).'">'.$row['subset_name'].'</a></th>';
					echo '<td><span class="badge badge-success">'.number_format($row['mol_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></th>';
					echo '<td><span class="badge">'.number_format($row['conf_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></th>';
				
					echo '<td>';
					echo '<div class="btn-group">';
					echo '<button class="btn btn-warning dropdown-toggle" data-toggle="dropdown">Compute <span class="caret"></span></button>';
					echo '<ul class="dropdown-menu">';
					$plugins = $this -> list_plugins();
					foreach($plugins as $plugin) {
						echo '<li><a data-toggle="modal" data-target="#modal"  href="'.$this -> get_link(array('module' => 'plugins', 'mode' => 'compute', 'plugin_name' => $plugin[0], 'target_id' => $row['tid'], 'ligand-subset' => $row['sid'], 'ajax' => 1), array(), array('project')).'">'.$plugin[1].'</a></li>';
					}
					echo '</ul>';
					echo '</div>';
					echo '</td>';
				
					echo '</tr>';
				}
			}
			echo '</table>';
		
			?>
			<script>
			$(function() {
			
				$('.overview-subsets').click(function() {
					var parent = $(this).closest('tr')
				
					if($(this).hasClass('active')) {
						parent.nextAll('tr').each(function() {
							if($(this).children('td').length < 5) {
								$(this).hide();
							}
							else {
								return false;
							}
						});
						parent.children('td[rowspan]').each(function() {
							  $(this).removeAttr('rowspan');
						});
						$(this).html($(this).html().replace('less', 'more'));
						$(this).removeClass('active');
					}
					else {
						var n = 1;
						parent.nextAll('tr').each(function() {
							if($(this).children('td').length < 5) {
								$(this).show();
								n += 1;	
							}
							else {
								return false;
							}
						});
						// give some rowspan
						parent.children('td:lt(2)').each(function() {
							  $(this).attr('rowspan', n);
						});
						$(this).html($(this).html().replace('more', 'less'));
						$(this).addClass('active');
					}
				});
			});
			</script>
			<?php
		
			echo '</br>';
		
			# show subset summary table
			echo '<table class="table table-striped table-hover">';
			echo '<tr>';
			echo '<th>#</th>';
			echo '<th>Subset</th>';
			echo '<th># of ligands</th>';
			echo '<th># of conformations</th>';
			echo '</tr>';
			$query = 'SELECT subset.id AS sid, subset.name, COUNT(DISTINCT conf.mol_id) AS mol_num, COUNT(conf.id) AS conf_num  FROM '.$this -> project.'docking_conformations AS conf JOIN '.$this -> project.'docking_ligand_subset AS subset ON conf.ligand_subset = subset.id GROUP BY ligand_subset;';
			$this -> Database -> query($query);
			#echo $query;
			$n=1;
			while($row = $this -> Database -> fetch_assoc()) {
				echo '<tr>';
				echo '<td>'.$n++.'</th>';
				echo '<td><a href="'.$this->get_link(array('module' => 'molecules', 'mode' => 'search', 'subset' => 'ligand-'.$row['sid']), array(), array('project')).'">'.$row['name'].'</a></th>';
				echo '<td><span class="badge badge-success">'.number_format($row['mol_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></th>';
				echo '<td><span class="badge">'.number_format($row['conf_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></th>';
				echo '</tr>';
			}
			echo '</table>';
			
			echo '</br>';
		
			$query = 'SELECT subset.id AS sid, subset.name, COUNT(DISTINCT conf.mol_id) AS mol_num, COUNT(conf.id) AS conf_num  FROM '.$this -> project.'docking_conformations AS conf JOIN '.$this -> project.'docking_user_subset_members AS members ON conf.id = members.conf_id JOIN '.$this -> project.'docking_user_subset AS subset ON members.user_subset_id = subset.id GROUP BY members.user_subset_id;';
			$this -> Database -> query($query);
			if($this -> Database -> num_rows() > 0) {
				# show subset summary table
				echo '<table class="table table-striped table-hover">';
				echo '<tr>';
				echo '<th>#</th>';
				echo '<th>User subset</th>';
				echo '<th># of ligands</th>';
				echo '<th># of conformations</th>';
				echo '</tr>';
				$n=1;
				while($row = $this -> Database -> fetch_assoc()) {
					echo '<tr>';
					echo '<td>'.$n++.'</th>';
					echo '<td><a href="'.$this->get_link(array('module' => 'molecules', 'mode' => 'search', 'subset' => 'user-'.$row['sid']), array(), array('project')).'">'.$row['name'].'</a></th>';
					echo '<td><span class="badge badge-success">'.number_format($row['mol_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></th>';
					echo '<td><span class="badge">'.number_format($row['conf_num'], $decimals = 0 , $dec_point = ',' , $thousands_sep = ' ' ).'</span></th>';
					echo '</tr>';
				}
				echo '</table>';
			}
		}
		else {
			echo '<center><a class="btn btn-large" data-toggle="modal" data-target="#modal" href="'.$this -> get_link(array('module' => 'data_management', 'mode' => 'import_form', 'ajax' => 1), array(), array('project', 'module')).'">Import docked conformations</a></center>';
		}
	}
	
	public function view_form() {
		global $CONFIG;
		
		$project = $this -> Database -> secure_mysql($_GET['project']);
		
		if($project) {
		
			echo '<div class="tabbable" id="form-container">';
			echo '<ul class="nav nav-tabs" id="form-switch">';
			echo '<li><a href="#tab-overview" data-toggle="tab">Overview</a></li>';
			echo '<li><a href="#tab-query" data-toggle="tab">Filtering</a></li>';
			echo '<li><a href="#tab-cheminformatics" data-toggle="tab">Cheminformatics</a></li>';
			echo '<li><a href="#tab-interactions" data-toggle="tab">Interactions</a></li>';
			echo '<li><a href="#tab-data-assessment" data-toggle="tab">RankScore</a></li>';
			echo '<li><a href="#tab-data-charts" data-toggle="tab">Charts</a></li>';
			echo '<li><a href="'.$this->get_link(array('module' => 'assays'), array(),array('project', 'module')).'">Assays</a></li>';
		
		
			echo '<li class="dropdown pull-right"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Data managment <b class="caret"></b></a>';
			echo '<ul class="dropdown-menu">';
			echo '<li><a data-toggle="modal" data-target="#modal" href="'.$this -> get_link(array('module' => 'data_management', 'mode' => 'import_form', 'ajax' => 1), array(), array('project', 'module')).'">Import docked conformations</a></li>';
		       	echo '</ul>';
			echo '</li>';
		
			# list of available formats
			$formats = array('chimera', 'mol2', 'sdf', 'pdb', 'pdbqt');
		
			if($this -> result_num > 0 && in_array($_GET['mode'], array('search', 'cheminformatics'))) {
				echo '<li class="dropdown pull-right"><a href="#" class="dropdown-toggle" data-toggle="dropdown">User Subsets <b class="caret"></b></a>';
				echo '<ul class="dropdown-menu">';
			
				$query = 'SELECT * FROM '.$this -> project.'docking_user_subset';
				$this -> Database -> query($query);
				if($this -> Database -> num_rows() > 0) {	
					echo '<li class="dropdown-submenu"><a href="#">Add to subset</a>';
					echo '<ul class="dropdown-menu">';
					while($row = $this -> Database -> fetch_assoc()) {
						echo '<li><a href="'.$this -> get_link(array('mode' => 'subset_add', 'subset_id_add' => $row['id'])).'">'.$row['name'].'</a></li>';
					}
					echo '</ul>';
					echo '</li>';
				}
				echo '<li class="divider"></li>';
				echo '<li><a href="#" onClick="create_subset(\''.$this -> get_link(array('module' => 'subsets','mode' => 'create', 'subset_name' => '')).'\')">Create new subset</a></li>';
			       	echo '</ul>';
				echo '</li>';
		
				echo '<li class="dropdown pull-right"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Downloads <b class="caret"></b></a>';
			
				echo '<ul class="dropdown-menu">';	
			
				echo '<li class="dropdown-submenu"><a href="#">Query</a>';
				echo '<ul class="dropdown-menu">';
				foreach($this -> target as $tid) {
					echo '<li class="dropdown-submenu"><a href="#">'.$this -> target_names[$tid].'</a>';
					echo '<ul class="dropdown-menu">';
					foreach($formats as $format) {
						echo '<li><a href="'.$this -> get_link(array('mode' => 'download_molecule', 'format' => $format, 'target_id' => $tid)).'">'.$format.'</a></li>';
					}
			       		echo '</ul>';
			       		echo '</li>';
			       	}
			       	echo '</ul>';
			       	echo '</li>';
			
			       	echo '<li class="dropdown-submenu"><a href="#">Selected</a>';
				echo '<ul class="dropdown-menu selected-download">';
				foreach($this -> target as $tid) {
					echo '<li class="dropdown-submenu"><a href="">'.$this -> target_names[$tid].'</a>';
					echo '<ul class="dropdown-menu selected-download">';
					foreach($formats as $format) {
						echo '<li><a href="'.$tid.'">'.$format.'</a></li>';
					}
			       		echo '</ul>';
			       		echo '</li>';
			       	}
			       	echo '</ul>';
			       	echo '</li>';
			
			
			       	echo '</ul>';
				echo '</li>';
			
			
			}
			elseif(in_array($_GET['mode'], array('molecule'))) {
			
				echo '<li class="dropdown pull-right"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Downloads <b class="caret"></b></a>';
			
				if(!empty($_GET['subset']) && !empty($_GET['target_id'])) {
				
					echo '<ul class="dropdown-menu">';
				
					echo '<li class="dropdown-submenu"><a href="#">All</a>';
					echo '<ul class="dropdown-menu">';
					foreach($formats as $format) {
						echo '<li><a href="'.$this -> get_link(array('mode' => 'download_molecule', 'format' => $format), array(), array('project', 'module', 'mode', 'mol_id', 'target_id', 'subset')).'">'.$format.'</a></li>';
					}
				       	echo '</ul>';
				       	echo '</li>';
				
					echo '<li class="dropdown-submenu"><a href="#">Selected</a>';
					echo '<ul class="dropdown-menu selected-download">';
					foreach($formats as $format) {
						echo '<li><a href="">'.$format.'</a></li>';
					}
				       	echo '</ul>';
				       	echo '</li>';
				       	
				       	echo '</ul>';
				}
				echo '</li>';
			}
			elseif($this -> result_num > 0) {
				echo '<li class="dropdown pull-right"><a href="#" class="dropdown-toggle" data-toggle="dropdown">Downloads <b class="caret"></b></a>';
				echo '<ul class="dropdown-menu">';
				echo '<li class="dropdown-submenu"><a href="#">Selected</a>';
				echo '<ul class="dropdown-menu selected-download">';
				foreach($formats as $format) {
					echo '<li><a href="">'.$format.'</a></li>';
				}
			       	echo '</ul>';
			       	echo '</li>';
			       	
			       	echo '</ul>';
				echo '</li>';
			}
	 		
	 		echo '</ul>';
	 		?>
	 		<script>
	 		$(function() {
	 			$('ul.selected-download > li > a').click(function(e) {
	 				e.preventDefault();
	 				$('form[name="selection-form"] > input[name="format"]').val($(this).text())
	 				if($(this).attr('href') > 0) {
	 					$('form[name="selection-form"] > input[name="target_id"]').val($(this).attr('href'))
	 				}
	 				$('form[name="selection-form"]').submit();
	 			});
	 		});
	 		//disable at load
	 		$('ul.selected-download').hide();
			$('ul.selected-download').parent('li').addClass('disabled');
	 		</script> 		
	 		<?php

		
			echo '<div class="tab-content">';
			
				echo '<div id="tab-overview"  class="tab-pane">';
				$this -> view_form_overview();
				echo '</div>';
				
				echo '<div id="tab-query"  class="tab-pane">';
				$this -> view_form_query();
				echo '</div>';
				
				echo '<div id="tab-cheminformatics"  class="tab-pane">';
				$this -> view_form_cheminformatics();
				echo '</div>';
				
				echo '<div id="tab-data-charts"  class="tab-pane">';
				$this -> view_form_data_charts();
				echo '</div>';
				
				echo '<div id="tab-data-assessment"  class="tab-pane">';
				$this -> view_form_data_assessment();
				echo '</div>';
				
				echo '<div id="tab-interactions"  class="tab-pane">';
				$this -> view_form_interactions();
				echo '</div>';
				
				echo '<div id="tab-empty"  class="tab-pane"></div>';
			
			echo '<div id="tab-downloads"  class="tab-pane">';
			$this -> view_form_downloads();
			echo '</div>';
			
			# activate tab
			if($_GET['mode'] == 'cheminformatics') {
				
				$vis = 'tab-cheminformatics';
			}
			elseif($_GET['mode'] == 'data_charts') {
				
				$vis = 'tab-data-charts';
			}
			elseif($_GET['mode'] == 'data_assessment') {
				
				$vis = 'tab-data-assessment';
			}
			elseif($_GET['mode'] == 'interactions') {
				
				$vis = 'tab-interactions';
			}
			elseif($_GET['mode'] == 'molecule') {
				
				$vis = 'tab-empty';
			}
			elseif($_GET['mode'] == 'search') {
				
				$vis = 'tab-query';
			}
			else {
				
				$vis = 'tab-overview';
			}
			echo '</div>';
			
			echo '<script>$(function() {$(\'#form-switch a[href$="#'.$vis.'"]\').tab(\'show\')});</script>';
		
		
		
			echo '</div>';
		}
	}
	
	public function view_cheminformatics() {
		$this -> view_search();
	}
	
	public function view_search() { # show, what you've got
		$project = $this -> Database -> secure_mysql($_GET['project']);
		if ($project) {
			$this -> view_form();
		
			# print short summary
			if(!empty($this -> result_num) || $this -> result_num === 0) {
				echo '<div class="alert alert-block '.($this -> result_num > 0 ? 'alert-success' : 'alert-error').'" style="margin: 0px auto; width:20%">Query retrieved <b>'.$this -> result_num.'</b> '.(!empty($_GET['disable_mol_grouping']) ?  'conformations' : 'molecules').' meeting given criteria.</div>';
			}
		
			if(!empty($this -> mols)) {
				# show pages
				$this -> pagination();

				if(count($this -> target) == 1) {
					# get target structure
					$target_id = $this -> target[0];
					$query = 'SELECT mol2 FROM '.$this -> project.'docking_targets WHERE id = '.$target_id.';';
					$this -> Database -> query($query);
					$row = $this -> Database -> fetch_row();
					$target_mol2 = $row[0];
		
					foreach ($this -> mols as $mol) {
						$mol2 .= $mol[$target_id]['mol2'];
					}
				

				
					# Init jmol
					# Show Jmol window with receptor
					echo '<div class="container-fluid">';
					echo '<div class="row-fluid">';
					echo '<div class="span5">';
					
					echo '<div id="jmol-wrapper">';
					$this -> visualizer($target_mol2, $mol2);			
					echo '</div>'; # jmol-wrapper
					echo '</div>';
					
#					echo '<div id="jmol-wrapper" data-spy="affix" data-offset-top="700" class="span6">';
#					$this -> visualizer($target_mol2, $mol2);			
#					echo '</div>'; # jmol-wrapper
					
					
					?>
					<script>
					$(function() {
						//activate affix
						$("#jmol-wrapper").affix({
						    offset: {
							top: $("#jmol-wrapper").parent().position().top
						    }
						});
						
						//clone table header
						parent = $('tr', $('table.molecules')).first()
						clone = parent.clone().appendTo($('table.molecules'))
						clone.children().each(function(index) {
							$(this).width(parent.children().eq(index).width())
						});
						clone.width(parent.width());
						clone.affix({
						    offset: {
							top: parent.position().top
						    }
						});
					});
					</script>
					<?php
				
					
					#Show conformation table
					echo '<div class="span7" style="overflow-x: scroll">';
				}
				
				# Open Selection form
				#Show conformation table
				echo '<form name="selection-form" method=GET>';
				echo '<input type="hidden" name="mode" value="download_molecule" />';
				echo '<input type="hidden" name="project" value="'.$this -> project_id.'" />';	
				echo '<input type="hidden" name="target_id" value="'.$this -> target[0].'" />';
				echo '<input type="hidden" name="format" value="'.$_GET['type'].'" />';
				# show table
				echo '<table class="molecules"><tr>';
				# get opposite sorting type
				$sort = $_GET['sort'] ? $_GET['sort'] : $_POST['sort'];
				$sort_type = $_GET['sort_type'] ? $_GET['sort_type'] : $_POST['sort_type'];
				foreach ($this -> data_structure as $field) {
					#switch molecular and conformational features
					if(
						($_GET['mode'] == 'cheminformatics' && ($field[2] == 0 || $field[2] == 1)) # molecular
						||
						($_GET['mode'] == 'search' && ($field[2] == 0 || $field[2] == 2)) #conformational
					) {
						if ($sort ==  $field[0]) {
							switch($sort_type) {
								case 'asc':
								$sub = array('sort' => $field[0], 'sort_type' => 'desc', 'page' => 1);
								$arrow = '<i class="icon-arrow-up"></i>';
								break;
								case 'desc':
								$sub = array('sort' => $field[0], 'sort_type' => 'asc', 'page' => 1);
								$arrow = '<i class="icon-arrow-down"></i>';
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
							echo '<th><input type="checkbox" name="selall-'.(!empty($_GET['disable_mol_grouping']) ? 'conf_ids[]' : 'mol_ids[]').'"/></th><th>';
						}
						else {
							echo '<th>';
						}			
						echo '<a href="'.$this -> get_link($sub).'" onClick="loading();">'.$field[1].' '.$arrow.'</a></th>';
						if ($field[0] == name) {
							echo '</th>';
							# show tanimoto or smarts rep
							if(is_array($this -> tanimoto)) {
								echo '<th>Tanimoto</th>';
							}
							elseif(is_array($this -> smarts_rep)) {
								echo '<th># of SMARTS</th>';
							}
							if(!empty($this -> mol_act[0])) {
								echo '<th>Activity [nM]</th>';
							}
							if((!empty($this -> mol_act[0]) && count($this -> mol_act) > 1) || (empty($this -> mol_act[0]) && count($this -> mol_act) > 0)) {
								echo '<th>Docking target Activity [nM]</th>';
							}
							echo '<th class="target">Target</th>';
							echo '<th>View in 3D</th>';
						}
						else {
							echo '</th>';
						}
					}
				}
				echo '</tr>';
				# Print data
				$n = 0; # even or odd
				foreach ($this -> mols as $mol) {
					$style = ($n&1) ? 'odd' : 'even';
				
					#get highest value for sorting when no target for sorting specified
					if(empty($_GET['sort_target'])) {
						$top = 0;
						$sort_top = array();
						foreach($this -> target as $tid) {
							if(isset($mol[$tid][$sort])) {
								$sort_top[$mol[$tid][$sort]] = $tid;
							}
						}
						if($sort == 'name') {
							uksort($sort_top, 'strnatcasecmp');
						}
						else {
							ksort($sort_top);
						}
						if($sort_type == 'asc') {
							$sort_top = array_reverse($sort_top);
						}
						#check if there's one value, then highlight first
						if(count(array_unique($sort_top)) == 1) {
							$top = $this -> target[0];
						}
						else {
							$top = array_pop($sort_top);
						}
					}
					$first = true;
					foreach($this -> target as $k => $tid) {
						if(is_array($mol[$tid])) { # display only existing conformers
							echo '<tr id="row-conf-'.($n+1).'" class="'.$style.'" onclick="showClickedConformation('.($n+1).')">';
							foreach ($this -> data_structure as $field) {
								if(
									($_GET['mode'] == 'cheminformatics' && ($field[2] == 0 || $field[2] == 1)) # molecular
									||
									($_GET['mode'] == 'search' && ($field[2] == 0 || $field[2] == 2)) #conformational
								) {
									if ($field[0] == 'name' ) {
										# show only once
										if ($first) {
											# index field
											echo '<td id="mol-num-'.$mol[$tid]['mol_id'].'" class="normal" rowspan='.count($mol).'>'.((($this -> page - 1) * $this -> per_page)+$n+1).'</td>';
											# show checkbox
											echo '<td id="mol-toggle-'.$mol[$tid]['mol_id'].'"  class="normal" rowspan='.count($mol).'>';
											echo '<input type="checkbox" name="'.(!empty($_GET['disable_mol_grouping']) ? 'conf_ids[]' : 'mol_ids[]').'" value="'.(!empty($_GET['disable_mol_grouping']) ? $mol[$tid]['conf_id'] : $mol[$tid]['mol_id']).'" />';
											echo '</td>';
											$img_size_prev = 100; # small IMG size
											$img_size_big = 300;
											echo '<td id="mol-image-'.$mol[$tid]['mol_id'].'" rowspan='.count($mol).'>';
											echo '<a href="'.$this -> get_link(array('mode' => 'molecule', 'mol_id' => $mol[$tid]['mol_id'], 'target_id' => count($this -> target) == 1 ? $this -> target[0] : '', 'subset' => $_GET['subset']), array(), array('project', 'module')).'">';
											echo $mol[$tid]['mol_name'].'</br>';
											echo '<img src="openbabel_ajax.php?smiles='.rawurlencode($mol[$tid]['smi']).'&output=svg" class="mol" width="'.$img_size_prev.'" height="'.$img_size_prev.'"/></a>';
								
											# show tanimoto 
											if(is_array($this -> tanimoto)) {
												echo '<td id="mol-tanimoto-'.$mol[$tid]['mol_id'].'" rowspan='.count($mol).' class="target">'.round($this -> tanimoto[$mol[$tid]['mol_id']],3).'</td>';
											}
											elseif(is_array($this -> smarts_rep)) {
												echo '<td id="mol-tanimoto-'.$mol[$tid]['mol_id'].'" rowspan='.count($mol).' class="target">'.round($this -> smarts_rep[$mol[$tid]['mol_id']],3).'</td>';
											}
											# show activity
									
											if(!empty($this -> mol_act[0])) {
												echo '<td id="mol-tanimoto-'.$mol[$tid]['mol_id'].'" rowspan='.count($mol).' class="target">';
												if(!empty($this -> mol_act[0][$mol[$tid]['mol_id']])) {
													foreach($this -> mol_act[0][$mol[$tid]['mol_id']] as $act) {
														switch($act['act_operator']) {
															case 1:
															$act_operator = '>';
															break;
															case 0:
															$act_operator = '=';
															break;
															case -1:
															$act_operator = '<';
															break;
														}
														echo '<a href="'.$this -> get_link(array('module' => 'assays', 'mode' => 'show', 'aid' => $act['assay_id']), array(), array('project', 'module', 'mode', 'aid')).'">';
														echo (!empty($act['act_value']) ? $act_operator.' '.$act['act_value'] : 'No Activity').'</br>';
														echo '</a>';
													}
												}
												echo '</td>';
											}	
										}	
										# show target connected activity
								
										if((!empty($this -> mol_act[0]) && count($this -> mol_act) > 1) || (empty($this -> mol_act[0]) && count($this -> mol_act) > 0)) {
											echo '<td>';
											if(!empty($this -> mol_act[$tid][$mol[$tid]['mol_id']])) {
												foreach($this -> mol_act[$tid][$mol[$tid]['mol_id']] as $act) {
													switch($act['act_operator']) {
														case 1:
														$act_operator = '>';
														break;
														case 0:
														$act_operator = '=';
														break;
														case -1:
														$act_operator = '<';
														break;
													}
													echo '<a href="'.$this -> get_link(array('module' => 'assays', 'mode' => 'show', 'aid' => $act['assay_id']), array(), array('project', 'module', 'mode', 'aid')).'">';
													echo (!empty($act['act_value']) ? $act_operator.' '.$act['act_value'].(!empty($act['act_type']) ? ' ['.$act['act_type'].']': '') : 'No Activity').'</br>';
													echo '</a>';
												}
											}
											echo '</td>';
										}
								
										# show target
										echo '<td class="target">'.$mol[$tid]['target_name'].'</td>';
										echo '<td>
										<a href="'.$this -> get_link(array('mode' => 'molecule', 'mol_id' => $mol[$tid]['mol_id'], 'target_id' => $tid), array(), array('module', 'project', 'subset')).'#3d"><i class="icon-plus-sign"></i></a></br>
										</td>';
									}
									elseif (@in_array($field[0], $this -> hide)) {
										echo '';
									}
									// round numeric fields 
									elseif (!empty($field[2])) {
										# check if it needs to be spanned
										$rowspan = $field[2] == 1 ? ' rowspan='.count($mol) : '';
										if(!empty($rowspan) && $first || empty($rowspan)) {
											if($sort == $field[0] && ($tid == $_GET['sort_target'] || $top == $tid)) {
												echo '<td'.$rowspan.'><b>'.round($mol[$tid][$field[0]], 2).'</b></td>';								
											}
											else {
												echo '<td'.$rowspan.'>'.round($mol[$tid][$field[0]], 2).'</td>';
											}
										}
									}
									else {
										# check if it needs to be spanned
										$rowspan = $field[2] == 1 ? ' rowspan='.count($mol) : '';
										if(!empty($rowspan) && $first || empty($rowspan)) {
											echo '<td'.$rowspan.'>';
											if($sort == $field[0] && ($tid == $_GET['sort_target'] || $top == $tid)) {
												echo '<b>'.$mol[$tid][$field[0]].'</b>';
											}
											else {
												echo $mol[$tid][$field[0]];
											}
											echo '</td>';
										}
									}
								}
							}
							echo '</tr>';
							#gone through first
							$first = false;
						}
					}
					$n++; #increase odd/even counter
				}
				echo '</tr></table>';
				echo '</form>';
				
				if(count($this -> target) == 1) {
					# highlight first conformation
					echo '<script>showClickedConformation(1);max_id='.($n).'</script>';
					echo '</div>';
					echo '</div>';
#					echo '
#						<script src="jquery/jquery.tablescroll.js" type="text/javascript"></script>
#						<script>
#						/*<![CDATA[*/

#						$(function()
#						{
#							$(\'#conformation-table\').tableScroll({height: $(window).height()*0.8, containerClass:\'conformation-table-wrapper\'});
#	
#						});

#						/*]]>*/
#						</script>';
				}
				# show pages
				$this -> pagination();
				
			}
		}
		else {
			echo "Please select project on top toolbar!";
		}
	}
}
?>
