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
 * Availability competency - Condition class
 *
 * @package    availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var array Cache for competency shortnames */
    protected static $competencydesc = [];

    /** @var int ID of the competency that this condition requires */
    protected $competencyid;

    /** @var int 1 if proficient is required, 0 if not proficient is required */
    protected $proficient;

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
        $this->proficient = (int)$structure->proficient ? 1 : 0;
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
            'proficient' => $this->proficient
        ];
    }

    /**
     * Determines whether a particular item is currently available.
     *
     * @param bool $not Set true if we are inverting the condition
     * @param \core_availability\info $info Item we're checking
     * @param bool $grabthelot Performance hint
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $course = $info->get_course();
        
        $ucc = \core_competency\api::get_user_competency_in_course($course->id, $userid, $this->competencyid);
        
        $isproficient = $ucc->get('proficiency') ? 1 : 0;
        
        $allow = ($isproficient === $this->proficient);

        if ($not) {
            $allow = !$allow;
        }

        return $allow;
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
        global $DB;
        $course = $info->get_course();

        if (!array_key_exists($this->competencyid, self::$competencydesc)) {
            $comp = $DB->get_record('competency', ['id' => $this->competencyid], 'id, shortname');
            if ($comp) {
                self::$competencydesc[$this->competencyid] = $comp->shortname;
            } else {
                self::$competencydesc[$this->competencyid] = get_string('missing', 'availability_competency');
            }
        }
        
        $name = format_string(self::$competencydesc[$this->competencyid], true, ['context' => \context_course::instance($course->id)]);
        
        $requireproficient = $this->proficient ? !$not : $not;

        if ($requireproficient) {
            return get_string('requires_competency', 'availability_competency', $name);
        } else {
            return get_string('requires_not_competency', 'availability_competency', $name);
        }
    }

    /**
     * Debug string.
     *
     * @return string
     */
    protected function get_debug_string() {
        return '#' . $this->competencyid . '-p' . $this->proficient;
    }
    
    /**
     * Checks whether this availability condition should be included after restore.
     *
     * @param string $restoreid Restore ID
     * @param int $courseid ID of target course
     * @param \base_logger $logger Logger for any warnings
     * @param string $name Name of this item
     * @param \base_task $task Current restore task
     * @return bool
     */
    public function include_after_restore($restoreid, $courseid, \base_logger $logger, $name, \base_task $task) {
        global $DB;
        
        $restorecontroller = \restore_controller::load_controller($restoreid);
        
        if ($restorecontroller->is_samesite()) {
            if ($DB->record_exists('competency_coursecomp', ['courseid' => $courseid, 'competencyid' => $this->competencyid])) {
                return true;
            } else {
                return false;
            }
        } else {
             return false;
        }
    }
}
