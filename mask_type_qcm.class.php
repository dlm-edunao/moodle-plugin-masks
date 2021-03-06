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
 * Display masks plugin frame
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_masks;

require_once('./mask_type.class.php');

class mask_type_qcm extends mask_type{
    //-------------------------------------------------------------------------
    // data

    private $maskType       = 'qcm';
    private $dbInterface    = null;
    private $fields         = null;


    //-------------------------------------------------------------------------
    // basics
    
    public function __construct(){
        // Establish database connection
        require_once('./database_interface.class.php');
        $this->dbInterface = new database_interface;

        // Define the fields that are to appear in the question editing form
        $this->fields = array(
            'question'          => MASKS_FIELD_TEXTAREA + MASKS_FIELD_REQUIRED,
            'goodanswer'        => MASKS_FIELD_TEXT + MASKS_FIELD_REQUIRED,
            'badanswer0'        => MASKS_FIELD_TEXT + MASKS_FIELD_REQUIRED,
            'badanswer1'        => MASKS_FIELD_TEXT,
            'badanswer2'        => MASKS_FIELD_TEXT,
            'badanswer3'        => MASKS_FIELD_TEXT,
            'goodanswerhint'    => MASKS_FIELD_TEXTAREA,
            'badanswerhint'     => MASKS_FIELD_TEXTAREA,
            'userhint'          => MASKS_FIELD_TEXTAREA,
        );
    }


    //-------------------------------------------------------------------------
    // mask_type API
    
    public function onNewMask( $id, $pageId ){
        // delegate work to generic method in base class
        $this->doNewMask( $id, $pageId, $this->maskType, $this->fields, $this->dbInterface, MASK_FLAGS_QUESTION );
    }

    public function onEditMask( $id, $maskId, $questionId, $questionData ){
        // delegate work to generic method in base class
        $this->doEditMask( $id, $maskId, $questionId, $questionData, $this->maskType, $this->fields, $this->dbInterface );
    }

    public function onClickMask( $questionId, $questionData, $hiddenFields ){
        // identify the set of available answers
        $choices = array( 'goodanswer', 'badanswer0' );
        for ($i = 1; array_key_exists('badanswer'.$i,$this->fields); ++$i ){
            $propName = 'badanswer'.$i;
            if ( property_exists( $questionData, $propName ) && ! empty( $questionData->$propName ) ){
                $choices[] = $propName;
            }
        }

        // build an indirection table for shuffling the questions with
        $choiceIndex = array();
        for ($i = 0; $i < count( $choices ); ++$i){
            $choiceIndex[] = $i;
        }

        // setup a deterministic random CD based on the user's user id
        global $USER;
        $seed = intval($USER->id) + intval($questionId);
        srand($seed);

        // shuffle the questions
        for ($i = count( $choices ); $i > 1; --$i){
            $rand = rand();
            $slot = $rand % $i;
            $hold = $choiceIndex[ $slot ];
            $choiceIndex[ $slot ] = $choiceIndex[ $i - 1 ];
            $choiceIndex[ $i - 1 ] = $hold;
        }

        // has the user submitted ananswer?
        if ( ! array_key_exists( 'answer', $_GET ) ){
            // no, so we need to render the question

            // prepare and html blob for the answer entry
            $answerHTML = '';
            for ($i = 0; $i < count( $choices ); ++$i){
                $choice         = $choiceIndex[ $i ];
                $answerField    = $choices[ $choice ];
                $answer         = $questionData->$answerField;
                $answerHTML     .= \html_writer::start_div( 'option', array( 'onclick' => 'this.querySelector("input").checked=true;event.preventDefault();' ) );
                $answerHTML     .= \html_writer::tag( 'input', '', array( 'type' => 'radio', 'name' => 'answer', 'value' => $i ) );
                $answerHTML     .= \html_writer::span( $answer );
                $answerHTML     .= \html_writer::end_div();
            }

            // render the page (updating database etc as we do)
            $hintText       = property_exists( $questionData, 'userhint' )? $questionData->userhint: '';
            $questionText   = $questionData->question;
            $this->renderQuestionPage( $hintText, $questionText, $answerHTML, $hiddenFields, $this->dbInterface, $questionId );

            // return false as we don't have a result to evaluate yet
            return false;
        } else {
            // compare the answer to the correct answer
            $answer             = $_GET[ 'answer' ];
            $answerIsCorrect    = ( ( $answer >= 0 ) && ( $answer < count( $choiceIndex ) ) && ( $choiceIndex[ $answer ] == 0 ) );

            // render the response page (updating database etc as we do)
            $goodAnswerResponse = ( property_exists( $questionData, 'goodanswerhint' ) )? $questionData->goodanswerhint: '';
            $badAnswerResponse  = ( property_exists( $questionData, 'badanswerhint' ) )? $questionData->badanswerhint: '';
            $this->renderAnswerResponsePage( $answerIsCorrect, $goodAnswerResponse, $badAnswerResponse, $this->dbInterface, $questionId );

            // return true or false to represent 'question passed' of not            
            return $answerIsCorrect;
        }
    }
}

