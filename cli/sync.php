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
 * CLI sync for full external webservice synchronisation.
 *
 * Sample cron entry:
 * # 5 minutes past 4am
 * 5 4 * * * $sudo -u www-data /usr/bin/php /var/www/moodle/enrol/medley/cli/sync.php
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *
 * @package    enrol_medley
 * @copyright  2018 Ronnald R Machado| 2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/clilib.php");

// $CFG->debug = DEBUG_NONE;       // No warnings and errors at all
// $CFG->debug = DEBUG_MINIMAL;    // Fatal errors only
$CFG->debug = DEBUG_NORMAL;     // Errors, warnings and notices
// $CFG->debug = DEBUG_ALL;        // All problems except strict PHP warnings
// $CFG->debug = DEBUG_DEVELOPER;  // DEBUG_ALL with all debug messages and strict warnings

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('verbose'=>false, 'help'=>false), array('v'=>'verbose', 'h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Execute enrol sync with external webservice.
The enrol_medley plugin must be enabled and properly configured.

Options:
-v, --verbose         Print verbose progress information
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php enrol/medley/cli/sync.php

Sample cron entry:
# 5 minutes past 4am
5 4 * * * sudo -u www-data /usr/bin/php /var/www/moodle/enrol/medley/cli/sync.php
";

    echo $help;
    die;
}

if (!enrol_is_enabled('medley')) {
    cli_error('enrol_es plugin is disabled, synchronisation stopped', 2);
}

if (empty($options['verbose'])) {
    $trace = new null_progress_trace();
} else {
    $trace = new text_progress_trace();
}

/** @var enrol_medley $enrol  */
$enrol = enrol_get_plugin('medley');

exit($enrol->sync_enrolments($trace, true)); 
// [2017-09-07] variavel 'full' (true) para criar a lista completa ou em partes - se ignorado ou false, faz apenas metade da lista (de acordo com horario)

