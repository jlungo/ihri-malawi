<?php
/**
 * © Copyright 2007 IntraHealth International, Inc.
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
 */
/**
 *  iHRIS_PageFormParent
 * @package I2CE
 * @subpackage Core
 * @author Carl Leitner <litlfred@ibiblio.org>
 * @copyright Copyright &copy; 2007 IntraHealth International, Inc. 
 * This file is part of I2CE. I2CE is free software; you can redistribute it and/or modify it under 
 * the terms of the GNU General Public License as published by the Free Software Foundation; either 
 * version 3 of the License, or (at your option) any later version. I2CE is distributed in the hope 
 * that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have 
 * received a copy of the GNU General Public License along with this program. If not, see <http://www.gnu.org/licenses/>.
 * @version 2.1
 * @access public
 */


class I2CE_PageAutoListEdit extends I2CE_PageFormAuto  {

    protected $primaryObject  = null;

    /**
     * Create and load data for the objects used for this form.
     * 
     * Create the list object and if this is a form submission load
     * the data from the form data.  It determines the type based on the
     * {@link $type} member variable.
     */
    protected function loadObjects() {
        if (! ($primaryFormName = $this->getPrimaryFormName())) {
            return false;
        }
        if ( !($primaryObjectClass = $this->factory->getClassName($primaryFormName))) {
            I2CE::raiseError("No object class associated to $primaryFormName");
            return false;
        }
        if ($this->isPost()) {
            $primaryObject = $this->factory->createContainer($primaryFormName);
            if (!$primaryObject instanceof $primaryObjectClass) {
                return false;
            }
            $primaryObject->load($this->post);
        } else {
            if ($this->get_exists('id')) {
                $id = $this->get('id');
                if (strpos($id,'|')=== false) {
                    I2CE::raiseError("Deprecated use of id variable");
                    $id = $primaryFormName . '|' . $id;
                }
            } else {
                $id = $primaryFormName . '|0';
            }
            $primaryObject = $this->factory->createContainer($id);
            if (!$primaryObject instanceof $primaryObjectClass || $primaryObject->getName() != $primaryFormName) {
                I2CE::raiseError("Could not create valid " . $primaryFornName . "form from id:$id");
                return false;
            }
            $primaryObject->populate();
        }

        $listConfig = $this->getListConfig();
        $nosetdefault = false;
        if (array_key_exists('nosetdefault',$listConfig) && $listConfig['nosetdefault']) {
            $nosetdefault = true;
        }
        if ($this->isGet() && !$nosetdefault) {
            $primaryObject->load($this->get());
        }
        $this->primaryObject = $primaryObject;
        $this->setObject($primaryObject, I2CE_PageForm::EDIT_PRIMARY, null, true);
        return true;
    }


    protected function getListConfig($type = null, $form = null) {
        $listConfig = null;
        if ($type === null) {
            if ($this->request_exists('type') && I2CE_MagicDataNode::checkKey($this->request('type'))) {
                $type = $this->request('type');
            }
        }
        if (!$form && $type) {
            $listConfig  = I2CE::getConfig()->getAsArray('/modules/Lists/auto_list/' . $type);
            $listConfig['type'] = $type;
        }
        if (!is_array($listConfig)) {
            $listConfig = array();
        }
        if ($form === null && !array_key_exists('form',$listConfig) && $this->request_exists('form') && I2CE_MagicDataNode::checkKey($this->request('form'))) {
            $form = $this->request('form');
        }
        if ($form) {
            $listConfig['form'] = $form;
            $listConfig['type'] = 0;
            $task = 'can_view_database_list_' . $form;
            if (I2CE_PermissionParser::taskExists($task)) {
                $listConfig['task'] =$task;
            }
            $edit_task = 'can_edit_database_list_' . $form;
            if (I2CE_PermissionParser::taskExists($edit_task)) {
                $listConfig['edit_task'] =$edit_task;
            }
        } 
        if (!$listConfig['form']) {
            return false;
        }
        $form = $listConfig['form'];

        if (!array_key_exists('text',$listConfig) || !$listConfig['text']) {
            $listConfig['text']=I2CE_FormFactory::instance()->getDisplayName($form);
        }
        if (!array_key_exists('title',$listConfig)) {
            $title = "Add/Update %s";
            I2CE::getConfig()->setIfIsSet($title,'/modules/Lists/messages/edit_title');
            $title = @vsprintf($title,$listConfig['text']);
            $listConfig['title'] = $title;
        }
        if ($this->request('remap') && $this->primaryObject instanceof I2CE_Form) {
            if (!array_key_exists('fields',$listConfig) || !is_array($listConfig['fields'])) {
                $listConfig['fields'] = array();
            }
            foreach ( $this->primaryObject->getFieldNames() as $field) {
                if ($field =='remap') {
                    $listConfig['fields']['remap'] = array('enabled'=>1);
                } else {
                    if (!array_key_exists($field,$listConfig['fields']) ||!is_array($listConfig['fields'][$field])) {
                        $listConfig['fields'][$field] = array();
                    }
                    $data = &$listConfig['fields'][$field];

                    if (!array_key_exists('attributes',$data) || !is_array($data['attributes'])) {
                        $data['attributes'] = array();
                    }
                    $data['attributes']['noedit']='strict';
                    unset($data);
                }
            }
            $listConfig['fields']['remap']=array('enabled'=>1);
        } else {
            $listConfig['fields']['remap']=array('enabled'=>0);
        }
        if ( !array_key_exists( 'display_order', $listConfig ) ) {
            if ( $this->getPrimary() instanceof I2CE_Form ) {
                $all_field_names = $this->getPrimary()->getFieldNames();
                $display_first = array_diff( $all_field_names, array( 'remap', 'i2ce_hidden' ) );
                $listConfig['display_order'] = implode( ',', $display_first );
            }
        }
        return $listConfig;
    }

