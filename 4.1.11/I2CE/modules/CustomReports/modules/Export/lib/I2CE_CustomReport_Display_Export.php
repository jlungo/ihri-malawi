<?php
/**
 * @copyright © 2008, 2009 Intrahealth International, Inc.
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
*  I2CE_CustomReport_Display_Export
* @package I2CE
* @subpackage Core
* @author Carl Leitner <litlfred@ibiblio.org>
* @version 2.1
* @access public
*/


class I2CE_CustomReport_Display_Export extends I2CE_CustomReport_Display{

    protected static $col_search = 
        array('tab'=>array("\r","\n","\t"),
              'csv'=>'"',
              'csvxls'=>'"',
              'html'=>'',
              'json'=>'',
              'rawjson'=>''
            );
    protected static $col_replace = 
        array('tab'=>array("","","        "),
              'csv'=>'""',
              'csvxls'=>'""',
              'html'=>'',
              'json'=>'""',
            );
    protected static $col_separate = 
        array('tab'=>"\t",
              'csv'=>',',
              'csvxls'=>',',
              'html'=>'',
              'json'=>',',
            );
    protected static $col_begin = 
        array('tab'=>'"',
              'csv'=>'"',
              'csvxls'=>'"',
              'html'=>'<td>',
              'json'=>'',
            );
    protected static $col_end = 
        array('tab'=>'"',
              'csv'=>'"',
              'csvxls'=>'"',
              'html'=>'</td>',
              'json'=>'',
            );
    protected static $row_begin = 
        array('tab'=>'',
              'csv'=>'',
              'csvxls'=>'',
              'html'=>'<tr>',
              'json'=>',',
            );
    protected static $row_end = 
        array('tab'=>"\n",
              'csv'=>"\n",
              'csvxls'=>"\n",
              'html'=>"</tr>\n",
              'json'=>'',
            );
    protected static $file_suffix =
        array( 'tab' => 'tab',
                'csv' => 'csv',
                'csvxls' => 'csv',
                'html' => 'html',
                'json' => 'json',
                'rawjson' => 'json'
             );



    /**
     * Process results
     * @param array $results_data an array of results.  indices are 'restults' and MDB2 Buffered result and 'num_results' the
     * number of results.  (these values may be false on failure)
     * @param DOMNode $contentNode.  Default to null a node to append the results onto
     */
    protected function processResults($results_data,$contentNode=null) {
        //$results_data['num_results']  is the total number of results or false
        if ($results_data['results'] == false) {
            I2CE::raiseError("No results");
            return '';
        }
        if ($this->row_start === false) {
            $row_num = 0;
        } else {
            $row_num = $this->row_start;
        }        
        $out = '';
        if ( $this->style == "rawjson" ) {
            $out = json_encode( $results_data['results']->fetchAll() );
        } else {
            while ($row = $results_data['results']->fetchRow()) {
                if (  PEAR::isError( $row ) ) {
                    break;
                }
                if (!($row_out = $this->processResultRow($row,$row_num,$contentNode))) {
                    $results_data['results']->free();
                    return '';
                }
                $out .= $row_out;
                $row_num++;
            }
        }
        $results_data['results']->free();
        return $out;
    }


    /**
     * Process a result row.
     * @param array $row
     * @param int $row_num The current row number when processing results.  If there was a result limit, it starts the count from the beginning of the
     * result offset.  Othwerwise, it starts counting form zero.
     * @param DOMNode $contentNode. Default to null. A node to append the result onto
     */
    protected function processResultRow($row,$row_num,$contentNode=null) {
        $mapped_row = $this->mapResults($row);
        if ( $row_num % 500 == 0  && array_key_exists('HTTP_HOST',$_SERVER) && I2CE_Dumper::usesDefaultOutputBuffering()) {
            ob_flush();
        }
        if ($this->style == 'xml') {
            return $this->processResultRowXML($mapped_row,$row_num);
        } else {
            array_unshift( $mapped_row, $row_num );
            return $this->processResultRowArray( $mapped_row );
        }
    }

