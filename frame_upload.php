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


///////////////////////////////////////////////////////////////////////////
// Set apache/php configuration directives to allow big files to be uploaded
// and time to be taken in processing

// set 'no memory limit'
@ini_set('memory_limit',-1);

// set 'no input time limit'
@ini_set('max_input_time',-1);
// set no time limit (by overriding ini file parameters)
@ini_set('max_execution_time',0);
// set no time limit (using intrinsic call)
set_time_limit( 0 );

// disable output but=ffering and compression in order to allow logs to be output in real time
@ini_set('output_buffering',0);
@ini_set('zlib.output_compression',0);
@ini_set('implicit_flush',1);


///////////////////////////////////////////////////////////////////////////
// prepare to render a moodle page

require_once('../../config.php');


///////////////////////////////////////////////////////////////////////////
// _GET / _POST parameters

$id             = required_param('id', PARAM_INT);

// determine whether we have data or not (if we don't then we need to display the form)
$haveData       = array_key_exists( 'docfile', $_FILES );


///////////////////////////////////////////////////////////////////////////
// Data from moodle

$cm         = get_coursemodule_from_id('masks', $id, 0, false, MUST_EXIST);
$instance   = $DB->get_record('masks', array('id'=>$cm->instance), '*', MUST_EXIST);
$course     = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);


///////////////////////////////////////////////////////////////////////////
// Sanity tests

require_course_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/masks:addinstance', $context);


///////////////////////////////////////////////////////////////////////////
// page rendering

// construct the 'move on to the next thing' js code to execute when we're all done
$jsCloseFrame       = 'parent.M.mod_masks.closeFrame();event.preventDefault();';

// if the required parameters weren't found then just resubmit the form
if ( $haveData !== true ){

    // include stylesheet
    echo \html_writer::tag( 'link', '', array( 'rel' => 'stylesheet', 'type' => 'text/css', 'href' => 'frame_styles.css' ) );

    // open root tag
    echo \html_writer::start_tag( 'div', array( 'id' => 'masks-frame', 'class' => 'upload' ));

    // open a tag for englobing the input form and related content
    echo \html_writer::start_tag( 'div', array( 'class' => 'upload-input' ) );
    echo html_writer::start_tag('form', array(
        'action'=>new moodle_url( '/mod/masks/frame_upload.php' ),
        'method'=>'post', 
        'enctype'=>'multipart/form-data', 
        'onsubmit'=>'document.getElementById("masks-frame").classList.toggle("upload-running");parent.M.mod_masks.iframeUpdateHeight()'
    ));
    echo html_writer::tag('input','',array('type'=>'hidden', 'name'=>'id', 'value'=>$id));

    // add page header
    $title = get_string('upload-input-title', 'mod_masks');
    echo \html_writer::start_div( 'frame-header' );
    echo \html_writer::div( $title, 'frame-title' );
    echo \html_writer::end_div();

    // open page body
    $body = get_string('upload-input-text', 'mod_masks');
    echo \html_writer::start_div( 'frame-body' );
    
    // display the upload instructions
    echo \html_writer::start_div( 'frame-section instructions' );
    echo \html_writer::div( $body, 'frame-text' );
    echo \html_writer::end_div();

    // construct the file selector
    echo \html_writer::start_div( 'frame-section upload-widget', array( 'ondrop' => 'event.stopPropagation();' ) );
    echo html_writer::tag('input','',array('id'=>'fileselector', 'type'=>'file', 'accept'=>'application/pdf', 'name'=>'docfile'));
    echo \html_writer::end_div();

    // close page body
    echo \html_writer::end_div();

    // add page footer
    echo \html_writer::start_div( 'frame-footer' );
    $strCancel  = get_string( 'label_cancel', 'mod_masks' );
    $strUpload  = get_string( 'label_upload', 'mod_masks' );
    echo \html_writer::tag( 'button', $strCancel, array( 'onclick' => $jsCloseFrame ) );
    echo html_writer::tag('input','',array('type'=>'submit','value'=>$strUpload));
    echo \html_writer::end_div();

    // close input-englobing tag
    echo html_writer::end_tag('form');
    echo \html_writer::end_tag( 'div' );
    
    // open  a parent tag for content to be displayed while waiting for upload to complete
    echo \html_writer::start_tag( 'div', array( 'class' => 'upload-wait' ) );

    // add page header
    $title = get_string('upload-wait-title', 'mod_masks');
    echo \html_writer::start_div( 'frame-header' );
    echo \html_writer::div( $title, 'frame-title' );
    echo \html_writer::end_div();

    // add page body
    $body = get_string('upload-wait-text', 'mod_masks');
    echo \html_writer::start_div( 'frame-body' );
    echo \html_writer::start_div( 'frame-section wait-text' );
    echo \html_writer::div( $body, 'frame-text' );
    echo \html_writer::end_div();
    echo \html_writer::end_div();
    
    // add a rotating gif to keep people patient
    echo \html_writer::div( '', 'uploading-img' );

    // close the 'waiting' parent tag    
    echo \html_writer::end_tag( 'div' );

    // close root tag
    echo \html_writer::end_div();

    // stop execution here (as we're all done)
    die();
}

///////////////////////////////////////////////////////////////////////////
// form data processing

// fetch the config record for the plugin
$config = get_config('mod_masks');

// instantiate an LMS interface object
require_once('upload_policies.class.php');
$policies   = new \mod_masks\upload_policies( $cm );

// instantiate a pdf upload processor object
require_once('upload_processor.class.php');
$processor  = new \mod_masks\upload_processor( $policies, $config );

// process the request data
$processor->process( $_FILES['docfile'], $id );


///////////////////////////////////////////////////////////////////////////
// Fetch data from the database

require_once('database_interface.class.php');
$dbInterface = new mod_masks\database_interface;
$docData = $dbInterface->fetchDocData( $cm->id );
$maskData = $dbInterface->fetchMaskData( $cm->id );


///////////////////////////////////////////////////////////////////////////
// Output new data to the page and the script to apply it

// generate the output script for setting up data structures
require_once(dirname(__FILE__).'/locallib.php');
echo generateMasksJSPageData( $docData, 'masks_pages' );
echo generateMasksJSMaskData( $maskData, 'masks_masks' );

// Apply the data to the parent
$jsAction = '';
$jsAction .= 'parent.M.mod_masks.applyPageData(masks_pages);';
$jsAction .= 'parent.M.mod_masks.applyMaskData(masks_masks);';
echo html_writer::script( $jsAction );


///////////////////////////////////////////////////////////////////////////
// Append a 'done' button to close the frame

$strDone    = get_string('label_upload_complete', 'mod_masks');
$doneAction = 'parent.M.mod_masks.closeFrame();';
$doneButton = \html_writer::tag( 'button', $strDone, array( 'onclick' => $doneAction ) );
$doneScript = \html_writer::script( $doneAction );

if ( ( count( $docData->pages ) > 0 ) && ! ( $config->debug > 0 ) ){
    echo \html_writer::script( 'parent.M.mod_masks.setAlertSuccess("uploadSuccess");' );
    echo $doneScript;
} else {
    if ( count( $docData->pages ) == 0 ){
        echo \html_writer::script( 'parent.M.mod_masks.setAlertWarn("uploadFail");' );
    } else {
        echo \html_writer::script( 'parent.M.mod_masks.setAlertSuccess("uploadSuccess");' );
    }
    echo $doneButton;
}

