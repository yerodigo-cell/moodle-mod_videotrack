# VideoTrack (`mod_videotrack`)

**VideoTrack** is a modern Moodle activity module designed to embed educational videos and accurately track students' viewing progress. It allows teachers to condition activity completion on students watching a minimum percentage of the video, prevent fast-forwarding, enforce window focus, and display the player as a standard page, an interactive popup card, or an inline player directly on the course page.



## Key Features

* **Flexible Video Sources:** Supports local MP4/video file uploads as well as external links from **YouTube**, **YouTube Shorts**, **HeyGen** (strict HTTPS host resolution), direct MP4/WebM URLs, and **Google Drive** (with optional manual duration tracking).
* **Three Course Page Display Modes:**
  1. **Standard activity page:** Opens the video player on its own dedicated activity page.
  2. **Card on course page (Popup modal):** Renders a modern video thumbnail card with live progress bar directly on the course page (compatible with standard course formats and `format_tiles`) that opens the player in a responsive modal popup.
  3. **Player embedded directly on course page:** Embeds the full interactive player inline on the course page with automatic height synchronization and quick teacher shortcuts for Settings and Progress Report.
* **Focus Mode (Auto-Pause on Tab/Window Switch):** When enabled, automatically pauses video playback whenever the student switches browser tabs or application windows, displaying a live status indicator in the card header.
* **Playback Control (No Forwarding):** Prevents students from skipping ahead past their furthest watched timestamp when a required completion percentage is active.
* **Smart Streaming-Style Resume:** Remembers each student's exact playback position (`highesttime` and `highestpercent`) and displays a *"Resume from MM:SS"* overlay across sessions.
* **Unified Subtitles & Captions (`CC`):** Built-in support for uploading **WebVTT (`.vtt`)** and **SubRip (`.srt`)** subtitle files, rendered uniformly across Chrome, Safari, Firefox, Edge, and mobile browsers.
* **Interactive Emoji Reactions:** Students can react in real time using a floating emoji bar, placing visual reaction markers along the video timeline.
* **Custom Accent Colour:** Teachers can customize the hex accent colour (default `#0ea5e9`) to match their institution or course branding across the progress bar, seek bar, buttons, and report badges.
* **Server-Side Security & GDPR Privacy Compliance:**
  * Server-side range and wall-clock plausibility checks in `mod_videotrack_save_progress`.
  * Strict HTTPS host allowlist (`app.heygen.com`) with redirects disabled for external metadata extraction.
  * Full Moodle Privacy API coverage (`core_privacy`) for both `videotrack_progress` and `videotrack_reactions`.

---

## How to Use It

### 1. Adding a VideoTrack Activity
1. Turn on **Edit mode** in your Moodle course.
2. Click **Add an activity or resource** and select **VideoTrack**.
3. Enter an **Activity name** and either paste a **Video URL (External)** or upload a **Video File (Local)**.
4. *(Optional)* Upload a `.vtt` or `.srt` subtitle file under **Captions (VTT or SRT file)**.

### 2. Configuring Display and Playback Options
Under the **Display and playback** section:
1. **Course page display:** Choose between *Standard activity page*, *Card on course page (opens player in popup)*, or *Player embedded directly on course page*.
2. **Accent colour:** Enter any valid hex colour code (e.g., `#0f6cbf` or `#ec4899`), or leave `#0ea5e9` for the default sky-blue style.
3. **Focus mode:** Enable this checkbox if you want the video to automatically pause when the student leaves the active tab or window.

### 3. Setting Up Activity Completion Conditions
1. Expand the **Completion conditions** section in the activity settings.
2. Select **Add requirements** (or *Show activity as complete when conditions are met*).
3. Check **Student must watch at least** and specify the required **Required percentage (%)** (default is `80%`).
   * *Note:* If you uncheck this condition, the video runs in **Free exploration mode** (`0%`), allowing students to fast-forward freely.
4. Save the activity. Completion is automatically recorded once the student watches the required percentage.

### 4. Viewing the Teacher Progress Report
1. Open the **Progress Report** from the activity secondary navigation tab (or via the bar-chart icon button on embedded/popup players).
2. Review each enrolled student's avatar, highest percentage watched, completion badge, and last access timestamp.

---

## Running Automated Tests (PHPUnit)

VideoTrack includes automated security and privacy regression tests in `tests/security_and_privacy_test.php`:

```bash
vendor/bin/phpunit --testdox mod/videotrack/tests/security_and_privacy_test.php
