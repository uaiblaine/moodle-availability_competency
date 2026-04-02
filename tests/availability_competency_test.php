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
 * Unit tests for availability_competency plugin.
 *
 * @package    availability_competency
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for availability_competency.
 */
class availability_competency_test extends \basic_testcase {

    /**
     * Test that condition class can be instantiated.
     */
    public function test_condition_instantiation(): void {
        $structure = (object)['type' => 'competency'];
        $condition = new condition($structure);
        $this->assertIsObject($condition);
    }

    /**
     * Test get_description method exists.
     */
    public function test_condition_get_description(): void {
        $structure = (object)['type' => 'competency'];
        $condition = new condition($structure);
        $description = $condition->get_description(false, false, null);
        $this->assertIsString($description);
    }
}
