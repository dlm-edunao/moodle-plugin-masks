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
 * MASKS Activity Module - backup management
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_masks_activity_task
 */

/**
 * Define the complete masks structure for backup, with file and id annotations
 */
class backup_masks_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $masks = new backup_nested_element('masks', array('id'), array(
            'name', 'intro', 'introformat', 'publish',
            'showresults', 'display', 'allowupdate', 'showunanswered',
            'limitanswers', 'timeopen', 'timeclose', 'timemodified',
            'completionsubmit'));

        $options = new backup_nested_element('options');

        $option = new backup_nested_element('option', array('id'), array(
            'text', 'maxanswers', 'timemodified'));

        $answers = new backup_nested_element('answers');

        $answer = new backup_nested_element('answer', array('id'), array(
            'userid', 'optionid', 'timemodified'));

        // Build the tree
        $masks->add_child($options);
        $options->add_child($option);

        $masks->add_child($answers);
        $answers->add_child($answer);

        // Define sources
        $masks->set_source_table('masks', array('id' => backup::VAR_ACTIVITYID));

        $option->set_source_table('masks_options', array('masksid' => backup::VAR_PARENTID), 'id ASC');

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $answer->set_source_table('masks_answers', array('masksid' => '../../id'));
        }

        // Define id annotations
        $answer->annotate_ids('user', 'userid');

        // Define file annotations
        $masks->annotate_files('mod_masks', 'intro', null); // This file area hasn't itemid

        // Return the root element (masks), wrapped into standard activity structure
        return $this->prepare_activity_structure($masks);
    }
}
