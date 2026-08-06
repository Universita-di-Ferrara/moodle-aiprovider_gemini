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
 * Test helper for the Gemini provider.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait testcase_helper_trait {
    /**
     * Create a Gemini provider instance.
     *
     * @param string $actionclass Action class.
     * @param array $actionconfig Action settings override.
     * @return \core_ai\provider Provider instance.
     */
    protected function create_provider(string $actionclass, array $actionconfig = []): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        $settings = [
            'model' => 'gemini-3.6-flash',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/interactions',
        ];
        foreach ($actionconfig as $key => $value) {
            $settings[$key] = $value;
        }

        return $manager->create_provider_instance(
            classname: '\\aiprovider_gemini\\provider',
            name: 'dummy',
            config: ['apikey' => 'test-key'],
            actionconfig: [
                $actionclass => ['settings' => $settings],
            ],
        );
    }
}
