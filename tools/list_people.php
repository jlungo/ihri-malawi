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

function getListID($listname, $value) {
		$where = array('operator' => 'FIELD_LIMIT', 'field'=>'name', 'style'=>'equals', 'data'=>array('value'=>$value));
		$object_id = I2CE_FormStorage::search($listname, false, $where, array(), true);

		if (!$object_id) {
			print_r("The value '$value' is not a valid $listname");
			die();
		}
		//print_r($object_id);
		return $object_id;
}


function getValue($id,$ff) {

	if (!$ff) {
		$ff = I2CE_FormFactory::instance();
	}
	if(!$id[1]=="") { 
		$obj = $ff->createContainer($id);
    	if (!$obj){
 			//print_r("Not a valid id '$id' ");
			return "* Unknown *";
		}  
    	$obj->populate();
    	$value = $obj->getField('name')->getValue();
   	 	$obj->cleanup();
		return $value;
	}
	return "* Unknown *";
}

function getPersonIdValue($idtype,$parent,$ff) {

	//Find the right record
   	$where = array(
                'operator' => 'AND',
                'operand' => array (
                    0 => array (
        						'operator'=>'FIELD_LIMIT',
        						'field'=>'id_type',
        						'style'=>'equals',
        						'data'=> array('value'=>'id_type' . "|". $idtype)
                        	),
                    1 => array (
        						'operator'=>'FIELD_LIMIT',
        						'field'=>'parent',
        						'style'=>'equals',
        						'data'=> array('value'=>$parent)
                    		)
                	)
    			);
    //print_r($where);			
    $id = I2CE_FormStorage::search("person_id", false, $where, array(), true);
	if (!$id) {
		echo "No details for this person " . $parent . " type " . $idtype;
		die();
	}
	if (!$ff) {
		$ff = I2CE_FormFactory::instance();
	} 
	$obj = $ff->createContainer('person_id|' .$id);
    if (!$obj){
 			echo "Not a valid id " . $id;
			die();
	}

	$obj->populate();
    $value = $obj->getField('id_num')->getValue();
    $obj->cleanup();
	return $value;
									
}

function getjobno($person) {
    if (!$person instanceof iHRIS_Person) {
        return null;
    }
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'end_date',
        'style'=>'null'
        );
    $per_pos_id = I2CE_FormStorage::search('person_position', $person->getNameId(),$where,'-start_date',1);
    if (!$per_pos_id) {
        return null;
    }
    

    $pos = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
    if (!$pos instanceof iHRIS_PersonPosition) {
        return null;
    }
    $pos->populate();

    $job = I2CE_FormFactory::instance()->createContainer($pos->getField('position')->getValue());
    if (!$job instanceof iHRIS_Position) {
        return null;
    }
    $job->populate();

    return $job->getField('code')->getValue();
}

function getjobStartdate($person) {
    if (!$person instanceof iHRIS_Person) {
        return null;
    }
    $where = array(
        'operator'=>'FIELD_LIMIT',
        'field'=>'end_date',
        'style'=>'null'
        );
    $per_pos_id = I2CE_FormStorage::search('person_position', $person->getNameId(),$where,'-start_date',1);
    if (!$per_pos_id) {
        return null;
    }
    

    $pos = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
    if (!$pos instanceof iHRIS_PersonPosition) {
        return null;
    }
    $pos->populate();

    return $pos->getField('start_date')->getValue()->displayDate();
}




$dir = getcwd();
chdir("../pages");
$i2ce_site_user_access_init = null;
$i2ce_site_user_database = null;
require_once( getcwd() . DIRECTORY_SEPARATOR . 'config.values.php');

$local_config = getcwd() . DIRECTORY_SEPARATOR .'local' . DIRECTORY_SEPARATOR . 'config.values.php';
if (file_exists($local_config)) {
    require_once($local_config);
}

if(!isset($i2ce_site_i2ce_path) || !is_dir($i2ce_site_i2ce_path)) {
    echo "Please set the \$i2ce_site_i2ce_path in $local_config";
    exit(55);
}

require_once ($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'I2CE_config.inc.php');

I2CE::raiseMessage("Connecting to DB");
putenv('nocheck=1');
if (isset($i2ce_site_dsn)) {
    @I2CE::initializeDSN($i2ce_site_dsn,   $i2ce_site_user_access_init,    $i2ce_site_module_config);         
} else if (isset($i2ce_site_database_user)) {    
    I2CE::initialize($i2ce_site_database_user,
                     $i2ce_site_database_password,
                     $i2ce_site_database,
                     $i2ce_site_user_database,
                     $i2ce_site_module_config         
        );
} else {
    die("Do not know how to configure system\n");
}

I2CE::raiseMessage("Connected to DB");

require_once($i2ce_site_i2ce_path . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'CLI.php');
$ff = I2CE_FormFactory::instance();
$user = new I2CE_User();

echo 'Prefix' . "," .	'surname' . "," .	'firstname' . "," .	'MaidenName' . "," .
	'nationality' . "," .	'birthnationality' . "," .	'residence' . "," .
	'FirstAppointmentDate' . "," .	'birth_date' . "," .	'gender' . "," .	'marital_status' . "," .
	'ChildDependents' . "," .	'OtherDependents' . "," .	'isretired' . "," .	'Employmenttype' . "," .
	'employee_number' . "," .	'current_job' . "," .	'current_job_start_date' . "," .
	'joined_date' .
    	 "\n";
$ids = I2CE_FormStorage::search('person');
foreach ($ids as $id) {
    $personObj = $ff->createContainer('person|' . $id);
    $personObj->populate();
 	$employee_number_type_id = getListID("id_type", "Employee Number");
          
    echo getValue($personObj->getField('Prefix')->getValue(),$ff) . "," .
    			$personObj->getField('surname')->getValue() . "," . 
    			$personObj->getField('firstname')->getValue() . "," . 
    			$personObj->getField('MaidenName')->getValue() . "," . 
    			getValue($personObj->getField('nationality')->getValue(),$ff) . "," .  

    			getValue($personObj->getField('birthnationality')->getValue(),$ff) . "," .  
    			getValue($personObj->getField('residence')->getValue(),$ff) . "," .
    			$personObj->getField('FirstAppointmentDate')->getValue()->displayDate() . "," .
     			$personObj->getField('birth_date')->getValue()->displayDate() . "," .
     			
    			getValue($personObj->getField('gender')->getValue(),$ff) . "," .
    			getValue($personObj->getField('marital_status')->getValue(),$ff) . "," . 
    			$personObj->getField('ChildDependents')->getValue() . "," . 
    			$personObj->getField('OtherDependents')->getValue() . "," .  
    			$personObj->getField('isretired')->getValue() . "," .  
     			getValue($personObj->getField('Employmenttype')->getValue(),$ff) . "," .
    			getPersonIdValue($employee_number_type_id,'person|' . $id,$ff) . "," .  
    			getJobNo($personObj) . "," .  
    			getjobStartdate($personObj) . 

    			"\n";
 
    //die("Just one for test\n");
}


