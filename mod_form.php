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
 * Instance configuration formula for setting up new instances of the module
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_masks_mod_form extends moodleform_mod {

    public function __construct($current, $section, $cm, $course) {
        // store away properties that we may need later
        $this->cm=$cm;
        $this->course=$course;
        // delegate to parent
        parent::__construct($current, $section, $cm, $course);
    }

    private function addtextfield($fieldnamebase,$maxlen,$defaultvalue=null,$fieldsuffix=''){
        $mform = $this->_form;
        $fieldname=$fieldnamebase.$fieldsuffix;
        $mform->addElement('text', $fieldname, get_string($fieldnamebase,'masks'), array('size'=>'60'));
        $mform->setType($fieldname, PARAM_TEXT);
        $mform->addRule($fieldname, null, 'required', null, 'client');
        $mform->addRule($fieldname, get_string('maximumchars', '', $maxlen), 'maxlength', $maxlen, 'client');
        if ($defaultvalue){
            $mform->setDefault($fieldname, $defaultvalue);
        }
    }
    
    function definition() {
        $mform = $this->_form;

        //-------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // text fields
        $this->addtextfield('name',255);
    }
    
    function definition_after_data(){
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    function validation($data, $files) {
        // delegate to parent class
        $errors = parent::validation($data, $files);
        return $errors;
    }
}

