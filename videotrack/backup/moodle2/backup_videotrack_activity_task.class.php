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

require_once($CFG->dirroot . '/mod/videotrack/backup/moodle2/backup_videotrack_stepslib.php');

class backup_videotrack_activity_task extends backup_activity_task {

    protected function define_my_settings() {
        // No custom settings.
    }

    protected function define_my_steps() {
        $this->add_step(new backup_videotrack_activity_structure_step('videotrack_structure', 'videotrack.xml'));
    }

    static public function get_encode_contenthash($content) {
        return self::encode_contenthash($content);
    }

    /**
     * Codificar los enlaces internos de la actividad para que sean transportables.
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Enlace al listado de actividades VideoTrack.
        $search = "/(" . $base . "\/mod\/videotrack\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@VIDEOTRACKINDEX*$2@$', $content);

        // Enlace a la vista individual de un VideoTrack.
        $search = "/(" . $base . "\/mod\/videotrack\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@VIDEOTRACKVIEWBYID*$2@$', $content);

        return $content;
    }

    public function get_fileareas() {
        return ['intro', 'video'];
    }

    public function get_configdata_attributes() {
        return [];
    }
}
