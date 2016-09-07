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
 * masks module admin settings and defaults
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if ($ADMIN->fulltree) {
    require_once( dirname(__FILE__).'/settings_injector.class.php' );
    require_once($CFG->libdir.'/resourcelib.php');
    require_once($CFG->libdir.'/moodlelib.php');

    // instantiate a settings injector object to simplify settings definition
    $settingsinjector   = new \mod_masks\settingsinjector($settings,'mod_masks');

    // Basic settings
    $settingsinjector->addsetting('cmdline_pdf2svg','pdf2svg');
    $settingsinjector->addsetting('debug',0,'ADMIN_SETTING_TYPE_CHECKBOX');
}

