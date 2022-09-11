<?php
/**
* © Copyright 2012 IntraHealth International, Inc.
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
* Class iHRIS_Module_UserStatistics
*
* @access public
*/


class iHRIS_Module_UserStatistics extends I2CE_Module {

    /**
     * @var MDB2 The database object
     */
    protected static $db;

    /**
     * @var array The list of prepared statements.
     */
    protected static $prepared;


    /**
     * @var array The cache of entry history information.
     */
    protected static $entries;

    /**
     * Set up the database and prepared statements if necessary.
     */
    protected static function setupDB() {
        if ( !self::$db instanceof MDB2_Driver_Common ) {
            self::$db = MDB2::singleton();
            self::$prepared = array();
            self::$prepared['user_log'] = self::$db->prepare("SELECT login,logout,activity FROM user_log WHERE user = ? ORDER BY activity DESC LIMIT ?", 
                    array( 'integer', 'integer' ), array( 'date', 'date', 'date' ) );
            if ( I2CE::pearError( self::$prepared['user_log'], "Error preparing user_log statement: " ) ) {
                return false;
            }
            /*
            self::$prepared['entry_log'] = self::$db->prepare("SELECT COUNT(DISTINCT e.record) AS records,(SELECT name FROM form WHERE id = (SELECT form FROM record WHERE id = e.record)) AS form,DATE(`date`) AS `date` FROM `entry` e WHERE DATE(`date`) >= (SELECT DISTINCT DATE(`date`) FROM entry WHERE who = ? ORDER BY `date` DESC LIMIT ?,1) AND who = ? GROUP BY 2,3 ORDER BY `date` DESC",
                    array( 'integer', 'integer', 'integer' ), array( 'integer', 'text', 'date' ) );
            if ( I2CE::pearError( self::$prepared['entry_log'], "Error preparing entry_log statement: " ) ) {
                return false;
            }
            self::$prepared['person_log'] = self::$db->prepare("SELECT COUNT(DISTINCT(p.person_id)) AS records,DATE(`date`) AS date FROM entry e,(SELECT r.id,id AS person_id FROM record r WHERE form = ( SELECT id FROM form WHERE name =  'person' ) UNION select r.id,r.parent_id AS person_id from record r where parent_form = 'person' UNION select r.id,p.parent_id AS person_id from record r join record p on p.id = r.parent_id AND p.parent_form = 'person' UNION select r.id,g.parent_id AS person_id from record r join record p on p.id = r.parent_id JOIN record g ON g.id = p.parent_id AND g.parent_form = 'person') AS p WHERE e.who = ? AND e.record = p.id GROUP BY 2 ORDER BY `date` DESC LIMIT ?",
                    array( 'integer', 'integer' ), array( 'integer', 'date' ) );
            if ( I2CE::pearError( self::$prepared['person_log'], "Error preparing person_log statement: " ) ) {
                return false;
            }
            */
            self::$prepared['entry_history'] = self::$db->prepare("SELECT DISTINCT record,DATE(`date`) AS `date` FROM `entry` WHERE `date` >= IFNULL((SELECT DISTINCT DATE(`date`) FROM entry WHERE who = ? ORDER BY `date` DESC LIMIT ?,1),(SELECT MIN(DATE(`date`)) FROM entry WHERE who = ?)) AND who = ? ORDER BY `date` DESC",
                    array( 'integer', 'integer', 'integer' ), array( 'integer', 'date' ) );
            if ( I2CE::pearError( self::$prepared['entry_history'], "Error preparing entry_history statement: " ) ) {
                return false;
            }

        }
        return true;
    }

    /**
     * Return the array of hooks available in this module.
     * @return array
     */
    public static function getHooks() {
        return array(
                'post_page_view_user' => 'post_page_view_user',
                );
    }

