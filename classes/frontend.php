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
    /** @var array Competency options as courseid => options, because core asks for them twice per form. */
    protected $optionscache = [];

    /**
     * Get javascript strings.
     * @return array
     */
    protected function get_javascript_strings() {
        return [
            'competency',
            'error_selectcompetency',
            'missing',
            'notlinked',
            'notproficient_course',
            'notproficient_global',
            'proficient',
            'proficient_course',
            'proficient_global',
        ];
    }

    /**
     * Function to initialize the params for the javascript array.
     *
     * The YUI form receives, in this order: the competencies linked to the course, which are the
     * only ones offered for a new restriction; the competencies this item's stored conditions name
     * but which are no longer linked, so that editing the item keeps them instead of losing them;
     * and the CSS class of a select on this Moodle branch.
     *
     * @param \stdClass $course
     * @param \cm_info|null $cm
     * @param \section_info|null $section
     * @return array
     */
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        global $CFG;

        $options = $this->get_course_competency_options((int)$course->id);

        $availability = null;
        if ($cm !== null) {
            $availability = $cm->availability;
        } else if ($section !== null) {
            $availability = $section->availability;
        }

        return [
            $options,
            $this->get_stored_competencies($availability, $options, (int)$course->id),
            // Core's own availability forms use custom-select on 4.5 and form-select from 5.0 on.
            (int)$CFG->branch < 500 ? 'custom-select' : 'form-select',
        ];
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
        // Only show if competencies are enabled on the site and some are linked to the course.
        return \core_competency\api::is_enabled() && !empty($this->get_course_competency_options((int)$course->id));
    }

    /**
     * Competencies linked to the course, sorted by name.
     *
     * Linking a competency to the course is what narrows a site's competencies down to a list a
     * select can offer, and it leaves the choice of frameworks to core's course competency picker.
     * The list is kept on the instance and sorted with the collator, following
     * availability_competencies by ssystems GmbH.
     *
     * @param int $courseid Course ID.
     * @return array List of objects with id and name, the name already formatted for HTML.
     */
    protected function get_course_competency_options(int $courseid): array {
        global $DB;

        if (!array_key_exists($courseid, $this->optionscache)) {
            $sql = "SELECT c.id, c.shortname
                      FROM {competency} c
                      JOIN {competency_coursecomp} cc ON c.id = cc.competencyid
                     WHERE cc.courseid = ?";
            $options = [];
            foreach ($DB->get_records_sql($sql, [$courseid]) as $rec) {
                $options[] = (object)[
                    'id' => (int)$rec->id,
                    'name' => format_string($rec->shortname, true, ['context' => \context_course::instance($courseid)]),
                ];
            }
            \core_collator::asort_objects_by_property($options, 'name', \core_collator::SORT_NATURAL);
            // The collator keeps the keys; renumber them so that the list reaches the YUI form as a JSON array.
            $this->optionscache[$courseid] = array_values($options);
        }
        return $this->optionscache[$courseid];
    }

    /**
     * Competencies named by the stored conditions of the item being edited that are not linked to the course.
     *
     * @param string|null $availability The item's stored availability JSON.
     * @param array $options Competencies linked to the course, as returned by get_course_competency_options().
     * @param int $courseid Course ID.
     * @return array List of objects with id and name, the name null when the competency does not exist.
     */
    protected function get_stored_competencies(?string $availability, array $options, int $courseid): array {
        global $DB;

        if (empty($availability)) {
            return [];
        }
        $ids = [];
        self::collect_competency_ids(json_decode($availability), $ids);
        foreach ($options as $option) {
            unset($ids[$option->id]);
        }
        if (empty($ids)) {
            return [];
        }

        $names = $DB->get_records_list('competency', 'id', array_keys($ids), '', 'id, shortname');
        $stored = [];
        foreach (array_keys($ids) as $id) {
            $name = null;
            if (isset($names[$id])) {
                $name = format_string($names[$id]->shortname, true, ['context' => \context_course::instance($courseid)]);
            }
            $stored[] = (object)['id' => $id, 'name' => $name];
        }
        return $stored;
    }

    /**
     * Collects the competency IDs of every competency condition in an availability tree.
     *
     * @param mixed $node A decoded tree or condition, anything else is ignored.
     * @param array $ids Collected IDs as keys (by reference).
     * @return void
     */
    protected static function collect_competency_ids($node, array &$ids): void {
        if (!is_object($node)) {
            return;
        }
        if (isset($node->c) && is_array($node->c)) {
            foreach ($node->c as $child) {
                self::collect_competency_ids($child, $ids);
            }
            return;
        }
        if (isset($node->type) && $node->type === 'competency' && isset($node->competencyid) && (int)$node->competencyid > 0) {
            $ids[(int)$node->competencyid] = true;
        }
    }
}
