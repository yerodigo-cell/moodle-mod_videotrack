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


defined('MOODLE_INTERNAL') || die();

class backup_videotrack_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        // XML structure element.
        $videotrack = new backup_nested_element('videotrack', ['id'], [
            'course', 'name', 'intro', 'introformat', 'videourl', 'targetpercent', 'timecreated', 'timemodified'
        ]);

        // Connect database table fields.
        $videotrack->set_source_table('videotrack', ['id' => backup::VAR_ACTIVITYID]);

        // Backup files in 'intro' and 'video' fileareas.
        $videotrack->annotate_files('mod_videotrack', 'intro', null); // Default intro files.
        $videotrack->annotate_files('mod_videotrack', 'video', null); // Local uploaded video files.

        return $this->prepare_activity_structure($videotrack);
    }
}
