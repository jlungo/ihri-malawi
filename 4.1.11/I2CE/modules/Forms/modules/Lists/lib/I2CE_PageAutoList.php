<?php
/**
 * @copyright © 2007, 2008, 2009 Intrahealth International, Inc.
 * This File is part of I2CE
 *
 * I2CE is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
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
 * View the details for then given record that is an instance of a I2CE_List.
 * @package I2CE
 * @subpackage Common
 * @access public
 * @author Carl Leitner
 * @since v2.0.0
 * @version v2.0.0
 */

/**
 * The page class for displaying a I2CE_List record.
 * @package I2CE
 * @subpackage Common
 * @access public
 */

class I2CE_PageAutoList extends I2CE_Page {


    protected function getPrimaryFormName() {
        $form = false;
        if ($this->request_exists('type') && I2CE::getConfig()->setIfIsSet($form,"/modules/Lists/auto_list/" . $this->request('type') . '/form') && $form) {
            return $form;
        } else if ($this->request_exists('form') && is_scalar($form = $this->request('form'))&& $form) {
            return $form;
        } else {
            return false;
        }
    }
    protected $prim_form  = false;
    protected function getPrimary() {
        if ($this->prim_form === false) {
            $ff = I2CE_FormFactory::instance();
            $this->prim_form= $ff->createContainer($this->getPrimaryFormName());
        }
        return $this->prim_form;
    }

    protected function loadHTMLTemplates() {
        if ( ! $this->getPrimaryFormName()) {
            $this->template->appendFileById("auto_list.html", 'div', 'siteContent' );
        }
        return true;
    }


    protected function action() {
        if ( !( $listConfig = $this->getListConfig())) {
            return $this->action_menu();
        } else if ($this->request_exists('id') && $id = $this->request('id')) {
            return $this->action_view_form($id);
        } else  if (array_key_exists('field',$listConfig) && $listConfig['field']) {
            return $this->action_select_field($listConfig);
        } else  {
            return $this->action_select_list($listConfig);
        }
    }

    /**
     * Handles creating hte I2CE_TemplateMeister templates and loading any default templates
     * @returns boolean true on success
     */
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


