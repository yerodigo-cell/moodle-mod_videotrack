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


namespace mod_videotrack\courseformat;

defined('MOODLE_INTERNAL') || die();

use core_courseformat\activityoverviewbase;
use core_courseformat\local\overview\overviewitem;
use core\output\local\properties\text_align;

class overview extends activityoverviewbase {

    /**
     * Get the extra overview items for the activity.
     *
     * @return overviewitem[]
     */
    public function get_extra_overview_items(): array {
        global $DB;

        $items = [];

        // Get the videotrack record for this instance.
        $videotrack = $DB->get_record('videotrack', ['id' => $this->cm->instance]);
        if (!$videotrack) {
            return $items;
        }

        // 1. Target percent item.
        if ($videotrack->targetpercent <= 0) {
            $percenttext = get_string('progressfree', 'mod_videotrack');
        } else {
            $percenttext = $videotrack->targetpercent . '%';
        }

        $items['targetpercent'] = new overviewitem(
            get_string('targetpercent', 'mod_videotrack'),
            $videotrack->targetpercent,
            $percenttext,
            text_align::CENTER
        );

        // 2. Report link item (only for teachers/managers with the capability).
        $context = \context_module::instance($this->cm->id);
        if (has_capability('moodle/course:manageactivities', $context)) {
            $reporturl = new \moodle_url('/mod/videotrack/report.php', ['id' => $this->cm->id]);
            $reportlink = \html_writer::link(
                $reporturl,
                get_string('report', 'mod_videotrack'),
                ['class' => 'btn btn-sm btn-secondary']
            );

            $items['report'] = new overviewitem(
                get_string('report', 'mod_videotrack'),
                'report',
                $reportlink,
                text_align::CENTER
            );
        }

        return $items;
    }
}
