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

namespace availability_competency;

/**
 * Availability competency - Frontend class
 *
 * @package    availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * Get javascript strings.
     * @return array
     */
    protected function get_javascript_strings() {
        return ['competency', 'proficient', 'yes', 'no'];
    }

    /**
     * Function to initialize the params for the javascript array.
     *
     * @param \stdClass $course
     * @param \cm_info|null $cm
     * @param \section_info|null $section
     * @return array
     */
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        global $DB;

        $jsarray = [];
        $sql = "SELECT c.id, c.shortname
                  FROM {competency} c
                  JOIN {competency_coursecomp} cc ON c.id = cc.competencyid
                 WHERE cc.courseid = ?";
        $competencies = $DB->get_records_sql($sql, [$course->id]);

        foreach ($competencies as $rec) {
            $jsarray[] = (object)[
                'id' => $rec->id,
                'name' => format_string($rec->shortname, true, ['context' => \context_course::instance($course->id)]),
            ];
        }

        return [$jsarray];
    }

    /**
     * Function to decide if the button to select the restriction will be presented.
     *
     * @param \stdClass $course
     * @param \cm_info|null $cm
     * @param \section_info|null $section
     * @return bool
     */
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        global $DB;
        // Only show if competencies are enabled on the site.
        if (!\core_competency\api::is_enabled()) {
            return false;
        }
        // Only show if there are some competencies linked to the course.
        return $DB->record_exists('competency_coursecomp', ['courseid' => $course->id]);
    }
}
