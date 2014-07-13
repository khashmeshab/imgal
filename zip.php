<?php
	/**
	 * Class to dynamically create a zip file (archive)
	 * @author Rochak Chauhan, modified by: Masoud Gheysari M <me@gheysari.com>
	 */
	class imZip  {  
	
		public $compressedData = array(); 
		public $centralDirectory = array(); // central directory   
		public $endOfCentralDirectory = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record
		public $oldOffset = 0;
		public $temp_file_name;
		private $file;
		private $data_length;
		private $name,$path;
		
		function __construct($path,$name,$temp_path) {
			$this->name=$name;
			$this->path=$path;
			
			//$this->temp_file_name=tempnam($temp_path,'imgal');
			//$this->temp_file_name=$temp_path.rand(100000,999999).'.imgaltemp';
			$this->temp_file_name=get_temp_name($this->path,'zip',null,'zip');
		}
		
		function add_files($files,$root_path='/') {
			foreach($files as $name=>$address) {
				if(is_array($address)) {
					$this->addDirectory($root_path.$name.'/');
					$this->add_files($address,$root_path.$name.'/');
				} else {
					$this->addFile(file_get_contents($address),$root_path.$name);
				}
			}
		}
	
		public function addDirectory($directoryName) {
			$directoryName = str_replace("\\", "/", $directoryName);  
	
			$feedArrayRow = "\x50\x4b\x03\x04";
			$feedArrayRow .= "\x0a\x00";    
			$feedArrayRow .= "\x00\x00";    
			$feedArrayRow .= "\x00\x00";    
			$feedArrayRow .= "\x00\x00\x00\x00"; 
	
			$feedArrayRow .= pack("V",0); 
			$feedArrayRow .= pack("V",0); 
			$feedArrayRow .= pack("V",0); 
			$feedArrayRow .= pack("v", strlen($directoryName) ); 
			$feedArrayRow .= pack("v", 0 ); 
			$feedArrayRow .= $directoryName;  
	
			$feedArrayRow .= pack("V",0); 
			$feedArrayRow .= pack("V",0); 
			$feedArrayRow .= pack("V",0); 
	
			fwrite($this->file,$feedArrayRow);
			$this->data_length+=strlen($feedArrayRow);
			
			$newOffset = $this->data_length;
	
			$addCentralRecord = "\x50\x4b\x01\x02";
			$addCentralRecord .="\x00\x00";    
			$addCentralRecord .="\x0a\x00";    
			$addCentralRecord .="\x00\x00";    
			$addCentralRecord .="\x00\x00";    
			$addCentralRecord .="\x00\x00\x00\x00"; 
			$addCentralRecord .= pack("V",0); 
			$addCentralRecord .= pack("V",0); 
			$addCentralRecord .= pack("V",0); 
			$addCentralRecord .= pack("v", strlen($directoryName) ); 
			$addCentralRecord .= pack("v", 0 ); 
			$addCentralRecord .= pack("v", 0 ); 
			$addCentralRecord .= pack("v", 0 ); 
			$addCentralRecord .= pack("v", 0 ); 
			$ext = "\x00\x00\x10\x00";
			$ext = "\xff\xff\xff\xff";  
			$addCentralRecord .= pack("V", 16 ); 
	
			$addCentralRecord .= pack("V", $this -> oldOffset ); 
			$this -> oldOffset = $newOffset;
	
			$addCentralRecord .= $directoryName;  
	
			$this -> centralDirectory[] = $addCentralRecord;  
		}	 
		
		public function addFile($data, $directoryName)   {
			$directoryName = str_replace("\\", "/", $directoryName);  
		
			$feedArrayRow = "\x50\x4b\x03\x04";
			$feedArrayRow .= "\x14\x00";    
			$feedArrayRow .= "\x00\x00";    
			$feedArrayRow .= "\x08\x00";    
			$feedArrayRow .= "\x00\x00\x00\x00"; 
			
			$uncompressedLength = strlen($data);  
			$compression = crc32($data);
			$gzCompressedData = gzcompress($data);  
			$gzCompressedData = substr( substr($gzCompressedData, 0, strlen($gzCompressedData) - 4), 2); 
			$compressedLength = strlen($gzCompressedData);  
			$feedArrayRow .= pack("V",$compression); 
			$feedArrayRow .= pack("V",$compressedLength); 
			$feedArrayRow .= pack("V",$uncompressedLength); 
			$feedArrayRow .= pack("v", strlen($directoryName) ); 
			$feedArrayRow .= pack("v", 0 ); 
			$feedArrayRow .= $directoryName;  
	
			$feedArrayRow .= $gzCompressedData;  
	
			$feedArrayRow .= pack("V",$compression); 
			$feedArrayRow .= pack("V",$compressedLength); 
			$feedArrayRow .= pack("V",$uncompressedLength); 
	
			fwrite($this->file,$feedArrayRow);
			$this->data_length+=strlen($feedArrayRow);
	
			$newOffset = $this->data_length;
	
			$addCentralRecord = "\x50\x4b\x01\x02";
			$addCentralRecord .="\x00\x00";    
			$addCentralRecord .="\x14\x00";    
			$addCentralRecord .="\x00\x00";    
			$addCentralRecord .="\x08\x00";    
			$addCentralRecord .="\x00\x00\x00\x00"; 
			$addCentralRecord .= pack("V",$compression); 
			$addCentralRecord .= pack("V",$compressedLength); 
			$addCentralRecord .= pack("V",$uncompressedLength); 
			$addCentralRecord .= pack("v", strlen($directoryName) ); 
			$addCentralRecord .= pack("v", 0 );
			$addCentralRecord .= pack("v", 0 );
			$addCentralRecord .= pack("v", 0 );
			$addCentralRecord .= pack("v", 0 );
			$addCentralRecord .= pack("V", 32 ); 
	
			$addCentralRecord .= pack("V", $this -> oldOffset ); 
			$this -> oldOffset = $newOffset;
	
			$addCentralRecord .= $directoryName;  
	
			$this -> centralDirectory[] = $addCentralRecord;  
		}
	
		public function prepareZippedfile() {
			$controlDirectory = implode("", $this -> centralDirectory);
			fwrite($this->file,$controlDirectory.$this->endOfCentralDirectory.
				pack("v", sizeof($this -> centralDirectory)).     
				pack("v", sizeof($this -> centralDirectory)).     
				pack("V", strlen($controlDirectory)).             
				pack("V", $this->data_length)."\x00\x00"); 
				
			fclose($this->file);
		}
	
		public function forceDownload() {
			$archiveName=$this->temp_file_name;
			
			$headerInfo = '';
			 
			if(ini_get('zlib.output_compression')) {
				ini_set('zlib.output_compression', 'Off');
			}
	
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false);
			header("Content-Type: application/zip");
			header("Content-Disposition: attachment; filename=".$this->name.'.zip'.";" );
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($archiveName));
			readfile($archiveName);
			//unlink($archiveName);
			die();
		}
		
		public function generate() {			
			if(!file_exists($this->temp_file_name)) {
				// remove the previous versions
				foreach(glob(get_temp_name($this->path,'zip','*','zip')) as $file)
					unlink($file);
				$files=prepare_file_list($this->path);
				$this->file=fopen($this->temp_file_name,'w');
				$this->addDirectory($this->name.'/');
				$this->add_files($files,$this->name.'/');
				$this->prepareZippedfile();
			}
			$this->forceDownload();
		}
	}
?>