<?php

trait medley{
	private function mecanica_courses(){
		$courses = array('medley_mecanica'=>array('ENG1031','ENG1032','ENG1700','ENG1701','ENG1702','ENG1703','ENG1704','ENG1705'
		,'ENG1707','ENG1708','ENG1709','ENG1710','ENG1712','ENG1713','ENG1714','ENG1715','ENG1716','ENG1717','ENG1718','ENG1719',
		'ENG1720','ENG1721','ENG1784'));
		return $courses;

	}

	private function adm_courses(){
		$courses = array('medley_adm'=>array('ADM1019','ADM1020'));
		return $courses;

	}

	public function get_medley_courses(){
		$mecanica = $this->mecanica_courses();
		$adm = $this->adm_courses();

		return array_merge($mecanica,$adm);

	}

	public function name_medley_courses(){
		$courses = $this->get_medley_courses();
		$courses = array_keys($courses);
		return $courses;

	}

}