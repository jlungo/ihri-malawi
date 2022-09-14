<?php
/**
* © Copyright 2009 IntraHealth International, Inc.
* 
* This File is part of I2CE 
* 
* I2CE is free software; you can redistribute it and/or modify 
* it under the terms of the GNU General Public License as published by 
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful, 
* but WITHOUT ANY WARRANTY; without even the implied warranty of 
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License 
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
* @package i2ce
* @subpackage forms
* @author Carl Leitner <litlfred@ibiblio.org>
* @version v4.0.3
* @since v4.0.3
* @filesource 
*/ 
/** 
* Class I2CE_FormStorage_eval
* 
* @access public
*/


class I2CE_FormStorage_eval extends I2CE_FormStorage_Mechanism{



    /**
     * Populate the member variables of the object from the database.
     * @param I2CE_Form $form
     */
    public function populate( $form) {
        $formName = $form->getName();
        $id = $form->getId();
        $storageOptions = $this->getStorageOptions($formName);
        if (!$storageOptions instanceof I2CE_MagicDataNode) {
            I2CE::raiseError("Bad storage options");
            return false;
        }
        if (!$storageOptions->is_parent('fields')) {
            I2CE::raiseError("No fields to store");
        }
        $parentPopulate = null;
        if ($storageOptions->setIfIsSet($parentPopulate, "fields/parent/populate") ) {
            $parent = null;
            @eval('$parent = ' . $parentPopulate . ';');
            $fieldObj->setFromDB($parentPopulte);
        }
        foreach ($form as $fieldName=>$fieldObj) {
            $fieldPopulate = null;
            if (!$storageOptions->setIfIsSet($fieldPopulate, "fields/$fieldName/populate") ) {
                I2CE::raiseError("Cannot populate field $fieldName");
                continue;
            }
            $dbValue = null;
            @eval('$dbValue = ' . $fieldPopulate . ';');
            $fieldObj->setFromDB($dbValue);
        }
        return true;
    }
    

    
    /**
     * Return an array of all the record ids for a given form.
     * @param string $form
     * @return array
     */
    public function getRecords( $form) {
        $storageOptions = $this->getStorageOptions($form);
        if (!$storageOptions instanceof I2CE_MagicDataNode) {
            I2CE::raiseError("Bad storage options");
            return array();
        }
        $getRecords = null;
        if (!$storageOptions->setIfIsSet($getRecords,'records')) {
            I2CE::raiseError("Nothing to get records for $form");
            return array();
        }
        @eval('$records = ' . $getRecords . ';');
        if (!is_array($records)) {
            I2CE::raiseError("invalid eval");
            return array();
        }
        return $records;
    }

    /**
     * Checks to see if this storage mechansim implements the writing methods.
     * You need to override this in a subclass that implements writable
     * @returns boolean
     */
    public function isWritable() {
        return false;
    }





}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
