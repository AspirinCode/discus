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

require_once "/usr/local/lib/openbabel.php";

if($_GET['smiles']) {
	$informat = 'smi';
	$outformat = $_GET['output'];
        $smiles = rawurldecode($_GET['smiles']);
	$OBMol = new OBMol;
	$OBConversion = new OBConversion;

	$OBConversion->SetInAndOutFormats($informat, $outformat);
	$OBConversion->ReadString($OBMol, $smiles);
        
        # switch mime types and other format specific options
	if($outformat == 'svg') {
		header('Content-type: image/svg+xml');
		
		$OBMol -> DeleteNonPolarHydrogens();
		# gen2d
		OBOp_c_Do(OBOp_FindType("gen2d"),$OBMol);
		
		$OBConversion -> SetOptions('C', $OBConversion::OUTOPTIONS);
		$OBConversion -> SetOptions('d', $OBConversion::OUTOPTIONS);
	}
	elseif($outformat == 'sdf') {
		# gen2d
		OBOp_c_Do(OBOp_FindType("gen2d"),$OBMol);
	}
        
        echo $OBConversion->WriteString($OBMol);
}
?>
