<?php
$dir = getcwd();
/*
echo 'Hello!, Welcome to iHRIS Manage for Malawi. <br>The import module is an Interoberability function betwen the Global System and iHRIS Manage of the Ministry of Health. This module is far from from finished, please call back next time. <br> N.B.: To edit this text, log on /module/import/lib/import_base.php <br>- Bye! Lungo';

exit;
*/
/*************************************************************************
 *
 *  Classes to handle reading headers and rows from data files
 *
 ************************************************************************/


abstract class DataFile {
    protected $file;    
    abstract public function getDataRow();
    abstract public function hasDataRow();
    abstract public function getHeaders();
    public function __construct($file) {
        $this->file = $file;
    }

    public function getFileName() {
        return $this->file;
    }

    public function close() {

    }

}

class CSVDataFile extends DataFile 
{
    protected $fp;
    protected $in_file_sep = false;
    protected $file_size = false;
    public function __construct($file) {
        parent::__construct($file);
        $this->filesize = filesize($file);
        if ( ($this->fp = fopen($this->file,"r")) === false) {
            echo("Please specify the name of a spreadsheet to import: " . $file . " is not openable");
        }
    }
    
    public function hasDataRow() {
        $currpos =  ftell($this->fp);
        if ($currpos === false) {
            return false;
        } else {
            return ($currpos < $this->filesize);
        }
    }

    public function getHeaders() {
        $this->in_file_sep = false;
        fseek($this->fp,0);
        foreach (array("\t",",",";") as $sep) {
            $headers = fgetcsv($this->fp, 4000, $sep);
            if ( $headers === FALSE|| count($headers) < 2) 
            {
                fseek($this->fp,0);
                continue;
            }
            $this->in_file_sep = $sep;
            break;
        }
        if (!$this->in_file_sep) {
            die("Could not get headers\n");
        }        
        foreach ($headers as &$header) {
            $header = trim($header);
        }
        unset($header);
        return $headers;
    }

    public function getDataRow() {
        return $data = fgetcsv($this->fp, 4000, $this->in_file_sep);
    }

    public function close() {
        fclose($this->fp);
    }
}


class ExcelDataFile extends DataFile 
{

    protected $rowIterator;

    public function __construct($file) {
        parent::__construct($file);
        include_once('PHPExcel/PHPExcel.php'); 

        $readerType = PHPExcel_IOFactory::identify($this->file);
        $reader = PHPExcel_IOFactory::createReader($readerType);
        $reader->setReadDataOnly(false);
        $excel = $reader->load($this->file);        
        $worksheet = $excel->getActiveSheet();
        $this->rowIterator = $worksheet->getRowIterator();
    }


    public function hasDataRow() {
        return $this->rowIterator->valid();
    }

    public function getHeaders() {
        $this->rowIterator->rewind();
        $row = $this->rowIterator->current();
        if (!$this->rowIterator->valid()) {
            I2CE::raiseMessage("Could not find header row");
            return false;
        }
        return $this->_readRow($row);
    }

    public function getDataRow() {
        $this->rowIterator->next();
        if (!$this->rowIterator->valid()) {
            return false;
        }
        return $this->_readRow($this->rowIterator->current());
    }

    protected function _readRow($row) {
        if (!$row instanceof PHPExcel_Worksheet_Row) {
            I2CE::raiseMessage("Invalid row object" . get_class($row));
            return false;
        }
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        $data = array();
        foreach ($cellIterator as $cell) {
            $data[] =  $cell->getValue();
        }
        return $data;
    }


}

# Local Variables:
# mode: php
# c-default-style: "bsd"
# indent-tabs-mode: nil
# c-basic-offset: 4
# End:

