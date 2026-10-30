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
        $table = new xmldb_table('videotrack_progress');
        $field = new xmldb_field('highesttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'highestpercent');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026063000, 'videotrack');
    }

    if ($oldversion < 2026081501) {
        $table = new xmldb_table('videotrack_reactions');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('videotrackid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reaction', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeoffset', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('videotrackid', XMLDB_KEY_FOREIGN, ['videotrackid'], 'videotrack', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $table->add_index('video_time', XMLDB_INDEX_NOTUNIQUE, ['videotrackid', 'timeoffset']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026081501, 'videotrack');
    }

    if ($oldversion < 2026100600) {
        $table = new xmldb_table('videotrack');

        $field1 = new xmldb_field('isgdrive', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'targetpercent');
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        $field2 = new xmldb_field('manualtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'isgdrive');
        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        upgrade_mod_savepoint(true, 2026100600, 'videotrack');
    }

    if ($oldversion < 2026102701) {
        $table = new xmldb_table('videotrack');
        $field = new xmldb_field('focusmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'manualtime');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026102701, 'videotrack');
    }

    if ($oldversion < 2026102702) {
        $table = new xmldb_table('videotrack');
        $field = new xmldb_field('accentcolor', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'focusmode');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026102702, 'videotrack');
    }

    if ($oldversion < 2026102703) {
        $table = new xmldb_table('videotrack');
        $field = new xmldb_field('displaymode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'accentcolor');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026102703, 'videotrack');
    }

    return true;
}
