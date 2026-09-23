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
 * Unit tests for the frontend class.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

/**
 * Unit tests for the frontend class.
 *
 * Coverage is declared in this docblock rather than with a CoversClass attribute because the
 * plugin still supports Moodle 4.5, whose moodle-cs cannot see PHP attributes.
 *
 * @covers \availability_competency\frontend
 */
final class frontend_test extends \advanced_testcase {
    /**
     * Enables competencies.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
    }

    /**
     * The form offers the competencies linked to the course, alphabetically, as a JSON array.
     */
    public function test_options_are_the_linked_competencies_in_alphabetical_order(): void {
        $course = $this->getDataGenerator()->create_course();
        $ids = [];
        foreach (['Zeta', 'Comp 10', 'Beta & gamma', 'Comp 2', 'Alpha'] as $name) {
            $ids[$name] = $this->create_competency($name, [$course->id]);
        }
        $this->create_competency('Not linked', []);

        [$options] = $this->init_params($course);

        $this->assertSame(['Alpha', 'Beta &amp; gamma', 'Comp 2', 'Comp 10', 'Zeta'], array_column($options, 'name'));
        $this->assertSame($ids['Alpha'], $options[0]->id);
        $this->assertStringStartsWith('[', json_encode($options));
    }

    /**
     * The button is offered only while competencies are enabled and some are linked to the course.
     */
    public function test_allow_add(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($this->allow_add($course));

        $this->create_competency('Linked', [$course->id]);
        $this->assertTrue($this->allow_add($course));

        set_config('enabled', 0, 'core_competency');
        $this->assertFalse($this->allow_add($course));
    }

    /**
     * Editing an item offers the competencies its conditions name but the course no longer links.
     */
    public function test_stored_competencies_not_linked_are_offered(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $linked = $this->create_competency('Linked', [$course->id]);
        $unlinked = $this->create_competency('Unlinked & gone', []);
        $missing = $unlinked + 1000;

        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tree = \core_availability\tree::get_root_json([
            (object)['type' => 'competency', 'competencyid' => $linked, 'proficient' => 1, 'scope' => 'course'],
            \core_availability\tree::get_nested_json([
                (object)['type' => 'competency', 'competencyid' => $unlinked, 'proficient' => 0, 'scope' => 'global'],
                (object)['type' => 'competency', 'competencyid' => $missing, 'proficient' => 1, 'scope' => 'course'],
            ], \core_availability\tree::OP_OR),
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        [, $stored] = $this->init_params($course, $cm);

        $this->assertEquals([
            (object)['id' => $unlinked, 'name' => 'Unlinked &amp; gone'],
            (object)['id' => $missing, 'name' => null],
        ], $stored);

        // Control: adding a new activity offers no stored competencies.
        [, $none] = $this->init_params($course);
        $this->assertSame([], $none);
    }

    /**
     * The select class follows the Moodle branch, as core's own availability forms do.
     */
    public function test_select_class_follows_the_branch(): void {
        global $CFG;
        $course = $this->getDataGenerator()->create_course();

        $CFG->branch = '405';
        $this->assertSame('custom-select', $this->init_params($course)[2]);
        $CFG->branch = '500';
        $this->assertSame('form-select', $this->init_params($course)[2]);
        $CFG->branch = '502';
        $this->assertSame('form-select', $this->init_params($course)[2]);
    }

    /**
     * Creates a competency, optionally linked to courses.
     *
     * @param string $shortname Short name.
     * @param array $courseids IDs of the courses to link it to.
     * @return int Competency ID.
     */
    protected function create_competency(string $shortname, array $courseids): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $competency = $generator->create_competency([
            'competencyframeworkid' => $framework->get('id'),
            'shortname' => $shortname,
        ]);
        foreach ($courseids as $courseid) {
            $generator->create_course_competency(['courseid' => $courseid, 'competencyid' => $competency->get('id')]);
        }
        return (int)$competency->get('id');
    }

    /**
     * Calls the protected get_javascript_init_params() on a new frontend.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Course module being edited.
     * @return array
     */
    protected function init_params(\stdClass $course, ?\cm_info $cm = null): array {
        $method = new \ReflectionMethod(frontend::class, 'get_javascript_init_params');
        return $method->invoke(new frontend(), $course, $cm, null);
    }

    /**
     * Calls the protected allow_add() on a new frontend.
     *
     * @param \stdClass $course Course.
     * @return bool
     */
    protected function allow_add(\stdClass $course): bool {
        $method = new \ReflectionMethod(frontend::class, 'allow_add');
        return $method->invoke(new frontend(), $course, null, null);
    }
}
