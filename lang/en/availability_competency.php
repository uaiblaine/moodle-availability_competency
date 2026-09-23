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
 * English language strings.
 *
 * @package availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['cleanuponcompetencydeletion'] = 'Clean up restrictions on competency deletion';
$string['cleanuponcompetencydeletion_desc'] = 'If enabled, all availability restrictions which name a particular competency are automatically removed from all activities and sections shortly after this competency is deleted, by a background task. This prevents activities and sections restricted to learners proficient in it from staying hidden from everyone due to an orphaned restriction which can never be fulfilled anymore.<br />If disabled, the restrictions remain in place and are shown as \'{$a}\' instead; nobody counts as proficient in a deleted competency, so a restriction requiring it stays closed and one requiring its absence is always met.';
$string['competency'] = 'Course competency';
$string['description'] = 'Require proficiency in a specified competency.';
$string['error_selectcompetency'] = 'Select a competency.';
$string['missing'] = '(Competency missing)';
$string['notlinked'] = '{$a} (not linked to this course)';
$string['notproficient_course'] = 'No – In this course';
$string['notproficient_global'] = 'No – Global';
$string['pluginname'] = 'Restriction by competency';
$string['privacy:metadata'] = 'The Restriction by competency plugin does not store any personal data.';
$string['proficient'] = 'Proficient';
$string['proficient_course'] = 'Yes – In this course';
$string['proficient_global'] = 'Yes – Global';
$string['requires_competency'] = 'You must be proficient in the competency <strong>{$a}</strong> in this course';
$string['requires_competency_global'] = 'You must be proficient in the competency <strong>{$a}</strong> (global proficiency)';
$string['requires_not_competency'] = 'You must not be proficient in the competency <strong>{$a}</strong> in this course';
$string['requires_not_competency_global'] = 'You must not be proficient in the competency <strong>{$a}</strong> (global proficiency)';
$string['task_remove_deleted_competency'] = 'Remove restrictions on a deleted competency';
$string['title'] = 'Competency';
