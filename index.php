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


require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

// If the Moodle 5.0+ course activities overview is available, redirect to it.
if (class_exists('\core_courseformat\activityoverviewbase')) {
    \core_courseformat\activityoverviewbase::redirect_to_overview_page($course->id, 'videotrack');
}

$PAGE->set_url('/mod/videotrack/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->fullname) . ': ' . get_string('modulenameplural', 'mod_videotrack'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_course::instance($course->id));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_videotrack'));

$modinfo = get_fast_modinfo($course);
$instances = $modinfo->get_instances_of('videotrack');

if (empty($instances)) {
    echo $OUTPUT->notification(get_string('noresponses', 'mod_videotrack'), 'info');
    echo $OUTPUT->footer();
    die();
}

$videotracks = $DB->get_records('videotrack', ['course' => $course->id]);

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('targetpercent', 'mod_videotrack'),
    get_string('report', 'mod_videotrack'),
];
$table->align = ['left', 'center', 'center'];

$hasanyreports = false;

foreach ($instances as $cm) {
    if (!$cm->uservisible) {
        continue;
    }

    $instanceid = $cm->instance;
    if (!isset($videotracks[$instanceid])) {
        continue;
    }
    $videotrack = $videotracks[$instanceid];

    // Name link.
    $namelink = html_writer::link($cm->url, $cm->get_formatted_name());

    // Target percent text.
    if ($videotrack->targetpercent <= 0) {
        $percenttext = get_string('progressfree', 'mod_videotrack');
    } else {
        $percenttext = $videotrack->targetpercent . '%';
    }

    // Report link if the user has capability.
    $reportlink = '-';
    $context = context_module::instance($cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
        $reporturl = new moodle_url('/mod/videotrack/report.php', ['id' => $cm->id]);
        $reportlink = html_writer::link(
            $reporturl,
            get_string('report', 'mod_videotrack'),
            ['class' => 'btn btn-sm btn-secondary']
        );
        $hasanyreports = true;
    }

    $table->data[] = [
        $namelink,
        $percenttext,
        $reportlink,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('noresponses', 'mod_videotrack'), 'info');
} else {
    // If no reports are accessible to the current user, remove the column.
    if (!$hasanyreports) {
        array_pop($table->head);
        foreach ($table->data as $key => $row) {
            array_pop($table->data[$key]);
        }
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
