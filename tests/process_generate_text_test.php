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
use GuzzleHttp\Psr7\Response;

/**
 * Tests for Gemini text processing.
 *
 * @package    aiprovider_gemini
 * @copyright  2026 University of Ferrara, Italy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_gemini\process_generate_text
 */
final class process_generate_text_test extends \advanced_testcase {
    /** @var provider */
    private provider $provider;

    /** @var generate_text */
    private generate_text $action;

    /**
     * Set up a text action and provider.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('apikey', 'test-key', 'aiprovider_gemini');
        set_config('action_generate_text_model', 'gemini-3.5-flash-lite', 'aiprovider_gemini');
        set_config(
            'action_generate_text_endpoint',
            provider::INTERACTIONS_ENDPOINT,
            'aiprovider_gemini'
        );
        set_config(
            'action_generate_text_systeminstruction',
            'Answer only with the requested content.',
            'aiprovider_gemini'
        );
        $this->provider = new provider();
        $this->action = new generate_text(
            contextid: 1,
            userid: 2,
            prompttext: 'Explain photosynthesis.',
        );
    }

    /**
     * Test the Interactions request shape and deprecated parameter audit.
     */
    public function test_create_interactions_request_object(): void {
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 'anonymous-user');
        $body = (string) $request->getBody();
        $data = json_decode($body);

        $this->assertSame('gemini-3.5-flash-lite', $data->model);
        $this->assertSame('Explain photosynthesis.', $data->input);
        $this->assertSame('Answer only with the requested content.', $data->system_instruction);
        foreach (['temperature', 'top_p', 'top_k', 'candidate_count', 'generation_config'] as $deprecated) {
            $this->assertStringNotContainsString('"' . $deprecated . '"', $body);
        }
    }

    /**
     * Test the legacy GenerateContent request shape.
     */
    public function test_create_generate_content_request_object(): void {
        set_config(
            'action_generate_text_endpoint',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
            'aiprovider_gemini'
        );
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $data = json_decode((string) $method->invoke($processor, 'anonymous-user')->getBody());

        $this->assertSame('user', $data->contents[0]->role);
        $this->assertSame('Explain photosynthesis.', $data->contents[0]->parts[0]->text);
        $this->assertSame(
            'Answer only with the requested content.',
            $data->system_instruction->parts[0]->text
        );
    }

    /**
     * Test parsing an Interactions response.
     */
    public function test_handle_interactions_success(): void {
        $body = file_get_contents(self::get_fixture_path(
            'aiprovider_gemini',
            'interactions_text_success.json'
        ));
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $result = $method->invoke($processor, new Response(200, [], $body));

        $this->assertTrue($result['success']);
        $this->assertSame('interaction-test-id', $result['id']);
        $this->assertSame('Generated interaction response.', $result['generatedcontent']);
        $this->assertSame(7, $result['prompttokens']);
        $this->assertSame(20, $result['completiontokens']);
    }

    /**
     * Test parsing a legacy GenerateContent response.
     */
    public function test_handle_generate_content_success(): void {
        set_config(
            'action_generate_text_endpoint',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
            'aiprovider_gemini'
        );
        $body = file_get_contents(self::get_fixture_path(
            'aiprovider_gemini',
            'generate_content_text_success.json'
        ));
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');
        $result = $method->invoke($processor, new Response(200, [], $body));

        $this->assertSame('generate-content-test-id', $result['id']);
        $this->assertSame('Generated legacy response.', $result['generatedcontent']);
        $this->assertSame(5, $result['prompttokens']);
        $this->assertSame(12, $result['completiontokens']);
    }

    /**
     * Test normalisation of provider error responses.
     */
    public function test_handle_api_error(): void {
        $processor = new process_generate_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        $clienterror = $method->invoke(
            $processor,
            new Response(429, ['Content-Type' => 'application/json'], '{"error":{"message":"Rate limit"}}')
        );
        $this->assertSame(429, $clienterror['errorcode']);
        $this->assertSame('Rate limit', $clienterror['errormessage']);

        $servererror = $method->invoke(
            $processor,
            new Response(500, [], '', '1.1', 'Internal Server Error')
        );
        $this->assertSame(500, $servererror['errorcode']);
        $this->assertSame('Internal Server Error', $servererror['errormessage']);
    }
}
