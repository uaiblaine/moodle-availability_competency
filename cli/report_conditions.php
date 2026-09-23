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
 * Lists competency restrictions whose competency cannot be used as intended. Read-only.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use availability_competency\local\condition_report;

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'problem' => ''],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

$help = "Lists the restrictions by competency that name no usable competency, in every activity and
section of the site. Nothing is changed.

Problems:
  nocompetency  The restriction names no competency: a restore could not map it to a
                competency of this site, or an earlier version of the restriction form saved
                it without one. Editing the activity or section asks for a competency.
  missing       The competency no longer exists. Nobody counts as proficient in it.
  notlinked     The competency is no longer linked to the course. Ratings still count, but
                it cannot be chosen for a new restriction.

Options:
  --problem=LIST  Comma-separated problems to list, all of them by default.
  -h, --help      Print this help.

Example:
  php availability/condition/competency/cli/report_conditions.php --problem=nocompetency,missing

";

if ($options['help']) {
    echo $help;
    exit(0);
}

$known = [condition_report::PROBLEM_NO_COMPETENCY, condition_report::PROBLEM_MISSING, condition_report::PROBLEM_NOT_LINKED];
$wanted = $known;
if ($options['problem'] !== '') {
    $wanted = array_map('trim', explode(',', $options['problem']));
    $unknown = array_diff($wanted, $known);
    if ($unknown) {
        cli_error('Unknown problem: ' . implode(', ', $unknown) . PHP_EOL . PHP_EOL . $help);
    }
}

$problems = array_filter(condition_report::find(), fn($problem) => in_array($problem->problem, $wanted, true));
if (!$problems) {
    cli_writeln('No problems found.');
    exit(0);
}

$courses = $DB->get_records_list('course', 'id', array_unique(array_column($problems, 'courseid')), '', 'id, shortname');
cli_writeln(implode("\t", ['problem', 'course', 'item', 'competencyid', 'requirement', 'edit']));
foreach ($problems as $problem) {
    if ($problem->itemtype === 'module') {
        $url = new moodle_url('/course/modedit.php', ['update' => $problem->itemid]);
    } else {
        $url = new moodle_url('/course/editsection.php', ['id' => $problem->itemid]);
    }
    cli_writeln(implode("\t", [
        $problem->problem,
        $courses[$problem->courseid]->shortname ?? $problem->courseid,
        $problem->itemtype . ' ' . $problem->itemid,
        $problem->competencyid,
        ($problem->proficient ? 'proficient' : 'not proficient') . ' (' . $problem->scope . ')',
        $url->out(false),
    ]));
}
cli_writeln(count($problems) . ' problem(s) found.');
