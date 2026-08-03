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

use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class process image generation.
 *
 * @package    aiprovider_gemini
 * @copyright  2025 University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertelli@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {
    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri(get_config('aiprovider_gemini', 'action_generate_image_endpoint'));
    }

    #[\Override]
    protected function get_model(): string {
        return get_config('aiprovider_gemini', 'action_generate_image_model');
    }

    #[\Override]
    protected function query_ai_api(): array {
        $validationerror = $this->validate_image_configuration();
        if ($validationerror !== null) {
            return $validationerror;
        }

        $response = parent::query_ai_api();
        if ($response['success']) {
            $response['draftfile'] = $this->base64_to_file(
                $this->action->get_configuration('userid'),
                $response['imagebase64'],
                $response['mimetype'] ?? 'image/jpeg',
            );
        }

        return $response;
    }

    /**
     * Convert a Moodle aspect ratio to the Gemini image format.
     *
     * @param string $ratio The Moodle aspect ratio.
     * @return string The Gemini aspect ratio.
     */
    private function calculate_size(string $ratio): string {
        return match ($ratio) {
            'square' => '1:1',
            'landscape' => '16:9',
            'portrait' => '9:16',
            default => throw new \coding_exception('Invalid aspect ratio: ' . $ratio),
        };
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $requestobj = (object) [
            'model' => $this->get_model(),
            'input' => [
                (object) [
                    'type' => 'text',
                    'text' => $this->action->get_configuration('prompttext'),
                ],
            ],
            'response_format' => (object) [
                'type' => 'image',
                'mime_type' => 'image/jpeg',
                'aspect_ratio' => $this->calculate_size(
                    $this->action->get_configuration('aspectratio')
                ),
                'image_size' => $this->calculate_image_quality(
                    $this->action->get_configuration('quality')
                ),
            ],
        ];

        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode($requestobj),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode((string) $response->getBody());

        foreach ($bodyobj->steps ?? [] as $step) {
            foreach ($step->content ?? [] as $content) {
                if (($content->type ?? '') === 'image' && !empty($content->data)) {
                    return [
                        'success' => true,
                        'imagebase64' => $content->data,
                        'mimetype' => $content->mime_type ?? 'image/jpeg',
                        'sourceurl' => null,
                        'revisedprompt' => null,
                    ];
                }
            }
        }

        return [
            'success' => false,
            'errorcode' => 502,
            'errormessage' => get_string('image_response_missing', 'aiprovider_gemini'),
        ];
    }

    /**
     * Convert base64 image data to a Moodle stored file.
     *
     * @param int $userid The user ID.
     * @param string $base64image The base64 image data.
     * @param string $mimetype The image MIME type.
     * @return \stored_file The stored file.
     */
    private function base64_to_file(int $userid, string $base64image, string $mimetype): \stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        $extension = match ($mimetype) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
        $filename = 'generatedimage_' . time() . '.' . $extension;
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;

        $imagebase64decoded = base64_decode($base64image, true);
        if ($imagebase64decoded === false) {
            throw new \coding_exception('The Gemini API returned invalid image data.');
        }
        file_put_contents($tempdst, $imagebase64decoded);

        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        return $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));
    }

    /**
     * Convert Moodle image quality to the Gemini image format.
     *
     * @param string $quality The Moodle image quality.
     * @return string The Gemini image size.
     */
    private function calculate_image_quality(string $quality): string {
        return match ($quality) {
            'standard' => '1K',
            'hd' => '2K',
            default => throw new \coding_exception('Invalid image quality: ' . $quality),
        };
    }

    /**
     * Validate image settings before making an API request.
     *
     * @return array|null An error response, or null when valid.
     */
    private function validate_image_configuration(): ?array {
        if (!preg_match('/^gemini-3(?:\.\d+)*-(?:flash(?:-lite)?|pro)-image$/i', $this->get_model())) {
            return [
                'success' => false,
                'errorcode' => 400,
                'errormessage' => get_string('image_model_unsupported', 'aiprovider_gemini'),
            ];
        }

        if (!$this->uses_interactions()) {
            return [
                'success' => false,
                'errorcode' => 400,
                'errormessage' => get_string('image_endpoint_unsupported', 'aiprovider_gemini'),
            ];
        }

        if (
            $this->get_model() === 'gemini-3.1-flash-lite-image'
            && $this->action->get_configuration('quality') === 'hd'
        ) {
            return [
                'success' => false,
                'errorcode' => 400,
                'errormessage' => get_string('image_quality_unsupported', 'aiprovider_gemini'),
            ];
        }
        return null;
    }
}
