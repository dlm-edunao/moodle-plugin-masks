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
 * MASKS Activity Module - class for abstracting database access
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_masks;

// Flags for pages
define( 'mod_masks\PAGE_FLAG_HIDDEN', 1 );

// Individual flags for masks
define( 'mod_masks\MASK_FLAG_HIDDEN', 0x01 );
define( 'mod_masks\MASK_FLAG_GRADED', 0x02 );
define( 'mod_masks\MASK_FLAG_CLOSABLE', 0x04 );
define( 'mod_masks\MASK_FLAG_DELETED', 0x80 );
// Combined flag sets for masks
define( 'mod_masks\MASK_FLAGS_NOTE', 0 );
define( 'mod_masks\MASK_FLAGS_CLOSABLE', MASK_FLAG_CLOSABLE );
define( 'mod_masks\MASK_FLAGS_QUESTION', MASK_FLAG_CLOSABLE | MASK_FLAG_GRADED );

// User question state mask state flags
// NOTE: the order of the constants is important as state changes will only be applied if the state value increases
// NOTE: the js code assumes that the state flags occupy no more than the 16 low bits of an integer value
define('MASKS_STATE_SEEN',0x10); // seen but not done
define('MASKS_STATE_DONE',0x20); // seen and closed
define('MASKS_STATE_FAIL',0x40); // at least one wrong answer given 
define('MASKS_STATE_PASS',0x80); // correct answer given with no wrong answers


class database_interface {

    /**
     * Fetch document data - that is the information regarding the pages that comprise the document
     *
     * @param integer $cmid The course module instance id ($cm->id)
     * @return struct the document representation
     */
    public function fetchDocData( $cmid ){
        global $DB;
     
        // initalise the result container   
        $result = new \stdClass;
        
        // fetch the set of records from the database
        $query =
            'SELECT page.orderkey, page.id, page.flags, page.docpage, docpage.doc, docpage.pagenum, docpage.imagename, docpage.w, docpage.h'.
            ' FROM {masks_page} AS page'. 
            ' JOIN {masks_doc_page} AS docpage ON docpage.id = page.docpage'. 
            ' WHERE page.parentcm = :cmid'.
            ' ORDER BY page.orderkey'.
            '';
        $result->pages = $DB->get_records_sql($query, array('cmid'=>$cmid));
        $result->isInitialised = ! empty( $result->pages );

        // generate image urls from image names
        $context = \context_module::instance( $cmid );
        foreach( $result->pages as $page ){
            $url = \moodle_url::make_pluginfile_url( $context->id, 'mod_masks', 'masks_doc_page', $page->docpage, '/', $page->imagename );
            $page->imageurl = strval( $url );
        }

        return $result;
    }

    /**
     * Fetch mask data - that is the information regarding the set of masks overlaid over document pages
     *
     * @param integer $cmid The course module instance id ($cm->id)
     * @return struct the mask set representation
     */
    public function fetchMaskData( $cmid ){
        global $DB, $USER;
     
        // initalise the result container   
        $result = new \stdClass;
        
        // fetch the set of mask records from the database
        $query =
            'SELECT mask.*, user.state AS userstate'.
            ' FROM {masks_page} AS page'.
            ' JOIN {masks_mask} AS mask ON page.id = mask.page'.
            ' LEFT JOIN {masks_user_state} AS user ON user.question = mask.question AND user.user = :user'.
            ' WHERE page.parentcm = :cmid'.
            ' AND (mask.flags & '.MASK_FLAG_DELETED.') = 0'.
            ' ORDER BY page.id, mask.id'.
            '';
        $masks = $DB->get_records_sql( $query, array( 'cmid'=>$cmid, 'user'=>$USER->id ) );

        // construct the result, group the masks by page
        $result->pages = array();
        $result->count = count( $masks );
        foreach($masks as $mask){
            $page = $mask->page;
            if ( ! array_key_exists( $page, $result->pages ) ){
                $result->pages[$page] = array();
            }
            $result->pages[$page][] = $mask;
        }

        return $result;
    }

