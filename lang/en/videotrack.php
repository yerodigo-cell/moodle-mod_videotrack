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

$string['completed'] = 'Completed';
$string['error_nouploadorurl'] = 'You must either provide a Video URL or upload a Video File.';
$string['eventcoursemoduleviewed'] = 'VideoTrack course module viewed';
$string['highestpercent'] = 'Highest Percent Watched';
$string['lastaccess'] = 'Last Access';
$string['modulename'] = 'VideoTrack';
$string['modulename_help'] = 'The VideoTrack activity allows you to embed a video and require the student to watch a specific percentage.';
$string['modulenameplural'] = 'VideoTracks';
$string['noresponses'] = 'No progress recorded yet for this video.';
$string['pluginadministration'] = 'VideoTrack administration';
$string['pluginname'] = 'VideoTrack';
$string['privacy:metadata:videotrack_progress'] = 'Stores the user\'s video playback progress and completion status.';
$string['privacy:metadata:videotrack_progress:highestpercent'] = 'The highest percentage of the video the user has watched.';
$string['privacy:metadata:videotrack_progress:iscompleted'] = 'Whether the user has completed the required target percent.';
$string['privacy:metadata:videotrack_progress:timecreated'] = 'The time the progress record was created.';
$string['privacy:metadata:videotrack_progress:timemodified'] = 'The time the progress record was last modified.';
$string['privacy:metadata:videotrack_progress:userid'] = 'The ID of the user.';
$string['progressfree'] = 'This video is for free exploration. You can watch it and skip ahead at your own pace.';
$string['progresshint'] = 'You must watch at least <strong>{$a}%</strong> of the video to complete this activity.';
$string['progresstitle'] = 'Viewing progress';
$string['report'] = 'Progress Report';
$string['student'] = 'Student';
$string['successmsg'] = 'Congratulations! You have reached the required percentage. You may now continue.';
$string['targetpercent'] = 'Required percentage (%)';
$string['targetpercent_help'] = 'The percentage of the video the student must watch to complete the activity (default is 80%). Enter 0 if you want the video to be free and allow fast-forwarding without restrictions.';
$string['videofile'] = 'Video File (Local)';
$string['videofile_help'] = 'Upload your MP4 video file here. Note: If you enter an external URL above, it will be prioritized over this file.';
$string['videotrack:addinstance'] = 'Add a new VideoTrack';
$string['videourl'] = 'Video URL (External)';
$string['videourl_help'] = 'Paste the YouTube link or a direct MP4 URL here. If you prefer to upload a file directly to Moodle, leave this blank and use the file uploader below.';
$string['videotrack:view'] = 'View VideoTrack';
