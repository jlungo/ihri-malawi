<?php

require_once("./import_base.php");

class PeopleProcessor extends Processor {

	protected $should_force;
	
	protected $prefixlist = array(	
		"Col" => "Col.",
		"Col." => "Col.",
		"Cpl" => "Cpl.",
		"Cpl." => "Cpl.",
		"DR" => "Dr.",
		"Dr" => "Dr.",
		"DR." => "Dr.",
		"Dr." => "Dr.",
		"Fr" => "Fr.",
		"FR" => "Fr.",
		"Fr." => "Fr.",
		"Hon" => "Hon.",
		"Hon." => "Hon.",
		"HON. PROF." => "Hon.",
		"Justice" => "Justice",
		"Lt" => "Lt.",
		"Lt." => "Lt.",
		"LT.COL" => "Lt. Col.",
		"Lt. Col." => "Lt. Col.",
		"Maj" => "Major",
		"Major" => "Major",
		"MII" => "Miss",
		"MIISS" => "Miss",
		"MIS" => "Miss",
		"Mis" => "Miss",
		"MISS" => "Miss",
		"Miss" => "Miss",
		"mMR" => "Mr.",
		"MR" => "Mr.",
		"MR." => "Mr.",
		"Mr" => "Mr.",
		"Mr." => "Mr.",
		"MRF" => "Mr.",
		"Mrfuka" => "Mr.",
		"SMr" => "Mr.",
		"MRS" => "Mrs.",
		"Mrs" => "Mrs.",
		"Mrs." => "Mrs.",
		"Mrs," => "Mrs.",
		"MRS." => "Mrs.",
		"MRSS" => "Mrs.",
		"MS" => "Ms",
		"Ms" => "Ms",
		"MS." => "Ms",
		"PASTOR" => "Pastor",
		"Pastor" => "Pastor",
		"Prof" => "Prof.",
		"PROF." => "Prof.",
		"Prof." => "Prof.",
		"Rev" => "Rev.",
		"Rev Fr" => "Rev.",
		"REV." => "Rev.",
		"Rev." => "Rev.",
		"Sgt" => "Sgt.",
		"Sgt." => "Sgt.",
		"Sister" => "Sister",
		);

	protected $maritallist = array(	
		"Divorced" => "Divorced",
		"Mariied" => "Married",
		"Marred" => "Married",
		"Married" => "Married",
		"Single" => "Single",
		"Widowed" => "Widowed",
		);
		
	protected $nationlist = array(	
		"BRITISH" => "United Kingdom",
		"BURUNDI" => "Burundi",
		"CUBAN" => "Cuba",
		"DRC" => "Democratic Republic of the Congo",
		"INDIA" => "India",
		"NIGERIAN" => "Nigeria",
		"PHILLIPINNES" => "Philippines",
		"RWANDA" => "Rwanda",
		"RWANDESE" => "Rwanda",
		"ZAMBIAN" => "Zambia",
		"ZIMBABWEAN" => "Zimbabwe",
		);		
	
	protected function getExpectedHeaders() {
		return array(
				//Person
				'prefix' => 'emEmpPrefix', //List
				'surname' => 'emEmpSurname',
				'firstname' => 'emEmpFirstName',
				'maiden_name' => 'emEmpMaidenName',
				'nationality' => 'emEmpCurrentNationality',
				'birthnationality' => 'emEmpBirthNationality', 
				'ta_of_origin' => 'emEmpTA', //Location
				'date_of_birth' => 'emEmpDOB',
				'gender' => 'emEmpSex', //List
				'marital_status' => 'emEmpMaritalStatusCode', //List
				'child_deps' => 'emChildDependantCount',
				'other_deps' => 'emOtherDependantCount',
				'is_retired' => 'emTerminate',
				'Employmenttype' => 'emEmpTermName',

				//Identification
				'employee_number' => 'emEmpRefNo',

				//Contacts
				'personal_address' => 'emEmpResAddress',
				'personal_primary_phone' => 'emEmpResTelephone',
				'personal_secondary_phone' => 'emEmpWorkTelephone',

				'work_address' => 'emEmpWorkAddress',
				'work_primary_phone' => 'emEmpWorkTelephone',

				'spouse_firstname' => 'emSpouseFirstName',
				'spouse_surname' => 'emSpouseSurname',

				'kin_firstname' => 'emNextKinFirstName',
				'kin_surname' => 'emNextKinSurname',
				'kin_address' => 'emNextKinResAddress',
				'kin_relationship' => 'emRelationship',
				'kin_primary_phone' => 'emNextKinResTelephone',
				'kin_secondary_phone' => 'emNextKinWorkTelephone',

				'emergency_firstname' => 'emEmeConFirstName',
				'emergency_surname' => 'emEmeConSurname',
				'emergency_relationship' => 'EmeConRelationship',
				'emergency_address' => 'emEmeConResAddress',
				'emergency_primary_phone' => 'emEmeConResTelephone',
				'emergency_secondary_phone' => 'emEmeConWorkTelephone',

				//Education not in global
				/*
				'edu_level' => 'edu_level', //List
				'edu_title' => 'edu_title',
				'edu_institution' => 'edu_institution',
				'edu_year_end' => 'edu_year_end',
				*/
				
				//Current Job
				'current_job' => 'emJobNo',
				'current_job_start_date' => 'CurrentPostDate',
				'joined_date' => 'emEmpJoinedDate',
				
		);
	}

