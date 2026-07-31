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
$string['cleanuponcompetencydeletion_desc'] = 'If enabled, all availability restrictions which require a particular competency are automatically removed from all activities and sections as soon as this competency is deleted. This prevents these activities and sections from staying hidden from everyone due to an orphaned restriction which can never be fulfilled anymore.<br />If disabled, the restrictions remain in place and are shown as \'{$a}\' instead.';
$string['competency'] = 'Course competency';
$string['description'] = 'Require proficiency in a specified competency.';
$string['missing'] = '(Competency missing)';
$string['no'] = 'No';
$string['pluginname'] = 'Restriction by competency';
$string['privacy:metadata'] = 'The Restriction by competency plugin does not store any personal data.';
$string['proficient'] = 'Proficient';
$string['requires_competency'] = 'You must be proficient in the competency: <strong>{$a}</strong>';
$string['requires_not_competency'] = 'You must not be proficient in the competency: <strong>{$a}</strong>';
$string['title'] = 'Competency';
$string['yes'] = 'Yes';
