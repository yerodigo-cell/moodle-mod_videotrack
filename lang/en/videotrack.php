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
defined('MOODLE_INTERNAL') || die();

$string['completed'] = 'Completed';
$string['error_nouploadorurl'] = 'You must either provide a Video URL or upload a Video File.';
$string['eventcoursemoduleviewed'] = 'VideoTrack course module viewed';
$string['gdrivedisclaimer'] = 'For a better experience and accurate tracking, we strongly recommend uploading your video to YouTube or attaching the MP4 file directly to Moodle. Manual tracking for Google Drive is provided only as a fallback alternative.';
$string['gdrivenotrack'] = 'This video is embedded from Google Drive. Progress tracking is disabled for this player.';
$string['highestpercent'] = 'Highest Percent Watched';
$string['isgdrive'] = 'Enable manual time tracking (Google Drive)';
$string['isgdrive_help'] = 'Check this box if you are embedding a Google Drive video or another player that does not support automatic tracking. It will calculate progress based on the time the student stays active on this page.';
$string['lastaccess'] = 'Last Access';
$string['manualresumehint'] = 'Your progress is saved at <strong>{$a}</strong>. Please play the video and manually fast-forward to this point.';
$string['manualtime'] = 'Total video duration (HH:MM:SS)';
$string['manualtime_help'] = 'Enter the total duration of the video in hours, minutes, and seconds. The required time on the page will be calculated based on this and the Required Percentage field.';
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
$string['resumebutton'] = 'Resume from {$a}';
$string['student'] = 'Student';
$string['successmsg'] = 'Congratulations! You have reached the required percentage. You may now continue.';
$string['targetpercent'] = 'Required percentage (%)';
$string['targetpercent_help'] = 'The percentage of the video the student must watch to complete the activity (default is 80%). Enter 0 if you want the video to be free and allow fast-forwarding without restrictions.';
$string['videofile'] = 'Video File (Local)';
$string['videofile_help'] = 'Upload your MP4 video file here. Note: If you enter an external URL above, it will be prioritized over this file.';
$string['videotrack:addinstance'] = 'Add a new VideoTrack';
$string['videotrack:view'] = 'View VideoTrack';
$string['videotrack:viewreport'] = 'View progress report';
$string['videourl'] = 'Video URL (External)';
$string['videourl_help'] = 'Paste the YouTube link or a direct MP4 URL here. If you prefer to upload a file directly to Moodle, leave this blank and use the file uploader below.';