    protected function setDisplayData() {
        parent::setDisplayData();
        $listConfig = $this->getListConfig();
        if ( ($primary = $this->getPrimary()) instanceof I2CE_List) {
            $this->template->setDisplayData( "type_name", $primary->getDisplayName() );
            $this->template->setDisplayData( "form", $primary->getName() );
            $this->template->setDisplayData( "id", $primary->getNameId() );
        }
        if (!$listConfig['type']){
            $this->template->setDisplayData( "form", $this->request('form'));
        } else {
            $this->template->setDisplayData( "type", $this->request('type'));
        }
        $this->template->setDisplayData( "link", $this->request("link") );
        if (I2CE_FormStorage::isWritable($this->getPrimaryFormName())) {
            $this->template->setDisplayData( "list_is_writable", 1);
        } else {
            $this->template->setDisplayData( "list_is_writable", 0);
        }
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
        if (!array_key_exists('form',$listConfig) || !$listConfig['form']) {
            return false;
        }
        $form = $listConfig['form'];

        if (!array_key_exists('text',$listConfig) || !$listConfig['text']) {
            $listConfig['text']=I2CE_FormFactory::instance()->getDisplayName($form);
        }
        if (!array_key_exists('edit_links',$listConfig) || !is_array($listConfig['edit_links'])) {
            $listConfig['edit_links'] = array();
        }
        if (!array_key_exists('edit',$listConfig['edit_links'])) {
            $text = 'Edit this information';
            I2CE::getConfig()->setIfIsSet($text,"/modules/Lists/messages/edit_text");
            if ($type) {
                $href = "auto_list_edit?type=$type&id=";
            } else {
                $href = "auto_list_edit?form=$form&id=";
            }
            $data =  array(
                    'href'=>$href,
                    'formfield'=>"$form:id",
                    'text'=>$text
                    );
            if (array_key_exists('edit_task',$listConfig)) {
                $data['task'] = $listConfig['edit_task'];
            }
            $listConfig['edit_links']['edit'] = $data;
        }
        if (!array_key_exists('select',$listConfig['edit_links'])) {
            if ($type) {
                $href = "auto_list?type=$type";
            } else {
                $href = "auto_list?form=$form";
            }
            $text = 'Select another %';
            I2CE::getConfig()->setIfIsSet($text,"/modules/Lists/messages/select_text");
            $text = @vsprintf($text,$listConfig['text']);
            $data =  array(
                    'href'=>$href,
                    'formfield'=>false,
                    'text'=>$text
                    );
            if (array_key_exists('task',$listConfig)) {
                $data['task'] = $listConfig['task'];
            }
            $listConfig['edit_links']['select'] = $data;
        }
        if (!array_key_exists('new',$listConfig['edit_links'])) {
            if ($type) {
                $href = "auto_list_edit?type=$type";
            } else {
                $href = "auto_list_edit?form=$form";
            }
            $text = 'Add new %s';
            I2CE::getConfig()->setIfIsSet($text,"/modules/Lists/messages/new_text");
            $text = @vsprintf($text,$listConfig['text']);
            $data =  array(
                    'href'=>$href,
                    'formfield'=>false,
                    'text'=>$text
                    );
            if (array_key_exists('task',$listConfig)) {
                $data['task'] = $listConfig['task'];
            }
            $listConfig['edit_links']['new'] = $data;
        }

        if ($this->user->getRole()=='admin') {
            $href = "auto_list_edit?remap=1&form=" .$form ."&id=";
            $text = 'Set remapping data';
            I2CE::getConfig()->setIfIsSet($text,"/modules/Lists/messages/remap_text");
            $data =  array(
                    'href'=>$href,
                    'formfield'=>$form. ':id',
                    'text'=>$text
                    );
            $listConfig['edit_links']['set_remap'] = $data;
        }
        if ( !I2CE_FormStorage::isWritable( $form ) ) {
            unset( $listConfig['edit_links']['edit'] );
            unset( $listConfig['edit_links']['set_remap'] );
        }
        if (!array_key_exists('title',$listConfig)) {
            $title = "View %s";
            I2CE::getConfig()->setIfIsSet($title,'/modules/Lists/messages/title');
            $title = @vsprintf($title,$listConfig['text']);
            $listConfig['title'] = $title;
        }
        if (!array_key_exists('fields',$listConfig) || !is_array($listConfig['fields'])) {
            $listConfig['fields']= array();
        }
        if ($this->request('remap') && $this->request('remap')) {
            $listConfig['default_disabled'] =1;
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

    function setRemapLinks(&$listConfig,$obj) {
        if (!is_array($listConfig) || !array_key_exists('edit_links',$listConfig) || !is_array($listConfig['edit_links']) 
                || !$obj instanceof I2CE_List || !($remapFieldObj=$obj->getField('remap')) instanceof I2CE_FormField_MAP 
                || !($remapID = $remapFieldObj->getMappedID()) || !($remapForm = $remapFieldObj->getMappedForm())
                || ($this->user->getRole() != 'admin') 
           ) {
            return;
        }
        $ff = I2CE_FormFactory::instance();
        $sourceForm = $obj->getName();
        $sourceId = $obj->getId();       
        foreach ($ff->getForms() as $form) {
            if (! ($sObj = $ff->createContainer($form)) instanceof I2CE_Form) {
                continue;
            }
            foreach ($sObj->getFieldNames() as $sfield) {
                if (! ($sFieldObj = $sObj->getField($sfield)) instanceof I2CE_FormField_MAP
                        || ! in_array($sourceForm,$sFieldObj->getSelectableForms())
                   ) {
                    continue;
                }
                $where = array(
                        'operator'=>'FIELD_LIMIT',
                        'field'=>$sfield,
                        'style'=>'equals',
                        'data'=>array('value'=>$sourceForm.'|'.$sourceId)
                        );
                if (($count = count(I2CE_FormStorage::search($form,false,$where) ) )< 1) {
                    continue;
                }
                $href = "auto_list_remap?form=$form&field=$sfield&id=";
                $text = 'Remap field %1$s in %2%s (%3$s matches)';
                I2CE::getConfig()->setIfIsSet($text,"/modules/Lists/messages/remap_field_text");
                $text = @sprintf($text,$form,$sfield,(string) $count);
                $data =  array(
                        'href'=>$href,
                        'formfield'=>$sourceForm . ':id',
                        'text'=>$text,
                        'attributes'=>array('onclick'=>"if (confirm('Are you sure?')) {return true;} else {return false;}" ) //needs to be localized

                        );
                $listConfig['edit_links']['remap_form_'  . $form . '_field_' . $sfield] = $data;	    
            }
        }
    }


    protected $primaryObject;
    protected function action_view_form($id) {
        $listConfig = $this->getListConfig();
        if (! ($this->primaryObject = $this->loadPrimaryObject($id,$listConfig)) instanceof I2CE_List) {
            if ($this->request_exists('type') && I2CE::getConfig()->setIfIsSet($form,"/modules/Lists/auto_list/" . $this->request('type') . '/form') && $form) {
                $append = '?type=' . $this->request('type');
            } else if ($this->request_exists('form') && is_scalar($form = $this->request('form'))&& $form) {
                $append = '?form=' . $this->request('form');
            } else {
                $append = '';
            }
            $this->setRedirect("auto_list" . $append);
            return false;
        }

        $this->setRemapLinks($listConfig,$this->primaryObject);
        if( ! ($node = $this->template->appendFileById("auto_view_list.html", 'div',  'siteContent')) instanceof DOMNode) {
            I2CE::raiseError("Could not load auto_view_list.html");
            return false;
        }
        return ( $this->generateAutoTemplate($listConfig,$this->primaryObject,$node,true) instanceof DOMNode);
    }

    protected function loadPrimaryObject($id,$listConfig) {
        if (!array_key_exists('form',$listConfig)) {
            return false;
        }
        $obj = I2CE_FormFactory::instance()->createContainer($id);
        if (!$obj instanceof I2CE_List || $obj->getName() != $listConfig['form'] ) {
            return false;
        }
        $ff = I2CE_FormFactory::instance();
        if (!$ff->hasRecord($obj->getName(),$obj->getID())) {
            $obj->cleanup();
            return false;
        }
        $obj->populate();
        return $obj;

    }

    protected function generateAutoTemplate($data,$obj, $node,$show_mapped = true) {	
        $this->setForm($obj);
        $form = $obj->getName() ;	
        if (array_key_exists('task',$data) && $data['task']) {
            $task = $data['task'];
            if (I2CE_PermissionParser::taskExists($task) && !$this->hasPermission("task($task)",$node)) {
                return false;
            }     
        }
        if ( ($ulNode= $this->template->getElementByName('form_edit_links',0,$node)) instanceof DOMNode 
                && array_key_exists('edit_links',$data) && is_array($data['edit_links'])
           ) {           
            $this->addLinks('li',$data['edit_links'],$ulNode);
        }
        if ( ($pNode= $this->template->getElementByName('form_action_links',0,$node)) instanceof DOMNode ) {
            $added =0;
            if ( array_key_exists('action_links',$data) && is_array($data['action_links'])) {
                $added = $this->addLinks('span',$data['action_links'],$pNode);
            }
            if($added == 0) {
                $this->template->removeNode($pNode);
            }
        }
        if ( ($tbodyNode= $this->template->getElementByName('form_fields',0,$node)) instanceof DOMNode ) {
            $field_tasks = array();
            $all_field_names = $obj->getFieldNames();
            $display_order = array();
            if (array_key_exists('display_order',$data) && is_string($data['display_order'])) {
                $display_order = explode(",",$data['display_order']);
            }
            $field_names = array();
            foreach($display_order as $field_name) {
                if ( ($pos = array_search($field_name,$all_field_names)) === false) {
                    continue;
                }
                unset($display_order[$pos]);
                $field_names[] = $field_name;
            }
            $field_data = array();
            if (array_key_exists('fields',$data) && is_array($data['fields'])) {
                $field_data= $data['fields'];
            }
            $listed_fields = array_keys($field_data);
            $field_names = array_unique(array_merge($field_names,$all_field_names,$listed_fields));
            $default_enabled = 1;
            if (array_key_exists('default_disabled',$data)) {
                $default_enabled = !$data['default_disabled'];
            }
            foreach ($field_names as $field_name) {
                if (!array_key_exists($field_name,$field_data) || !is_array($field_data[$field_name])) {
                    $f_data = array();
                } else {
                    $f_data = $field_data[$field_name];
                }
                if (array_key_exists('enabled' , $f_data)) {
                    if (!$f_data['enabled']) { 
                        continue;
                    }
                } else {
                    if (!$default_enabled) {
                        continue;
                    }
                }
                if (array_key_exists('attributes',$f_data) && is_array($f_data['attributes'])) {
                    $attrs = $f_data['attributes'];
                }
                $attrs['type']='form';
                if (array_key_exists('is_method',$f_data) && $f_data['is_method']) {
                    $attrs['name']=$form. '->' . $field_name .'()';
                    $tbodyNode->appendChild($trNode  = $this->template->createElement('tr',array('colspan'=>2)));
                    $trNode->appendChild($tdNode  = $this->template->createElement('td',array('colspan'=>2)));
                    $tdNode->appendChild($this->template->createElement('span',$attrs));
                } else {
                    $attrs['name']=$form. ':' . $field_name;
                    if (!array_key_exists('showhead',$attrs)) {
                        $attrs['showhead'] = 'default';
                    }
                    if (!$attrs['showhead']) {
                        unset($attrs['showhead']);
                    }
                    $attrs['auto_link'] = 1;
                    $tbodyNode->appendChild($this->template->createElement('span',$attrs));
                }
            }
        }
        if (array_key_exists('display_name',$data) && $data['display_name']) {
            $display_name = $data['display_name'];
        } else {
            $display_name =  $this->primaryObject->getDisplayName();
        }
        $this->template->setDisplayDataImmediate('form_display_name',$display_name,$node);

        if (array_key_exists('title',$data) && $data['title']) {
            $title = $data['title'];
        } else {
            $title = $this->getTitle();
        }
        if (array_key_exists('subtitle',$data) && $data['subtitle']) {
            $subtitle = $data['subtitle'];
        } else {
            $subtitle =  $this->primaryObject->getDisplayName();
        }

        $this->template->setDisplayDataImmediate('form_title',$title,$node);
        $this->template->setDisplayDataImmediate('form_subtitle',$subtitle,$node);
        if (array_key_exists('mapped',$data) && is_array($data['mapped']) && ($mapped_node = $this->template->getElementById('mapped_forms')) instanceof DOMNode) {
            $this->template->addHeaderLink('view.js');
            foreach ($data['mapped'] as $list => $list_data) {
                $mapped_node->appendChild($divNode = $this->template->createElement('div'));
                $this->show_mapped($obj,$list,$list_data,$divNode);
            }
        }
        if (array_key_exists('linked',$data) && is_array($data['linked']) && ($linked_node = $this->template->getElementById('linked_forms')) instanceof DOMNode) {
            foreach ($data['linked'] as  $link_data) {
                $linked_node->appendChild($divNode = $this->template->createElement('div'));
                $this->show_linked($obj,$link_data,$divNode);
            }

        }
        return $node;
    }

    protected function show_mapped($obj,$list,$data,$node) {
        $task = 'can_view_database_list_' . $list;
        if (!array_key_exists('task',$data) && I2CE_PermissionParser::taskExists($task) ) {
            $data['task']= $task;
        }
        if ($task && I2CE_PermissionParser::taskExists($task) && !$this->hasPermission("task($task)",$node)) {
            return;
        }     
        $ff = I2CE_FormFactory::instance();
        $formClass = $ff->getClassName($list);
        if (!I2CE_List::isList($list)) {
            return;
        }
        if (!is_array($data)) {
            $data = array();
        }
        if (!array_key_exists('title',$data)) {
            $data['title'] = $ff->getDisplayName($list);
        }
        if (array_key_exists('mapped_field',$data) && $data['mapped_field']) {
            $mapped_field= $data['mapped_field'];
        } else {
            $mapped_field = $obj->getName();
        }
        if (array_key_exists('orders',$data)) {
            $orders = $data['orders'];
        } else {
            $orders = I2CE_List::getSortFields($list);
        }
        $limit = false;
        if (array_key_exists('limit',$data)) {
            $limit = $data['limit'];
        }

        $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>$mapped_field,
                'style'=>'equals',
                'data'=>array('value'=>$obj->getNameId()));

        if (array_key_exists('where',$data) && is_array($data['where'])) {
            $where = array(
                    'operator'=>'AND',
                    'operand'=>array(0=>$data['where'], 1=>$where)
                    );
        }
        $map_ids = I2CE_FormStorage::search($list, false,$where,$orders,$limit);
        foreach ($map_ids as $map_id) {
            $mapObj =$ff->createContainer($list .'|'.$map_id);
            if (!$mapObj instanceof I2CE_Form) {
                continue;
            }                                  
            $mapObj->populate();
            $node->appendChild($divNode = $this->template->createElement('div'));
            $this->template->appendFileByNode('auto_view_list.html','div',$divNode);
            if (! ($this->generateAutoTemplate($data,$mapObj,$divNode,false)) instanceof DOMNode) {
                continue;
            }
            $this->setForm( $mapObj, $divNode);
        }
    }





    protected function show_linked($obj,$data,$node) {
        $task = false;
        if (!array_key_exists('task',$data) && I2CE_PermissionParser::taskExists($task) ) {
            $data['task']= $task;
        }
        if ($task && I2CE_PermissionParser::taskExists($task) && !$this->hasPermission("task($task)",$containerNode)) {
            return;
        }     	
        $ff = I2CE_FormFactory::instance();
        if (!is_array($data) || !array_key_exists('form',$data) || ! ($form = $data['form']) || ! ($formObj = $ff->createContainer($form)) instanceof I2CE_Form) {
            return false;
        }
        $printf = false;
        $printf_args = array();
        $orders = null;
        $link = false;
        $mapped_field = $obj->getName();
        if ($formObj instanceof I2CE_List) {
            $style = 'default';
            if (array_key_exists('style',$data) && is_scalar($data['style']) && $data['style']) {
                $style = $data['style'];
            }
            $printf_args = I2CE_List::getDisplayFields($form,$style);
            $printf = I2CE_List::getDisplayString($form,$style);
            $orders = I2CE_List::getSortFields($form);	    
            $link  = "auto_list?form=$form&id=";
        }
        if (array_key_exists('link',$data) && is_scalar($data['link']) && $data['link']) {
            $link = $data['link'];
        }
        if (array_key_exists('field',$data) && is_scalar($data['field']) && $data['field']) {
            $mapped_field = $data['field'];
        }

        if (array_key_exists('orders',$data)) {
            $orders = $data['orders'];
        }
        if (array_key_exists('printf',$data) && is_scalar($data['printf']) && $data['printf']) {
            $printf = $data['printf'];
        }
        if (array_key_exists('printf_args',$data) && is_array($data['printf_args']) && count($data['printf_args'])>0)  {
            $printf_args = $data['printf_args'];
        }
        $link_field = array();
        if (array_key_exists('link_field',$data) && is_scalar($data['link_field']) && $data['link_field']) {
            $link_field = explode(':',$data['link_field']);
        }

        if (!$printf || count($printf_args) == 0 || !$link) {
            return;
        }
        $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>$mapped_field,
                'style'=>'equals',
                'data'=>array('value'=>$obj->getNameId()));
        if (array_key_exists('where',$data) && is_array($data['where'])) {
            $where = array(
                    'operator'=>'AND',
                    'operand'=>array(0=>$data['where'], 1=>$where)
                    );
        }
        $limit = false;
        if (array_key_exists('limit',$data)) {
            $limit = $data['limit'];
        }
        $title = $formObj->getDisplayName();
        if (array_key_exists('title',$data) && is_scalar($data['title']) && $data['title']) {
            $title = $data['title'];
        }
        $arg_walker =array();
        foreach ($printf_args as $i=>&$arg) {
            $t_arg = explode(":",$arg);
            if (count($t_arg) > 1) {
                $arg_walker[$i] = $t_arg;
                $arg = $t_arg[0];
            }
        }
        unset($arg);
        if ( count ($dispDatas  = I2CE_FormStorage::listDisplayFields($form,$printf_args,false,$where,$orders,$limit)) == 0) {
            return false;
        }
        $linkedNode = $this->template->appendFileByNode('auto_view_linked.html','div',$node);	
        if (($pageDispNode = $this->template->getElementByName('pager_display',0,$linkedNode))instanceof DOMNode
                &&(($pageResultsNode = $this->template->getElementByName('pager_results',0,$linkedNode))instanceof DOMNode)
           ) {
            $pageDispNode->setAttribute('id','linked_' . $form . '_pager_display');
            $pageResultsNode->setAttribute('id','linked_' . $form . '_results');
            if (!  ($dispDatas = $this->paginateList($dispDatas,'linked_' . $form))) {
                return false;
            }
        }
        $this->template->setDisplayDataImmediate('link_title',$title);
        if (! ($tbodyNode = $this->template->getElementByName('link_fields',0,$node)) instanceof DOMNode) {
            return false;
        }
        foreach ($dispDatas as $id=>$r_dispData) {
            $dispData = array();
            foreach($printf_args as $i=>$arg) {
                $dispData[$i] = $r_dispData[$arg];
            }
            foreach ($arg_walker as $i=>$fields) {
                $val = $dispData[$i];
                $count = 0;
                foreach ($fields as $field) {
                    $count++;
                    if ($count == 1) {
                        continue;
                    }
                    list($wform,$wid) =array_pad(explode('|',$val,2),2,'');
                    $val = I2CE_FormStorage::lookupField($wform,$wid,array($field),'');
                }
                $dispData[$i] = $val;
            }
            $text = @vsprintf($printf,$dispData);
            //form = person_position 
            $linkid = $form . '|' . $id ;
            foreach ($link_field as $lfield) {
                list($lform,$lid) =array_pad(explode('|',$linkid,2),2,'');
                $linkid = I2CE_FormStorage::lookupField($lform,$lid,array($lfield),'');
            }
            $attrs = array(
                    'href'=> $link . $linkid
                    );
            $tbodyNode->appendChild($trNode  = $this->template->createElement('tr',array('colspan'=>2)));
            $trNode->appendChild($tdNode  = $this->template->createElement('td',array('colspan'=>2)));
            $tdNode->appendChild($this->template->createElement('a',$attrs,$text));
        }
    }




