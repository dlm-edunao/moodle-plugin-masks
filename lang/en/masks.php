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
 * Strings for component 'masks', language 'en'
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// general strings - for use selecting a module type, or listing module types, etc
$string['modulename']                   = 'Maskable PDF';
$string['modulenameplural']             = 'Maskable PDF';
$string['modulename_help']              = 'Upload PDF files, mask out zones and add questions for students to answer in order to see the full page';

// plugin administration strings
$string['pluginadministration']         = 'Maskable PDF module administration';
$string['pluginname']                   = 'Maskable PDF';

// admin settings
$string['settingname_cmdline_pdf2svg']  = 'Command line for executing pdf2svg utility (that must be installed on the system for this plugin to work)';
$string['settingname_debug']            = 'Enable debugging options';

// instance settings
$string['name']                         = 'Activity Name';

// Misc strings
$string['page-mod-masks-x']              = 'Any Maskable PDF view';
$string['modulename_link']              = 'mod/masks/view';

// Messages displayed in notification area
$string['notReadyMsg']                  = 'Document not ready. Please try again later.';

// Texts for menus
$string['page']                         = 'Page';
$string['options']                      = 'Options';
$string['full-size']                    = 'Zoom 100%';
$string['re-show-masks']                = 'Show Passed Masks';
$string['page-hidden']                  = 'Hide Page';
$string['reupload']                     = 'Re-Upload Document';
$string['add-mask-menu']                = 'ADD';
$string['mask-actions-group']           = ''; // 'Mask: ';
$string['edit-question']                = 'Edit';
$string['mask-style-menu']              = 'Style';
$string['mask-hidden']                  = 'Hide';
$string['mask-deleted']                 = 'Delete';
$string['layout-save-group']            = ''; // 'Unsaved Changes: ';
$string['save-layout']                  = 'Save Now';

// Text for mask-type-related frames
$string['label_title']                  = 'Title';
$string['label_note']                   = 'Note text';
$string['label_question']               = 'The question *';
$string['label_answer']                 = 'Correct answer (one valid alternative per line) *';
$string['label_response']               = 'Answer';
$string['label_goodanswer']             = 'The correct answer *';
$string['label_badanswer0']             = 'An incorrect answer *';
$string['label_badanswer1']             = 'Another incorrect answer';
$string['label_badanswer2']             = 'Another incorrect answer';
$string['label_badanswer3']             = 'Another incorrect answer';
$string['label_goodanswerhint']         = 'Correct answer response';
$string['label_badanswerhint']          = 'Incorrect answer response';
$string['label_userhint']               = 'Hint';
$string['label_togglehint']             = 'Show Hint';
$string['label_togglehelp']             = 'Show Help';
$string['label_submit']                 = 'Submit';
$string['label_cancel']                 = 'Cancel';
$string['label_close']                  = 'Close';
$string['goodanswer_title']             = 'Correct!';
$string['goodanswer_text']              = 'Well done. That is the correct answer.';
$string['badanswer_title']              = 'No: That is the wrong answer';
$string['badanswer_text']               = 'That is the wrong answer. It is important to get the correct answer on your first attempt';

// Text for layour auto-save frames
$string['save-confirm-title']           = 'Save Layout Changes?';
$string['save-confirm-text']            = 'You have made changs to the document layout that have not been saved. If you do not save them now then they may be lost';
$string['label_save']                   = 'Save Now';
$string['label_nosave']                 = 'Do Not Save';

// Text for upload frames
$string['upload-input-title']           = 'Upload a PDF document';
$string['upload-input-text']            = ''
    . 'Choose a pdf document to upload<br><br>'
    . 'NOTE: The file size limit for your server will be configured by your system administrators. If you are having trouble uploading a very large file then please check with your administrators.<br>'
    ;
$string['upload-wait-title']            = 'Uploading Document';
$string['upload-wait-text']             = ''
    . 'Your file is being uploaded to the server.<br>'
    . 'This operation may take a little time.<br><br>'
    . 'Once uploaded the file will be processed on the server.<br><br>'
    . 'This message may vanish for a moment while the server is processing.<br<br>'
    . 'Please do not refresh your browser page or navigate to a different page while the upload is in progress.<br>'
    ;
