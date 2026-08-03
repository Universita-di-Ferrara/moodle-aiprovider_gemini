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
    protected function get_endpoint(): UriInterface {
        return new Uri(get_config('aiprovider_gemini', 'action_generate_text_endpoint'));
    }

    #[\Override]
    protected function get_model(): string {
        return get_config('aiprovider_gemini', 'action_generate_text_model');
    }

    #[\Override]
    protected function get_system_instruction(): string {
        return get_config('aiprovider_gemini', 'action_generate_text_systeminstruction');
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $prompttext = $this->action->get_configuration('prompttext');
        $systeminstruction = $this->get_system_instruction();

        if ($this->uses_interactions()) {
            $requestobj = (object) [
                'model' => $this->get_model(),
                'input' => $prompttext,
            ];
            if (!empty($systeminstruction)) {
                $requestobj->system_instruction = $systeminstruction;
            }
        } else {
            $requestobj = (object) [
                'contents' => [
                    (object) [
                        'role' => 'user',
                        'parts' => [
                            (object) ['text' => $prompttext],
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
        $responsebody = json_decode((string) $response->getBody());

        if ($this->uses_interactions()) {
            return $this->handle_interactions_success($responsebody);
        }

        $bodycandidate = $responsebody->candidates[0] ?? null;
        $usagemetadata = $responsebody->usageMetadata ?? null;
        return [
            'success' => true,
            'id' => $responsebody->responseId ?? null,
            'generatedcontent' => $bodycandidate->content->parts[0]->text ?? '',
            'finishreason' => $bodycandidate->finishReason ?? 'unknown',
            'prompttokens' => $usagemetadata->promptTokenCount ?? 0,
            'completiontokens' => $usagemetadata->candidatesTokenCount
                ?? $usagemetadata->totalTokenCount
                ?? 0,
        ];
    }

    /**
     * Handle a successful Interactions API response.
     *
     * @param object|null $responsebody Decoded response body.
     * @return array The normalised Moodle response.
     */
    private function handle_interactions_success(?object $responsebody): array {
        $textparts = [];
        foreach ($responsebody->steps ?? [] as $step) {
            foreach ($step->content ?? [] as $content) {
                if (($content->type ?? '') === 'text' && isset($content->text)) {
                    $textparts[] = $content->text;
                }
            }
        }

        $usage = $responsebody->usage ?? null;
        return [
            'success' => true,
            'id' => $responsebody->id ?? null,
            'generatedcontent' => implode('', $textparts),
            'finishreason' => $responsebody->status ?? 'completed',
            'prompttokens' => $usage->total_input_tokens ?? 0,
            'completiontokens' => $usage->total_output_tokens ?? 0,
        ];
    }
}
