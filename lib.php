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



/**
 * Add a new instance of the videotrack activity.
 *
 * @param stdClass $videotrack The activity instance object.
 * @param mod_videotrack_mod_form|null $mform The form.
 * @return int The ID of the new instance.
 */
function videotrack_add_instance($videotrack, $mform = null) {
    global $DB;
    $videotrack->timecreated = time();
    $videotrack->timemodified = $videotrack->timecreated;

    $id = $DB->insert_record('videotrack', $videotrack);

    if (isset($videotrack->video)) {
        // In add_instance $videotrack->coursemodule is the ID of the newly created cm.
        $context = context_module::instance($videotrack->coursemodule);
        file_save_draft_area_files(
            $videotrack->video,
            $context->id,
            'mod_videotrack',
            'video',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }
    return $id;
}

/**
 * Update an existing instance of the videotrack activity.
 *
 * @param stdClass $videotrack The activity instance object.
 * @param mod_videotrack_mod_form|null $mform The form.
 * @return bool True on success.
 */
function videotrack_update_instance($videotrack, $mform = null) {
    global $DB;
    $videotrack->timemodified = time();
    $videotrack->id = $videotrack->instance;

    $DB->update_record('videotrack', $videotrack);

    if (isset($videotrack->video)) {
        $context = context_module::instance($videotrack->coursemodule);
        file_save_draft_area_files(
            $videotrack->video,
            $context->id,
            'mod_videotrack',
            'video',
            0,
            ['subdirs' => 0, 'maxfiles' => 1]
        );
    }
    return true;
}

/**
 * Delete an instance of the videotrack activity.
 *
 * @param int $id The ID of the instance.
 * @return bool True on success.
 */
function videotrack_delete_instance($id) {
    global $DB;
    if (!$videotrack = $DB->get_record('videotrack', ['id' => $id])) {
        // If the record does not exist, we MUST return true.
        // Returning false would cause Moodle to get stuck in an infinite deletion loop.
        return true;
    }

    $DB->delete_records('videotrack_progress', ['videotrackid' => $videotrack->id]);

    // Get the CM and delete files BEFORE deleting the videotrack record.
    $cm = get_coursemodule_from_instance('videotrack', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_videotrack', 'video');
    }

    $DB->delete_records('videotrack', ['id' => $videotrack->id]);
    return true;
}

/**
 * Serves the files from the videotrack file areas.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Options.
 * @return bool False if file not found, otherwise does not return.
 */
function mod_videotrack_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, true, $cm);
    if ($filearea !== 'video') {
        return false;
    }

    $itemid = (int)array_shift($args);

    if (empty($args)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = empty($args) ? '/' : '/' . implode('/', $args) . '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_videotrack', $filearea, $itemid, $filepath, $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Indicates API features that the videotrack supports.
 *
 * @param string $feature The feature to check.
 * @return mixed True if supported, null if unknown.
 */
function videotrack_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Extends settings secondary course/activity navigation (tabs) with report.php for teachers.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param navigation_node|null $node The navigation node.
 */
function videotrack_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node = null) {
    global $PAGE;
    $cm = $PAGE->cm;

    // If there is no course module information or the main node does not exist, exit.
    if (!$cm || !$node) {
        return;
    }

    $context = context_module::instance($cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
        $url = new moodle_url('/mod/videotrack/report.php', ['id' => $cm->id]);

        // Add the report node directly to the activity node.
        $reportnode = $node->add(
            get_string('report', 'mod_videotrack'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'videotrackreport',
            new pix_icon('i/report', '')
        );

        $reportnode->showinflatnavigation = true;
    }
}