    protected function addLinks($tag,$data,$containerNode) {
        $added =0;
        foreach ($data as $link_data) {
            if (array_key_exists('task',$link_data) && $link_data['task']) {
                $task = $link_data['task'];
                if (I2CE_PermissionParser::taskExists($task) && !$this->hasPermission("task($task)",$containerNode)) {
                    continue;
                }     
            }
            if (!array_key_exists('href',$link_data) || !$link_data['href']
                    ||!array_key_exists('formfield',$link_data)
                    || !array_key_exists('text',$link_data) || !$link_data['text']
               ) {
                continue;
            }
            if ($link_data['formfield']) {
                $attrs = array(
                        'type'=>'form',
                        'name'=>$link_data['formfield'],
                        'href'=>$link_data['href']
                        );
                if (array_key_exists('attributes',$link_data) && is_array($link_data['attributes'])) {
                    foreach ($link_data['attributes'] as $attr=>$val) {
                        if (!is_scalar($val)) {
                            continue;
                        }
                        $attrs[$attr] = $val;
                    }
                }
                $tagNode = $this->template->createElement($tag);
                $tagNode->appendChild($this->template->createElement('span',$attrs,$link_data['text']));
                $containerNode->appendChild($tagNode);
            } else {
                $attrs = array('href'=>$link_data['href']);
                $tagNode = $this->template->createElement($tag);
                $tagNode->appendChild($this->template->createElement('a',$attrs,$link_data['text']));
                $containerNode->appendChild($tagNode);
            }
            $added++;
        }
        return $added; 
    }