    /**
     * Handle any additional actions after all the child forms have
     * been loaded on the user view page.
     * @param iHRIS_PageViewUser $page
     */
    public function post_page_view_user( $page ) {
        if ( !$page instanceof iHRIS_PageViewUser ) {
            I2CE::raiseError("post_page_view_user hook called on a page that isn't the View User page.");
            return;
        }
        $user = $page->getViewUser();
        $template = $page->getTemplate();
        $defaults = I2CE::getConfig()->modules->UserStatistics->defaults;
        $login_limit = 10;
        $defaults->setIfIsSet( $login_limit, "login_limit" );


        $userAccess = I2CE::getUserAccess();
        $userid = $userAccess->getUserId( $user->getId() );
        $logins = self::getLoginHistory( $userid, $login_limit );
        $template->addHeaderLink( "view_user_statistics.css" );
        $stats_node = $template->appendFileById( "view_user_statistics_login_history.html", "div", "user_details" );
        $template->setDisplayDataImmediate( "history_limit", $login_limit, $stats_node );
        if ( $logins ) {

            while( $row = $logins->fetchRow() ) {
                $node = null;
                if ( $row->logout ) {
                    $node = $template->appendFileById("view_user_statistics_logged_out.html", "tr", "user_stats_login_history" );
                    $logout = I2CE_Date::fromDB($row->logout);
                    $template->setDisplayDataImmediate( "user_stats_logout", $logout->displayDate(), $node );
                } else {
                    $node = $template->appendFileById("view_user_statistics_logged_in.html", "tr", "user_stats_login_history" );
                    $activity = I2CE_Date::fromDB($row->activity);
                    $template->setDisplayDataImmediate( "user_stats_activity", $activity->displayDate(), $node );
                }
                $login = I2CE_Date::fromDB($row->login);
                $template->setDisplayDataImmediate( "user_stats_login", $login->displayDate(), $node );
            }
        }

        $days_limit = 5;
        $defaults->setIfIsSet( $days_limit, "days_forms_limit" );

        if ( !self::setupEntryHistory( $userid, $days_limit ) ) {
            I2CE::raiseError( "Unable to set up entry history for $userid ($days_limit days)" );
            return;
        }

        if ( self::$entries[$userid]['has_person'] ) {
            $person_node = $template->appendFileById( "view_user_statistics_person_history.html", "div", "user_details" );
            $template->setDisplayDataImmediate( "days_limit", $days_limit, $person_node );

            foreach( self::$entries[$userid]['dates'] as $date => $data ) {
                if ( count( $data['person'] ) > 0 ) {
                    $node = $template->appendFileById( "view_user_statistics_person_row.html", "tr", "user_stats_person_history" );
                    $dateObj = I2CE_Date::fromDB( $date );
                    $template->setDisplayDataImmediate( "user_stats_person_date", $dateObj->displayDate(), $node );
                    $template->setDisplayDataImmediate( "user_stats_person_count", count($data['person']), $node );
                }
            }
        }

        if ( self::$entries[$userid]['has_forms'] ) {
            $forms_node = $template->appendFileById( "view_user_statistics_form_history.html", "div", "user_details" );
            $template->setDisplayDataImmediate( "days_limit", $days_limit, $forms_node );

            $displays = array();
            $formConfig = I2CE::getConfig()->modules->forms->forms;
            foreach( self::$entries[$userid]['dates'] as $date => $data ) {
                $date_node = $template->appendFileById( "view_user_statistics_form_date.html", "tr", "user_stats_form_history" );
                $dateObj = I2CE_Date::fromDB( $date );
                $template->setDisplayDataImmediate( "form_date", $dateObj->displayDate(), $date_node );
                $total = 0;
                ksort($data['forms']);
                foreach( $data['forms'] as $form => $count ) {
                    if ( !array_key_exists( $form, $displays ) ) {
                        if ( !empty( $formConfig->$form->display ) ) {
                            $displays[$form] = $formConfig->$form->display;
                        } else {
                            $displays[$form] = $form;
                        }
                    }
                    $form_node = $template->appendFileById( "view_user_statistics_form_row.html", "tr", "user_stats_form_history" );
                    $template->setDisplayDataImmediate( "form_form", $displays[$form], $form_node );
                    $template->setDisplayDataImmediate( "form_count", $count, $form_node );
                    $total += $count;
                }
                $total_node = $template->appendFileById( "view_user_statistics_form_total.html", "tr", "user_stats_form_history" );
                $template->setDisplayDataImmediate( "form_date", $dateObj->displayDate(), $total_node );
                $template->setDisplayDataImmediate( "total_count", $total, $total_node );
            }
        }

        /*
        $defaults->setIfIsSet( $days_limit, "days_person_limit" );
        $persons = self::getPersonHistory( $userid, $days_limit );
        if ( $persons && $persons->numRows() > 0 ) {
            $person_node = $template->appendFileById( "view_user_statistics_person_history.html", "div", "user_details" );
            $template->setDisplayDataImmediate( "days_limit", $days_limit, $person_node );

            while( $row = $persons->fetchRow() ) {
                $node = $template->appendFileById( "view_user_statistics_person_row.html", "tr", "user_stats_person_history" );
                $date = I2CE_Date::fromDB( $row->date );
                $template->setDisplayDataImmediate( "user_stats_person_date", $date->displayDate(), $node );
                $template->setDisplayDataImmediate( "user_stats_person_count", $row->records, $node );

            }
        }

        $days_limit = 5;
        $defaults->setIfIsSet( $days_limit, "days_forms_limit" );

        $forms = self::getFormHistory( $userid, $days_limit );
        if ( $forms && $forms->numRows() > 0 ) {
            $forms_node = $template->appendFileById( "view_user_statistics_form_history.html", "div", "user_details" );
            $template->setDisplayDataImmediate( "days_limit", $days_limit, $forms_node );

            $counts = array();
            $displays = array();
            while ( $row = $forms->fetchRow() ) {
                if ( !array_key_exists( $row->date, $counts ) ) {
                    $counts[$row->date] = array();
                }
                $counts[$row->date][$row->form] = $row->records;
                $displays[$row->form] = '';
            }
            $formConfig = I2CE::getConfig()->modules->forms->forms;
            foreach( $displays as $form => &$display ) {
                if ( !empty( $formConfig->$form->display ) ) {
                    $display = $formConfig->$form->display;
                } else {
                    $display = $form;
                }
            }
            foreach( $counts as $date => $data ) {
                $date_node = $template->appendFileById( "view_user_statistics_form_date.html", "tr", "user_stats_form_history" );
                $dateObj = I2CE_Date::fromDB( $date );
                $template->setDisplayDataImmediate( "form_date", $dateObj->displayDate(), $date_node );
                $total = 0;
                ksort($data);
                foreach( $data as $form => $count ) {
                    $form_node = $template->appendFileById( "view_user_statistics_form_row.html", "tr", "user_stats_form_history" );
                    $template->setDisplayDataImmediate( "form_form", $displays[$form], $form_node );
                    $template->setDisplayDataImmediate( "form_count", $count, $form_node );
                    $total += $count;
                }
                $total_node = $template->appendFileById( "view_user_statistics_form_total.html", "tr", "user_stats_form_history" );
                $template->setDisplayDataImmediate( "form_date", $dateObj->displayDate(), $total_node );
                $template->setDisplayDataImmediate( "total_count", $total, $total_node );
            }
        }
        */

    }

