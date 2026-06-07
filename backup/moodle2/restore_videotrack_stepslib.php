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
 * Structure step to restore one videotrack activity.
 *
 * @package    mod_videotrack
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_videotrack_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('videotrack', '/activity/videotrack');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the videotrack data.
     *
     * @param array $data The data to process.
     */
    protected function process_videotrack($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert videotrack record.
        $newitemid = $DB->insert_record('videotrack', $data);

        // Map old ID to new ID.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * After execute process.
     */
    protected function after_execute() {
        // Restore files associated with 'intro' and 'video' fileareas.
        $this->add_related_files('mod_videotrack', 'intro', null);
        $this->add_related_files('mod_videotrack', 'video', null);
    }
}
