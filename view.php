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
 * @copyright   2026 EduPlugins Studio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$inpopup = optional_param('inpopup', 0, PARAM_BOOL);
$embed = optional_param('embed', 0, PARAM_BOOL);
$isembed = ($inpopup || $embed);

$cm = get_coursemodule_from_id('videotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videotrack:view', $context);

$PAGE->set_cm($cm, $course, $videotrack);
$PAGE->set_url('/mod/videotrack/view.php', [
    'id' => $cm->id,
    'embed' => (int)$embed,
    'inpopup' => (int)$inpopup,
]);
$PAGE->set_title(format_string($videotrack->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/mod/videotrack/styles.css'));

if ($isembed) {
    $PAGE->set_pagelayout('embedded');
    if (isset($PAGE->activityheader)) {
        $PAGE->activityheader->disable();
    }
}

// Trigger Moodle course_module_viewed event for reports and external plugins (e.g. Level Up XP).
$eventclass = 'mod_videotrack' . chr(92) . 'event' . chr(92) . 'course_module_viewed';
$event = $eventclass::create([
    'objectid' => $videotrack->id,
    'context' => $context,
    'courseid' => $course->id,
    'other' => [
        'instanceid' => $videotrack->id,
        'cmid' => $cm->id,
    ],
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('videotrack', $videotrack);
$event->trigger();

$videourl = trim($videotrack->videourl ?? '');
$videourl = html_entity_decode($videourl);
$isyoutube = false;
$ytid = '';
$isgdrive = (strpos($videourl, 'drive.google.com') !== false);

if (!empty($videourl)) {
    if (stripos($videourl, 'youtube.com') !== false || stripos($videourl, 'youtu.be') !== false) {
        $isyoutube = true;
        $parsed = parse_url($videourl);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        if ($query) {
            parse_str($query, $params);
            if (isset($params['v'])) {
                $ytid = $params['v'];
            }
        }

        if (empty($ytid) && !empty($path)) {
            $pathparts = explode('/', trim($path, '/'));
            foreach ($pathparts as $index => $part) {
                if (in_array(strtolower($part), ['shorts', 'embed', 'v', 'live'])) {
                    if (isset($pathparts[$index + 1])) {
                        $ytid = $pathparts[$index + 1];
                        break;
                    }
                }
            }
        }

        if (empty($ytid) && stripos($videourl, 'youtu.be') !== false) {
            $ytid = trim($path, '/');
        }

        if (!empty($ytid)) {
            $ytid = explode('?', $ytid)[0];
            $ytid = explode('&', $ytid)[0];
            $ytid = substr($ytid, 0, 11);
        }

        if (strlen($ytid) !== 11) {
            $isyoutube = false;
            $ytid = '';
        }
    }
}

$fs = get_file_storage();

if (empty($videourl) || (!$isyoutube && strpos($videourl, 'http') === false)) {
    $files = $fs->get_area_files($context->id, 'mod_videotrack', 'video', 0, 'sortorder', false);
    foreach ($files as $f) {
        if (!$f->is_directory() && strpos($f->get_mimetype(), 'video/') === 0) {
            $videourl = moodle_url::make_pluginfile_url(
                $f->get_contextid(),
                $f->get_component(),
                $f->get_filearea(),
                $f->get_itemid(),
                $f->get_filepath(),
                $f->get_filename()
            )->out();
            $isyoutube = false;
            break;
        }
    }
}

$captionsurl = '';
$captionfiles = $fs->get_area_files($context->id, 'mod_videotrack', 'captions', 0, 'sortorder', false);
foreach ($captionfiles as $cf) {
    if (!$cf->is_directory()) {
        $captionsurl = moodle_url::make_pluginfile_url(
            $cf->get_contextid(),
            $cf->get_component(),
            $cf->get_filearea(),
            $cf->get_itemid(),
            $cf->get_filepath(),
            $cf->get_filename()
        )->out();
        break;
    }
}

$now = time();
$progress = $DB->get_record('videotrack_progress', ['videotrackid' => $videotrack->id, 'userid' => $USER->id]);
if (!$progress && !$isgdrive) {
    $progress = new stdClass();
    $progress->videotrackid = $videotrack->id;
    $progress->userid = $USER->id;
    $progress->highestpercent = 0;
    $progress->highesttime = 0;
    $progress->iscompleted = 0;
    $progress->timecreated = $now;
    $progress->timemodified = $now;
    $progress->id = $DB->insert_record('videotrack_progress', $progress);
}

$currentpercent = $progress ? (int)$progress->highestpercent : 0;
$highesttime = ($progress && isset($progress->highesttime)) ? (int)$progress->highesttime : 0;
$iscompleted = $progress ? (bool)$progress->iscompleted : false;

$ismanualtrack = !empty($videotrack->isgdrive);
$manualtime = (int)($videotrack->manualtime ?? 0);
$focusmode = !empty($videotrack->focusmode);

$rawaccent = videotrack_sanitize_accentcolor($videotrack->accentcolor ?? '');
$hascustomaccent = ($rawaccent !== '' && $rawaccent !== '#0ea5e9');
$accentcolor = $hascustomaccent ? $rawaccent : '#0ea5e9';

if ($isgdrive && !$ismanualtrack && !$iscompleted) {
    if (!$progress) {
        $progress = new stdClass();
        $progress->videotrackid = $videotrack->id;
        $progress->userid = $USER->id;
        $progress->highesttime = 0;
        $progress->highestpercent = 100;
        $progress->iscompleted = 1;
        $progress->timecreated = $now;
        $progress->timemodified = $now;
        $DB->insert_record('videotrack_progress', $progress);
    } else {
        $progress->highestpercent = 100;
        $progress->iscompleted = 1;
        $progress->timemodified = $now;
        $DB->update_record('videotrack_progress', $progress);
    }

    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    }

    $iscompleted = true;
    $currentpercent = 100;
}

$isfree = ($videotrack->targetpercent <= 0);

$completion = new completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
    if ($iscompleted || $isfree) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    }
}

$formattedtime = ($highesttime >= 3600)
    ? sprintf("%02d:%02d:%02d", intdiv($highesttime, 3600), intdiv($highesttime % 3600, 60), $highesttime % 60)
    : sprintf("%02d:%02d", intdiv($highesttime, 60), $highesttime % 60);

// Teacher capability check: only teachers/admins with editing rights can see Settings and Report shortcuts.
$canmanage = has_capability('moodle/course:manageactivities', $context);
$settingsurl = $canmanage ? (new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]))->out(false) : '';
$reporturl = $canmanage ? (new moodle_url('/mod/videotrack/report.php', ['id' => $cm->id]))->out(false) : '';

