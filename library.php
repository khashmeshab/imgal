<?php
	function get_temp_name($path,$pre='',$post='',$ext='.tmp') {
		if(!$post) $post = filemtime ($path);
		$hash = md5($path);
		return TEMP_PATH."imgal-$pre-$hash-$post.$ext";
	}
	
	function is_image($path) {
		$file_extension=get_file_extension($path);
		return in_array($file_extension,$GLOBALS['image_extensions']);
	}
	
	function get_file_extension($path) {
	    $dot_position=strrpos($path,'.')+1;
		$file_extension=strtolower(substr($path,$dot_position,strlen($path)-$dot_position));
		return $file_extension;		
	}
	
	function get_file_name($path) {
		for($i=0;$i<strlen($path);$i++) {
			if(substr($path,-1,1)=='/') {
				$path=substr($path,0,-1);
			} else {
				break;
			}
		}
		$slash_position=strrpos($path,'/')+1;
		$file_name=substr($path,$slash_position);
		return $file_name;
	}
	
	function get_file_path($path) {
	    $slash_position=strrpos($path,'/');
		$file_path=substr($path,0,$slash_position+1);
		return $file_path;
	}

	function is_text($path) {
		$file_extension=get_file_extension($path);
		return in_array($file_extension,$GLOBALS['text_extensions']);
	}
	
	function is_html($path) {
		$file_extension=get_file_extension($path);
		return in_array($file_extension,$GLOBALS['html_extensions']);
	}
	
	function is_code($path) {
		$file_extension=get_file_extension($path);
		return in_array($file_extension,$GLOBALS['code_extensions']);
	}
		
	function make_physical_path($path) {
		$j=array();
		if(!is_file($path) && !is_dir($path)) {
			for($i=0;$i<=strlen($path);$i++) {
				if(substr($path,$i,1)=='/' || $i==strlen($path))
					$j[]=substr($path,0,$i);
			}
			foreach($j as $i) {
				if(is_file($i.'.imgal')) {
					$path=file_get_contents($i.'.imgal').substr($path,strlen($i));
					return make_physical_path($path);
					break;
				}
			}
		} else {
			return $path;
		}
	}
	
	function search_add_files($browsing,$files,&$files_list) {
		foreach($files as $name=>$address) {
			if(is_array($address)) {
				search_add_files($browsing.$name.'/',$address,$files_list);
			} else {
				$files_list[$browsing.$name]=$address;
			}
		}
	}
	
	function label($name,$tags=null) {
		global $language;
		$text=$language[$name];
		if($tags) {
			foreach($tags as $tag=>$data) {
				$text=str_replace("%$tag%",$data,$text);
			}
		}
		return $text;
	}

	function prepare_file_list($path,&$tar_total_size=0) {
		$files=array();
		if ($handle = @opendir($path)) {
    		while (false !== ($file = @readdir($handle))) {
    			if(@is_file($path.'/'.$file)) {
    				if(substr($file,-5,5)=='imgal') {
    					$vir_name=substr($file,0,-6);
    					$new_file=make_physical_path($path.'/'.$vir_name);
    					if(is_file($new_file)) {
    						if(substr($new_file,-10,10)!='.imgaltemp') {
    							$files[$vir_name]=$new_file;
    							$tar_total_size+=	@filesize($new_file)+
    												1024-(@filesize($new_file)%512);
    						}
    					} else {
    						$files[$vir_name]=prepare_file_list($new_file,$tar_total_size);
    					}
    				} else {
    					if(substr($file,-10,10)!='.imgaltemp') {
    						$files[$file]=$path.'/'.$file;
    						$tar_total_size+=	@filesize($path.'/'.$file)+
    											1024-(@filesize($path.'/'.$file)%512);
    					}
    				}
    			} elseif(@is_dir($path.'/'.$file) && $file!='.' && $file!='..') {
    				$files[$file]=prepare_file_list($path.'/'.$file.'/',$tar_total_size);
    			}
    		}
    	}
    	return $files;
	}
?>