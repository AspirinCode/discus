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

class plugin_interface {
	public static $name = 'Plugin name';
	public static $desc = 'Short description of the plugin functionality';
	public $batch_size = 100; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	
	
	protected $temp_dir;
	
	# input formats for receptor and ligands (type none if not relevant; obmol for OBMol class pointer [default])
	# `pointer` is another dummy format meaning that only IDs of subsets and targets are needed to execute; array('target_id' => 1, 'subset' => 'ligand-1')
	public static $input = array('ligands' => 'mol2', 'receptor' => 'mol2');
	
	# an array with list of resulting fields and their types, both conformational and molecular features
	public static $result = array(
		'conf' => array(
				array('field_name' => 'name', 'field_label' => 'Name', 'type' => 'float', 'prefix' => 'confprop.')
			),
		'mol' => array(
				array('field_name' => 'name', 'field_label' => 'name', 'type' => 'float', 'prefix' => 'molprop.')
			)
		);
	
	# compute returns an dictionary of field values
	public function compute($ligands, $receptor = null) {
	
	}
	
	# create temp directory on sturtup
	public function __construct() {
		$this -> temp_dir = $this -> mk_temp_dir();
	}
	
	public function __destruct() {
		$this -> rm_temp_dir($this -> temp_dir);
	}
	
	public function mk_temp_dir() {
		global $CONFIG;
		# generate random name for dir
		$rand = md5(time()*rand()*mt_rand());
		
		if(mkdir($CONFIG['temp_dir'].'/plugin_'.$rand, 0777, true)) {
			return $CONFIG['temp_dir'].'/plugin_'.$rand;
		}
		else {
			return false;
		}
	}
	
	private function rm_temp_dir($dir) {
		if(is_dir($dir)) {
			$files = array_diff(scandir($dir), array('.','..'));
			foreach($files as $file) { 
				(is_dir($dir.'/'.$file)) ? $this -> rm_temp_dir($dir.'/'.$file) : unlink($dir.'/'.$file); 
			}
			return rmdir($dir);
		}
		elseif(file_exists($dir)) {
			return unlink($dir);
		}
		else {
			return false;
		}
	}
}
?>
