<?php

require_once("./import_base.php");

class EstabProcessor extends Processor {
		
	protected function getExpectedHeaders() {
		return array(		
				'costcentre' => 'costcentre',
				'position' => 'position',
				'grade' => 'grade',
				'headcount' => 'headcount',

		);
	}

	public function __construct($file, $should_delete) {
		parent::__construct($file);

		if ($should_delete) {

			echo "Removing existing establishment plan data\n";

			$ids = I2CE_FormStorage::search('EstablishmentPlan');
			echo "Deleting " . sizeof($ids) . " establishment plan records\n";
			foreach ($ids as $id) {
				$obj = $this->ff->createContainer('EstablishmentPlan|' . $id);
				$obj->populate();
				$obj->delete();
			}

		}
	}

	protected function _processRow()       {
		
		if(strlen($this->mapped_data['grade']) != 0) {
			$grade_id = $this->getListID("Grade", $this->mapped_data['grade']);
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
            						'field'=>'title',
            						'style'=>'equals',
            						'data'=> array('value'=>$this->mapped_data['position'])
                        		)
                    	)
        			);
        			
        echo "Position Title: " . $this->mapped_data['position'] . " - grade:" . $this->mapped_data['grade'] . "\n";			
        $position_id = I2CE_FormStorage::search("job", false, $where, array(), true);
		if (!$position_id) {
			echo "Cannot import establishment plan, the position '" . "Title: " . $this->mapped_data['position'] . " - grade:" . $this->mapped_data['grade'] . "' does not exist";
			return false;
		}
		$position_obj = $this->ff->createContainer('job|' . $position_id);
		$position_obj->populate();
		
	
		if(strlen($this->mapped_data['costcentre']) != 0) {
				$costcentre_id = $this->getListID("facility", $this->mapped_data['costcentre']);
		}
	    		
		//Construct the estab plan object
		if (! ($formObj = $this->ff->createContainer('EstablishmentPlan')) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate establishment plan form", E_USER_ERROR);
			die();
		}
		$formObj->getField('headcount')->setValue($this->mapped_data['headcount']);
		$formObj->getField('position')->setFromPost('job|' . $position_id);
		$formObj->getField('costcentre')->setFromPost('facility|' . $costcentre_id);

		echo "Saving establishment plan for '" . $this->mapped_data['costcentre'] . ", " . $this->mapped_data['position'] . "'\n";
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
$file = getcwd() . "/../data/EstablishmentPlan.csv";


I2CE::raiseMessage("Loading from $file");

$processor = new EstabProcessor($file, $should_delete);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

