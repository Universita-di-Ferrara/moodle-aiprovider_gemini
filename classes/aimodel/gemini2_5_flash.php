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

namespace aiprovider_gemini\aimodel;

use core_ai\aimodel\base;
use MoodleQuickForm;

/**
 * Gemini 2.5 flash AI model.
 *
 * @package    aiprovider_gemini
 * @copyright  University of Ferrara, Italy
 * @author     Andrea Bertelli <andrea.bertell@unife.it>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini2_5_flash extends base implements gemini_base {

    #[\Override]
    public function get_model_name(): string {
        return 'gemini-2.5-flash';
    }

    #[\Override]
    public function get_model_display_name(): string {
        return 'Gemini 2.5 flash';
    }

    #[\Override]
    public function has_model_settings(): bool {
        return true;
    }

    /**
     * Get the endpoint for Gemini 2.5 Flash.
     * @return string The endpoint URL.
     */
    public function get_endpoint(): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->get_model_name() . ':generateContent';
    }

    #[\Override]
    public function add_model_settings(MoodleQuickForm $mform): void {
        $mform->addElement(
            'text',
            'topP',
            get_string('settings_top_p', 'aiprovider_gemini'),
        );
        $mform->setType('topP', PARAM_FLOAT);
        $mform->addHelpButton('topP', 'settings_top_p', 'aiprovider_gemini');

        $mform->addElement(
            'text',
            'maxOutputTokens',
            get_string('settings_max_completion_tokens', 'aiprovider_gemini'),
        );
        $mform->setType('maxOutputTokens', PARAM_INT);
        $mform->addHelpButton('maxOutputTokens', 'settings_max_completion_tokens', 'aiprovider_gemini');
    }

    #[\Override]
    public function model_type(): array {
        return [self::MODEL_TYPE_TEXT];
    }
}
