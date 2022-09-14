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
class I2CE_PageImport extends I2CE_Page {
        
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
        	move_uploaded_file($_FILES["hrhis"]["tmp_name"],"uploads/test.zip");      	        	
     $zip = new ZipArchive;
     $res = $zip->open('uploads/test.zip');
         $zip->extractTo('uploads/');
         $zip->close();
     
        $this->user = new I2CE_User();
        $this->db= MDB2::singleton();
        $this->ff = I2CE_FormFactory::instance();
                            
      $this->loadCountryLookup();		
		$this->designation= I2CE_FormStorage::listFields('job',array('code','id','title'));		
		$this->districts= I2CE_FormStorage::listFields('district',array('name','id'));		
      $this->department= I2CE_FormStorage::listFields('department',array('id','sub_vote'));      
      $this->employmentid= I2CE_FormStorage::listFields('employmentid',array('id','check_num'));      
		$this->run();	
        }
    }    
    
        public function run() 
{
        $this->success = 0;
        $tmp_fields = json_decode( file_get_contents("uploads/field.json" ) );
		  $tmp_field_opts = json_decode( file_get_contents( "uploads/fieldOption.json" ));
		  $tmp_org_units = json_decode( file_get_contents("uploads/organizationUnit.json" ));
        $values = json_decode( file_get_contents("uploads/values.json" ));        

		  $fields = array();
		  foreach( $tmp_fields as $field_data ) {
    	  $fields[$field_data->id] = $field_data->name;
}

$field_opts = array();
foreach( $tmp_field_opts as $opt_data ) {
    //$field_opts[ $opt_data->field_name ][ $opt_data[0]->id ] = $opt_data[0]->value;
    foreach( $opt_data as $entry ) {
        if ( $entry instanceof stdClass ) {
            $field_opts[ $opt_data->field_name ][ $entry->id ] = $entry->value;
        }
    }
}
foreach( $field_opts as $field => $data ) {
    foreach( $data as $id => $value ) {
        //echo "$field,$id,$value\n";
    }
}

$org_units = array();
foreach( $tmp_org_units as $unit_data ) {
    $org_units[ $unit_data->shortname ] = $unit_data->longname;
}
        foreach( $values as $value ) {
    $key = 0;
    $record = $value->$key;
    $output = array();
    $output['facility'] = $org_units[ $value->orgunit_name ] . "\n";
    //echo "Form name = " . $value->form_name . "\n";
    foreach( $record->value as $field => $data ) 
    {
        $field_name = $fields[$field];
        if ( $data instanceof stdClass ) {
            if ( $data->date ) {
                $output[$field_name] = $data->date;
                //echo "$field_name = " . $data->date . "\n";
            } else {
                echo "Unknown class for $field_name\n";
                //print_r( $data );
                die();
            }
        } elseif ( array_key_exists( $field_name, $field_opts ) && array_key_exists( $data, $field_opts[$field_name] ) ) {
            //echo "$field_name = " . $field_opts[$field_name][$data] . " ($data)\n";
            $output[$field_name] = $field_opts[$field_name][$data];
        } else {
            //echo "$field_name = $data\n";
            $output[$field_name] = $data;
        }
        
    }

        $_SESSION["output"]=$output;
        $employee_exist=false;
        
        $existing_staff_info=array();
        //checking to see if an employee belongs to DED,RAS,TED or MD
        if($_SESSION["output"]["employer"]=="District Executive Director" || $_SESSION["output"]["employer"]=="Town Director" || $_SESSION["output"]["employer"]=="Municipal Director" || $_SESSION["output"]["employer"]=="Regional Administrative Secretary")
        {        
			
        }
        else 
        {
        $existing_staff_info[$_SESSION["output"]["check_no"]]=$_SESSION["output"]["firstname"].":".$_SESSION["output"]["surname"].":".$_SESSION["output"]["check_no"];       
        continue;
        }
        //check to see if an employee already existing in the database
        foreach ($this->employmentid as $id=>&$datas)
        {
		  if($datas["check_num"]==$_SESSION["output"]["check_no"])
		  {
		  $existing_staff_info[$_SESSION["output"]["check_no"]]=$_SESSION["output"]["firstname"].":".$_SESSION["output"]["surname"].":".$_SESSION["output"]["check_no"];
		  $employee_exist=true;
		  break;
		  }
        }
        if($employee_exist==true)
        {
        continue;
        }
            if ($this->processRow()) {
                $this->success++;                
            }
        }
        
    		  	
    		  	$counter=0;
    		  	foreach ($existing_staff_info as $staff)
    		  	{
    		  	$counter++;
		      $staffarray=explode(":",$staff);
               
		  		}
		  		$this->template->addFile( "import_fail.html" );
    }
    
    public function processRow() 
    {
        if ( $this->_processRow()) {  
       return true;
        } else {
            return false;
        }
    }

    public function setTestMode($testmode) {
        $this->testmode = $testmode;
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

    protected $effective_date;
    protected function _processRow() {    	  	       
        $success = false;

            if (!$personObj = $this->createNewPerson())
	{
            break;
        }                      
            $success = $this->setNewPosition($personObj);
            $personObj->cleanup();
        return $success;
    }


    protected $existing_codes;
    protected function loadCountryLookup() {
        $this->existing_codes= I2CE_FormStorage::listFields('country',array('alpha_two'));
        foreach ($this->existing_codes as $id=>&$data) {
            $data = $data['alpha_two'];
        }
        unset($data);
    }

protected function loadDistricts() {
	$domicile=trim($_SESSION["output"]["domicile"]);        
        foreach ($this->districts as $id=>&$data) 
        {
        $domarrayspace=explode(" ",$domicile);
        $domarraydash=explode("-",$domicile);
        $districtarray=explode(" ",$data["name"]);
            if ((count($domarrayspace)==1 && $domicile==$data["name"]) or (count($domarraydash)==1 && $domicile==$data["name"]))
            {
            return $data["id"];
            break;
            }
            
            elseif ((count($domarrayspace)==2 && count($districtarray)>1) or (count($domarraydash)==2 && count($districtarray)>1))
            {
            if (($domarrayspace[1]=="Urban" and ($districtarray[1]=="Municipal" or $districtarray[1]=="Urban") and $domarrayspace[0]==$districtarray[0]) or ($domarraydash[1]=="Urban" and ($districtarray[1]=="Municipal" or $districtarray[1]=="Urban") and $domarraydash[0]==$districtarray[0]))
            {
            return $data["id"];
            break;
            }    
             
            elseif(($domarrayspace[1]=="Rural" and ($districtarray[1]=="District" or $districtarray[1]=="Rural") and $domarrayspace[0]==$districtarray[0]) or ($domarraydash[1]=="Rural" and ($districtarray[1]=="District" or $districtarray[1]=="Rural") and $domarraydash[0]==$districtarray[0]))
            {
            return $data["id"];
            break;
            }                                  
            }
            
            else if((count($domarrayspace)==2 and count($districtarray)==1 and $domarrayspace[0]==$districtarray[0]) or (count($domarraydash)==2 and count($districtarray)==1 and $domarraydash[0]==$districtarray[0]))
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
    $jobcodedescr=trim($_SESSION["output"]["designation"]);    
    	if(strpos($jobcodedescr,"Laboratory"))
    	{
    		str_replace("Laboratory","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Laboratory)";
    		$jobcodedescr=trim($jobcodedescr);
    	}
    	else if(strpos($jobcodedescr,"Orthopedic"))
    	{
    		str_replace("Orthopedic","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Othopaedic)";
    		$jobcodedescr=trim($jobcodedescr);    		
    	}
    	else if(strpos($jobcodedescr,"Dental"))
    	{
			str_replace("Dental","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Dental)";
    		$jobcodedescr=trim($jobcodedescr);    		
    	}
    	else if(strpos($jobcodedescr,"Radiology"))
    	{
			str_replace("Radiology","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Radiology)";
    		$jobcodedescr=trim($jobcodedescr);     		
    	}
    	else if(strpos($jobcodedescr,"Optician"))
    	{
			str_replace("Optician","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Optician)";
    		$jobcodedescr=trim($jobcodedescr);    		
    	}
    	else if(strpos($jobcodedescr,"Pharmacy"))
    	{
			str_replace("Pharmacy","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Pharmacy)";
    		$jobcodedescr=trim($jobcodedescr);    		
    	}
    	else if(strpos($jobcodedescr,"Opthalamy"))
    	{
			str_replace("Opthalamy","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Opthalamy)";
    		$jobcodedescr=trim($jobcodedescr);    		
    	}
    	else if(strpos($jobcodedescr,"Radiography"))
    	{
			str_replace("Radiography","",$jobcodedescr);
    		$jobcodedescr=$jobcodedescr." (Radiography)";
    		$jobcodedescr=trim($jobcodedescr);    		
    	}    	    	    	
    
    foreach ($this->designation as $id=>&$data) 
        {
        if($jobcodedescr==$data['title'])
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
		$newdate=explode("-",$_SESSION["output"]["confirmation_date"]);
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
			if($marital=="Married")
			return 2;
			else if($marital=="Single")
			return 3;
			else if($marital=="Divorced" or $marital=="Separated")
			return 1;
			else if($marital=="Widow" or $marital=="Widower")
			return 4;
		}	

    function createNewPerson() {
    	$output=$_SESSION["output"];
    	        //for a NE we create the person
	$fname=ucwords(strtolower($output["firstname"]));
	$surname=ucwords(strtolower($output["surname"]));	
	$othername=ucwords(strtolower($output["middlename"]));	

        $personObj = $this->ff->createContainer('person');
        $personObj->firstname = trim($fname);
        $personObj->surname = trim($surname);
        $personObj->othername = trim($othername);
            //Getting highest education level
        if($_SESSION["output"]["edu_evel"]=="Postgraduate Diploma")
        $edu_level="degree|7";
        else if($_SESSION["output"]["edu_evel"]=="PhD")
        $edu_level="degree|10";
        else if($_SESSION["output"]["edu_evel"]=="Secondary School")
        $edu_level="degree|2";
        else if($_SESSION["output"]["edu_evel"]=="Certificate")
        $edu_level="degree|4";
        else if($_SESSION["output"]["edu_evel"]=="Diploma")
        $edu_level="degree|5";
        else if($_SESSION["output"]["edu_evel"]=="Masters Degree")
        $edu_level="degree|9";
        else if($_SESSION["output"]["edu_evel"]=="Advance Diploma")
        $edu_level="degree|6";
        else if($_SESSION["output"]["edu_evel"]=="Advance Secondary School")
        $edu_level="degree|3";
        else if($_SESSION["output"]["edu_evel"]=="Primary School")
        $edu_level="degree|1";
        else if($_SESSION["output"]["edu_evel"]=="Bachelor Degree")
        $edu_level="degree|8";
        else
        $edu_level="degree|8";
        
        $personObj->getField("high_edu_lev")->setFromPost($edu_level);
    
    
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
	
	//Adding Next Of Kin
	$kinObj = $this->ff->createContainer('nextofkin');
	$kinObj->getField('name')->setFromPost($_SESSION["output"]["next_kin"]);	
	$kinObj->getField('relationship')->setFromPost($_SESSION["output"]["relation_next_kin"]);
	$kinObj->getField('telephone')->setFromPost($_SESSION["output"]["contact_of_Next_of_Kin"]);
	$kinObj->getField('parent')->setValue($parent_id);
	$this->save($kinObj);

	//Adding Confirmation Date
	$confObj = $this->ff->createContainer('confirmationtoservice');
	$confObj->getField('parent')->setValue($parent_id);
	$confObj->getField('confirmationdate')->setFromPost($_SESSION["output"]["confirmation_date"]);
	$this->save($confObj);

    //Adding Profession Info's
    $profObj = $this->ff->createContainer('profession');
    $profObj->getField('name')->setValue($_SESSION["output"]["profession"]);
	 $profObj->getField('parent')->setValue($parent_id);
	 $this->save($profObj);   
    
	//Adding Demographic info
			//getting number of children
	if($_SESSION["output"]["number_of_children"]>4)
	$dependents=4;
	else
	$dependents=$_SESSION["output"]["number_of_children"];
	$gender=$_SESSION["output"]["sex"];
			//getting Religion
	if($_SESSION["output"]["Religion"]=="Muslim")
	$religion="religion|2";
	else if($_SESSION["output"]["Religion"]=="Christian")
	$religion="religion|1";
	else 
	$religion="religion|3";
			////getting disability
	if($_SESSION["output"]["disability"]=="Physical Disability")
	$disability="disability|4";
	else if($_SESSION["output"]["disability"]=="Visual Impaired")
	$disability="disability|2";
	else if($_SESSION["output"]["disability"]=="None")
	$disability="disability|1";
	else
	$disability="disability|5";
	
	$domObj = $this->ff->createContainer('demographic');
	$domObj->getField('parent')->setValue($parent_id);	        
	$domObj->getField('birth_date')->setFromPost($_SESSION["output"]["dob"]);
	$domObj->getField('religion')->setFromPost($religion);
	$domObj->getField('disability')->setFromPost($disability);
		
	$domObj->gender=array("gender",$gender[0]);
	$domObj->marital_status=array("marital_status",$this->get_marital_id($_SESSION["output"]["marital"]));	
	$domObj->dependents=$dependents;	
	$this->save($domObj);

        //Adding Employment Identification
		$idObj = $this->ff->createContainer('employmentid');
        $check_num=$_SESSION["output"]["check_no"];
        $pf=$_SESSION["output"]["file_no"];        
        $idObj->getField('check_num')->setValue($check_num);
        $idObj->getField('pf_num')->setValue($pf);
                
        $idObj->getField('parent')->setValue($parent_id);
        $this->save($idObj);
        return $personObj;
    }



    function setNewPosition($personObj) 
    {
	$postid = $_SESSION["output"]["file_no"];        
	$parent_id=$_SESSION["parent_id"];
	if($_SESSION["output"]["salary"]!="")
	$salary=number_format($_SESSION["output"]["salary"]);

        //create new forms
        $newPosObj = $this->ff->createContainer('position');
        $newPersonPosObj = $this->ff->createContainer('person_position');
        $newSalaryObj = $this->ff->createContainer('salary');

        //creating position
        $newPosObj->status = array('position_status','closed');
        $newPosObj->code = $_SESSION["output"]["file_no"];
        $newPosObj->title = $this->load_designation('designation');
        $newPosObj->description = $this->load_designation('designation');
        $newPosObj->pos_type= array('position_type','5');
        //$newPosObj->department= array('department',$this->load_department()); 
        $newPosObj->job = array('job',$this->load_designation('job'));
        $newPosObj->getField('posted_date')->setFromPost(date("Y-m-d H:i:s"));       
        $newPosObj->getField('proposed_hiring_date')->setFromPost($_SESSION["output"]["first_appointment"]);
        //$newPosObj->getField('proposed_end_date')->setFromPost($this->convert_date("end"));
	     $newPosObj->getField('proposed_salary')->setFromPost('currency|1='.$_SESSION["output"]["salary"]);
        $newPosObj->getField('source')->setFromPost('salary_source|1');
        $newPosId = $this->save($newPosObj);
        
	//Setting position        
        $newPersonPosObj->getField('start_date')->setFromPost($_SESSION["output"]["first_appointment"]);
        $newPersonPosObj->position =array('position',$newPosId);        
        $newPersonPosObj->getField('parent')->setValue($parent_id);
        $newPersonPosId = $this->save($newPersonPosObj);    

	$newSalaryObj->getField('salary')->setFromPost('currency|1='.$salary);
	$newSalaryObj->getField('start_date')->setFromPost($_SESSION["output"]["first_appointment"]);    
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