    protected $select_field =false;
    protected function getSelectField($listConfig) {
        if ($this->select_field ===false) {
            if (! ($obj = $this->getPrimary()) instanceof I2CE_List) {
                I2CE::raiseError("primary not list");
                return false;
            }
            $select_field_name =false;
            if (!array_key_exists('field',$listConfig) || !$listConfig['field']) {
                return false;
            }
            $this->select_field = $obj->getField($listConfig['field']);
            $obj->load($this->request(),false,false);
            if (!$this->select_field instanceof I2CE_FormField_MAP) {
                $this->select_field =null;
                return false;
            }
            if ( $this->select_field->issetValue() ) {
                $selectObj = $this->select_field->getMappedFormObject();
                if ($selectObj instanceof I2CE_Form) {
                    //make this object avaialable for record level security
                    $this->template->setForm($selectObj);
                }
            }
        }
        return $this->select_field;
    }
    protected function action_select_list($listConfig) {	
        $this->template->addFile( "auto_lists_type_list.html" );
        $this->template->appendFileById( "auto_list_type_header.html", "th", "lists_header" );        

        $this->setTemplateVars($listConfig);
        $list = I2CE_List::listOptions($this->getPrimaryFormName(),$this->showHidden());
        if ( $this->request_exists('letter') ) {
            $list = array_filter( $list, "self::filter_by_" . $this->request('letter') );
        }
        if ( count($list) > 0 ) {
            if (!  ($list = $this->paginateList($list))) {
                return false;
            }
        }
        return $this->actionDisplayList_row($list,$listConfig);
    }

