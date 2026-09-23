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
 * Condition on whether the user is, or is not, proficient in one competency.
 *
 * The rating read is the one given in the restricted item's course or the site-wide one, depending on the scope.
 *
 * @package    availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var string Scope reading the rating given in the course the restricted item belongs to. */
    const SCOPE_COURSE = 'course';

    /** @var string Scope reading the learner's site-wide proficiency, whichever course or plan it came from. */
    const SCOPE_GLOBAL = 'global';

    /** @var array Competency short names as competencyid => shortname, or false when there is no such competency. */
    protected static $competencynames = [];

    /** @var array Course ratings as "userid-courseid" => [competencyid => proficient]. */
    protected static $courseproficiencies = [];

    /** @var array Site-wide proficiency as userid => [competencyid => proficient]. */
    protected static $globalproficiencies = [];

    /** @var int ID of the competency that this condition requires, 0 when it could not be restored */
    protected $competencyid;

    /** @var int 1 if proficient is required, 0 if not proficient is required */
    protected $proficient;

    /** @var string Which rating is read, self::SCOPE_COURSE or self::SCOPE_GLOBAL */
    protected $scope;

    /** @var bool|null Whether the running restore comes from this site, null outside a restore */
    protected $restoresamesite = null;

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        if (!property_exists($structure, 'competencyid')) {
            throw new \coding_exception('Invalid ->competencyid for competency condition');
        }
        if (!property_exists($structure, 'proficient')) {
            throw new \coding_exception('Invalid ->proficient for competency condition');
        }
        $this->competencyid = (int)$structure->competencyid;
        // 0 is what a restore stores when the competency could not be mapped; anything below is corrupt.
        if ($this->competencyid < 0) {
            throw new \coding_exception('Invalid ->competencyid for competency condition');
        }
        $this->proficient = (int)$structure->proficient ? 1 : 0;

        // Conditions saved before the global scope existed carry no scope and read the course rating.
        $scope = $structure->scope ?? self::SCOPE_COURSE;
        if ($scope !== self::SCOPE_COURSE && $scope !== self::SCOPE_GLOBAL) {
            throw new \coding_exception('Invalid ->scope for competency condition');
        }
        $this->scope = $scope;
    }

    /**
     * Saving function.
     *
     * @return \stdClass
     */
    public function save() {
        return (object)[
            'type' => 'competency',
            'competencyid' => $this->competencyid,
            'proficient' => $this->proficient,
            'scope' => $this->scope,
        ];
    }

    /**
     * Determines whether a particular item is currently available.
     *
     * @param bool $not Set true if we are inverting the condition
     * @param \core_availability\info $info Item we're checking
     * @param bool $grabthelot Performance hint, not read because every rating of the user is read at once anyway
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $allow = $this->is_proficient((int)$userid, (int)$info->get_course()->id) === (bool)$this->proficient;
        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    /**
     * Whether the user holds a proficient rating in this condition's competency and scope.
     *
     * The stored ratings are read directly rather than through \core_competency\api, whose getters
     * check the capabilities of the current user, throw when the competency is not linked to the
     * course and create a missing user_competency_course record on read. None of that suits an
     * availability check, which also runs for guests and on behalf of other users, and core's
     * {@see \core_availability\info::is_available()} only catches coding_exception, so any other
     * exception would break the whole course page. Reading the tables directly follows the
     * approach of availability_competencies by ssystems GmbH.
     *
     * A rating is honoured whether or not the competency is still linked to the course and whether
     * or not competencies are currently enabled: both only stop new ratings, so a learner who was
     * rated proficient keeps access. A competency that does not exist counts as not proficient,
     * which makes a "not proficient" condition on it available.
     *
     * @param int $userid User ID.
     * @param int $courseid Course the restricted item belongs to.
     * @return bool
     */
    protected function is_proficient(int $userid, int $courseid): bool {
        if ($userid <= 0 || $this->get_competency_name() === false) {
            return false;
        }
        if ($this->scope === self::SCOPE_GLOBAL) {
            $proficiencies = self::get_global_proficiencies($userid);
        } else {
            $proficiencies = self::get_course_proficiencies($userid, $courseid);
        }
        return $proficiencies[$this->competencyid] ?? false;
    }

    /**
     * Every course rating of a user in a course, read at once because a course page asks for one
     * restricted item after another.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return array Proficiency as competencyid => bool, missing when the user was never rated.
     */
    protected static function get_course_proficiencies(int $userid, int $courseid): array {
        global $DB;
        $key = $userid . '-' . $courseid;
        if (!array_key_exists($key, self::$courseproficiencies)) {
            $proficiencies = $DB->get_records_menu(
                \core_competency\user_competency_course::TABLE,
                ['userid' => $userid, 'courseid' => $courseid],
                '',
                'competencyid, proficiency'
            );
            self::$courseproficiencies[$key] = array_map('boolval', $proficiencies);
        }
        return self::$courseproficiencies[$key];
    }

    /**
     * Every site-wide proficiency of a user, read at once for the same reason as the course ratings.
     *
     * @param int $userid User ID.
     * @return array Proficiency as competencyid => bool, missing when the user holds no record.
     */
    protected static function get_global_proficiencies(int $userid): array {
        global $DB;
        if (!array_key_exists($userid, self::$globalproficiencies)) {
            $proficiencies = $DB->get_records_menu(
                \core_competency\user_competency::TABLE,
                ['userid' => $userid],
                '',
                'competencyid, proficiency'
            );
            self::$globalproficiencies[$userid] = array_map('boolval', $proficiencies);
        }
        return self::$globalproficiencies[$userid];
    }

    /**
     * Short name of the competency, looked up once per request.
     *
     * Deliberately not tied to the course link: a condition keeps naming its competency after the
     * competency is unlinked from the course, as its evaluation keeps honouring the ratings.
     *
     * @return string|false False when there is no such competency.
     */
    protected function get_competency_name() {
        global $DB;
        if ($this->competencyid <= 0) {
            return false;
        }
        if (!array_key_exists($this->competencyid, self::$competencynames)) {
            self::$competencynames[$this->competencyid] = $DB->get_field(
                \core_competency\competency::TABLE,
                'shortname',
                ['id' => $this->competencyid]
            );
        }
        return self::$competencynames[$this->competencyid];
    }

    /**
     * Forgets everything read during this request.
     *
     * Called by {@see observer::competency_evidence_created()} so that a rating given earlier in the
     * same request is seen, and by tests, which roll back the database but not static state.
     */
    public static function wipe_static_cache(): void {
        self::$competencynames = [];
        self::$courseproficiencies = [];
        self::$globalproficiencies = [];
    }

    /**
     * Obtains a string describing this restriction.
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param \core_availability\info $info Item we're checking
     * @return string Description of restriction
     */
    public function get_description($full, $not, \core_availability\info $info) {
        $name = $this->get_competency_name();
        if ($name === false) {
            $name = get_string('missing', 'availability_competency');
        } else {
            // Descriptions are built while modinfo is being populated, where format_string() must not run;
            // core formats the placeholder later, in the course context.
            $name = self::description_format_string($name);
        }

        $requireproficient = $this->proficient ? !$not : $not;
        if ($this->scope === self::SCOPE_GLOBAL) {
            $identifier = $requireproficient ? 'requires_competency_global' : 'requires_not_competency_global';
        } else {
            $identifier = $requireproficient ? 'requires_competency' : 'requires_not_competency';
        }
        return get_string($identifier, 'availability_competency', $name);
    }

    /**
     * Debug string.
     *
     * @return string
     */
    protected function get_debug_string() {
        return '#' . $this->competencyid . '-p' . $this->proficient . '-' . $this->scope;
    }

    /**
     * Notes whether the restore comes from this site, for update_after_restore().
     *
     * Core calls this for every condition right before update_after_restore(), which is not given
     * the restore task. The condition is always kept: dropping it would open the item to everyone.
     *
     * @param string $restoreid Restore ID
     * @param int $courseid ID of target course
     * @param \base_logger $logger Logger for any warnings
     * @param string $name Name of this item
     * @param \base_task $task Current restore task
     * @return bool Always true
     */
    public function include_after_restore($restoreid, $courseid, \base_logger $logger, $name, \base_task $task) {
        $this->restoresamesite = $task instanceof \restore_task ? $task->is_samesite() : null;
        return true;
    }

    /**
     * Remaps the competency ID after a restore.
     *
     * Core maps competencies by framework and competency idnumber when the backup included the
     * course competencies ({@see \restore_course_competencies_structure_step}). Without a mapping,
     * the ID still names the same competency on the same site (duplicating an activity, importing,
     * or a backup without competencies), so it is kept. On another site the same number names an
     * unrelated competency, if any, so the condition is pointed at no competency and a warning is
     * logged. Remapping through the restore mapping and logging what could not be restored follows
     * availability_competencies by ssystems GmbH.
     *
     * @param string $restoreid Restore ID
     * @param int $courseid ID of target course
     * @param \base_logger $logger Logger for any warnings
     * @param string $name Name of this item (for use in warning messages)
     * @return bool True if there was any change
     */
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        if ($this->competencyid <= 0) {
            return false;
        }

        $rec = \restore_dbops::get_backup_ids_record($restoreid, \core_competency\competency::TABLE, $this->competencyid);
        if ($rec && $rec->newitemid) {
            if ((int)$rec->newitemid === $this->competencyid) {
                return false;
            }
            $this->competencyid = (int)$rec->newitemid;
            return true;
        }

        if ($this->restoresamesite !== false) {
            return false;
        }

        $logger->process(
            'Restored item (' . $name . ') has availability condition on a competency that was not restored',
            \backup::LOG_WARNING
        );
        $this->competencyid = 0;
        return true;
    }
}
