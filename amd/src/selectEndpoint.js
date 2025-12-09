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

/**
 * Gemini AI Provider - Endpoint Selection JS
 * Updates endpoint URLs based on selected models.
 *
 * @module      aiprovider_gemini/selectEndpoint
 * @copyright   2025 University of Ferrara, Italy
 * @author      Andrea Bertelli <andrea.bertelli@unife.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Settings logic for Gemini AI Provider.
 * Updates endpoint URLs based on model selection.
 */
define(['jquery'], function($) {
    return {
        init: function() {
            // Configurazione delle mappature: Nome campo Select -> Nome campo Input -> Suffisso URL
            const mappings = [
                {
                    type: 'text',
                    select: 'action_generate_text_model',
                    input: 'action_generate_text_endpoint',
                    suffix: ':generateContent'
                },
                {
                    type: 'text',
                    select: 'action_summarise_text_model',
                    input: 'action_summarise_text_endpoint',
                    suffix: ':generateContent'
                },
                {
                    type: 'image',
                    select: 'action_generate_image_model',
                    input: 'action_generate_image_endpoint',
                    suffix: ':predict'
                }
            ];

            // Itera su ogni configurazione
            mappings.forEach(function(map) {
                // Moodle costruisce gli ID delle impostazioni come: id_s_pluginname_settingname
                const selectId = '#id_s_aiprovider_gemini_' + map.select;
                const inputId = '#id_s_aiprovider_gemini_' + map.input;

                const $select = $(selectId);
                const $input = $(inputId);

                // Se entrambi gli elementi esistono nella pagina
                if ($select.length && $input.length) {

                    // Funzione per aggiornare l'URL
                    const updateEndpoint = function() {
                        const model = $select.val();
                        // Base URL standard per Gemini
                        const baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
                        // Costruisci il nuovo URL
                        const newUrl = baseUrl + model + map.suffix;

                        // Aggiorna il campo input e fa un leggero effetto visivo
                        $input.val(newUrl).css('background-color', '#e8f0fe').animate({backgroundColor: '#ffffff'}, 500);
                    };

                    // Aggiungi il listener per il cambio
                    $select.on('change', updateEndpoint);
                }
            });
        }
    };
});


