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
     * Maximum allowed percentage jump in a single progress report (unless targetpercent is already small).
     * Requires progress to arrive in multiple increasing reports rather than a single jump to 100%.
     */
    const MAX_SINGLE_PERCENT_INCREMENT = 50;

    /**
     * Maximum playback speed factor tolerated when comparing reported progress against wall-clock time.
     * Allows 2x playback speed plus a small network jitter buffer.
     */
    const MAX_PLAYBACK_RATE_FACTOR = 2.5;

    /**
     * Grace buffer in seconds for network jitter between telemetry calls.
     */
    const WALL_CLOCK_BUFFER_SECONDS = 3;

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
     * @throws invalid_parameter_exception
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

        // 1. Server-side range validation (Fixes mod_videotrack/VTRK-SEC-001).
        if ($percent < 0 || $percent > 100 || $currenttime < 0) {
            throw new invalid_parameter_exception('Progress out of range.');
        }

        $cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
        $videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/videotrack:view', $context);

        $now = time();
        $targetpercent = (int)$videotrack->targetpercent;
        $manualtime = isset($videotrack->manualtime) ? (int)$videotrack->manualtime : 0;

        $progress = $DB->get_record('videotrack_progress', [
            'videotrackid' => $videotrack->id,
            'userid' => $USER->id,
        ]);

        // 2. Server-side plausibility check (Fixes mod_videotrack/VTRK-SEC-001).
        $plausible = self::is_progress_plausible($progress, $percent, $currenttime, $targetpercent, $manualtime, $now);

        if (!$plausible) {
            // If there is no prior record at all and a student sends an implausible jump (e.g. percent=100),
            // initialize the baseline record at 0% so subsequent legitimate reports have a wall-clock reference,
            // while keeping the activity incomplete.
            if (!$progress) {
                $baseline = new \stdClass();
                $baseline->videotrackid = $videotrack->id;
                $baseline->userid = $USER->id;
                $baseline->highestpercent = 0;
                $baseline->highesttime = 0;
                $baseline->iscompleted = ($targetpercent <= 0) ? 1 : 0;
                $baseline->timecreated = $now;
                $baseline->timemodified = $now;
                $DB->insert_record('videotrack_progress', $baseline);
            }
            return ['success' => false];
        }

        $completed = ($targetpercent <= 0 || $percent >= $targetpercent) ? 1 : 0;

        if ($progress) {
            $updated = false;
            if ($percent > (int)$progress->highestpercent) {
                $progress->highestpercent = $percent;
                $updated = true;
            }
            if (isset($progress->highesttime) && $currenttime > (int)$progress->highesttime) {
                $progress->highesttime = $currenttime;
                $updated = true;
            } else if (!isset($progress->highesttime) && $currenttime > 0) {
                $progress->highesttime = $currenttime;
                $updated = true;
            }

            if ($completed && empty($progress->iscompleted)) {
                $progress->iscompleted = 1;
                $updated = true;
            }

            // Always update timemodified on valid progress reports to maintain accurate wall-clock tracking.
            $progress->timemodified = $now;
            if ($updated) {
                $DB->update_record('videotrack_progress', $progress);
            } else {
                $DB->set_field('videotrack_progress', 'timemodified', $now, ['id' => $progress->id]);
            }
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
        if (!empty($progress->iscompleted)) {
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
     * Checks whether the reported progress is plausible given prior state and wall-clock time elapsed.
     *
     * @param \stdClass|false $progress Existing progress record or false if none.
     * @param int $percent Reported percentage (0..100).
     * @param int $currenttime Reported playback position in seconds.
     * @param int $targetpercent Configured target percentage for completion.
     * @param int $manualtime Configured video duration in seconds (0 if not configured).
     * @param int $now Current unix timestamp.
     * @return bool True if plausible, false otherwise.
     */
    protected static function is_progress_plausible(
        $progress,
        int $percent,
        int $currenttime,
        int $targetpercent,
        int $manualtime,
        int $now
    ): bool {
        // Free mode (targetpercent <= 0) does not require playback verification.
        if ($targetpercent <= 0) {
            return true;
        }

        if (!$progress) {
            // First report ever for this user/activity:
            // Reject a single jump straight to the completion target or > MAX_SINGLE_PERCENT_INCREMENT.
            if ($percent >= $targetpercent || $percent > self::MAX_SINGLE_PERCENT_INCREMENT) {
                return false;
            }

            // If manualtime (duration) is configured, check that initial currenttime is within a reasonable window.
            if ($manualtime > 0) {
                $maxinitialtime = (int)ceil(($manualtime * (self::MAX_SINGLE_PERCENT_INCREMENT / 100)) + self::WALL_CLOCK_BUFFER_SECONDS);
                if ($currenttime > $maxinitialtime) {
                    return false;
                }
            }

            return true;
        }

        $prevpercent = (int)$progress->highestpercent;
        $prevtime = isset($progress->highesttime) ? (int)$progress->highesttime : 0;
        $lasttimestamp = !empty($progress->timemodified) ? (int)$progress->timemodified : (int)$progress->timecreated;
        $elapsed = max(0, $now - $lasttimestamp);

        $percentdelta = $percent - $prevpercent;
        $timedelta = $currenttime - $prevtime;

        // If the student is re-watching an earlier segment (no forward jump in percent or time), allow it.
        if ($percentdelta <= 0 && $timedelta <= 0) {
            return true;
        }

        // 1. Require multiple increasing reports rather than a single massive jump from 0 to target.
        if ($percentdelta > self::MAX_SINGLE_PERCENT_INCREMENT) {
            return false;
        }

        // If jumping to or past targetpercent from 0%, reject unless previous progress was already recorded (> 0).
        if ($prevpercent === 0 && $percent >= $targetpercent) {
            return false;
        }

        // 2. Wall-clock plausibility check against reported playback seconds (currenttime).
        if ($timedelta > 0) {
            $maxallowedtimedelta = ($elapsed * self::MAX_PLAYBACK_RATE_FACTOR) + self::WALL_CLOCK_BUFFER_SECONDS;
            if ($timedelta > $maxallowedtimedelta) {
                return false;
            }
        }

        // 3. Wall-clock plausibility check against known activity duration (manualtime), when configured.
        if ($manualtime > 0 && $percentdelta > 0) {
            $requiredseconds = ($percentdelta / 100.0) * $manualtime;
            $maxallowedseconds = ($elapsed * self::MAX_PLAYBACK_RATE_FACTOR) + self::WALL_CLOCK_BUFFER_SECONDS;
            if ($requiredseconds > $maxallowedseconds) {
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
