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

class plugin_dummy extends plugin_interface {
	public static $name = 'Dummy Plugin';
	public static $desc = 'Testing plugins for DiSCuS';
	public $batch_size = 0; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	

	public static  $result = array(
		'conf' => array(),
		'mol' => array()
		);

	public static $input = array('ligands' => 'obmol', 'receptor' => 'mol2');
	
	# compute returns an dictionary of field values
	public function compute($ligands, $receptor = null) {
#		foreach($ligands as $l) {
#			echo $l -> GetTitle().'</br>';
#		}
#		echo $receptor;
		echo count($ligands);
	}
}
?>
