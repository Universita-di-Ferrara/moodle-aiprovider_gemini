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

use core\http_client;
use core_ai\aiactions\responses\response_base;
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
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
abstract class abstract_processor extends process_base {
    /**
     * Get the endpoint URI.
     *
     * @return UriInterface
     */
    abstract protected function get_endpoint(): UriInterface;

    /**
     * Get the name of the model to use.
     *
     * @return string
     */
    abstract protected function get_model(): string;

    /**
     * Get the system instructions.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        return $this->action::get_system_instruction();
    }

    /**
     * Create the request object to send to the OpenAI API.
     *
     * This object contains all the required parameters for the request.
     *
     *
     *
     * @param string $userid The user id.
     * @return RequestInterface The request object to send to the OpenAI API.
     */
    abstract protected function create_request_object(
        string $userid,
    ): RequestInterface;

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    abstract protected function handle_api_success(ResponseInterface $response): array;

    /**
     * Check if an HTTP status code is retryable by failing over to the next API key.
     *
     * Retryable: 429 (rate limit), 401/403 (auth error — key may be invalid/revoked).
     * Not retryable: 400 (bad request), 200 (success), 5xx (server error — key won't help).
     *
     * @param int $statuscode The HTTP status code.
     * @return bool True if the error is retryable with a different key.
     */
    private function is_retryable_status(int $statuscode): bool {
        return in_array($statuscode, [429, 401, 403]);
    }

    #[\ReturnTypeWillChange]
    protected function query_ai_api(): array {
        $numkeys = count($this->provider->get_apikeys());
        $maxattempts = max(1, $numkeys); // Try at most once per available key.

        for ($attempt = 0; $attempt < $maxattempts; $attempt++) {
            $result = $this->make_single_request();

            if ($result['success']) {
                return $result;
            }

            $errorcode = $result['errorcode'] ?? 0;

            // If the error is retryable and we have more keys to try, failover.
            if ($this->is_retryable_status($errorcode) && $attempt < $maxattempts - 1) {
                $advanced = $this->provider->advance_to_next_key();
                if (!$advanced) {
                    // Only one key configured, no point retrying.
                    return $result;
                }
                // Log the failover for debugging.
                debugging(
                    "aiprovider_gemini: Failover from key index " .
                    ($this->provider->get_active_key_index() - 1 < 0 ? $numkeys - 1 : $this->provider->get_active_key_index() - 1) .
                    " due to error {$errorcode}: " . ($result['errormessage'] ?? 'unknown') .
                    ". Switched to key index " . $this->provider->get_active_key_index(),
                    DEBUG_DEVELOPER
                );
                continue;
            }

            // Non-retryable error or exhausted all keys.
            return $result;
        }

        // Should not reach here, but safety net.
        return [
            'success' => false,
            'errorcode' => 0,
            'errormessage' => 'All API keys exhausted',
        ];
    }

    /**
     * Make a single API request with the current active key.
     *
     * @return array The response array.
     */
    private function make_single_request(): array {
        // Create the request object.
        $request = $this->create_request_object(
            userid: $this->provider->generate_userid($this->action->get_configuration('userid')),
        );
        $request = $this->provider->add_authentication_headers($request);
        $client = \core\di::get(http_client::class);
        try {
            // Call the external AI service.
            $response = $client->send($request, [
                'base_uri' => $this->get_endpoint(),
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $e) {
            // Handle any exceptions.
            return [
                'success' => false,
                'errorcode' => $e->getCode(),
                'errormessage' => $e->getMessage(),
            ];
        }
        // Double-check the response codes, in case of a non 200 that didn't throw an error.
        $status = $response->getStatusCode();
        if ($status === 200) {
            return $this->handle_api_success($response);
        } else {
            return $this->handle_api_error($response);
        }
    }

    /**
     * Handle an error from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The error response.
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $responsearr = [
            'success' => false,
            'errorcode' => $response->getStatusCode(),
        ];

        $status = $response->getStatusCode();
        if ($status >= 500 && $status < 600) {
            $responsearr['errormessage'] = $response->getReasonPhrase();
        } else {
            $bodyobj = json_decode($response->getBody()->getContents());
            $responsearr['errormessage'] = $bodyobj->error->message;
        }

        return $responsearr;
    }
}
