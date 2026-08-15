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

/**
 * Web service function for getting video reactions.
 *
 * @package    mod_videotrack
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack\external;

use external_api;
use external_function_parameters;
use external_value;
use external_multiple_structure;
use external_single_structure;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Get reactions external API.
 */
class get_reactions extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module ID.'),
        ]);
    }

    /**
     * Executes the get reactions action.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute($cmid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        $cmid = $params['cmid'];

        $cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
        $videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], 'id', MUST_EXIST);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videotrack:view', $context);

        $records = $DB->get_records('videotrack_reactions', ['videotrackid' => $videotrack->id], 'timeoffset ASC', 'id, reaction, timeoffset');
        
        $reactions = [];
        foreach ($records as $record) {
            $reactions[] = [
                'id' => $record->id,
                'reaction' => $record->reaction,
                'timeoffset' => $record->timeoffset,
            ];
        }

        return [
            'reactions' => $reactions,
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'reactions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Reaction ID'),
                    'reaction' => new external_value(PARAM_ALPHA, 'Reaction code'),
                    'timeoffset' => new external_value(PARAM_INT, 'Time offset'),
                ])
            ),
        ]);
    }
}
