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
 * VideoTrack (mod_videotrack)
 *
 * @package     mod_videotrack
 * @copyright   2026 Yeison Díaz
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

require_sesskey();

$cmid = required_param('cmid', PARAM_INT);
$percent = required_param('percent', PARAM_INT);

$cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

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
    $progress = new stdClass();
    $progress->videotrackid = $videotrack->id;
    $progress->userid = $USER->id;
    $progress->highestpercent = $percent;
    $progress->iscompleted = $completed;
    $progress->timecreated = time();
    $progress->timemodified = time();
    $DB->insert_record('videotrack_progress', $progress);
}

// Actualizar Moodle si alcanzó la meta
if ($completed) {
    require_once($CFG->libdir.'/completionlib.php');
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->set_module_viewed($cm);
        $completion->update_state($cm, COMPLETION_COMPLETE);
    }
}

echo json_encode(['success' => true]);
die();
