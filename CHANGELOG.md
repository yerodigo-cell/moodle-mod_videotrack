# Changelog — VideoTrack (`mod_videotrack`)

All notable changes to this project are documented in this file.

## [1.7.1] - 2026-11-05

### 🐛 Bug Fixes & UI Refinements
* **External Direct `.mp4` Playback Fix:** Fixed an issue where external direct `.mp4` video URLs hosted on third-party or institutional servers without CORS headers failed to play due to the `crossorigin="anonymous"` attribute on the HTML5 `<video>` element.
* **Google Drive Focus Mode Reset Fix:** Fixed an issue where switching browser tabs or windows with Focus Mode enabled caused Google Drive embedded videos to reload and restart from `0:00`. Focus Mode now pauses the progress bar timer while preserving the video playback position, and clarified this behavior in the activity form settings.
* **Moodle 5.3 Popup & Embed Navigation Fix:** Suppressed the native `Previous / Next` activity navigation bar and extra bottom whitespace injected inside modal popup and inline embedded players in Moodle 5.2/5.3+.
* **Moodle 4.1 Course Card Container Fix:** Removed the default grey background box and extra padding around `.course-description-item` in Moodle 4.1 when using Popup Card or Inline Embed display modes.
* **Header Completion Check Badge:** Replaced the bottom completion banner with a compact green check badge in the top-right card header (`#vt-success-msg`) to prevent layout shifts during playback.
* **Custom Accent Styling on Player Controls:** Extended custom accent colour support (`--vt-accent`) to the *Resume* button and the HTML5/YouTube seek bar timeline when a custom colour is configured.
* **Refined Timeline Emoji Markers & Report Avatars:** Removed double shadows on timeline reaction emojis for crisp rendering, and improved circular avatar/initials alignment and `Throwable` theme safety in `report.php`.

---

## [1.7] - 2026-10-31

### ✨ New Features
* **Course Page Display Modes:** Added three flexible display options in activity settings (*Course page display*):
  1. *Standard activity page* (classic dedicated view).
  2. *Card on course page (opens player in popup)* with automatic video/YouTube/Drive thumbnail extraction, live progress bar, and full `format_tiles` support.
  3. *Player embedded directly on course page* with responsive auto-resizing iframe and quick teacher action buttons (*Settings* and *Progress Report*).
* **Focus Mode (Auto-Pause on Tab/Window Switch):** Added optional Focus Mode that automatically pauses playback whenever the student switches browser tabs or application windows, featuring a live header status indicator and fullscreen transition protection.
* **Unified Subtitles & Closed Captions (`CC`):** Added native support for uploading **WebVTT (`.vtt`)** and **SubRip (`.srt`)** subtitle files with automatic on-the-fly `.srt` to `.vtt` conversion and a unified cross-browser caption overlay.
* **Custom Accent Colour (`--vt-accent`):** Teachers can customize the hex accent colour to match their course or institutional branding.
* **Smart 100% Completion Rounding & Queued Saves:** Normalized progress at `>= 98%` (or within the last `1.2s` of playback) to `100%` and added a queued AJAX flush (`videotrackFlushProgress`) when pausing or closing the popup modal.

### 🔒 Security, Privacy & Compatibility
* **Closes `mod_videotrack/VTRK-SEC-001`:** Added strict server-side range validation (`0..100`) and wall-clock plausibility checks (`is_progress_plausible()`) in `classes/external/save_progress.php`.
* **Closes `mod_videotrack/VTRK-SEC-002`:** Enforced strict HTTPS host allowlist validation (`app.heygen.com`) via `parse_url()` in `lib.php`, disabled cURL redirects, and ensured URL resolution runs only on instance save/update.
* **Closes `mod_videotrack/VTRK-PRIV-001`:** Added full Moodle Privacy API (`core_privacy`) coverage for `videotrack_reactions` (metadata, context/user discovery, export, and deletion) and cleaned up reaction records in `videotrack_delete_instance()`.
* **Automated PHPUnit Test Suite:** Added 7 security and privacy regression tests in `tests/security_and_privacy_test.php`.
* **Cross-Version & Database Compatibility:** Improved compatibility with **Moodle 4.1 – 4.5 and 5.2 / 5.3+**, **PHP 8.1–8.4**, **MariaDB**, and **PostgreSQL**.
