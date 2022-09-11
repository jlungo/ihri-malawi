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
 * The page to send feedback about the site.
 * @package I2CE
 * @subpackage Common
 * @access public
 * @author Ally Shaban <ashabani@intrahealth.org>
 * @since v2.0.0
 * @version v2.0.0
 */

/**
 * The page class for displaying the feedback form.
 * @package I2CE
 * @subpackage Common
 * @access public
 */
 
require_once("import_base.php");
class I2CE_PageImport extends I2CE_Page 
{
    protected $headers;
    protected $mapped_data;
    protected $data;
    protected $user;    
    protected $db;
    protected $row = 1;
    protected $ff; 
    protected $processRows =null;
    protected $dataFile;
    protected $file;
        
    /**
     * Perform the main actions of the page.
     * @global array
     */	
    protected function action() 
    {
        $i2ce_config = I2CE::getConfig()->I2CE;
        parent::action();
        $valid = true;
        $err_msg = "";
        if ( $this->isPost() )
        {        	
        	move_uploaded_file($_FILES["hrhis"]["tmp_name"],"/var/lib/iHRIS/ihris-manage-malawi/4.1/modules/import/lib/uploads/POPSM.csv");
         $this->manual_constructor();
        }
    }
    
    public function manual_constructor()
    {
    	    $file="/var/lib/iHRIS/ihris-manage-malawi/4.1/modules/import/lib/uploads/POPSM.csv";
      $this->file = $file;
        $file_ext = strtolower(substr($file, strrpos($file, '.') + 1));
        if ($file_ext == 'csv') {
            //although CSV can be processed by PHPExcel, we keep this separate in case PHPExcel cannot be installed, we can still export the file as a CSV and process it
            $this->dataFile = new CSVDataFile($file);
        } else {
            $this->dataFile = new ExcelDataFile($file);
        }     
        $this->user = new I2CE_User();
        $this->db= MDB2::singleton();
        $this->ff = I2CE_FormFactory::instance();
        $this->mapHeaders();
        $this->initBadFile(); 
      $this->loadCountryLookup();
		$this->designation= I2CE_FormStorage::listFields('job',array('code','id','title'));
		$this->districts= I2CE_FormStorage::listFields('district',array('name','id'));
      $this->department= I2CE_FormStorage::listFields('department',array('id','sub_vote'));
      $this->employmentid= I2CE_FormStorage::listFields('employmentid',array('id','check_num','parent'));
      $this->run();
      $this->template->addFile("import_success.html");
    }
    
    protected function mapData() 
    {
        $mapped_data = $this->mapData1();                
        return $mapped_data;
    }
    
        public function getCurrentRow() {
        return $this->row;
    }
    
       public function hasDataRow() {
        return $this->dataFile->hasDataRow();
    }
    
        protected $success = 0;

    protected $blank_lines = 0;
    public function run() 
    {
        $this->success = 0;
        while ( $this->hasDataRow()) {
            if ($this->processRow()) {
                $this->success++;
            }
        }
    }
    
    public function getStats() 
    {
        return array('success'=>$this->success,'attempts'=>($this->row -1)); //this may be off by one.
        $row = $processor->getCurrentRow();
    }
    
        public function processRow() 
    {
        if (!$this->dataFile->hasDataRow()) {
            return false;
        }
        $this->data = $this->dataFile->getDataRow();
        if (!is_array($this->data)) {
            $this->blank_lines++;
            return false;
        }
        $is_blank = true;
        foreach ($this->data as $cell) {
            if (is_string($cell) && strlen(trim($cell)) != 0) {
                $is_blank = false;
                $this->blank_lines = 0;
                break;
            }
        }
        if ($is_blank) {
            $this->blank_lines++;
        } 
        $this->row++;
        if ( ! ($this->mapped_data = $this->mapData1())) {
            return false;
        }
        if ( $this->_processRow()) {            
	    $_SESSION["processed"]="yes";   
            return true;
        } else {
            return false;
        }
    }
    
        protected function save($obj,$cleanup = true) 
    {
        if (!$obj instanceof I2CE_Form) {
            return false;
        }
            $obj->save($this->user);    
            $id = $obj->getID();
            if ($cleanup) {
                $obj->cleanup();
            }
            return $id;
        
    }
    
