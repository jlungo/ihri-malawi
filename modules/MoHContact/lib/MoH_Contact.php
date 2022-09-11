<?php
class MoH_Contact extends I2CE_Module {
    public static function getMethods() {
        return array(
            'iHRIS_PageView->action_MoHContact' => 'action_MoHContact'
            
            );
    }
 
 
    public function action_MoHContact($obj) {
        if (!$obj instanceof iHRIS_PageView) {
            return;
        }
        return $obj->addChildForms('MoHContact');
    }
   }
?>
