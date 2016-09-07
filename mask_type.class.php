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
 * masks masked pdf activity Base class for mask types
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_masks;

// use bit mask to compose field type
define( 'MASKS_FIELD_OPTIONAL', 0 );
define( 'MASKS_FIELD_REQUIRED', 1 );
define( 'MASKS_FIELD_TEXT', 2 );
define( 'MASKS_FIELD_TEXTAREA', 4 );


abstract class mask_type{

    //-------------------------------------------------------------------------
    // Protected Data
    
    // moodle execution environment (determined at the start of the page and stored here for use as required)
    protected $course       = null;
    protected $cm           = null;
    protected $masksInstance = null;
    protected $activeMask   = null;


    //-------------------------------------------------------------------------
    // Public API
    
    public function applyMoodleEnvironment( $course, $cm, $masksInstance ){
        $this->course       = $course;
        $this->cm           = $cm;
        $this->masksInstance = $masksInstance;
    }
    
    public function setActiveMask( $maskId ){
        $this->activeMask   = $maskId;
    }


    //-------------------------------------------------------------------------
    // Abstract Public API
    
    /* Method used to process a 'new mask' page
     * @param integer $id a course module instance id ($cm->id)
     * @param integer $pageId the masks_page db row to which the new mask should be assigned
     */
    abstract function onNewMask( $id, $pageId );

    /* Method used to process an 'edit mask' page
     * @param integer $id a course module instance id ($cm->id)
     * @param integer $maskId the masks_mask db row to be editted
     * @param integer $questionId the masks_question db row corresponding to $maskId
     * @param object $questionData the form data retrieved from the database
     */
    abstract function onEditMask( $id, $maskId, $questionId, $questionData );
    
    /* Method used to process a studen't interaction with a mask (the 'click on mask' page)
     * @param integer $questionId the masks_question db row corresponding to $maskId
     * @param object $questionData the form data retrieved from the database
     * @param array $hiddenFields the set of hidden parameters that need to be posted back with form submission
     * @return boolean true if the action results in mask being closed else false
     */
    abstract function onClickMask( $questionId, $questionData, $hiddenFields );
    

    //-------------------------------------------------------------------------
    // Protected helper functions
    
    protected function doNewMask( $id, $pageId, $maskType, $fields, $dbInterface, $flags ){
        // do we have a complete data set?
        $haveData = $this->haveData( $fields );

        // if the required parameters weren't found then just resubmit the form
        if ( $haveData !== true ){
            // render the form
            $hiddenFields = array( 'id' => $id, 'pageid' => $pageId );
            $this->renderEditForm( 'new_'.$maskType, $maskType, 'frame_new_mask.php', $fields, $_GET, array(), $hiddenFields );
        } else {
            // fetch the data that has been submitted and pack it into a json record for storage
            $newData        = $this->fetchSubmittedData( $fields );
            $newData->type  = $maskType;
            $jsonData       = json_encode( $newData );

            // write new record to database and retrieve updated full data snapshot
            $resultData = new \stdClass;
            $resultData->newMask = $dbInterface->addMask( $id, $pageId, $jsonData, $flags );
            $resultData->maskData = $dbInterface->fetchMaskData($id);

            // encode the result data and the script to apply it
            $jsData = 'var masksData =' . json_encode( $resultData );
            echo \html_writer::script( $jsData );
            $jsAction = '';
            $jsAction .= 'parent.M.mod_masks.applyMaskData(masksData.maskData);';
            $jsAction .= 'parent.M.mod_masks.selectMask(masksData.newMask);';
            $jsAction .= 'parent.M.mod_masks.closeFrame();';
            if ($resultData->maskData->count==1){
                $jsAction .= 'parent.M.mod_masks.setAlertSuccess("firstMaskAdded");';
            }
            echo \html_writer::script( $jsAction );
        }
    }

    protected function doEditMask( $id, $maskId, $questionId, $questionData, $maskType, $fields, $dbInterface ){
        // do we have a complete data set?
        $haveData = $this->haveData( $fields );

        // if the required parameters weren't found then just resubmit the form
        if ( $haveData !== true ){
            // render the form
            $hiddenFields = array( 'id' => $id, 'mid' => $maskId, 'qid' => $questionId );
            $this->renderEditForm( 'edit_'.$maskType, $maskType, 'frame_edit_mask.php', $fields, $_GET, (array)$questionData, $hiddenFields );
        } else {
            // fetch the data that has been submitted and pack it into a json record for storage
            $newData = $this->fetchSubmittedData( $fields );

            // write modified record to database
            $resultData = new \stdClass;
            $dbInterface->updateQuestion( $questionId, $newData );

            // encode the result data and the script to apply it
            $jsData = 'var masksData =' . json_encode( $resultData );
            echo \html_writer::script( $jsData );
            $jsAction = '';
            $jsAction .= 'parent.M.mod_masks.setAlertSuccess("questionSaved");';
            $jsAction .= 'parent.M.mod_masks.closeFrame();';
            echo \html_writer::script( $jsAction );
        }
    }