// Hide the "Back to course" button when rendered embedded or inside the course modal popup.
$courseurl = $isembed ? '' : (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

$templatecontext = [
    'cmid' => $cm->id,
    'activityname' => format_string($videotrack->name),
    'isembed' => (bool)$isembed,
    'inpopup' => (bool)$inpopup,
    'courseurl' => $courseurl,
    'canmanage' => $canmanage,
    'settingsurl' => $settingsurl,
    'reporturl' => $reporturl,
    'settingslabel' => get_string('settings'),
    'reportlabel' => get_string('report', 'mod_videotrack'),
    'backtocoursetext' => get_string('backtocourse', 'mod_videotrack'),
    'videourl' => $videourl,
    'captionsurl' => $captionsurl,
    'hascaptions' => !empty($captionsurl),
    'isyoutube' => $isyoutube,
    'isgdrive' => $isgdrive,
    'focusmode' => $focusmode,
    'focusmodebadge' => get_string('focusmode_badge', 'mod_videotrack'),
    'focusmodepaused' => get_string('focusmode_paused', 'mod_videotrack'),
    'accentcolor' => $accentcolor,
    'hascustomaccent' => $hascustomaccent,
    'showprogressbar' => (!$isgdrive || $ismanualtrack),
    'ytid' => $ytid,
    'targetpercent' => $videotrack->targetpercent,
    'currentpercent' => $currentpercent,
    'highesttime' => $highesttime,
    'iscompleted' => $iscompleted,
    'isfree' => $isfree,
    'showresumebutton' => ($currentpercent > 0 && $currentpercent < 100),
    'progresstitle' => get_string('progresstitle', 'mod_videotrack'),
    'progressfree' => $isfree,
    'progresshint' => $isfree
        ? get_string('progressfree', 'mod_videotrack')
        : get_string('progresshint', 'mod_videotrack', $videotrack->targetpercent),
    'successmsg' => get_string('successmsg', 'mod_videotrack'),
    'resumebtntext' => get_string('resumebutton', 'mod_videotrack', $formattedtime),
    'manualresumehint' => get_string('manualresumehint', 'mod_videotrack', $formattedtime),
    'gdrivenotrack' => get_string('gdrivenotrack', 'mod_videotrack'),
];

if (!$isgdrive || $ismanualtrack || $focusmode) {
    $PAGE->requires->js_call_amd('mod_videotrack/tracker', 'init', [
        $cm->id,
        $videotrack->targetpercent,
        $isyoutube,
        $ytid,
        $currentpercent,
        $highesttime,
        $ismanualtrack,
        $manualtime,
        $focusmode,
    ]);
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_videotrack/player', $templatecontext);

echo $OUTPUT->footer();
