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


/**
 * Upgrade script for mod_videotrack.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool
 */
function xmldb_videotrack_upgrade($oldversion): bool {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026063000) {
        // Define field highesttime to be added to videotrack_progress.
        $table = new xmldb_table('videotrack_progress');
        $field = new xmldb_field('highesttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'highestpercent');

        // Conditionally launch add field highesttime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Videotrack savepoint reached.
        upgrade_mod_savepoint(true, 2026063000, 'videotrack');
    }

    if ($oldversion < 2026081501) {
        // Define table videotrack_reactions to be created.
        $table = new xmldb_table('videotrack_reactions');

        // Adding fields to table videotrack_reactions.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('videotrackid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reaction', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeoffset', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table videotrack_reactions.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('videotrackid', XMLDB_KEY_FOREIGN, ['videotrackid'], 'videotrack', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table videotrack_reactions.
        $table->add_index('video_time', XMLDB_INDEX_NOTUNIQUE, ['videotrackid', 'timeoffset']);

        // Conditionally launch create table for videotrack_reactions.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Videotrack savepoint reached.
        upgrade_mod_savepoint(true, 2026081501, 'videotrack');
    }

    return true;
}