    protected function processResultRowArray($row) {
        $vals = array();

        foreach ($this->cols as $key) {
            if ($key == '#') {
                continue;
            }
            $lkey = strtolower($key);
            if (array_key_exists($lkey,$row)) {
                $prefix = '';
                if ( $this->style == 'csvxls' && is_numeric($row[$lkey]) && $row[$lkey] >= 999999999999999 ) {
                    $prefix = '=';
                }
                $vals[] = $prefix . self::$col_begin[$this->style] . str_replace(self::$col_search[$this->style],self::$col_replace[$this->style],$row[$lkey]) . self::$col_end[$this->style]  ;
            }else if (array_key_exists($key,$row)) {
                $prefix = '';
                if ( $this->style == 'csvxls' && is_numeric($row[$key]) && $row[$key] >= 999999999999999 ) {
                    $prefix = '=';
                }
                $vals[] = $prefix . self::$col_begin[$this->style] . str_replace(self::$col_search[$this->style],self::$col_replace[$this->style],$row[$key]) . self::$col_end[$this->style]  ;

            } else {
                $vals[] = '';
            }
        }
        if ( $this->style == 'json' ) {
            return self::$row_begin[$this->style] . json_encode($vals) . self::$row_end[$this->style];
        } else {
            return self::$row_begin[$this->style] . implode(self::$col_separate[$this->style],$vals) . self::$row_end[$this->style];
        }
    }

    protected function canView() {
        return true;
    }

    protected function processResultRowXML($row, $row_num) {
        $out = " <dataRow id='$row_num'>\n";
        $vals = array();
        foreach ($this->cols as $key) {            
            $lkey = strtolower($key);
            if (array_key_exists($lkey,$row)) {
                $val = $row[$lkey];
            } else {
                $val = '';
            }
            $out .= "  <dataElement name='$key'>" . htmlentities($val) . "</dataElement>\n";
        }
        return $out . " </dataRow>\n";
    }


    protected function getXMLMetaData($headers) {
        $user = new I2CE_User();
        $sender_role = $user->getRole();
        $sender_username = $user->username();
        $sender_name =   $user->firstname . ' ' . $user->lastname;;
        $sender_email = $user->email;
        $data_elements = '';
        $report = addslashes($this->view );
        $name = $report;
        $this->config->setIfIsSet($name,'display_name');
        $desc = $name . ' report';
        $this->config->setIfIsSet($desc,'description');
        $when = date("c");
        array_shift($headers); //get rid of the # header                                     

        

        foreach ($headers as $key=>$header) {  
            $data_elements .= "   <elemDesc id='$key'>
    <name>$header</name>
   </elemDesc>\n";
        } 
        return "<ihrisReport id='$report'> 
 <reportDetails>
  <name>$name</name>
  <description>$desc</description>
  <whenGenerated>$when</whenGenerated>
  <sender>
   <user>$sender_username</user>
   <name>$sender_name</name>
   <role>$sender_role</role>
   <email>$sender_email</email>   
  </sender>
  <dataElements>
$data_elements  </dataElements>
 </reportDetails>
 <reportData>
";
        return $out;
    }

   
    protected $cols;


    protected $avail_xslt = null;

    protected function getAvailableXSLT() {
        if (is_array($this->avail_xslt)) {
            return $this->avail_xslt;
        }
        $this->avail_xslt = array();
        if ( class_exists('XSLTProcessor',false) && array_key_exists('xslts',$this->defaultOptions) && is_array($this->defaultOptions['xslts'])) {
            foreach ($this->defaultOptions['xslts'] as $xslt=>$data) {
                if (!is_array($data) || !array_key_exists('definition',$data) || strlen(trim($data['definition'])) == 0 ){
                    continue;
                }
                $this->avail_xslt[$xslt] = $data;
            }
        }
        return $this->avail_xslt;
    }


