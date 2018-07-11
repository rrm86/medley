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
 * Database enrolment plugin settings and presets.
 *
 * @package    enrol_medley
 * @copyright  Ronnald R Machado
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_medley_settings', '', get_string('pluginname_desc', 'enrol_medley')));

    $settings->add(new admin_setting_heading('enrol_medley_exdbheader', get_string('settingsheaderdb', 'enrol_medley'), ''));

    $options = array('SOAP');
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('enrol_medley/protocol',
                                                  get_string('protocol', 'enrol_medley'), get_string('protocol_desc', 'enrol_medley'),
                                                  '', $options));

    $settings->add(new admin_setting_configtext('enrol_medley/serverurl',
                                                get_string('serverurl', 'enrol_medley'), get_string('serverurl_desc', 'enrol_medley'),
                                                'localhost'));

    $settings->add(new admin_setting_configtext('enrol_medley/default_params',
                                                get_string('default_params', 'enrol_medley'), get_string('default_params_desc', 'enrol_medley'), ''));

    /*
    $settings->add(new admin_setting_configtext('enrol_medley/courses_function',
                                                get_string('courses_function', 'enrol_medley'), get_string('courses_function_desc', 'enrol_medley'), ''));

    $settings->add(new admin_setting_configtext('enrol_medley/role_assignments_function',
                                                get_string('role_assignments_function', 'enrol_medley'), get_string('role_assignments_function', 'enrol_medley'), ''));

    $settings->add(new admin_setting_heading('enrol_medley_localheader', get_string('settingsheaderlocal', 'enrol_medley'), ''));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_medley/localcoursefield', get_string('localcoursefield', 'enrol_medley'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_medley/localuserfield', get_string('localuserfield', 'enrol_medley'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_medley/localrolefield', get_string('localrolefield', 'enrol_medley'), '', 'shortname', $options));

    $settings->add(new admin_setting_heading('enrol_medley_remoteheader', get_string('settingsheaderremote', 'enrol_medley'), ''));

    $settings->add(new admin_setting_configtext('enrol_medley/remotecoursefield', get_string('remotecoursefield', 'enrol_medley'), get_string('remotecoursefield_desc', 'enrol_medley'), ''));

    $settings->add(new admin_setting_configtext('enrol_medley/remoteuserfield', get_string('remoteuserfield', 'enrol_medley'), get_string('remoteuserfield_desc', 'enrol_medley'), ''));

    $settings->add(new admin_setting_configtext('enrol_medley/remoterolefield', get_string('remoterolefield', 'enrol_medley'), get_string('remoterolefield_desc', 'enrol_medley'), ''));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_medley/defaultrole', get_string('defaultrole', 'enrol_medley'), get_string('defaultrole_desc', 'enrol_medley'), $student->id, $options));
    }
    */

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_medley/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));

    if (!during_initial_install()) {
        $settings->add(new admin_setting_configselect('enrol_medley/defaultcategory', get_string('defaultcategory', 'enrol_medley'), get_string('defaultcategory_desc', 'enrol_medley'), 1, make_categories_options()));
    }

    $settings->add(new admin_setting_configtext('enrol_medley/templatecourse', get_string('templatecourse', 'enrol_medley'), get_string('templatecourse_desc', 'enrol_medley'), ''));
}
