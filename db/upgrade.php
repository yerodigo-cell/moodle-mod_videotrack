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
 * Upgrade script for mod_videotrack.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_videotrack_upgrade($oldversion): bool {
    global $CFG, $DB;

    if ($oldversion < 2026063000) {

        // Define field highesttime to be added to videotrack_progress.
        $table = new xmldb_table('videotrack_progress');
        $field = new xmldb_field('highesttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'highestpercent');

        $dbman = $DB->get_manager();
        
        // Conditionally launch add field highesttime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Videotrack savepoint reached.
        upgrade_mod_savepoint(true, 2026063000, 'videotrack');
    }

    return true;
}