        protected $header_map;
    protected function mapHeaders() {
        $this->headers = $this->dataFile->getHeaders();
        foreach ($this->headers as &$header) {
            $header = strtolower(trim($header));
        }
        unset($header);
        $this->header_map = array();
        $expected_headers = $this->getExpectedHeaders();
        foreach ($expected_headers as $expected_header_ref => $expected_header) {
            if (($header_col = array_search(strtolower($expected_header),$this->headers)) === false) {
                I2CE::raiseMessage("Could not find $expected_header in the following headers:\n\t" . implode(" ", $expected_headers). "\nFull list of found headers is:\n\t" . implode(" ", $this->headers));
                die();
            }
            $this->header_map[$expected_header_ref] = $header_col;
        }
    }
    
    protected function mapData1() 
    {
        $mapped_data = array();
        foreach ($this->header_map as $header_ref=>$header_col) {
            if (!array_key_exists($header_col,$this->data)) {
                $mapped_data[$header_ref] = false;
            } else {
                $mapped_data[$header_ref] = $this->data[$header_col];
            }
        }
        return $mapped_data;
    }
    
protected $bad_headers;
    protected $bad_fp;        
    protected $bad_file_name;
    protected function initBadFile() {
        $info = pathinfo($this->file);
        $bad_fp =false;
        $this->bad_file_name = dirname($this->file) . DIRECTORY_SEPARATOR . basename($this->file,'.'.$info['extension']) . '.bad_' .date('d-m-Y_G:i') .'.csv';        
        $this->bad_headers = $this->headers;
        $this->bad_headers[] = "Row In " . basename($this->file);
        $this->bad_headers[] = "Reasons for failure";
    }



    function addBadRecord($reason) {
        if (!is_resource($this->bad_fp)) {
            $this->bad_fp = fopen($this->bad_file_name,"w");
            if (!is_resource($this->bad_fp)) {
                I2CE::raiseMessage("Could not open $this->bad_file_name for writing.", E_USER_ERROR);
                die();
            }        
            fputcsv($this->bad_fp, $this->bad_headers);
        }
        I2CE::raiseMessage("Skipping processing of row $this->row: $reason");
        $raw_data = $this->data;
        $raw_data[] = $this->row;
        $raw_data[] = $reason;
        fputcsv($this->bad_fp, $raw_data);
    }    
    
        public static function raw2hex($s) {       
        $op = '';
        for($i = 0; $i < strlen($s); $i++){
            $op .= str_pad(dechex(ord($s[$i])),2,"0",STR_PAD_LEFT);
        }
        return $op;
    }

