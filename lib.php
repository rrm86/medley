<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External webservice enrolment plugin.
 *
 * This plugin synchronises enrolment and roles with external webservice
 *
 * @package    enrol_medley
 * @copyright  Ronnald R Machado
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('medley.php');

defined('MOODLE_INTERNAL') || die();


class enrol_medley_plugin extends enrol_plugin {

	use medley;

    public function __construct() {
        $this->load_config();

        if (isset($this->config->default_params) && !empty($this->config->default_params)) {
            $params = explode(',', $this->config->default_params);
            $default_params = array();
            foreach ($params as $p) {
                list($paramname, $value) = explode(':', $p);
                $default_params[$paramname] = $value;
            }
            $this->config->medley_default_params = $default_params;
        } else {
            $this->config->medley_default_params = array();
        }
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function instance_deleteable($instance) {
        return (!enrol_is_enabled('medley'));
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        return false;
    }

    /**
     * Forces synchronisation of all enrolments with external database.
     *
     * @param progress_trace $trace
     * @param null|int $onecourse limit sync to one course only (used primarily in restore)
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    //public function sync_enrolments(progress_trace $trace, $onecourse = null) {
	public function sync_enrolments(progress_trace $trace, $full = null) {
        global $CFG, $DB;
		
		// [2017-09-07] variavel 'full' para criar a lista completa ou em partes - alterado o parametro na API
		
        require_once($CFG->dirroot.'/group/lib.php');

        // we may need a lot of memory here
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);  

        $oneidnumber = null;
		/*
        if ($onecourse) {
            if (!$course = $DB->get_record('course', array('id'=>$onecourse), 'id,'.$this->enrol_localcoursefield)) {
                // Course does not exist, nothing to do.
                $trace->output("Requested course {$onecourse} does not exist, no sync performed.");
                $trace->finished();
                return;
            }
            if (empty($course->{$this->enrol_localcoursefield})) {
                $trace->output("Requested course {$onecourse} does not have {$this->enrol_localcoursefield}, no sync performed.");
                $trace->finished();
                return;
            }
            $oneidnumber = $course->idnumber;
        }
*/		
        /*iniciar o foreach aqui*/
        $course_names = $this->name_medley_courses();

        foreach ($course_names as $name) {
        	

    		$courses = $this->get_courses($trace,$name);

            if (count($courses)) {

                $ignorehidden = false;//$this->get_config('ignorehiddencourses');

                $trace->output('got courses');

                foreach ($courses as $course) {

                	
                    $course = array_change_key_case($course, CASE_LOWER);
                    $shortname = $course['shortname'];

                    // Does the course exist in moodle already?
                    $course_obj = $DB->get_record('course', array('shortname' => $shortname));
                    if (empty($course_obj)) { // Course doesn't exist

                        if (!$newcourseid = $this->create_course($course, $trace)) {
                            $trace->output('Warning: course not created');
                            continue;
                        }
                        $trace->output('Course created: '.$shortname);
                        $course_obj = $DB->get_record('course', array('id'=>$newcourseid));
                    } else {  // Check if course needs update & update as needed.
                        $this->update_course($course_obj, $course, $trace);
                        $trace->output('course already exists; updated: '.$shortname);
                    }
                    if ($ignorehidden && !$course_obj->visible) {
                        $trace->output('Course hidden; skipping enrols');
                        continue;
                    }

                    // Enrol & unenrol

                    foreach ($course['enrols'] as $role_shortname => $members) {
                    	

                        $trace->output('Enrolling: '. $role_shortname);

                        $role = $DB->get_record('role', array('shortname' => $role_shortname));

                        // Prune old medley enrolments
                        // hopefully they'll fit in the max buffer size for the RDBMS
                        $sql= "SELECT u.id as userid, u.username, ue.status, ra.contextid, ra.itemid as instanceid
                                 FROM {user} u
                                 JOIN {role_assignments} ra
                                   ON (ra.userid = u.id AND
                                       ra.component = 'enrol_medley' AND
                                       ra.roleid = :roleid)
                                 JOIN {user_enrolments} ue
                                   ON (ue.userid = u.id AND
                                       ue.enrolid = ra.itemid)
                                 JOIN {enrol} e
                                   ON (e.id = ue.enrolid)
                                WHERE u.deleted = 0
                                  AND e.courseid = :courseid ";

                        $params = array('roleid'=>$role->id, 'courseid'=>$course_obj->id);

                        $context = context_course::instance($course_obj->id);

                        if (!empty($members)) {

                            list($ml, $params2) = $DB->get_in_or_equal($members, SQL_PARAMS_NAMED, 'm', false);
                            $sql .= "AND u.username $ml"; // not in members
                            $params = array_merge($params, $params2);
                            unset($params2);

                        } else {
                            $shortname = format_string($course_obj->shortname, true, array('context' => $context));
                            $trace->output('No users to enrol with this role');
                        }
                        $todelete = $DB->get_records_sql($sql, $params);

                        if (!empty($todelete)) {
                            // no transactions here!
                            //$transaction = $DB->start_delegated_transaction();
                            foreach ($todelete as $row) {
                                $instance = $DB->get_record('enrol', array('id'=>$row->instanceid));
                                switch ($this->get_config('unenrolaction')) {
                                    case ENROL_EXT_REMOVED_UNENROL:
                                        $this->unenrol_user($instance, $row->userid);
                                        $trace->output(get_string('extremovedunenrol', 'enrol_medley',
                                                                  array('user_username'=> $row->username,
                                                                        'course_shortname'=>$course_obj->shortname,
                                                                        'course_id'=>$course_obj->id)));
                                        break;

                                    case ENROL_EXT_REMOVED_KEEP:
                                        // Keep - only adding enrolments
                                        break;

                                    case ENROL_EXT_REMOVED_SUSPEND:
                                        if ($row->status != ENROL_USER_SUSPENDED) {
                                            $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$row->userid));
                                            $trace->output(get_string('extremovedsuspend', 'enrol_medley',
                                                                      array('user_username'=> $row->username,
                                                                            'course_shortname'=>$course_obj->shortname,
                                                                            'course_id'=>$course_obj->id)));
                                        }
                                        break;

                                    case ENROL_EXT_REMOVED_SUSPENDNOROLES:
                                        if ($row->status != ENROL_USER_SUSPENDED) {
                                            $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, array('enrolid'=>$instance->id, 'userid'=>$row->userid));
                                        }
                                        role_unassign_all(array('contextid'=>$row->contextid, 'userid'=>$row->userid, 'component'=>'enrol_medley', 'itemid'=>$instance->id));
                                        $trace->output(get_string('extremovedsuspendnoroles', 'enrol_medley',
                                                                  array('user_username'=> $row->username,
                                                                        'course_shortname'=>$course_obj->shortname,
                                                                        'course_id'=>$course_obj->id)));
                                        break;
                                }
                            }
                            //$transaction->allow_commit();
                        }

                        // Insert current enrolments
                        // bad we can't do INSERT IGNORE with postgres...

                        // Add necessary enrol instance if not present yet;
                        $sql = "SELECT c.id, c.visible, e.id as enrolid
                                  FROM {course} c
                                  JOIN {enrol} e
                                    ON (e.courseid = c.id AND
                                        e.enrol = 'medley')
                                 WHERE c.id = :courseid";
                        $params = array('courseid'=>$course_obj->id);
                        if (!($course_instance = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE))) {
                            $course_instance = new stdClass();
                            $course_instance->id = $course_obj->id;
                            $course_instance->visible = $course_obj->visible;
                            $course_instance->enrolid = $this->add_instance($course_instance);
                        }

                        if (!$instance = $DB->get_record('enrol', array('id'=>$course_instance->enrolid))) {
                            continue; // Weird; skip this one.
                        }

                        $transaction = $DB->start_delegated_transaction();
                        foreach ($members as $medley_member) {

                            $sql = 'SELECT id,username,1 FROM {user} WHERE username = ? AND deleted = 0';

                            $member = $DB->get_record_sql($sql, array($medley_member));

                            if (empty($member) || empty($member->id)){
                            	
                                $trace->output('could not find user:'.$medley_member);
                                continue;
                            }

                            $sql= "SELECT ue.status
                                     FROM {user_enrolments} ue
                                     JOIN {enrol} e
                                       ON (e.id = ue.enrolid AND
                                           e.enrol = 'medley')
                                    WHERE e.courseid = :courseid
                                      AND ue.userid = :userid";
                            $params = array('courseid'=>$course_obj->id, 'userid'=>$member->id);
                            $userenrolment = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);

                            if (empty($userenrolment)) {

                                $this->enrol_user($instance, $member->id, $role->id);
                                // Make sure we set the enrolment status to active. If the user wasn't
                                // previously enrolled to the course, enrol_user() sets it. But if we
                                // configured the plugin to suspend the user enrolments _AND_ remove
                                // the role assignments on external unenrol, then enrol_user() doesn't
                                // set it back to active on external re-enrolment. So set it
                                // unconditionally to cover both cases.
                                $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$member->id));
                                $trace->output('Enrolled user: '.$member->username);

                            } else {

                                if (!$DB->record_exists('role_assignments', array('roleid'=>$role->id, 'userid'=>$member->id, 'contextid'=>$context->id, 'component'=>'enrol_medley', 'itemid'=>$instance->id))) {
                                    // This happens when reviving users or when user has multiple roles in one course.
                                    $context = context_course::instance($course_obj->id);

                                    role_assign($role->id, $member->id, $context->id, 'enrol_medley', $instance->id);
                                    $trace->output("Assign role to user '$member->username' in course '$course_obj->shortname ($course_obj->id)'");
                                }
                                if ($userenrolment->status == ENROL_USER_SUSPENDED) {
                                    // Reenable enrolment that was previously disabled. Enrolment refreshed
                                    $DB->set_field('user_enrolments', 'status', ENROL_USER_ACTIVE, array('enrolid'=>$instance->id, 'userid'=>$member->id));
                                    $trace->output('Reenable enrolment for user:'.$course_obj->shortname);
                                }
                            }
                        }

                        $transaction->allow_commit();
                    }

                    $trace->output('done enrols; starting groups');

                    foreach ($course['groups'] as $pucgroup => $members) {

                        if ($group = $DB->get_record('groups', array('name'=>$pucgroup, 'courseid' => $course_obj->id))) {
                            $group_id = $group->id;
                        } else {
                            $group = new stdclass();
                            $group->name = $pucgroup;
                            $group->courseid = $course_obj->id;
                            $group_id = groups_create_group($group);
                            $trace->output('Created group: '.$pucgroup);
                        }

                        foreach ($members as $member) {
                            // user must exist to avoid exception throw
                            if ($user_id = $DB->get_field('user', 'id', array('username' => $member))) {
                                groups_add_member($group_id, $user_id);  // it already avoids duplicate members
                            }
                        }

                        $groups_members = groups_get_members($group_id, 'u.username, u.id');
                        foreach ($groups_members as $gm) {

    						/*  alteração RGA - 2014-08-21: verifica se o usuario foi inscrito pelo plugin 'manual'    */
    						/*  alteração RRM - 2016-11-04: complementa a verificação, verifica se a inscrição foi por 'cohort'    */
    						$sql= "SELECT e.enrol
    								 FROM {enrol} e
    								 JOIN {user_enrolments} ue
    								   ON (e.id = ue.enrolid)
    								WHERE ue.userid = :userid
    								  AND e.courseid = :courseid
    								  AND (e.enrol = 'manual'
    								  OR  e.enrol = 'cohort')";

    						$params = array('courseid'=>$group->courseid, 'userid'=>$gm->id);
    						$userenrolmenttype = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
    						$userenrolmanual = false;
    						if (!empty($userenrolmenttype)) {
    							$userenrolmanual = true;
    						}

    						// if (!in_array($gm->username, $members)) {


    						// somente apaga da lista se nao estiver na lista de grupos e se nao tiver sido inscrito manualmente
    						/* fim da alteração RGA - 2014-09-02    */


    						if (!in_array($gm->username, $members) && !$userenrolmanual) {
                                groups_remove_member($group_id, $gm->id);
                            }
                        }

                    }
                    $trace->output('done with groups; done with this course');

                }
            }
    }
        $trace->finished();
    }

    private function get_courses($trace, $name) {
        global $DB;
		
		// [2017-09-07] variavel 'full' para criar a lista completa ou em partes - alterado o parametro na API
		
		$hora_atual = date ("G");
		($hora_atual == '0' || $hora_atual == '12') ? $parte = 1 : $parte = 2;
        $contador = 1; //Adicionado para testes do número de acessos ao webservice
		
        $courses = array();

        $functionname = 'obterDadosDisciplinasCCEAD';

        $visible_courses = $DB->get_records_menu('course', array('visible' => 1), '', 'shortname, id');
        $visible_courses =  array('ENG1031','ENG1032');

        $visible_courses = $this->get_medley_courses();
        $visible_courses = $visible_courses[$name];
       
       

		/*
        if (!$full) {
			$trace->output('Obtendo lista de disciplinas via webservice - '. $parte .'a parte. Hora atual: ' . $hora_atual);
		} else {
			$trace->output('Obtendo lista de disciplinas via webservice - Lista completa.');
		}
		*/
        $disciplinas = $this->call_medley($functionname);

		$num_disciplinas = count($disciplinas->obterDadosDisciplinasCCEADResult->vetDisciplina->DadosDisciplina);
		$disciplina_atual = 0; //Adicionado para contagem do número de disciplinas e divisao das partes

        $trace->output('Obtendo dados de ' . $num_disciplinas . ' disciplinas.');
		
		#print_r($disciplinas);
		
        if (1 == 1) {

			//$trace->output('Listagem completa obtida.');
			if (!$full) {
				$num_disciplinas_parte = ($parte==1) ? ($inicio_parte2-1) : ($num_disciplinas-$inicio_parte2+1);
				//$trace->output('Iniciando agora varredura da ' . $parte .'a parte (' . $num_disciplinas_parte . ' disciplinas).');
			}
			$trace->output('----------------------------------------------------------------');
            foreach ($disciplinas->obterDadosDisciplinasCCEADResult->vetDisciplina->DadosDisciplina as $d) {

				
				$enrol = array('editingteacher' => array(), 'student' => array());
                $groups = array();

                $trace->output($d->codDisciplina);

                if (is_object($d->vetTurma->DadosTurma)) {


					$trace->output('>>> single group');
					$t = $d->vetTurma->DadosTurma;

					//var_dump ($t);

                    #$groups[$t->codTurma] = array();

                    if ($t->professorTitular != 0) {
                        $t->professorTitular = str_pad($t->professorTitular, 5, "0", STR_PAD_LEFT);
                        #$enrol['editingteacher'][] = 'f'.$t->professorTitular;
                        #$groups[$t->codTurma][] = 'f'.$t->professorTitular;
						$trace->output('Main teacher: '.$t->professorTitular);	
					}

                    if (!empty($t->vetOutrosProfessores)) {
						$teachers = (array) $t->vetOutrosProfessores->int;
						foreach ($teachers as $value) {
							$value = str_pad($value, 5, "0", STR_PAD_LEFT);
							#$enrol['editingteacher'][] = 'f'.$value;
							#$groups[$t->codTurma][] = 'f'.$value;
							$trace->output('Additional teacher: '.$value);
						}
                    }

                    try {
                        if (in_array($d->codDisciplina, $visible_courses)) {

                                $trace->output('Course exists and is visible; getting students');

								//sleep(2);

								//$trace->output('[ Acesso: ' . $contador++ . ' ] - ' . date('d/m/Y h:i:s a', time()));
								

                                $trace->output('Turma: '.$t->codTurma);

								$result = $this->call_medley('obterDadosAlunosDisciplinaCCEAD',
                                                          array('disciplina' => $d->codDisciplina, 'turma' => $t->codTurma));

                                if (isset($result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos)) {
                                    if (is_array($result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos->DadosAluno)) {
                                        foreach ($result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos->DadosAluno as $a) {
                                        	$this->create_user($a);
                                            $a->matricula = str_pad($a->matricula, 7, "0", STR_PAD_LEFT);
                                            $enrol['student'][] = 'a'.$a->matricula;
                                            $groups['PUC-RIO'][] = 'a'.$a->matricula;
                                        }
                                    } else {
                                    	$this->create_user($a);
                                        $a = $result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos->DadosAluno;
                                        $a->matricula = str_pad($a->matricula, 7, "0", STR_PAD_LEFT);
                                        $enrol['student'][] = 'a'.$a->matricula;
                                        $groups['PUC-RIO'][] = 'a'.$a->matricula;
                                    }
                                }
                                $courses[] = array('shortname' => 'disciplinas',
                                   'fullname' => 'DISCIPLINAS AGREGADAS',
                                   'enrols' => $enrol,
                                   'groups' => $groups);

                        } else {
                            $trace->output('Course does not exist or is not visible; skipping students');
                        }
                    } catch (Exception $e) {
                        $trace->output('Exception call_medley: '.$e->getMessage());
                    }
                } else {
					$trace->output('>>> more groups');

					foreach ($d->vetTurma->DadosTurma as $t) {

                        #$groups[$t->codTurma] = array();

						//var_dump ($t);
						
                        if ($t->professorTitular != 0) {
                            $t->professorTitular = str_pad($t->professorTitular, 5, "0", STR_PAD_LEFT);
                            #$enrol['editingteacher'][] = 'f'.$t->professorTitular;
                            #$groups[$t->codTurma][] = 'f'.$t->professorTitular;
							$trace->output('Main teacher: '.$t->professorTitular);	
                        }
					
						if (!empty($t->vetOutrosProfessores)) {

							$teachers = (array) $t->vetOutrosProfessores->int;
							foreach ($teachers as $value) {
								$value = str_pad($value, 5, "0", STR_PAD_LEFT);
								#$enrol['editingteacher'][] = 'f'.$value;
								#$groups[$t->codTurma][] = 'f'.$value;
								$trace->output('Additional teacher: '.$value);
							}
						}
						
                        try {

                            if (in_array($d->codDisciplina, $visible_courses)) {
                            	print_r($d->codDisciplina);
                                $trace->output('Course exists and is visible; getting students...');

								//sleep(2);

								//$trace->output('[ Acesso: ' . $contador++ . ' ] - ' . date('d/m/Y H:i:s', time()));

                                $trace->output('Turma: '.$t->codTurma);

                                $result = $this->call_medley('obterDadosAlunosDisciplinaCCEAD',
                                                         array('disciplina' => $d->codDisciplina, 'turma' => $t->codTurma));
                                

                                if (isset($result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos)) {
                                    if (is_array($result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos->DadosAluno)) {
                                        foreach ($result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos->DadosAluno as $a) {
                                        	$this->create_user($a);
                                            $a->matricula = str_pad($a->matricula, 7, "0", STR_PAD_LEFT);
                                            $enrol['student'][] = 'a'.$a->matricula;
                                            $groups['PUC-RIO'][] = 'a'.$a->matricula;
											//$trace->output('alocação de aluno: ' . $a->matricula);
                                        }
                                    } else {
                                    	$this->create_user($a);
                                        $a = $result->obterDadosAlunosDisciplinaCCEADResult->vetAlunos->DadosAluno;
                                        $a->matricula = str_pad($a->matricula, 7, "0", STR_PAD_LEFT);
                                        $enrol['student'][] = 'a'.$a->matricula;
                                        $groups['PUC-RIO'][] = 'a'.$a->matricula;
										//$trace->output('alocação de aluno: ' . $a->matricula);
                                    }
                                }
                                $courses[] = array('shortname' => 'disciplinas',
                                   'fullname' => 'DISCIPLINAS AGREGADAS',
                                   'enrols' => $enrol,
                                   'groups' => $groups);


                            } else {
                              $trace->output('Course does not exist or is not visible; skipping students...');
                            }
                        } catch (Exception $e) {
							$trace->output('Exception call_medley: '.$e->getMessage());
                        }
                    }
                }
                

				//sleep(1);
            }
        } else {
            die('Obter dados disciplinas falhou. Abortando.');
        }
        
        #print_r($courses);
        $all_members = array();

        for ($i=0; $i < sizeof($courses); $i++) {
                $all_members = array_merge($all_members,$courses[$i]['enrols']['student']);
        		
        }

        $all_members = array_unique($all_members);
        foreach ($all_members as $m) {
            $all_enrols['student'][] = $m;
            $all_groups['PUC-RIO'][] = $m;
        }
        $coursesout[] = array('shortname' => $name,
                                   'fullname' => $name,
                                   'enrols' => $all_enrols,
                                   'groups' => $all_groups);


        return $coursesout;
    }

    private function call_medley($functionname, $params = array()) {

        $serverurl = $this->config->serverurl . '?wsdl';

        $params = array_merge($this->config->medley_default_params, $params);


        ////Do the main soap call
        try {
			$client = new SoapClient($serverurl, array('keep_alive' => false));    // incluido parametro keep_alive = false para fechar as conexoes (BUG 2017-09-15)

			echo ("Chamada do webservice: " . $functionname . "\r\n");
            $resp = $client->__soapCall($functionname, array($params));
            
			//echo ("Retorno do webservice sem erros.\r\n");
            return $resp;
        } catch (Exception $e) {
			echo ("*** Retorno do webservice com erro: " .$e->getMessage() . "(code: " . $e->faultcode . ") \r\n");
			return false;
        }
    }

    /**
     * Will create the moodle course from the template
     * course_ext is an array as obtained from medley -- flattened somewhat
     *
     * @param array $course_ext
     * @param progress_trace $trace
     * @return mixed false on error, id for the newly created course otherwise.
     */
    function create_course($course_ext, progress_trace $trace) {
        global $CFG, $DB;

        require_once("$CFG->dirroot/course/lib.php");

        // Override defaults with template course
        $template = false;
        if ($this->config->templatecourse) {
            if ($template = $DB->get_record('course', array('shortname'=>$this->config->templatecourse))) {
                $template = fullclone(course_get_format($template)->get_course());
                unset($template->id); // So we are clear to reinsert the record
                unset($template->fullname);
                unset($template->shortname);
                unset($template->idnumber);
            }
        }
        if (!$template) {
            $courseconfig = get_config('moodlecourse');
            $template = new stdClass();
            $template->summary        = '';
            $template->summaryformat  = FORMAT_HTML;
            $template->format         = $courseconfig->format;
            $template->newsitems      = $courseconfig->newsitems;
            $template->showgrades     = $courseconfig->showgrades;
            $template->showreports    = $courseconfig->showreports;
            $template->maxbytes       = $courseconfig->maxbytes;
            $template->groupmode      = $courseconfig->groupmode;
            $template->groupmodeforce = $courseconfig->groupmodeforce;
            $template->visible        = $courseconfig->visible;
            $template->lang           = $courseconfig->lang;
            $template->enablecompletion = $courseconfig->enablecompletion;
// *** incluir o numero de secoes padrao para os cursos criados manualmente -- nao esta funcionando com o template
            $template->numsections    = $courseconfig->numsections; // (2017/08/02)
        }

        $course = $template;

        $course->category = $this->config->defaultcategory;
        if (!$DB->record_exists('course_categories', array('id'=>$this->config->defaultcategory))) {
            $categories = $DB->get_records('course_categories', array(), 'sortorder', 'id', 0, 1);
            $first = reset($categories);
            $course->category = $first->id;
        }

        // Override with required ext data
        $course->idnumber  = $course_ext['shortname'];
        $course->fullname  = $course_ext['fullname'];
        $course->shortname = $course_ext['shortname'];
        if (empty($course->idnumber) || empty($course->fullname) || empty($course->shortname)) {
            // We are in trouble!
            $trace->output(get_string('cannotcreatecourse', 'enrol_medley').' '.var_export($course, true));
            return false;
        }

        $summary = $this->get_config('course_summary');
        if (!isset($summary) || empty($course_ext[$summary][0])) {
            $course->summary = '';
        } else {
            $course->summary = $course_ext[$this->get_config('course_summary')][0];
        }

        // Check if the shortname already exists if it does - skip course creation.
        if ($DB->record_exists('course', array('shortname' => $course->shortname))) {
            $trace->output(get_string('duplicateshortname', 'enrol_medley', $course));
            return false;
        }

        $newcourse = create_course($course);
        return $newcourse->id;
    }

    /**
     * Will update a moodle course with new values from external webservice
     * A field will be updated only if it is marked to be updated
     * on sync in plugin settings
     *
     * @param object $course
     * @param array $externalcourse
     * @param progress_trace $trace
     * @return bool
     */
    protected function update_course($course, $externalcourse, progress_trace $trace) {
        global $CFG, $DB;

        $coursefields = array ('shortname', 'fullname', 'summary');
        static $shouldupdate;

        // Initialize $shouldupdate variable. Set to true if one or more fields are marked for update.
        if (!isset($shouldupdate)) {
            $shouldupdate = false;
            foreach ($coursefields as $field) {
                $shouldupdate = $shouldupdate || $this->get_config('course_'.$field.'_updateonsync');
            }
        }

        // If we should not update return immediately.
        if (!$shouldupdate) {
            return false;
        }

        require_once("$CFG->dirroot/course/lib.php");
        $courseupdated = false;
        $updatedcourse = new stdClass();
        $updatedcourse->id = $course->id;

        // Update course fields if necessary.
        foreach ($coursefields as $field) {
            // If field is marked to be updated on sync && field data was changed update it.
            if ($this->get_config('course_'.$field.'_updateonsync')
                    && isset($externalcourse[$this->get_config('course_'.$field)][0])
                    && $course->{$field} != $externalcourse[$this->get_config('course_'.$field)][0]) {
                $updatedcourse->{$field} = $externalcourse[$this->get_config('course_'.$field)][0];
                $courseupdated = true;
            }
        }

        if (!$courseupdated) {
            $trace->output(get_string('courseupdateskipped', 'enrol_medley', $course));
            return false;
        }

        // Do not allow empty fullname or shortname.
        if ((isset($updatedcourse->fullname) && empty($updatedcourse->fullname))
                || (isset($updatedcourse->shortname) && empty($updatedcourse->shortname))) {
            // We are in trouble!
            $trace->output(get_string('cannotupdatecourse', 'enrol_medley', $course));
            return false;
        }

        // Check if the shortname already exists if it does - skip course updating.
        if (isset($updatedcourse->shortname)
                && $DB->record_exists('course', array('shortname' => $updatedcourse->shortname))) {
            $trace->output(get_string('cannotupdatecourse_duplicateshortname', 'enrol_medley', $course));
            return false;
        }

        // Finally - update course in DB.
        update_course($updatedcourse);
        $trace->output(get_string('courseupdated', 'enrol_medley', $course));

        return true;
    }

    /**
     * Automatic enrol sync executed during restore.
     * Useful for automatic sync by course->idnumber or course category.
     * @param stdClass $course course record
     */
    public function restore_sync_course($course) {
        // TODO: this can not work because restore always nukes the course->idnumber, do not ask me why (MDL-37312)
        // NOTE: for now restore does not do any real logging yet, let's do the same here...
        $trace = new error_log_progress_trace();
        $this->sync_enrolments($trace, $course->id);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;

        if ($instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>$this->get_name()))) {
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.

        } else if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_KEEP) {
            if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
                $this->enrol_user($instance, $userid, null, 0, 0, $data->status);
            }

        } else {
            if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
                $this->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_SUSPENDED);
            }
        }
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        global $DB;

        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL or $this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Skip any roles restore, they should be already synced automatically.
            return;
        }

        // Just restore every role.
        if ($DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            role_assign($roleid, $userid, $contextid, 'enrol_'.$instance->enrol, $instance->id);
        }
    }

    public function restore_group_member($instance, $groupid, $userid) {
        groups_add_member($groupid, $userid, $this::$component, $instance->id);
    }

    //Funcao para verificar e formatar variavel $numPeriodo
    //Exemplo: 20141 -> 2014.1
    //Retorno "" em caso de erro
    //Caso contrario retorna $numPeriodo formatado
    function addDot( $numPeriodo ) {
        $newNumPeriodo = "";

        if (strlen($numPeriodo)==5 && (preg_match("/\d{5}/", $numPeriodo))) {
            $newNumPeriodo = preg_replace("/(\d{4})(\d{1})/", "\${1}.\${2}", $numPeriodo);
        } else if(strlen($numPeriodo)==6 && (preg_match("/\d{4}[.]\d{1}/", $numPeriodo))) {
            //do nothing
            $newNumPeriodo = $numPeriodo;
        }


        return $newNumPeriodo;
    }
    private function create_user($user){
    	global $DB;
    	$user = (array) $user;
    	try{
    		$id = $DB->get_record('user', array('username' => 'a'.$user['matricula']), $fields = 'id', $strictness = IGNORE_MULTIPLE);
    		$fullname = $this->split_fullname($user['nome']);
    		if(is_null($id->id)){
    			$new_user = new stdClass();
    			$new_user->auth="ws";
    			$new_user->confirmed="1";
	            $new_user->mnethostid="1";
	            $new_user->username='a'.$user['matricula'];
	            $new_user->firstname=$fullname[0];
	            $new_user->lastname=$fullname[1];
	            if ($user['email'] != null) {
	            	$new_user->email=$user['email'];
	            }
	            $DB->insert_record('user', $new_user, false);#create new user 
    		}
    	}catch (Exception $e){
    		print_r($e);
    }
 
    }

    public function split_fullname($fullname){
	        list($firstname, $lastname) = explode(' ', $fullname,2);
	        if (is_null($lastname)) {
	        	$lastname = 'Empty';
	        }
	        return array($firstname, $lastname);
	    }
}