$string['label_upload']                 = 'Upload';
$string['label_upload_complete']        = 'Done';

// Alert texts
$string['alert_uploadnofile']           = 'To get started please upload a PDF file';
$string['alert_uploadsuccess']          = 'Congratulations. Your document has been uploaded.';
$string['alert_uploadfailed']           = 'Upload failed - please try again or contact your system administrator';
$string['alert_firstMaskAdded']         = 'Drag the mask to move and resize it';
$string['alert_questionSaved']          = 'Changes have been saved';
$string['alert_changesSaved']           = 'Changes have been saved';
$string['alert_saveStyleChange']        = 'Click the SAVE NOW button to save the style change';
$string['alert_savePageHidden']         = 'Hide page from students: Click the SAVE NOW to save this change';
$string['alert_saveMaskHidden']         = 'Hide mask from students: Click the SAVE NOW to save this change';
$string['alert_saveDeletion']           = 'Delete mask: Click the SAVE NOW to delete it forever';
$string['alert_saveChanges']            = 'Click the SAVE NOW button to save unsaved changes';
$string['alert_studentGradePass']       = 'CORRECT';
$string['alert_studentGradeDone']       = '';
$string['alert_studentGradeFail']       = 'WRONG ANSWER';
$string['alert_gradeNamePass']          = 'Correct Answers';
$string['alert_gradeNameToGo']          = 'Questions Remaining';

// Textes sent down to the javascript for dynamic use in browser
$string['navigateaway']                 = 'You have made changs that have not been saved\nTo save them click on \"'.$string['label_save'].'\"';

// Text strings for different mask types
$string['add-mask-qcm']                 = 'Multiple Choice Question';
$string['add-mask-qtxt']                = 'Simple Question';
$string['add-mask-basic']               = 'Dismissable Note';
$string['add-mask-note']                = 'Permanent Note';

$string['title_new_qcm']                = 'New Multiple Choice Question';
$string['title_new_qtxt']               = 'New Simple Question';
$string['title_new_basic']              = 'New Dismissable Note';
$string['title_new_note']               = 'New Permanent Note';

$string['title_edit_qcm']               = 'Multiple Choice Question';
$string['title_edit_qtxt']              = 'Simple Question';
$string['title_edit_basic']             = 'Dismissable Note';
$string['title_edit_note']              = 'Permanent Note';

$string['edithelp_qcm']                 = ''
    . 'This mask type displays a question when it is clicked. When the student submits the correct answer to the question the mask vanishes revealing the text beneath.<br><br>'
    . 'Enter a question, a correct answer and one or more incorrect alternatives.<br><br>'
    . 'The alternative answers will be reordered when presented to students, with different students seeing the answers in different orders.<br><br>'
    . 'When a student submits a correct answer a standard "that is correct" text is displayed. You may optionally provide a Correct Hint Response to replace the default text.<br><br>'
    . 'In the same way, when a student submits a correct answer a standard "that is wrong" text is displayed. You may optionally provide an Incorrect Hint Response to replace the default text.<br><br>'
    . 'You may optionally add a hint text that will be displayed in exactly the same way as this help text.'
    ;
$string['edithelp_qtxt']                 = ''
    . 'This mask type displays a question when it is clicked. When the student submits the correct answer to the question the mask vanishes revealing the text beneath.<br><br>'
    . 'Enter a question, and one or more correct answers (one per line in the "answer" box below).<br><br>'
    . 'When a student submits a correct answer a standard "that is correct" text is displayed. You may optionally provide a Correct Hint Response to replace the default text.<br><br>'
    . 'In the same way, when a student submits a correct answer a standard "that is wrong" text is displayed. You may optionally provide an Incorrect Hint Response to replace the default text.<br><br>'
    . 'You may optionally add a hint text that will be displayed in exactly the same way as this help text.'
    ;
$string['edithelp_basic']               = ''
    . 'This mask type displays a note when it is clicked.<br><br>'
    . 'When the student closes the note the mask vanishes revealing the text beneath.'
    ;
$string['edithelp_note']                = ''
    . 'This mask type displays a note whenever it is clicked.<br><br>'
    . 'It is intended to be used to add comments and reminders to documents.'
    ;

