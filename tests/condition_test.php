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
 * Unit tests for condition class behaviour.
 *
 * @package    availability_competency
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for condition class.
 */
class condition_test extends \basic_testcase {

    /**
     * Test that condition save method returns valid structure.
     */
    public function test_condition_save(): void {
        $structure = (object)['type' => 'competency', 'id' => 1];
        $condition = new condition($structure);
        $result = $condition->save();
        $this->assertIsObject($result);
        $this->assertObjectHasProperty('type', $result);
    }

    /**
     * Test that get_debug_string method exists and returns string.
     */
    public function test_condition_debug_string(): void {
        $structure = (object)['type' => 'competency', 'id' => 1];
        $condition = new condition($structure);
        // Protected method, test via reflection.
        $reflection = new \ReflectionMethod($condition, 'get_debug_string');
        $reflection->setAccessible(true);
        $debug = $reflection->invoke($condition);
        $this->assertIsString($debug);
    }
}
