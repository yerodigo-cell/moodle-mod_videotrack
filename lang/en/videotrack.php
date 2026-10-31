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

$string['accentcolor'] = 'Accent colour';
$string['accentcolor_help'] = 'Enter a hex colour code (for example #0f6cbf) to customize the progress bar and player highlights. Leave empty or keep #0ea5e9 to use the default sky-blue style.';
$string['backtocourse'] = 'Back to course';
$string['captionsfile'] = 'Captions (VTT or SRT file)';
$string['captionsfile_help'] = 'Upload a WebVTT (.vtt) or SubRip (.srt) file to display subtitles or captions on the video player.';
$string['completed'] = 'Completed';
$string['completiondetail:targetpercent'] = 'Watch at least {$a}% of the video';
$string['completionwatch'] = 'Student must watch at least';
$string['displayandplayback'] = 'Display and playback';
$string['displaymode'] = 'Course page display';
$string['displaymode_help'] = 'Choose how the video activity is presented on the course page: as a standard activity link, as a summary card that opens the player in a popup modal, or with the video player embedded directly on the course page.';
$string['displaymode_inline'] = 'Player embedded directly on course page';
$string['displaymode_page'] = 'Standard activity page';
$string['displaymode_popup'] = 'Card on course page (opens player in popup)';
$string['error_invalidcolor'] = 'Please enter a valid hex colour code (e.g. #0f6cbf).';
$string['error_invalidpercent'] = 'Please enter a valid percentage between 1 and 100.';
$string['error_nouploadorurl'] = 'You must either provide a Video URL or upload a Video File.';
$string['eventcoursemoduleviewed'] = 'VideoTrack course module viewed';
$string['focusmode'] = 'Focus mode';
$string['focusmode_badge'] = 'Focus Mode';
$string['focusmode_desc'] = 'Automatically pause the video when switching browser tabs or windows (for Google Drive videos, this pauses the progress bar instead of the video player)';
$string['focusmode_help'] = 'When enabled, the video will automatically pause if the student switches to another browser tab or application window, displaying a Focus Mode indicator in the top-right header of the card. Note: Due to Google Drive player restrictions, for Google Drive videos this mode pauses the progress bar timer rather than the video itself.';
$string['focusmode_paused'] = 'Paused — Keep this window active';
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
$string['privacy:metadata:videotrack_progress'] = 'Stores the video playback progress and completion status for each user.';
$string['privacy:metadata:videotrack_progress:highestpercent'] = 'The highest percentage of the video the user has watched.';
$string['privacy:metadata:videotrack_progress:highesttime'] = 'The highest playback time in seconds reached by the user.';
$string['privacy:metadata:videotrack_progress:iscompleted'] = 'Whether the user has completed the required target percent.';
$string['privacy:metadata:videotrack_progress:timecreated'] = 'The time the progress record was created.';
$string['privacy:metadata:videotrack_progress:timemodified'] = 'The time the progress record was last modified.';
$string['privacy:metadata:videotrack_progress:userid'] = 'The ID of the user.';
$string['privacy:metadata:videotrack_reactions'] = 'Information about user reactions during video playback.';
$string['privacy:metadata:videotrack_reactions:reaction'] = 'The reaction type submitted by the user.';
$string['privacy:metadata:videotrack_reactions:timecreated'] = 'The timestamp when the reaction was recorded.';
$string['privacy:metadata:videotrack_reactions:timeoffset'] = 'The video timestamp in seconds where the reaction occurred.';
$string['privacy:metadata:videotrack_reactions:userid'] = 'The ID of the user who submitted the reaction.';
$string['progressfree'] = 'This video is for free exploration. You can watch it and skip ahead at your own pace.';
$string['progresshint'] = 'You must watch at least <strong>{$a}%</strong> of the video to complete this activity.';
$string['progresstitle'] = 'Viewing progress';
$string['report'] = 'Progress Report';
$string['resumebutton'] = 'Resume from {$a}';
$string['student'] = 'Student';
$string['successmsg'] = 'Congratulations! You have reached the required percentage. You may now continue.';
$string['targetpercent'] = 'Required percentage (%)';
$string['targetpercent_help'] = 'The percentage of the video the student must watch to complete the activity (default is 80%). Uncheck this condition if you want the video to be free and allow fast-forwarding without restrictions.';
$string['videofile'] = 'Video File (Local)';
$string['videofile_help'] = 'Upload your MP4 video file here. Note: If you enter an external URL above, it will be prioritized over this file.';
$string['videotrack:addinstance'] = 'Add a new VideoTrack';
$string['videotrack:view'] = 'View VideoTrack';
$string['videotrack:viewreport'] = 'View progress report';
$string['videourl'] = 'Video URL (External)';
$string['videourl_help'] = 'Paste the YouTube link or a direct MP4 URL here. If you prefer to upload a file directly to Moodle, leave this blank and use the file uploader below.';
$string['watchvideo'] = 'Watch video';