    /**
     * Return a database rowset for the user login history.
     * @param integer $userid The user id
     * @param integer $limit The number of records to return.
     * @return MDB2_Result
     */
    protected static function getLoginHistory( $userid, $limit=10 ) {
        if ( !self::setupDB() ) {
            return false;
        }
        $result = self::$prepared['user_log']->execute( array( $userid, $limit ) );
        if ( I2CE::pearError( $result, "Error getting login history: " ) ) {
            return false;
        }
        return $result;
    }

    /**
     * Return the entry history for this person as an array
     * @param integer $userid The user id
     * @param integer $days The number of days to include
     * @return array
     */
    protected static function setupEntryHistory( $userid, $days = 5 ) {
        if ( !self::setupDB() ) {
            return false;
        }
        if ( !is_array( self::$entries ) ) {
            self::$entries = array();
        }
        if ( array_key_exists( $userid, self::$entries ) ) {
            return self::$entries[$userid];
        } else {
            self::$entries[$userid] = array( 'has_person' => false, 'has_forms' => false, 'dates' => array() );
            if ( !self::setupDB() ) {
                return false;
            }
            $result = self::$prepared['entry_history']->execute( array( $userid, $days-1, $userid, $userid ) );
            if ( I2CE::pearError( $result, "Error getting entry history: " ) ) {
                unset( self::$entries[$userid] );
                return false;
            }
            $records = array();
            $tally = array();
            while ( $row = $result->fetchRow() ) {
                $tally[$row->date][] = $row->record;
                $records[$row->record] = 1;
            }
            $result->free();
            if ( count($records) == 0 ) {
                return true;
            }
            $form_query = "SELECT r.id,f.name,r.parent_form,r.parent_id FROM record r JOIN form f ON f.id = r.form WHERE r.id IN ( " . implode( ',', array_keys($records) ) . " )";
            $result = self::$db->query( $form_query );
            if ( I2CE::pearError( $result, "Error getting record details: " ) ) {
                return false;
            }
            $forms = array();
            $person = array();
            $parents = array();
            while ( $row = $result->fetchRow() ) {
                $forms[$row->id] = $row->name;
                if ( !$row->parent_form || !$row->parent_id ) {
                    continue;
                }
                if ( $row->parent_form == 'person' ) {
                    $person[$row->id] = $row->parent_id;
                } else {
                    if ( !array_key_exists( $row->parent_id, $parents ) ) {
                        $parents[$row->parent_id] = array();
                    }
                    $parents[$row->parent_id][] = $row->id;
                }
            }
            $result->free();
            $loop_check = 0;
            while ( count($parents) > 0 ) {
                if ( $loop_check++ > 50 ) {
                    I2CE::raiseError( "Too many loops for the entry history for $userid ($days days)" );
                    return false;
                }
                $parent_query = "SELECT id,parent_form,parent_id FROM record WHERE id IN ( " . implode( ',', array_keys($parents) ) . " )";
                $result = self::$db->query( $parent_query );
                if ( $result->numRows() == 0 ) {
                    $parents = array();
                } else {
                    while ( $row = $result->fetchRow() ) {
                        if ( !$row->parent_form || !$row->parent_id ) {
                            unset( $parents[$row->id] );
                            continue;
                        }
                        if ( $row->parent_form == 'person' ) {
                            foreach( $parents[$row->id] AS $record ) {
                                $person[$record] = $row->parent_id;
                            }
                            unset( $parents[$row->id] );
                        } else {
                            if ( !array_key_exists( $row->parent_id, $parents ) ) {
                                $parents[$row->parent_id] = array();
                            }
                            foreach( $parents[$row->id] AS $record ) {
                                $parents[$row->parent_id][] = $record;
                            }
                            unset( $parents[$row->id] );
                        }
                    }
                }
            }
            foreach( $tally as $date => $records ) {
                if ( !array_key_exists( $date, self::$entries[$userid]['dates'] ) ) {
                    self::$entries[$userid]['dates'][$date] = array( 'forms' => array(), 'person' => array() );
                }
                foreach( $records as $record ) {
                    if ( !array_key_exists( $record, $forms ) ) {
                        I2CE::raiseMessage( "$record not in forms array." );
                        continue;
                    }
                    if ( !array_key_exists( $forms[$record], self::$entries[$userid]['dates'][$date]['forms'] ) ) {
                        self::$entries[$userid]['dates'][$date]['forms'][$forms[$record]] = 0;
                    }
                    self::$entries[$userid]['has_forms'] = true;
                    self::$entries[$userid]['dates'][$date]['forms'][$forms[$record]]++;
                    if ( array_key_exists( $record, $person ) ) {
                        if ( !array_key_exists( $person[$record], self::$entries[$userid]['dates'][$date]['person'] ) ) {
                            self::$entries[$userid]['dates'][$date]['person'][$person[$record]] = 0;
                        }
                        self::$entries[$userid]['has_person'] = true;
                        self::$entries[$userid]['dates'][$date]['person'][$person[$record]]++;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Return the number of forms modified by this person for a total of the given
     * days.
     * @param integer $userid The user id
     * @param integer $days The number of days to include
     * @return MDB2_Result
     */
    /*
    protected static function getFormHistory( $userid, $days=5 ) {
        if ( !self::setupDB() ) {
            return false;
        }
        $result = self::$prepared['entry_log']->execute( array( $userid, $days-1, $userid ) );
        if ( I2CE::pearError( $result, "Error getting form history: " ) ) {
            return false;
        }
        return $result;
    }
    */

    /**
     * Return the number of person records changed by the users totalled by day.  This includes any 
     * modifications to any child forms for the person.  Down to 4 levels of children.
     * @param integer $userid The user id
     * @param integer $days The number of days to include
     * @return MDB2_Result
     */
    /*
    protected static function getPersonHistory( $userid, $days=5 ) {
        if ( !self::setupDB() ) {
            return false;
        }

        $results = self::$prepared['person_log']->execute( array( $userid, $days ) );
        if ( I2CE::PearError( $results, "Error getting person history: " ) ) {
            return false;
        }
        return $results;
        
    }
    */




}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
