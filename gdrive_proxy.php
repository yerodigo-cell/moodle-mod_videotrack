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
 * Proxy script to securely stream Google Drive videos bypassing browser restrictions.
 *
 * @package     mod_videotrack
 * @copyright   2026 EduPlugins Studio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$fileid = required_param('id', PARAM_ALPHANUMEXT);

// Verify user has access to the course module.
$cm = get_coursemodule_from_id('videotrack', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videotrack:view', $context);

// 1. Resolve the final Google Drive direct URL (bypasses virus scan prompts).
// We cache this in the session because fetching the UUID takes 1-2 seconds.
// If we don't cache it, every single video chunk request (seeking, buffering) will
// add seconds of delay, causing the video to take a long time to start.
if (!isset($SESSION->videotrack_gdrive_urls)) {
    $SESSION->videotrack_gdrive_urls = [];
}
$now = time();
$cached = $SESSION->videotrack_gdrive_urls[$fileid] ?? null;
$baseurl = 'https://drive.google.com/uc?export=download&id=' . $fileid;

if (!$cached || ($now - $cached->time > 3600)) {
    $SESSION->videotrack_gdrive_urls[$fileid] = (object)[
        'time' => $now,
        'url' => videotrack_get_final_video_url($baseurl),
    ];
}
$finalurl = $SESSION->videotrack_gdrive_urls[$fileid]->url;

// Close session to prevent locking the user's Moodle navigation while streaming a large video.
\core\session\manager::write_close();

// Prevent script from timing out during a long video stream.
set_time_limit(0);

// Flush and disable all output buffers so the stream goes directly to the client.
// This prevents PHP from loading the entire 490MB video into RAM and crashing.
while (ob_get_level()) {
    ob_end_clean();
}

// 2. Setup cURL for streaming.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $finalurl);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for the cURL transfer.

// Explicitly stream chunks and flush to avoid any memory buildup.
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
    echo $data;
    flush();
    return strlen($data);
});

// 3. Forward the Range header if the browser requested it (crucial for video seeking).
$headers = [];
if (isset($_SERVER['HTTP_RANGE'])) {
    $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// 4. Handle headers from Google Drive to send them back to the browser.
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) {
    $len = strlen($header);
    $headerlower = strtolower($header);

    // We only forward safe, media-related headers.
    if (
        strpos($headerlower, 'content-type:') === 0 ||
        strpos($headerlower, 'content-length:') === 0 ||
        strpos($headerlower, 'content-range:') === 0 ||
        strpos($headerlower, 'accept-ranges:') === 0
    ) {
        header(trim($header));
    }

    // Forward the HTTP status code (200 OK or 206 Partial Content).
    if (preg_match('/^HTTP\/(1\.0|1\.1|2)\s+(200|206)\s/i', $header, $matches)) {
        http_response_code(intval($matches[2]));
    }

    return $len;
});

// 5. Execute stream and exit.
curl_exec($ch);
curl_close($ch);
die();