    /**
     * Fetch question data
     *
     * @param integer $questionId The question id
     * @return the decode question data record
     */
    public function fetchQuestionData( $questionId ){
        global $DB, $USER;
     
        // initalise the result container   
        $result = new \stdClass;
        
        // fetch the question record from the database
        $record = $DB->get_record('masks_question', array('id'=>$questionId), 'id,data' );

        // construct the result
        $result = json_decode( $record->data );

        return $result;
    }

    /**
     * Instantiate a new masks_doc database row
     *
     * @param integer $cmId The course module id ($cm->id) of the cm object representing the masks activity instance
     * @param string $fileName The name of the file that has been uploaded
     * @param integer $pageCount The number of page images that have been extracted from the uploaded file
     * @return integer identifier of new row
     */
    public function getNewDoc( $cmid, $fileName, $pageCount ){
        global $DB;
        $row = new \stdClass;
        $row->parentcm  = $cmid;
        $row->created   = time();
        $row->filename  = $fileName;
        $row->pages     = $pageCount;
        $newRow = $DB->insert_record( 'masks_doc', $row );
        return $newRow;
    }

    /**
     * Instantiate a new masks_doc database row
     *
     * @param integer $docId The identifier of the masks_doc that the page belongs to (as returned by getNewDoc())
     * @param integer $pageNumber The pdf file page number that the new object is intended to represent
     * @return integer identifier of new row
     */
    public function getNewDocPage( $docId, $pageNumber ){
        global $DB;
        $row = new \stdClass;
        $row->doc       = $docId;
        $row->pagenum   = $pageNumber;
        $newRow = $DB->insert_record( 'masks_doc_page', $row );
        return $newRow;
    }

    /**
     * Fill in the doc page parameters that are not supplied in the call to getNewDocPage()
     *
     * @param integer $docPageId The identifier of the row to update
     * @param string $imageName The file name of the image that should be rendered to display this page 
     * @param integer $width The width of the image that represents the page 
     * @param integer $height The height of the image that represents the page 
     */
    public function populateDocPage( $docPageId, $imageName, $width, $height ){
        global $DB;
        $row = new \stdClass;
        $row->id        = $docPageId;
        $row->imagename = strval( $imageName );
        $row->w         = $width;
        $row->h         = $height;
        $DB->update_record( 'masks_doc_page', $row );
    }

    /**
     * Assign a set of images to the pages of an masks exercise
     *
     * @param integer $cmid The course module instance id ($cm->id)
     * @param array $docPageIds An array of integer masks_doc_page identifiers that identify the images to be used
     */
    public function assignPages( $cmid, $docPageIds ){
        global $DB;
        // start by retrieving the identifiers of any existing masks_page records
        // that exist for this module instance
        $oldPages = $DB->get_records( 'masks_page', array('parentcm'=>$cmid), 'orderkey', 'orderkey,id,flags' );

        // sort the new page set by key
        ksort( $docPageIds );
        
        // iterate over the new pages and old pages together
        $idx = 0;
        $oldCount = count( $oldPages );
        foreach( $docPageIds as $docPage ){
            if ( $idx < $oldCount ){
                // we have a spare record to use so go for it
                $row = new \stdClass;
                $row->id        = $oldPages[ $idx ]->id;
                $row->orderkey  = $idx;
                $row->docpage   = $docPage;
                $DB->update_record( 'masks_page', $row, true );
            } else {
                // we need to add a new record
                $row = new \stdClass;
                $row->orderkey  = $idx;
                $row->docpage   = $docPage;
                $row->parentcm  = $cmid;
                $DB->insert_record( 'masks_page', $row );
            }
            ++$idx;
        }

        // consider deleting any leftover pages
        $rowsToDelete = array();
        for(; $idx < $oldCount; ++$idx ){
            // look to see if any masks exist for this page
            $maskCount = $DB->count_records( 'masks_mask', array( 'page' => $oldPages[ $idx ]->id ) );
            if ( $maskCount > 0 ){
                // we have masks for this page so just update the flags
                $row = new \stdClass;
                $row->id        = $oldPages[ $idx ]->id;
                $row->orderkey  = $idx;
                $row->flags     = $oldPages[ $idx ]->flags | mod_masks\PAGE_FLAG_HIDDEN;
                $DB->update_record( 'masks_page', $row, true );
            } else {
                // have no masks so mark for deletion
                $rowsToDelete[] = $oldPages[ $idx ]->id;
            }
        }
        
        // if we have any rows to delete then go for it
        if ( ! empty( $rowsToDelete ) ){
            $DB->delete_records_list( 'masks_page', 'id', $rowsToDelete );
        }
    }

