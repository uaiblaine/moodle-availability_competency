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
 * Coverage is declared in this docblock rather than with a CoversClass attribute because the
 * plugin still supports Moodle 4.5, whose moodle-cs cannot see PHP attributes.
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
     * Data provider which runs each test once with the cleanup setting enabled and once with it disabled.
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
     * @param bool $cleanupenabled Whether the cleanup setting is enabled.
     */
    public function test_only_competency_condition_is_removed(bool $cleanupenabled): void {
        $this->set_cleanup($cleanupenabled);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        $this->delete_competency($competency);

        if ($cleanupenabled) {
            // The whole restriction should be gone now.
            $this->assertNull($this->get_availability($page->cmid));
        } else {
            // The restriction should still be in place.
            $this->assert_availability_unchanged($page->cmid, $structure);
        }
    }

    /**
     * Tests that only the competency condition is removed, keeping the other conditions and their showc entries.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup setting is enabled.
     */
    public function test_competency_condition_is_removed_but_others_are_kept(bool $cleanupenabled): void {
        $this->set_cleanup($cleanupenabled);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        // The showc flags differ, so keeping the removed condition's flag instead of the date's is caught.
        $datecondition = \availability_date\condition::get_json('>=', time());
        $structure = \core_availability\tree::get_root_json(
            [$this->get_competency_json($competency), $datecondition],
            \core_availability\tree::OP_AND,
            [false, true]
        );
        $this->set_availability($page->cmid, $course->id, $structure);

        $this->delete_competency($competency);

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
     * @param bool $cleanupenabled Whether the cleanup setting is enabled.
     */
    public function test_unrelated_conditions_are_untouched(bool $cleanupenabled): void {
        $this->set_cleanup($cleanupenabled);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $othercompetency = $this->create_competency();

        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($othercompetency)]);
        $this->set_availability($page->cmid, $course->id, $structure);
        // Control: a second page restricted by the deleted competency shows whether the cleanup ran.
        $controlpage = $generator->create_module('page', ['course' => $course->id]);
        $controlstructure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($controlpage->cmid, $course->id, $controlstructure);

        $this->delete_competency($competency);

        // Unchanged either way: the restriction names only the other competency.
        $this->assert_availability_unchanged($page->cmid, $structure);
        if ($cleanupenabled) {
            $this->assertNull($this->get_availability($controlpage->cmid));
        } else {
            $this->assert_availability_unchanged($controlpage->cmid, $controlstructure);
        }
    }

    /**
     * Tests that a subtree which was already empty is kept, so that a tree not naming the competency stays as it was.
     *
     * Core treats an empty subtree as met, so under an OR it opens the item; dropping it would change access.
     */
    public function test_already_empty_subtree_is_kept(): void {
        $this->set_cleanup(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $controlpage = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $othercompetency = $this->create_competency();

        $emptysubtree = \core_availability\tree::get_nested_json([]);
        $structure = \core_availability\tree::get_root_json(
            [$this->get_competency_json($othercompetency), $emptysubtree],
            \core_availability\tree::OP_OR
        );
        $this->set_availability($page->cmid, $course->id, $structure);
        // Control: shows that the cleanup ran.
        $controlstructure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($controlpage->cmid, $course->id, $controlstructure);

        $this->delete_competency($competency);

        $this->assertNull($this->get_availability($controlpage->cmid));
        $this->assert_availability_unchanged($page->cmid, $structure);
    }

    /**
     * Tests that the cleanup runs in an ad hoc task, and only when the setting is enabled.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup setting is enabled.
     */
    public function test_cleanup_runs_in_an_adhoc_task(bool $cleanupenabled): void {
        $this->set_cleanup($cleanupenabled);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        \core_competency\api::delete_competency($competency);

        // Nothing changes in the request that deleted the competency.
        $this->assert_availability_unchanged($page->cmid, $structure);
        $tasks = \core\task\manager::get_adhoc_tasks(task\remove_deleted_competency::class);
        $this->assertCount($cleanupenabled ? 1 : 0, $tasks);

        $this->run_cleanup_tasks();
        if ($cleanupenabled) {
            $this->assertNull($this->get_availability($page->cmid));
        } else {
            $this->assert_availability_unchanged($page->cmid, $structure);
        }
    }

    /**
     * Tests that the cleanup is off on a site where the setting was never saved.
     */
    public function test_cleanup_is_off_when_never_configured(): void {
        unset_config('cleanuponcompetencydeletion', 'availability_competency');
        $this->assertFalse(get_config('availability_competency', 'cleanuponcompetencydeletion'));

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        \core_competency\api::delete_competency($competency);

        $this->assertSame([], \core\task\manager::get_adhoc_tasks(task\remove_deleted_competency::class));
        $this->assert_availability_unchanged($page->cmid, $structure);
    }

    /**
     * Tests that a task without a competency ID finishes without touching any restriction.
     *
     * @covers \availability_competency\task\remove_deleted_competency
     */
    public function test_task_without_competency_does_nothing(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        $task = new task\remove_deleted_competency();
        $task->set_custom_data([]);
        $this->expectOutputString("No competency ID given, nothing to remove.\n");
        $task->execute();

        $this->assert_availability_unchanged($page->cmid, $structure);
    }

    /**
     * Tests that the condition is also removed from a nested subtree and that an emptied subtree is dropped.
     *
     * @dataProvider cleanup_enabled_provider
     * @param bool $cleanupenabled Whether the cleanup setting is enabled.
     */
    public function test_competency_condition_is_removed_from_nested_subtree(bool $cleanupenabled): void {
        $this->set_cleanup($cleanupenabled);

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

        $this->delete_competency($competency);

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
     * @param bool $cleanupenabled Whether the cleanup setting is enabled.
     */
    public function test_competency_condition_is_removed_from_section(bool $cleanupenabled): void {
        global $DB;

        $this->set_cleanup($cleanupenabled);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 1], ['createsections' => true]);
        $competency = $this->create_competency();
        $sectionid = (int)$DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);

        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $DB->set_field('course_sections', 'availability', json_encode($structure), ['id' => $sectionid]);
        rebuild_course_cache($course->id, true);

        $this->delete_competency($competency);

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
     * Every operator, requirement and date outcome of a root tree [condition on the competency, date].
     *
     * The condition is removed exactly when it can never be met again, which depends on its requirement and
     * on the negation its operator pushes down to it.
     *
     * @return array
     */
    public static function operator_provider(): array {
        $cases = [];
        $removed = [
            '&' => [1 => true, 0 => false],
            '|' => [1 => true, 0 => false],
            '!&' => [1 => false, 0 => true],
            '!|' => [1 => false, 0 => true],
        ];
        foreach ($removed as $op => $byproficient) {
            foreach ($byproficient as $proficient => $expected) {
                foreach (['date passed' => true, 'date ahead' => false] as $datelabel => $datepassed) {
                    $cases["op {$op}, proficient {$proficient}, {$datelabel}"] = [$op, $proficient, $datepassed, $expected];
                }
            }
        }
        return $cases;
    }

    /**
     * Tests that only conditions that can never be met again are removed, and that no learner loses access.
     *
     * @dataProvider operator_provider
     * @param string $op Operator of the root tree.
     * @param int $proficient 1 when the condition requires proficiency, 0 when it requires its absence.
     * @param bool $datepassed Whether the date condition is met.
     * @param bool $expectremoved Whether the competency condition should be removed.
     */
    public function test_only_unmeetable_conditions_are_removed(
        string $op,
        int $proficient,
        bool $datepassed,
        bool $expectremoved
    ): void {
        $this->set_cleanup(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        $date = \availability_date\condition::get_json('>=', $datepassed ? time() - DAYSECS : time() + DAYSECS);
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency, $proficient), $date], $op);
        $this->set_availability($page->cmid, $course->id, $structure);

        \core_competency\api::delete_competency($competency);
        condition::wipe_static_cache();
        $availablebefore = $this->is_available_to($course->id, $page->cmid, $student->id);

        $this->run_cleanup_tasks();

        $tree = json_decode($this->get_availability($page->cmid));
        if ($expectremoved) {
            $this->assertCount(1, $tree->c);
            $this->assertEquals('date', $tree->c[0]->type);
        } else {
            $this->assert_availability_unchanged($page->cmid, $structure);
        }
        if ($availablebefore) {
            $this->assertTrue($this->is_available_to($course->id, $page->cmid, $student->id));
        }
    }

    /**
     * Tests that negations accumulate through nested trees, as core pushes them down.
     */
    public function test_negation_accumulates_through_nested_trees(): void {
        $this->set_cleanup(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();

        // Under '!&' then '!|' the two negations cancel out: "proficient" fails and goes, emptying its subtree.
        $nested = \core_availability\tree::get_nested_json(
            [$this->get_competency_json($competency, 1)],
            \core_availability\tree::OP_NOT_OR
        );
        $date = \availability_date\condition::get_json('>=', time());
        $structure = \core_availability\tree::get_root_json([$nested, $date], \core_availability\tree::OP_NOT_AND);
        $this->set_availability($page->cmid, $course->id, $structure);

        $this->delete_competency($competency);

        $tree = json_decode($this->get_availability($page->cmid));
        $this->assertCount(1, $tree->c);
        $this->assertEquals('date', $tree->c[0]->type);
        $this->assertEquals('!&', $tree->op);
    }

    /**
     * Tests that the task logs each changed item with its previous restriction.
     */
    public function test_task_logs_the_previous_restriction(): void {
        $this->set_cleanup(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $competency = $this->create_competency();
        $structure = \core_availability\tree::get_root_json([$this->get_competency_json($competency)]);
        $this->set_availability($page->cmid, $course->id, $structure);

        $log = $this->delete_competency($competency);

        $this->assertStringContainsString("changed course_modules id {$page->cmid} in course {$course->id}.", $log);
        $this->assertStringContainsString('Previous availability: ' . json_encode($structure), $log);
        $this->assertStringContainsString('New availability: none', $log);
        $this->assertStringContainsString('1 item(s) changed.', $log);
    }

    /**
     * Deletes a competency through core's API and runs the cleanup task it may have queued.
     *
     * @param int $competencyid The competency ID.
     * @return string What the task logged.
     */
    protected function delete_competency(int $competencyid): string {
        \core_competency\api::delete_competency($competencyid);
        return $this->run_cleanup_tasks();
    }

    /**
     * Runs the queued cleanup tasks.
     *
     * @return string What the tasks logged, captured because tests must not print.
     */
    protected function run_cleanup_tasks(): string {
        ob_start();
        $this->runAdhocTasks(task\remove_deleted_competency::class);
        return ob_get_clean();
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
     * Builds a stored competency condition on the given competency.
     *
     * It carries no scope, like a condition saved before 1.2.0; the observer reads type, competency ID and
     * proficient only.
     *
     * @param int $competencyid The competency ID which the condition names.
     * @param int $proficient 1 when proficiency is required, 0 when its absence is.
     * @return \stdClass The condition structure.
     */
    protected function get_competency_json(int $competencyid, int $proficient = 1): \stdClass {
        return (object)[
            'type' => 'competency',
            'competencyid' => $competencyid,
            'proficient' => $proficient,
        ];
    }

    /**
     * Enables or disables the cleanup of restrictions on competency deletion.
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
     * Whether a course module is available to a user, evaluated afresh.
     *
     * @param int $courseid The course ID.
     * @param int $cmid The course module ID.
     * @param int $userid The user ID.
     * @return bool
     */
    protected function is_available_to(int $courseid, int $cmid, int $userid): bool {
        rebuild_course_cache($courseid, true);
        return get_fast_modinfo($courseid, $userid)->get_cm($cmid)->available;
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