    protected function initializeTemplate() {
        if ( ! ($listConfig = $this->getListConfig())) {
            $title = "Administer Database Lists";
            I2CE::getConfig()->setIfIsSet($title,"/modules/Lists/message/menu_title");
            $this->args['title'] = $title;
        } else {
            $this->args['title'] = $listConfig['title'];
        }
        return parent::initializeTemplate();
    }


    public function __construct( $args,$request_remainder, $get = null,$post = null) {
        parent::__construct( $args,$request_remainder,$get,$post);
        $this->button_templates = array('button_save_return'=>'auto_button_save_return.html');
    }

    protected function getPrimaryFormName() {
        $listConfig = $this->getListConfig();
        if (!$listConfig) {
            return false;
        }
        return $listConfig['form'];
    }


    /**
     * Save the objects to the database.
     * 
     * Save the default object being edited and return to the view page.
     * @global array
     */
    protected function save() {        
        if ($this->primaryObject instanceof I2CE_List 
                && ($hideField = $this->primaryObject->getField('i2ce_hidden')) instanceof I2CE_FormField_YESNO 
                && ($remapField = $this->primaryObject->getField('remap')) instanceof I2CE_FormField_REMAP
                && $remapField->isSetValue()) {
            $hideField->setFromDB(1);
        }
        return parent::save();
    }



    /**
     * Checks to see if there are any permissions in the page's args for the given action.
     * If so, it evaluates them.  If not returns true.
     * @returns boolean
     */
    protected function checkActionPermission($action) {
        if (!($primaryFormName = $this->getPrimaryFormName())) {
            return false; //weirdness.  should just stop whatever is happening
        }
        if (!parent::checkActionPermission($action)) {
            return false;
        }
        $listConfig = $this->getListConfig();
        if (array_key_exists('edit_task',$listConfig) && $listConfig['edit_task']) {
            return $this->hasPermission("task(" .  $listConfig['edit_task'] . ")");
        } else {
            return true;
        }
    }


    protected function getBaseTemplate() {
        return "auto_edit_list.html";
    }

    protected function loadHTMLTemplates() {
        if (! $listConfig = $this->getListConfig()) {
            return false;
        }
        $this->template->setDisplayData('type',$listConfig['type']);
        $this->template->setDisplayData('form',$listConfig['form']);
        $this->template->setDisplayData('button_return',$this->getViewLink());
        if (! ($this->generateAutoTemplate($listConfig,'siteContent'))) {
            return false;
        }

        return true;

    }


    protected function  getViewLink() {
        $listConfig = $this->getListConfig();
        if ($listConfig['type']) {
            $link= 'auto_list?type=' . $listConfig['type'];
        } else {
            $link = 'auto_list?form=' . $listConfig['form'];
        }
        return  $link .  "&id=" . $this->getPrimary()->getNameID();
    }





}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
