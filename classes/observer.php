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
 * Observers of core competency events, registered in db/events.php.
 *
 * @package    availability_competency
 * @copyright 2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Queues the removal of the restrictions on a deleted competency, if the admin enabled it.
     *
     * Nobody counts as proficient in a deleted competency, so a restriction requiring proficiency in it could
     * never be met again. Core fires this event once per removed competency, after the deletion is committed,
     * including the competency's descendants and every competency of a deleted framework. Each removal scans
     * every activity and section of the site, so it runs in an ad hoc task rather than in the request that
     * deleted the competency.
     *
     * @param \core\event\competency_deleted $event The competency_deleted event.
     * @return void
     */
    public static function competency_deleted(\core\event\competency_deleted $event): void {
        // The cleanup is opt-in; the setting defaults to off.
        if (!get_config('availability_competency', 'cleanuponcompetencydeletion')) {
            return;
        }

        $task = new task\remove_deleted_competency();
        $task->set_custom_data(['competencyid' => (int)$event->objectid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Observer for the competency_evidence_created event.
     *
     * Adding evidence is how core writes every rating, in a course and site-wide alike, so the
     * ratings the condition has read during this request are dropped to let a rating given earlier
     * in the same request unlock the item straight away. Refreshing on this event follows
     * availability_competencies by ssystems GmbH.
     *
     * @param \core\event\competency_evidence_created $event The competency_evidence_created event.
     * @return void
     */
    public static function competency_evidence_created(\core\event\competency_evidence_created $event): void {
        condition::wipe_static_cache();
    }

    /**
     * Removes every reference to the given competency from the availability restrictions of all course modules and
     * course sections on the site.
     *
     * Run by {@see task\remove_deleted_competency}.
     *
     * @param int $competencyid The ID of the deleted competency.
     * @return void
     */
    public static function remove_competency_from_availability(int $competencyid): void {
        global $DB;

        $affectedcourses = [];

        $transaction = $DB->start_delegated_transaction();

        // The LIKE only narrows the candidates; the tree walk decides by condition type and competency ID.
        foreach (['course_modules', 'course_sections'] as $table) {
            $select = $DB->sql_like('availability', '?');
            $recordset = $DB->get_recordset_select(
                $table,
                $select,
                ['%"competency"%'],
                '',
                'id, course AS courseid, availability'
            );

            foreach ($recordset as $record) {
                $tree = json_decode($record->availability);

                if ($tree === null || !isset($tree->c) || !is_array($tree->c)) {
                    continue;
                }

                if (!self::remove_competency_from_tree($tree, $competencyid)) {
                    continue;
                }

                // An item left without restrictions stores null, as \core_availability\info::update_after_restore() does.
                if (empty($tree->c)) {
                    $newvalue = null;
                } else {
                    $newvalue = json_encode($tree);
                }

                $DB->set_field($table, 'availability', $newvalue, ['id' => $record->id]);

                $affectedcourses[$record->courseid] = true;
            }
            $recordset->close();
        }

        $transaction->allow_commit();

        // As after core's restore, clearing is enough: modinfo is rebuilt on its next read.
        foreach (array_keys($affectedcourses) as $courseid) {
            rebuild_course_cache($courseid, true);
        }
    }

    /**
     * Removes every condition on the given competency from an availability tree, in place.
     *
     * Core has no API that removes one condition from a stored tree: the availability form rebuilds the whole
     * tree in the browser, and {@see \core_availability\info::update_dependency_id_across_course()} only remaps
     * IDs. A nested subtree which becomes empty through the removal is dropped as well, because core treats an
     * empty subtree as met; a subtree that was already empty is left alone, as it does not name the competency.
     * Only a root tree with op '&' or '!|' carries showc, one flag per child of c, and it is kept parallel to c.
     *
     * The result is then checked by verify_competency_removal(). If that fails, which would be a bug, the
     * tree is reported as unchanged so that the caller does not write it back.
     *
     * @param \stdClass $tree The (sub)tree node to process, holding a list of children in its c property.
     * @param int $competencyid The ID of the deleted competency.
     * @return bool True if the tree was changed (and verified), false otherwise.
     */
    protected static function remove_competency_from_tree(\stdClass $tree, int $competencyid): bool {
        // A deep copy for the check: clone would share the child objects that the removal changes in place.
        $original = json_decode(json_encode($tree));

        $changed = self::remove_competency_from_tree_recursive($tree, $competencyid);

        if (!$changed) {
            return false;
        }

        if (!self::verify_competency_removal($original, $tree, $competencyid)) {
            debugging(
                'The availability tree manipulation after the deletion of competency ' . $competencyid
                    . ' could not be verified and was therefore skipped. This is a bug in availability_competency'
                    . ' and should be reported.',
                DEBUG_DEVELOPER
            );
            return false;
        }

        return true;
    }

    /**
     * Recursive worker of remove_competency_from_tree(), which describes the rules it applies.
     *
     * @param \stdClass $tree The (sub)tree node to process, holding a list of children in its c property.
     * @param int $competencyid The ID of the deleted competency.
     * @return bool True if the tree was changed, false otherwise.
     */
    protected static function remove_competency_from_tree_recursive(\stdClass $tree, int $competencyid): bool {
        if (!isset($tree->c) || !is_array($tree->c)) {
            return false;
        }

        $haveshowc = isset($tree->showc) && is_array($tree->showc);

        // Rebuilt by appending rather than with unset(), so that c and showc stay parallel and still encode as JSON arrays.
        $changed = false;
        $newchildren = [];
        $newshowc = [];
        foreach ($tree->c as $index => $child) {
            if (isset($child->c)) {
                if (self::remove_competency_from_tree_recursive($child, $competencyid)) {
                    $changed = true;
                    if (empty($child->c)) {
                        continue;
                    }
                }
                $newchildren[] = $child;
                if ($haveshowc) {
                    $newshowc[] = $tree->showc[$index];
                }
            } else if (
                isset($child->type) && $child->type === 'competency'
                    && isset($child->competencyid) && (int)$child->competencyid === $competencyid
            ) {
                $changed = true;
            } else {
                $newchildren[] = $child;
                if ($haveshowc) {
                    $newshowc[] = $tree->showc[$index];
                }
            }
        }

        if ($changed) {
            $tree->c = $newchildren;
            if ($haveshowc) {
                $tree->showc = $newshowc;
            }
        }

        return $changed;
    }

    /**
     * Whether the edit removed exactly the conditions on the deleted competency and nothing else.
     *
     * Checks that the result names the competency nowhere, that it holds the same other conditions as the
     * original (none lost, none added), and that every showc still has one flag per child.
     *
     * @param \stdClass $original The pristine original tree.
     * @param \stdClass $result The manipulated tree.
     * @param int $competencyid The ID of the deleted competency.
     * @return bool True if the manipulation is verified to be correct, false otherwise.
     */
    protected static function verify_competency_removal(\stdClass $original, \stdClass $result, int $competencyid): bool {
        $originalcompetencyleaves = [];
        $originalotherleaves = [];
        self::collect_leaves($original, $competencyid, $originalcompetencyleaves, $originalotherleaves);

        $resultcompetencyleaves = [];
        $resultotherleaves = [];
        self::collect_leaves($result, $competencyid, $resultcompetencyleaves, $resultotherleaves);

        if (!empty($resultcompetencyleaves)) {
            return false;
        }

        // Sorted: the check is about which conditions survive, not their order.
        sort($originalotherleaves);
        sort($resultotherleaves);
        if ($originalotherleaves !== $resultotherleaves) {
            return false;
        }

        if (!self::verify_showc_integrity($result)) {
            return false;
        }

        return true;
    }

    /**
     * Collects the leaf conditions of a tree as JSON strings, split into those on the competency and all others.
     *
     * @param \stdClass $tree The (sub)tree node to process.
     * @param int $competencyid The ID of the deleted competency.
     * @param array $competencyleaves The list collecting the conditions requiring the deleted competency (by reference).
     * @param array $otherleaves The list which collects all other conditions (by reference).
     * @return void
     */
    protected static function collect_leaves(
        \stdClass $tree,
        int $competencyid,
        array &$competencyleaves,
        array &$otherleaves
    ): void {
        if (!isset($tree->c) || !is_array($tree->c)) {
            return;
        }
        foreach ($tree->c as $child) {
            if (isset($child->c)) {
                self::collect_leaves($child, $competencyid, $competencyleaves, $otherleaves);
            } else if (
                isset($child->type) && $child->type === 'competency'
                    && isset($child->competencyid) && (int)$child->competencyid === $competencyid
            ) {
                $competencyleaves[] = json_encode($child);
            } else {
                $otherleaves[] = json_encode($child);
            }
        }
    }

    /**
     * Recursively verifies that the parallel showc array of each node matches the number of that node's children.
     *
     * @param \stdClass $tree The (sub)tree node to process.
     * @return bool True if the showc arrays are consistent, false otherwise.
     */
    protected static function verify_showc_integrity(\stdClass $tree): bool {
        if (!isset($tree->c) || !is_array($tree->c)) {
            return true;
        }
        if (isset($tree->showc) && is_array($tree->showc) && count($tree->showc) !== count($tree->c)) {
            return false;
        }
        foreach ($tree->c as $child) {
            if (isset($child->c) && !self::verify_showc_integrity($child)) {
                return false;
            }
        }
        return true;
    }
}
