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
 * Behat data generator for availability_competency.
 *
 * Restricts an activity by a competency without going through the form, which needs a JavaScript
 * scenario and several steps:
 *
 *     Given the following "availability_competency > activity restrictions" exist:
 *       | activity | competency | proficient | scope  |
 *       | page1    | COMP1      | 1          | global |
 *
 * The activity is named by its idnumber and the competency by its idnumber. "proficient" defaults
 * to 1. Without "scope" the condition has the shape saved before 1.2.0, which reads the course rating.
 *
 * Adapted from the Behat generator of availability_competencies by ssystems GmbH.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_availability_competency_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'activity restrictions' => [
                'singular' => 'activity restriction',
                'datagenerator' => 'activity_restriction',
                'required' => ['activity', 'competency'],
                'switchids' => ['activity' => 'cmid', 'competency' => 'competencyid'],
            ],
        ];
    }

    /**
     * Get the competency id using an idnumber.
     *
     * @param string $idnumber Idnumber of the competency.
     * @return int The competency id.
     * @throws Exception When no competency has this idnumber.
     */
    protected function get_competency_id(string $idnumber): int {
        global $DB;

        if (!$id = $DB->get_field('competency', 'id', ['idnumber' => $idnumber])) {
            throw new Exception('The specified competency with idnumber "' . $idnumber . '" could not be found.');
        }

        return (int)$id;
    }
}
