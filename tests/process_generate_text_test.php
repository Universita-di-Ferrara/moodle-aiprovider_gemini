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

use core_ai\aiactions\generate_text;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/testcase_helper_trait.php');

/**
 * Test Gemini text request and response handling.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class process_generate_text_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Test an Interactions API request.
     */
    public function test_interactions_request_removes_sampling_parameters(): void {
        $provider = $this->create_provider(generate_text::class, [
            'systeminstruction' => 'Answer briefly.',
            'modelextraparams' => json_encode([
                'temperature' => 0.5,
                'top_p' => 0.9,
                'top_k' => 40,
                'maxOutputTokens' => 100,
            ]),
        ]);
        $action = new generate_text(contextid: 1, userid: 1, prompttext: 'Hello');
        $processor = new process_generate_text($provider, $action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, '1')->getBody()->getContents());

        $this->assertSame('gemini-3.6-flash', $body->model);
        $this->assertSame('Hello', $body->input);
        $this->assertSame('Answer briefly.', $body->system_instruction);
        $this->assertSame(100, $body->generation_config->max_output_tokens);
        $this->assertObjectNotHasProperty('temperature', $body);
        $this->assertObjectNotHasProperty('top_p', $body);
        $this->assertObjectNotHasProperty('top_k', $body);
    }

    /**
     * Test a legacy generateContent request has a valid final user turn.
     */
    public function test_generate_content_request_shape(): void {
        $provider = $this->create_provider(generate_text::class, [
            'model' => 'gemini-3-flash-preview',
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/' .
                'gemini-3-flash-preview:generateContent',
            'systeminstruction' => 'Answer briefly.',
        ]);
        $action = new generate_text(contextid: 1, userid: 1, prompttext: 'Hello');
        $processor = new process_generate_text($provider, $action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, '1')->getBody()->getContents());

        $this->assertCount(1, $body->contents);
        $this->assertSame('user', $body->contents[0]->role);
        $this->assertSame('Hello', $body->contents[0]->parts[0]->text);
        $this->assertObjectNotHasProperty('role', $body->system_instruction);
    }

    /**
     * Test an Interactions API response.
     */
    public function test_interactions_response(): void {
        $provider = $this->create_provider(generate_text::class);
        $action = new generate_text(contextid: 1, userid: 1, prompttext: 'Hello');
        $processor = new process_generate_text($provider, $action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $response = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
            'id' => 'interaction-1',
            'steps' => [
                ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Hi there']]],
            ],
        ]));
        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertSame('interaction-1', $result['id']);
        $this->assertSame('Hi there', $result['generatedcontent']);
    }
}
