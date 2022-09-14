<?php
class MoH_Education extends I2CE_Module {
    public static function getMethods() {
        return array(
            'iHRIS_PageView->action_MoHEducation' => 'action_MoHEducation'
            
            );
    }
 
 
    public function action_MoHEducation($obj) {
        if (!$obj instanceof iHRIS_PageView) {
            return;
        }
        return $obj->addChildForms('MoHEducation');
    }
   }
?>