    /**
     * Adds any controls for this display to the content node.
     * @param DOMNode $contentNode 
     * @returns boolean;
     */
    protected function displayReportControl($contentNode) {
        parent::displayReportControl($contentNode);
        if (function_exists('gzencode')) {
            $this->template->setDisplayDataImmediate('has_gz',1);
        } else {
            $this->template->setDisplayDataImmediate('has_gz',0);
        }
        if (function_exists('bzcompress')) {
            $this->template->setDisplayDataImmediate('has_bz2',1);
        } else {
            $this->template->setDisplayDataImmediate('has_bz2',0);
        }
        if (@class_exists('ZipArchive',false)) {
            $this->template->setDisplayDataImmediate('has_zip',1);
        } else {
            $this->template->setDisplayDataImmediate('has_zip',0);
        }


        $xslts = $this->getAvailableXSLT();
        if (count($xslts) == 0) {
            return true;
        }
        if ( ! ($selNode = $this->template->getElementById('export_style',$contentNode)) instanceof DOMNode) {
            I2CE::raiseError("Bad");
        }
        foreach ($xslts as $xslt => $data) {
            $optionNode = $this->template->createElement('option',array('value'=>'xml:' . $xslt),"XML: " . $xslt);
            $this->template->appendNode($optionNode,$selNode);
        }
        return true;
    }
    /**
     * Generate the the exported report
     * @returns string
     */
    public function generateExport() {
        I2CE::longExecution( array( "max_execution_time" => 1800 ) );
        $style = $this->getStyle();
        $formfields = $this->getDisplayFieldsData();
        $headers = array('#');
        foreach ($formfields as $formfield=>$data) {
            if (!$data) {
                continue;
            }
            $headers[$formfield] = $data['header'];
        }
        $this->cols = array_keys($headers);
        switch ($style) {
        case 'xml':
            $post = " </reportData>\n</ihrisReport>\n";
            array_shift($this->cols);
            $pre =  $this->getXMLMetaData($headers);
            break;
        case 'tab':
            $post = '';       
            $pre = "\xEF\xBB\xBF" . $this->processResultRowArray($headers);
            break;
        case 'csv':
            $post = '';
            $pre = "\xEF\xBB\xBF" . $this->processResultRowArray($headers);
            break;
        case 'rawjson' :
            $post= '}';
            unset($headers[0]);
            $pre = '{"headers":' .json_encode($headers). ',"data":';
            break;
        case 'json' :
            $post= ']';
            unset($headers[0]);
            $pre = '[' .json_encode(array_values($headers));
            break;
        default: //html snippet
            $name = addslashes(str_replace(array(' ',"\n","\t") , array('_',' ','_'),$this->config->display_name));
            $pre =  "<table id='" .  addslashes($this->view )." name='$name'>\n";
            $post = '</table>';  
            $pre .= $this->processResultRowArray($headers);
            break; 
        }
        $data = $this->getResults();
        $out = $this->processResults($data,$resultsNode=null);
        $out = $pre  . $out . $post;
        if ($style == 'xml' && $this->transform ) {
            $xmlDoc = new DOMDocument();
            if (!@$xmlDoc->loadXML($out)) {
                I2CE::raiseError("Could not load source document");
                return $out;
            }
            $xslDoc = new DOMDocument();
            if (!array_key_exists('xslts',$this->defaultOptions)
                || !is_array($this->defaultOptions['xslts'])
                || !@$xslDoc->loadXML($this->defaultOptions['xslts'][$this->transform]['definition'])) {
                I2CE::raiseError("Could not load transform");
                return false;
            }
            $proc = new XSLTProcessor();
            if (!  @$proc->importStylesheet($xslDoc)) {
                I2CE::raiseError("Could not import style sheet");
                return false;
            }
            if ( ($out =   @$proc->transformToXML($xmlDoc)) === false) {
                I2CE::raiseError("Could not transform accoring to xsl");
                return false;
            }
        }
        switch ($this->compression) {
        case 'bz2':
            return bzcompress($out);
        case 'gz':
            return gzencode($out,9);
        case 'zip':
            if (!@class_exists('ZipArchive',false)) {
                I2CE::raiseError("zip not present");
                $this->compression = false;
                break;
            }
            $zip = new ZipArchive();            
            $temp_file = tempnam(sys_get_temp_dir(), 'EXPORT_ZIP');
            if ($zip->open($temp_file)  !== true) {
                I2CE::raiseError("Could not ceaete zip on $temp_file");
                $this->compression = false;
                break;
            }                
            $filename =  $this->getFileName(null, false);
            $zip->addFromString($filename, $out);
            $zip->close();
            $out= file_get_contents($temp_file);
            break;
        default:
            break;
        }
        return $out;
    }

    protected $style = null;
    protected $transform = false;
    protected $compression = false;

    protected function getStyle() {
        if ($this->style) {
            return $this->style;
        }
        if (!array_key_exists('export_compression',$this->defaultOptions)) {
            $this->compression = false;
        } else {
            switch(strtolower($this->defaultOptions['export_compression'])) {
            case 'bz2':
                if (function_exists('bzcompress')) {
                    $this->compression = 'bz2';                
                } else {
                    $this->compression = false;
                }
                break;
            case 'gz':
                if (function_exists('gzencode')) {
                    $this->compression = 'gz';       
                } else {
                    $this->compression = false;       
                }         
                break;
            case 'zip':
                $this->compression = 'zip';
                break;
            default:
                $this->compression = false;
                break;
            }
        }
        list($this->style, $this->transform) = array_pad(explode(':',strtolower($this->defaultOptions['export_style']),2),2,'');        
        if ( !$this->style || !(array_key_exists($this->style, self::$col_search) || $this->style == 'xml')) {
            $this->style = 'csv';
        }
        if (!in_array($this->style,array('csv','csvxls','tab','xml','json','rawjson'))) {
            $this->style ='html';
        }
        if ($this->transform) {
            $avail = $this->getAvailableXSLT();
            if (!array_key_exists($this->transform,$avail)) {
                $this->transform = false;
            }
        }
        return $this->style;
    }
    public function setTransform($transform) {
        $this->transform = $transform;
    }

