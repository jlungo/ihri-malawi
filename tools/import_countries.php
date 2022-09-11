#!/usr/bin/php
<?php
/*
 * © Copyright 2007, 2008 IntraHealth International, Inc.
 * 
 * This File is part of iHRIS
 * 
 * iHRIS is free software; you can redistribute it and/or modify
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
 */
/**
 * The page wrangler
 * 
 * This page loads the main HTML template for the home page of the site.
 * @package iHRIS
 * @subpackage DemoManage
 * @access public
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007, 2008 IntraHealth International, Inc. 
 * @since Demo-v2.a
 * @version Demo-v2.a
 */


require_once("./import_base.php");



/*********************************************
 *
 *      Process Class
 *
 *********************************************/

class CountryCodeProcessor extends Processor {


    protected function getExpectedHeaders() {
        return array(
            'name'=>'name',
            'two'=>'two',
            'numeric'=>'numeric'
            );
    }

    protected $existing_codes;
    public function __construct($file) {
        parent::__construct($file);
        $this->existing_codes= I2CE_FormStorage::listFields('country',array('alpha_two'));
        foreach ($this->existing_codes as $id=>&$data) {
            $data = $data['alpha_two'];
        }
        unset($data);
    }

    protected function _processRow()       {
        if (!is_array($this->mapped_data) ) {
            return true;
        }
        foreach ($this->mapped_data as &$d) {
            $d = trim($d);
        }
        unset($d);
        if( !array_key_exists('name',$this->mapped_data) || !is_string($this->mapped_data['name']) || strlen($this->mapped_data['name'])==0) {
            $this->addBadRecord("Nothing in name column");
            return false;
        }
        if ( !array_key_exists('two',$this->mapped_data) || !is_string($this->mapped_data['two']) || strlen($this->mapped_data['two'])==0) {
            $this->addBadRecord("Nothing in name two digit alpha numeric");
            return false;
        }
        if (in_array($this->mapped_data['two'],$this->existing_codes)) {
            return true;
        }
        
        if (! ($formObj = $this->ff->createContainer('country')) instanceof I2CE_Form) {
            I2CE::raiseError("Cannot instantiate form country", E_USER_ERROR);
            die();
        }

        $fieldheadermap = array('name'=>'name','code'=>'numeric','alpha_two'=>'two');
        foreach ($fieldheadermap as $field=>$header) {
            if ( !array_key_exists($header,$this->mapped_data) || !is_string($this->mapped_data[$header]) || strlen($this->mapped_data[$header])==0) {
                continue;
            }
            $formObj->getField($field)->setValue($this->mapped_data[$header]);
        }
        $formObj->getField('primary')->setValue(strtoupper($formObj->getField('primary')->getValue()) == 1 ? 1 : 0); //make sure the primary field is set
        $formObj->setID($this->mapped_data['two']);
        $this->save($formObj);
        return true;
    }



}




/*********************************************
 *
 *      Execute!
 *
 *********************************************/




#$file = dirname(__FILE__) . '/data/country_codes.csv' ;
                          
	$file = getcwd() . "/../data/country_codes.csv";
                          
	I2CE::raiseMessage("Loading from $file");
                          
    $processor = new CountryCodeProcessor($file);
    $processor->run();                         


    echo "Processing Statistics:\n";
    print_r( $processor->getStats());



# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
