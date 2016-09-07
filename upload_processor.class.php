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
 * MASKS Activity Upload processor class used for uploading and splitting pdf files into pages
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_rich_pdf
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_masks;

class upload_processor{

    //-------------------------------------------------------------------------
    // Public API - Basics
    //-------------------------------------------------------------------------
    
    /**
     * __construct()
     * @param object $config   - the configuration object containing miscellaneous config parameters
     * @param object $policies - the interface object that will provide all of the back end interfacing
     */
    public function __construct($policies,$config){
        $this->policies = $policies;
        $this->config   = $config;
    }

    
    //-------------------------------------------------------------------------
    // Public API - Main methods
    //-------------------------------------------------------------------------

    /**
     * process()
     * @param array $fileData - the $_FILES['file'] data sent by the web page (or it's equivalent in a test environmnet)
     * @param integer $cmid - the $cm->id value for the course module instance
     */
    public function process( $fileData, $cmid ){

        // Log start of the upload process 
        $this->policies->logProgressTitle( 'Processing uploaded file: ' . $fileData['name'] );

        // setup a temp folder to work with
        $this->policies->logProgressHeading('Creating Work Path');
        $workPath = $this->policies->getWorkFolderName();
        flush();
        
        // save the pdf file to the work folder
        $this->policies->logProgressHeading('Saving PDF File to Work Path');
        $pdfName = "doc.pdf";
        $pdfFile = $workPath . "/" . $pdfName;
        $uploadLocation = $fileData['tmp_name'];
        move_uploaded_file( $uploadLocation, $pdfFile );
        flush();

        // get hold of the 
        // convert the uploaded pdf file to a set of pages
        $cmdLine = $this->config->cmdline_pdf2svg;
        $this->policies->logProgressHeading('Converting PDF File to Pages');
        system($cmdLine." $pdfFile $workPath/page-%04d.svg all");
        flush();
        
        // retrieve the list of generated files
        $this->policies->logProgress("Retrieving Generated File List");
        $pageFiles = array_diff(scandir($workPath), array('..', '.', $pdfName));
        if ( empty( $pageFiles ) ){
            $this->policies->logWarning('No generated page files found');
            return;
        }

        // setup the documane node in the database to which the pages need to be attached
        $this->policies->logProgress("Creating doc node in Moodle");
        $this->policies->initDocument( $cmid, $fileData['name'], count( $pageFiles ) );

        // iterate over the page files to store them away
        $this->policies->logProgressHeading("Storing Generated Pages in Moodle");
        foreach( $pageFiles as $pageFile ){
            preg_match('/([0-9]+)[^0-9]*$/',$pageFile,$matches);
            $pageNum  = $matches[ 1 ];
            $this->policies->logProgress('Storing Page: '.$pageNum);
            $pageId  = $this->policies->storePageImage( $pageNum, $workPath.'/'.$pageFile );
        }

        // finalise the upload, generating an exercise from the uploaded pages
        $this->policies->logProgress("Generating Moodle Exercise From PDF Pages");
        $this->policies->finaliseUpload( $cmid );
    }


    //-------------------------------------------------------------------------
    // Private Data
    //-------------------------------------------------------------------------
    
    // The policies object provides all of the interfacing to the server back end
    // It can be re-implemented for testing purposes
    private $policies = null;
}

