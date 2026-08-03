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

use core_ai\aiactions;
use core_ai\rate_limiter;
use core\http_client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

/**
 * Class provider.
 *
 * @package    aiprovider_gemini
 * @copyright  2025 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /** @var string Gemini models endpoint. */
    private const MODELS_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** @var string Gemini Interactions endpoint. */
    public const INTERACTIONS_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    /** @var string The Google AI Studio - Gemini API key. */
    private string $apikey;

    /** @var bool Is global rate limiting for the API enabled. */
    private bool $enableglobalratelimit;

    /** @var int The global rate limit. */
    private int $globalratelimit;

    /** @var bool Is user rate limiting for the API enabled */
    private bool $enableuserratelimit;

    /** @var int The user rate limit. */
    private int $userratelimit;

    /**
     * Class constructor.
     */
    public function __construct() {
        // Get api key from config.
        $this->apikey = get_config('aiprovider_gemini', 'apikey');
        // Get global rate limit from config.
        $this->enableglobalratelimit = get_config('aiprovider_gemini', 'enableglobalratelimit');
        $this->globalratelimit = get_config('aiprovider_gemini', 'globalratelimit');
        // Get user rate limit from config.
        $this->enableuserratelimit = get_config('aiprovider_gemini', 'enableuserratelimit');
        $this->userratelimit = get_config('aiprovider_gemini', 'userratelimit');
    }

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
        ];
    }

    /**
     * Generate a user id.
     *
     * This is a hash of the site id and user id,
     * this means we can determine who made the request
     * but don't pass any personal data to Google.
     *
     * @param string $userid The user id.
     * @return string The generated user id.
     */
    public function generate_userid(string $userid): string {
        global $CFG;
        return hash('sha256', $CFG->siteidentifier . $userid);
    }

    /**
     * Update a request to add any headers required by the provider.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function add_authentication_headers(RequestInterface $request): RequestInterface {
        return $request
            ->withAddedHeader('x-goog-api-key', $this->apikey);
    }

    #[\Override]
    public function is_request_allowed(aiactions\base $action): array|bool {
        $ratelimiter = \core\di::get(rate_limiter::class);
        $component = \core\component::get_component_from_classname(get_class($this));

        // Check the user rate limit.
        if ($this->enableuserratelimit) {
            if (
                !$ratelimiter->check_user_rate_limit(
                    component: $component,
                    ratelimit: $this->userratelimit,
                    userid: $action->get_configuration('userid')
                )
            ) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'User rate limit exceeded',
                ];
            }
        }

        // Check the global rate limit.
        if ($this->enableglobalratelimit) {
            if (
                !$ratelimiter->check_global_rate_limit(
                    component: $component,
                    ratelimit: $this->globalratelimit
                )
            ) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'Global rate limit exceeded',
                ];
            }
        }

        return true;
    }

    /**
     * Get any action settings for this provider.
     *
     * @param string $action The action class name.
     * @param \admin_root $ADMIN The admin root object.
     * @param string $section The section name.
     * @param bool $hassiteconfig Whether the current user has moodle/site:config capability.
     * @return array An array of settings.
     */
    public function get_action_settings(
        string $action,
        \admin_root $ADMIN,
        string $section,
        bool $hassiteconfig
    ): array {

        global $PAGE;
        $PAGE->requires->js_call_amd('aiprovider_gemini/selectEndpoint', 'init');

        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $settings = [];
        if ($actionname === 'generate_text' || $actionname === 'summarise_text') {
            // Add the model setting.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_gemini/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_gemini'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_gemini'),
                $this->get_default_model($actionname),
                $this->get_all_models($actionname),
            );
            // Add API endpoint.
            $settings[] = new \admin_setting_configtext(
                "aiprovider_gemini/action_{$actionname}_endpoint",
                new \lang_string("action:{$actionname}:endpoint", 'aiprovider_gemini'),
                new \lang_string('endpoint_desc', 'aiprovider_gemini'),
                $this->get_default_endpoint($actionname),
                PARAM_URL,
            );
            // Add system instruction settings.
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_gemini/action_{$actionname}_systeminstruction",
                new \lang_string("action:{$actionname}:systeminstruction", 'aiprovider_gemini'),
                new \lang_string("action:{$actionname}:systeminstruction_desc", 'aiprovider_gemini'),
                $action::get_system_instruction(),
                PARAM_TEXT
            );
        } else if ($actionname === 'generate_image') {
            // Add the model setting.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_gemini/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_gemini'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_gemini'),
                'gemini-3.1-flash-image',
                $this->get_all_models($actionname),
            );
            // Add API endpoint.
            $settings[] = new \admin_setting_configtext(
                "aiprovider_gemini/action_{$actionname}_endpoint",
                new \lang_string("action:{$actionname}:endpoint", 'aiprovider_gemini'),
                new \lang_string('endpoint_desc', 'aiprovider_gemini'),
                self::INTERACTIONS_ENDPOINT,
                PARAM_URL,
            );
            // Gemini does not support system instructions for image generation.
        }

        return $settings;
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        return !empty($this->apikey);
    }

    /**
     * Return the default model for an action.
     *
     * @param string $actionname The action name.
     * @return string The default model identifier.
     */
    private function get_default_model(string $actionname): string {
        if ($actionname === 'generate_image') {
            return 'gemini-3.1-flash-image';
        }
        return 'gemini-3.5-flash-lite';
    }

    /**
     * Return the default endpoint for an action.
     *
     * @param string $actionname The action name.
     * @return string The default endpoint.
     */
    private function get_default_endpoint(string $actionname): string {
        return self::INTERACTIONS_ENDPOINT;
    }

    /**
     * Decide whether a model is a stable Gemini model.
     *
     * @param string $modelid The model identifier without the models/ prefix.
     * @return bool True when the model is suitable for this provider.
     */
    private function is_stable_model(string $modelid): bool {
        if (!str_starts_with($modelid, 'gemini-')) {
            return false;
        }
        return !preg_match(
            '/(?:preview|experimental|^gemini-.*-exp$|-latest$|-live(?:-|$)|-tts(?:-|$)|embedding)/i',
            $modelid
        );
    }

    /**
     * Decide whether a model supports the requested action.
     *
     * @param string $modelid The model identifier without the models/ prefix.
     * @param array $supportedmethods Methods returned by the models endpoint.
     * @param string $actionname The Moodle action name.
     * @return bool True when the model belongs in the selector.
     */
    private function model_supports_action(
        string $modelid,
        array $supportedmethods,
        string $actionname
    ): bool {
        $hasgeneratecontent = in_array('generateContent', $supportedmethods, true);

        if ($actionname === 'generate_image') {
            return preg_match('/^gemini-3(?:\.\d+)*-(?:flash(?:-lite)?|pro)-image$/i', $modelid)
                && ($hasgeneratecontent
                    || in_array('createInteraction', $supportedmethods, true)
                    || empty($supportedmethods));
        }

        return !str_contains($modelid, '-image') && $hasgeneratecontent;
    }

    /**
     * Get list of all Gemini models.
     * @return array List of models.
     * @param string $actionname The action name (generate_text, generate_image, etc.).
     */
    private function get_all_models($actionname): array {
        // Call the Gemini API to get the list of models.
        $endpoint = self::MODELS_ENDPOINT;
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
                if (!in_array($actionname, ['generate_text', 'summarise_text', 'generate_image'], true)) {
                    return [];
                }
                foreach ($bodyobj->models ?? [] as $model) {
                    $cleanid = str_replace('models/', '', $model->name ?? '');
                    $supportedmethods = (array) ($model->supportedGenerationMethods ?? []);
                    if (
                        !$this->is_stable_model($cleanid)
                        || !$this->model_supports_action($cleanid, $supportedmethods, $actionname)
                    ) {
                        continue;
                    }
                    $displayname = $model->displayName ?? $cleanid;
                    $models[$cleanid] = $displayname;
                }

                if (!empty($bodyobj->nextPageToken)) {
                    $request = new Request(
                        method: 'GET',
                        uri: $endpoint . '?pageToken=' . $bodyobj->nextPageToken,
                    );
                    $request = $this->add_authentication_headers($request);
                }
            } while (!empty($bodyobj->nextPageToken));

            return $models;
        } catch (\Exception $e) {
            return [new \lang_string("getallmodels_error", "aiprovider_gemini")];  // Return error array on exception.
        }
    }
}
