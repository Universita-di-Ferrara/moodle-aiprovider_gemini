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
 * Abstract processor with primary → fallback key support.
 *
 * On auth errors (401/403), automatically retries with the fallback key
 * if one is configured. Rate limit errors (429) are NOT retried with a
 * different key — rate limits are per-project, not per-key.
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
     * Create the request object to send to the Gemini API.
     *
     * @param string $userid The user id.
     * @return RequestInterface The request object to send to the API.
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
     * Check if an HTTP status code is an authentication error that warrants fallback.
     *
     * Only 401 (unauthorized) and 403 (forbidden) trigger fallback — these indicate
     * the key itself is invalid or revoked. Rate limits (429) are per-project and
     * switching keys won't help.
     *
     * @param int $statuscode The HTTP status code.
     * @return bool True if the error is an auth error.
     */
    private function is_auth_error(int $statuscode): bool {
        return in_array($statuscode, [401, 403]);
    }

    #[\ReturnTypeWillChange]
    protected function query_ai_api(): array {
        // Always start with the primary key.
        $this->provider->reset_to_primary();

        // First attempt with primary key.
        $result = $this->make_single_request();

        if ($result['success']) {
            return $result;
        }

        $errorcode = $result['errorcode'] ?? 0;

        // If auth error and a fallback key is available, try once more.
        if ($this->is_auth_error($errorcode) && $this->provider->switch_to_fallback()) {
            debugging(
                "aiprovider_gemini: Primary key returned auth error {$errorcode}: " .
                ($result['errormessage'] ?? 'unknown') .
                ". Retrying with fallback key.",
                DEBUG_DEVELOPER
            );

            $result = $this->make_single_request();
        }

        return $result;
    }

    /**
     * Make a single API request with the current key.
     *
     * @return array The response array.
     */
    private function make_single_request(): array {
        $request = $this->create_request_object(
            userid: $this->provider->generate_userid($this->action->get_configuration('userid')),
        );
        $request = $this->provider->add_authentication_headers($request);
        $client = \core\di::get(http_client::class);

        try {
            $response = $client->send($request, [
                'base_uri' => $this->get_endpoint(),
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $e) {
            return [
                'success' => false,
                'errorcode' => $e->getCode(),
                'errormessage' => $e->getMessage(),
            ];
        }

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
