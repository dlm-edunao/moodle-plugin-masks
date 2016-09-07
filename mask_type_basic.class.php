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

class mask_type_basic extends mask_type{
    //-------------------------------------------------------------------------
    // data

    private $maskType       = 'basic';
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
            'title' => MASKS_FIELD_TEXT + MASKS_FIELD_REQUIRED,
            'note'  => MASKS_FIELD_TEXTAREA + MASKS_FIELD_REQUIRED,
        );
    }


    //-------------------------------------------------------------------------
    // mask_type API

    public function onNewMask( $id, $pageId ){
        // delegate work to generic method in base class
        $this->doNewMask( $id, $pageId, $this->maskType, $this->fields, $this->dbInterface, MASK_FLAGS_CLOSABLE );
    }

    public function onEditMask( $id, $maskId, $questionId, $questionData ){
        // delegate work to generic method in base class
        $this->doEditMask( $id, $maskId, $questionId, $questionData, $this->maskType, $this->fields, $this->dbInterface );
    }

    public function onClickMask( $questionId, $questionData, $hiddenFields ){
        global $USER;

        // update the database to signal that this element has been seen
        $this->dbInterface->updateUserQuestionState( $this->cm, $USER->id, $questionId, 'DONE' );

        // delegate work to generic method in base class
        $this->renderInfoPage( $questionData->title, $questionData->note, 'info once', 'parent.M.mod_masks.closeMask(); parent.M.mod_masks.closeFrame();' );

        // we always close a basic mask after viewing the popup so return true
        return true;
    }
}

