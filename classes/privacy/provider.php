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
 * Privacy API provider for mod_videotrack.
 *
 * @package     mod_videotrack
 * @copyright   2026 EduPlugins Studio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * @package    mod_videotrack
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('videotrack_progress', [
            'userid' => 'privacy:metadata:videotrack_progress:userid',
            'highestpercent' => 'privacy:metadata:videotrack_progress:highestpercent',
            'highesttime' => 'privacy:metadata:videotrack_progress:highesttime',
            'iscompleted' => 'privacy:metadata:videotrack_progress:iscompleted',
            'timecreated' => 'privacy:metadata:videotrack_progress:timecreated',
            'timemodified' => 'privacy:metadata:videotrack_progress:timemodified',
        ], 'privacy:metadata:videotrack_progress');

        // Closes mod_videotrack/VTRK-PRIV-001: declare user reactions metadata.
        $collection->add_database_table('videotrack_reactions', [
            'userid' => 'privacy:metadata:videotrack_reactions:userid',
            'reaction' => 'privacy:metadata:videotrack_reactions:reaction',
            'timeoffset' => 'privacy:metadata:videotrack_reactions:timeoffset',
            'timecreated' => 'privacy:metadata:videotrack_reactions:timecreated',
        ], 'privacy:metadata:videotrack_reactions');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sqlprogress = "SELECT c.id
                          FROM {context} c
                          JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                          JOIN {videotrack} v ON v.id = cm.instance
                          JOIN {videotrack_progress} vp ON vp.videotrackid = v.id
                         WHERE vp.userid = :userid";

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videotrack',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sqlprogress, $params);

        // Include contexts where the user has recorded video reactions (VTRK-PRIV-001).
        $sqlreactions = "SELECT c.id
                           FROM {context} c
                           JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                           JOIN {videotrack} v ON v.id = cm.instance
                           JOIN {videotrack_reactions} vr ON vr.videotrackid = v.id
                          WHERE vr.userid = :userid";

        $contextlist->add_from_sql($sqlreactions, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'modname' => 'videotrack',
            'cmid' => $context->instanceid,
        ];

        $sqlprogress = "SELECT vp.userid
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                          JOIN {videotrack} v ON v.id = cm.instance
                          JOIN {videotrack_progress} vp ON vp.videotrackid = v.id
                         WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sqlprogress, $params);

        // Include users with reactions in this activity context (VTRK-PRIV-001).
        $sqlreactions = "SELECT vr.userid
                           FROM {course_modules} cm
                           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                           JOIN {videotrack} v ON v.id = cm.instance
                           JOIN {videotrack_reactions} vr ON vr.videotrackid = v.id
                          WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sqlreactions, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'videotrack',
            'userid' => $userid,
        ] + $contextparams;

        // 1. Export progress records.
        $sqlprogress = "SELECT cm.id AS cmid, v.name, vp.highestpercent, vp.highesttime,
                               vp.iscompleted, vp.timecreated, vp.timemodified
                          FROM {context} c
                          JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                          JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                          JOIN {videotrack} v ON v.id = cm.instance
                          JOIN {videotrack_progress} vp ON vp.videotrackid = v.id
                         WHERE c.id $contextsql AND vp.userid = :userid";

        $records = $DB->get_records_sql($sqlprogress, $params);

        foreach ($records as $record) {
            $context = \context_module::instance($record->cmid);
            $data = (object) [
                'highestpercent' => $record->highestpercent,
                'highesttime' => $record->highesttime,
                'iscompleted' => $record->iscompleted,
                'timecreated' => \core_privacy\local\request\transform::datetime($record->timecreated),
                'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
            ];

            writer::with_context($context)->export_data([get_string('progresstitle', 'mod_videotrack')], $data);
        }

        // 2. Export reaction records (VTRK-PRIV-001).
        $sqlreactions = "SELECT vr.id, cm.id AS cmid, vr.reaction, vr.timeoffset, vr.timecreated
                           FROM {context} c
                           JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                           JOIN {videotrack} v ON v.id = cm.instance
                           JOIN {videotrack_reactions} vr ON vr.videotrackid = v.id
                          WHERE c.id $contextsql AND vr.userid = :userid
                       ORDER BY vr.id ASC";

        $reactions = $DB->get_records_sql($sqlreactions, $params);
        $reactionsbycm = [];

        foreach ($reactions as $reaction) {
            $reactionsbycm[$reaction->cmid][] = (object) [
                'reaction' => $reaction->reaction,
                'timeoffset' => (int) $reaction->timeoffset,
                'timecreated' => \core_privacy\local\request\transform::datetime($reaction->timecreated),
            ];
        }

        foreach ($reactionsbycm as $cmid => $items) {
            $context = \context_module::instance($cmid);
            writer::with_context($context)->export_data(
                ['reactions'],
                (object) ['reactions' => $items]
            );
        }
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videotrack', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('videotrack_progress', ['videotrackid' => $cm->instance]);
        $DB->delete_records('videotrack_reactions', ['videotrackid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('videotrack', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $DB->delete_records('videotrack_progress', [
                'videotrackid' => $cm->instance,
                'userid' => $userid,
            ]);
            $DB->delete_records('videotrack_reactions', [
                'videotrackid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('videotrack', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $params = ['videotrackid' => $cm->instance] + $userparams;

        $DB->delete_records_select('videotrack_progress', "videotrackid = :videotrackid AND userid $usersql", $params);
        $DB->delete_records_select('videotrack_reactions', "videotrackid = :videotrackid AND userid $usersql", $params);
    }
}
