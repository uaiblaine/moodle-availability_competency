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

namespace availability_competency\local;

/**
 * Finds competency restrictions whose competency cannot be used as intended.
 *
 * Backs cli/report_conditions.php. It only reads.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_report {
    /** @var string The restriction names no competency. */
    const PROBLEM_NO_COMPETENCY = 'nocompetency';

    /** @var string The competency no longer exists, so nobody counts as proficient in it. */
    const PROBLEM_MISSING = 'missing';

    /** @var string The competency exists but is no longer linked to the course. */
    const PROBLEM_NOT_LINKED = 'notlinked';

    /**
     * Every competency condition with a problem, in the restrictions of all activities and sections of the site.
     *
     * @return array List of objects with problem (one of the PROBLEM_* constants), courseid, itemtype
     *     ('module' or 'section'), itemid (course module or section ID), competencyid, proficient and
     *     scope; activities come before sections, each ordered by course and ID.
     */
    public static function find(): array {
        global $DB;

        $conditions = [];
        foreach (['course_modules' => 'module', 'course_sections' => 'section'] as $table => $itemtype) {
            $records = $DB->get_recordset_select(
                $table,
                $DB->sql_like('availability', ':pattern'),
                ['pattern' => '%"competency"%'],
                'course, id',
                'id, course, availability'
            );
            foreach ($records as $record) {
                foreach (self::collect_conditions(json_decode((string)$record->availability)) as $condition) {
                    $conditions[] = (object)[
                        'courseid' => (int)$record->course,
                        'itemtype' => $itemtype,
                        'itemid' => (int)$record->id,
                        'competencyid' => $condition->competencyid,
                        'proficient' => $condition->proficient,
                        'scope' => $condition->scope,
                    ];
                }
            }
            $records->close();
        }

        $existing = self::existing_competencies(array_column($conditions, 'competencyid'));
        $links = [];
        $problems = [];
        foreach ($conditions as $condition) {
            if ($condition->competencyid <= 0) {
                $condition->problem = self::PROBLEM_NO_COMPETENCY;
            } else if (!isset($existing[$condition->competencyid])) {
                $condition->problem = self::PROBLEM_MISSING;
            } else {
                if (!array_key_exists($condition->courseid, $links)) {
                    $linked = $DB->get_fieldset_select(
                        'competency_coursecomp',
                        'competencyid',
                        'courseid = ?',
                        [$condition->courseid]
                    );
                    $links[$condition->courseid] = array_fill_keys(array_map('intval', $linked), true);
                }
                if (isset($links[$condition->courseid][$condition->competencyid])) {
                    continue;
                }
                $condition->problem = self::PROBLEM_NOT_LINKED;
            }
            $problems[] = $condition;
        }
        return $problems;
    }

    /**
     * The competency conditions of a decoded availability tree, nested trees included.
     *
     * @param mixed $node A decoded tree or condition; anything else yields nothing.
     * @return array List of objects with competencyid, proficient and scope.
     */
    protected static function collect_conditions($node): array {
        if (!is_object($node)) {
            return [];
        }
        if (isset($node->c) && is_array($node->c)) {
            $conditions = [];
            foreach ($node->c as $child) {
                $conditions = array_merge($conditions, self::collect_conditions($child));
            }
            return $conditions;
        }
        if (!isset($node->type) || $node->type !== 'competency') {
            return [];
        }
        return [(object)[
            'competencyid' => (int)($node->competencyid ?? 0),
            'proficient' => empty($node->proficient) ? 0 : 1,
            'scope' => $node->scope ?? 'course',
        ]];
    }

    /**
     * Which of the given competency IDs exist.
     *
     * @param array $ids Competency IDs, duplicates and non-positive values allowed.
     * @return array The existing IDs as keys.
     */
    protected static function existing_competencies(array $ids): array {
        global $DB;

        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
        $existing = [];
        // Core's get_in_or_equal() does not split long lists, and some databases cap the number of parameters.
        foreach (array_chunk($ids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            foreach ($DB->get_fieldset_select('competency', 'id', "id $insql", $params) as $id) {
                $existing[(int)$id] = true;
            }
        }
        return $existing;
    }
}