    protected function getDate($date) {
        //first check the date e.g 16/05/2011
        $matches = array();
        if (is_numeric($date) && class_exists('PHPExcel',false)) {
            //in case we are reading it from excel which returns 40777 instead of 22/08/2011 for example
            $date = PHPExcel_Style_NumberFormat::toFormattedString($date, "DD/MM/YYYY");
        }
        if (!preg_match('/^([0-9]+)\\/([0-9]+)\\/([0-9]+)$/',trim($date),$matches)) {
            $this->addBadRecord("Bad date format [$date]");
            return false;
        }
        list($date,$day,$month,$year) = $matches;
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12 || $year < 1900 || $year > 2100) {
            $this->addBadRecord("Invalid date");
            return false;
        }
        return I2CE_Date::getDate($day,$month,$year);
    }

    
    

    protected function getExpectedHeaders() 
    {
        return  array
        (
		'CHECK_NO'=>'CHECK_NO',
		'district'=>'VOTE_DESCR',
		'omang'=>'CHECK_NO',
		'DEPT_CODE'=>'DEPT_CODE',
		'JOBCODE'=>'JOBCODE',
		'JOBCODEDESCR'=>'JOBCODEDESCR',
		'LOCATCODE'=>'LOCATCODE',
		'LOCATION'=>'WORKDISTR',
		'FIRST_NAME'=>'FIRST_NAME',
		'surname'=>'LAST_NAME',
		'othername'=>'MIDDLE_NAME',
		'BIRTHDATE'=>'BIRTHDATE',
		'DOMCODE'=>'DOMCODE',
		'DOMDESCR'=>'DOMDESCR',
		'PF_NO'=>'FILE_NO',
		//'PROMOTION'=>'PROMOTION',
		'JOBCODE'=>'JOBCODE',
		'JOBCODEDESCR'=>'JOBCODEDESCR',
		'DATE_HIRED'=>'DATE_HIRED',
		'SALARY_GRADE'=>'SCHEDULE',		
		'GRADE'=>'PAYGRADE',
		'PAYRATE'=>'PAYRATE',
		'SEX'=>'SEX',
		'MARITAL'=>'MARITAL',
		'NO_CHILD'=>'NO_CHILD',
		'CONF_DATE'=>'ADJ_HIRE_DATE'		       
          );
    }
       protected static $required_cols_by_transaction = array(
        'NE'=>array('FIRST_NAME','surname')
        );

    protected static $infinium_termination_map = array(
        'DISMI'=>'pos_change_reason|3', //dismisal
        'DEATH'=>'pos_change_reason|1' //death
        // 'CONTE'=>'', //terminaion of contract
        // 'COMRE'=>'', //Compulory retirement
        // 'VOLRE'=>'', //volunary retirement
        // 'EXTTR'=>'', //transfer to another ministry
        // 'PURGE'=>'', //code to purge employees  --- SKIP!
        // 'ENDCO'=>'' //end of contract
        );
    protected static $infinium_stat_code_map = array(
        'TEMPF'=>'position_type|8',//Temp full time -- double check
        'PERM'=>'position_type|1',//Permanent and penshionable
        'FTCON'=>'position_type|2',//fixed term contract --douvle check
        'PROB'=>'position_type|6' //probabionary
        //nothing for acting.
        );


    protected $effective_date;
    protected function _processRow() 
    {
        if (!$this->verifyData()) 
        {
            return false;
        }
        
        //Check to see if an employee exist in the database.
        foreach ($this->employmentid as $id=>&$datas)
        {
        if($datas["check_num"]==$this->mapped_data["CHECK_NO"])
        {
        $this->empl_status="existing_employee";
        $this->update_employee_info($datas["parent"]);
        break;
        }
        }     
        $success = false;
   if($this->empl_status!="existing_employee")
   {   
   if (!$personObj = $this->createNewPerson()) 
	{
            break;
   }                     
            $success = $this->setNewPosition($personObj);            
            $personObj->cleanup();
        return $success;
   }
   else
   $this->empl_status="";
   }


    protected $existing_codes;
    protected function loadCountryLookup() {
        $this->existing_codes= I2CE_FormStorage::listFields('country',array('alpha_two'));
        foreach ($this->existing_codes as $id=>&$data) {
            $data = $data['alpha_two'];
        }
        unset($data);
    }



    function verifyData()
    {
        
        $missing_cols = array();
        foreach (self::$required_cols_by_transaction["NE"] as $required_col) {
            if ($this->mapped_data[$required_col] === false || (is_string($this->mapped_data[$required_col]) && strlen($this->mapped_data[$required_col]) == 0)) {
                $missing_cols[] = $required_col;
            }
        }
        if (count($missing_cols) > 0) {
            $this->addBadRecord("Missing required columns " . implode(" ",$missing_cols));
		$codearr=explode("-",$this->mapped_data["CHECK_NO"]);
		if(count($codearr)>1)
		{
		$_SESSION["depcode"]=trim($codearr[0]);
		}    
            return false;
        }
        return true;
    }

