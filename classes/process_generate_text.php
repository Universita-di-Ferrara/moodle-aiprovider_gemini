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

    /** @var $fallbackmodel Fallback model in case model selection has a problem **/
    private string $fallbackmodel = "gemini-2.5-flash";

    #[\Override]
    protected function get_endpoint(): UriInterface {
        // The Gemini Endpoint contains the model, so to use the selected model we update it.
        $endpointtemplate = get_config('aiprovider_gemini', 'action_generate_text_endpoint');
        $theendpoint = str_replace('@@selectedmodel@@', $this->get_model(), $endpointtemplate);
        return new Uri($theendpoint);
    }

    #[\Override]
    protected function get_model(): string {
        $themodel = get_config('aiprovider_gemini', 'action_generate_text_model');
        // Previously the value of model saved in the settings was a numeric array key.
        // But we made the array key the model name so we could access it here.
        // Anyone on the old settings however would be in trouble, so we use this fallback in that case.
        if (!$themodel || is_numeric($themodel)) {
            $themodel = $this->fallbackmodel;
        }
        return $themodel;
    }

    #[\Override]
    protected function get_system_instruction(): string {
        return get_config('aiprovider_gemini', 'action_generate_text_systeminstruction');
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        /*
         Google Gemini REST API requires a specific request format.
         Example request body:

        {
            "system_instruction": {
              "parts": [
                {
                  "text": "You are a cat. Your name is Neko."
                }
              ]
            },
            "contents": [
                {
                    "parts": [
                        {
                            "text": "Hello there"
                        }
                    ]
                }
            ]
        }
        */

        // Create the user object.
        $userobj = new \stdClass();
        $userobj->role = 'user';
        $userobj->parts = [
            "text" => $this->action->get_configuration('prompttext'),
        ];

        // Create the request object.
        $requestobj = new \stdClass();

        // Set model and system instruction.
        $requestobj->model = $this->get_model();

        // If there is a system string available, use it.
        $systeminstruction = $this->get_system_instruction();
        if (!empty($systeminstruction)) {
            $systemobj = new \stdClass();
            $systemobj->role = 'model';
            $systemobj->parts = [
                ["text" => $systeminstruction],
            ];

            $requestobj->system_instruction = $systemobj;
            $requestobj->contents = $userobj;
        } else {
            $requestobj->contents = $userobj;
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

        $usagemetadata = $responsebody->usageMetadata;
        $bodycandidate = $responsebody->candidates[0] ?? null;
        return [
            'success' => true,
            'id' => $responsebody->responseId,
            'generatedcontent' => $bodycandidate->content->parts[0]->text,
            'finishreason' => $bodycandidate->finishReason ?? 'unknown',
            'prompttokens' => $usagemetadata->promptTokenCount,
            'completiontokens' => $usagemetadata->totalTokenCount,
        ];
    }
}
