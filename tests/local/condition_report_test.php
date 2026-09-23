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
 * Unit tests for the condition report.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency\local;

/**
 * Unit tests for the condition report.
 *
 * Coverage is declared in this docblock rather than with a CoversClass attribute because the
 * plugin still supports Moodle 4.5, whose moodle-cs cannot see PHP attributes.
 *
 * @covers \availability_competency\local\condition_report
 */
final class condition_report_test extends \advanced_testcase {
    /**
     * The report lists exactly the conditions naming no, a deleted or an unlinked competency.
     */
    public function test_find_lists_each_problem_once(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $linked = $this->create_competency([$course->id, $othercourse->id]);
        $unlinked = $this->create_competency([$othercourse->id]);
        $deleted = $this->create_competency([$course->id]);
        $DB->delete_records('competency', ['id' => $deleted]);

        $healthy = $this->restrict_module($course, [$this->condition($linked)]);
        $notlinked = $this->restrict_module($course, [$this->condition($unlinked, 0, 'global')]);
        $missing = $this->restrict_module($course, [
            (object)['type' => 'date', 'd' => '>=', 't' => 1],
            \core_availability\tree::get_nested_json([$this->condition($deleted)], \core_availability\tree::OP_OR),
        ]);
        course_create_sections_if_missing($course, 1);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1], '*', MUST_EXIST);
        $DB->set_field(
            'course_sections',
            'availability',
            json_encode(\core_availability\tree::get_root_json([$this->condition(0)])),
            ['id' => $section->id]
        );
        // Control: the same competency is fine in a course it is linked to.
        $this->restrict_module($othercourse, [$this->condition($unlinked)]);

        $problems = condition_report::find();

        $found = array_map(fn($p) => [$p->problem, $p->itemtype, $p->itemid, $p->competencyid], $problems);
        $this->assertEqualsCanonicalizing([
            [condition_report::PROBLEM_NOT_LINKED, 'module', $notlinked, $unlinked],
            [condition_report::PROBLEM_MISSING, 'module', $missing, $deleted],
            [condition_report::PROBLEM_NO_COMPETENCY, 'section', (int)$section->id, 0],
        ], $found);
        $this->assertNotContains($healthy, array_column($problems, 'itemid'));

        $notlinkedproblem = $problems[array_search($notlinked, array_column($problems, 'itemid'))];
        $this->assertSame((int)$course->id, $notlinkedproblem->courseid);
        $this->assertSame(0, $notlinkedproblem->proficient);
        $this->assertSame('global', $notlinkedproblem->scope);
    }

    /**
     * A site without competency restrictions has nothing to report.
     */
    public function test_find_without_restrictions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $this->assertSame([], condition_report::find());
    }

    /**
     * Creates a competency linked to courses.
     *
     * @param array $courseids IDs of the courses to link it to.
     * @return int Competency ID.
     */
    protected function create_competency(array $courseids): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency(['competencyframeworkid' => $framework->get('id')]);
        foreach ($courseids as $courseid) {
            $generator->create_course_competency(['courseid' => $courseid, 'competencyid' => $competency->get('id')]);
        }
        return (int)$competency->get('id');
    }

    /**
     * A competency condition as stored in an availability tree.
     *
     * @param int $competencyid Competency ID.
     * @param int $proficient 1 when proficiency is required, 0 when its absence is.
     * @param string $scope Scope of the rating.
     * @return \stdClass
     */
    protected function condition(int $competencyid, int $proficient = 1, string $scope = 'course'): \stdClass {
        return (object)['type' => 'competency', 'competencyid' => $competencyid, 'proficient' => $proficient, 'scope' => $scope];
    }

    /**
     * Creates a page restricted by the given conditions.
     *
     * @param \stdClass $course Course.
     * @param array $conditions Conditions and nested trees for the root of the tree.
     * @return int Course module ID.
     */
    protected function restrict_module(\stdClass $course, array $conditions): int {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tree = \core_availability\tree::get_root_json($conditions);
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $page->cmid]);
        return (int)$page->cmid;
    }
}
