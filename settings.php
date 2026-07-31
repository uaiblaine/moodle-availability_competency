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
 * Availability competency - Settings file
 *
 * @package availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'availability_competency/cleanuponcompetencydeletion',
        new \core\lang_string('cleanuponcompetencydeletion', 'availability_competency'),
        new \core\lang_string(
            'cleanuponcompetencydeletion_desc',
            'availability_competency',
            new \core\lang_string('missing', 'availability_competency')
        ),
        0
    ));
}
