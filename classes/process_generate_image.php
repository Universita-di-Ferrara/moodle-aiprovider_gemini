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
    /** @var int The number of images to generate. */
    private int $numberimages = 1;


    #[\Override]
    protected function query_ai_api(): array {
        $response = parent::query_ai_api();

        // If the request was successful, save the URL to a file.
        if ($response['success']) {
            $fileobj = $this->base64_to_file(
                $this->action->get_configuration('userid'),
                $response['imagebase64']
            );
            // Add the file to the response, so the calling placement can do whatever they want with it.
            $response['draftfile'] = $fileobj;
        }

        return $response;
    }

    /**
     * Convert the given aspect ratio to an image size
     *
     * @param string $ratio The aspect ratio of the image.
     * @return string The size of the image.
     * aspectRatio: Changes the aspect ratio of the generated image. Supported values are:
     * "1:1", "3:4", "4:3", "9:16", and "16:9".
     * The default is "1:1".
     */
    private function calculate_size(string $ratio): string {
        if ($ratio === 'square') {
            $size = '1:1';
        } else if ($ratio === 'landscape') {
            $size = '16:9';
        } else if ($ratio === 'portrait') {
            $size = '9:16';
        } else {
            throw new \coding_exception('Invalid aspect ratio: ' . $ratio);
        }
        return $size;
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        /* ATTENTION: PROMPT TEXT MUST BE IN ENGLISH:
        * en: english (default value)
        * zh o zh-CN: chinese (simplified)
        * zh-TW: chinese (traditional)
        * hi: Hindi
        * ja: japanese
        * ko: korean
        * pt: portuguese
        * es: espanish
        * https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/imagen-api?hl=it#-drest
        * Google Gemini REST API requires a specific request format.
        * Example request body:
        *
        * {
        *     "instances": [
        *     {
        *       "prompt": "Draw a cat on the table."
        *     }
        *       ],
        *     "parameters":
        *         {
        *             "sampleCount": 1,
        *             "aspectRatio": "1:1",
        *         }
        *
        * }

        * Example response:
        * {
        *   "predictions": [
        *         {
        *           "bytesBase64Encoded": "BASE64_IMG_BYTES",
        *           "mimeType": "image/png"
        *         },
        *         {
        *           "mimeType": "image/png",
        *           "bytesBase64Encoded": "BASE64_IMG_BYTES"
        *         }
        *     ]
        * }


        */

        // Create the request object.
        $requestobj = new \stdClass();

        $requestobj->instances = [
            (object) [
                'prompt' => $this->action->get_configuration('prompttext'),
            ],
        ];

        $requestobj->parameters = (object) [
            'sampleCount' => $this->numberimages,
            'aspectRatio' => $this->calculate_size($this->action->get_configuration('aspectratio')),
            'imageSize' => $this->calculate_image_quality($this->action->get_configuration('quality')),
            'languageCode' => 'en', // Force English for best results.
        ];

        // Append the extra model settings. Check if Gemini supports them.
        $modelsettings = $this->get_model_settings();
        foreach ($modelsettings as $setting => $value) {
            $requestobj->$setting = $value;
        }

        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode((object) $requestobj),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $responsebody = $response->getBody();
        $bodyobj = json_decode($responsebody);

        $predictions = $bodyobj->predictions;

        // I have only one image.
        $imagebase64 = $predictions[0]->bytesBase64Encoded;

        return [
            'success' => true,
            'imagebase64' => $imagebase64,
        ];
    }

    /**
     * Convert a base64 image to a stored_file object.
     * Google Gemini returns images as base64 encoded strings.
     *
     * @param int $userid The user id.
     * @param string $base64image The base64 of the image.
     * @return \stored_file The file object.
     */
    private function base64_to_file(int $userid, string $base64image): \stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        // Create a temporary file to store the image, based on tiimestamp.
        $filename = 'generatedimage_' . time() . '.png';

        // Download the image and add the watermark.
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        $imagebase64decoded = base64_decode($base64image);
        file_put_contents($tempdst, $imagebase64decoded);

        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        // We put the file in the user draft area initially.
        // Placements (on behalf of the user) can then move it to the correct location.
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
     * Convert the given quality to an image size
     * that is compatible with the Gemini Imagen API.
     *
     * @param string $quality The quality of the image.
     * @return string The size of the image.
     * quality: Changes the quality of the generated image. Supported values are
     * "standard" and "hd". The default is "standard".
     */
    private function calculate_image_quality(string $quality): string {
        switch ($quality) {
            case 'standard':
                return '1k';
            case 'hd':
                return '2k';
            default:
                throw new \coding_exception('Invalid image quality: ' . $quality);
        }
    }
}
