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
    * @package I2CE
    * @author Carl Leitner <litlfred@ibiblio.org>
    * @since v2.0.0
    * @version v2.0.0
    */
/**
 * Class for defining all the database fields used by a {@link I2CE_Form} object.
 * @package I2CE
 * @access public
 */
class I2CE_FormField_ASSOC_LIST extends I2CE_FormField_DB_STRING { 


    /**
     * Return the value of this field from the database format for the given type
     * @param integer $type The type of the field to be returned.
     * @param mixed $value
     */
    public function getFromDB($value ) {
        return json_decode($value,true );
    }

    /**
     * Sets the value of this field from the database format.
     * @param mixed $value
     */
    public function setFromDB( $value ) {
        $this->value = $this->getFromDB($value );
        $this->sortKeys();
    }   
                
    protected function sortKeys() {
        switch (strtoupper($this->getOption('key_sort'))) {
        case 'NONE':
            break;
        case 'SORT_REGULAR':
            ksort($this->value,SORT_REGULAR);
            break;
        case 'SORT_NUMERIC':
            ksort($this->value,SORT_NUMERIC);
            break;
        case 'SORT_STRING':
            ksort($this->value,SORT_STRING);
            break;
        case 'SORT_STRING_CASE':
            uksort($this->value, "strcasecmp");
            //ksort($this->value,SORT_STRING | SORT_FLAG_CASE); php 5.4
            break;
        case 'SORT_LOCAL_STRING':
            ksort($this->value,SORT_LOCALE_STRING);
            break;
        case 'SORT_NATURAL':
            uksort($this->value, "strnatcasecmp");
            // ksort($this->value,SORT_NATURAL); php 5.4
            break;
        default:
        case 'SORT_NATURAL_CASE':
            uksort($this->value, "strnatcasecmp");
            //ksort($this->value,SORT_NATURAL | SORT_FLAG_CASE);  php 5.4
            break;
        }
    }

    /**  
     * Sets the value of this field from the posted form.
     * @param array $post The $_POST array holding the values for this form.
     */
    public function setFromPost( $post ) {
        if ( is_array( $post)) {
            $this->value = $post;
        } else if (is_string($post)) {
            $this->value = $this->getFromDB($post);
        } 
        $this->sortKeys();
    }
        
        

    public function getValue() {
        if (!$this->issetValue()) {
            return array();
        } 
        return $this->value;
    }

    public function getKeys() {        
        if (!$this->issetValue()) {
            return array();
        } 
        return array_keys($this->value);
    }

    public function getValueOfKey($key) {
        if (!$this->issetValue()||!array_key_exists($key,$this->value)) {
            return null;
        }
        return $this->value[$key];
    }


    public function keyIsSet($key) {
        return is_array($this->value) && array_key_exists($key,$this->value) && is_scalar($this->value[$key]);
    }

    public function setValueOfKey($key,$val) {
        if (!$this->issetValue()) {
            $this->value = array();
        }
        if (is_scalar($val)) {
            $this->value[$key] = $val;
        } else {
            $this->value[$key] = null;
        }
        $this->sortKeys();
    }
        
    public function getDBValue() {
        if (!$this->issetValue()) {
            return json_encode(array());
        } else {
            return json_encode($this->value);
        }
    }
        
    /**
     * Returns the value of this field as a human readable format.
     * @param I2CE_Entry $entry If a I2CE_Entry object has been passed to this method then it will return the value for that entry assuming it's an 
     * entry for this field.
     * @return mixed
     */
    public function getDisplayValue( $entry=false,$style='default' ) {
        if ( $entry instanceof I2CE_Entry ) {
            $value = $entry->getValue();
        } else {
            $value = $this->getValue();
        }
        $vals = array();
        foreach ($value as $k=>$v) {
            $vals[] = "$k: $v ";
        }
        return implode( ", ", $vals );
    }


    public function removeKey($key)  {
        if (!$this->issetValue()) {
            return;
        }
        if (!array_key_exists($key,$this->value)) {
            return;
        }
        unset($this->value[$key]);
    }


