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

/**
 * Unit tests for availability_competency.
 */
class availability_competency_test extends \basic_testcase {
    /**
     * Test that condition class can be instantiated.
     */
    public function test_condition_instantiation(): void {
        $structure = (object)[
            'type' => 'competency',
            'competencyid' => 1,
            'proficient' => 1,
        ];
        $condition = new condition($structure);
        $this->assertInstanceOf(condition::class, $condition);
    }

    /**
     * Test save returns expected condition payload.
     */
    public function test_condition_save_payload(): void {
        $structure = (object)[
            'type' => 'competency',
            'competencyid' => 42,
            'proficient' => 0,
        ];
        $condition = new condition($structure);
        $saved = $condition->save();

        $this->assertSame('competency', $saved->type);
        $this->assertSame(42, $saved->competencyid);
        $this->assertSame(0, $saved->proficient);
    }
}
