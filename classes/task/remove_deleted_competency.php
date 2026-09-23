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

namespace availability_competency\task;

/**
 * Removes the restrictions on a deleted competency, for the optional cleanup setting.
 *
 * Queued by {@see \availability_competency\observer::competency_deleted()}, one task per competency.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_deleted_competency extends \core\task\adhoc_task {
    /**
     * Name shown in the list of ad hoc tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_remove_deleted_competency', 'availability_competency');
    }

    /**
     * Removes the restrictions on the competency named in the custom data.
     *
     * A task without a competency ID can never succeed, so it logs and returns instead of throwing,
     * which would retry it forever.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();
        $competencyid = (int)($data->competencyid ?? 0);
        if ($competencyid <= 0) {
            mtrace('No competency ID given, nothing to remove.');
            return;
        }
        \availability_competency\observer::remove_competency_from_availability($competencyid);
    }
}
