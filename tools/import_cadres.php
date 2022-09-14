<?php

require_once("./import_base.php");

class CadreProcessor extends Processor {

	protected function getExpectedHeaders() {
		return array(
				'position_title' => 'position_title',
				'hrh_strat' => 'hrh_strat',
				'who' => 'who', 
		);
	}
	
	public function __construct($file) {
		parent::__construct($file);
	}

	protected function _processRow()       {

		$user = new I2CE_User();
		$position_ids = I2CE_FormStorage::search('job');
		
		//Ensure we have all required list data for this row, creating it if we need to
		if(strlen($this->mapped_data['who']) != 0) {
			$whocadre_id = $this->getListID("cadre", $this->mapped_data['who']);
		}
		if(strlen($this->mapped_data['hrh_strat']) != 0) {
			$hrhcadre_id = $this->getListID("hrhcadre", $this->mapped_data['hrh_strat']);
		}

		//Find the position (job) object
		foreach ($position_ids as $id) {
			$positionObj = $this->ff->createContainer('job|' . $id);
			$positionObj->populate();
			if ($positionObj->getField('title')->getValue() == $this->mapped_data['position_title']) {
				$positionObj->getField('cadre')->setFromPost('cadre|'.$whocadre_id);
				$positionObj->getField('hrhcadre')->setFromPost('hrhcadre|'.$hrhcadre_id);
				I2CE::raiseMessage("Updating position: " . $positionObj->getField('id')->getValue() . "\n");
				$positionObj->save($user);
			}
		}

	}

	protected function getListID($listname, $value) {
		$where = array('operator' => 'FIELD_LIMIT', 'field'=>'name', 'style'=>'equals', 'data'=>array('value'=>$value));
		$object_id = I2CE_FormStorage::search($listname, false, $where, array(), true);

		if (!$object_id) {
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

$file = getcwd() . "/../data/Cadres.csv";

I2CE::raiseMessage("Loading from $file");

$processor = new CadreProcessor($file);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

