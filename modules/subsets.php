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

class subsets extends base {
	public function create() {
		global $User;
		# get project name and use it as table name
		$this -> get_project_db();
		$subset_name = $this -> Database -> secure_mysql($_GET['subset_name']);
		if(!empty($subset_name)) {
			$query = 'INSERT INTO '.$this -> project.'docking_user_subset (name, user_id) VALUES ("'.$subset_name.'", '.$User -> id().')';
			#echo $query.'</br>';
			$this -> Database -> query($query);
			header('Location: '.$this -> get_link(array('module' => 'molecules','mode' => 'subset_add', 'subset_id_add' => $this -> Database -> insert_id())));
		}
		else {
			header('Location: '.$this -> get_link(array('module' => 'molecules','mode' => 'search')));
		}
	}
}
?>
