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
 * mod_masks javascript library
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


// Namespace for the plugin code and data
M.mod_masks={
    // the YUI instance
    Y : null,

    // state variables
    currentPage     : 0,
    selectedMask    : null,
    dragInfo        : { maskId : -1 },
    maskChanges     : {},
    pageChanges     : {},
    alertLevel      : 0,
    
    // constants - user progress flags
    FLAG_SEEN       : 0x10, // seen but not done
    FLAG_DONE       : 0x20, // seen and closed
    FLAG_FAIL       : 0x40, // at least one wrong answer given
    FLAG_PASS       : 0x80, // correct answer given with no wrong answers

    // constants - mask and page flags
    FLAG_HIDDEN     : 0x01, // the page or flag is hidden
    FLAG_GRADED     : 0x02, // the mask's question is included in grading
    FLAG_CLOSABLE   : 0x04, // the mask can be closed
    FLAG_DELETED    : 0x80, // the page or flag is deleted

    //------------------------------------------------------
    // Init methods

    init: function(Y){
        this.Y = Y;

        // fix a click-action attribute to the body node to always have an action to escalate to
        $('body').attr('click-action','click-away');
        $('body').attr('dblclick-action','click-away');
        $('#masks').attr('click-action','click-masks');
        $('#masks').attr('dblclick-action','click-masks');

        // setup event handlers
        var body=$('body');
        body.click( this.onClick );
        body.dblclick( this.onDblClick );
        if ( M.mod_masks_state.type == 1 ){
            $('#masks-masks')
                .addClass('editor')
                .mousedown( M.mod_masks.onMaskMouseDn )
                .mouseup( M.mod_masks.onMaskMouseUp )
                .mousemove( M.mod_masks.onMaskMouseMv );
        } else {
            // for students disable the context menu
            // I'm not entirely sure that this makes sense but I'm unsure whether some browsers
            // will propose to view bare image in another tab if this isn't here
            $('#masks-masks').on('contextmenu', function() {
                return false;
            });
            // calculate and display the studen's current grade by scanning the user data blob
            this.showGradeInfo("");
        }

        // setup load event handler for iframe
        var frame=$('#popup-mdl-frame');
        frame.load( this.iframeLoaded );

        // start at first page
        if ( M.mod_masks_pages.length > 0 ){
            this.gotoPage(0);
        } else {
            var emptySet = {};
            M.mod_masks.activateFrame( "upload", emptySet, 'no' );
        }
    },


    //------------------------------------------------------
    // Event Handlers

    onDblClick: function(e){
        // locate the action by bubbling up the tree until we find a node that has the right attribute attached
        var target = $(e.target);
        var action = target.attr('dblclick-action');
        while ( !action ){
            var target = target.parent();
            var action = target.attr('dblclick-action');
        }

        // delegate processing to a shared routine
        M.mod_masks.processImpulseEvent(e,target,action);
    },

    onClick: function(e){
        // locate the action by bubbling up the tree until we find a node that has the right attribute attached
        var target = $(e.target);
        var action = target.attr('click-action');
        while ( !action ){
            var target = target.parent();
            var action = target.attr('click-action');
        }

        // delegate processing to a shared routine
        M.mod_masks.processImpulseEvent(e,target,action);
    },


    processImpulseEvent: function(e,target,action){
        // hide any visible menus
        $( '.menu-show' ).removeClass( 'menu-show' );

        // deal with the action
        switch (action){
            case 'nav-to-left-end':
                M.mod_masks.gotoPage( 0 );
                break;

            case 'nav-to-left':
                M.mod_masks.gotoPage( M.mod_masks.currentPage - 1 );
                break;

            case 'nav-to-right':
                M.mod_masks.gotoPage( M.mod_masks.currentPage + 1 );
                break;

            case 'nav-to-right-end':
                M.mod_masks.gotoPage( M.mod_masks_pages.length - 1 );
                break;

            case 'show-menu':
                var menuName = target.attr('menu');
                var fullName = '#masks-menu-'+menuName;
                var menuNode = $( fullName );
                menuNode.addClass( 'menu-show' );
                break;

            case 'goto-page':
                var pageNumber = target.attr('page');
                M.mod_masks.gotoPage( pageNumber );
                break;

            case 'toggle':
                M.mod_masks.clearAlertSuccess();
                var toggleName = target.attr('arg');
                M.mod_masks.toggleClass( toggleName );
                break;

            case 'add-mask':
                var maskType = target.attr('masktype');
                M.mod_masks.activateFrame( 'add-mask', { masktype: maskType }, 'prompt' );
                break;

            case 'edit-mask':
                // start by selecting the mask
                var maskId = target.parent().attr('maskid');
                M.mod_masks.selectMask(maskId);
                // delegate to 'edit-question' to open the editor form
                this.processImpulseEvent(e,target,'edit-question');
                break;
                
            case 'edit-question':
                M.mod_masks.activateFrame( action, { mid: M.mod_masks.selectedMask.id, qid: M.mod_masks.selectedMask.question, pageid: M.mod_masks.selectedMask.page }, 'no' );
                break;

            case 'click-mask':
                var pageIdx = M.mod_masks.currentPage;
                var pageId  = M.mod_masks_pages[ pageIdx ].id;
                var maskIdx = target.attr('maskidx');
                var mask    = M.mod_masks_masks.pages[ pageId ][ maskIdx ];
                var isLastQuestion = ( ( mask.flags & this.FLAG_GRADED ) != 0 && ( mask.userstate & this.FLAG_DONE ) == 0 && M.mod_masks.countUnpassedMasks( this.FLAG_GRADED ) == 1 )? 1: 0;
                M.mod_masks.selectedMask = mask;
                M.mod_masks.activateFrame( 'click-mask', { mid: mask.id, qid: mask.question, pageid: mask.page, islast: isLastQuestion }, 'no' );
                break;

            case 'save-layout':
                M.mod_masks.clearAlertInfo();
                // drop through to code below (don't break!)
            case 'reupload':
                var emptySet = {};
                M.mod_masks.activateFrame( action, emptySet, 'no' );
                break;

            case 'set-mask-style':
                var newStyle = target.attr('mask-style');
                var mask     = M.mod_masks.selectedMask;
                var maskId   = mask.id;
                $('#mask-'+maskId).removeClass( 'mask-style-'+mask.style ).addClass( 'mask-style-'+newStyle );
                mask.style = newStyle;
                M.mod_masks.maskChanges[ maskId ] = mask;
                M.mod_masks.setSaveLayoutMenu(true);
                M.mod_masks.setAlertInfo('saveStyleChange');
                break;

            case 'click-masks':
                // prevent the event from bubbling
                e.stopPropagation();
                e.preventDefault();
                break;

            case 'click-away':
                // allow the event to bubble
                break;

            default:
                console.warn('unrecognised action: ', action );
        }
    },

    //------------------------------------------------------
    // DOM interaction

    gotoPage: function(pageNumber){
        // apply bounds checks to page number
        var newPage = pageNumber;
        var maxPage = M.mod_masks_pages.length - 1;
        newPage = Math.min( maxPage, newPage );
        newPage = Math.max( 0, newPage );
        var pageHasChanged = ( M.mod_masks.currentPage != newPage );
        M.mod_masks.currentPage = newPage;

        // update the page nav menu
        $( '#masks-page-num .nav-num-word' ).html( '' + ( newPage + 1 ) );
        var flagNode = $( '#masks' );
        flagNode.removeClass( 'first-page last-page' );
        if ( newPage == 0 ){
            flagNode.addClass( 'first-page' );
        }
        if ( newPage >= maxPage ){
            flagNode.addClass( 'last-page' );
        }

        // update the image tag
        var src = M.mod_masks_pages[ newPage ].imageurl;
        if ( $( '#masks-page-image' ).attr( 'src' ) != src ){
            $( '#masks-page-image' ).attr( 'src', src );
        }

        // render the masks for this page
        M.mod_masks.clearMasks();
        M.mod_masks.renderMasks();

        // reset mask selection
        M.mod_masks.selectMask( -1 );

        // setup the 'page hidden' class correctly
        var page         = M.mod_masks_pages[ M.mod_masks.currentPage ];
        var pageIsHidden = ( page.flags & M.mod_masks.FLAG_HIDDEN ) != 0;
        var rootNode     = $('#masks');
        var toggleNode   = $('#masks-toggle-page-hidden');
        rootNode.removeClass('page-hidden');
        toggleNode.removeClass('toggle');
        if ( pageIsHidden ){
            rootNode.addClass('page-hidden');
            toggleNode.addClass('toggle');
        }
        
        // if the page has changed and the layout-save menu is active then
        // try to auto-save the layout
        if ( ( pageHasChanged === true ) && ! $('#masks').hasClass('hide-layout-save-group') ){
            M.mod_masks.activateFrame( 'save-layout', { confirm: 0 }, 'no' );
        }

        // clear out the alert message (if there is one hanging around)
        this.clearAlertSuccess();
    },

    toggleClass: function( toggleName ){
        // change DOM state to reflect toggle change
        var menuNode   = $( '#masks-toggle-' + toggleName );
        menuNode.toggleClass( 'toggle' );

        // apply logical state changes
        var isToggleActive = menuNode.hasClass( 'toggle' );
        switch( toggleName ){
            case 'full-size':
                $('#masks').removeClass('full-size');
                if ( isToggleActive ){
                    $('#masks').addClass('full-size');
                }
                break;

            case 'page-hidden':
                var pageNum  = M.mod_masks.currentPage;
                var page     = M.mod_masks_pages[ pageNum ];
                var rootNode = $('#masks');
                var navNode  = $('#page-name-'+pageNum);
                if ( isToggleActive ){
                    rootNode.addClass('page-hidden');
                    navNode.addClass('page-hidden');
                    page.flags = page.flags | M.mod_masks.FLAG_HIDDEN;
                    M.mod_masks.setAlertInfo('savePageHidden');
                } else {
                    rootNode.removeClass('page-hidden');
                    navNode.removeClass('page-hidden');
                    page.flags = page.flags & ~M.mod_masks.FLAG_HIDDEN;
                    M.mod_masks.setAlertInfo('saveChanges');
                }
                M.mod_masks.pageChanges[ pageNum ] = page;
                M.mod_masks.setSaveLayoutMenu(true);
                break;

            case 'mask-hidden':
                var mask     = M.mod_masks.selectedMask;
                var maskId   = mask.id;
                var maskNode = $('#mask-'+maskId);
                if ( isToggleActive ){
                    maskNode.addClass('mask-hidden');
                    mask.flags = mask.flags | M.mod_masks.FLAG_HIDDEN;
                    M.mod_masks.setAlertInfo('saveMaskHidden');
                } else {
                    maskNode.removeClass('mask-hidden');
                    mask.flags = mask.flags & ~M.mod_masks.FLAG_HIDDEN;
                    M.mod_masks.setAlertInfo('saveChanges');
                }
                M.mod_masks.maskChanges[ maskId ] = mask;
                M.mod_masks.setSaveLayoutMenu(true);
                break;

            case 'mask-deleted':
                var mask     = M.mod_masks.selectedMask;
                var maskId   = mask.id;
                var maskNode = $('#mask-'+maskId);
                if ( isToggleActive ){
                    maskNode.addClass('mask-deleted');
                    mask.flags = mask.flags | M.mod_masks.FLAG_DELETED;
                    M.mod_masks.setAlertInfo('saveDeletion');
                } else {
                    maskNode.removeClass('mask-deleted');
                    mask.flags = mask.flags & ~M.mod_masks.FLAG_DELETED;
                    M.mod_masks.setAlertInfo('saveChanges');
                }
                M.mod_masks.maskChanges[ maskId ] = mask;
                M.mod_masks.setSaveLayoutMenu(true);
                break;

            case 're-show-masks':
                // iterate over the mask data structure
                for( var p in M.mod_masks_masks.pages ){
                    var maskSet = M.mod_masks_masks.pages[ p ];
                    for ( var maskId in maskSet ){
                        var mask = maskSet[ maskId ];
                        // if the toggle is active then store away the state flags in the higher bits of the integerotherwise restore them
                        if ( isToggleActive ){
                            mask.userstate = mask.userstate << 16;
                        } else{
                            mask.userstate = mask.userstate >> 16;
                        }
                    }
                }

                // refresh the display of the current page
                M.mod_masks.gotoPage( M.mod_masks.currentPage );
                break;

            default:
                console.warn('unrecognised toggle action: ', toggleName );
        }
    },


    //------------------------------------------------------
    // Alert messages for the teacher
    
    setAlertSuccess: function( msgId ){
        this.setAlert(msgId,1,'alert-success');
    },

    setAlertInfo: function( msgId, cssClass ){
        this.setAlert(msgId,2,'alert-info');
    },

    setAlertWarn: function( msgId, cssClass ){
        this.setAlert(msgId,3,'alert-error');
    },

    setAlert: function( msgId, priority, cssClass ){
        // manage the alert priority system
        if (this.alertLevel>priority){
            return;
        }
        this.alertLevel=priority;
        // apply the alert
        var msg = M.mod_masks_texts[msgId] || ( msgId );
        $('.alert')
            .attr('class','alert '+cssClass )
            .html( msg );
    },

    clearAlertSuccess: function(){
        this.clearAlert(1);
    },

    clearAlertInfo: function(){
        this.clearAlert(2);
    },

    clearAlertWarn: function(){
        this.clearAlert(3);
    },

    clearAlert: function(priority){
        // manage the alert priority system
        if (this.alertLevel>priority){
            return;
        }
        this.alertLevel=0;
        $('.alert')
            .attr('class','alert hidden' )
            .html('');
    },


    //------------------------------------------------------
    // Managing iframe popups
    
    activateFrame: function( frameName, args, saveLayout ){
        // if there's a previous 'success' alert being displayed then get rid of it        
        this.clearAlertInfo();

        // update the properties of the iframe DOM tag
        var pageId = ( M.mod_masks_pages.length > 0 )? M.mod_masks_pages[ M.mod_masks.currentPage ].id: -1;
        var url = M.mod_masks_frames[ frameName ];
        url += '&pageid='+pageId;
        for(var key in args){
            var val = args[ key ];
            url += '&'+key+'='+val;
        }
        
        // if the save layout value is set here then indirect us through the save layout page
        if ( saveLayout != 'no' && ! $('#masks').hasClass('hide-layout-save-group') ){
            var confirm = ( saveLayout == 'prompt' )? 1: 0;
            this.activateFrame( 'save-layout', { id: M.mod_masks_state.cmid, confirm: confirm, nextframe: encodeURIComponent(url) }, 'no' );
        } else {
            // activate the iframe popup
            M.mod_masks.activatePopup( 'iframe' );
            // setup frame tag properties
            var frameTag = $('#popup-mdl-frame');
            frameTag.height( '1px' );
            frameTag.attr( 'src', url );
            frameTag.removeClass('loaded')
        }
    },

    activatePopup: function( popupId ){
        // if we already have an active popup then deactivate it
        $('.popup-active').removeClass('popup-active');
        $('#masks').removeClass('have-popup');
        // now activate the popup that we want
        $('#popup-parent-'+popupId ).addClass('popup-active');
        $('#masks').addClass('have-popup');
        // move the scroll reference object to the top left of the current view
        $('#masks-scroll-ref' ).offset({top:$(window).scrollTop(),left:$(window).scrollTop()});        
        // scroll the question or suchlike into view
        var offset0 = $(window).scrollTop()+64; // +64 to get cleanly beneath the title bar that floats over the screen
        var offset1 = $('#masks>.masks-body').offset().top;
        var offset  = Math.max(offset0,offset1);
        $('#popup-parent-'+popupId ).offset({top:offset});
    },


    //------------------------------------------------------
    // mask display
    
    clearMasks: function(){
        $( '#masks-masks' ).html('');
    },
    
    renderMasks: function(){
        // if there are no masks on this page then we're done
        var pageId = M.mod_masks_pages[ M.mod_masks.currentPage ].id;
        if (! (pageId in M.mod_masks_masks.pages ) ) {
            return;
        }
        // instantiate the elements
        var rootNode = $( '#masks-masks' );
        var pageMasks = M.mod_masks_masks.pages[pageId];
        for (var i = 0; i < pageMasks.length; ++i){
            var mask = pageMasks[ i ];
            // decide whether the mask is to be rendered at all
            var maskIsHidden    = ( mask.flags & M.mod_masks.FLAG_HIDDEN ) != 0;
            var maskIsDeleted   = ( mask.flags & M.mod_masks.FLAG_DELETED ) != 0;
            var maskIsSeen      = ( mask.userstate & M.mod_masks.FLAG_DONE );
            var viewHidden      = M.mod_masks_state.type == 1;
            if ( maskIsDeleted || maskIsSeen || ( maskIsHidden && ! viewHidden ) ){
                continue;
            }

            // calculate the mask position
            var maskX = parseInt(mask.x)*0.01+"%";
            var maskY = parseInt(mask.y)*0.01+"%";
            var maskW = parseInt(mask.w)*0.01+"%";
            var maskH = parseInt(mask.h)*0.01+"%";

            // setup the root div (invisible)
            var maskRoot = $('<div/>')
                .width( maskW )
                .height( maskH )
                .css( { left: maskX, top: maskY } )
                .addClass('mask-root mask-style-'+mask.style)
                .attr('maskid',mask.id)
                .attr('id','mask-'+mask.id);

            // if the mask is hidden or deleted then add the appropriate classes
            if ( maskIsHidden ){
                maskRoot.addClass( 'mask-hidden' );
            }
            if ( maskIsDeleted ){
                maskRoot.addClass( 'mask-deleted' );
            }

            // add a set of divs to display the mask itself
            $('<div/>').addClass('mask-layer mask-back').appendTo( maskRoot );
            $('<div/>').addClass('mask-layer mask-main').appendTo( maskRoot );
            $('<div/>').addClass('mask-layer mask-front').appendTo( maskRoot );

            // add student and teacher variations
            switch ( M.mod_masks_state.type ){
                case 0:
                    // student: make the mask roots clickable
                    maskRoot.attr( 'click-action', 'click-mask' );
                    maskRoot.attr( 'dblclick-action', 'click-mask' );
                    maskRoot.attr( 'maskidx', i );
                    break;

                case 1:
                    // teacher: add a div for selection / move handling (only for teachers)
                    $('<div/>').addClass('masks-handle m c').attr('dblclick-action','edit-mask').appendTo( maskRoot );
                    // teacher: add a set of divs for resize handling (only for teachers)
                    $('<div/>').addClass('masks-handle t l').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle t c').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle t r').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle m l').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle m r').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle b l').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle b c').appendTo( maskRoot );
                    $('<div/>').addClass('masks-handle b r').appendTo( maskRoot );
                    break;

                default:
                    log.warn( 'bad mod_masks_state.type vakue: ', M.mod_masks_state.type );
            }

            // append the created node to the DOM node
            maskRoot.appendTo( rootNode );
        }
    },

    selectMask: function(maskId){
        // see if the selected mask is on this page
        var mask = null;
        var pageId = M.mod_masks_pages[ M.mod_masks.currentPage ].id;
        if( pageId in M.mod_masks_masks.pages ){
            var pageMasks = M.mod_masks_masks.pages[pageId];
            for (var i=0; i < pageMasks.length; ++i){
                if ( pageMasks[i].id == maskId ){
                    mask = pageMasks[i];
                    break;
                }
            }
        }

        // if the mask was not found on this page then disable the mask action menu and clear the selected mask
        if ( ! mask ){
            M.mod_masks.selectedMask   = null;
            M.mod_masks.setMaskActionMenu( false );
            return null;
        }

        // set the selected mask, enable the action menu (if possilbe) and highlight the mask
        M.mod_masks.selectedMask   = mask;
        M.mod_masks.setMaskActionMenu( true );
        $('.selected-mask').removeClass('selected-mask');
        $('#mask-'+maskId).addClass('selected-mask');

        // return the selected mask object
        return mask;
    },


    //------------------------------------------------------
    // mask moving / resizing

    // on mouse button pressed
    onMaskMouseDn:function(e){
        var target = $(e.target);
        if ( target.attr('id') == 'masks-masks' ){
            e.preventDefault();
            return;
        }
        if (! target.hasClass('masks-handle') || e.which != 1 ){
            return;
        }
        var maskId = target.parent().attr('maskid');
        if ( maskId > 0 ){
            // select the mask in question (highlight it and so on)
            var mask = M.mod_masks.selectMask(maskId);
            if (!mask){
                M.mod_masks.dragInfo.maskId = -1;
                $('#masks').removeClass('dragging-mask');
                return;
            }
            
            // claculate initial X0, Y0, X1, Y1
            var x0 = parseInt(mask.x);
            var y0 = parseInt(mask.y);
            var x1 = parseInt(mask.x) + parseInt(mask.w);
            var y1 = parseInt(mask.y) + parseInt(mask.h);            

            // lookup the specifier classes present on the target
            var hasT = target.hasClass('t')? 1: 0;
            var hasM = target.hasClass('m')? 1: 0;
            var hasB = target.hasClass('b')? 1: 0;
            var hasL = target.hasClass('l')? 1: 0;
            var hasC = target.hasClass('c')? 1: 0;
            var hasR = target.hasClass('r')? 1: 0;
            var isMv = ( hasC & hasM );

            // setup a tracking record to represent the mask that we're starting to drag
            M.mod_masks.dragInfo = {
                target: target,
                maskId: maskId,
                refX: e.pageX,
                refY: e.pageY,
                posX0: x0,
                posX1: x1,
                posY0: y0,
                posY1: y1,
                newX0: x0,
                newX1: x1,
                newY0: y0,
                newY1: y1,
                mulX0: ( hasL + isMv ),
                mulX1: ( hasR + isMv ),
                mulY0: ( hasT + isMv ),
                mulY1: ( hasB + isMv ),
            };

            // flag the scene as having a moving mask in progress to allow us to react
            $('#masks').addClass('dragging-mask');

            // prevent the event from bubbling
            e.stopPropagation();
            e.preventDefault();
        }
    },

    // on mouse button released
    onMaskMouseUp:function(e){
        var dragInfo = M.mod_masks.dragInfo;
        if ( dragInfo.maskId != -1 ){
            // prevent the event from bubbling
            e.stopPropagation();
            e.preventDefault();

            // if there are no masks on this page then we're done
            var pageId = M.mod_masks_pages[ M.mod_masks.currentPage ].id;
            if (! (pageId in M.mod_masks_masks.pages ) ) {
                return;
            }
            // locate the mask that we're intending to update
            var pageMasks = M.mod_masks_masks.pages[pageId];
            for (var i = 0; i < pageMasks.length; ++i){
                var mask = pageMasks[ i ];
                // if this isn't the mask that we're looking for the skip it
                if ( mask.id != dragInfo.maskId ){
                    continue;
                }
                // calculate and store away the position update in the official page mask data
                mask.x = dragInfo.newX0;
                mask.y = dragInfo.newY0;
                mask.w = dragInfo.newX1 - dragInfo.newX0;
                mask.h = dragInfo.newY1 - dragInfo.newY0;
                // add the mask to the change list
                M.mod_masks.maskChanges[ mask.id ] = mask;

                // if the mouse position has moved then activate the mask move menu
                if ( ( dragInfo.newX0 != dragInfo.posX0 ) || ( dragInfo.newX1 != dragInfo.posX1 ) ||
                     ( dragInfo.newY0 != dragInfo.posY0 ) || ( dragInfo.newY1 != dragInfo.posY1 ) ){
                    M.mod_masks.setSaveLayoutMenu( true );
                    M.mod_masks.setAlertInfo('saveChanges');
                }
                break;
            }
            dragInfo.maskId = -1;
            $('#masks').removeClass('dragging-mask');
        }
    },

    // on mouse move
    onMaskMouseMv:function(e){
        if ( M.mod_masks.dragInfo.maskId != -1 ){
            var dragInfo = M.mod_masks.dragInfo;
            // start by looking to see if the mouse is out of bounds
            var canvas = $('#masks-masks');
            var canvasW = canvas.width();
            var canvasH = canvas.height()
            var x = e.pageX;
            var y = e.pageY;
            var canvasOffset = canvas.offset();
            var minPageX = canvasOffset.left;
            var minPageY = canvasOffset.top;
            var maxPageX = minPageX + canvas.width();
            var maxPageY = minPageY + canvas.height();
            if ( x < minPageX || y < minPageY || x >= maxPageX || y >= maxPageY ){
                // the mouse is out of bounds so simulate a button release
                M.mod_masks.onMaskMouseUp(e);
                return;
            }
            // calculate the mouse move vector (from the original mouse down position)
            var dX = Math.round( ( e.pageX - dragInfo.refX ) * 10000.0 / canvasW );
            var dY = Math.round( ( e.pageY - dragInfo.refY ) * 10000.0 / canvasH );
            // derive changes in x0, y0, x1, y1
            var dX0 = dX * dragInfo.mulX0;
            var dX1 = dX * dragInfo.mulX1;
            var dY0 = dY * dragInfo.mulY0;
            var dY1 = dY * dragInfo.mulY1;
            // derive updated coorinates
            var newX0 = dX0 + dragInfo.posX0;
            var newX1 = dX1 + dragInfo.posX1;
            var newY0 = dY0 + dragInfo.posY0;
            var newY1 = dY1 + dragInfo.posY1;
            // clamp out-of bounds edges
            var maxX1 = 9999;
            var maxY1 = 9999;
            if ( newX0 < 0 ){ newX0 = 0; dX0 = 0; }
            if ( newY0 < 0 ){ newY0 = 0; dY0 = 0; }
            if ( newX1 > maxX1 ){ newX1 = maxX1; dX1 = 0; }
            if ( newY1 > maxY1 ){ newY1 = maxY1; dY1 = 0; }
            // derive new w, h
            var newW = newX1 - newX0;
            var newH = newY1 - newY0;
            // clamp min w/h to keep it in bounds
            var minWH = 100;
            if ( newW < minWH ){
                newX0 = ( dX0 == 0 )? newX0: newX1 - minWH;
                newX1 = ( dX1 == 0 )? newX1: newX0 + minWH;
                newW = minWH;
            }
            if ( newH < minWH ){
                newY0 = ( dY0 == 0 )? newY0: newY1 - minWH;
                newY1 = ( dY1 == 0 )? newY1: newY0 + minWH;
                newH = minWH;
            }
            // store the draginfo for later use
            dragInfo.newX0 = newX0;
            dragInfo.newX1 = newX1;
            dragInfo.newY0 = newY0;
            dragInfo.newY1 = newY1;
            // map coordinates back to image space
            var resultX = newX0 / 100.0 + '%';
            var resultY = newY0 / 100.0 + '%';
            var resultW = newW / 100.0 + '%';
            var resultH = newH / 100.0 + '%';
            // update the screen widget
            $( '#mask-' + dragInfo.maskId )
                .css( { left:resultX, top:resultY } )
                .width( resultW )
                .height( resultH );

            // prevent the event from bubbling
            e.stopPropagation();
            e.preventDefault();
        }
    },

    //------------------------------------------------------
    // activating / deactivating menu-bar menus

    setMaskActionMenu:function(desiredState){
        // remove the class if it's already there
        $('#masks').removeClass('hide-mask-actions-group');

        // add it again if it's required
        if ( desiredState === false ){
            $('#masks').addClass('hide-mask-actions-group');
        } else {
            // determine whether the selected mask is hidden and update the menu display to match
            var hideMaskNode = $( '#masks-toggle-mask-hidden' );
            var maskIsHidden = ( M.mod_masks.selectedMask.flags & M.mod_masks.FLAG_HIDDEN ) != 0;
            hideMaskNode.removeClass( 'toggle' );
            if ( maskIsHidden ){
                hideMaskNode.addClass( 'toggle' );
            }
            // determine whether the selected mask is deleted and update the menu display to match
            var delMaskNode = $( '#masks-toggle-mask-deleted' );
            var maskIsDeleted = ( M.mod_masks.selectedMask.flags & M.mod_masks.FLAG_DELETED ) != 0;
            delMaskNode.removeClass( 'toggle' );
            if ( maskIsDeleted ){
                delMaskNode.addClass( 'toggle' );
            }
        }
    },

    setSaveLayoutMenu:function(desiredState){
        // remove the class if it's already there
        $('#masks').removeClass('hide-layout-save-group');

        // add it again if it's required
        if ( desiredState === false ){
            $('#masks').addClass('hide-layout-save-group');
        }

        // if the menu is enabled then add a 'wait one moment - dont' run off yet' message
        window.onbeforeunload = ( desiredState === false )? null: M.mod_masks.promptBeforeLeavingPage;
    },
    
    promptBeforeLeavingPage: function(){
        return M.mod_masks_texts.pageExitPrompt;
    },

    //------------------------------------------------------
    // API used by save-layout frame

    getMaskChanges: function(){
        var changes = [];
        for(var maskId in this.maskChanges ){
            var mask = this.maskChanges[maskId];
            changes.push( mask );
        }
        return JSON.stringify( changes );
    },

    getPageChanges: function(){
        var changes = [];
        for(var pageId in this.pageChanges ){
            var page = this.pageChanges[pageId];
            changes.push( { id:page.id, flags:page.flags } );
        }
        return JSON.stringify( changes );
    },

    clearChangeLists: function(){
        this.maskChanges = {};
        this.pageChanges = {};
        this.setSaveLayoutMenu(false);
        M.mod_masks.clearAlertInfo();
    },

    //------------------------------------------------------
    // API used by sub-frames

    // callback when iframe contents is loaded for us to resize the iframe node to fit its contents
    iframeLoaded: function(){
        M.mod_masks.iframeUpdateHeight();
        $( '#popup-parent-iframe' ).addClass('loaded');
    },

    iframeUpdateHeight: function(){
        var height = $('#masks').height() / 2;
        var frameNode = document.getElementById('popup-mdl-frame');
        if ( frameNode ){
            height = frameNode.contentWindow.document.body.scrollHeight;
        }
        $( frameNode ).height( height+'px' );
    },

    closeMask: function(){
        var mask     = M.mod_masks.selectedMask;
        var maskId   = mask.id;
        // if the mask is presnet over the image then get rid of it
        $('#mask-'+maskId).remove();
        // flag the mask as seen so that it doesn't get re-displayed after a page change
        mask.userstate = mask.userstate | M.mod_masks.FLAG_DONE;
    },

    closeFrame: function(){
        $('.popup-active').removeClass('popup-active');
        $('#masks').removeClass('have-popup');
        $('#popup-mdl-frame').height( '1px' );
        // scroll the scroll reference into view
        $("#masks-scroll-ref")[0].scrollIntoView({behavior: "smooth"});
    },

    applyMaskData: function(data){
        M.mod_masks.clearMasks();
        M.mod_masks_masks = data;
        M.mod_masks.renderMasks();
    },

    applyPageData: function(data){
        M.mod_masks_pages = data;

        // grab hold of a copy of the first child of the page menu (to use as a reference node)
        var refNode  = $('#page-name-0').parent().clone();
        var refChild = refNode.find('.page-hidden');
        var refNum   = refNode.find('.nav-num-word');
        refChild.removeClass('page-hidden');

        // clear out existing page nodes
        var menuRoot = $('#masks-menu-page-select').find('.menu-popup');
        menuRoot.html('');
        
        // special case - if the doc is empty then just put a page0 entry back in so as to have something to clone next time round
        if ( M.mod_masks_pages.length == 0 ){
            menuRoot.append( refNode );
            M.mod_masks.gotoPage( M.mod_masks.currentPage );
            return;
        }

        // reset and re-apply page-hidden flags in the page nav drop-down menu
        for (var pageNum in M.mod_masks_pages){
            var page     = M.mod_masks_pages[ pageNum ];
            var isHidden = page.flags & M.mod_masks.FLAG_HIDDEN;
            refNode.attr('page',pageNum);
            refChild.removeClass('page-hidden');
            if ( isHidden ){
                refChild.addClass('page-hidden');
            }
            refNum.html( 1 + parseInt( pageNum ) );
            var newNode  = refNode.clone();
            menuRoot.append( newNode );
        }

        // refresh the display of the page itself
        M.mod_masks.gotoPage( M.mod_masks.currentPage );
    },

    setMaskState: function( maskId, newState ){
        for( var p in M.mod_masks_masks.pages ){
            var maskSet = M.mod_masks_masks.pages[ p ];
            for ( var m in maskSet ){
                if ( maskSet[ m ].id == maskId ){
                    maskSet[ m ].userstate = parseInt(newState);
                }
            }
        }
    },

    onMaskPass: function(){
        this.showGradeSuccess( 'studentGradePass' );
    },

    onMaskDoneAfterFail: function(){
        this.showGradeInfo( 'studentGradeDone' );
    },

    onMaskFail: function(){
        this.showGradeInfo( 'studentGradeFail' );
    },

    onMaskDone: function(){
        this.showGradeInfo( 'studentGradeDone' );
    },


    //------------------------------------------------------
    // Grade management

    showGradeSuccess: function(intro){
        this.clearAlertInfo();
        this.setAlertSuccess( this.getGradeString( intro ) );
    },

    showGradeInfo: function(intro){
        this.setAlertInfo( this.getGradeString( intro ) );
    },

    getGradeString: function( intro ){
        var gradeData = this.calculateGradeData();
        var introMsg = ( intro == "" )? "": M.mod_masks_texts[intro];
        var introMsg = ( introMsg == "" )? "": ( introMsg + " - " );
        var result = introMsg +
            M.mod_masks_texts["gradeNamePass"] + ": " + gradeData.goodPasses + " / " + ( gradeData.goodPasses + gradeData.badPasses + gradeData.fails ) +
            " - " + M.mod_masks_texts["gradeNameToGo"] + " " + ( gradeData.numQuestions - gradeData.goodPasses - gradeData.badPasses );
        return result;
    },
    
    calculateGradeData: function(){
        var result = { numQuestions:0, goodPasses:0, badPasses:0, fails:0, unattempted:0, unseen:0, numNotes:0, notesDone:0, notesToDo:0 };
        // iterate over the masks...
        for( var p in M.mod_masks_masks.pages ){
            var maskSet = M.mod_masks_masks.pages[ p ];
            for ( var maskId in maskSet ){
                var mask = maskSet[ maskId ];
                // skip hidden masks
                if ( ( mask.flags & ( this.FLAG_HIDDEN | this.FLAG_DELETED ) ) > 0 ){
                    continue;
                }
                var isGraded   = ( ( mask.flags & this.FLAG_GRADED ) != 0 );
                var isClosable = ( ( mask.flags & this.FLAG_CLOSABLE ) != 0 );
                var isGoodPass = ( ( mask.userstate & this.FLAG_FAIL + this.FLAG_PASS ) == this.FLAG_PASS );
                var isBadPass  = ( ( mask.userstate & this.FLAG_FAIL + this.FLAG_DONE ) == this.FLAG_FAIL + this.FLAG_DONE );
                var isFail     = ( ( mask.userstate & this.FLAG_FAIL + this.FLAG_DONE ) == this.FLAG_FAIL );
                var isToDo     = ( ( mask.userstate & this.FLAG_FAIL + this.FLAG_DONE ) == 0 );
                var isUnseen   = ( ( mask.userstate & this.FLAG_SEEN ) == 0 );
                var isDone     = ( ( mask.userstate & this.FLAG_DONE ) == this.FLAG_DONE );
                result.numQuestions += isGraded? 1: 0;
                result.goodPasses   += isGoodPass ? 1: 0;
                result.badPasses    += isBadPass  ? 1: 0;
                result.fails        += isFail     ? 1: 0;
                result.unattempted  += ( isGraded && isToDo && !isUnseen )? 1: 0;
                result.unseen       += ( isGraded && isUnseen )? 1: 0;
                result.numNotes     += ( isClosable && !isGraded )? 1: 0;
                result.notesDone    += ( !isGraded && isDone )? 1: 0;
                result.notesToDo    += ( isClosable && !isGraded && !isDone )? 1: 0;
            }
        }
        return result;
    },

    countActiveMasks: function( maskTypeFlag ){
        var result = 0;
        // count the active masks
        for( var p in M.mod_masks_masks.pages ){
            var maskSet = M.mod_masks_masks.pages[ p ];
            for ( var maskId in maskSet ){
                var mask = maskSet[ maskId ];
                result += ( ( mask.flags & ( this.FLAG_HIDDEN | this.FLAG_DELETED | maskTypeFlag ) ) == maskTypeFlag )? 1 : 0;
            }
        }
        return result;
    },

    countUnpassedMasks: function( maskTypeFlag ){
        var result = 0;
        // count the active masks that have not yet been closed
        for( var p in M.mod_masks_masks.pages ){
            var maskSet = M.mod_masks_masks.pages[ p ];
            for ( var maskId in maskSet ){
                var mask = maskSet[ maskId ];
                var isGradable = ( ( mask.flags & ( this.FLAG_HIDDEN | this.FLAG_DELETED | maskTypeFlag ) ) == maskTypeFlag );
                var isPassed   = ( ( mask.userstate & this.FLAG_DONE ) != 0 );
                result += ( isGradable && !isPassed )? 1 : 0;
            }
        }
        return result;
    },
};