    public function setStyle($style) {
        $this->style = $style;
    }

    protected function getTransform() {
        $this->getStyle(); //make sure things are setup        
        return $this->transform;
    }


    protected function getCompression() {
        $this->getStyle(); //make sure things are setup        
        return $this->compression;
    }

    public function getFileName($style = null, $compression = null) {
        if ($style === null) {
            $style = $this->getStyle();
        }
        if ($this->transformsToHTML()) {
            $style = 'html';
        }
        $filename = addslashes(str_replace(array(' ',"\n","\t") , array('_',' ','_'),$this->config->display_name)) . '_' . date("d_m_Y") . '.' . self::$file_suffix[$style];
        if ($compression === null) {
            $compression = $this->compression;
        }
        if ( $compression) {
            $filename .= '.'  . $compression;
        }
        return $filename;
    }

    protected function transformsToHTML() {
        $style = $this->getStyle();        
        return ($style ==='xml' && !($this->compression) && $this->transform && preg_match('/<xsl:output\s+method="html"/',$this->defaultOptions['xslts'][$this->transform]['definition']));
    }

    /**
     * Return the content type for this file.
     * @param boolean $include_html
     * @return string
     */
    public function getContentType( $include_html=false ) {
        $style = $this->getStyle();
        if  ($this->transformsToHTML()) {
            $style = 'html';
        }
        switch ($this->compression) {
        case 'zip':
            return "application/zip; charset=binary";
            break;
        case 'bz2':
            return "application/x-bzip";
            break;
        case 'gz':
            return "application/x-gzip";
            break;
            //gzip is an encoding and not a mime-type so we fall through here.
        default:
            switch ($style) {
            case 'tab':
                if (array_key_exists( 'HTTP_USER_AGENT', $_SERVER) && preg_match('/\s+MSIE\s+\d\.\d;/',$_SERVER['HTTP_USER_AGENT'])) {
                    return "application/vnd.ms-excel";
                } else {
                    return "text/csv; charset=UTF-8";
                }
                break;
            case 'csv':
                if (array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) && preg_match('/\s+MSIE\s+\d\.\d;/',$_SERVER['HTTP_USER_AGENT'])) {
                    return "application/vnd.ms-excel";
                } else{
                    return "text/csv; charset=UTF-8";
                } 
                break;
            case 'xml':            
                return "text/xml; charset=UTF-8";
                break;
            case 'html' :
                if ( $include_html ) {
                    return "text/html; charset=UTF-8";
                }
                break;
            }
            break;
        }
        return false;
    }
    /**
     * Display the report
     * @param DOMNode $contentNode The DOM node we wish to display into
     * @param boolean $processResults Defaults to true meaning we run through the results
     * @param mixed $controls.  If null (default), we display all the report controsl.  If string or an array of string, we only display the indicated controls
     * @returns boolean. true on sucess
     */
    public function display($contentNode,$processResults=true, $controls = null) {        
        $this->saveDefaultView();
        $style = $this->getStyle();
        if ( ($errors  = I2CE_Dumper::cleanlyEndOutputBuffers())) {
            I2CE::raiseError("Got errors:\n$errors");
        }
        if  ($this->transformsToHTML()) {
            $style = 'html';
        }
        $filename = $this->getFileName();
        $encoding = '';
        if (array_key_exists('HTTP_HOST',$_SERVER)) {
            header("Content-disposition: attachment; filename=\"$filename\"");
            $contentType = $this->getContentType();
            if ( $contentType ) {
                header("Content-type: $contentType");
            } else {
                //html snippet
                foreach( $this->template->getHeaders() as $header) {
                    header($header);
                }
            }
            // Flush the headers so the download box appears fast
            flush();
        }
        echo   $this->generateExport() ;
        flush();
        exit; // we want to make sure there is no further output or that the $this->page->display() method is not called
    }






}
# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