    /**
     * Instantiate a new question object and an associated mask
     *
     * @param integer $cmid The course module instance id ($cm->id)
     * @param integer $pageId the masks_page on which the mask is being added
     * @param string $questionData the json encoded question data blob
     * @param integer $flags - a bitmask of flags composed from the mask flags constants defined above
     * @returns integer mask id for newly created mask
     */
    public function addMask( $cmId, $pageId, $questionData, $flags ){
        global $DB;

        // start by instantiating the new question record
        $dbRecord           = new \stdClass;
        $dbRecord->parentcm = $cmId;
        $dbRecord->data     = $questionData;
        $questionId         = $DB->insert_record( 'masks_question', $dbRecord );

        // now add the mask record
        $dbRecord           = new \stdClass;
        $dbRecord->flags    = $flags;
        $dbRecord->question = $questionId;
        $dbRecord->page     = $pageId;
        $dbRecord->x        = 20;
        $dbRecord->y        = 20;
        $dbRecord->w        = 1000;
        $dbRecord->h        = 1000;
        $maskId             = $DB->insert_record( 'masks_mask', $dbRecord );
        
        // return the new mask id
        return $maskId;
    }

    /**
     * Update a question object
     *
     * @param integer $id The masks_question row for the question being updated
     * @param object $questionChanges the question content that has changed
     */
    public function updateQuestion( $questionId, $questionChanges ){
        global $DB;

        $questionData = $this->fetchQuestionData( $questionId );
        foreach( $questionChanges as $field => $value ){
            $questionData->$field = $value;
        }
        $jsonData = json_encode( $questionData );
        
        // start by instantiating the new question record
        $dbRecord           = new \stdClass;
        $dbRecord->id       = $questionId;
        $dbRecord->data     = $jsonData;
        $questionId         = $DB->update_record( 'masks_question', $dbRecord );
    }

    /**
     * Check whether the user has submitted an answer to this question yet
     *
     * @param integer $userId The $USER->id value for the user in question
     * @param integer $questionId The masks_question row for the question being updated
     * @return boolean true if the user has submitted no wrong for this question yet, else false
     */
    public function isFirstQuestionAttempt( $userId, $questionId ){
        global $DB;
        $failCount = $DB->get_field( 'masks_user_state', 'failcount', array( 'user'=>$userId, 'question'=>$questionId ) );
        return ( $failCount == null ) || ( $failCount == 0 );
    }

