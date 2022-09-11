<?php
class iHRIS_Leave extends I2CE_Module {
    public static function getMethods() {
        return array(
            'iHRIS_PageView->action_PersonLeave' => 'action_PersonLeave'
            
            );
    }
 
 
    public function action_PersonLeave($obj) {
        if (!$obj instanceof iHRIS_PageView) {
            return;
        }
        return $obj->addChildForms('PersonLeave');
    }
   }
?>
