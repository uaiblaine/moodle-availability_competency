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
 * Unit tests for the condition class.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

/**
 * Unit tests for the condition class.
 *
 * Coverage is declared in this docblock rather than with a CoversClass attribute because the
 * plugin still supports Moodle 4.5, whose moodle-cs cannot see PHP attributes.
 *
 * Several techniques here (wiping the static cache in setUp, the backup_ids temporary table for
 * restore tests) follow the tests of availability_competencies by ssystems GmbH.
 *
 * @covers \availability_competency\condition
 */
final class condition_test extends \advanced_testcase {
    /** @var bool Whether a test created the backup_ids temporary table. */
    protected $backupidstemp = false;

    /**
     * Enables competencies and forgets what earlier tests read.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
        set_config('enabled', 1, 'core_competency');
        // The database is rolled back after each test, but static state is not.
        condition::wipe_static_cache();
    }

    /**
     * Drops the backup_ids temporary table again, which resetting the database does not do.
     */
    protected function tearDown(): void {
        if ($this->backupidstemp) {
            \backup_controller_dbops::drop_backup_ids_temp_table('');
            $this->backupidstemp = false;
        }
        parent::tearDown();
    }

    /**
     * A condition in each scope reads its own rating, and a "not proficient" requirement is the opposite.
     */
    public function test_each_scope_reads_its_own_rating(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $courseonly = $this->getDataGenerator()->create_user();
        $globalonly = $this->getDataGenerator()->create_user();
        $this->rate_in_course($courseonly->id, $course->id, $competency, true);
        $this->rate_globally($globalonly->id, $competency, true);

        $this->assertTrue($this->available($this->make_condition($competency, 1, 'course'), $course, $courseonly->id));
        $this->assertFalse($this->available($this->make_condition($competency, 1, 'global'), $course, $courseonly->id));
        $this->assertFalse($this->available($this->make_condition($competency, 1, 'course'), $course, $globalonly->id));
        $this->assertTrue($this->available($this->make_condition($competency, 1, 'global'), $course, $globalonly->id));

        $this->assertFalse($this->available($this->make_condition($competency, 0, 'course'), $course, $courseonly->id));
        $this->assertTrue($this->available($this->make_condition($competency, 0, 'global'), $course, $courseonly->id));
    }

    /**
     * A rating which is not proficient does not count, and the core "must not" inverts the result.
     */
    public function test_not_proficient_rating_and_inversion(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->rate_in_course($user->id, $course->id, $competency, false);

        $condition = $this->make_condition($competency, 1, 'course');
        $this->assertFalse($this->available($condition, $course, $user->id));
        $this->assertTrue($this->available($condition, $course, $user->id, true));
    }

    /**
     * The course scope reads the rating of the course the item belongs to, not of another course.
     */
    public function test_course_scope_ignores_a_rating_in_another_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id, $othercourse->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->rate_in_course($user->id, $othercourse->id, $competency, true);

