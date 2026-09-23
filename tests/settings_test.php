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
 * Unit tests for the plugin's admin settings.
 *
 * @package    availability_competency
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_competency;

/**
 * Unit tests for settings.php, which declares no class to cover.
 *
 * @coversNothing
 */
final class settings_test extends \advanced_testcase {
    /**
     * The cleanup on competency deletion is a checkbox on the plugin's settings page, disabled by default.
     */
    public function test_cleanup_setting_is_registered_disabled(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = admin_get_root(true, true)->locate('availabilitysettingcompetency');

        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $setting = $page->settings->availability_competencycleanuponcompetencydeletion ?? null;
        $this->assertInstanceOf(\admin_setting_configcheckbox::class, $setting);
        $this->assertSame('0', (string)$setting->get_defaultsetting());
    }
}
