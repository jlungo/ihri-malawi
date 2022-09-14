#!/usr/bin/php
<?php

/**
 * Locations importer - imports heirachical location data from ../data/Locations.csv
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

class LocationProcessor extends Processor {

	protected $current_country;
	protected $current_region;
	protected $current_district;
	protected $country_id = 1;
	protected $region_id = 1;
	protected $district_id = 1;
	protected $tradauth_id = 1;

	protected function getExpectedHeaders() {
		return array(
				'country' => 'country',
				'region' => 'region',
				'district' => 'district',
				'traditional_auth' => 'traditional_auth',
		);
	}

	public function __construct($file) {
		parent::__construct($file);
		
		$ids = I2CE_FormStorage::search('county');
		if (sizeof($ids) > 0) {
			$this->tradauth_id = max($ids) + 1;
		}
		foreach ($ids as $id) {
    		$locationObj = $this->ff->createContainer('county|' . $id);
    		$locationObj->populate();
    		echo 'Deleting ' . $locationObj->getField('name')->getValue() . "\n";
    		$locationObj->delete();
		}
		
		$ids = I2CE_FormStorage::search('district');
		if (sizeof($ids) > 0) {
			$this->district_id = max($ids) + 1;
		}
		foreach ($ids as $id) {
			$locationObj = $this->ff->createContainer('district|' . $id);
			$locationObj->populate();
			echo 'Deleting ' . $locationObj->getField('name')->getValue() . "\n";
			$locationObj->delete();
		}
		
		$ids = I2CE_FormStorage::search('region');
		if (sizeof($ids) > 0) {
			$this->region_id = max($ids) + 1;
		}
		foreach ($ids as $id) {
			$locationObj = $this->ff->createContainer('region|' . $id);
			$locationObj->populate();
			echo 'Deleting ' . $locationObj->getField('name')->getValue() . "\n";
			$locationObj->delete();
		}		
	}

	protected function _processRow()       {


		if(strlen($this->mapped_data['country']) != 0) {
			$this->current_country = $this->getListID("country", $this->mapped_data['country']);
		}
		
		if(strlen($this->mapped_data['region']) != 0) {
			if (! ($formObj = $this->ff->createContainer('region')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate region form", E_USER_ERROR);
				die();
			}
				
			$formObj->getField('name')->setValue($this->mapped_data['region']);
			$formObj->getField('country')->setFromPost('country|' . $this->current_country);
			$formObj->setID($this->region_id);
			$this->current_region = $this->region_id;
			$this->region_id++;
			echo "Saving region '" . $this->mapped_data['region'] . "' with ID " . $this->current_region . " and parent ID " . $this->current_country . "\n";
			$this->save($formObj);
		}
		
		if(strlen($this->mapped_data['district']) != 0) {
			if (! ($formObj = $this->ff->createContainer('district')) instanceof I2CE_Form) {
				I2CE::raiseError("Cannot instantiate district form", E_USER_ERROR);
				die();
			}
		
			$formObj->getField('name')->setValue($this->mapped_data['district']);
			$formObj->getField('region')->setFromPost('region|' . $this->current_region);
			$formObj->setID($this->district_id);
			$this->current_district = $this->district_id;
			$this->district_id++;
			echo "Saving district '" . $this->mapped_data['district'] . "' with ID " . $this->current_district . " and parent ID " . $this->current_region . "\n";
			$this->save($formObj);
		}
		
		if (!isset($this->current_country) || !isset($this->current_region) || !isset($this->current_district) || strlen($this->mapped_data['traditional_auth']) == 0) {
			I2CE::raiseError("Missing field, unable to determine specific location on row $this->row", E_USER_ERROR);
		}
		
		if (! ($formObj = $this->ff->createContainer('county')) instanceof I2CE_Form) {
			I2CE::raiseError("Cannot instantiate traditional auth form", E_USER_ERROR);
			die();
		}
		$formObj->getField('name')->setValue($this->mapped_data['traditional_auth']);
		$formObj->getField('district')->setFromPost('district|' . $this->current_district);
		$formObj->setID($this->tradauth_id);
		echo "Saving traditional authority '" . $this->mapped_data['traditional_auth'] . "' with ID " . $this->tradauth_id . " and parent ID " . $this->current_district . "\n";
		$this->tradauth_id++;
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


$file = getcwd() . '/../data/Locations.csv';

I2CE::raiseMessage("Loading from $file");

$processor = new LocationProcessor($file);
$processor->run();
echo "Processing Statistics:\n";
print_r( $processor->getStats());

