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

class plugin_ffscore extends plugin_interface {
	public static $name = 'Score in FF';
	public static $desc = 'Computes scores in MMFF94 forcefields';
	public $batch_size = 0; # default size of batch, it's suppose to be as high as possible, although computation time should be lower than 10sec; 0 = unlimited
	
	public static $input = array('ligands' => 'obmol', 'receptor' => 'obmol');

	public static  $result = array(
		'conf' => array(
				array('field_name' => 'prerm_', 'field_label' => 'HPSCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'hmscore', 'field_label' => 'HMSCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'hsscore', 'field_label' => 'HSSCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'ave_score', 'field_label' => 'AVE_SCORE', 'type' => 'float', 'prefix' => 'confprop.'),
				array('field_name' => 'xscore_bind_energy', 'field_label' => 'XScore Binding Energy', 'type' => 'float', 'prefix' => 'confprop.'),
			),
		'mol' => array()
		);

	# compute returns an dictionary of field values
	public function compute($ligands, $receptor) {
		
		# group ligands by molecules
		$mols = array();
		foreach(array_keys($ligands) as $l) {
			list($conf_id, $mol_id) = explode('|', $l);
			$mols[$mol_id][] = $l;
			
		}
#		print_r($mols);
#		return;
		
		foreach($mols as $mol_id => $conf_keys) {
		
			$ligand = $ligands[$conf_keys[0]];
#			$ligand -> AddHydrogens();
			
			$receptor_num = $receptor -> NumAtoms();
			$ligand_num = $ligand -> NumAtoms();
			
			$complex = new OBMol($receptor);
			$complex -> add($ligand);

			#generate interacions
			$ligand_bit = new OBBitVec($receptor_num + $ligand_num);
			$receptor_bit = new OBBitVec($receptor_num + $ligand_num);
			$ligand_bit -> SetRangeOn($receptor_num + 1, $receptor_num + $ligand_num);
			$receptor_bit -> SetRangeOn(1, $receptor_num);
	
			$ff = OBForceField_FindForceField('mmff94');
			#$ff2 = OBForceField::FindForceField('mmff94');
			OBForceField_AddIntraGroup($ff,$ligand_bit); # bonded interactions in the ligand
			OBForceField_AddInterGroup($ff,$ligand_bit); # non-bonded between ligand-ligand atoms
			OBForceField_AddInterGroups($ff,$ligand_bit, $receptor_bit); # non-bonded between ligand and pocket atoms

			$success = OBForceField_Setup($ff, $complex);#, $obconstraint);

			if($success) {
				foreach($conf_keys as $key) {
					echo $ligand -> GetTitle(), '<br>';
					$ligand = $ligands[$key];
#					$ligand -> AddHydrogens();
					
					$start = microtime(true);
					if($ligand_num == $ligand -> NumAtoms()) {
						foreach(range(1, $ligand_num) as $idx) {
							$atom = $ligand -> GetAtom($idx);
							$complex_atom = $complex -> GetAtom($receptor_num + $idx);
							$complex_atom -> SetVector($atom -> GetVector());
						}
						OBForceField_SetCoordinates($ff, $complex);
						$pre_vdw = OBForceField_E_VDW($ff, 0);
						$pre_ele = OBForceField_E_Electrostatic($ff, 0);
					}
					else {
						echo 'PANIC!!!!';
					}
#					echo $ligand -> GetTitle(), '<br>', $pre_vdw, '<br>', $pre_ele, '<br>';
	#				OBForceField_MolecularDynamicsTakeNSteps($ff, 10, 400);
	#				OBForceField_ConjugateGradients($ff, 500, 0.05);
	#				$post_vdw = OBForceField_E_VDW($ff, 0);
	#				$post_ele = OBForceField_E_Electrostatic($ff, 0);
	#				echo $ligand -> GetTitle(), '<br>', $post_vdw, '<br>', $post_ele, '<br>';
					echo round(microtime(true) - $start, 4), '<br>';
				}
			}
		}
#		return $out;
	}
}
?>
