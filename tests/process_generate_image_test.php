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

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/testcase_helper_trait.php');

/**
 * Test Gemini image request and response handling.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class process_generate_image_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Test the Interactions image request.
     */
    public function test_interactions_image_request(): void {
        $provider = $this->create_provider(generate_image::class, [
            'model' => 'gemini-3.1-flash-image',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/interactions',
        ]);
        $action = new generate_image(
            contextid: 1,
            userid: 1,
            prompttext: 'A red apple',
            quality: 'hd',
            aspectratio: 'landscape',
            numimages: 1,
            style: 'vivid',
        );
        $processor = new process_generate_image($provider, $action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, '1')->getBody()->getContents());

        $this->assertSame('gemini-3.1-flash-image', $body->model);
        $this->assertSame('A red apple', $body->input);
        $this->assertSame('image', $body->response_format->type);
        $this->assertSame('16:9', $body->response_format->aspect_ratio);
        $this->assertSame('2K', $body->response_format->image_size);
    }

    /**
     * Test parsing the Interactions image response.
     */
    public function test_interactions_image_response(): void {
        $provider = $this->create_provider(generate_image::class, [
            'model' => 'gemini-3.1-flash-image',
        ]);
        $action = new generate_image(
            contextid: 1,
            userid: 1,
            prompttext: 'A red apple',
            quality: 'standard',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
        $processor = new process_generate_image($provider, $action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $response = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'id' => 'interaction-image-1',
            'output_image' => ['data' => 'aW1hZ2U='],
        ]));
        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertSame('aW1hZ2U=', $result['imagebase64']);
    }

    /**
     * Test that an image response without image data returns a core error.
     */
    public function test_missing_image_response_contains_error_details(): void {
        $provider = $this->create_provider(generate_image::class, [
            'model' => 'gemini-3.1-flash-image',
        ]);
        $action = new generate_image(
            contextid: 1,
            userid: 1,
            prompttext: 'A red apple',
            quality: 'standard',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
        $processor = new process_generate_image($provider, $action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $response = new \GuzzleHttp\Psr7\Response(200, [], '{}');
        $result = $method->invoke($processor, $response);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['errorcode']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
        $this->assertNotEmpty($result['errormessage']);
    }

    /**
     * Test that an upstream HTTP error returns the fields required by core AI.
     */
    public function test_http_error_contains_error_details(): void {
        $provider = $this->create_provider(generate_image::class, [
            'model' => 'gemini-3.1-flash-image',
        ]);
        $action = new generate_image(
            contextid: 1,
            userid: 1,
            prompttext: 'A red apple',
            quality: 'standard',
            aspectratio: 'square',
            numimages: 1,
            style: 'vivid',
        );
        $processor = new process_generate_image($provider, $action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');
        $response = new \GuzzleHttp\Psr7\Response(502, [], '');
        $result = $method->invoke($processor, $response);

        $this->assertFalse($result['success']);
        $this->assertSame(502, $result['errorcode']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
        $this->assertNotEmpty($result['errormessage']);
    }
}
