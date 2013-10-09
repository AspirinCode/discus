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

class Timer
{
	function __construct() {
		$this -> startPoint = microtime(true);
		$this -> endPoint = 0;
		$this -> mysqlTime = 0;
		$this -> templateTime = 0;
		$this -> overallTime = 0;
		$this -> templateTimeStarted = false;
	}
	
	//General timer
	function overallTimer() {
		$this -> endPoint = microtime(true);
		$this -> overallTime = $this -> endPoint - $this -> startPoint;
		return round($this -> overallTime, 5);
	}

	//Template timer
	function start_template() {
		if($this -> templateTimeStarted === false) {
			$this -> startPointTemplate = microtime(true);
			$this -> templateTimeStarted = true;
		}
	}
	
	function stop_template() {
		$this -> templateTime += microtime(true) - $this -> startPointTemplate;
		$this -> templateTimeStarted = false;
	}
	
	function templateTimer() {
		$this -> stop_template();
		return round($this -> templateTime, 5);
	}

	function templatePercentage() {
		return round(($this -> templateTime / $this -> overallTimer())* 100, 2);
	}

	//Mysql timer
	function start_mysql() {
		$this -> startPointMysql = microtime(true);
	}

	function stop_mysql() {
		$this -> mysqlTime += microtime(true) - $this -> startPointMysql;
	}

	function mysqlTimer() {
		return round($this -> mysqlTime, 5);
	}

	function mysqlPercentage() {
		return round(($this -> mysqlTimer() / $this -> overallTimer())* 100, 2);
	}
}
?>