    protected function fetchSubmittedData( $fields ){
        // encode question data
        $newData = new \stdClass;
        foreach( $fields as $field => $flags ){
            $newData->$field = htmlentities($_GET[ $field ]);
        }
        
        return $newData;
    }

    protected function renderEditForm( $contextName, $maskType, $target, $fields, $refData0, $refData1, $hiddenFields ){
        // setup the form writer helper object
        require_once('./form_writer.class.php');
        $formWriter = new \mod_masks\form_writer( $refData0, $refData1 );

        // include stylesheet
        echo $this->getStylesheetTag();

        // open page root tag
        echo \html_writer::start_tag( 'div', array( 'id' => 'masks-frame', 'class' => 'mask-edit-form hide-hint' ) );

        // display a help section with a toggle button and a hideable text zone
        $helpText = get_string( 'edithelp_'.$maskType, 'mod_masks' );
        $clickScript = 'document.getElementById("masks-frame").classList.toggle("hide-hint");';
        $strToggleHelp  = get_string( 'label_togglehelp', 'mod_masks' );
        echo \html_writer::start_div( 'frame-section hint-section' );
        echo \html_writer::start_div( 'frame-sub-section button-sub-section' );
        echo \html_writer::tag( 'button', $strToggleHelp, array( 'class' => 'hide-toggle', 'onclick' => $clickScript ) );
        echo \html_writer::end_div();
        echo \html_writer::start_div( 'frame-sub-section text-sub-section' );
        echo \html_writer::div( $helpText, 'frame-text hint' );
        echo \html_writer::end_div();
        echo \html_writer::end_div();

        // add page header
        $strTitle = get_string( 'title_'.$contextName, 'mod_masks' );
        echo \html_writer::start_div( 'frame-header' );
        echo \html_writer::div( $strTitle, 'frame-title' );
        echo \html_writer::end_div();

        // open a frame body tag to contain all of our question content
        $formWriter->openForm($target,$hiddenFields);
        $formWriter->addHidden('masktype',$maskType);
        echo \html_writer::start_div( 'frame-body' );

        // add the visible form fields
        echo \html_writer::start_div( 'frame-section question-section' );
        foreach( $fields as $field => $flags ){
            $requiredFlag   = ( $flags & MASKS_FIELD_REQUIRED ) != 0;
            $fieldType      = $flags & ~MASKS_FIELD_REQUIRED;
            switch ( $fieldType ){
                case MASKS_FIELD_TEXT:
                    $formWriter->addTextField( $field, $requiredFlag );
                    break;

                case MASKS_FIELD_TEXTAREA;
                    $formWriter->addTextArea( $field, $requiredFlag );
                    break;

                default:
                    throw new \Exception( "unrecognised form field type for field: $maskType > $field" );
            }
        }
        echo \html_writer::end_div();

        // close the form and frame body, adding submit buttins and suchlike
        echo \html_writer::end_div();
        $formWriter->closeForm();

        // close page root tag
        echo \html_writer::end_tag( 'div' );
    }

    protected function renderInfoPage( $title, $body, $cssClass, $buttonCode ){
        // include stylesheet
        echo $this->getStylesheetTag();

        // open root tag
        echo \html_writer::start_tag( 'div', array( 'id' => 'masks-frame', 'class' => $cssClass ) );

        // add page header
        echo \html_writer::start_div( 'frame-header' );
        echo \html_writer::div( $title, 'frame-title' );
        echo \html_writer::end_div();

        // add page body
        echo \html_writer::start_div( 'frame-body' );
        echo \html_writer::div( $this->renderBodyText( $body ), 'frame-text' );
        echo \html_writer::end_div();

        // add page footer
        echo \html_writer::start_div( 'frame-footer' );
        $strClose = get_string( 'label_close', 'mod_masks' );
        echo \html_writer::tag( 'button', $strClose, array( 'onclick' => $buttonCode ) );
        echo \html_writer::end_div();

        // close root tag
        echo \html_writer::end_tag( 'div' );
    }

