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
 * Security and privacy regression tests for mod_videotrack.
 *
 * Covers:
 * - mod_videotrack/VTRK-SEC-001 (progress range and server-side plausibility checks)
 * - mod_videotrack/VTRK-SEC-002 (strict HeyGen HTTPS host allowlist)
 * - mod_videotrack/VTRK-PRIV-001 (Privacy API and activity deletion coverage for reactions)
 *
 * @package    mod_videotrack
 * @category   test
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotrack;

use core_privacy\local\metadata\collection;
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

/**
 * Security and privacy tests for mod_videotrack.
 *
 * @covers \mod_videotrack\external\save_progress
 * @covers \mod_videotrack\privacy\provider
 */
final class security_and_privacy_test extends provider_testcase {
    /**
     * Helper to create a course, enrolled student and VideoTrack activity.
     *
     * @param array $options Activity options.
     * @return array [$course, $student, $videotrack, $cm]
     */
    private function setup_activity(array $options = []): array {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $record = (object) array_merge([
            'course' => $course->id,
            'name' => 'Test VideoTrack',
            'intro' => 'Intro',
            'introformat' => FORMAT_HTML,
            'videourl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'targetpercent' => 80,
            'isgdrive' => 0,
            'manualtime' => 60,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $options);

        $record->id = $DB->insert_record('videotrack', $record);

        $module = $DB->get_record('modules', ['name' => 'videotrack'], '*', MUST_EXIST);
        $cm = (object) [
            'course' => $course->id,
            'module' => $module->id,
            'instance' => $record->id,
            'section' => 0,
            'added' => $now,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => COMPLETION_VIEW_REQUIRED,
        ];
        $cm->id = add_course_module($cm);
        course_add_cm_to_section($course->id, $cm->id, 0);

        $cm = get_coursemodule_from_id('videotrack', $cm->id, 0, false, MUST_EXIST);
        return [$course, $student, $record, $cm];
    }

    /**
     * VTRK-SEC-001: Rejects negative percent (-1) with invalid_parameter_exception.
     */
    public function test_save_progress_rejects_negative_percent(): void {
        [, $student, , $cm] = $this->setup_activity();
        $this->setUser($student);

        $this->expectException(invalid_parameter_exception::class);
        save_progress::execute($cm->id, -1, 0);
    }

    /**
     * VTRK-SEC-001: Rejects percent above 100 (101) with invalid_parameter_exception.
     */
    public function test_save_progress_rejects_percent_above_100(): void {
        [, $student, , $cm] = $this->setup_activity();
        $this->setUser($student);

        $this->expectException(invalid_parameter_exception::class);
        save_progress::execute($cm->id, 101, 0);
    }

    /**
     * VTRK-SEC-001: Calling save_progress with percent=100 and no prior progress keeps the activity incomplete.
     */
    public function test_save_progress_single_jump_to_100_stays_incomplete(): void {
        global $DB;

        [$course, $student, $videotrack, $cm] = $this->setup_activity([
            'targetpercent' => 80,
            'manualtime' => 60,
        ]);
        $this->setUser($student);

        $result = save_progress::execute($cm->id, 100, 0);
        $this->assertFalse($result['success']);

        $progress = $DB->get_record('videotrack_progress', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]);
        $this->assertNotEmpty($progress);
        $this->assertEquals(0, (int)$progress->iscompleted);
        $this->assertEquals(0, (int)$progress->highestpercent);

        $completion = new \completion_info($course);
        $compdata = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, (int)$compdata->completionstate);
    }

    /**
     * VTRK-SEC-001: Normal incremental playback sequence completes the activity.
     */
    public function test_save_progress_incremental_playback_completes_activity(): void {
        global $DB;

        [, $student, $videotrack, $cm] = $this->setup_activity([
            'targetpercent' => 80,
            'manualtime' => 20,
        ]);
        $this->setUser($student);

        // Step 1: 40% watched.
        $res1 = save_progress::execute($cm->id, 40, 8);
        $this->assertTrue($res1['success']);

        // Simulate 10 seconds of wall-clock time passing before the next report.
        $progress = $DB->get_record('videotrack_progress', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ], '*', MUST_EXIST);
        $progress->timemodified -= 10;
        $DB->update_record('videotrack_progress', $progress);

        // Step 2: 80% watched (40% delta over 10s elapsed on a 20s video is plausible).
        $res2 = save_progress::execute($cm->id, 80, 16);
        $this->assertTrue($res2['success']);

        $updated = $DB->get_record('videotrack_progress', ['id' => $progress->id], '*', MUST_EXIST);
        $this->assertEquals(1, (int)$updated->iscompleted);
        $this->assertEquals(80, (int)$updated->highestpercent);
    }

    /**
     * VTRK-SEC-002: No HTTP request is made when app.heygen.com is only in query/path/fragment or private/loopback hosts.
     */
    public function test_heygen_url_validation_blocks_untrusted_and_private_hosts(): void {
        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->never())->method('get');

        $untrustedurls = [
            'https://127.0.0.1/?app.heygen.com',
            'http://localhost/?app.heygen.com',
            'http://169.254.169.254/latest/meta-data/?app.heygen.com',
            'https://evil.example.com/app.heygen.com/video',
            'https://evil.example.com/#app.heygen.com',
            'http://app.heygen.com/videos/123',
        ];

        foreach ($untrustedurls as $url) {
            $this->assertFalse(videotrack_is_allowed_heygen_url($url));
            $this->assertSame($url, videotrack_extract_heygen_url($url, $mockcurl));
        }
    }

    /**
     * VTRK-SEC-002: Request is made only when the host is exactly app.heygen.com over HTTPS.
     */
    public function test_heygen_url_validation_allows_exact_https_heygen_host(): void {
        $validurl = 'https://app.heygen.com/videos/abc123';
        $expectedmp4 = 'https://resource2.heygen.ai/transcoded/video123.mp4';

        $this->assertTrue(videotrack_is_allowed_heygen_url($validurl));

        $mockcurl = $this->createMock(\curl::class);
        $mockcurl->expects($this->once())
            ->method('get')
            ->with($validurl)
            ->willReturn('<html><body>"' . $expectedmp4 . '"</body></html>');

        $resolved = videotrack_extract_heygen_url($validurl, $mockcurl);
        $this->assertSame($expectedmp4, $resolved);
    }

    /**
     * VTRK-PRIV-001: Reactions are covered by Privacy API metadata, discovery, export, deletion and instance deletion.
     */
    public function test_privacy_provider_and_instance_deletion_cover_reactions(): void {
        global $DB;

        [, $student, $videotrack, $cm] = $this->setup_activity();
        $context = \context_module::instance($cm->id);
        $this->setUser($student);

        // 1. Metadata includes videotrack_reactions.
        $collection = new collection('mod_videotrack');
        $collection = provider::get_metadata($collection);
        $tables = [];
        foreach ($collection->get_collection() as $item) {
            $tables[] = $item->get_name();
        }
        $this->assertContains('videotrack_reactions', $tables);

        // 2. Save a reaction without any progress row.
        save_reaction::execute($cm->id, 'heart', 15);
        $this->assertEquals(1, $DB->count_records('videotrack_reactions', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]));

        // 3. Context and user discovery find the user via their reaction.
        $contextlist = provider::get_contexts_for_userid($student->id);
        $this->assertContainsEquals($context->id, $contextlist->get_contextids());

        $userlist = new userlist($context, 'mod_videotrack');
        provider::get_users_in_context($userlist);
        $this->assertContainsEquals($student->id, $userlist->get_userids());

        // 4. Export user data includes the reaction.
        $approved = new approved_contextlist($student, 'mod_videotrack', [$context->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $exported = $writer->get_data(['reactions']);
        $this->assertNotEmpty($exported->reactions);
        $this->assertEquals('heart', $exported->reactions[0]->reaction);

        // 5. Delete data for user removes their reaction.
        provider::delete_data_for_user($approved);
        $this->assertEquals(0, $DB->count_records('videotrack_reactions', [
            'videotrackid' => $videotrack->id,
            'userid' => $student->id,
        ]));

        // 6. Re-insert and test delete_data_for_users.
        save_reaction::execute($cm->id, 'like', 25);
        $approvedusers = new approved_userlist($context, 'mod_videotrack', [$student->id]);
        provider::delete_data_for_users($approvedusers);
        $this->assertEquals(0, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));

        // 7. Re-insert and test videotrack_delete_instance() deletes reactions.
        save_reaction::execute($cm->id, 'clap', 30);
        $this->assertEquals(1, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));
        videotrack_delete_instance($videotrack->id);
        $this->assertEquals(0, $DB->count_records('videotrack_reactions', ['videotrackid' => $videotrack->id]));
    }
}
