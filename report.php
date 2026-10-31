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

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/enrollib.php');

$id = required_param('id', PARAM_INT); // CM ID.

// 1. Get instance and course data.
$cm = get_coursemodule_from_id('videotrack', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$videotrack = $DB->get_record('videotrack', ['id' => $cm->instance], '*', MUST_EXIST);

// 2. Secure access control (Course teachers or admins only).
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/videotrack:viewreport', $context);

// 3. Configure the Moodle page with course tabs support.
$PAGE->set_url('/mod/videotrack/report.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('report', 'mod_videotrack'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->requires->css(new moodle_url('/mod/videotrack/styles.css'));

// Keep the report tab active.
$PAGE->navbar->add(get_string('report', 'mod_videotrack'));

$rawaccent = videotrack_sanitize_accentcolor($videotrack->accentcolor ?? '');
$accentcolor = ($rawaccent !== '') ? $rawaccent : '#0ea5e9';
$courseurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
$backlabel = get_string('backtocourse', 'mod_videotrack');

echo $OUTPUT->header();

// Modern report styles scoped to the report container.
echo '<style>
.vt-report-card {
    --vt-accent: ' . s($accentcolor) . ';
    max-width: 1024px;
    margin: 20px auto 32px auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 12px 32px -4px rgba(15, 23, 42, 0.06), 0 4px 14px -2px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}
.vt-report-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 24px;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.vt-report-title-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}
.vt-report-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--vt-accent, #0ea5e9);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.16);
    flex-shrink: 0;
}
.vt-report-heading {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}
.vt-report-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    background-color: var(--vt-accent, #0ea5e9);
    color: #ffffff !important;
    border: 0;
    border-radius: 10px;
    text-decoration: none !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.14);
    flex-shrink: 0;
}
.vt-report-back-btn svg {
    display: block;
    transition: transform 0.2s ease;
}
.vt-report-back-btn:hover {
    background-color: var(--vt-accent, #0ea5e9);
    color: #ffffff !important;
    filter: brightness(1.06);
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.18);
}
.vt-report-back-btn:hover svg {
    transform: translateX(-2px);
}
.vt-report-table-wrap {
    padding: 0;
    overflow-x: auto;
}
.vt-report-card table.mod_videotrack_report {
    margin: 0 !important;
    border: 0 !important;
    width: 100%;
    border-collapse: collapse;
}
.vt-report-card table.mod_videotrack_report thead th {
    background: #f8fafc !important;
    color: #475569 !important;
    font-size: 0.78rem !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 14px 24px !important;
    border-top: 0 !important;
    border-left: 0 !important;
    border-right: 0 !important;
    border-bottom: 1px solid #e2e8f0 !important;
}
.vt-report-card table.mod_videotrack_report tbody td {
    padding: 16px 24px !important;
    vertical-align: middle !important;
    border-left: 0 !important;
    border-right: 0 !important;
    border-top: 0 !important;
    border-bottom: 1px solid #f1f5f9 !important;
    color: #334155;
    font-size: 0.92rem;
}
.vt-report-card table.mod_videotrack_report tbody tr:last-child td {
    border-bottom: 0 !important;
}
.vt-report-card table.mod_videotrack_report tbody tr:hover td {
    background-color: #f8fafc !important;
}
.vt-report-student {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    color: #0f172a;
}
.vt-report-student > a,
.vt-report-student .userpicture,
.vt-report-student .userinitials {
    margin: 0 !important;
    flex-shrink: 0 !important;
}
.vt-report-student > a {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px !important;
    height: 38px !important;
    border-radius: 50% !important;
    text-decoration: none !important;
}
.vt-report-student img.userpicture {
    width: 38px !important;
    height: 38px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    display: block !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08) !important;
}
.vt-report-student span.userinitials {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 38px !important;
    height: 38px !important;
    min-width: 38px !important;
    min-height: 38px !important;
    border-radius: 50% !important;
    background-color: #f1f5f9 !important;
    border: 1px solid #cbd5e1 !important;
    color: var(--vt-accent, #0ea5e9) !important;
    font-size: 0.82rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.02em !important;
    line-height: 1 !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05) !important;
    box-sizing: border-box !important;
}
.vt-report-progress-cell {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 220px;
}
.vt-report-track {
    flex: 1;
    height: 8px;
    border-radius: 999px;
    background-color: #f1f5f9;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.05);
}
.vt-report-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--vt-accent, #0ea5e9);
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.vt-report-pct-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 52px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: var(--vt-accent, #0ea5e9);
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1;
}
.vt-report-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1;
}
.vt-report-status-done {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #059669;
}
.vt-report-status-pending {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #64748b;
}
.vt-report-date {
    color: #64748b;
    font-size: 0.88rem;
}
</style>';

echo '<div class="vt-report-card">';
echo '  <div class="vt-report-top">';
echo '    <div class="vt-report-title-wrap">';
echo '      <span class="vt-report-dot"></span>';
echo '      <h3 class="vt-report-heading">' . format_string($videotrack->name) . ' — ' .
    s(get_string('report', 'mod_videotrack')) . '</h3>';
