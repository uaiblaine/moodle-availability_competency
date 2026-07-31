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
 * Unit tests for the event observer.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

/**
 * Unit tests for the event observer.
 *
 * @covers \availability_competency\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Load required libraries and prepare the environment.
     */
    public function setUp(): void {
        global $CFG;

        $this->resetAfterTest();

        // We need the course library for rebuild_course_cache().
        require_once($CFG->dirroot . '/course/lib.php');

        // Deleting competencies through the core competency API requires the manage capability.
        $this->setAdminUser();

        parent::setUp();
    }

    /**
     * Data provider which runs each test once with the cleanup kill switch enabled and once with it disabled.
     *
     * @return array
     */
    public static function cleanup_enabled_provider(): array {
        return [
            'Cleanup enabled' => [true],
            'Cleanup disabled' => [false],
        ];
    }

    /**
     * Tests that a restriction which only requires the deleted competency is removed (if the cleanup is enabled).
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup kill switch is enabled.
     */
    public function test_only_competency_condition_is_removed(bool $cleanupenabled): void {
        // Set the kill switch.
        $this->set_cleanup($cleanupenabled);

        // Create the necessary data assets.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        // Restrict the activity to the competency only.
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        // Delete the competency.
        \core_competency\api::delete_competency($competency);

        if ($cleanupenabled) {
            // The whole restriction should be gone now.
            $this->assertNull($this->get_availability($page->cmid));
        } else {
            // The restriction should still be in place.
            $this->assert_availability_unchanged($page->cmid, $structure);
        }
    }

    /**
     * Tests that only the competency condition is removed while other conditions and the showc array are kept.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup kill switch is enabled.
     */
    public function test_competency_condition_is_removed_but_others_are_kept(bool $cleanupenabled): void {
        // Set the kill switch.
        $this->set_cleanup($cleanupenabled);

        // Create the necessary data assets.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        // Restrict the activity to the competency AND a date, using an explicit (asymmetric) showc array.
        $datecondition = \availability_date\condition::get_json('>=', time());
        $structure = \core_availability\tree::get_root_json(
            [$this->get_competency_json($competency), $datecondition],
            \core_availability\tree::OP_AND,
            [false, true]
        );
        $this->set_availability($page->cmid, $course->id, $structure);

        // Delete the competency.
        \core_competency\api::delete_competency($competency);

        if ($cleanupenabled) {
            // The date condition should remain, with its showc entry kept in sync.
            $tree = json_decode($this->get_availability($page->cmid));
            $this->assertNotNull($tree);
            $this->assertCount(1, $tree->c);
            $this->assertEquals('date', $tree->c[0]->type);
            $this->assertEquals([true], $tree->showc);
        } else {
            // The whole restriction should still be in place.
            $this->assert_availability_unchanged($page->cmid, $structure);
        }
    }

    /**
     * Tests that a restriction referring to a different competency is left untouched.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup kill switch is enabled.
     */
    public function test_unrelated_conditions_are_untouched(bool $cleanupenabled): void {
        // Set the kill switch.
        $this->set_cleanup($cleanupenabled);

        // Create the necessary data assets.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $othercompetency = $this->create_competency();

        // Restrict the activity to the other competency.
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($othercompetency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        // Delete the first competency.
        \core_competency\api::delete_competency($competency);

        /* The restriction must still be in place, regardless of whether the cleanup is enabled, as it does not
           require the deleted competency. */
        $this->assert_availability_unchanged($page->cmid, $structure);
    }

    /**
     * Tests that the condition is also removed from a nested subtree and that an emptied subtree is dropped.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup kill switch is enabled.
     */
    public function test_competency_condition_is_removed_from_nested_subtree(bool $cleanupenabled): void {
        // Set the kill switch.
        $this->set_cleanup($cleanupenabled);

        // Create the necessary data assets.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $mixedpage = $generator->create_module('page', ['course' => $course->id]);
        $emptypage = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        // Case 1: A date condition AND a nested OR subtree which requires the competency or a date.
        $datecondition = \availability_date\condition::get_json('>=', time());
        $nested = \core_availability\tree::get_nested_json(
            [$this->get_competency_json($competency), $datecondition],
            \core_availability\tree::OP_OR
        );
        $mixedstructure = \core_availability\tree::get_root_json([$datecondition, $nested]);
        $this->set_availability($mixedpage->cmid, $course->id, $mixedstructure);

        // Case 2: A nested subtree which only requires the competency, so the whole tree becomes empty afterwards.
        $emptynested = \core_availability\tree::get_nested_json([$this->get_competency_json($competency)]);
        $emptystructure = \core_availability\tree::get_root_json([$emptynested]);
        $this->set_availability($emptypage->cmid, $course->id, $emptystructure);

        // Delete the competency.
        \core_competency\api::delete_competency($competency);

        if ($cleanupenabled) {
            // Case 1: The nested subtree should now hold only the date condition, the root still two children.
            $mixedtree = json_decode($this->get_availability($mixedpage->cmid));
            $this->assertCount(2, $mixedtree->c);
            $this->assertCount(1, $mixedtree->c[1]->c);
            $this->assertEquals('date', $mixedtree->c[1]->c[0]->type);

            // Case 2: The emptied subtree should have been dropped, leaving no restriction at all.
            $this->assertNull($this->get_availability($emptypage->cmid));
        } else {
            // Both restrictions should still be in place.
            $this->assert_availability_unchanged($mixedpage->cmid, $mixedstructure);
            $this->assert_availability_unchanged($emptypage->cmid, $emptystructure);
        }
    }

    /**
     * Tests that the cleanup also covers restrictions placed on course sections.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup kill switch is enabled.
     */
    public function test_competency_condition_is_removed_from_section(bool $cleanupenabled): void {
        global $DB;

        // Set the kill switch.
        $this->set_cleanup($cleanupenabled);

        // Create the necessary data assets.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 1], ['createsections' => true]);
        $competency = $this->create_competency();
        $sectionid = (int)$DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);

        // Restrict the section to the competency only.
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $DB->set_field('course_sections', 'availability', json_encode($structure), ['id' => $sectionid]);
        rebuild_course_cache($course->id, true);

        // Delete the competency.
        \core_competency\api::delete_competency($competency);

        $availability = $DB->get_field('course_sections', 'availability', ['id' => $sectionid]);
        if ($cleanupenabled) {
            // The whole restriction should be gone now.
            $this->assertNull($availability);
        } else {
            // The restriction should still be in place.
            $this->assertEquals(json_encode($structure), $availability);
        }
    }

    /**
     * Creates a competency framework with one competency and returns the competency ID.
     *
     * @return int The competency ID.
     */
    protected function create_competency(): int {
        $lpg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);

        return (int)$competency->get('id');
    }

    /**
     * Builds the JSON structure of an availability_competency condition, as the editing form would save it.
     *
     * @param int $competencyid The competency ID which the condition requires.
     * @return \stdClass The condition structure.
     */
    protected function get_competency_json(int $competencyid): \stdClass {
        return (object)[
            'type' => 'competency',
            'competencyid' => $competencyid,
            'proficient' => 1,
        ];
    }

    /**
     * Enables or disables the cleanup kill switch on the plugin settings.
     *
     * @param bool $cleanupenabled Whether the cleanup should be enabled.
     */
    protected function set_cleanup(bool $cleanupenabled): void {
        set_config('cleanuponcompetencydeletion', $cleanupenabled ? 1 : 0, 'availability_competency');
    }

    /**
     * Sets the availability restriction of a course module and rebuilds the course cache.
     *
     * @param int $cmid The course module ID.
     * @param int $courseid The course ID.
     * @param \stdClass $structure The availability tree structure.
     */
    protected function set_availability(int $cmid, int $courseid, \stdClass $structure): void {
        global $DB;

        $DB->set_field('course_modules', 'availability', json_encode($structure), ['id' => $cmid]);
        rebuild_course_cache($courseid, true);
    }

    /**
     * Returns the raw availability restriction of a course module directly from the database.
     *
     * @param int $cmid The course module ID.
     * @return string|null The availability JSON or null.
     */
    protected function get_availability(int $cmid): ?string {
        global $DB;

        return $DB->get_field('course_modules', 'availability', ['id' => $cmid]);
    }

    /**
     * Asserts that the availability restriction of a course module still equals the given (unchanged) structure.
     *
     * @param int $cmid The course module ID.
     * @param \stdClass $structure The availability tree structure which was originally set.
     */
    protected function assert_availability_unchanged(int $cmid, \stdClass $structure): void {
        $this->assertEquals(json_encode($structure), $this->get_availability($cmid));
    }
}
