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

class plugin_xscore extends plugin_interface {
	public static $name = 'Xscore';
	public static $desc = 'Computes Tripos CScore values';
	public $batch_size = 0; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	
	public static $input = array('ligands' => 'mol2', 'receptor' => 'pdb');

	public static  $result = array(
		'conf' => array(
				array('field_name' => 'hpscore', 'field_label' => 'HPSCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'hmscore', 'field_label' => 'HMSCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'hsscore', 'field_label' => 'HSSCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'ave_score', 'field_label' => 'AVE_SCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'xscore_bind_energy', 'field_label' => 'XScore Binding Energy', 'type' => 'float', 'prefix' => 'confprop.'),
			),
		'mol' => array()
		);

	# compute returns an dictionary of field values
	public function compute($ligands, $receptor = null) {
		# save receptor
		file_put_contents($this -> temp_dir.'/target.pdb', $receptor);
		
		# save ligands
		file_put_contents($this -> temp_dir.'/ligands.mol2', $ligands);
		
		# run Xscore
		exec('XSCORE_PARAMETER=./plugins/xscore/parameter ./plugins/xscore/bin/xscore -score '.$this -> temp_dir.'/target.pdb '.$this -> temp_dir.'/ligands.mol2', $o);
		
		# filter output from warnings etc.
		$scores = preg_grep('/^Molecule/', $o);
		
		# decode output to CSV 
		$out = array();
		foreach($scores as $line) {
			$csv = str_getcsv(preg_replace('/\s+/', ' ', $line), ' ');
			$out[$csv[7]] = array('hpscore' => $csv[2], 'hmscore' => $csv[3], 'hsscore' => $csv[4], 'ave_score' => $csv[5], 'xscore_bind_energy' => $csv[6]);
		}
		
		#remove log file
		unlink('xscore.log');
		
		return $out;
	}
}
?>