    protected function setTemplateVars($listConfig,$link_data = array()) {
        if (!is_array($link_data)) {
            $link_data = array();
        }
        $this->template->setDisplayData('text',$listConfig['text']);
        if (!$listConfig['type']) {
            $link_data['form']=$listConfig['form'];
        } else {
            $link_data['type']=$listConfig['type'];
        }
        if ($listConfig['type']) {
            $this->template->setDisplayData('type',$listConfig['type']);
        } else {
            $this->template->setDisplayData('form',$listConfig['form']);
        }
        $link_data['show_i2ce_hidden'] = $this->showHidden();
        if ($this->request_exists('letter') && $this->request('letter')) {
            $link_data['letter'] = $this->request('letter');
        }
        $this->template->setDisplayData('add_new_link',$link_data);
        if (($hiddenSelectNode = $this->template->getElementByName('show_i2ce_hidden',0)) instanceof DOMElement) {
            $this->template->addHeaderLink("mootools-core.js");
            $h_link_data = $link_data;
            if (array_key_exists('show_i2ce_hidden',$h_link_data)) {
                unset($h_link_data['show_i2ce_hidden']);
            }
            $url = "index.php/auto_list?" . http_build_query($h_link_data) . '&show_i2ce_hidden=';
            $js = 'document.location.href = "' .addslashes($url) . '" + this.get("value");'; 
            $hiddenSelectNode->setAttribute('onChange',$js);
            $this->template->selectOptionsImmediate('show_i2ce_hidden',$this->showHidden());
        }
        $can_edit = true;
        if (array_key_exists('edit_task',$listConfig) && ! $this->hasPermission('task(' . $listConfig['edit_task'] .')')) {
            $can_edit =false;
        }
        if ($can_edit  && I2CE_FormStorage::isWritable($this->getPrimaryFormName())) {
            $this->template->setDisplayData( "list_is_writable", 1);
        } else {
            $this->template->setDisplayData( "list_is_writable", 0);
        }
        if ( ($link = $this->getRemapAllLink($listConfig)) && ($this->hasRemapData($listConfig))) {
            $this->template->setDisplayData('list_hasremap',1);
            $this->template->setDisplayData('remap_link',$link);
        } else {
            $this->template->setDisplayData('list_hasremap',0);
        }
        $this->addAlphabet($link_data);

    }


    protected function addAlphabet($link_data) {
        if ($this->module == 'I2CE') {
            $url = $this->page;
        } else {
            $url = $this->module . '/' . $this->page;
        }
        if (! ($alpha_node = $this->template->appendFileById( "auto_lists_type_header_alphabet_clear.html", "span", "lists_alphabet" )) instanceof DOMNode) {
            return;
        }
        if (array_key_exists('letter',$link_data)) {
            unset($link_data['letter']);
        }
        $this->template->setDisplayDataImmediate( "alpha_link", http_build_query($link_data) , $alpha_node);
        $atoz = range( 'A', 'Z' );
        array_unshift( $atoz, '#' );
        foreach( $atoz as $letter ) {
            if ( $letter == '#' ) {
                $link_data['letter'] = 'num';
            } else {
                $link_data['letter'] = $letter;
            }
            if ( $letter == $this->get('letter') || ($letter == '#' && $this->get('letter') == 'num') ) {
                if  (! ($alpha_node = $this->template->appendFileById( "auto_lists_type_header_alphabet_selected.html", "span", "lists_alphabet" )) instanceof DOMNode) {
                    continue;
                }
            } else {
                if (! ($alpha_node = $this->template->appendFileById( "auto_lists_type_header_alphabet.html", "span", "lists_alphabet" )) instanceof DOMNode) {
                    continue;
                }
                $this->template->setDisplayDataImmediate( "alpha_link", http_build_query($link_data) , $alpha_node);
            }
            $this->template->setDisplayDataImmediate( "alpha_name", $letter, $alpha_node );
        }
    }



    protected function showHidden() {
        $show_hidden = 0;
        if ($this->request_exists('show_i2ce_hidden')) {
            $show_hidden = (int) $this->request('show_i2ce_hidden');
            if ($show_hidden < 0 || $show_hidden > 2) {
                $show_hidden = 0;
            }
        }
        return $show_hidden;
    }



    protected function action_select_field($listConfig) {        
        if (! ($obj = $this->getPrimary()) instanceof I2CE_List) {
            I2CE::raiseError("No primary list");
            return false;
        }
        if (! ($select_field = $this->getSelectField($listConfig)) instanceof I2CE_FormField_MAPPED) {
            I2CE::raiseError("select field is not map");
            return false;
        }

        if (! ($node =$this->template->addFile( "auto_lists_type_mapped.html" )) instanceof DOMNode) {
            return false;
        }
        $this->template->setDisplayData( 'field', $select_field->getName(), $node );
        $this->template->setDisplayData( 'field_name', $select_field->getHeader(), $node );
        $this->template->setDisplayData( "type_name", $obj->getDisplayName() );
        if( ($formNode = $this->template->getElementByTagName('form',0,$node))instanceof DOMElement){
            $formNode->setAttribute('action','auto_list');
        }

        $add_node = $this->template->getElementById('mapped');
        if (!$add_node instanceof DOMNode) {
            I2CE::raiseError("Don't know where to add mapped field options");
        } else { 
            $select_template = "lists_type_mapped_" . $this->getPrimaryFormName() . "_" . $select_field->getName() . ".html";
            $select_template = $this->template->findTemplate( $select_template, false );
            if ( !$select_template ) {
                $select_template = "lists_type_mapped_default.html";
            }
            $node = $this->template->appendFileById( $select_template, "span", "mapped" );
            $form_node = $this->template->getElementById('select_form_field_node');
            if ($form_node instanceof DOMElement) {
                $form_node->setAttribute('show_i2ce_hidden',$this->showHidden()); 
                $select_field->processDOMEditable($node, $this->template,$form_node);                        
                $add_node->appendChild($node);
            } else {
                I2CE::raiseError("could not find 'select_form_field_node' in " . $select_template);
            }
        }
        $this->template->appendFileById( "lists_type_header.html", "th", "lists_header" );
        $keys = explode('[',$htmlname = $select_field->getHTMLName());
        foreach ($keys as &$key) {
            if (strlen($key) > 0 && substr($key,-1) == ']') {
                $key = substr($key,0,-1);
            }	    
        }
        unset($key);	
        $link_data = array($htmlname=>$this->request($keys));
        $this->setTemplateVars($listConfig,$link_data);

        if (!$select_field->isSetValue() && !$this->request_exists($keys)) {
            return true; //don't show any options until a value has been selected
        }
        //select_field may be not set.  that's ok.  listOptions does error checking
        $list = I2CE_List::listOptions($this->getPrimaryFormName(),$this->showHidden(),$select_field);
        if ( $this->request_exists('letter') ) {
            $list = array_filter( $list, "self::filter_by_" . $this->request('letter') );
        }
        if ( count($list) > 0 ) {
            if (!  ($list = $this->paginateList($list))) {
                return false;
            }
        }
        return $this->actionDisplayList_row($list,$listConfig);
    }

