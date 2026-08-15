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
 * Web service definitions for mod_videotrack.
 *
 * @package    mod_videotrack
 * @copyright  2026 EduPlugins Studio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_videotrack_save_progress' => [
        'classname'     => 'mod_videotrack\external\save_progress',
        'methodname'    => 'execute',
        'description'   => 'Saves the video progress of a user.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'mod_videotrack_save_reaction' => [
        'classname'     => 'mod_videotrack\external\save_reaction',
        'methodname'    => 'execute',
        'description'   => 'Saves a video reaction.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'mod_videotrack_get_reactions' => [
        'classname'     => 'mod_videotrack\external\get_reactions',
        'methodname'    => 'execute',
        'description'   => 'Gets reactions for a video.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];