protected function loadDistricts() 
{
	$domicile=trim($this->mapped_data['DOMDESCR']);        
        foreach ($this->districts as $id=>&$data) 
        {
        $domarray=explode(" ",$domicile);
        $districtarray=explode(" ",$data["name"]);
            if (count($domarray)==1 && $domicile==$data["name"])
            {
            return $data["id"];
            break;
            }
            
            elseif (count($domarray)==2 && count($districtarray)>1)
            {
            if ($domarray[1]=="Urban" and ($districtarray[1]=="Municipal" or $districtarray[1]=="Urban") and $domarray[0]==$districtarray[0])
            {
            return $data["id"];
            break;
            }    
             
            elseif($domarray[1]=="Rural" and ($districtarray[1]=="District" or $districtarray[1]=="Rural") and $domarray[0]==$districtarray[0])
            {
            return $data["id"];
            break;
            }                                  
            }
            
            else if(count($domarray)==2 and count($districtarray)==1 and $domarray[0]==$districtarray[0])
            {
            return $data["id"];
            break;
            }
            else
            continue;                        
        }        
}
    
    protected function load_designation($from)
    {
    $jobcode=$this->mapped_data["JOBCODE"];
    $jobcodedescr=$this->mapped_data["JOBCODEDESCR"];    
    
    foreach ($this->designation as $id=>&$data) 
        {
        if (trim($jobcode)==trim($data['code']))
        {      
        if($from=='designation')  
        return $data['title'];
        else
        return $data['id'];
        break;
        }
        
        else if($jobcodedescr==$data['title'])
        {        
         if($from=='designation')  
        return $data['title'];
        else
        return $data['id'];
        break;
        }
        }
    }
    
    protected function load_department()
    {
    $depcode=$this->mapped_data["DEPT_CODE"];   
    
    foreach ($this->department as $id=>$data) 
        {   
        if (trim($depcode)==trim($data['sub_vote']))
        {    
        return $data['id'];
        break;
        }
        }
     }
      
      function convert_date($from)
      {
      		if($from=="hired" or $from=="conf")
      		{
		if($from=="conf")
		$newdate=explode("-",$this->mapped_data['CONF_DATE']);
		else if($from=="hired")
      		$newdate=explode("-",$this->mapped_data['DATE_HIRED']); 
		if($newdate[2] >12)       
		$datehired="19".$newdate[2]."-".$newdate[1]."-".$newdate[0];

		else if($newdate[2] <=12)       
		$datehired="20".$newdate[2]."-".$newdate[1]."-".$newdate[0];

		$datehired_timestamp = strtotime($datehired);
		$datehired = date('Y-m-d', $datehired_timestamp);
		return $datehired;
      		}
      		
      		else if($from=="end" or $from=="birth")
      		{
      		$newdate=explode("-",$this->mapped_data['BIRTHDATE']);
		if($from=="birth")
		{
		$datebirth="19".$newdate[2]."-".$newdate[1]."-".$newdate[0];
		$datebirth_timestamp = strtotime($datebirth);								 
		$datebirth = date('Y-m-d h:m:s', $datebirth_timestamp);	
		return $datebirth;
		exit;
		}	
      		$newdate[2]="19".$newdate[2]+60;
      	  	$dateretire=$newdate[2]."-".$newdate[1]."-".$newdate[0];  								
		$dateretire_timestamp = strtotime($dateretire);								 
		$dateretire = date('Y-m-d h:m:s', $dateretire_timestamp);		    
		return $dateretire;   
      		}	
      }

	function get_marital_id($marital)
		{
			if($marital=="M")
			return 2;
			else if($marital=="S")
			return 3;
			else if($marital=="D")
			return 1;
			else if($marital=="W")
			return 4;
		}	
    
    
function create_person_obj($person)
{
        $personObj = $this->ff->createForm($person);
        if (!$personObj instanceof iHRIS_Person) 
        {
            return false;
        }
        $personObj->populate();
        return $personObj;	
}

function create_person_pos_obj($personObj)
{	     
	     $where = array(
                'operator'=>'FIELD_LIMIT',
                'field'=>'end_date',
                'style'=>'null'
                       );
                       
		  $per_pos_id = I2CE_FormStorage::search('person_position', $personObj->getNameId(),$where,'-start_date',1);
        if (!$per_pos_id) {
            return false;
        }                
        $persPosObj = I2CE_FormFactory::instance()->createContainer('person_position'.'|'.$per_pos_id);
        if (!$persPosObj instanceof iHRIS_PersonPosition) {
            return false;
        }
        $persPosObj->populate();
        return $persPosObj;	
}
    
