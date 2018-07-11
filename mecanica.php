<?php

trait Mecanica{
	public function get_mec_courses(){
		$mec_courses = array('ENG1031');
		return $mec_courses;
	}
	public function get_mec_users($mec_courses){
		
		return $mec_users;
	}
	public function create_mec_course($mec_users){
		$this->enrol_mec_students();

	}
	private function enrol_mec_users($mec_users){
		return 'asda';
	
	}
	public function teste(){
		return 'teste';
	}

}