    public function ensureKey($key)  {
        if (!$this->issetValue()) {
            $this->value = array();
        }
        if (!array_key_exists($key,$this->value)) {
            $this->value[$key] = null;
        }
        $this->sortKeys();
    }
        
        
    /**
     * Checks to see if the current value for this is set and valid.
     * @return boolean
     */
    public function isValid() {
        if ( !is_array($this->value)) {
            return false;
        }
        $valid = true;
        foreach ($this->value as $key=>$val) {
            $valid &= is_scalar($val);
        }
        return $valid;
    }
        
    /**
     * Return the display value of this form field as a DOM Node.
     * @param DOMNode $node
     * @param I2CE_Template $template
     * @return DOMNode
     */
    public function getDisplayNode( $node,$template ) {
        $text_node = $template->createElement('span', array('class'=>'assoc_container'));
        $value = $this->getValue();
        foreach ($value as $k=>$v) {
            $pair_node = $template->createElement('span', array('class'=>'assoc_pair_container'));
            $text_node->appendChild($pair_node);
            $pair_node->appendChild( $template->createElement('span', array('class'=>'assoc_pair_key'), $k));
            $pair_node->appendChild( $template->createElement('span', array('class'=>'assoc_pair_value'), $this->getSingleDisplayValue($v)));
        }
        if ( ($href = $this->getHref()) ) {
            $link_node = $template->createElement( "a", array( "href" => $href ) );
            $link_node->appendChild( $text_node );
            return $link_node;
        } else {
            return $text_node;
        }
    }


    public function processDOMEditable($node,$template,$form_node) {
        if (!$this->issetValue()) {
            return;
        }
        $name_base = $this->getHTMLName();
        $class = strtr($name_base,':','_');
        $qry = ".//input";
        if (! ($contNode = $template->loadFile('assoc_input_container.html','span')) instanceof DOMNode) {
            I2CE::raiseError("bad assoc_input_container.html");
            return false;
        }            
        $node->appendChild($contNode);
        if (! ($contNode = $template->getElementById('assoc_container',$contNode)) instanceof DOMNode) {
            I2CE::raiseError("No assoc_container node");
            return false;
        }
        if ($form_node->hasAttribute('input_class')) {
            $class = $form_node->getAttribute('input_class') . '_';
        } else {
            $class = 'input_';
        }
        $attrList = array();
        $attrs = array ('size','maxlength','style');
        foreach ($attrs as $attr) {
            if (!$form_node->hasAttribute($attr)) {
                continue;
            }
            $attrList[$attr]  = $form_node->getAttribute($attr);
        }
        foreach ($this->value as $key=>$val) {
            if (! ($inpNode = $template->loadFile('assoc_input.html','span')) instanceof DOMNode) {
                I2CE::raiseError("bad assoc_input.html");
                return false;
            }            
            $name = $name_base . '[' . $key . ']';
            $template->setDisplayDataImmediate('key',$key,$inpNode);
            $template->setAttribute('name',$name,null,$qry,$inpNode);            
            $inputs = $template->query($qry,$inpNode);
            if ($inputs instanceof DOMNodeList) {
                foreach ($inputs as $input) {
                    if (!$input instanceof DOMElement) {
                        continue;
                    }
                    $exis_class = '';
                    if ($input->hasAttribute('class')) {
                        $exis_class = trim($input->getAttribute('class')) . ' ' ;
                    }        
                    $input->setAttribute('class',$exis_class . $class . $key);
                    $input->setAttribute('value',$this->getSingleDisplayValue($val));
                    foreach ($attrList as $attr=>$attrVal) {
                        $input->setAttribute($attr,$attrVal);
                    }
                }
            }
            $contNode->appendChild($inpNode);
        }
        $this->setElement($contNode);
    }
    
          
    protected function getSingleDisplayValue($val) {
        return $val;
    }


    public function postprocessDOMEditable( $node, $template, $form_node ) {
        if ( !($inputs = $template->query(".//input" ,$node))  instanceof DOMNodeList) {
            return;
        }
        $template->addHeaderLink('mootools-core.js');
        $template->addHeaderLink('mootools-more.js');
        $template->addHeaderLink('I2CE_InputFormatter.js');
        

        foreach ($inputs as $input) {
            if (!$input instanceof DOMElement) {
                continue;
            }
            $this->postprocessInput($input,$template,$form_node);
        }
    }

    protected function postprocessInput( $node, $template, $form_node ) {
        
    }

}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
