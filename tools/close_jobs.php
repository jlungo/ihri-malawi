<?php

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
    	     	 
$ids = I2CE_FormStorage::search('person_position');
foreach ($ids as $id) {
    $personPositionObj = $ff->createContainer('person_position|' . $id);
    $personPositionObj->populate();
    if ($personPositionObj->getField('position')->getValue() != null) {
    	$positionObj = $ff->createContainer($personPositionObj->getField('position')->getValue());
    	$positionObj->populate();
    	if ($positionObj->getField('status')->getValue()[1] != "closed") {
    		$positionObj->getField('status')->setFromPost('position_status|closed');
    		I2CE::raiseMessage("Closing position: " . $positionObj->getField('id')->getValue() . "\n");
    		$positionObj->save($user);
    	} else {
    		I2CE::raiseMessage("Skipping already closed position: " . $positionObj->getField('id')->getValue() . "\n");
    	}
    }
}


