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

		if ($should_delete) {

			echo "Removing existing person data\n";

			$ids = I2CE_FormStorage::search('MoHEducation');
			echo "Deleting " . sizeof($ids) . " education records\n";
			foreach ($ids as $id) {
				$obj = $this->ff->createContainer('MoHEducation|' . $id);
				$obj->populate();
				$obj->delete();
			}

			$ids = I2CE_FormStorage::search('person_id');
			echo "Deleting " . sizeof($ids) . " person identification records\n";
			foreach ($ids as $id) {
				$obj = $this->ff->createContainer('person_id|' . $id);
				$obj->populate();
				$obj->delete();
			}

			$ids = I2CE_FormStorage::search('person');
			echo "Deleting " . sizeof($ids) . " person records\n";
			foreach ($ids as $id) {
				$obj = $this->ff->createContainer('person|' . $id);
				$obj->populate();
				$obj->delete();
			}
		}
	}

	protected function _processRow()       {
	
		//Ensure we have all required list data for this row, creating it if we need to and if thats allowed
		if(strlen($this->mapped_data['prefix']) != 0) {	
				// look up prefix in list			
				if (array_key_exists($this->mapped_data['prefix'],$this->prefixlist)) {
  						//echo "Prefix: " . $this->mapped_data['prefix'] . " - " . $this->prefixlist[$this->mapped_data['prefix']] . "\n";
  						$prefix_id = $this->getListID("Prefix", $this->prefixlist[$this->mapped_data['prefix']]);
  				}
				else
  				{
  						$prefix_id = "";
  				}
		}
		
		if(strlen($this->mapped_data['nationality']) != 0) {
				// look up Country in list	
				if (array_key_exists($this->mapped_data['nationality'],$this->nationlist)) {
  						echo "Current Country: " . $this->mapped_data['nationality'] . " - " . $this->nationlist[$this->mapped_data['nationality']] . "\n";
  						$nationality_id = $this->getListID("country", $this->nationlist[$this->mapped_data['nationality']]);
  				}
				else
  				{
  						// Assuming 'Malawi' not spelt correctly
  						$nationality_id = $this->getListID("country", "Malawi");
  						//echo "Current Country: " . $this->mapped_data['nationality'] . " - " . "Malawi" . "\n";

  				}
		}

		if(strlen($this->mapped_data['birthnationality']) != 0) {		
				// look up Country in list			
				if (array_key_exists($this->mapped_data['birthnationality'],$this->nationlist)) {
  						echo "Birth Country: " . $this->mapped_data['birthnationality'] . " - " . $this->nationlist[$this->mapped_data['birthnationality']] . "\n";
  						$birthnationality_id = $this->getListID("country", $this->nationlist[$this->mapped_data['birthnationality']]);
  				}
				else
  				{
  						// Assuming 'Malawi' not spelt correctly
  						$birthnationality_id = $this->getListID("country", "Malawi");
  						// echo "Birth Country: " . $this->mapped_data['nationality'] . " - " . "Malawi" . "\n";
  				}
		}
		
		if(strlen($this->mapped_data['marital_status']) != 0) {
				// look up marital status in list			
				if (array_key_exists($this->mapped_data['marital_status'],$this->maritallist)) {
  						// echo "Marital status : " . $this->mapped_data['marital_status'] . " - " . $this->maritallist[$this->mapped_data['marital_status']] . "\n";
  						$marital_status_id = $this->getListID("marital_status", $this->maritallist[$this->mapped_data['marital_status']]);
  				}
				else
  				{
  						$marital_status_id = "";
  				}			
		}

		
		if(strlen($this->mapped_data['ta_of_origin']) != 0) {
			$ta_of_origin_id = $this->getListID("county", ucwords(strtolower($this->mapped_data['ta_of_origin'])));
		}
		//echo "ta_of_origin_id : " . $ta_of_origin_id . " - " . ucwords(strtolower($this->mapped_data['ta_of_origin'])) . "\n";



		if(strlen($this->mapped_data['gender']) != 0) {
			$gender_id = $this->getListID("gender", $this->mapped_data['gender']);
		}
		//echo "gender_id : " . $gender_id . " - " . ucwords(strtolower($this->mapped_data['gender'])) . "\n";


	    if(strlen($this->mapped_data['is_retired']) != 0) {
	    	$is_retired = False;
			if ($this->mapped_data['is_retired'] == "1"){
				$is_retired = True;
			}
		}
	    //echo "is_retired : " . $is_retired . "\n";


		if(strlen($this->mapped_data['Employmenttype']) != 0) {
			$Employmenttype = $this->getListID("Employmenttype", $this->mapped_data['Employmenttype']);
		}
		echo "Employmenttype : " . $Employmenttype . " - " . ucwords(strtolower($this->mapped_data['Employmenttype'])) . "\n";

		$employee_number_type_id = $this->getListID("id_type", "Employee Number");
		$personal_type_id = $this->getListID("MoHContactType", "Personal");
		$work_type_id = $this->getListID("MoHContactType", "Work");
		$spouse_type_id = $this->getListID("MoHContactType", "Spouse");
		$kin_type_id = $this->getListID("MoHContactType", "Next of Kin");
		$emergency_type_id = $this->getListID("MoHContactType", "Emergency");

		//Construct the person object
		if (! ($formObj = $this->ff->createContainer('person')) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate person form", E_USER_ERROR);
			die();
		}
		$formObj->getField('Prefix')->setFromPost('Prefix|' . $prefix_id);
		$formObj->getField('surname')->setValue(ucwords(strtolower($this->mapped_data['surname'])));
		$formObj->getField('firstname')->setValue(ucwords(strtolower($this->mapped_data['firstname'])));
		if(strlen($this->mapped_data['Employmenttype']) != 0) {
		    $formObj->getField('Employmenttype')->setFromPost('Employmenttype|' . $Employmenttype);
		}
		if(strlen($this->mapped_data['maiden_name']) != 0) {
			$formObj->getField('MaidenName')->setValue(ucwords(strtolower($this->mapped_data['maiden_name'])));
		}
		if(strlen($this->mapped_data['nationality']) != 0) {
			$formObj->getField('nationality')->setFromPost('country|' . $nationality_id);
		}
		if(strlen($this->mapped_data['birthnationality']) != 0) {
			$formObj->getField('birthnationality')->setFromPost('country|' . $birthnationality_id);
		}
		if(strlen($this->mapped_data['ta_of_origin']) != 0) {
			$formObj->getField('residence')->setFromPost('county|' . $ta_of_origin_id);
		}
		$parsed_start_date = date_parse( $this->mapped_data['joined_date']);
		$formObj->getField('FirstAppointmentDate')->setValue(I2CE_Date::getDate( $parsed_start_date['day'], $parsed_start_date['month'], $parsed_start_date['year'] ));
		$parsed_dob = date_parse( $this->mapped_data['date_of_birth']);
		$formObj->getField('birth_date')->setValue(I2CE_Date::getDate( $parsed_dob['day'], $parsed_dob['month'], $parsed_dob['year'] ));
		$formObj->getField('gender')->setFromPost('gender|' . $gender_id);
		$formObj->getField('marital_status')->setFromPost('marital_status|' . $marital_status_id);
		$formObj->getField('ChildDependents')->setValue($this->mapped_data['child_deps']);
		$formObj->getField('OtherDependents')->setValue($this->mapped_data['other_deps']);
		$formObj->getField('isretired')->setValue($this->mapped_data['is_retired']);
		echo "Saving person '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
		$person_id = $this->save($formObj);

		//Construct employee number identification entry
		if (! ($formObj = $this->ff->createContainer('person_id')) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate person_id form", E_USER_ERROR);
			die();
		}
		$formObj->getField('id_type')->setFromPost('id_type|' . $employee_number_type_id);
		$formObj->getField('id_num')->setValue($this->mapped_data['employee_number']);
		$formObj->getField('parent')->setFromPost('person|' . $person_id);
		echo "Saving identification info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
		$this->save($formObj);

		//Construct contact info entries

		//Personal
		if (strlen($this->mapped_data['personal_address']) != 0 || strlen($this->mapped_data['personal_primary_phone']) != 0) {
			if (! ($formObj = $this->ff->createContainer('MoHContact')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate MoHContact form", E_USER_ERROR);
				die();
			}
			$formObj->getField('ContactType')->setFromPost('MoHContactType|' . $personal_type_id);
			$formObj->getField('ResAddress')->setValue(ucwords(strtolower($this->mapped_data['personal_address'])));
			$formObj->getField('CellPhone')->setValue($this->mapped_data['personal_primary_phone']);
			$formObj->getField('ResPhone')->setValue($this->mapped_data['personal_secondary_phone']);
			$formObj->getField('parent')->setFromPost('person|' . $person_id);
			echo "Saving personal contact info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
			$this->save($formObj);
		}

		//Work
		if (strlen($this->mapped_data['work_address']) != 0 || strlen($this->mapped_data['work_primary_phone']) != 0) {
			if (! ($formObj = $this->ff->createContainer('MoHContact')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate MoHContact form", E_USER_ERROR);
				die();
			}
			$formObj->getField('ContactType')->setFromPost('MoHContactType|' . $work_type_id);
			$formObj->getField('WorkAddress')->setValue(ucwords(strtolower($this->mapped_data['work_address'])));
			$formObj->getField('WorkPhone')->setValue($this->mapped_data['work_primary_phone']);
			$formObj->getField('parent')->setFromPost('person|' . $person_id);
			echo "Saving work contact info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
			$this->save($formObj);
		}

		//Spouse
		if (strlen($this->mapped_data['spouse_firstname']) != 0) {
			if (! ($formObj = $this->ff->createContainer('MoHContact')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate MoHContact form", E_USER_ERROR);
				die();
			}
			$formObj->getField('ContactType')->setFromPost('MoHContactType|' . $spouse_type_id);
			$formObj->getField('Firstname')->setValue(ucwords(strtolower($this->mapped_data['spouse_firstname'])));
			$formObj->getField('Surname')->setValue(ucwords(strtolower($this->mapped_data['spouse_surname'])));
			$formObj->getField('parent')->setFromPost('person|' . $person_id);
			echo "Saving spouse contact info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
			$this->save($formObj);
		}

		//Next of Kin
		if (strlen($this->mapped_data['kin_firstname']) != 0) {
			if (! ($formObj = $this->ff->createContainer('MoHContact')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate MoHContact form", E_USER_ERROR);
				die();
			}
			$formObj->getField('ContactType')->setFromPost('MoHContactType|' . $kin_type_id);
			$formObj->getField('Firstname')->setValue(ucwords(strtolower($this->mapped_data['kin_firstname'])));
			$formObj->getField('Surname')->setValue(ucwords(strtolower($this->mapped_data['kin_surname'])));
			$formObj->getField('Relationship')->setValue(ucwords(strtolower($this->mapped_data['kin_relationship'])));
			$formObj->getField('ResAddress')->setValue(ucwords(strtolower($this->mapped_data['kin_address'])));
			$formObj->getField('CellPhone')->setValue($this->mapped_data['kin_primary_phone']);
			$formObj->getField('ResPhone')->setValue($this->mapped_data['kin_secondary_phone']);
			$formObj->getField('parent')->setFromPost('person|' . $person_id);
			echo "Saving next of kin contact info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
			$this->save($formObj);
		}

		//Emergency
		if (strlen($this->mapped_data['emergency_firstname']) != 0) {
			if (! ($formObj = $this->ff->createContainer('MoHContact')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate MoHContact form", E_USER_ERROR);
				die();
			}
			$formObj->getField('ContactType')->setFromPost('MoHContactType|' . $emergency_type_id);
			$formObj->getField('Firstname')->setValue(ucwords(strtolower($this->mapped_data['emergency_firstname'])));
			$formObj->getField('Surname')->setValue(ucwords(strtolower($this->mapped_data['emergency_surname'])));
			$formObj->getField('Relationship')->setValue(ucwords(strtolower($this->mapped_data['emergency_relationship'])));
			$formObj->getField('ResAddress')->setValue(ucwords(strtolower($this->mapped_data['emergency_address'])));
			$formObj->getField('CellPhone')->setValue($this->mapped_data['emergency_primary_phone']);
			$formObj->getField('ResPhone')->setValue($this->mapped_data['emergency_secondary_phone']);
			$formObj->getField('parent')->setFromPost('person|' . $person_id);
			echo "Saving emergency contact info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
			$this->save($formObj);
		}

		//Construct education infomation
		// not in global
		/* if ($edu_level_id != 0) {
			if (! ($formObj = $this->ff->createContainer('MoHEducation')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate MoHEducation form", E_USER_ERROR);
				die();
			}
			$formObj->getField('educationlevel')->setFromPost('EducationType|' . $edu_level_id);
			$formObj->getField('title')->setValue($this->mapped_data['edu_title']);
			$formObj->getField('institution')->setValue($this->mapped_data['edu_institution']);
			$formObj->getField('courseyearend')->setValue($this->mapped_data['edu_year_end']);
			$formObj->getField('parent')->setFromPost('person|' . $person_id);
			echo "Saving education info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
			$this->save($formObj);
		}
		*/

		//Construct current job information
		if (strlen($this->mapped_data['current_job']) != 0) {
			if (! ($formObj = $this->ff->createContainer('person_position')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate person_position form", E_USER_ERROR);
				die();
			}
			//Find the right job - this cannot be force created
			$where = array('operator' => 'FIELD_LIMIT', 'field'=>'code', 'style'=>'equals', 'data'=>array('value'=>$this->mapped_data['current_job']));
			$job_id = I2CE_FormStorage::search("position", false, $where, array(), true);
			if (!$job_id) {
				echo "WARNING: Unable to associate '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "' to their current job, job '" . $this->mapped_data['current_job'] . "' could not be found\n";
			} 
			else 
			{
				$formObj->getField('position')->setFromPost('position|' . $job_id);
				$parsed_start_date = date_parse( $this->mapped_data['current_job_start_date']);
				$formObj->getField('start_date')->setValue(I2CE_Date::getDate( $parsed_start_date['day'], $parsed_start_date['month'], $parsed_start_date['year'] ));
				$formObj->getField('parent')->setFromPost('person|' . $person_id);
				echo "Saving current job info for '" . $this->mapped_data['surname'] . ", " . $this->mapped_data['firstname'] . "'\n";
				$this->save($formObj);
			}
		}
		$formObj->cleanup();		
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

