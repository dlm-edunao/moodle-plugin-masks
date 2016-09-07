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
 * mod_masks - renderer class
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_masks_renderer extends plugin_renderer_base {

    /**
     * Render the teachers' view
     *
     * @param object $docData The description of the document to be rendered (array of page records with image names, etc)
     * @return string The HTML to display.
     */
    public function renderTeacherView( $docData ) {
        global $OUTPUT;
        $result = '';
        
        // add notifications
        $stralertMsg = get_string( 'alert_uploadnofile', 'mod_masks' );
        $result .= $OUTPUT->notification( $stralertMsg, 'notifysuccess');

        // open the main page area
        $result .= $this->openPage();

        // render the header bar (with menus and page nav)
        $result .= $this->openHeader();
        $result .= $this->renderPageNavWidget( count ( $docData->pages ) );
        $result .= $this->renderMenuBar( true );
        $result .= $this->closeHeader();

        // render the main page area
        $result .= $this->openBody();
        $result .= $this->renderPageView( $docData );
        $result .= $this->closeBody();

        // close the main page area
        $result .= $this->closePage();

        // return output
        return $result;
    }

    /**
     * Render the students' view
     *
     * @param object $docData The description of the document to be rendered (array of page records with image names, etc)
     * @return string The HTML to display.
     */
    public function renderStudentView( $docData ) {
        global $OUTPUT;
        $result = '';
        
        // add notifications
        $result .= $OUTPUT->notification('This is the STUDENT view', 'notifysuccess');

        // open the main page area
        $result .= $this->openPage();

        // render the header bar (with menus and page nav)
        $result .= $this->openHeader();
        $result .= $this->renderPageNavWidget( count ( $docData->pages ) );
        $result .= $this->renderMenuBar( false );
        $result .= $this->closeHeader();

        // render the main page area
        $result .= $this->openBody();
        $result .= $this->renderPageView( $docData );
        $result .= $this->closeBody();

        // close the main page area
        $result .= $this->closePage();

        // return output
        return $result;
    }

    /**
     * Render an empty window with a 'document not ready' message
     *
     * @return string The HTML to display.
     */
    public function renderNotReadyMessage() {
        global $OUTPUT;
        $result = '';
        
        // add notifications
        $strNotification = get_string( 'notReadyMsg', 'mod_masks' );
        $result .= $OUTPUT->notification($strNotification, 'notifyproblem');

        // return output
        return $result;
    }


    //-----------------------------------------------------------------------------------
    // Private utilities - page wrapper

    private function openPage(){
        $result = '';

        // open the page
        $rootClasses = 'hide-mask-actions-group hide-layout-save-group'; 
        $result .= html_writer::start_tag( 'div', array( 'id' => 'masks', 'class' => $rootClasses ) );

        // add a little dummy div that can be used to record a scroll position and returned to as an anchor
        $result .= html_writer::tag( 'div', '', array( 'id' => 'masks-scroll-ref' ) );

        // open the overlay space
        $result .= html_writer::start_tag( 'div', array( 'id' => 'page-overlay' ) );
        $result .= html_writer::tag( 'div', '', array( 'id' => 'page-mask' ) );

        // add iframe overlay
        $result .= html_writer::start_tag( 'div', array( 'id' => 'popup-parent-iframe' ) );
        $result .= html_writer::tag( 'iframe', '', array( 'id' => 'popup-mdl-frame' ) );
        $result .= html_writer::end_tag( 'div' );

        // close the overlay space
        $result .= html_writer::end_tag( 'div' );

        return $result;
    }

    private function closePage(){
        $result = '';

        // close the page
        $result .= html_writer::end_tag( 'div' );

        return $result;
    }


    //-----------------------------------------------------------------------------------
    // Private utilities - header components

    private function openHeader(){
        $result = '';
        $result .= html_writer::start_div('masks-header');
        return $result;
    }

    private function closeHeader(){
        $result = '';
        $result .= html_writer::end_div();
        return $result;
    }

    private function renderPageNavWidget( $numPages ){
        $result = '';

        // open page nav area tags
        $result .= html_writer::start_div('page-nav');
        
        // buttons for navigating left
        $result .= html_writer::span( '<<', 'nav-button nav-left', array( 'click-action' => 'nav-to-left-end' ) );
        $result .= html_writer::span( '<', 'nav-button nav-left', array( 'click-action' => 'nav-to-left' ) );

        // page selection drop-down menu
        $result .= $this->openMenuFromButton( 'page-select', $this->renderPageName( '', 'masks-page-num' ) );
        for( $i = 0; ( $i == 0 ) || ( $i < $numPages ); ++$i ){
            $attributes = array( 'click-action' => 'goto-page', 'page' => $i );
            $pageName = $this->renderPageName( $i+1, 'page-name-'.$i );
            $result .= $this->renderMenuEntry( $pageName, $attributes );
        }
        $result .= $this->closeMenu();

        // buttons for navigating right
        $result .= html_writer::span( '>', 'nav-button nav-right', array( 'click-action' => 'nav-to-right' ) );
        $result .= html_writer::span( '>>', 'nav-button nav-right', array( 'click-action' => 'nav-to-right-end' ) );

        // close page nav area tags
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderMenuBar( $includeTeacherOptions ){
        $result = '';

        // open menu bar
        $result .= html_writer::start_div('menu-bar');

        // open the basic menu button group
        $result .= $this->openMenuBarGroup();

        // add the options menu
        $result .= $this->openMenuFromIcon( 'options', 'i/settings' );
// The following line removed as it would appear that mobile devices can resize the view without the option provided here
//        $result .= $this->renderToggleMenuEntry( 'full-size' );
        if ( $includeTeacherOptions === true ){
            $result .= $this->renderToggleMenuEntry( 'page-hidden' );
            $result .= $this->renderActionMenuEntry( 'reupload', 'i/import' );
        } else {
            $result .= $this->renderToggleMenuEntry( 're-show-masks' );
        }
        $result .= $this->closeMenu();

        // add Add Mask menu
        if ($includeTeacherOptions){
            $strAddMaskMenu  = get_string( 'add-mask-menu', 'mod_masks' );
            $spanAddMaskMenu = html_writer::span( $strAddMaskMenu, 'bold' );
            $result .= $this->openMenuFromButton('add-mask', $spanAddMaskMenu );
            require_once(dirname(__FILE__).'/mask_types_manager.class.php');
            $typeNames = \mod_masks\mask_types_manager::getTypeNames();
            foreach($typeNames as $typeName){
                $result .= $this->renderActionMenuEntry( 'add-mask', 't/add', array( 'masktype' => $typeName ) );
            }
            $result .= $this->closeMenu();
        }

        // close the basic menu button group
        $result .= $this->closeMenuBarGroup();

        // add mask editing action buttons
        if ($includeTeacherOptions){
            // open the button group
            $result .= $this->openMenuBarGroup( 'mask-actions-group' );
            // add some buttons
            $result .= $this->renderActionButton( 'edit-question', 't/editstring' );
            $result .= $this->renderToggleButton( 'mask-hidden' );
            $result .= $this->renderToggleButton( 'mask-deleted' );
            // add Style menu
            $strMaskStyleMenu  = get_string( 'mask-style-menu', 'mod_masks' );
            $spanMaskStyleMenu = html_writer::span( $strMaskStyleMenu );
            $result .= $this->openMenuFromButton('mask-style', $spanMaskStyleMenu );
            for ( $i = 0; $i < 9; ++$i ){
                $result .= $this->renderStyleMenuEntry( $i );
            }
            $result .= $this->closeMenu();
            // close the button group
            $result .= $this->closeMenuBarGroup();
        }

        // add mask move / resize confirmation buttons
        if ($includeTeacherOptions){
            $result .= $this->openMenuBarGroup('layout-save-group');
            $result .= $this->renderActionButton( 'save-layout', 't/backup', true );
            $result .= $this->closeMenuBarGroup();
        }

        // close menu bar
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderPageName( $pageNumber, $pageNameId ){
        // lookup loca strings
        $strPage = get_string( 'page', 'mod_masks' );

        $attributes = array( 'id' => $pageNameId );
        $result = '';
        $result .= html_writer::start_div( 'nav-page-name', $attributes );
        $result .= html_writer::start_span( 'nav-page-word' );
        $result .= $strPage.' ';
        $result .= html_writer::span( $pageNumber, 'nav-num-word' );
        $result .= html_writer::end_span();
        $result .= html_writer::end_div();

        return $result;
    }


    //-----------------------------------------------------------------------------------
    // Private utilities - body components

    private function openBody(){
        $result = '';
        $result .= html_writer::start_div('masks-body');
        return $result;
    }

    private function closeBody(){
        $result = '';
        $result .= html_writer::end_div();
        return $result;
    }

    private function renderPageView( $docData ){
        // calculate the max page width
        $pageWidth = 0;
        foreach( $docData->pages as $page ){
            $pageWidth = max( $pageWidth, $page->w );
        }
        
        // setup alternative image-width styles for controlling the size of the page image
        $styles = '';
        $styles .= '#masks-page-space{width:100%}';
        $styles .= '#masks.full-size #masks-page-space{width:'.$pageWidth.'px}';

        // render the result
        $result = '';
        $result .= html_writer::tag( 'style', $styles );
        $result .= html_writer::start_tag( 'div', array( 'id' => 'masks-page-space' ) );
        $result .= html_writer::empty_tag( 'img', array( 'id' => 'masks-page-image' ) );
        $result .= html_writer::tag( 'div', '', array( 'id' => 'masks-masks' ) );
        $result .= html_writer::end_tag('div');
        return $result;
    }

        
    //-----------------------------------------------------------------------------------
    // Private utilities - menu-bar menus components

    private function openMenuBarGroup( $groupId = '' ){
        $result = '';

        // open the div
        $result .= html_writer::start_div( 'menu-bar-group ' . $groupId );

        // add a title
        $strTitle = empty( $groupId )? '': get_string( $groupId, 'mod_masks' );
        if ( ! empty( $strTitle ) ){
            $result .= html_writer::div( $strTitle, 'menu-bar-group-title' );
        }

        return $result;
    }

    private function closeMenuBarGroup(){
        return html_writer::end_div();
    }

    private function renderActionButton( $clickAction, $menuIcon, $bold=false ){
        global $OUTPUT;
        $result = '';

        $strButtonText = get_string( $clickAction, 'mod_masks' );
        $attributes = array( 'click-action' => $clickAction );

        $result .= html_writer::start_div( 'action-button ', $attributes );
        $result .= html_writer::empty_tag( 'img', array( 'src' => $OUTPUT->pix_url( $menuIcon ) ) );
        $result .= html_writer::span( $strButtonText, $bold? 'bold': '' );
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderToggleButton( $toggleName ){
        global $OUTPUT;
        $result = '';

        $strButtonText = get_string( $toggleName, 'mod_masks' );
        $attributes = array(
            'click-action' => 'toggle', 
            'arg' => $toggleName,
            'id' => 'masks-toggle-'.$toggleName );

        $result .= html_writer::start_div( 'toggle-button', $attributes );
        $result .= html_writer::empty_tag( 'img', array( 'class' => 'toggle-off', 'src' => $OUTPUT->pix_url( 'i/completion-manual-n' ) ) );
        $result .= html_writer::empty_tag( 'img', array( 'class' => 'toggle-on', 'src' => $OUTPUT->pix_url( 'i/completion-manual-y' ) ) );
        $result .= html_writer::span( $strButtonText );
        $result .= html_writer::end_div();

        return $result;
    }

    //-----------------------------------------------------------------------------------
    // Private utilities - menu-bar drop-down menus components

    private function openMenuFromButton( $menuName, $buttonContent ){
        global $OUTPUT;
        $result = '';

        // open wrapper
        $result .= html_writer::start_div( 'menu-wrapper', array( 'id' => 'masks-menu-'.$menuName ) );

        // add menu button
        $result .= html_writer::start_div( 'menu-button', array( 'click-action' => 'show-menu', 'menu' => $menuName ) );
        $result .= $buttonContent;
        $result .= html_writer::empty_tag( 'img', array( 'src' => $OUTPUT->pix_url( 't/expanded' ) ) );
        $result .= html_writer::end_div();

        // open menu body
        $result .= html_writer::start_div( 'menu-popup', array( 'id' => 'drop-down-'.$menuName) );

        return $result;
    }

    private function openMenuFromIcon( $menuName, $iconPath ){
        global $OUTPUT;
        $result = '';

        // open wrapper
        $result .= html_writer::start_div( 'menu-wrapper', array( 'id' => 'masks-menu-'.$menuName ) );

        // add menu button
        $result .= html_writer::start_div( 'menu-button', array( 'click-action' => 'show-menu', 'menu' => $menuName ) );
        $result .= html_writer::empty_tag( 'img', array( 'src' => $OUTPUT->pix_url( $iconPath ) ) );
        $result .= html_writer::empty_tag( 'img', array( 'src' => $OUTPUT->pix_url( 't/expanded' ) ) );
        $result .= html_writer::end_div();

        // open menu body
        $result .= html_writer::start_div( 'menu-popup', array( 'id' => 'drop-down-'.$menuName) );

        return $result;
    }

    private function closeMenu(){
        $result = '';

        // close menu body
        $result .= html_writer::end_div();

        // close wrapper
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderMenuEntry( $content, $attributes ){
        $result = '';

        $result .= html_writer::start_div( 'menu-entry', $attributes );
        $result .= $content;
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderActionMenuEntry( $clickAction, $menuIcon, $extraAttributes = array() ){
        global $OUTPUT;
        $result = '';

        $strMenuTextId = $clickAction;
        foreach( $extraAttributes as $attribValue ){
            $strMenuTextId .= '-' . $attribValue;
        }
        $strMenuText = get_string( $strMenuTextId, 'mod_masks' );
        $attributes = $extraAttributes;
        $attributes[ 'click-action' ] = $clickAction;

        $result .= html_writer::start_div( 'menu-entry', $attributes );
        $result .= html_writer::empty_tag( 'img', array( 'src' => $OUTPUT->pix_url( $menuIcon ) ) );
        $result .= html_writer::span( $strMenuText );
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderStyleMenuEntry( $idx ){
        global $OUTPUT;
        $result = '';
        
        $attributes = array( 'click-action' => 'set-mask-style', 'mask-style' => $idx );

        $result .= html_writer::start_div( 'menu-entry', $attributes );
        $result .= html_writer::start_div( 'mask-root mask-style-'.$idx );
        $result .= html_writer::div( '', 'mask-back' );
        $result .= html_writer::div( '', 'mask-main' );
        $result .= html_writer::div( '', 'mask-front' );
        $result .= html_writer::end_div();
        $result .= html_writer::end_div();

        return $result;
    }

    private function renderToggleMenuEntry( $toggleName ){
        global $OUTPUT;
        $result = '';

        $strMenuText = get_string( $toggleName, 'mod_masks' );
        $attributes = array(
            'click-action' => 'toggle', 
            'arg' => $toggleName,
            'id' => 'masks-toggle-'.$toggleName );

        $result .= html_writer::start_div( 'menu-entry', $attributes );
        $result .= html_writer::empty_tag( 'img', array( 'class' => 'toggle-off', 'src' => $OUTPUT->pix_url( 'i/completion-manual-n' ) ) );
        $result .= html_writer::empty_tag( 'img', array( 'class' => 'toggle-on', 'src' => $OUTPUT->pix_url( 'i/completion-manual-y' ) ) );
        $result .= html_writer::span( $strMenuText );
        $result .= html_writer::end_div();

        return $result;
    }
}

