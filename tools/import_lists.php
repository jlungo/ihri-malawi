#!/usr/bin/php
<?php

/**
 * List importer - imports any standard refdata list
 *
 * @author Oliver Dennis <ihrismalawi@gmail.com>
 * @version 1.0
 */


require_once("./import_base.php");



/*********************************************
 *
*      Process Class
*
*********************************************/

class ListProcessor extends Processor {

	protected $list_name;
	protected $next_id = 1;

	protected function getExpectedHeaders() {
		return array(
				'name'=>'name',
		);
	}

	public function __construct($file) {
		parent::__construct($file);
		$this->list_name = basename($file, '.csv');
		
		$ids = I2CE_FormStorage::search($this->list_name);
		if (sizeof($ids) > 0) {
			$this->next_id = max($ids) + 1;
		}
		foreach ($ids as $id) {
			$listObj = $this->ff->createContainer($this->list_name . '|' . $id);
			$listObj->populate();
			echo 'Deleting ' . $listObj->getField('name')->getValue() . "\n";
			$listObj->delete();
		}
	}

	protected function _processRow()       {

		if (! ($formObj = $this->ff->createContainer($this->list_name)) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate form $this->list_name", E_USER_ERROR);
			die();
		}

		$fieldheadermap = array('name'=>'name');
		foreach ($fieldheadermap as $field=>$header) {
			if ( !array_key_exists($header,$this->mapped_data) || !is_string($this->mapped_data[$header]) || strlen($this->mapped_data[$header])==0) {
				continue;
			}
			$formObj->getField($field)->setValue($this->mapped_data[$header]);
		}
		//$formObj->getField('primary')->setValue(strtoupper($formObj->getField('primary')->getValue()) == 1 ? 1 : 0); //make sure the primary field is set
		$formObj->setID($this->next_id);
		$this->next_id++;
		$this->save($formObj);
		return true;
	}



}




/*********************************************
 *
*      Execute!
*
*********************************************/



$basedir = getcwd() . '/../data/lists/';
$files = scandir($basedir);

foreach ($files as $file) {
	if (endsWith($file, '.csv')) {
		I2CE::raiseMessage("Loading from $file");

		$processor = new ListProcessor($basedir . $file);
		$processor->run();
		echo "Processing Statistics:\n";
		print_r( $processor->getStats());
	}
}


function endsWith( $str, $sub ) {
	return ( substr( $str, strlen( $str ) - strlen( $sub ) ) == $sub );
}
