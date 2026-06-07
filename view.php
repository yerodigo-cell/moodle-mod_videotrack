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
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('videotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/videotrack/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($videotrack->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Disparar evento de Moodle course_module_viewed para reportes y plugins externos (ej. Level Up XP).
$event = \mod_videotrack\event\course_module_viewed::create([
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

// 3. Process video URL and check if it is a YouTube video (including Shorts).
$videourl = trim($videotrack->videourl ?? '');
$videourl = html_entity_decode($videourl);
$isyoutube = false;
$ytid = '';

if (!empty($videourl)) {
    if (stripos($videourl, 'youtube.com') !== false || stripos($videourl, 'youtu.be') !== false) {
        $isyoutube = true;
        $parsed = parse_url($videourl);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        // Método 1: Estándar (watch?v=ID).
        if ($query) {
            parse_str($query, $params);
            if (isset($params['v'])) {
                $ytid = $params['v'];
            }
        }

        // Método 2: Shorts, Embed o Live (youtube.com/shorts/ID).
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

        // Método 3: Enlace corto (youtu.be/ID).
        if (empty($ytid) && stripos($videourl, 'youtu.be') !== false) {
            $ytid = trim($path, '/');
        }

        // Limpiar el ID eliminando parámetros de consulta (?si=..., etc.).
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

if (empty($videourl) || (!$isyoutube && strpos($videourl, 'http') === false)) {
    $fs = get_file_storage();
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

// 4. Buscar el progreso actual
$progress = $DB->get_record('videotrack_progress', ['videotrackid' => $videotrack->id, 'userid' => $USER->id]);
$currentpercent = $progress ? (int)$progress->highestpercent : 0;
$iscompleted = $progress ? (bool)$progress->iscompleted : false;

$isfree = ($videotrack->targetpercent <= 0);

if ($iscompleted || $isfree) {
    $completion = new completion_info($course);
    if ($completion->is_enabled($cm)) {
        $completion->set_module_viewed($cm);
        if ($isfree) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }
    }
}

$templatecontext = [
    'videourl' => $videourl,
    'isyoutube' => $isyoutube,
    'ytid' => $ytid,
    'targetpercent' => $videotrack->targetpercent,
    'currentpercent' => $currentpercent,
    'iscompleted' => $iscompleted,
    'isfree' => $isfree,
    'progresstitle' => get_string('progresstitle', 'mod_videotrack'),
    'progresshint' => $isfree
        ? get_string('progressfree', 'mod_videotrack')
        : get_string('progresshint', 'mod_videotrack', $videotrack->targetpercent),
    'successmsg' => get_string('successmsg', 'mod_videotrack'),
];

$PAGE->requires->js_call_amd('mod_videotrack/tracker', 'init', [
    $cm->id,
    $videotrack->targetpercent,
    $isyoutube,
    $ytid,
    $currentpercent,
]);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('mod_videotrack/player', $templatecontext);

echo $OUTPUT->footer();
