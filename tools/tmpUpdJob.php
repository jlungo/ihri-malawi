<?php

require_once("./import_base.php");

class PeopleProcessor extends Processor {

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
	}

	protected function _processRow()       {
	
				
		if(strlen($this->mapped_data['EstbType']) != 0) {
			$EstbType = true;
			if ($this->mapped_data['EstbType'] == "1"){
				$EstbType = false;
			}
		}
	    echo "EstbType: " . $EstbType . "\n";
	    
	    // Need this for position object 
		if(strlen($this->mapped_data['JobNo']) != 0) {																						
				//Find the right job 		
				$where = array(
	            'operator'=>'FIELD_LIMIT',
	            'field'=>'code',
	            'style'=>'equals',
	            'data'=> array('value'=>$this->mapped_data['JobNo'])
	            );	
	            
	            $id = I2CE_FormStorage::search("position",false,$where,array(),true);
			
				echo "\n" . $id . "\n";
				
				if (! ($formObj = $this->ff->createContainer('position|' . $id)) instanceof I2CE_Form) {
					I2CE::raiseError("Cannot instantiate position form", E_USER_ERROR);
					die();
				}
				$formObj->populate();
				if($formObj->getField('code')->getValue() == $this->mapped_data['JobNo']) {				
					$formObj->getField('estab')->setValue($EstbType);	
					echo "Saving current job info " . $formObj->getField('code')->getValue() . " " . $formObj->getField('title')->getValue() . " " . $formObj->getField('estab')->getValue() . "\n";
					$this->save($formObj);
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

$file = getcwd() . "/../data/" . $argv[1];

print_r($file);

I2CE::raiseMessage("Loading from $file");

$processor = new PeopleProcessor($file, $should_force, $should_delete);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

