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
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module form.
 *
 * @package    mod_videotrack
 * @copyright   2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_videotrack_mod_form extends moodleform_mod {
    /**
     * Define the form.
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name', 'moodle'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', 'moodle', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'video_settings', get_string('pluginname', 'mod_videotrack'));

        // Allow leaving this empty if a file is uploaded.
        $mform->addElement('text', 'videourl', get_string('videourl', 'mod_videotrack'), ['size' => '64']);
        $mform->setType('videourl', PARAM_URL);
        $mform->addHelpButton('videourl', 'videourl', 'mod_videotrack');

        $mform->addElement(
            'filemanager',
            'video',
            get_string('videofile', 'mod_videotrack'),
            null,
            ['subdirs' => 0, 'maxbytes' => $CFG->maxbytes, 'maxfiles' => 1, 'accepted_types' => ['video']]
        );
        $mform->addHelpButton('video', 'videofile', 'mod_videotrack');

        $mform->addElement('text', 'targetpercent', get_string('targetpercent', 'mod_videotrack'), ['size' => '4']);
        $mform->setType('targetpercent', PARAM_INT);
        $mform->setDefault('targetpercent', 80);
        // Allow 0 for free exploration mode.
        $mform->addRule('targetpercent', null, 'numeric', null, 'client');
        $mform->addRule('targetpercent', 'Maximum 100', 'maxlength', 3, 'client');
        $mform->addHelpButton('targetpercent', 'targetpercent', 'mod_videotrack');

        // New fields for Google Drive / manual tracking.
        $mform->addElement('checkbox', 'isgdrive', get_string('isgdrive', 'mod_videotrack'));
        $mform->addHelpButton('isgdrive', 'isgdrive', 'mod_videotrack');
        $mform->setDefault('isgdrive', 0);

        $disclaimerhtml = '<div class="alert alert-warning mt-1 mb-0 py-2" style="font-size: 0.9em; max-width: 600px;">';
        $disclaimerhtml .= '<i class="fa fa-info-circle mr-1"></i> ' . get_string('gdrivedisclaimer', 'mod_videotrack') . '</div>';
        $disclaimergroup = [$mform->createElement('static', 'gdrivedisclaimer_content', '', $disclaimerhtml)];
        $mform->addGroup($disclaimergroup, 'gdrivedisclaimer_group', ' ', '', false);
        $mform->hideIf('gdrivedisclaimer_group', 'isgdrive', 'notchecked');

        $timegrp = [];
        $timegrp[] = $mform->createElement('text', 'manualtime_h', '', ['size' => 2, 'placeholder' => 'hh']);
        $timegrp[] = $mform->createElement('static', 'sep1', '', ' : ');
        $timegrp[] = $mform->createElement('text', 'manualtime_m', '', ['size' => 2, 'placeholder' => 'mm']);
        $timegrp[] = $mform->createElement('static', 'sep2', '', ' : ');
        $timegrp[] = $mform->createElement('text', 'manualtime_s', '', ['size' => 2, 'placeholder' => 'ss']);

        $mform->addGroup($timegrp, 'manualtime_group', get_string('manualtime', 'mod_videotrack'), ' ', false);
        $mform->setType('manualtime_h', PARAM_INT);
        $mform->setType('manualtime_m', PARAM_INT);
        $mform->setType('manualtime_s', PARAM_INT);
        $mform->setDefault('manualtime_h', 0);
        $mform->setDefault('manualtime_m', 0);
        $mform->setDefault('manualtime_s', 0);
        $mform->addHelpButton('manualtime_group', 'manualtime', 'mod_videotrack');
        $mform->hideIf('manualtime_group', 'isgdrive', 'notchecked');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Data preprocessing.
     *
     * @param array $defaultvalues Default values.
     */
    public function data_preprocessing(&$defaultvalues) {
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('video');
            file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_videotrack',
                'video',
                0,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $defaultvalues['video'] = $draftitemid;
        }

        if (isset($defaultvalues['manualtime'])) {
            $t = (int)$defaultvalues['manualtime'];
            $defaultvalues['manualtime_h'] = intdiv($t, 3600);
            $defaultvalues['manualtime_m'] = intdiv($t % 3600, 60);
            $defaultvalues['manualtime_s'] = $t % 60;
        }
    }

    /**
     * Validation.
     *
     * @param array $data Data.
     * @param array $files Files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $url = trim($data['videourl'] ?? '');
        global $USER;

        $draftitemid = $data['video'] ?? 0;
        $filecontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $hasuploadedfile = !$fs->is_area_empty($filecontext->id, 'user', 'draft', $draftitemid);

        if (empty($url) && !$hasuploadedfile) {
            $errors['videourl'] = get_string('error_nouploadorurl', 'mod_videotrack');
            $errors['video'] = get_string('error_nouploadorurl', 'mod_videotrack');
        }
        return $errors;
    }
}
