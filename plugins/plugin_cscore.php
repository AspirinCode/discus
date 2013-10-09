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

class plugin_cscore extends plugin_interface {
	public static $name = 'Tripos CScore';
	public static $desc = 'Computes Tripos CScore values';
	public $batch_size = 0; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	
	public static $input = array('ligands' => 'obmol', 'receptor' => 'mol2');

	public static  $result = array(
		'conf' => array(
				array('field_name' => 'd_score', 'field_label' => 'D_Score', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'pmf_score', 'field_label' => 'PMF_Score', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'g_score', 'field_label' => 'G_Score', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'chemscore', 'field_label' => 'ChemScore', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'cscore', 'field_label' => 'CScore', 'type' => 'int', 'prefix' => 'confprop.'),
			),
		'mol' => array()
		);

	# compute returns an dictionary of field values
	public function compute($ligands, $receptor = null) {
		#save receptor
		file_put_contents($this -> temp_dir.'/target.mol2', $receptor);
		
		$OBConversion = new OBConversion;
		$OBConversion->SetInAndOutFormats('mol2', 'mol2');
		$OBConversion -> SetOptions('l', $OBConversion::OUTOPTIONS);
		
		mkdir($this -> temp_dir.'/ligands.mdb', 0777, true);
		foreach($ligands as $name => $OBMol) {
			$OBMol -> AddHydrogens();
			file_put_contents($this -> temp_dir.'/ligands.mdb/'.$name.'.mol2', $OBConversion->WriteString($OBMol));
#			file_put_contents($this -> temp_dir.'/ligands.mdb/'.$name.'.mol2', $OBMol);
		}
		
		exec('echo "\$TA_BIN/CScore '.$this -> temp_dir.'/target.mol2 ./plugins/cscore/cscore.par '.$this -> temp_dir.'/ligands.mdb" | /home/tripos/sybylx2.0/sybyl -shell', $o);
		
		$out = array();
		$handle = fopen($this -> temp_dir.'/ligands.cso', 'r');
		while (($row = fgets($handle, 1000)) !== FALSE) {
			$csv = str_getcsv(preg_replace('/\s+/', ' ', $row), ' ');
			$cscore = 0;
			foreach(array(3,5,7,9) as $i) {
				if($csv[$i] < 0) {
					$cscore++;
				}
			}
			$out[$csv[1]] = array('d_score' => $csv[3], 'pmf_score' => $csv[5], 'g_score' => $csv[7], 'chemscore' => $csv[9], 'cscore' => $cscore);
		}
		fclose($handle);
		
		return $out;
	}
}
?>
