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

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class process text generation.
 *
 * @package    aiprovider_gemini
 * @copyright  2025 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends abstract_processor {
    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $prompt = $this->action->get_configuration('prompttext');
        $systeminstruction = $this->get_system_instruction();
        $modelsettings = $this->get_model_settings();

        if ($this->get_api_type() === \aiprovider_gemini\aimodel\gemini_base::API_INTERACTIONS) {
            $requestobj = (object) [
                'model' => $this->get_model(),
                'input' => $prompt,
            ];
            if (!empty($systeminstruction)) {
                $requestobj->system_instruction = $systeminstruction;
            }
            if (!empty($modelsettings['maxOutputTokens'])) {
                $requestobj->generation_config = (object) [
                    'max_output_tokens' => (int) $modelsettings['maxOutputTokens'],
                ];
            }
        } else {
            $requestobj = (object) [
                'contents' => [
                    (object) [
                        'role' => 'user',
                        'parts' => [
                            (object) ['text' => $prompt],
                        ],
                    ],
                ],
            ];
            if (!empty($systeminstruction)) {
                $requestobj->system_instruction = (object) [
                    'parts' => [
                        (object) ['text' => $systeminstruction],
                    ],
                ];
            }
            if ($modelsettings) {
                $requestobj->generationConfig = (object) $modelsettings;
            }
        }

        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode($requestobj),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(ResponseInterface $response): array {
        $bodystring = (string) $response->getBody();
        $responsebody = json_decode($bodystring);

        if ($this->get_api_type() === \aiprovider_gemini\aimodel\gemini_base::API_INTERACTIONS) {
            $generatedcontent = $responsebody->output_text ?? $this->get_interaction_text($responsebody);
            $usage = $responsebody->usage ?? null;
            return [
                'success' => true,
                'id' => $responsebody->id ?? null,
                'generatedcontent' => $generatedcontent,
                'finishreason' => 'stop',
                'prompttokens' => $usage->input_tokens ?? 0,
                'completiontokens' => $usage->output_tokens ?? 0,
                'model' => $this->get_response_model($responsebody),
            ];
        }

        $usagemetadata = $responsebody->usageMetadata ?? (object) [];
        $bodycandidate = $responsebody->candidates[0] ?? null;
        $generatedcontent = $bodycandidate->content->parts[0]->text ?? '';
        return [
            'success' => true,
            'id' => $responsebody->responseId ?? null,
            'generatedcontent' => $generatedcontent,
            'finishreason' => $bodycandidate->finishReason ?? 'unknown',
            'prompttokens' => $usagemetadata->promptTokenCount ?? 0,
            'completiontokens' => $usagemetadata->candidatesTokenCount ?? $usagemetadata->totalTokenCount ?? 0,
            'model' => $this->get_response_model($responsebody),
        ];
    }

    /**
     * Extract text from the raw Interactions API response.
     *
     * @param object $responsebody Decoded response body.
     * @return string Generated text.
     */
    private function get_interaction_text(object $responsebody): string {
        $text = '';
        foreach (($responsebody->steps ?? []) as $step) {
            foreach (($step->content ?? []) as $content) {
                if (($content->type ?? '') === 'text') {
                    $text .= $content->text ?? '';
                }
            }
        }
        return $text;
    }
}
