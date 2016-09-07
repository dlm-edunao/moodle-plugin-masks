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
 * masks masked pdf activity An explicit registry of mask types
 *
 * @copyright  2016 Edunao SAS (contact@edunao.com)
 * @author     Sadge (daniel@edunao.com)
 * @package    mod_masks
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_masks;

require_once(dirname(__FILE__).'/mask_type_qcm.class.php');
require_once(dirname(__FILE__).'/mask_type_qtxt.class.php');
require_once(dirname(__FILE__).'/mask_type_basic.class.php');
require_once(dirname(__FILE__).'/mask_type_note.class.php');

class mask_types_manager{
    private static $types = null;

    /* Populate the type list (if it's not already populated)
     */
    private static function populateTypeList(){
        if ( self::$types == null ){
            self::$types = array(
                'qtxt'  => new mask_type_qtxt,
                'qcm'   => new mask_type_qcm,
                'basic' => new mask_type_basic,
                'note'  => new mask_type_note,
            );
        }
    }

    /* Get the array of registered type names
     * @returns array of string type names 
     */
    public static function getTypeNames(){
        self::populateTypeList();
        return array_keys( self::$types );
    }

    /* Get the type name for this class
     * @param string mask type name (as one of the entries provided by getTypeNames())
     * @returns mixed instance of the mask_type_... class or null if the type name was not recognised
     */
    public static function getTypeHandler( $typeName ){
        self::populateTypeList();
        return array_key_exists( $typeName, self::$types )? self::$types[ $typeName ]: null;
    }
}


