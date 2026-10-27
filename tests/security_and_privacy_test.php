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
 * Security and Privacy regression tests for mod_videotrack.
 *
 * Covers:
 * - mod_videotrack/VTRK-SEC-001 (progress range and server-side plausibility)
 * - mod_videotrack/VTRK-SEC-002 (HeyGen allowlist over HTTPS)
 * - mod_videotrack/VTRK-PRIV-001 (Privacy API and activity deletion for reactions)
 *
 * @package     mod_videotrack
 * @category    test
 * @copyright   2026 EduPlugins Studio
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use invalid_parameter_exception;
use mod_videotrack\external\save_progress;
use mod_videotrack\external\save_reaction;
use mod_videotrack\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/videotrack/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Regression test suite for VideoTrack security and privacy findings.
 *
 * @covers \mod_videotrack\external\save_progress
 * @covers \mod_videotrack\privacy\provider
 */
final class security_and_privacy_test extends provider_testcase {

    /**
     * Helper to create a course, enrolled student and VideoTrack instance.
     *
     * @param int $targetpercent Target completion percentage.
     * @param int $manualtime Optional video duration in seconds.
     * @return array [$course, $student, $videotrack, $cm, $context]
     */
    private function setup_videotrack_environment(int $targetpercent = 80, int $manualtime = 0): array {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $module = $DB->get_record('modules', ['name' => 'videotrack'], '*', MUST_EXIST);

        $videotrack = (object)[
            'course' => $course->id,
            'name' => 'Test VideoTrack',
            'intro' => 'Intro',
            'introformat' => FORMAT_HTML,
            'videourl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'targetpercent' => $targetpercent,
            'isgdrive' => 0,
            'manualtime' => $manualtime,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $videotrack->id = $DB->insert_record('videotrack', $videotrack);

        $cm = (object)[
            'course' => $course->id,
            'module' => $module->id,
            'instance' => $videotrack->id,
            'section' => 0,
            'added' => time(),
            'visible' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => COMPLETION_VIEW_REQUIRED,
        ];
        $cm->id = add_course_module($cm);
        course_add_cm_to_section($course->id, $cm->id, 0);

        $cm = get_coursemodule_from_id('videotrack', $cm->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        return [$course, $student, $videotrack, $cm, $context];
    }

    /**
     * VTRK-SEC-001: Rejects negative percent (-1) with invalid_parameter_exception.
     */
    public function test_save_progress_rejects_negative_percent(): void {
        [, $student, , $cm] = $this->setup_videotrack_environment(80);
        $this->setUser($student);

        $this->expectException(invalid_parameter_exception::class);
        save_progress::execute($cm->id, -1, 0);
    }

    /**
     * VTRK-SEC-001: Rejects percent above 100 (101) with invalid_parameter_exception.
     */
    public function test_save_progress_rejects_percent_above_100(): void {
        [, $student, , $cm] = $this->setup_videotrack_environment(80);
        $this->setUser($student);

        $this->expectException(invalid_parameter_exception::class);
        save_progress::execute($cm->id, 101, 0);
    }

    /**
     * VTRK-SEC-001: Calling save_progress with percent=100 and no prior progress leaves activity incomplete.
     */
    public function test_save_progress_single_jump_to_100_stays_incomplete(): void {
        global $DB;

        [$course, $student, $videotrack, $cm] = $this->setup_videotrack_environment(80);
        $this->setUser($student);

        $result = save_progress::execute($cm->id, 100, 0);
        $this->assertFalse($result['success']);

        $progress = $DB->get_record('videotrack_progress', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]);

        $this->assertNotEmpty($progress);
        $this->assertEquals(0, (int)$progress->iscompleted);
        $this->assertLessThan(80, (int)$progress->highestpercent);

        $completion = new \completion_info($course);
        $compdata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, (int)$compdata->completionstate);
    }

    /**
     * VTRK-SEC-001: Incremental plausible playback sequence completes the activity.
     */
    public function test_save_progress_incremental_sequence_completes_activity(): void {
        global $DB;

        [, $student, $videotrack, $cm] = $this->setup_videotrack_environment(80, 0);
        $this->setUser($student);

        // Step 1: 30%
        $res1 = save_progress::execute($cm->id, 30, 10);
        $this->assertTrue($res1['success']);

        // Simulate 15 seconds of elapsed wall-clock time on the server record.
        $DB->set_field('videotrack_progress', 'timemodified', time() - 15, [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]);

        // Step 2: 65%
        $res2 = save_progress::execute($cm->id, 65, 22);
        $this->assertTrue($res2['success']);

        // Simulate another 15 seconds of elapsed wall-clock time.
        $DB->set_field('videotrack_progress', 'timemodified', time() - 15, [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]);

        // Step 3: 85% (reaches targetpercent = 80).
        $res3 = save_progress::execute($cm->id, 85, 30);
        $this->assertTrue($res3['success']);

        $progress = $DB->get_record('videotrack_progress', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ], '*', MUST_EXIST);