    /**
     * Update the state of a question as a result of user interaction
     * This routine will check to see whether the new state value is greater than the old state value
     * and only store changes if they actually make sense.
     * It will also update the gradebook as required 
     *
     * @param object $cm The course module instance that houses the question
     * @param integer $userId The $USER->id value for the user in question
     * @param integer $questionId The masks_question row for the question being updated
     * @param integer $stateName One of:
     *      'NONE' - the mask popup has not even been seen
     *      'VIEW' - the mask popup has been viewed but but the mask should be left visible
     *      'DONE' - the mask popup did not contain a graded question but the mask should now be hidden
     *      'FAIL' - the mask popup contained a graded question - the supplied answer was incorrect - the mask should be left visible
     *      'PASS' - the mask popup contained a graded question - the supplied answer was correct - the mask should now be hidden
     * @return integer $stateValue if the state was updated, 0 if it was not
     */
    public function updateUserQuestionState( $cm, $userId, $questionId, $stateName ){
        global $CFG, $DB;

        // convert the state name to a flag set value
        $stateNameValues = array( 
            'NONE' => 0, 
            'VIEW' => MASKS_STATE_SEEN, 
            'DONE' => MASKS_STATE_SEEN | MASKS_STATE_DONE,
            'FAIL' => MASKS_STATE_SEEN | MASKS_STATE_FAIL,
            'PASS' => MASKS_STATE_SEEN | MASKS_STATE_DONE | MASKS_STATE_PASS
        );
        $stateValue = $stateNameValues[ $stateName ];

        // fetch the existing state record (if there is one)
        $record = $DB->get_record('masks_user_state', array('question'=>$questionId, 'user'=>$userId), 'id,state,failcount' );
        if ( $record ){
            // Look for a state regression
            $oldStateValue  = $record->state;
            if ( $stateValue <= $oldStateValue ){
                // the state change goes backwards so ignore it
                return 0;
            }

            // If we have previously failed then convert a new 'pass,done,seen' to just 'done,seen'
            if ( ( $record->state & MASKS_STATE_FAIL ) != 0 ){
                $stateValue = $stateValue & ~MASKS_STATE_PASS;
            }

            // the state has progressed so update the database
            $record->lastupdate = time();
            $record->state      = $record->state | $stateValue;
            $record->failcount  += ( $stateValue & MASKS_STATE_FAIL ) / MASKS_STATE_FAIL;
            $record->lastupdate = time();
            $DB->update_record( 'masks_user_state', $record );

            // if we've failed then we're done as there is no chance of needing to update the PASS count
            if ( $stateName == 'FAIL' ){
                return $record->state;
            }
            $haveFailed         = ( $record->failcount > 0 );

        } else {
            // set an initial 'old state' value to indicate that this is the first visit
            $haveFailed         = false;

            // no previous record exists so insert a new one
            $record             = new \stdClass;
            $record->user       = $userId;
            $record->question   = $questionId;
            $record->state      = $stateValue;
            $record->failcount  = ( $stateValue & MASKS_STATE_FAIL ) / MASKS_STATE_FAIL;
            $record->firstview  = time();
            $record->lastupdate = $record->firstview;
            $DB->insert_record( 'masks_user_state', $record );
        }

        return $record->state;
    }

    /**
     * Calculate the user's score and update the moodle gradebook 
     *
     * @param object $cm The course module instance that's being graded
     * @param integer $userId The $USER->id value for the user in question
     */
    public function gradeUser( $cm, $userId ){
        global $CFG, $DB;
        require_once($CFG->libdir.'/gradelib.php');

        // count the number of questions
        $query=''
            .'SELECT count(*) AS result'
            .' FROM {masks_page} AS p'
            .' JOIN {masks_mask} AS m ON m.page = p.id'
            .' WHERE p.parentcm = :cmid'
            .' AND (m.flags & '.(MASK_FLAG_GRADED|MASK_FLAG_HIDDEN).')='.MASK_FLAG_GRADED
            ;
        $numQuestions = $DB->get_field_sql( $query, array( 'cmid'=>$cm->id ) );

        // count the number of correct answers
        $query=''
            .'SELECT count(*) as result'
            .' FROM {masks_question} q'
            .' JOIN {masks_user_state} s ON q.id = s.question'
            .' WHERE q.parentcm = :cmid'
            .' AND s.user = :userid'
            .' AND (s.state & '.MASKS_STATE_PASS.') > 0'
            ;
        $passes = $DB->get_field_sql( $query, array( 'cmid'=>$cm->id, 'userid'=>$userId ) );

        // calculate and apply the grade        
        $gradeValue     = $passes * 100.0 / $numQuestions;
        $gradeRecord    = array( 'userid'=>$userId, 'rawgrade'=>$gradeValue );
        $gradeResult    = \grade_update('mod/masks', $cm->course, 'mod', 'masks', $cm->instance, 0, $gradeRecord, null);
        if ( $gradeResult != GRADE_UPDATE_OK ){
            throw new \moodle_exception( 'Failed to update gradebook' );
        }        
    }
}

