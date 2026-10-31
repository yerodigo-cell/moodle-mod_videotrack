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

  

/\*\*

 \* Web service function for saving video progress.

 \*

 \* @package    mod_videotrack

 \* @copyright  2026 EduPlugins Studio

 \* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

 \*/

  

namespace mod_videotrack\\external;

  

use context_module;

use completion_info;

use external_api;

use external_function_parameters;

use external_value;

use external_single_structure;

use invalid_parameter_exception;

use stdClass;

  

defined('MOODLE_INTERNAL') || die();

  

global $CFG;

require_once($CFG->libdir . '/externallib.php');

  

/\*\*

 \* Save progress external API.

 \*/

class save_progress extends external_api {

/\*\*

     \* Maximum allowed percentage jump in a single unauthenticated first report when targetpercent > 50.

     \*/

public const MAX_SINGLE_PERCENT_JUMP = 50;

  

/\*\*

     \* Maximum tolerated playback speed multiplier for wall-clock plausibility checks.

     \*/

public const MAX_PLAYBACK_RATE = 2.5;

  

/\*\*

     \* Grace window in seconds for network jitter on wall-clock checks.

     \*/

public const CLOCK_GRACE_SECONDS = 30;

  

/\*\*

     \* Returns description of method parameters

     \*

     \* @return external_function_parameters

     \*/

public static function execute_parameters() {

return new external_function_parameters([

'cmid' => new external_value(PARAM_INT, 'The course module ID.'),

'percent' => new external_value(PARAM_INT, 'The progress percentage achieved.'),

'currenttime' => new external_value(PARAM_INT, 'The highest time in seconds reached.', VALUE_DEFAULT, 0),

        ]);

    }

  

/\*\*

     \* Executes the save progress action.

     \*

     \* @param int $cmid

     \* @param int $percent

     \* @param int $currenttime

     \* @return array

     \*/

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

  

// Normalize 98%+ to 100% on the server as well so videos stopping milliseconds before the end reach 100%.

if ($percent >= 98) {

$percent = 100;

        }

  

$cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);

$videotrack = $DB->get_record(

'videotrack',

            ['id' => $cm->instance],

'id, targetpercent, manualtime',

            MUST_EXIST

        );

  

$context = context_module::instance($cm->id);

  

self::validate_context($context);

require_capability('mod/videotrack:view', $context);

  

$now = time();

$progress = $DB->get_record('videotrack_progress', [

'videotrackid' => $videotrack->id,

'userid' => $USER->id,

        ]);

  

// 2. Server-side plausibility check against prior progress (VTRK-SEC-001).

$targetpercent = (int)$videotrack->targetpercent;

$duration = isset($videotrack->manualtime) ? (int)$videotrack->manualtime : 0;

  

if (!self::is_progress_plausible($progress ?: null, $percent, $currenttime, $targetpercent, $duration, $now)) {

if (!$progress) {

$baseline = new stdClass();

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

  

$effectivepercent = $progress ? max((int)$progress->highestpercent, $percent) : $percent;

if ($effectivepercent >= 98) {

$effectivepercent = 100;

        }

$completed = ($targetpercent <= 0 || $effectivepercent >= $targetpercent) ? 1 : 0;

  

if ($progress) {

$progress->highestpercent = $effectivepercent;

if (isset($progress->highesttime) && $currenttime > (int)$progress->highesttime) {

$progress->highesttime = $currenttime;

            } else if (!isset($progress->highesttime) && $currenttime > 0) {

$progress->highesttime = $currenttime;

            }

if ($completed) {

$progress->iscompleted = 1;

            }

$progress->timemodified = $now;

$DB->update_record('videotrack_progress', $progress);

        } else {

$progress = new stdClass();

$progress->videotrackid = $videotrack->id;

$progress->userid = $USER->id;

$progress->highestpercent = $effectivepercent;

$progress->highesttime = $currenttime;

$progress->iscompleted = $completed;

$progress->timecreated = $now;

$progress->timemodified = $now;

$DB->insert_record('videotrack_progress', $progress);

        }

  

// Update Moodle completion when target is reached.

if (!empty($progress->iscompleted)) {

$course = get_course($cm->course);

require_once($CFG->libdir . '/completionlib.php');

$completion = new completion_info($course);

if ($completion->is_enabled($cm)) {

$completion->set_module_viewed($cm);

$completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);

            }

        }

  

return ['success' => true];

    }

  

/\*\*

     \* Checks whether the reported progress increment is plausible given prior state and wall-clock time.

     \*

     \* @param stdClass|null $progress Existing progress record from DB, or null if first report.

     \* @param int $percent Reported percentage (0..100).

     \* @param int $currenttime Reported playback position in seconds (>= 0).

     \* @param int $targetpercent Configured target percentage for the activity.

     \* @param int $duration Configured duration in seconds (manualtime), or 0 if unknown.

     \* @param int $now Current server timestamp.

     \* @return bool True if plausible, false if rejected.

     \*/

public static function is_progress_plausible(

        ?stdClass $progress,

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

  

// Only if no baseline record exists at all (direct API call without opening view.php),

// reject a single jump > 50%.

if ($progress === null) {

if ($percent > self::MAX_SINGLE_PERCENT_JUMP) {

return false;

            }

return true;

        }

  

$prevpercent = (int)$progress->highestpercent;

$percentdelta = $percent - $prevpercent;

  

// Reject single unauthenticated jumps > 50% from 0% when no time has elapsed.

$elapsed = max(0, $now - (int)$progress->timemodified);

if ($prevpercent === 0 && $percentdelta > self::MAX_SINGLE_PERCENT_JUMP && $elapsed < 2) {

return false;

        }

  

// If manualtime duration is explicitly set (Google Drive manual timer mode), check wall-clock speed.

if ($duration > 0 && $percentdelta > self::MAX_SINGLE_PERCENT_JUMP) {

$maxallowedadvance = ($elapsed \* self::MAX_PLAYBACK_RATE) + self::CLOCK_GRACE_SECONDS;

$impliedseconds = ($percentdelta / 100.0) \* $duration;

if ($impliedseconds > $maxallowedadvance) {

return false;

            }

        }

  

return true;

    }

  

/\*\*

     \* Returns description of method result value

     \*

     \* @return external_single_structure

     \*/

public static function execute_returns() {

return new external_single_structure([

'success' => new external_value(PARAM_BOOL, 'True if successful.'),

        ]);

    }

}
