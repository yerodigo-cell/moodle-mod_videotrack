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
 * Web service function for saving a reaction.
 *
 * @package    mod_videotrack
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Save reaction external API.
 */
class save_reaction extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module ID.'),
            'reaction' => new external_value(PARAM_ALPHANUMEXT, 'The reaction code (e.g. heart, like).'),
            'timeoffset' => new external_value(PARAM_INT, 'The time offset in seconds where the reaction occurred.'),
        ]);
    }

    /**
     * Executes the save reaction action.
     *
     * @param int $cmid
     * @param string $reaction
     * @param int $timeoffset
     * @return array
     */
    public static function execute($cmid, $reaction, $timeoffset) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'reaction' => $reaction,
            'timeoffset' => $timeoffset,
        ]);

        $cmid = $params['cmid'];
        $reaction = $params['reaction'];
        $timeoffset = $params['timeoffset'];

        $cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
        $videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], 'id', MUST_EXIST);

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/videotrack:view', $context);

        // Save the reaction.
        $record = new \stdClass();
        $record->videotrackid = $videotrack->id;
        $record->userid = $USER->id;
        $record->reaction = $reaction;
        $record->timeoffset = $timeoffset;
        $record->timecreated = time();

        $record->id = $DB->insert_record('videotrack_reactions', $record);

        return [
            'success' => true,
            'reactionid' => $record->id,
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if successful.'),
            'reactionid' => new external_value(PARAM_INT, 'ID of the saved reaction.'),
        ]);
    }
}
