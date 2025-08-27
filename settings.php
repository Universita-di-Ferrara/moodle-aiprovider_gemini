<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     aiprovider_gemini
 * @copyright   2025 University of Ferrara, Italy
 * @author      Andrea Bertelli <andrea.bertelli@unife.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_ai\admin\admin_settingspage_provider;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Provider specific settings heading.
    $settings = new admin_settingspage_provider(
        'aiprovider_gemini',
        new lang_string('pluginname', 'aiprovider_gemini'),
        'moodle/site:config',
        true,
    );

    $settings->add(new admin_setting_heading(
        'aiprovider_gemini/general',
        new lang_string('settings', 'core'),
        '',
    ));

    // Setting to store gemini API key.
    $settings->add(new admin_setting_configpasswordunmask(
        'aiprovider_gemini/apikey',
        new lang_string('apikey', 'aiprovider_gemini'),
        new lang_string('apikey_desc', 'aiprovider_gemini'),
        '',
    ));

    // Setting to enable/disable global rate limiting.
    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_gemini/enableglobalratelimit',
        new lang_string('enableglobalratelimit', 'aiprovider_gemini'),
        new lang_string('enableglobalratelimit_desc', 'aiprovider_gemini'),
        0,
    ));

    // Setting to set how many requests per hour are allowed for the global rate limit.
    // Should only be enabled when global rate limiting is enabled.
    $settings->add(new admin_setting_configtext(
        'aiprovider_gemini/globalratelimit',
        new lang_string('globalratelimit', 'aiprovider_gemini'),
        new lang_string('globalratelimit_desc', 'aiprovider_gemini'),
        100,
        PARAM_INT,
    ));
    $settings->hide_if('aiprovider_gemini/globalratelimit', 'aiprovider_gemini/enableglobalratelimit', 'eq', 0);

    // Setting to enable/disable user rate limiting.
    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_gemini/enableuserratelimit',
        new lang_string('enableuserratelimit', 'aiprovider_gemini'),
        new lang_string('enableuserratelimit_desc', 'aiprovider_gemini'),
        0,
    ));

    // Setting to set how many requests per hour are allowed for the user rate limit.
    // Should only be enabled when user rate limiting is enabled.
    $settings->add(new admin_setting_configtext(
        'aiprovider_gemini/userratelimit',
        new lang_string('userratelimit', 'aiprovider_gemini'),
        new lang_string('userratelimit_desc', 'aiprovider_gemini'),
        10,
        PARAM_INT,
    ));
    $settings->hide_if('aiprovider_gemini/userratelimit', 'aiprovider_gemini/enableuserratelimit', 'eq', 0);
}
