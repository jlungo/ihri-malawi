<?php

require_once("./import_base.php");

class PositionProcessor extends Processor {

	protected $should_force;

	protected function getExpectedHeaders() {
		return array(
				'code' => 'esPostCode',
				'title' => 'esPostDescription',
				'grade' => 'esGradeCode', 
				'cadre' => 'cadre', //List
		);
	}

	public function __construct($file, $should_force, $should_delete) {
		parent::__construct($file);
		$this->should_force = $should_force;

		if ($should_delete) {

			echo "Removing existing position data\n";

			$ids = I2CE_FormStorage::search('job');
			echo "Deleting " . sizeof($ids) . " position records\n";
			foreach ($ids as $id) {
				$obj = $this->ff->createContainer('job|' . $id);
				$obj->populate();
				$obj->delete();
			}

		}
	}

	protected function _processRow()       {

		//Ensure we have all required list data for this row, creating it if we need to and if thats allowed
		if(strlen($this->mapped_data['grade']) != 0) {
			$grade_id = $this->getListID("Grade", $this->mapped_data['grade']);
		}

		if(strlen($this->mapped_data['cadre']) != 0) {
			$cadre_id = $this->getListID("cadre", $this->mapped_data['cadre']);
		}

		//Construct the position (job) object
		if (! ($formObj = $this->ff->createContainer('job')) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate position (job) form", E_USER_ERROR);
			die();
		}
		$formObj->getField('code')->setValue($this->mapped_data['code']);
		$formObj->getField('title')->setValue($this->mapped_data['title']);
		$formObj->getField('Grade')->setFromPost('Grade|' . $grade_id);
		//$formObj->getField('cadre')->setFromPost('cadre|' . $cadre_id); Don't have cadre yet

		echo "Saving position '" . $this->mapped_data['code'] . "'\n";
		$this->save($formObj);
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
	$file = getcwd() . "/../data/Positions-Quick.csv";
} else {
	$file = getcwd() . "/../data/Positions.csv";
}

I2CE::raiseMessage("Loading from $file");

$processor = new PositionProcessor($file, $should_force, $should_delete);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

