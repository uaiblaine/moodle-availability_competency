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
 * Keeps the copyright of the code adapted from other plugins (Copyrights guideline).
 *
 * The plugins directory asks that adapted code keep its original holder. The readiness test
 * accepts any copyright line, so it cannot tell when one of these is dropped.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class upstream_credits_test extends \basic_testcase {
    /**
     * Each adapted file, with the copyright holder of the code it was adapted from.
     *
     * @return array Path relative to the plugin root => holder as written in its copyright line.
     */
    private static function adapted_files(): array {
        return [
            'classes/observer.php' => 'Alexander Bias <bias@alexanderbias.de>',
            'tests/observer_test.php' => 'Moodle an Hochschulen e.V. <kontakt@moodle-an-hochschulen.de>',
            'tests/generator/lib.php' => 'Alexander Bias, ssystems GmbH <abias@ssystems.de>',
            'tests/generator/behat_availability_competency_generator.php' => 'Alexander Bias, ssystems GmbH <abias@ssystems.de>',
        ];
    }

    /**
     * Every adapted file keeps a copyright line naming the original holder.
     *
     * @return void
     */
    public function test_adapted_files_keep_the_original_copyright(): void {
        foreach (self::adapted_files() as $path => $holder) {
            $source = file_get_contents(dirname(__DIR__) . '/' . $path);
            $this->assertMatchesRegularExpression(
                '/@copyright\s+\d{4} ' . preg_quote($holder, '/') . '$/m',
                $source,
                "$path no longer credits $holder."
            );
        }
    }

    /**
     * The README credits both plugins the adapted code comes from.
     *
     * @return void
     */
    public function test_the_readme_credits_the_upstream_plugins(): void {
        $readme = file_get_contents(dirname(__DIR__) . '/README.md');
        $this->assertStringContainsString('ssystems-de/moodle-availability_competencies', $readme);
        $this->assertStringContainsString('moodle-an-hochschulen/moodle-availability_cohort', $readme);
    }
}
