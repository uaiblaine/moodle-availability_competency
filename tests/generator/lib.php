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
 * Data generator for availability_competency plugin.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Generator class for availability_competency plugin.
 */
class availability_competency_generator extends component_generator_base {
    /**
     * Resets the generator's state between tests.
     */
    public function reset(): void {
        // Nothing to reset.
    }

    /**
     * Replaces the restrictions of an activity with one competency condition.
     *
     * Adapted from the data generator of availability_competencies by ssystems GmbH.
     *
     * @param array $data 'cmid' and 'competencyid'; optionally 'proficient' (1 by default) and 'scope'. Without a
     *     scope the condition has the shape saved before 1.2.0, which reads the course rating.
     */
    public function create_activity_restriction(array $data): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');

        $condition = [
            'type' => 'competency',
            'competencyid' => (int)$data['competencyid'],
            'proficient' => ($data['proficient'] ?? '') === '' || (int)$data['proficient'] ? 1 : 0,
        ];
        if (($data['scope'] ?? '') !== '') {
            $condition['scope'] = $data['scope'];
        }
        $tree = \core_availability\tree::get_root_json([(object)$condition]);

        $courseid = $DB->get_field('course_modules', 'course', ['id' => $data['cmid']], MUST_EXIST);
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $data['cmid']]);
        // The course cache was built without the restriction.
        rebuild_course_cache($courseid, true);
    }
}
