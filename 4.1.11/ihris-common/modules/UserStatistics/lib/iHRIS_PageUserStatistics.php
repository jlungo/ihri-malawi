<?php
/**
* © Copyright 2013 IntraHealth International, Inc.
* 
* This File is part of iHRIS Common 
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
*
* @package iHRIS
* @subpackage Common
* @author Luke Duncan <lduncan@intrahealth.org>
* @version v4.1
* @since v4.1
* @filesource
*/
/**
* Class iHRIS_PageUserStatistics
*
* @access public
*/


class iHRIS_PageUserStatistics extends I2CE_Page_CustomReports {

    /**
     * Perform any actions for the page.
     * @return boolean
     */
    public function action() {
        if ( !parent::action() ) {
            return false;
        }
        if ( !$this->hasPermission('role(admin)') ) {
            return false;
        }
        $this->template->setAttribute( "class", "active", "menuConfigure", "a[@href='configure']" );
        $this->template->appendFileById( "menu_configure.html", "ul", "menuConfigure" );
        $this->template->setAttribute( "class", "active", "menuUserStatistics", "a[@href='UserStatistics']" );
        $displayObj = new iHRIS_CustomReport_Display_UserStatistics( $this, "UserStatistics" );

        $contentNode = $this->template->getElementById('siteContent');
        if ( !$contentNode instanceof DOMNode ) {
            I2CE::raiseError("Couldn't find siteContent node.");
            return false;
        }
        $this->template->addHeaderLink("customReports_display_Default.css");
        $this->template->setDisplayData( "limit_description", false );
        return $displayObj->display($contentNode );
    }


}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