echo '    </div>';
echo '    <a href="' . s($courseurl) . '" class="vt-report-back-btn" title="' . s($backlabel) .
    '" aria-label="' . s($backlabel) . '">';
echo '      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" ' .
    'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
echo '        <line x1="19" y1="12" x2="5" y2="12"></line>';
echo '        <polyline points="12 19 5 12 12 5"></polyline>';
echo '      </svg>';
echo '    </a>';
echo '  </div>';

// Fetch all progress records for this activity at once to prevent DB queries inside the loop.
$allprogress = $DB->get_records(
    'videotrack_progress',
    ['videotrackid' => $videotrack->id],
    '',
    'userid, id, highestpercent, iscompleted, timemodified'
);

// 4. Find enrolled users in the course with permission to view the activity.
$users = get_enrolled_users($context, 'mod/videotrack:view', 0, 'u.*');

// Filter to exclude teachers/admins unless they have actively recorded progress (> 0%) on this video.
foreach ($users as $key => $user) {
    if (has_capability('moodle/course:manageactivities', $context, $user)) {
        $hasactiveprogress = isset($allprogress[$user->id]) && (int)$allprogress[$user->id]->highestpercent > 0;
        if (!$hasactiveprogress) {
            unset($users[$key]);
        }
    }
}

$targetpercent = (int)$videotrack->targetpercent;

if (empty($users)) {
    echo '<div style="padding: 24px;">';
    echo $OUTPUT->notification(get_string('noresponses', 'mod_videotrack'), 'info');
    echo '</div>';
} else {
    $table = new html_table();
    $table->head = [
        get_string('student', 'mod_videotrack'),
        get_string('highestpercent', 'mod_videotrack'),
        get_string('completed', 'mod_videotrack'),
        get_string('lastaccess', 'mod_videotrack'),
    ];
    $table->attributes['class'] = 'generaltable mod_videotrack_report';

    foreach ($users as $user) {
        $progress = $allprogress[$user->id] ?? null;

        $fullname = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
        try {
            $defaultname = fullname($user);
            if (is_string($defaultname) && trim($defaultname) !== '') {
                $fullname = $defaultname;
            }
        } catch (Throwable $e) {
            // Keep safe firstname and lastname fallback if theme overrides fullname improperly.
            $fullname = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
        }

        $initials = core_text::strtoupper(
            core_text::substr(trim((string)($user->firstname ?? '')), 0, 1) .
            core_text::substr(trim((string)($user->lastname ?? '')), 0, 1)
        );
        if ($initials === '') {
            $initials = 'U';
        }
        $fallbackavatar = '<span class="userinitials size-38">' . s($initials) . '</span>';

        $userpicture = $fallbackavatar;
        try {
            $pic = $OUTPUT->user_picture($user, ['size' => 38, 'link' => false]);
            if (is_string($pic) && trim($pic) !== '') {
                $userpicture = $pic;
            }
        } catch (Throwable $e) {
            $userpicture = $fallbackavatar;
        }

        // Student cell with avatar and name.
        $studentcell = html_writer::div(
            $userpicture . html_writer::span(s($fullname)),
            'vt-report-student'
        );

        $percent = $progress ? max(0, min(100, (int)$progress->highestpercent)) : 0;
        $isdone = false;
        if ($progress) {
            $isdone = !empty($progress->iscompleted)
                || ($targetpercent > 0 && $percent >= $targetpercent)
                || ($targetpercent <= 0 && $percent > 0);
            if ($isdone && empty($progress->iscompleted)) {
                $DB->set_field('videotrack_progress', 'iscompleted', 1, ['id' => $progress->id]);
            }
        }

        // Modern smooth pill progress bar + percentage badge.
        $progressbar = '<div class="vt-report-progress-cell">' .
            '<div class="vt-report-track" role="progressbar" aria-valuenow="' . $percent . '" ' .
            'aria-valuemin="0" aria-valuemax="100">' .
            '<div class="vt-report-fill" style="width: ' . $percent . '%;"></div>' .
            '</div>' .
            '<span class="vt-report-pct-badge">' . $percent . '%</span>' .
            '</div>';

        // Modern status pill badge.
        if ($isdone) {
            $completedcell = '<span class="vt-report-status-pill vt-report-status-done">' .
                '<i class="fa fa-check-circle" aria-hidden="true"></i> ' .
                s(get_string('yes', 'moodle')) .
                '</span>';
        } else {
            $completedcell = '<span class="vt-report-status-pill vt-report-status-pending">' .
                '<i class="fa fa-clock-o" aria-hidden="true"></i> ' .
                s(get_string('no', 'moodle')) .
                '</span>';
        }

        // Formatted last access date.
        $lastaccesscell = '<span class="vt-report-date">—</span>';
        if ($progress && !empty($progress->timemodified) && $percent > 0) {
            $formatteddate = userdate($progress->timemodified, get_string('strftimedatetime', 'langconfig'));
            $lastaccesscell = '<span class="vt-report-date">' . s($formatteddate) . '</span>';
        }

        $table->data[] = [
            $studentcell,
            $progressbar,
            $completedcell,
            $lastaccesscell,
        ];
    }

    echo '<div class="vt-report-table-wrap">';
    echo html_writer::table($table);
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();
