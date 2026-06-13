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
 * Web service function for saving video progress.
 *
 * @package    mod_videotrack
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

defined('MOODLE_INTERNAL') || die();

/**
 * Save progress external API.
 */
class save_progress extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module ID.'),
            'percent' => new external_value(PARAM_INT, 'The progress percentage achieved.')
        ]);
    }

    /**
     * Executes the save progress action.
     *
     * @param int $cmid
     * @param int $percent
     * @return array
     */
    public static function execute($cmid, $percent) {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'percent' => $percent
        ]);

        $cmid = $params['cmid'];
        $percent = $params['percent'];

        $cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videotrack:view', $context);

        $progress = $DB->get_record('videotrack_progress', ['videotrackid' => $videotrack->id, 'userid' => $USER->id]);
        $completed = ($percent >= $videotrack->targetpercent) ? 1 : 0;

        if ($progress) {
            if ($percent > $progress->highestpercent) {
                $progress->highestpercent = $percent;
                $progress->iscompleted = $completed ? 1 : $progress->iscompleted;
                $progress->timemodified = time();
                $DB->update_record('videotrack_progress', $progress);
            }
        } else {
            $progress = new \stdClass();
            $progress->videotrackid = $videotrack->id;
            $progress->userid = $USER->id;
            $progress->highestpercent = $percent;
            $progress->iscompleted = $completed;
            $progress->timecreated = time();
            $progress->timemodified = time();
            $DB->insert_record('videotrack_progress', $progress);
        }

        // Update Moodle completion if target reached.
        if ($completed) {
            require_once($CFG->libdir . '/completionlib.php');
            $completion = new \completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->set_module_viewed($cm);
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }
        }

        return ['success' => true];
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if successful.')
        ]);
    }
}