    public function __call( $func, $args ) {
        if ( substr( $func, 0, 10 ) == "filter_by_" ) {
            $letter = substr($func, 10);
            if ( $letter == "num" ) {
                if ( is_numeric( $args[0]['display'][0] ) ) {
                    return true;
                }   else {
                    return false;
                }
            }
            if ( strtolower( $args[0]['display'][0] ) == strtolower($letter) ) {
                return true;
            }  else {
                return false;
            }
        } else {
            return parent::__call( $func, $args );
        }
    }



    protected function action_menu() {
        if (! ($catsNode = $this->template->getElementByID('list_categories'))instanceof DOMNode) {
            I2CE::raiseError("Cannot find list_categories");
            return false;
        }

        $style ='tab';	
        I2CE::getConfig()->setIfIsSet($style,"/modules/Lists/auto_list_options/menu/style");
        if (!I2CE_ModuleFactory::instance()->isEnabled('tabbed-pages') || !$style =='tab') {
            $style = 'column';
        }
        if ($this->request_exists('style') && (is_scalar($this->request('style'))) && $this->request('style')) {
            $style = $this->request('style');
        }
        $method = 'action_menu_'  . $style;
        if ($this->_hasMethod($method)) {
            return $this->$method($catsNode);
        }
        return $this->action_menu_column($catsNode); //default display
    }

    protected function action_menu_column($catsNode) {
        $cols = 2;
        I2CE::getConfig()->setIfIsSet($cols,"/modules/Lists/auto_list_options/menu/cols");
        $cols = (int) $cols;
        if ($this->request_exists('cols')) {
            $cols =  (int) $this->request('cols');
        }
        if ($cols < 1 ) {
            $cols = 1;
        } 
        $catNodeT = $this->template->createElement('div',array('style'=>'display:table;width:100%'));
        $catsNode->appendChild($catNodeT);
        $catNodeR = $this->template->createElement('div',array('style'=>'display:table-row;width:100%'));
        $catNodeT->appendChild($catNodeR);
        $catNodeCells = array();
        $width = 99.0/$cols;
        for ($i=0; $i < $cols; $i++) {	    
            $catsNodeCells[$i] = $this->template->createElement('div',array('style'=>'display:table-cell;width:' . $width . '%'));
            $catNodeR->appendChild($catsNodeCells[$i]);
        }

        $categories = $this->getCategorizedLists();
        $tot  = 0;
        foreach($categories as $cat_details) {
            if (!is_array($cat_details) 
                    || !array_key_exists('subcategory',$cat_details)	
                    || !is_array($cat_details['subcategory'])) {
                continue;
            }
            $tot +=count($cat_details['subcategory']);
        }
        $count =0;
        foreach ($categories as $cat=>$cat_details) {
            if (!is_array($cat_details) 
                    || !array_key_exists('subcategory',$cat_details)	
                    || !is_array($subcats = $cat_details['subcategory'])) {
                continue;
            }
            $catNode = $this->template->createElement('h2');
            $cat_name = $cat;
            if (array_key_exists('text',$cat_details)
                    && is_scalar($cat_details['text'])
               ) {
                $cat_name = $cat_details['text'];
            }
            $catNode->appendChild($this->template->createTextNode( $cat_name));
            $catListNode = $this->template->createElement('div');
            foreach ($subcats as $subcat => $lists) {
                $scatListNode = $this->template->createElement('ul');
                $is_available = false;
                foreach ($lists as $list=>$listConfig) {
                    if (! ($linkNode = $this->getListLinkNode($listConfig)) instanceof DOMNode) {
                        continue;
                    }
                    $is_available = true;
                    $liNode = $this->template->createElement('li');
                    $liNode->appendChild($linkNode);
                    $scatListNode->appendChild($liNode);		    
                }
                if ( $is_available ) {
                    if ($subcat != '0') {
                        $scatNode = $this->template->createElement('h3');
                        $scatNode->appendChild($this->template->createTextNode( $subcat));
                        $catListNode->appendChild($scatNode);
                    }
                    $catListNode->appendChild($scatListNode);		
                }
            }
            $which = (int) ($count/ ($tot/$cols));
            $count += count($subcats);
            $catsNodeCells[$which]->appendChild($catNode);
            $catsNodeCells[$which]->appendChild($catListNode);
        }
        return true;
    }