    protected function renderQuestionPage( $hintText, $questionText, $answerHTML, $hiddenFields, $dbInterface, $questionId ){
        global $USER;

        // update the database to inform it that we have seen the question
        $isFirstAttempt = $dbInterface->isFirstQuestionAttempt( $USER->id, $questionId );
        $updatedGrades  = $dbInterface->updateUserQuestionState( $this->cm, $USER->id, $questionId, 'VIEW' );
        echo \html_writer::script( $this->getGradeUpdateScript( $updatedGrades ) );

        // include stylesheet
        echo $this->getStylesheetTag();

        // open page root tag
        echo \html_writer::start_tag( 'div', array( 'id' => 'masks-frame', 'class' => 'hide-hint' ) );

        // setup the form writer helper object
        require_once('./form_writer.class.php');
        $formWriter = new \mod_masks\form_writer();

        // open a frame body tag to contain all of our question and hint content
        echo \html_writer::start_div( 'frame-body' );

        // if we have a hint then display a hint section with a toggle button and a hideable text zone
        if ( ! empty( $hintText ) && !$isFirstAttempt ){
            $clickScript = 'document.getElementById("masks-frame").classList.toggle("hide-hint");';
            $strToggleHint  = get_string( 'label_togglehint', 'mod_masks' );
            echo \html_writer::start_div( 'frame-section hint-section' );
            echo \html_writer::start_div( 'frame-sub-section button-sub-section' );
            echo \html_writer::tag( 'button', $strToggleHint, array( 'class' => 'hide-toggle', 'onclick' => $clickScript ) );
            echo \html_writer::end_div();
            echo \html_writer::start_div( 'frame-sub-section text-sub-section' );
            echo \html_writer::div( $hintText, 'frame-text hint' );
            echo \html_writer::end_div();
            echo \html_writer::end_div();
        }

        // open the form and add hidden fields
        $formWriter->openForm('frame_click_mask.php',$hiddenFields);

        // add question
        echo \html_writer::start_div( 'frame-section question-section' );
        echo \html_writer::div( $this->renderBodyText( $questionText ), 'frame-text question' );
        echo \html_writer::end_div();

        // add answer        
        echo \html_writer::start_div( 'frame-section answer-section' );
        echo \html_writer::start_div( 'answer' );
        echo $answerHTML;
        echo \html_writer::end_div();
        echo \html_writer::end_div();

        // close frame body tag
        echo \html_writer::end_div();

        // close the form, adding submit buttons and suchlike
        $formWriter->closeForm();

        // close page root tag
        echo \html_writer::end_tag( 'div' );
    }

    protected function renderAnswerResponsePage( $answerIsCorrect, $goodAnswerResponse, $badAnswerResponse, $dbInterface, $questionId ){
        // inform the database interface of the student's good or bad response
        global $USER;
        
        if ( $answerIsCorrect ){
            // update the database to inform it that we have passed the question
            $updatedGrades      = $dbInterface->updateUserQuestionState( $this->cm, $USER->id, $questionId, 'PASS' );
            $gradeUpdateScript  = $this->getGradeUpdateScript( $updatedGrades );

            // display a congratulations message and show a button to close the window and dismiss the mask
            $strGoodAnswerTitle = get_string( 'goodanswer_title', 'mod_masks' );
            $strGoodAnswerText  = get_string( 'goodanswer_text', 'mod_masks' );
            $strText = ( ! empty( $goodAnswerResponse ) )? $goodAnswerResponse: $strGoodAnswerText;
            $this->renderInfoPage( $strGoodAnswerTitle, $strText, 'good-answer', $gradeUpdateScript.'parent.M.mod_masks.closeMask(); parent.M.mod_masks.closeFrame();' );
        } else {
            // update the database to inform it that we have failed the question
            $updatedGrades      = $dbInterface->updateUserQuestionState( $this->cm, $USER->id, $questionId, 'FAIL' );
            $gradeUpdateScript  = $this->getGradeUpdateScript( $updatedGrades );

            // display a wrong answer message and show a button to close the window without dismissing the mask
            $strBadAnswerTitle = get_string( 'badanswer_title', 'mod_masks' );
            $strBadAnswerText  = get_string( 'badanswer_text', 'mod_masks' );
            $strText = ( ! empty( $badAnswerResponse ) )? $badAnswerResponse: $strBadAnswerText;
            $this->renderInfoPage( $strBadAnswerTitle, $strText, 'bad-answer', $gradeUpdateScript.'parent.M.mod_masks.closeFrame();' );
        }
    }

    protected function renderBodyText( $txt ){
        return "<pre>$txt\n</pre>";
    }

    protected function getGradeUpdateScript( $updatedGrades ){
        $result = ( $updatedGrades == 0 )? '': "parent.M.mod_masks.setMaskState( $this->activeMask, $updatedGrades );";

        if ( ( $updatedGrades & MASKS_STATE_PASS ) == MASKS_STATE_PASS ){
            $result .= 'parent.M.mod_masks.onMaskPass();';
        }
        else if ( ( $updatedGrades & MASKS_STATE_DONE + MASKS_STATE_FAIL ) == MASKS_STATE_DONE + MASKS_STATE_FAIL ){
            $result .= 'parent.M.mod_masks.onMaskDoneAfterFail();';
        }
        else if ( ( $updatedGrades & MASKS_STATE_FAIL ) == MASKS_STATE_FAIL ){
            $result .= 'parent.M.mod_masks.onMaskFail();';
        }
        else if ( ( $updatedGrades & MASKS_STATE_DONE ) == MASKS_STATE_DONE ){
            $result .= 'parent.M.mod_masks.onMaskDone();';
        }
        
        return $result;
    }

    protected function haveData( $fields ){
        $haveData = true;
        foreach( $fields as $field => $flags ){
            $required = ( $flags & MASKS_FIELD_REQUIRED ) != 0;
            $haveData = $haveData && ( $required == false ) || ( array_key_exists( $field, $_GET ) && !empty( $_GET[$field] ) );
        }
        return $haveData;
    }

    private function getStylesheetTag(){
        return \html_writer::tag( 'link', '', array( 'rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'frame_styles.css' ) );
    }
}

