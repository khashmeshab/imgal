<?php
	class imTar {
		private $files_list,$tar_total_size,$name;
	
		function __construct($path,$name) {
			$this->name=$name;
			$files=prepare_file_list($path,$this->tar_total_size);
			$this->add_files($files,$name.'/');
		}
	
		function add_files($files,$root_path='/') {
			foreach($files as $fname=>$address) {
				if(is_array($address)) {
					$this->add_files($address,$this->name.$fname.'/');
				} else {
					$this->files_list[$root_path.$fname]=$address;
				}
			}
		}	

		/**
		 * @author		Josh Barger <joshb@npt.com>, modified by: Masoud Gheysari M <me@gheysari.com>
		 * @copyright	Copyright (C) 2002  Josh Barger
		 */
		function generate() {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private",false);
			header("Content-Type: application/x-tar");
			header("Content-Disposition: attachment; filename=".$this->name.".tar;" );
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".($this->tar_total_size+512));
			
			foreach($this->files_list as $name => $address) {
				$header .= str_pad($name,100,chr(0));
				$header .= str_pad(decoct('777'),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct('0'),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct('0'),7,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct(filesize($address)),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_pad(decoct(filectime($address)),11,"0",STR_PAD_LEFT) . chr(0);
				$header .= str_repeat(" ",8);
				$header .= "0";
				$header .= str_repeat(chr(0),100);
				$header .= str_pad("ustar",6,chr(32));
				$header .= chr(32) . chr(0);
				$header .= str_pad('root',32,chr(0));
				$header .= str_pad('root',32,chr(0));
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),8);
				$header .= str_repeat(chr(0),155);
				$header .= str_repeat(chr(0),12);

				$checksum = str_pad(decoct($this->computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
				for($i=0; $i<6; $i++) {
					$header[(148 + $i)] = substr($checksum,$i,1);
				}
				$header[154] = chr(0);
				$header[155] = chr(32);
				
				echo $header;
				readfile($address);
				echo str_repeat(chr(0),512-(filesize($address)%512));
				unset($header);
			}
			echo str_repeat(chr(0),512);
			die();
		}
		
		function computeUnsignedChecksum($bytestring) {
			for($i=0; $i<512; $i++)
				$unsigned_chksum += ord($bytestring[$i]);
			for($i=0; $i<8; $i++)
				$unsigned_chksum -= ord($bytestring[148 + $i]);
			$unsigned_chksum += ord(" ") * 8;

			return $unsigned_chksum;
		}
	}
?>