        $this->assertEquals(1, (int)$progress->iscompleted);
        $this->assertEquals(85, (int)$progress->highestpercent);
    }

    /**
     * VTRK-SEC-002: No request is made when app.heygen.com appears only in query, path or fragment,
     * or for loopback, private and link-local destinations.
     */
    public function test_heygen_url_blocks_spoofed_and_internal_destinations(): void {
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->never())->method('get');

        $blockedurls = [
            'https://127.0.0.1/?app.heygen.com',
            'http://localhost/?app.heygen.com',
            'http://169.254.169.254/latest/meta-data/?app.heygen.com',
            'https://10.0.0.1/app.heygen.com/video',
            'https://192.168.1.1/#app.heygen.com',
            'http://app.heygen.com/share/12345', // HTTP scheme must also be rejected.
            'https://evil.example.com/app.heygen.com',
        ];

        foreach ($blockedurls as $url) {
            $this->assertFalse(videotrack_is_allowed_heygen_url($url), "URL should be rejected: $url");
            $returned = videotrack_extract_heygen_url($url, $mockcurl);
            $this->assertSame($url, $returned);
        }
    }

    /**
     * VTRK-SEC-002: A request is made when the host is exactly app.heygen.com over HTTPS.
     */
    public function test_heygen_url_fetches_exact_https_host(): void {
        $validurl = 'https://app.heygen.com/videos/abc123xyz';
        $expectedmp4 = 'https://resource2.heygen.ai/transcoded/video123.mp4';

        $this->assertTrue(videotrack_is_allowed_heygen_url($validurl));

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())
            ->method('get')
            ->with($this->equalTo($validurl))
            ->willReturn('<html><body><video src="' . $expectedmp4 . '"></video></body></html>');

        $extracted = videotrack_extract_heygen_url($validurl, $mockcurl);
        $this->assertSame($expectedmp4, $extracted);
    }

    /**
     * VTRK-PRIV-001: Privacy API provider and activity deletion cover videotrack_reactions.
     */
    public function test_privacy_provider_and_deletion_cover_reactions(): void {
        global $DB;

        [, $student, $videotrack, $cm, $context] = $this->setup_videotrack_environment(80);
        $this->setUser($student);

        // Save a reaction for the student.
        save_reaction::execute($cm->id, 'heart', 42);
        $this->assertEquals(1, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));

        // 1. Context discovery includes reactions even without progress records.
        $contextlist = provider::get_contexts_for_userid($student->id);
        $this->assertContainsEquals($context->id, $contextlist->get_contextids());

        // 2. User discovery includes reactions.
        $userlist = new userlist($context, 'mod_videotrack');
        provider::get_users_in_context($userlist);
        $this->assertContainsEquals($student->id, $userlist->get_userids());

        // 3. Export includes reactions.
        $approved = new approved_contextlist($student, 'mod_videotrack', [$context->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $exportedreactions = $writer->get_data(['reactions']);
        $this->assertNotEmpty($exportedreactions->reactions);
        $this->assertEquals('heart', $exportedreactions->reactions[0]->reaction);
        $this->assertEquals(42, $exportedreactions->reactions[0]->timeoffset);

        // 4. Delete data for user removes reactions.
        provider::delete_data_for_user($approved);
        $this->assertEquals(0, $DB->count_records('videotrack_reactions', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]));

        // 5. Re-insert and test delete_data_for_users.
        save_reaction::execute($cm->id, 'like', 15);
        $approvedusers = new approved_userlist($context, 'mod_videotrack', [$student->id]);
        provider::delete_data_for_users($approvedusers);
        $this->assertEquals(0, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));

        // 6. Re-insert and test videotrack_delete_instance() cleans up reactions.
        save_reaction::execute($cm->id, 'star', 30);
        $this->assertEquals(1, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));
        videotrack_delete_instance($videotrack->id);
        $this->assertEquals(0, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));
    }
}
