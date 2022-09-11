<?php

require_once("./import_base.php");

class JobProcessor extends Processor {

	protected $should_force;
	
	protected $costcentrelist = array(	
		"001" => "HEADQUARTERS",
		"002" => "QUEEN ELIZABETH CENTRAL HOSPITAL",
		"003" => "ZOMBA CENTRAL HOSPITAL",
		"004" => "ZOMBA MENTAL HOSPITAL",
		"005" => "KAMUZU CENTRAL HOSPITAL",
		"006" => "MZUZU CENTRAL HOSPITAL",
		"007" => "NSANJE DISTRICT HEALTH OFFICE",
		"008" => "CHIKWAWA DISTRICT HEALTH OFFICE",
		"009" => "MULANJE DISTRICT HEALTH OFFICE",
		"010" => "THYOLO DISTRICT HEALTH OFFICE",
		"011" => "CHIRADZULU DISTRICT HEALTH OFFICE",
		"012" => "BLANTYRE DISTRICT HEALTH OFFICE",
		"013" => "MWANZA DISTRICT HEALTH OFFICE",
		"014" => "ZOMBA DISTRICT HEALTH OFFICE",
		"015" => "MACHINGA DISTRICT HEALTH OFFICE",
		"016" => "MANGOCHI DISTRICT HEALTH OFFICE",
		"017" => "PHALOMBE DISTRICT HEALTH OFFICE",
		"018" => "BALAKA DISTRICT HEALTH OFFICE",
		"019" => "NTCHEU DISTRICT HEALTH OFFICE",
		"020" => "DEDZA DISTRICT HEALTH OFFICE",
		"021" => "LILONGWE DISTRICT HEALTH OFFICE",
		"022" => "SALIMA DISTRICT HEALTH OFFICE",
		"023" => "DOWA DISTRICT HEALTH OFFICE",
		"024" => "MCHINJI DISTRICT HEALTH OFFICE",
		"025" => "NKHOTAKOTA DISTRICT HEALTH OFFICE",
		"026" => "NTCHISI DISTRICT HEALTH OFFICE",
		"027" => "KASUNGU DISTRICT HEALTH OFFICE",
		"028" => "MZIMBA DISTRICT HEALTH OFFICE",
		"029" => "NKHATABAY DISTRICT HEALTH OFFICE",
		"030" => "RUMPHI DISTRICT HEALTH OFFICE",
		"031" => "CHITIPA DISTRICT HEALTH OFFICE",
		"032" => "KARONGA DISTRICT HEALTH OFFICE",
		"034" => "HEALTH SERVICE COMMISSION",
		"035" => "NENO DISTRICT HEALTH OFFICE",
		);
		
	protected function getExpectedHeaders() {
		return array(		
				'JobNo' => 'esJobNo',
				'PostDescription' => 'esPostDescription',
				'CosCenRefCode' => 'esCosCenRefCode',
				'CosCenName' => 'esCosCenName',
				'GradeCode' => 'esGradeCode',
				'PostCode' => 'esPostCode', 								
				'ProgrammeRefCode' => 'esProgrammeRefCode',
				'ProgrammeName' => 'esProgrammeName',
				'SubProRefCode' => 'esSubProRefCode',
				'SubProName' => 'esSubProName',
				'SubSubRefCode' => 'esSubSubRefCode',
				'SubSubName' => 'esSubSubName',
				'SectionRefCode' => 'esSectionRefCode',
				'SectionName' => 'esSectionName',
				'UnitRefCode' => 'esUnitRefCode',
				'UnitName' => 'esUnitName',
				'EstbType' => 'esEstbType',
				'EndDate' => 'esEndDate',

		);
	}

	public function __construct($file, $should_force, $should_delete) {
		parent::__construct($file);
		$this->should_force = $should_force;



		if ($should_delete) {

			echo "Removing existing job data\n";

			$ids = I2CE_FormStorage::search('position');
			echo "Deleting " . sizeof($ids) . " job records\n";
			foreach ($ids as $id) {
				$obj = $this->ff->createContainer('position|' . $id);
				$obj->populate();
				$obj->delete();
			}

		}
	}

