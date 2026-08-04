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
use GuzzleHttp\Psr7\Uri;

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
    protected function get_endpoint(): UriInterface {
        return new Uri($this->provider->actionconfig[$this->action::class]['settings']['endpoint']);
    }

    /**
     * Get the name of the model to use.
     *
     * @return string
     */
    protected function get_model(): string {
        return $this->provider->actionconfig[$this->action::class]['settings']['model'];
    }

    /**
     * Get the system instructions.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        $settings = $this->provider->actionconfig[$this->action::class]['settings'] ?? [];
        return $settings['systeminstruction'] ?? $this->action::get_system_instruction();
    }

    /**
     * Get the model settings.
     *
     * @return array
     */
    protected function get_model_settings(): array {
        $settings = $this->provider->actionconfig[$this->action::class]['settings'];
        if (!empty($settings['modelextraparams'])) {
            // Custom model settings.
            $params = json_decode($settings['modelextraparams'], true);
            foreach ($params as $key => $param) {
                $settings[$key] = $param;
            }
        }

        // Unset unnecessary settings.
        unset(
            $settings['model'],
            $settings['endpoint'],
            $settings['systeminstruction'],
            $settings['providerid'],
            $settings['modelextraparams'],
        );

        // These sampling parameters are deprecated for the current and future Gemini models.
        return $this->remove_deprecated_sampling_parameters($settings);
    }

    /**
     * Remove deprecated sampling parameters from a settings tree.
     *
     * @param array $settings Settings to sanitise.
     * @return array Sanitised settings.
     */
    private function remove_deprecated_sampling_parameters(array $settings): array {
        $deprecated = ['temperature', 'top_p', 'top_k', 'topp', 'topk'];
        foreach ($settings as $key => $value) {
            if (in_array(strtolower((string) $key), $deprecated, true)) {
                unset($settings[$key]);
            } else if (is_array($value)) {
                $settings[$key] = $this->remove_deprecated_sampling_parameters($value);
            }
        }
        return $settings;
    }

    /**
     * Get the API transport from the configured endpoint.
     *
     * This also preserves support for existing custom action configurations.
     *
     * @return string API transport.
     */
    protected function get_api_type(): string {
        $endpoint = rtrim((string) $this->get_endpoint(), '/');
        if (str_ends_with($endpoint, ':predict')) {
            return \aiprovider_gemini\aimodel\gemini_base::API_PREDICT;
        }
        if (str_ends_with($endpoint, ':generateContent')) {
            return \aiprovider_gemini\aimodel\gemini_base::API_GENERATE_CONTENT;
        }
        if (str_ends_with($endpoint, '/interactions')) {
            return \aiprovider_gemini\aimodel\gemini_base::API_INTERACTIONS;
        }
        return \aiprovider_gemini\aimodel\gemini_base::API_GENERATE_CONTENT;
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

    #[\Override]
    protected function query_ai_api(): array {

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
            // Guzzle can report a zero code for transport errors. The core AI
            // error factory requires a valid error code, so map those errors
            // to a server failure while preserving the original message.
            $errorcode = $e->getCode() > 0 ? $e->getCode() : 500;
            return \core_ai\error\factory::create($errorcode, $e->getMessage())->get_error_details();
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
        $status = $response->getStatusCode();
        if ($status >= 500 && $status < 600) {
            $errormessage = $response->getReasonPhrase();
        } else {
            $bodyobj = json_decode((string) $response->getBody());
            $errormessage = $bodyobj->error->message ?? $response->getReasonPhrase();
        }

        return \core_ai\error\factory::create($status, $errormessage)->get_error_details();
    }
}
