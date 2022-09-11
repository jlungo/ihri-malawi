<?php
/**
* © Copyright 2013 IntraHealth International, Inc.
* 
* This File is part of I2CE 
* 
* I2CE is free software; you can redistribute it and/or modify 
* it under the terms of the GNU General Public License as published by 
* the Free Software Foundation; either version 3 of the License, or
* (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful, 
* but WITHOUT ANY WARRANTY; without even the implied warranty of 
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License 
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
* @package I2CE
* @subpackage List
* @author Carl Leitner <litlfred@ibiblio.org>
* @version v4.1.5
* @since v4.1.5
* @filesource 
*/ 
/** 
* Class I2CE_PageRemap
* 
* @access public
*/


class I2CE_PageRemap extends I2CE_Page {
    protected $ff;
    protected function _display($supress_output = false) {
	$this->template->addHeaderLink('mootools-core.js');
	$this->template->addHeaderLink('mootools-more.js');
	parent::_display($supress_output);
        if ( ($errors = I2CE_Dumper::cleanlyEndOutputBuffers())) {
            I2CE::raiseError("Errors:\n" . $errors);
        }
	$this->ff = I2CE_FormFactory::instance();
	if (!$this->request_exists('id') 
	    || !  $formid = $this->request('id') 
	    ) {
	    $this->pushError("Bad list id $id"); //needs to be localized
	    return false;
	}
        $success = true;
        list($form,$id) =array_pad(explode("|",$formid,2),2,'');
        I2CE::raiseError(print_r($this->request(),true));
        if ($id == '*') {
            $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'remap',
                'style'=>'not_null',
                'data'=>array()
                );
            $ids = I2CE_FormStorage::search($form,false,$where);
            I2CE::raiseError("Form $form has remapping data for " . implode(" ", $ids));
            if (count($ids) > 0) {
                foreach (I2CE_List::getFieldsMappingToList($form) as $rform=>$fields) {
                    foreach ($fields as $fieldObj) {
                        $field = $fieldObj->getName();
                        foreach ($ids as $id) {
                            I2CE::raiseError("Checking for remaps on $rform+$field");
                            $success &= $this->doRemap($rform,$field,$form . '|'. $id);
                        }
                    }
                }
            }
            $url = "index.php/auto_list?form=" . $form;
        } else {
            $form = '';
            if (!$this->request_exists('form') || !  ($form = $this->request('form')) || !in_array($form,$this->ff->getForms()) ) {
                $this->pushError("Form $form not found");
                return false;
            }
            $field ='';
            if (!$this->request_exists('field') || !  $field = $this->request('field')) {
                $this->pushError("Bad Field $field");
                return false;
            }
            $success = $this->doRemap($form,$field,$formid);
            $url = "index.php/auto_list?id=$formid&form=" . $form;

        }
	if ($success) {
	    $this->pushContent( "Data was succesully remapped.  Continue on to database lists <a href='$url'>site</a>?");
	} else {
	    $this->pushContent( "Data was <b>not</b> succesully remapped.  Continue on to database lists <a href='$url'>site</a>?");
	}
	return true;
    }

    protected function doRemap($form,$field,$id) {
	$obj = $this->ff->createContainer($id);
	if  (! $obj instanceof I2CE_List) {
	    $this->pushError("ID $id does not refer to a list"); //needs to be localized
	    return false;
	}
	$obj->populate();
	$newform ='0';
	$newid ='0';
	$rField = $obj->getField('remap');
	if ( (!$rField instanceof I2CE_FormField_REMAP) 
	     || !($newform = $rField->getMappedForm()) 
	     || ! ($newid = $rField->getMappedID())) {
	    $this->pushError("No remapping data has been set for $id [$newform|$newid]" .get_class($rField));
	    return false;
	}
	$where = array(
	    'operator'=>'FIELD_LIMIT',
	    'field'=>$field,
	    'style'=>'equals',
	    'data'=>array('value'=>$id)
	    );
	if (( ($count = count($remapIDs =I2CE_FormStorage::search($form,false,$where) ) ))< 1) {
	    $this->pushMessage("No fields found to remap");
	    return true;
	}
	$this->pushCount($count);
	$exec = array('max_execution_time'=>20*60, 'memory_limit'=> (256 * 1048576));	    
	$user = new I2CE_User();
	$success= true;
	foreach  ($remapIDs as $i=>$remapID) {
	    I2CE::longExecution($exec);
	    if ( ! ($remapObj = $this->ff->createContainer($form .'|'.$remapID)) instanceof I2CE_Form) {
		$this->pushMessage("Could not create $form|$remapID",$i+1);
		$success= false;	
		continue;
	    }
	    $remapObj->populate();
	    if (! ($fieldObj= $remapObj->getField($field)) instanceof I2CE_FormField_MAP) {
		$this->pushMessage("Field $field is not a map field",$i+1);
		$success= false;
		$remapObj->cleanup();
		continue;
	    }
	    $fieldObj->setFromDB($newform .'|' . $newid);
	    $this->pushMessage("Remapping $field in  $form|$remapID to be $newform|$newid",$i+1);
	    if (! ($remapObj->save($user))) {
		$success= false;
		$this->pushMessage("Could not save $field in $form|$remapID to be $newform|$newid",$i+1);
	    } else {
		$this->pushMessage("Remapped $field in $form|$remapID to be $newform|$newid",$i+1);
	    }
	    $remapObj->cleanup();

	}
        return $success;
    }
    protected function pushContent($html) {
	$js_message = '<script type="text/javascript">addContent("<div>' . $html .'</div>");</script>';
	echo $js_message;
	flush();

    }

    protected function pushError($message,$i=0) {	 
	I2CE::raiseError($message);
	$this->pushMessage($message,$i);
    }
    
    protected function pushMessage($message,$i=0) {	
        I2CE::raiseMessage($message);
	$js_message = '<script type="text/javascript">addMessage("' .  str_replace("\n",'<br/>',addcslashes($message , '"\\')) . '",' . $i .  ");</script>\n";
	echo $js_message;
	flush();	
    }

    protected function pushCount($count) {
	$js_message = '<script type="text/javascript">setCount(' .$count. ");</script>\n";
	echo $js_message;
	flush();	
    }
    
  }
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
