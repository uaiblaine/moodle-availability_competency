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
 * Bootstrap class name checks for the YUI form.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

/**
 * Keeps the YUI form's class names valid on Moodle 4.5 (Bootstrap 4) and 5.x (Bootstrap 5).
 *
 * The form ships one source for both, and no linter reads class names out of JavaScript.
 *
 * @coversNothing
 */
final class bootstrap_compat_test extends \basic_testcase {
    /**
     * The form uses no Bootstrap 4 spacing names, which 5.x only keeps as deprecated styles.
     */
    public function test_no_bootstrap4_spacing_names(): void {
        $this->assertDoesNotMatchRegularExpression('/\b[mp][lr]-(?:[0-5]|auto)\b/', $this->form_source());
    }

    /**
     * The form takes the select class from the frontend instead of naming either branch's class.
     *
     * custom-select is Bootstrap 4 only and form-select Bootstrap 5 only, see frontend::get_javascript_init_params().
     */
    public function test_no_hardcoded_select_class(): void {
        $source = $this->form_source();
        $this->assertStringNotContainsString('custom-select', $source);
        $this->assertStringNotContainsString('form-select', $source);
        $this->assertStringContainsString('class="\' + this.selectClass + \'"', $source);
    }

    /**
     * Source of the YUI form module.
     *
     * @return string
     */
    protected function form_source(): string {
        $source = file_get_contents(__DIR__ . '/../yui/src/form/js/form.js');
        $this->assertNotEmpty($source);
        return $source;
    }
}