    protected function action_menu_tab($catsNode) {

        //<div id='tab_panel'>
        //<ul class='tabs' id='tabs_link'/>
        //<div class='tabs_content' id='tabs_content'/>
        //</div>
        $this->template->addHeaderLink('mootools-core.js');
        $this->template->addHeaderLink('mootools-more.js');
        $this->template->addHeaderLink('I2CE_AjaxTabPanel.js');
        $this->template->addHeaderLink('tabs.css');

        $catsNode->appendChild($tabNode = $this->template->createElement('div',array('id'=>'tab_panel')));
        $tabNode->appendChild( $tabsNode = $this->template->createElement('ul',array('class'=>'tabs','id'=>'tabs_link')));
        $tabNode->appendChild( $tabsContentNode = $this->template->createElement('div',array('class'=>'tabs_content','id'=>'tabs_content')));
        $categories = $this->getCategorizedLists();
        
        $selected = false;
        if ($this->request_exists('selected_tab') 
                && is_scalar($this->request('selected_tab'))
                && array_key_exists($this->request('selected_tab'),$categories)) {
            $selected = $this->request('selected_tab');
        } elseif ( array_key_exists( 'HTTP_REFERER', $_SERVER ) ) {
            $referer = parse_url( $_SERVER['HTTP_REFERER'] );
            $ref_qry = array();
            if ( array_key_exists( 'query', $referer ) ) {
                parse_str( $referer['query'], $ref_qry );
                $selected_form = null;
                if ( array_key_exists( 'type', $ref_qry ) ) {
                    $selected_form = $ref_qry['type'];
                } elseif ( array_key_exists( 'form', $ref_qry ) ) {
                    $selected_form = $ref_qry['form'];
                }
                if ( $selected_form && ($listConfig = $this->getListConfig( $selected_form )) && array_key_exists( 'category', $listConfig ) ) {
                    $selected = $listConfig['category'];
                }
            }
        }
        if ( !$selected ) {
            $selected = key($categories);
        }
        $selected = preg_replace('/[^a-zA-Z0-9_\,]/s','',$selected);
        $js = 'document.addEvent("domready", function() {
                var tab = new I2CE_AjaxTabPanel("tab_panel");
                if (tab) { tab.showTab("' . addslashes($selected) . '");}});';
        $this->template->addHeaderText($js,'script','create_tabs');
        foreach ($categories as $cat=>$cat_details) {
            $tab_id = preg_replace('/[^a-zA-Z0-9_\,]/s','',$cat);
            if (!is_array($cat_details) 
                    || !array_key_exists('subcategory',$cat_details)	
                    || !is_array($subcats = $cat_details['subcategory'])) {
                continue;
            }
            $cat_name = $cat;
            if (array_key_exists('text',$cat_details)
                    && is_scalar($cat_details['text'])
               ) {
                $cat_name = $cat_details['text'];
            }
            $attrs  = array('class'=>'tab_link','id'=>'tab_link_' . $tab_id);
            $t_attrs = array('id'=>'tab_content_' . $tab_id,'class'=>'tab_content');
            if ($cat  != $selected) {
                $t_attrs['style']='display:none';
            }
            $tabsNode->appendChild($liNode = $this->template->createElement('li',$attrs,$cat_name));
            $catsNodeCell = $this->template->createElement('div',$t_attrs);
            $tabsContentNode->appendChild($catsNodeCell);
            $catsNodeCell->appendChild($catNode=  $this->template->createElement('h2'));
            $catNode->appendChild($this->template->createTextNode( $cat_name));
            $catListNode = $this->template->createElement('div');
            $catsNodeCell->appendChild($catListNode);
            foreach ($subcats as $subcat => $lists) {
                $scatListNode = $this->template->createElement('ul');
                $is_available = false;
                foreach ($lists  as $list=>$listConfig) {
                    if (! ($linkNode = $this->getListLinkNode($listConfig)) instanceof DOMNode) {
                        continue;
                    }
                    $is_available = true;
                    $liNode = $this->template->createElement('li');
                    $liNode->appendChild($linkNode);
                    $scatListNode->appendChild($liNode);		    
                }
                if ( $is_available ) {
                    if ($subcat != '0') {
                        $scatNode = $this->template->createElement('h3');
                        $scatNode->appendChild($this->template->createTextNode( $subcat));
                        $catListNode->appendChild($scatNode);
                    }
                    $catListNode->appendChild($scatListNode);		
                }
            }
        }		
        return true;
    }


    protected function getCategorizedLists() {
        $categories = array();
        $skip_forms = array();
        $viewed_forms = array();
        $auto_lists = I2CE::getConfig()->getKeys("/modules/Lists/auto_list");
        $configs = array();
        foreach ($auto_lists as $type) {
            if (! ($listConfig = $this->getListConfig($type)) ) {
                continue;
            }
            $form = false;
            if (!array_key_exists('form',$listConfig)  || ! ($form  = $listConfig['form'])) {
                continue;
            }
            $viewed_forms[] = $form;
            $skip = false;
            if (array_key_exists('skip',$listConfig)  &&  $listConfig['skip']) {
                $skip_forms[] = $form;
                continue;
            }
            $cat = 'other_lists';	    
            if (array_key_exists('category',$listConfig) && is_string($listConfig['category']) && strlen($listConfig['category'])) {
                $cat = $listConfig['category'];
            }
            $cats[] = $cat;
            if (!array_key_exists($cat,$configs)) {
                $configs[$cat] = array();
            }
            $configs[$cat][$type] = $listConfig;
        }
        $this->loadAllLists();
        if ($this->user->getRole() == 'admin') {
            $other_forms = array_diff(array_diff($this->all_lists,$viewed_forms),$skip_forms);
            if (count($other_forms) > 0) {
                $cats[] = 'other_lists'; //semi-reserved list
                if (!array_key_exists('other_lists',$configs)) {
                    $configs['other_lists'] = array();
                }
                $other_forms_data = array();
                foreach( $other_forms as $form) {
                    if (  ! ($listConfig = $this->getListConfig(false,$form))) {
                        continue;
                    }
                    $other_forms_data[] =  $listConfig;
                }
                if (!array_key_exists('other_lists',$configs)) {
                    $configs['other_lists'] = array();
                }

                //usort($other_forms_data,array($this,'sortByTextKey'));
                $configs['other_lists'] = array_merge($configs['other_lists'],$other_forms_data);
            }
        }
        $cats = array_unique($cats);
        $cat_names = I2CE::getConfig()->getAsArray("/modules/Lists/auto_list_category");
        if (!is_array($cat_names)) {
            $cat_names = array();
        }
        if (!array_key_exists('other_lists',$cat_names)) {
            $cat_name = 'Other Lists';
            I2CE::getConfig()->setIfIsSet($cat_name,"/modules/Lists/messages/other_lists");
            $cat_names['other_lists'] = $cat_name;
        }

        foreach ($cats as $cat) {
            $s_categories = array();
            $subcats = array();
            $sconfigs= array();
            foreach ($configs[$cat] as $list=>$listConfig) {
                $subcat = '0'; //reserved subcategory as default
                if (array_key_exists('subcategory',$listConfig) && is_scalar($listConfig['subcategory']) && $listConfig['subcategory']) {
                    $subcat= $listConfig['subcategory'];		    
                }
                $subcats[] = $subcat;
                if (!array_key_exists($subcat,$sconfigs)) {
                    $sconfigs[$cat] = array();
                }
                $sconfigs[$subcat][$list] = $listConfig;
            }
            $subcats  = array_unique($subcats);
            sort($subcats);
            foreach ($subcats as $subcat) {
                if (count($sconfigs[$subcat]) == 0) {
                    continue;
                }
                usort($sconfigs[$subcat],array($this,'sortByTextKey'));
                $s_categories[$subcat] = $sconfigs[$subcat];
            }
            if (count($s_categories) == 0) {
                continue;
            }
            if (version_compare(PHP_VERSION, '5.4.0') < 0 ) {
                ksort($s_categories, SORT_STRING );
            } else { 
                ksort($s_categories, SORT_NATURAL | SORT_FLAG_CASE);
            }
            $cat_name = $cat;
            if (array_key_exists($cat,$cat_names) 
                    && is_scalar($cat_names[$cat])) {
                $cat_name = $cat_names[$cat];
            }
            $categories[$cat] = array('text'=>$cat_name,'subcategory'=>$s_categories);
        }
        if ($this->request_exists('category') && is_scalar($cat = $this->request('category')) && $cat) {
            $t_categories = array();
            foreach (explode(",",$cat) as $cat) {
                if (!array_key_exists($cat,$categories)) {
                    continue;
                }
                $t_categories[$cat]=$categories[$cat];
            }
            $categories =$t_categories;
        }
        uasort($categories,array($this,'sortByTextKey'));

        return $categories;
    }


    protected function sortByTextKey($a,$b) {
        return strcasecmp($a['text'],$b['text']);
    }

    protected $all_lists = array();

    protected function loadAllLists() {
        $this->all_lists = array();

        $ff = I2CE_FormFactory::instance();
        foreach ($ff->getForms() as $form) {
            $formClass = $ff->getClassName($form);
            if (!I2CE_List::isList($formClass)) {
                continue;
            }
            $this->all_lists[] = $form;
        }
    }

    protected $remap_ids = array();
    protected function hasRemapData($listConfig) {

        $form = false;
        if (array_key_exists('form',$listConfig) && is_scalar($listConfig['form'])) {
            $form = $listConfig['form'];
        } 
        $ff = I2CE_FormFactory::instance();
        $formClass = $ff->getClassName($form);
        if (!$formClass || !I2CE_List::isList($formClass)) {
            return false;
        }
        $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'remap',
                'style'=>'not_null',
                'data'=>array()
                );
        $this->remap_ids = I2CE_FormStorage::search($form,false,$where);
        return count( $this->remap_ids) > 0;
    }

    protected function getRemapAllLink($listConfig) {
        if ($this->user->getRole()  != 'admin') {
            return false;
        }
        $form = false;
        if (array_key_exists('form',$listConfig) && is_scalar($listConfig['form'])) {
            $form = $listConfig['form'];
        } 
        $ff = I2CE_FormFactory::instance();
        $formClass = $ff->getClassName($form);
        if (!$formClass || !I2CE_List::isList($formClass)) {
            return false;
        }
        return 'index.php/auto_list_remap?id='  . $form .'|*';
    }


    protected function getListLinkNode($listConfig) {
        $task = false;
        if (array_key_exists('task',$listConfig) && is_scalar($listConfig['task'])) {
            $task = $listConfig['task'];
        } 
        if ($task  && I2CE_PermissionParser::taskExists($task) && !$this->hasPermission("task($task)")) {
            return false;
        }
        $form = false;
        if (array_key_exists('form',$listConfig) && is_scalar($listConfig['form'])) {
            $form = $listConfig['form'];
        } 
        if (!$form || !in_array($form,$this->all_lists)) {
            return false;
        }
        $text ='';
        if (array_key_exists('text',$listConfig) && is_scalar($listConfig['text'])) {
            $text = $listConfig['text'];
        }  
        if (!$text) {
            $text = I2CE_FormStorage::instance()->getDisplayName($form);
        }
        if (! $listConfig['type']) {
            $attrs = array('href'=>'index.php/auto_list?form=' . $form);
        } else {
            $attrs = array('href'=>'index.php/auto_list?type=' . $listConfig['type']);
        }

        return  $this->template->createElement('a',$attrs,$text);

    }



    protected function paginateList($list,$jumper_id = 'select_list') {
        if ($this->module == 'I2CE') {
            $url = $this->page;
        } else {
            $url = $this->module . '/' . $this->page;
        }

        $page_size = 50;
        if (array_key_exists('page_length',$this->args)) {
            $page_size = $this->args['page_length'];
        }
        $page_size = (int) $page_size;
        if ($page_size <=  0) {
            $page_size = 50;
        }
        $total_pages = max(1,ceil (count($list)/$page_size));
        $form = $this->getPrimaryFormName();
        $pageVar = 'page';
        if ($jumper_id != 'select_list') {
            $pageVar = $jumper_id . '_page';
        }
        if ($total_pages > 1) {
            $page_no =  (int) $this->request($pageVar);
            $page_no = min(max(1,$page_no),$total_pages);
            $offset = (($page_no - 1)*$page_size );
            $list = array_slice($list, $offset, $page_size,true);
            $qry_fields = $this->request();
            $qry_fields['form'] = $form;
            foreach (array($pageVar) as $key) {
                if (array_key_exists($key,$qry_fields)) {
                    unset($qry_fields[$key]);
                }
            }        
            $this->makeJumper($jumper_id,$page_no,$total_pages,$url,$qry_fields,$pageVar);                
        }
        return $list;
    }


    protected function actionDisplayList_row($list,$listConfig) {
        $odd = false;
        if ($listConfig['type']) {
            $link = 'auto_list?type=' . $listConfig['type'];
        } else {
            $link = 'auto_list?form=' . $listConfig['form'];
        }
        $imported = $this->template->loadFile( "lists_type_row.html", "tr", "lists_body" );
        if (!$imported instanceof DOMNode) {
            I2CE::raiseError("Could not find lists_type_row.html");
            return false;
        }        
        $remaped = $this->template->loadFile( "lists_type_row_remapped.html", "tr", "lists_body" );
        if (!$remaped instanceof DOMNode) {
            $remaped = $imported;
        }        

        $append = $this->template->getElementById('lists_body');
        if (!$append instanceof DOMNode) {
            I2CE::raiseError("Don't know where to append list rows");
            return false;
        }

        foreach( $list as  $data) {
            $id = substr($data['value'],strlen($listConfig['form']) + 1);
            if (in_array($id,$this->remap_ids)
                    && ( $remap = I2CE_FormStorage::lookupField($listConfig['form'],$id,'remap',''))
               ) {
                $imported_row = $remaped->cloneNode(true);            	
                if ($listConfig['type']) {
                    $url = 'index.php/auto_list?type=' . $listConfig['type'];
                } else {
                    $url = 'index.php/auto_list?form=' . $listConfig['type'];
                }
                $url .= '&id=' . $listConfig['form'] . '|' . $id;
                $this->template->setDisplayDataImmediate('remapped_link',$url,$imported_row);
                list($rform,$rid) = array_pad(explode('|',$remap,2),2,'');
                $this->template->setDisplayDataImmediate('remapped_value',I2CE_List::lookup($rid,$rform),$imported_row);
            } else {
                $imported_row = $imported->cloneNode(true);            
            }
            $this->template->appendNode($imported_row,$append);
            if ( $odd ) {
                $this->template->setNodeAttribute( "class", "even", $imported_row );
            }
            $odd = !$odd;
            $this->template->setDisplayDataImmediate( "lists_row_link", $link .'&id=' . $data['value'], $imported_row);
            $this->template->setDisplayDataImmediate( "lists_row_name", $data['display'], $imported_row );
        }
        return true;        
    }


    protected function getViewPage( $type ) {
        if ( I2CE::getConfig()->is_parent( "/I2CE/page/view_" . $type ) ) {
            return "view_" . $type;
        } else {
            return 'auto_list';
        }
    }



}