        $condition = $this->make_condition($competency, 1, 'course');
        $this->assertFalse($this->available($condition, $course, $user->id));
        // Control: the same condition in the course holding the rating is met.
        $this->assertTrue($this->available($condition, $othercourse, $user->id));
    }

    /**
     * Unlinking the competency from the course keeps the ratings counting, without an exception.
     */
    public function test_rating_counts_after_unlinking(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $rated = $this->getDataGenerator()->create_user();
        $unrated = $this->getDataGenerator()->create_user();
        $this->rate_in_course($rated->id, $course->id, $competency, true);

        $this->setAdminUser();
        \core_competency\api::remove_competency_from_course($course->id, $competency);
        $this->assertFalse(\core_competency\course_competency::record_exists_select(
            'courseid = ? AND competencyid = ?',
            [$course->id, $competency]
        ));

        $condition = $this->make_condition($competency, 1, 'course');
        $this->assertTrue($this->available($condition, $course, $rated->id));
        $this->assertFalse($this->available($condition, $course, $unrated->id));
    }

    /**
     * Ratings keep counting while competencies are disabled on the site, without an exception.
     */
    public function test_rating_counts_while_competencies_are_disabled(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $rated = $this->getDataGenerator()->create_user();
        $unrated = $this->getDataGenerator()->create_user();
        $this->rate_in_course($rated->id, $course->id, $competency, true);
        set_config('enabled', 0, 'core_competency');

        $condition = $this->make_condition($competency, 1, 'course');
        $this->assertTrue($this->available($condition, $course, $rated->id));
        $this->assertFalse($this->available($condition, $course, $unrated->id));
    }

    /**
     * A competency that does not exist counts as not proficient, even when a stale rating is left behind.
     */
    public function test_missing_competency_counts_as_not_proficient(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->rate_in_course($user->id, $course->id, $competency, true);
        $this->rate_globally($user->id, $competency, true);

        // Control: while the competency exists, the ratings are met.
        $this->assertTrue($this->available($this->make_condition($competency, 1, 'course'), $course, $user->id));
        $this->assertTrue($this->available($this->make_condition($competency, 1, 'global'), $course, $user->id));

        // Core refuses to delete a linked or rated competency (competency::can_all_be_deleted()),
        // so remove the record directly.
        $DB->delete_records('competency', ['id' => $competency]);
        condition::wipe_static_cache();

        foreach (['course', 'global'] as $scope) {
            $this->assertFalse($this->available($this->make_condition($competency, 1, $scope), $course, $user->id));
            $this->assertTrue($this->available($this->make_condition($competency, 0, $scope), $course, $user->id));
            $this->assertFalse($this->available($this->make_condition(0, 1, $scope), $course, $user->id));
            $this->assertTrue($this->available($this->make_condition(0, 0, $scope), $course, $user->id));
        }
    }

    /**
     * Guests see a restricted item as unavailable instead of the course page breaking.
     *
     * Goes through modinfo, which is the path a course page takes.
     */
    public function test_guest_does_not_break_the_course_page(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $proficient = $this->create_restricted_page($course, $competency, 1, 'course');
        $notproficient = $this->create_restricted_page($course, $competency, 0, 'global');

        $this->setGuestUser();
        $modinfo = get_fast_modinfo($course);
        $this->assertFalse($modinfo->get_cm($proficient)->available);
        $this->assertTrue($modinfo->get_cm($notproficient)->available);
    }

    /**
     * A category-level prohibition of viewing course competencies does not break the course page.
     */
    public function test_prohibited_competency_view_does_not_break_the_course_page(): void {
        global $CFG;
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $competency = $this->create_competency([$course->id]);
        $cmid = $this->create_restricted_page($course, $competency, 1, 'course');
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->rate_in_course($user->id, $course->id, $competency, true);

        assign_capability(
            'moodle/competency:coursecompetencyview',
            CAP_PROHIBIT,
            $CFG->defaultuserroleid,
            \context_coursecat::instance($category->id)->id
        );
        $this->setUser($user);
        $this->assertFalse(has_capability('moodle/competency:coursecompetencyview', \context_course::instance($course->id)));

        $this->assertTrue(get_fast_modinfo($course)->get_cm($cmid)->available);
    }

    /**
     * Evaluating a restriction writes nothing, not even for a user who was never rated.
     */
    public function test_evaluation_writes_no_record(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $user = $this->getDataGenerator()->create_user();
        $before = $DB->count_records('competency_usercompcourse');

        $this->assertFalse($this->available($this->make_condition($competency, 1, 'course'), $course, $user->id));

        $this->assertSame($before, $DB->count_records('competency_usercompcourse'));
    }

    /**
     * The ratings of a user are read once per request, not once per restricted item.
     */
    public function test_ratings_are_read_once_per_request(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $first = $this->create_competency([$course->id]);
        $second = $this->create_competency([$course->id]);
        $user = $this->getDataGenerator()->create_user();
        $this->rate_in_course($user->id, $course->id, $second, true);
        $this->available($this->make_condition($first, 1, 'course'), $course, $user->id);

        $reads = $DB->perf_get_reads();
        $this->assertFalse($this->available($this->make_condition($first, 1, 'course'), $course, $user->id));
        $this->assertSame($reads, $DB->perf_get_reads());

        // A second competency only costs the lookup of its own name.
        $this->assertTrue($this->available($this->make_condition($second, 1, 'course'), $course, $user->id));
        $this->assertSame($reads + 1, $DB->perf_get_reads());
    }

    /**
     * A user who is not logged in is not proficient, and asking costs no query.
     */
    public function test_not_logged_in_user_is_not_proficient(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);

        $reads = $DB->perf_get_reads();
        $this->assertFalse($this->available($this->make_condition($competency, 1, 'global'), $course, 0));
        $this->assertTrue($this->available($this->make_condition($competency, 0, 'course'), $course, 0));
        $this->assertSame($reads, $DB->perf_get_reads());
    }

    /**
     * A long-lived process, such as a cron runner, reads the ratings again once its cache is old enough.
     */
    public function test_long_lived_process_reads_ratings_again(): void {
        $clock = $this->mock_clock_with_frozen();
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $user = $this->getDataGenerator()->create_user();
        $condition = $this->make_condition($competency, 1, 'course');
        $this->assertFalse($this->available($condition, $course, $user->id));

        // A rating given in another process: the generator stores it without the event this process would observe.
        $this->rate_in_course($user->id, $course->id, $competency, true);
        $this->assertFalse($this->available($condition, $course, $user->id));

        $clock->bump(condition::STATIC_CACHE_LIFETIME);
        $this->assertTrue($this->available($condition, $course, $user->id));
    }

    /**
     * The ratings a process keeps are capped, however many users it evaluates.
     */
    public function test_static_cache_is_bounded(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $condition = $this->make_condition($competency, 1, 'course');

        // Users do not have to exist: a user without ratings is not proficient.
        for ($userid = 1; $userid <= condition::STATIC_CACHE_SIZE + 10; $userid++) {
            $this->available($condition, $course, $userid);
        }

        $cached = new \ReflectionProperty(condition::class, 'courseproficiencies');
        $this->assertLessThanOrEqual(condition::STATIC_CACHE_SIZE, count($cached->getValue()));
    }

    /**
     * A rating given earlier in the same request is seen by the next evaluation.
     *
     * @covers \availability_competency\observer::competency_evidence_created
     */
    public function test_rating_in_the_same_request_is_seen(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $condition = $this->make_condition($competency, 1, 'course');

        // Ask first, so that "not proficient" is what the request has read.
        $this->assertFalse($this->available($condition, $course, $user->id));

        $this->setAdminUser();
        \core_competency\api::grade_competency_in_course($course->id, $user->id, $competency, 4);

        $this->assertTrue($this->available($condition, $course, $user->id));
    }

    /**
     * Descriptions name the competency through core's deferred formatting and say which rating counts.
     */
    public function test_description(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id], 'Problem & solving');
        $info = new \core_availability\mock_info($course);
        $name = condition::description_format_string('Problem & solving');

        $cases = [
            [1, 'course', false, 'requires_competency'],
            [1, 'course', true, 'requires_not_competency'],
            [0, 'course', false, 'requires_not_competency'],
            [0, 'course', true, 'requires_competency'],
            [1, 'global', false, 'requires_competency_global'],
            [0, 'global', false, 'requires_not_competency_global'],
        ];
        foreach ($cases as [$proficient, $scope, $not, $identifier]) {
            $description = $this->make_condition($competency, $proficient, $scope)->get_description(true, $not, $info);
            $this->assertSame(get_string($identifier, 'availability_competency', $name), $description);
        }

        // Core resolves the placeholder later, escaping the name once.
        $formatted = \core_availability\info::format_info(
            $this->make_condition($competency, 1, 'global')->get_description(true, false, $info),
            $course
        );
        $this->assertStringContainsString('<strong>Problem &amp; solving</strong>', $formatted);
    }

    /**
     * A missing competency is described as such, an unlinked one keeps its name.
     */
    public function test_description_of_missing_and_unlinked_competencies(): void {
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id], 'Teamwork');
        $info = new \core_availability\mock_info($course);

        $this->setAdminUser();
        \core_competency\api::remove_competency_from_course($course->id, $competency);
        $this->assertStringContainsString(
            'Teamwork',
            $this->make_condition($competency, 1, 'course')->get_description(true, false, $info)
        );

        $this->assertSame(
            get_string('requires_competency', 'availability_competency', get_string('missing', 'availability_competency')),
            $this->make_condition(0, 1, 'course')->get_description(true, false, $info)
        );
    }

    /**
     * The constructor requires competencyid (0 or more) and proficient, accepts only known scopes and reads no scope as course.
     */
    public function test_constructor_and_save(): void {
        $legacy = new condition((object)['type' => 'competency', 'competencyid' => 7, 'proficient' => 1]);
        $this->assertEquals(
            (object)['type' => 'competency', 'competencyid' => 7, 'proficient' => 1, 'scope' => 'course'],
            $legacy->save()
        );

        // 0 is accepted: it is what a restore stores for a competency it could not map.
        $unmapped = new condition((object)['type' => 'competency', 'competencyid' => 0, 'proficient' => 1]);
        $this->assertSame(0, $unmapped->save()->competencyid);

        $global = new condition((object)['type' => 'competency', 'competencyid' => 7, 'proficient' => 0, 'scope' => 'global']);
        $this->assertSame('global', $global->save()->scope);
        $this->assertSame(0, $global->save()->proficient);

        $invalid = [
            ['proficient' => 1],
            ['competencyid' => 7],
            ['competencyid' => 7, 'proficient' => 1, 'scope' => 'site'],
            ['competencyid' => -1, 'proficient' => 1],
        ];
        foreach ($invalid as $fields) {
            try {
                new condition((object)(['type' => 'competency'] + $fields));
                $this->fail('Expected a coding_exception for ' . json_encode($fields));
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('competency condition', $e->getMessage());
            }
        }
    }

    /**
     * A restore remaps the competency through core's mapping.
     */
    public function test_restore_remaps_through_the_mapping(): void {
        $restoreid = $this->create_restore_id();
        \restore_dbops::set_backup_ids_record($restoreid, \core_competency\competency::TABLE, 100, 200);
        $logger = new \core_backup_html_logger(\backup::LOG_WARNING);
        $condition = $this->make_condition(100, 1, 'global');

        $this->assertTrue($condition->include_after_restore($restoreid, 1, $logger, 'Page 1', $this->make_restore_task(false)));
        $this->assertTrue($condition->update_after_restore($restoreid, 1, $logger, 'Page 1'));
        $this->assertSame(200, $condition->save()->competencyid);
        $this->assertSame('global', $condition->save()->scope);
    }

    /**
     * Without a mapping, a restore on the same site keeps the competency.
     */
    public function test_restore_on_the_same_site_keeps_the_competency(): void {
        $restoreid = $this->create_restore_id();
        $logger = new \core_backup_html_logger(\backup::LOG_WARNING);
        $condition = $this->make_condition(100, 1, 'course');

        $this->assertTrue($condition->include_after_restore($restoreid, 1, $logger, 'Page 1', $this->make_restore_task(true)));
        $this->assertFalse($condition->update_after_restore($restoreid, 1, $logger, 'Page 1'));
        $this->assertSame(100, $condition->save()->competencyid);
        $this->assertStringNotContainsString('was not restored', $logger->get_html());
    }

    /**
     * Without a mapping, a restore from another site points the condition at no competency and says so.
     */
    public function test_restore_from_another_site_without_mapping_is_logged(): void {
        $restoreid = $this->create_restore_id();
        $logger = new \core_backup_html_logger(\backup::LOG_WARNING);
        $condition = $this->make_condition(100, 1, 'course');

        $this->assertTrue($condition->include_after_restore($restoreid, 1, $logger, 'Page 1', $this->make_restore_task(false)));
        $this->assertTrue($condition->update_after_restore($restoreid, 1, $logger, 'Page 1'));
        $this->assertSame(0, $condition->save()->competencyid);
        $this->assertStringContainsString('Page 1', $logger->get_html());
        $this->assertStringContainsString('was not restored', $logger->get_html());
    }

    /**
     * A condition already pointing at no competency is left alone by a later restore, without a warning.
     */
    public function test_restore_leaves_a_condition_without_competency_alone(): void {
        $restoreid = $this->create_restore_id();
        $logger = new \core_backup_html_logger(\backup::LOG_WARNING);
        $condition = $this->make_condition(0, 1, 'course');

        $this->assertTrue($condition->include_after_restore($restoreid, 1, $logger, 'Page 1', $this->make_restore_task(false)));
        $this->assertFalse($condition->update_after_restore($restoreid, 1, $logger, 'Page 1'));
        $this->assertSame(0, $condition->save()->competencyid);
        $this->assertStringNotContainsString('was not restored', $logger->get_html());
    }

    /**
     * Duplicating an activity goes through the restore path and keeps the condition intact.
     */
    public function test_duplicating_an_activity_keeps_the_condition(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $competency = $this->create_competency([$course->id]);
        $cmid = $this->create_restricted_page($course, $competency, 0, 'global');

        if (method_exists(\core_courseformat\local\cmactions::class, 'duplicate')) {
            // Moodle 5.2 deprecated duplicate_module() in favour of cmactions::duplicate().
            $newcm = \core_courseformat\formatactions::cm($course)->duplicate($cmid);
        } else {
            $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($cmid));
        }

        $tree = json_decode($DB->get_field('course_modules', 'availability', ['id' => $newcm->id]));
        $this->assertCount(1, $tree->c);
        $this->assertEquals(
            (object)['type' => 'competency', 'competencyid' => $competency, 'proficient' => 0, 'scope' => 'global'],
            $tree->c[0]
        );
    }

    /**
     * Creates a competency, optionally linked to courses.
     *
     * @param array $courseids IDs of the courses to link it to.
     * @param string|null $shortname Short name, generated when null.
     * @return int Competency ID.
     */
    protected function create_competency(array $courseids = [], ?string $shortname = null): int {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework();
        $record = ['competencyframeworkid' => $framework->get('id')];
        if ($shortname !== null) {
            $record['shortname'] = $shortname;
        }
        $competency = $generator->create_competency($record);
        foreach ($courseids as $courseid) {
            $generator->create_course_competency(['courseid' => $courseid, 'competencyid' => $competency->get('id')]);
        }
        return (int)$competency->get('id');
    }

    /**
     * Stores a course rating.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $competencyid Competency ID.
     * @param bool $proficient Whether the rating is proficient.
     */
    protected function rate_in_course(int $userid, int $courseid, int $competencyid, bool $proficient): void {
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_user_competency_course([
            'userid' => $userid,
            'courseid' => $courseid,
            'competencyid' => $competencyid,
            'proficiency' => $proficient ? 1 : 0,
            'grade' => $proficient ? 4 : 1,
        ]);
    }

    /**
     * Stores a global proficiency.
     *
     * @param int $userid User ID.
     * @param int $competencyid Competency ID.
     * @param bool $proficient Whether the rating is proficient.
     */
    protected function rate_globally(int $userid, int $competencyid, bool $proficient): void {
        $this->getDataGenerator()->get_plugin_generator('core_competency')->create_user_competency([
            'userid' => $userid,
            'competencyid' => $competencyid,
            'proficiency' => $proficient ? 1 : 0,
            'grade' => $proficient ? 4 : 1,
        ]);
    }

    /**
     * Creates a condition.
     *
     * @param int $competencyid Competency ID.
     * @param int $proficient 1 when proficiency is required, 0 when its absence is.
     * @param string $scope Scope of the rating.
     * @return condition
     */
    protected function make_condition(int $competencyid, int $proficient, string $scope): condition {
        return new condition((object)[
            'type' => 'competency',
            'competencyid' => $competencyid,
            'proficient' => $proficient,
            'scope' => $scope,
        ]);
    }

    /**
     * Evaluates a condition for a user in a course.
     *
     * @param condition $condition The condition.
     * @param \stdClass $course Course of the restricted item.
     * @param int $userid User ID.
     * @param bool $not Whether the condition is inverted.
     * @return bool
     */
    protected function available(condition $condition, \stdClass $course, int $userid, bool $not = false): bool {
        return $condition->is_available($not, new \core_availability\mock_info($course, $userid), true, $userid);
    }

    /**
     * Creates a page restricted by one competency condition.
     *
     * @param \stdClass $course Course.
     * @param int $competencyid Competency ID.
     * @param int $proficient 1 when proficiency is required, 0 when its absence is.
     * @param string $scope Scope of the rating.
     * @return int Course module ID.
     */
    protected function create_restricted_page(\stdClass $course, int $competencyid, int $proficient, string $scope): int {
        global $DB;
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tree = \core_availability\tree::get_root_json([
            (object)['type' => 'competency', 'competencyid' => $competencyid, 'proficient' => $proficient, 'scope' => $scope],
        ]);
        $DB->set_field('course_modules', 'availability', json_encode($tree), ['id' => $page->cmid]);
        rebuild_course_cache($course->id, true);
        return (int)$page->cmid;
    }

    /**
     * Creates the temporary table restore mappings live in.
     *
     * @return string Restore ID.
     */
    protected function create_restore_id(): string {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $restoreid = 'restore' . random_string(10);
        \backup_controller_dbops::create_backup_ids_temp_table($restoreid);
        $this->backupidstemp = true;
        return $restoreid;
    }

    /**
     * Creates a restore task stub which only answers whether the restore comes from this site.
     *
     * @param bool $samesite Whether the restore comes from this site.
     * @return \restore_task
     */
    protected function make_restore_task(bool $samesite): \restore_task {
        $task = $this->createStub(\restore_task::class);
        $task->method('is_samesite')->willReturn($samesite);
        return $task;
    }
}
