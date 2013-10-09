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

class plugin_interactions extends plugin_interface {
	public static $name = 'Interactions Plugin';
	public static $desc = 'Computing interactions for DiSCuS';
	public $batch_size = 0; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	

	public static  $result = array(
		'conf' => array(),
		'mol' => array()
		);

	public static $input = array('ligands' => 'pointer', 'receptor' => 'null');
	
	# compute returns an dictionary of field values
	public function compute($ligands, $receptor = null) {
		$proc = popen('PYTHONPATH=$PYTHONPATH:/usr/local/lib /usr/bin/env python ./plugins/interactions/discus_interactions.py -p '.((int)$_GET['project']).' -t '.$ligands['target_id'].' '.($ligands['ligand_subset'] > 0 ? '-s '.$ligands['ligand_subset'] : '').' 2>&1', 'r');
		while($buffer = fgets($proc)) {
			echo $buffer;
			flush();
			ob_flush();
		}
	}
}
?>
