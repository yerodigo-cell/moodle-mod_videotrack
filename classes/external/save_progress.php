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
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack\external;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Save progress external API.
 */
class save_progress extends external_api {
    /**
     * Maximum allowed percentage jump in a single telemetry report when targetpercent > 0.
     * Prevents completing an activity in a single unauthenticated jump (e.g. 0% -> 100%).
     */
    public const MAX_SINGLE_PERCENT_JUMP = 50;

    /**
     * Maximum tolerated playback speed multiplier for wall-clock plausibility checks.
     */
    public const MAX_PLAYBACK_RATE = 2.5;

    /**
     * Grace window in seconds for network jitter on wall-clock checks.
     */
    public const CLOCK_GRACE_SECONDS = 3;

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module ID.'),
            'percent' => new external_value(PARAM_INT, 'The progress percentage achieved.'),
            'currenttime' => new external_value(PARAM_INT, 'The highest time in seconds reached.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Executes the save progress action.
     *
     * @param int $cmid
     * @param int $percent
     * @param int $currenttime
     * @return array
     */
    public static function execute($cmid, $percent, $currenttime = 0) {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'percent' => $percent,
            'currenttime' => $currenttime,
        ]);

        $cmid = $params['cmid'];
        $percent = $params['percent'];
        $currenttime = $params['currenttime'];

        // 1. Server-side range validation (Closes mod_videotrack/VTRK-SEC-001).
        if ($percent < 0 || $percent > 100 || $currenttime < 0) {
            throw new invalid_parameter_exception('Progress out of range.');
        }

        $cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
        $videotrack = $DB->get_record(
            'videotrack',
            ['id' => $cm->instance],
            'id, targetpercent, manualtime',
            MUST_EXIST
        );

        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videotrack:view', $context);

        $now = time();
        $progress = $DB->get_record('videotrack_progress', [
            'videotrackid' => $videotrack->id,
            'userid' => $USER->id,
        ]);

        // 2. Server-side plausibility check against prior progress and wall-clock time (VTRK-SEC-001).
        $targetpercent = (int)$videotrack->targetpercent;
        $duration = isset($videotrack->manualtime) ? (int)$videotrack->manualtime : 0;

        if (!self::is_progress_plausible($progress ?: null, $percent, $currenttime, $targetpercent, $duration, $now)) {
            // If no baseline record exists yet, initialize a 0% baseline so future incremental
            // telemetry reports have a server-side wall-clock reference timestamp.
            if (!$progress) {
                $baseline = new \stdClass();
                $baseline->videotrackid = $videotrack->id;
                $baseline->userid = $USER->id;
                $baseline->highestpercent = 0;
                $baseline->highesttime = 0;
                $baseline->iscompleted = 0;
                $baseline->timecreated = $now;
                $baseline->timemodified = $now;
                $DB->insert_record('videotrack_progress', $baseline);
            }
            return ['success' => false];
        }

        $completed = ($targetpercent <= 0 || $percent >= $targetpercent) ? 1 : 0;

        if ($progress) {
            $updated = false;
            if ($percent > $progress->highestpercent) {
                $progress->highestpercent = $percent;
                $updated = true;
            }
            if (isset($progress->highesttime) && $currenttime > $progress->highesttime) {
                $progress->highesttime = $currenttime;
                $updated = true;
            } else if (!isset($progress->highesttime) && $currenttime > 0) {
                $progress->highesttime = $currenttime;
                $updated = true;
            }
            if ($completed && empty($progress->iscompleted)) {
                $updated = true;
            }

            // Always update timemodified on valid telemetry so subsequent wall-clock checks stay accurate.
            $progress->timemodified = $now;
            if ($updated) {
                $progress->iscompleted = $completed ? 1 : (int)$progress->iscompleted;
            }
            $DB->update_record('videotrack_progress', $progress);
        } else {
            $progress = new \stdClass();
            $progress->videotrackid = $videotrack->id;
            $progress->userid = $USER->id;
            $progress->highestpercent = $percent;
            $progress->highesttime = $currenttime;
            $progress->iscompleted = $completed;
            $progress->timecreated = $now;
            $progress->timemodified = $now;
            $DB->insert_record('videotrack_progress', $progress);
        }

        // Update Moodle completion only after passing range and plausibility checks.
        if ($completed) {
            $course = get_course($cm->course);
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
     * Checks whether the reported progress increment is plausible given prior state and wall-clock time.
     *
     * @param \stdClass|null $progress Existing progress record from DB, or null if first report.
     * @param int $percent Reported percentage (0..100).
     * @param int $currenttime Reported playback position in seconds (>= 0).
     * @param int $targetpercent Configured target percentage for the activity.
     * @param int $duration Configured duration in seconds (manualtime), or 0 if unknown.
     * @param int $now Current server timestamp.
     * @return bool True if plausible, false if rejected.
     */
    public static function is_progress_plausible(
        ?\stdClass $progress,
        int $percent,
        int $currenttime,
        int $targetpercent,
        int $duration,
        int $now
    ): bool {
        // Free exploration mode (targetpercent <= 0) does not enforce restricted completion.
        if ($targetpercent <= 0) {
            return true;
        }

        if ($progress === null) {
            // First telemetry report: reject any single jump that reaches the completion target
            // (when targetpercent > MAX_SINGLE_PERCENT_JUMP) or exceeds MAX_SINGLE_PERCENT_JUMP.
            if ($percent > self::MAX_SINGLE_PERCENT_JUMP) {
                return false;
            }
            if ($targetpercent > 0 && $percent >= $targetpercent) {
                return false;
            }
            return true;
        }

        $prevpercent = (int)$progress->highestpercent;
        $prevtime = isset($progress->highesttime) ? (int)$progress->highesttime : 0;
        $elapsed = max(0, $now - (int)$progress->timemodified);

        $percentdelta = $percent - $prevpercent;
        $timedelta = $currenttime - $prevtime;

        // 1. Require incremental reports: reject single jumps > 50 percentage points.
        if ($percentdelta > self::MAX_SINGLE_PERCENT_JUMP) {
            return false;
        }

        // 2. If playback seconds increased, check against wall-clock time elapsed since last report.
        $maxallowedadvance = ($elapsed * self::MAX_PLAYBACK_RATE) + self::CLOCK_GRACE_SECONDS;
        if ($timedelta > 0 && $timedelta > $maxallowedadvance) {
            return false;
        }

        // 3. If the activity has a configured duration (manualtime > 0), also check that the
        // percentage increment does not exceed what wall-clock time allows.
        if ($duration > 0 && $percentdelta > 0) {
            $impliedseconds = ($percentdelta / 100.0) * $duration;
            if ($impliedseconds > $maxallowedadvance) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if successful.'),
        ]);
    }
}
