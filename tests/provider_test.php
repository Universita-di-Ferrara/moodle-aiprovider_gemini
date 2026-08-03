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
 * Tests for the Gemini provider.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_gemini\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Set up provider configuration used by the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('apikey', 'test-key', 'aiprovider_gemini');
        set_config('enableglobalratelimit', 0, 'aiprovider_gemini');
        set_config('globalratelimit', 100, 'aiprovider_gemini');
        set_config('enableuserratelimit', 0, 'aiprovider_gemini');
        set_config('userratelimit', 10, 'aiprovider_gemini');
    }

    /**
     * Test the supported Moodle actions.
     */
    public function test_get_action_list(): void {
        $provider = new provider();

        $this->assertContains(\core_ai\aiactions\generate_text::class, $provider->get_action_list());
        $this->assertContains(\core_ai\aiactions\generate_image::class, $provider->get_action_list());
        $this->assertContains(\core_ai\aiactions\summarise_text::class, $provider->get_action_list());
    }

    /**
     * Test stable model filtering and action capabilities.
     */
    public function test_model_filtering(): void {
        $provider = new provider();
        $stablemethod = new \ReflectionMethod($provider, 'is_stable_model');
        $supportmethod = new \ReflectionMethod($provider, 'model_supports_action');

        $this->assertTrue($stablemethod->invoke($provider, 'gemini-3.5-flash-lite'));
        $this->assertFalse($stablemethod->invoke($provider, 'gemini-3-flash-preview'));
        $this->assertTrue($stablemethod->invoke($provider, 'gemini-3.1-flash-image'));

        $this->assertTrue($supportmethod->invoke(
            $provider,
            'gemini-3.5-flash-lite',
            ['generateContent'],
            'generate_text'
        ));
        $this->assertTrue($supportmethod->invoke(
            $provider,
            'gemini-3.1-flash-image',
            ['generateContent'],
            'generate_image'
        ));
        $this->assertFalse($supportmethod->invoke(
            $provider,
            'imagen-4.0-generate-001',
            ['predict'],
            'generate_image'
        ));
    }

    /**
     * Test the requested defaults.
     */
    public function test_default_models(): void {
        $provider = new provider();
        $method = new \ReflectionMethod($provider, 'get_default_model');

        $this->assertSame('gemini-3.5-flash-lite', $method->invoke($provider, 'generate_text'));
        $this->assertSame('gemini-3.5-flash-lite', $method->invoke($provider, 'summarise_text'));
        $this->assertSame('gemini-3.1-flash-image', $method->invoke($provider, 'generate_image'));
    }
}