	protected function _processRow()       {
		
		if(strlen($this->mapped_data['GradeCode']) != 0) {
			$grade_id = $this->getListID("Grade", $this->mapped_data['GradeCode']);
		}	
	
		//Find the right position - this cannot be force created
       	$where = array(
                    'operator' => 'AND',
                    'operand' => array (
                        0 => array (
            						'operator'=>'FIELD_LIMIT',
            						'field'=>'Grade',
            						'style'=>'equals',
            						'data'=> array('value'=>'Grade|' . $grade_id)
                            	),
                        1 => array (
            						'operator'=>'FIELD_LIMIT',
            						'field'=>'code',
            						'style'=>'equals',
            						'data'=> array('value'=>$this->mapped_data['PostCode'])
                        		)
                    	)
        			);
        			
        echo "Post code: " . $this->mapped_data['PostCode'] . " - grade:" . $this->mapped_data['GradeCode'] . "\n";			
        $position_id = I2CE_FormStorage::search("job", false, $where, array(), true);
		if (!$position_id) {
			I2CE::raiseError("Cannot import job, the position '" . "Post code: " . $this->mapped_data['PostCode'] . " - grade:" . $this->mapped_data['GradeCode'] . "' does not exist", E_USER_ERROR);
			die();
		}
		$position_obj = $this->ff->createContainer('job|' . $position_id);
		$position_obj->populate();
		$position_title = $position_obj->getField('title')->getValue();
	
		if(strlen($this->mapped_data['CosCenRefCode']) != 0) {
				if(strlen($this->mapped_data['CosCenRefCode']) == 3) {				
					$costcentre_id = $this->getListID("facility", ucwords(strtolower($this->mapped_data['CosCenName'])));
					$globalcostcentre_id = $this->getListID("globalcostcentre", ucwords(strtolower($this->mapped_data['CosCenName'])));				
				}
				
				if(strlen($this->mapped_data['CosCenRefCode']) == 2) {				    
					if($this->mapped_data['CosCenName'][0] != "*") { 
				    	$globalcostcentre_id = $this->getListID("globalcostcentre", ucwords(strtolower("*".$this->mapped_data['CosCenName'])));
				    }
				    else
				    {
				    	$globalcostcentre_id = $this->getListID("globalcostcentre", ucwords(strtolower($this->mapped_data['CosCenName'])));								    
				    }
					$costcentre_id = $this->getListID("facility", ucwords(strtolower($this->costcentrelist["0" . $this->mapped_data['CosCenRefCode']])));
				}
		}
	    echo "CostCentreIDs: " . $costcentre_id . " - " . $globalcostcentre_id . "\n";
	    
	    if(strlen($this->mapped_data['EstbType']) != 0) {
			$EstbType = true;
			if ($this->mapped_data['EstbType'] == "1"){
				$EstbType = false;
			}
		}
	    echo "EstbType: " . $EstbType . "\n";

		if(strlen($this->mapped_data['ProgrammeName']) != 0) {
			$programme_id = $this->getListID("Programme", $this->mapped_data['ProgrammeRefCode'] . " " . ucwords(strtolower($this->mapped_data['ProgrammeName'])));
		}
	    echo "programme_id: " . $programme_id . "\n";
	    	    
		if(strlen($this->mapped_data['SubProName']) != 0) {
			$sub_programme_id = $this->getListID("SubProgramme", $this->mapped_data['SubProRefCode'] . " " . ucwords(strtolower($this->mapped_data['SubProName'])));
		}
	    echo "sub_programme_id: " . $sub_programme_id . "\n";
	    
		if(strlen($this->mapped_data['SubSubName']) != 0) {
			$sub_sub_programme_id = $this->getListID("SubSubProgramme", $this->mapped_data['SubSubRefCode'] . " " . ucwords(strtolower($this->mapped_data['SubSubName'])));
		}
	    echo "sub_sub_programme_id: " . $sub_sub_programme_id . "\n";
	    $section_id = "";
	    $station_id = "";
		if(strlen($this->mapped_data['UnitName']) != 0) {
			$section_id = $this->getListID("Section", $this->mapped_data['UnitRefCode'] . " " . ucwords(strtolower($this->mapped_data['UnitName'])));
		
			if(strlen($this->mapped_data['SectionName']) != 0) {
				$station_id = $this->getListID("station", $this->mapped_data['SectionRefCode'] . " " . ucwords(strtolower($this->mapped_data['SectionName'])));
			}
		}
		else
		{
			if(strlen($this->mapped_data['SectionName']) != 0) {
				$section_id = $this->getListID("Section", $this->mapped_data['SectionRefCode'] . " " . ucwords(strtolower($this->mapped_data['SectionName'])));
			}		
		}
		echo "UnitName: " . $this->mapped_data['UnitName'] . "\n";		
		echo "SectionName: " . $this->mapped_data['SectionName'] . "\n";
	    echo "section_id: " . $section_id . "\n";
	    echo "station_id: " . $station_id . "\n";
	    		
		//Construct the job (position) object
		if (! ($formObj = $this->ff->createContainer('position')) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate job (position) form", E_USER_ERROR);
			die();
		}
		$formObj->getField('code')->setValue($this->mapped_data['JobNo']);
		$formObj->getField('job')->setFromPost('job|' . $position_id);
		$formObj->getField('title')->setValue($position_title);
		$formObj->getField('estab')->setValue($EstbType);
		$parsed_enddate = date_parse( $this->mapped_data['EndDate']);
		if ($parsed_enddate['year'] > 1900) {
			$formObj->getField('end_date')->setValue(I2CE_Date::getDate( $parsed_enddate['day'], $parsed_enddate['month'], $parsed_enddate['year'] ));
		}
		
		if(strlen($this->mapped_data['PostDescription']) != 0) {
			$formObj->getField('description')->setValue(ucwords(strtolower($this->mapped_data['PostDescription'])));
		}
		if(strlen($station_id) != 0) {
			$formObj->getField('station')->setFromPost('station|' . $station_id);
		}

		if(strlen($globalcostcentre_id) != 0) {
			$formObj->getField('globalcostcentre')->setFromPost('globalcostcentre|' . $globalcostcentre_id);
		}
		
		if(strlen($costcentre_id) != 0) {
			$formObj->getField('facility')->setFromPost('facility|' . $costcentre_id);
		}
		
		if(strlen($this->mapped_data['ProgrammeName']) != 0) {
			$formObj->getField('Programme')->setFromPost('Programme|' . $programme_id);
		}
		if(strlen($this->mapped_data['SubSubName']) != 0) {
			$formObj->getField('SubProgramme')->setFromPost('SubProgramme|' . $sub_programme_id);
		}
		if(strlen($this->mapped_data['SubSubName']) != 0) {
			$formObj->getField('SubSubProgramme')->setFromPost('SubSubProgramme|' . $sub_sub_programme_id);
		}
		if(strlen($section_id) != 0) {
			$formObj->getField('Section')->setFromPost('Section|' . $section_id);
		}

		echo "Saving job '" . $this->mapped_data['JobNo'] . "'\n";
		$this->save($formObj);
		$formObj->cleanup();
		return true;

	}

	protected function getListID($listname, $value) {
		$where = array('operator' => 'FIELD_LIMIT', 'field'=>'name', 'style'=>'equals', 'data'=>array('value'=>$value));
		$object_id = I2CE_FormStorage::search($listname, false, $where, array(), true);
		echo "Object name '" . $listname . "'\n";
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
	$file = getcwd() . "/../data/Jobs-Quick.csv";
} else {
	$file = getcwd() . "/../data/Jobs.csv";
}


I2CE::raiseMessage("Loading from $file");

$processor = new JobProcessor($file, $should_force, $should_delete);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

