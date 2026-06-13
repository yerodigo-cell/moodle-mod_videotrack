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


require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$id = required_param('id', PARAM_INT); // CM ID.

// 1. Get instance and course data.
$cm = get_coursemodule_from_id('videotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

// 2. Secure access control (Course teachers or admins only).
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videotrack:view', $context);
require_capability('moodle/course:manageactivities', $context);

// 3. Configure the Moodle page with course tabs support.
$PAGE->set_url('/mod/videotrack/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'mod_videotrack'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// Keep the report tab active.
$PAGE->navbar->add(get_string('report', 'mod_videotrack'));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($videotrack->name) . ' - ' . get_string('report', 'mod_videotrack'));

// 4. Find enrolled students in the course with permission to view the activity.
$users = get_enrolled_users($context, 'mod/videotrack:view', 0, 'u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt');

// Filter to exclude users with capacity to manage the activity (teachers, admins).
foreach ($users as $key => $user) {
    if (has_capability('moodle/course:manageactivities', $context, $user->id)) {
        unset($users[$key]);
    }
}

if (empty($users)) {
    echo $OUTPUT->notification(get_string('noresponses', 'mod_videotrack'), 'info');
} else {
    // Create Moodle native table with Bootstrap styles.
    $table = new html_table();
    $table->head = [
        get_string('student', 'mod_videotrack'),
        get_string('highestpercent', 'mod_videotrack'),
        get_string('completed', 'mod_videotrack'),
        get_string('lastaccess', 'mod_videotrack'),
    ];
    $table->attributes['class'] = 'generaltable mod_videotrack_report table-hover';

    // Fetch all progress records for this activity at once to prevent DB queries inside the loop.
    $allprogress = $DB->get_records('videotrack_progress', ['videotrackid' => $videotrack->id], '', 'userid, id, highestpercent, iscompleted, timemodified');

    foreach ($users as $user) {
        // Get the progress record for this user.
        $progress = $allprogress[$user->id] ?? null;

        $fullname = fullname($user);
        $userpicture = $OUTPUT->user_picture($user, ['size' => 35]);

        // Student cell with avatar and name.
        $studentcell = html_writer::div($userpicture . ' ' . html_writer::span($fullname, 'ml-2'), 'd-flex align-items-center');

        $percent = $progress ? (int)$progress->highestpercent : 0;

        // Render progress bar using native bootstrap classes.
        $progressbar = '
        <div class="d-flex align-items-center" style="min-width: 200px;">
            <div class="progress w-100 mr-2" style="height: 16px; border-radius: 8px; background-color: #e9ecef;">
                <div class="progress-bar progress-bar-striped bg-info" ' .
                'role="progressbar" style="width: ' . $percent . '%;" ' .
                'aria-valuenow="' . $percent . '" ' .
                'aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span class="font-weight-bold">' . $percent . '%</span>
        </div>';

        // Completion status with official icons.
        if ($progress && $progress->iscompleted) {
            $completedcell = html_writer::span(
                '<i class="fa fa-check-circle text-success mr-1"></i> ' . get_string('yes'),
                'text-success font-weight-bold'
            );
        } else {
            $completedcell = html_writer::span('<i class="fa fa-circle-o text-muted mr-1"></i> ' . get_string('no'), 'text-muted');
        }

        // Formatted last access date.
        $lastaccesscell = '-';
        if ($progress && $progress->timemodified) {
            $lastaccesscell = userdate($progress->timemodified, get_string('strftimedatetime', 'langconfig'));
        }

        $table->data[] = [
            $studentcell,
            $progressbar,
            $completedcell,
            $lastaccesscell,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