function update_employee_info($person)
{
$personObj=$this->create_person_obj($person);
$existingPersonPosObj = $this->create_person_pos_obj($personObj);
$existingPosObj = $this->ff->createContainer( $existingPersonPosObj->position);
$existingPosObj->populate();
$salary=number_format($this->mapped_data["PAYRATE"]); 
$existingPosObj->getField('proposed_salary')->setFromPost('currency|1='.$salary);
$this->save($existingPosObj);
}
    
    function createNewPerson() {
    	        //for a NE we create the person
	$fname=ucwords(strtolower($this->mapped_data['FIRST_NAME']));
	$surname=ucwords(strtolower($this->mapped_data['surname']));	
	$othername=ucwords(strtolower($this->mapped_data['othername']));	

        $personObj = $this->ff->createContainer('person');
        $personObj->firstname = trim($fname);
        $personObj->surname = trim($surname);
        $personObj->othername = trim($othername);

    
    
        //Adding Nationality 
        if (($country_id = array_search('TZ',$this->existing_codes)) !== false) 
        {        
            $personObj->nationality = array('country',$country_id);
        }
        
        //Adding Place Of Domicile
        $domid=$this->loadDistricts();
        $personObj->residence=array('district',$domid);
	$personID = $this->save($personObj,false);
	$parent_id='person|'.$personID;
	$_SESSION["parent_id"]=$parent_id;
	//Adding Confirmation Date
	$confObj = $this->ff->createContainer('confirmationtoservice');
	$confObj->getField('parent')->setValue($parent_id);
	$confObj->getField('confirmationdate')->setFromPost($this->convert_date("conf"));
	$this->save($confObj);

	//Adding Demographic info
	if($this->mapped_data["NO_CHILD"]>4)
	$dependents=4;
	else
	$dependents=$this->mapped_data["NO_CHILD"];
	
	$domObj = $this->ff->createContainer('demographic');
	$domObj->getField('parent')->setValue($parent_id);	        
	$domObj->getField('birth_date')->setFromPost($this->convert_date("birth"));	
	$domObj->gender=array("gender",$this->mapped_data["SEX"]);
	$domObj->marital_status=array("marital_status",$this->get_marital_id($this->mapped_data["MARITAL"]));	
	$domObj->dependents=$dependents;	
	$this->save($domObj);

        //Adding Employment Identification
		$idObj = $this->ff->createContainer('employmentid');
        $check_num=$this->mapped_data['CHECK_NO'];
        $pf=$this->mapped_data['PF_NO'];        
        $idObj->getField('check_num')->setValue($check_num);
        $idObj->getField('pf_num')->setValue($pf);

        $idObj->getField('parent')->setValue($parent_id);
        $this->save($idObj);
        return $personObj;
    }   



    function setNewPosition($personObj) 
    {
	$postid = $this->mapped_data['PF_NO'];        
	$parent_id=$_SESSION["parent_id"];
	$salary=number_format($this->mapped_data["PAYRATE"]);

        //create new forms
        $newPosObj = $this->ff->createContainer('position');
        $newPersonPosObj = $this->ff->createContainer('person_position');
        $newSalaryObj = $this->ff->createContainer('salary');

        //creating position
        $newPosObj->status = array('position_status','closed');
        $newPosObj->code = $this->mapped_data['PF_NO'];
        $newPosObj->title = $this->load_designation('designation');
        $newPosObj->description = $this->load_designation('designation');
        $newPosObj->pos_type= array('position_type','5');        
        $newPosObj->department= array('department',$this->load_department()); 
        $newPosObj->job = array('job',$this->load_designation('job'));
        $newPosObj->getField('posted_date')->setFromPost(date("Y-m-d H:i:s"));       
        $newPosObj->getField('proposed_hiring_date')->setFromPost($this->convert_date("hired"));
        //$newPosObj->getField('proposed_end_date')->setFromPost($this->convert_date("end"));
	$newPosObj->getField('proposed_salary')->setFromPost('currency|1='.$salary);
        $newPosObj->getField('source')->setFromPost('salary_source|1');
        $newPosId = $this->save($newPosObj);
        
	//Setting position        
        $newPersonPosObj->getField('start_date')->setFromPost($this->convert_date('hired'));
        $newPersonPosObj->position =array('position',$newPosId);        
        $newPersonPosObj->getField('parent')->setValue($parent_id);
        $newPersonPosId = $this->save($newPersonPosObj);    

	$newSalaryObj->getField('salary')->setFromPost('currency|1='.$salary);
	$newSalaryObj->getField('start_date')->setFromPost($this->convert_date("hired"));    
	$newSalaryObj->getField('parent')->setValue('person_position|'.$newPersonPosId);
	$newSalaryId = $this->save($newSalaryObj);
        return true;
    }

}


        	//if ( true ) {
        		//$this->template->addFile( "import_success.html" );
        	//} else {
        		//$this->template->addFile( "import_fail.html" );
        	//}


# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:
