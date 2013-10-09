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

class plugin_molecular extends plugin_interface {
	public static $name = 'Molecular Features';
	public static $desc = 'Computes molecular features using OpenBabel';
	public $batch_size = 0; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	
	public static $input = array('ligands' => 'obmol', 'receptor' => 'null');

	public static  $result = array(
		'conf' => array(),
		'mol' => array(
				array('field_name' => 'num_atoms', 'field_label' => '# Atoms', 'type' => 'int', 'prefix' => 'molprop.'),
				array('field_name' => 'num_heavy_atoms', 'field_label' => '# Heavy Atoms', 'type' => 'int', 'prefix' => 'molprop.'),
				array('field_name' => 'num_bonds', 'field_label' => '# Bonds', 'type' => 'int', 'prefix' => 'molprop.'),
				array('field_name' => 'num_rot_bonds', 'field_label' => '# Rot. Bonds', 'type' => 'int', 'prefix' => 'molprop.'),
				array('field_name' => 'mol_weight', 'field_label' => 'Mol. Weight', 'type' => 'float', 'prefix' => 'molprop.'),
				array('field_name' => 'exact_mass', 'field_label' => 'Exact Mass', 'type' => 'float', 'prefix' => 'molprop.'),
				array('field_name' => 'total_charge', 'field_label' => 'Total Charge', 'type' => 'int', 'prefix' => 'molprop.'),
				array('field_name' => 'num_acceptors', 'field_label' => '# H-Acceptors', 'type' => 'int', 'prefix' => 'molprop.'),
				array('field_name' => 'num_donors', 'field_label' => '# H-Donors', 'type' => 'int', 'prefix' => 'molprop.'),
			)
		);

	# compute returns an dictionary of field values
	public function compute($ligands, $receptor = null) {
		$result = array();
		foreach($ligands as $l) {
			$out = array();
			
			$out['num_atoms'] = $l -> NumAtoms();
			$out['num_heavy_atoms'] = $l -> NumHvyAtoms();
			$out['num_bonds'] = $l -> NumBonds();
			$out['num_rot_bonds'] = $l -> NumRotors();
			
			$out['mol_weight'] = $l -> GetMolWt();
			$out['exact_mass'] = $l -> GetExactMass();
			$out['total_charge'] = $l -> GetTotalCharge();
			
			#$out['num_rings'] = $l -> NumAtoms();
			
			# get number of HBA and HBD
			$hba = 0;
			$hbd = 0;
			foreach(range(1, $out['num_atoms']) as $idx) {
				$atom = $l -> GetAtom($idx);
				if($atom -> IsHbondDonor()) {
					$hbd++;
				}
				if($atom -> IsHbondAcceptor()) {
					$hba++;
				}				
			}
			$out['num_donors'] = $hbd;
			$out['num_acceptors'] = $hba;
			
			# push values to the output
			$result[$l -> GetTitle()] = $out;
		}
		return $result;
	}
}
?>
