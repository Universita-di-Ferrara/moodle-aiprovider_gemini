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

use core_ai\form\action_settings_form;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Psr7\Request;
use core\http_client;

/**
 * Class provider.
 *
 * @package    aiprovider_gemini
 * @copyright  2025 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
            \core_ai\aiactions\explain_text::class,

        ];
    }

    /**
     * Update a request to add any headers required by the provider.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function add_authentication_headers(RequestInterface $request): RequestInterface {
        return $request
            ->withAddedHeader('x-goog-api-key', $this->config['apikey']);
    }

    #[\Override]
    public static function get_action_settings(
        string $action,
        array $customdata = [],
    ): action_settings_form|bool {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata['actionname'] = $actionname;
        $customdata['action'] = $action;
        if ($actionname === 'generate_text' || $actionname === 'summarise_text' || $actionname === 'explain_text') {
            return new form\action_generate_text_form(customdata: $customdata);
        } else if ($actionname === 'generate_image') {
            return new form\action_generate_image_form(customdata: $customdata);
        }

        return false;
    }

    #[\Override]
    public static function get_action_setting_defaults(string $action): array {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata = [
            'actionname' => $actionname,
            'action' => $action,
            'providername' => 'aiprovider_openai',
        ];
        if ($actionname === 'generate_text' || $actionname === 'summarise_text' || $actionname === 'explain_text') {
            $mform = new form\action_generate_text_form(customdata: $customdata);
            return $mform->get_defaults();
        } else if ($actionname === 'generate_image') {
            $mform = new form\action_generate_image_form(customdata: $customdata);
            return $mform->get_defaults();
        }

        return [];
    }
    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        return !empty($this->config['apikey']);
    }

    /**
     * Get list of all Gemini models.
     * @return array List of models.
     * @param string $actionname The action name (generate_text, generate_image, etc.).
     */
    private function get_all_models($actionname): array {
        // Call the Gemini API to get the list of models.
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models';
        $request = new Request(
            method: 'GET',
            uri: $endpoint,
        );
        $request = $this->add_authentication_headers($request);
        $client = \core\di::get(http_client::class);

        $models = [];
        try {
            do {
                $response = $client->send($request);
                if ($response->getStatusCode() !== 200) {
                    return [];  // Return empty array on error.
                }
                $responsebody = $response->getBody();
                $bodyobj = json_decode($responsebody->getContents());

                /*
                * Filter models based on the action name.
                */
                if ($actionname === 'generate_text' || $actionname === 'summarise_text') {
                    // Regex to filter model "gemini-version-tipo".
                    $pattern = '/^models\/gemini-\d+(\.\d+)?(-\d+)?-(pro|flash|flash-lite)(-8b)?$/';
                } else if ($actionname === 'generate_image') {
                    // Regex to filter imagen models, only stable versions.
                    // Struttura: models/imagen-x.y-generate-<numero>.
                    $pattern = '/^models\/imagen-\d+(\.\d+)?(-[a-z]+)?-generate-\d+$/i';
                } else {
                    return [];
                }
                foreach ($bodyobj->models as $model) {
                    if (preg_match($pattern, $model->name)) {
                        $models[] = $model->name;
                    }
                }
                if ($bodyobj->nextPageToken) {
                    $request = new Request(
                        method: 'GET',
                        uri: $endpoint . '?pageToken=' . $bodyobj->nextPageToken,
                    );
                    $request = $this->add_authentication_headers($request);
                }
            } while ($bodyobj->nextPageToken);

            return $models;
        } catch (\Exception $e) {
            return [new \lang_string("getallmodels_error", "aiprovider_gemini")];  // Return error array on exception.
        }
    }
}
