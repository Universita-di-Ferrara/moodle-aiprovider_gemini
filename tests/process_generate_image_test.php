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

use core_ai\aiactions\generate_image;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for Gemini image processing.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_gemini\process_generate_image
 */
final class process_generate_image_test extends \advanced_testcase {
    /** @var provider */
    private provider $provider;

    /** @var generate_image */
    private generate_image $action;

    /**
     * Set up an image action and provider.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('apikey', 'test-key', 'aiprovider_gemini');
        set_config('action_generate_image_model', 'gemini-3.1-flash-image', 'aiprovider_gemini');
        set_config(
            'action_generate_image_endpoint',
            provider::INTERACTIONS_ENDPOINT,
            'aiprovider_gemini'
        );
        $this->provider = new provider();
        $this->action = new generate_image(
            contextid: 1,
            userid: 2,
            prompttext: 'A classroom in a futuristic university.',
            quality: 'hd',
            aspectratio: 'landscape',
            numimages: 1,
            style: 'vivid',
        );
    }

    /**
     * Test the Interactions image request shape and deprecated parameter audit.
     */
    public function test_create_request_object(): void {
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 'anonymous-user');
        $body = (string) $request->getBody();
        $data = json_decode($body);

        $this->assertSame('gemini-3.1-flash-image', $data->model);
        $this->assertSame('text', $data->input[0]->type);
        $this->assertSame('A classroom in a futuristic university.', $data->input[0]->text);
        $this->assertSame('image', $data->response_format->type);
        $this->assertSame('16:9', $data->response_format->aspect_ratio);
        $this->assertSame('2K', $data->response_format->image_size);
        foreach (['temperature', 'top_p', 'top_k', 'candidate_count', 'generation_config'] as $deprecated) {
            $this->assertStringNotContainsString('"' . $deprecated . '"', $body);
        }
    }

    /**
     * Test Lite model quality validation.
     */
    public function test_lite_model_rejects_hd(): void {
        set_config(
            'action_generate_image_model',
            'gemini-3.1-flash-lite-image',
            'aiprovider_gemini'
        );
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'validate_image_configuration');
        $result = $method->invoke($processor);

        $this->assertSame(400, $result['errorcode']);
        $this->assertFalse($result['success']);
    }

    /**
     * Test parsing a native Gemini image response.
     */
    public function test_handle_api_success(): void {
        $body = file_get_contents(self::get_fixture_path(
            'aiprovider_gemini',
            'interactions_image_success.json'
        ));
        $processor = new process_generate_image($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $result = $method->invoke($processor, new Response(200, [], $body));

        $this->assertTrue($result['success']);
        $this->assertSame('aW1hZ2UtZGF0YQ==', $result['imagebase64']);
        $this->assertSame('image/jpeg', $result['mimetype']);
        $this->assertNull($result['sourceurl']);
        $this->assertNull($result['revisedprompt']);
    }
}
