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
* @package common
* @subpackage uuid_map
* @author Carl Leitner <litlfred@ibiblio.org>
* @version v4.0.0
* @since v4.0.0
* @filesource 
*/ 
/** 
* Class iHRIS_Module_UUID_Map
* 
* @access public
*/


class iHRIS_Module_UUID_Map extends I2CE_Module{
    
    /**
     * Method called when the module is enabled for the first time.
     * @param boolean -- returns true on success. false on error.
     */
    public function action_initialize() {
        I2CE::raiseError("Initializing UUID Map Tables");
        if (!I2CE_Util::runSQLScript('init_uuid_table.sql')) {
            I2CE::raiseError("Could not initialize uuid table");
            return false;
        }
        return true;
    }


    /**
     * Upgrade this module if necessary
     * @param string $old_vers
     * @param string $new_vers
     * @return boolean
     */
    public function upgrade( $old_vers, $new_vers ) {
        if ( I2CE_Validate::checkVersion( $old_vers, '<', '4.0.6.1' ) ) {
            if (!$this->addLastModifiedColumn()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Adds the last_modifed column to the uuid_map table if it is not there
     * @returns true on success
     */
    protected function addLastModifiedColumn() {
        $db = MDB2::singleton();
        $rows = $db->queryAll("SHOW FULL COLUMNS FROM `uuid_map` WHERE Field='last_modifed'");
        if(count($rows)> 0) {
            I2CE::raiseError("uuid_map table already has last_modifed");
        } else {
            $qry_alter = "ALTER TABLE `uuid_map` ADD COLUMN   `last_modified` timestamp  NULL DEFAULT CURRENT_TIMESTAMP;";
            if ( I2CE::pearError( $db->exec($qry_alter), "Error adding parent_id, parent_form column to $table table:")) {
                return false;
            }
        }
        return true;
    }



    /**
     * Checkst to see if the UUID Pecl module is installed
     * @returns boolean
     */
    public static function hasUUID() {
        return extension_loaded('uuid');
    }

}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
