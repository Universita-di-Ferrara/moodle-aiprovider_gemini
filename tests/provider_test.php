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

namespace aiprovider_gemini;

/**
 * Test Gemini provider model registration.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class provider_test extends \advanced_testcase {
    /**
     * Test the supported model list.
     */
    public function test_model_classes(): void {
        $names = [];
        foreach (helper::get_model_classes() as $class) {
            $names[] = (new $class())->get_model_name();
        }
        sort($names);

        $this->assertSame([
            'gemini-3-flash-preview',
            'gemini-3-pro-image',
            'gemini-3.1-flash-image',
            'gemini-3.1-flash-lite',
            'gemini-3.1-flash-lite-image',
            'gemini-3.1-pro-preview',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.6-flash',
        ], $names);
        $this->assertNotContains('gemini-2.5-flash', $names);
        $this->assertNotContains('imagen-4.0-generate-001', $names);
        $this->assertNotContains('gemini-3.1-flash-lite-preview', $names);
    }
}