	public function __construct($file, $should_force, $should_delete) {
		parent::__construct($file);
		$this->should_force = $should_force;
	}

	protected function _processRow()       {

		if(strlen($this->mapped_data['Employmenttype']) != 0) {
			$Employmenttype = $this->getListID("Employmenttype", $this->mapped_data['Employmenttype']);
		}
		echo "Employmenttype : " . $Employmenttype . " - " . ucwords(strtolower($this->mapped_data['Employmenttype'])) . "\n";

		// Need this for employee number identification object 
		if(strlen($this->mapped_data['employee_number']) != 0) {
				//Find the right person record 		
				$where = array(
	            'operator'=>'FIELD_LIMIT',
	            'field'=>'id_num',
	            'style'=>'equals',
	            'data'=> array('value'=>$this->mapped_data['employee_number'])
	            );	
	            	            
	            $id = I2CE_FormStorage::search("person_id",false,$where,array(),true);
				//echo "\n" . $id . "\n";
				
				if (! ($formObj = $this->ff->createContainer('person_id|' . $id)) instanceof I2CE_Form) {
					I2CE::raiseError("Cannot instantiate person_id form", E_USER_ERROR);
					die();
				}
				$formObj->populate();
				if($formObj->getField('id_num')->getValue() == $this->mapped_data['employee_number']) {				
					echo $formObj->getField('id_num')->getValue() . "\n";	
									
					$obj = $this->ff->createContainer($formObj->getField('parent')->getValue());
 					if (!$obj){
 						echo "Not a valid id " . $id;
						die();
					}
					$obj->populate();
    				$obj->getField('Employmenttype')->setFromPost('Employmenttype|' . $Employmenttype);
    				$this->save($obj);
    				$obj->cleanup();													
				}	            
	            $formObj->cleanup();																																																																										
		}																																																																																									

		return true;
	}

	protected function getListID($listname, $value) {
		$where = array('operator' => 'FIELD_LIMIT', 'field'=>'name', 'style'=>'equals', 'data'=>array('value'=>$value));
		$object_id = I2CE_FormStorage::search($listname, false, $where, array(), true);

		if (!$object_id && $this->should_force) {
			if (! ($formObj = $this->ff->createContainer($listname)) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate $listname form", E_USER_ERROR);
				die();
			}
			$formObj->getField('name')->setValue($value);
			echo "Saving new $listname '$value' \n";
			$object_id = $this->save($formObj);
		}

		if (!$object_id) {
			I2CE::raiseError("The value '$value' is not a valid $listname and force create is set to false", E_USER_ERROR);
			die();
		}

		return $object_id;
	}



}


/*********************************************
 *
*      Execute!
*
*********************************************/

$should_delete = in_array("delete", $argv);
$should_force = in_array("force", $argv);
$use_quick_data = in_array("quick", $argv);

if ($use_quick_data) {
	$file = getcwd() . "/../data/People-Quick.csv";
} else {
	$file = getcwd() . "/../data/People.csv";
}

I2CE::raiseMessage("Loading from $file");

$processor = new PeopleProcessor($file, $should_force, $should_delete);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

