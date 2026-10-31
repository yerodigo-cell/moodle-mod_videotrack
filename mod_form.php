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
 * @copyright  2026 EduPlugins Studio
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

        // Ensure .srt is registered in Moodle's filetype list so filemanager displays and accepts both .vtt and .srt.
        if (class_exists('core_filetypes')) {
            $types = core_filetypes::get_types();
            if (!isset($types['srt'])) {
                try {
                    core_filetypes::add_type('srt', 'text/plain', 'text', ['document'], '', 'SubRip Subtitle');
                } catch (Throwable $e) {
                    // Ignore if already exists or cannot be written.
                    unset($e);
                }
            }
        }

        // Captions (.vtt and .srt) file uploader.
        $mform->addElement(
            'filemanager',
            'captions',
            get_string('captionsfile', 'mod_videotrack'),
            null,
            ['subdirs' => 0, 'maxbytes' => $CFG->maxbytes, 'maxfiles' => 1, 'accepted_types' => ['.vtt', '.srt']]
        );
        $mform->addHelpButton('captions', 'captionsfile', 'mod_videotrack');

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

        // Display and playback section.
        $mform->addElement('header', 'display_settings', get_string('displayandplayback', 'mod_videotrack'));

        $displayoptions = [
            0 => get_string('displaymode_page', 'mod_videotrack'),
            1 => get_string('displaymode_popup', 'mod_videotrack'),
            2 => get_string('displaymode_inline', 'mod_videotrack'),
        ];
        $mform->addElement('select', 'displaymode', get_string('displaymode', 'mod_videotrack'), $displayoptions);
        $mform->setType('displaymode', PARAM_INT);
        $mform->setDefault('displaymode', 0);
        $mform->addHelpButton('displaymode', 'displaymode', 'mod_videotrack');

        // Accent colour (Hex code, defaults to current sky-blue #0ea5e9 if left empty).
        $mform->addElement(
            'text',
            'accentcolor',
            get_string('accentcolor', 'mod_videotrack'),
            ['size' => '10', 'placeholder' => '#0ea5e9']
        );
        $mform->setType('accentcolor', PARAM_RAW_TRIMMED);
        $mform->setDefault('accentcolor', '#0ea5e9');
        $mform->addHelpButton('accentcolor', 'accentcolor', 'mod_videotrack');

        // Focus Mode (Auto-pause when switching tabs or windows).
        $mform->addElement(
            'advcheckbox',
            'focusmode',
            get_string('focusmode', 'mod_videotrack'),
            get_string('focusmode_desc', 'mod_videotrack'),
            null,
            [0, 1]
        );
        $mform->addHelpButton('focusmode', 'focusmode', 'mod_videotrack');
        $mform->setDefault('focusmode', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add custom completion rules to the Completion conditions section.
     *
     * @return array Array of element names added.
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';

        $groupname = 'completionwatchgroup' . $suffix;
        $enabledel = 'targetpercentenabled' . $suffix;
        $valueel = 'targetpercent' . $suffix;

        $group = [];
        $group[] = $mform->createElement(
            'checkbox',
            $enabledel,
            '',
            get_string('completionwatch', 'mod_videotrack')
        );
        $group[] = $mform->createElement(
            'text',
            $valueel,
            '',
            ['size' => 4]
        );
        $group[] = $mform->createElement('static', 'targetpercentsymbol' . $suffix, '', '%');

        $mform->setType($valueel, PARAM_INT);
        $mform->addGroup($group, $groupname, '', ' ', false);
        $mform->addHelpButton($groupname, 'targetpercent', 'mod_videotrack');
        $mform->setDefault($enabledel, 1);
        $mform->setDefault($valueel, 80);
        $mform->disabledIf($valueel, $enabledel, 'notchecked');

        return [$groupname];
    }

    /**
     * Determines if a custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool True if enabled.
     */
    public function completion_rule_enabled($data) {
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        $enabledel = 'targetpercentenabled' . $suffix;
        $valueel = 'targetpercent' . $suffix;

        return !empty($data[$enabledel]) && !empty($data[$valueel]) && (int)$data[$valueel] > 0;
    }

    /**
     * Data preprocessing.
     *
     * @param array $defaultvalues Default values.
     */
    public function data_preprocessing(&$defaultvalues) {
        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        $enabledel = 'targetpercentenabled' . $suffix;
        $valueel = 'targetpercent' . $suffix;

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

            $captionsdraftitemid = file_get_submitted_draft_itemid('captions');
            file_prepare_draft_area(
                $captionsdraftitemid,
                $this->context->id,
                'mod_videotrack',
                'captions',
                0,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $defaultvalues['captions'] = $captionsdraftitemid;

            $pct = isset($defaultvalues['targetpercent']) ? (int)$defaultvalues['targetpercent'] : 0;
            $defaultvalues[$enabledel] = ($pct > 0) ? 1 : 0;
            $defaultvalues[$valueel] = ($pct > 0) ? $pct : 80;

            $savedcolor = isset($defaultvalues['accentcolor']) ? trim((string)$defaultvalues['accentcolor']) : '';
            $defaultvalues['accentcolor'] = ($savedcolor !== '') ? $savedcolor : '#0ea5e9';
            $defaultvalues['displaymode'] = isset($defaultvalues['displaymode']) ? (int)$defaultvalues['displaymode'] : 0;
        } else {
            $defaultvalues[$enabledel] = 1;
            $defaultvalues[$valueel] = 80;
            $defaultvalues['accentcolor'] = '#0ea5e9';
            $defaultvalues['displaymode'] = 0;
        }

        if (isset($defaultvalues['manualtime'])) {
            $t = (int)$defaultvalues['manualtime'];
            $defaultvalues['manualtime_h'] = intdiv($t, 3600);
            $defaultvalues['manualtime_m'] = intdiv($t % 3600, 60);
            $defaultvalues['manualtime_s'] = $t % 60;
        }
    }

    /**
     * Return submitted data and normalize targetpercent when completion rule is modified.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }

        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        $enabledel = 'targetpercentenabled' . $suffix;
        $valueel = 'targetpercent' . $suffix;

        if (!empty($data->completionunlocked)) {
            if (!empty($suffix) && isset($data->{$valueel})) {
                $data->targetpercent = (int)$data->{$valueel};
            }

            if (
                empty($data->{$enabledel}) ||
                (isset($data->completion) && (int)$data->completion !== COMPLETION_TRACKING_AUTOMATIC)
            ) {
                $data->targetpercent = 0;
            }
        } else if (!empty($this->current->instance) && isset($this->current->targetpercent)) {
            $data->targetpercent = (int)$this->current->targetpercent;
        }

        if (isset($data->targetpercent)) {
            $data->targetpercent = max(0, min(100, (int)$data->targetpercent));
        }

        $data->accentcolor = isset($data->accentcolor) ? videotrack_sanitize_accentcolor($data->accentcolor) : '';
        $data->displaymode = isset($data->displaymode) ? (int)$data->displaymode : 0;

        return $data;
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

        // Validate captions file extension (.vtt or .srt).
        $captionsdraftid = $data['captions'] ?? 0;
        if ($captionsdraftid) {
            $captionfiles = $fs->get_area_files($filecontext->id, 'user', 'draft', $captionsdraftid, 'id', false);
            foreach ($captionfiles as $cf) {
                $ext = strtolower(pathinfo($cf->get_filename(), PATHINFO_EXTENSION));
                if (!in_array($ext, ['vtt', 'srt'], true)) {
                    $errors['captions'] = get_string('invalidfiletype', 'error', $cf->get_filename());
                }
            }
        }

        // Validate hex color if provided (accepts #RRGGBB, RRGGBB, #RGB, RGB).
        $accentcolor = trim($data['accentcolor'] ?? '');
        if ($accentcolor !== '' && videotrack_sanitize_accentcolor($accentcolor) === '') {
            $errors['accentcolor'] = get_string('error_invalidcolor', 'mod_videotrack');
        }

        $suffix = method_exists($this, 'get_suffix') ? $this->get_suffix() : '';
        $enabledel = 'targetpercentenabled' . $suffix;
        $valueel = 'targetpercent' . $suffix;
        $groupname = 'completionwatchgroup' . $suffix;

        if (!empty($data['completionunlocked']) && !empty($data[$enabledel])) {
            $pct = (int)($data[$valueel] ?? 0);
            if ($pct < 1 || $pct > 100) {
                $errors[$groupname] = get_string('error_invalidpercent', 'mod_videotrack');
            }
        }

        return $errors;
    }
}
