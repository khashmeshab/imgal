<?php

/**
 * @package		imgal: Image Gallery and File Browser
 * @name 		imgal Core File
 * @author		Masoud Gheysari M <me@gheysari.com>
 * @license		GPLv3
 * @copyright 	2014 - Masoud Gheysari M
 */
	
	ini_set('display_errors','Off');
	
	require_once('config.php');

	define('IMGAL_VERSION','imgal-2.0.0');
	
	define('DIR_ICONS'			,'icons/'.DEFAULT_ICONS.'/');
	define('DIR_THEME'			,'themes/'.DEFAULT_ICONS.'/');
	define('DIR_LANGUAGES'		,realpath('.').'/languages/');
	
	session_start();
	
	if(isset($_GET['language'])) {
		$_SESSION['language']=$_POST['language'];
	} elseif(!$_SESSION['language']) {
		$_SESSION['language']=DEFAULT_LANGUAGE;
	}
	
	require_once(DIR_LANGUAGES.'english.php');
	require_once(DIR_LANGUAGES.$_SESSION['language'].'.php');
	
	$image_extensions=array('png','jpg','gif');
	$text_extensions=array('txt','log','ini','bat','sh','nfo');
	$html_extensions=array('htm','html');
	$code_extensions=array('php');
	
	$browsing=$_GET['path'];
	
	if(!isset($browsing))
		$browsing='/';
	
	if(strstr($browsing,'..')) // added for security.
		$browsing='/';
		
	$browsing=str_replace('\\','/',$browsing);
		
	if(substr(ROOT_PATH,-1,1)=='/' || substr(ROOT_PATH,-1,1)=='\\') { // added to resolve possible error in mkdir() because of two slashes.
		$path=substr(ROOT_PATH,0,-1).$browsing;
	} else {
		$path=ROOT_PATH.$browsing;
	}
	
	if(substr($path,-1,1)=='/' || substr($path,-1,1)=='\\')
		$path=substr($path,0,-1);
	$path=make_physical_path($path);
	if(is_dir($path))
		$path.='/';
		
	$path=str_replace('\\','/',$path);
	
	$mode=$_GET['mode'];
	
	switch($mode) {
		case 'logout':
			session_unset();
			$message=label('TEXT_LOGOUT_SUCCESSFUL');
			break;
			
		case 'login':
			if($_POST['password']==HERO_PASSWORD) {
				$_SESSION['username']='hero';
				$message=label('TEXT_LOGIN_SUCCESSFUL',array('user'=>'HERO'));
			} else {
				$message=label('TEXT_INVALID_PASSWORD',array('user'=>'HERO'));
			}
			break;
			
		case 'mkdir':
			if($_SESSION['username']!='hero') {
				$message=label('TEXT_NO_ACCESS_MKDIR');
			} else {
				$mkdir=get_file_path($path).$_POST['dir-name'];;
				if(@mkdir($mkdir)) {
					$message=label('TEXT_MKDIR_SUCCESSFUL');
				} else {
					$message=label('TEXT_MKDIR_ERROR');
				}
			}
			break;
		
		case 'upload':
			if($_SESSION['username']!='hero') {
				$message=label('TEXT_NO_ACCESS_UPLOAD');
			} else {
				$uploaddir=get_file_path($path);
				$uploadfile=$uploaddir.basename($_FILES['file-path']['name']);
				if(move_uploaded_file($_FILES['file-path']['tmp_name'], $uploadfile)) {
					$message=label('TEXT_UPLOAD_SUCCESSFUL');
				} else {
					$message=label('TEXT_UPLOAD_ERROR');
				}
			}
			break;
			
		case 'link':
			if($_SESSION['username']!='hero') {
				$message=label('TEXT_NO_ACCESS_LINK');
			} else {
				$address=$_POST['address'];
				$address=str_replace('\\','/',$address);
				$file=fopen(get_file_path($path).get_file_name($address).'.imgal','w');
				fwrite($file,$address);
				fclose($file);
				$message=label('TEXT_LINK_SUCCESSFUL');
			}
			
		case 'thumb':
			if(is_image($path)) {
				switch (get_file_extension($path)) {
					case 'png':
						$im = imagecreatefrompng($path);
						break;
					case 'jpg':
						$im = imagecreatefromjpeg($path);
						break;
					case 'gif':
						$im = imagecreatefromgif($path);
						break;
				}
				if($im) {
					header("Content-Type: image/jpeg");
					$width=imagesx($im);
					$height=imagesy($im);
					if($width/$height>MAX_THUMB_WIDTH/MAX_THUMB_HEIGHT) {
						$new_width=MAX_THUMB_WIDTH;
						$new_height=($height/$width)*MAX_THUMB_WIDTH;
					} else {
						$new_width=($width/$height)*MAX_THUMB_HEIGHT;
						$new_height=MAX_THUMB_HEIGHT;
					}
					if(FAST_RENDER) {
						$im2=imagecreate($new_width,$new_height);
						imagecopyresized($im2,$im,0,0,0,0,$new_width,$new_height,$width,$height);
					} else {
						$im2=imagecreatetruecolor($new_width,$new_height);
						imagecopyresampled($im2,$im,0,0,0,0,$new_width,$new_height,$width,$height);
					}
					imagejpeg($im2);
				}
				die();
			}
			break;
			
		case 'image':
			if(is_image($path)) {
				header('Content-Disposition: attachment; filename="'.get_file_name($path).'"');
				header('Content-Length: '.filesize($path));
				readfile($path);
				die();
			}
			break;
		
		case 'download':
			if(is_file($path)) {
				header('Content-Disposition: attachment; filename="'.get_file_name($path).'"');
				header('Content-Length: '.filesize($path));
				readfile($path);
				die();
			}
			break; 
			
		case 'download-zip':
			if(is_dir($path) && DOWNLOAD_ZIP_DIR) {
				$create_zip=new createZip($temp_path);
				$files=prepare_file_list($path);
				$dir_name=get_file_name($browsing);
				if(!$dir_name) $dir_name='root';
				$create_zip->addDirectory($dir_name.'/');
				zip_add_files($create_zip,$files,$dir_name.'/');
				$create_zip->prepareZippedfile();
				$create_zip->forceDownload($dir_name.'.zip');
				die();
			}
			break;
		
		case 'download-tar':
			if(is_dir($path) && DOWNLOAD_TAR_DIR) {
				$dir_name=get_file_name($browsing);
				if(!$dir_name) $dir_name='root';
				$files=prepare_file_list($path,$tar_total_size);
				tar_add_files($files,$dir_name.'/',$files_list);
				generateTAR($files_list,$tar_total_size);
				die();
			}
			break;
			
		case 'copy':
			if($_SESSION['username']!='hero') {
				$message=label('TEXT_NO_ACCESS_COPY');
			} else {
				$url = $_POST['copy-url'];
				$localfile = $path.get_file_name($url);
				if(copy($url, $localfile))
					$message=label('TEXT_COPY_SUCCESSFUL');
				else 
					$message=label('TEXT_COPY_ERROR');
			}
			break;
		
		case 'search':
			$find=$_POST['query'];
			$matches=array();
			$files=prepare_file_list($path);
			search_add_files($browsing,$files,$files_list);
			foreach($files_list as $name=>$address) {
				if(($i=stripos(get_file_name($name),$find))!==false) {
					$matches[$name]=$address;
				}
			}
			break;
	}
	
	if(is_file($path)) {
		if(is_image($path)) {
			$file_path=get_file_path($path);
			$files=array();
			if ($handle = opendir($file_path)) {
			    while (false !== ($file = readdir($handle))) {
			    	if(is_file($file_path.'/'.$file) && in_array(get_file_extension($file),$image_extensions)) {
			    		$files[]=$file;
			    	}
			    }
			    closedir($handle);
			    
				$current_image=array_search(get_file_name($path),$files);
				$total_images=sizeof($files);
				if($current_image>0) {
					$previous_file=$files[$current_image-1];
				}
				if($current_image<$total_images) {
					$next_file=$files[$current_image+1];
				}
			}

			generate_header();
			echo '<center>';
			echo make_image_in_frame($browsing,false);
			echo '</center>';
			generate_footer();
			die();
		} elseif(is_text($path) && PREVIEW_TEXT_FILES) {
			generate_header();
			echo '<pre>';
			readfile($path);
			echo '</pre>';
			generate_footer();
			die();
		} elseif(is_html($path) && PREVIEW_HTML_FILES) {
			generate_header();
			readfile($path);
			generate_footer();
			die();
		} elseif(is_code($path) && PREVIEW_CODE_FILES) {
			generate_header();
			echo '<table dir="ltr" width="100%"><tr><td>';
			highlight_file($path);
			echo '</td></tr></table>';
			generate_footer();
			die();
		} else {
			header('Content-Disposition: attachment; filename="'.get_file_name($path).'"');
			header('Content-Length: '.filesize($path));
			readfile($path);
			die();
		}
	}

	generate_header();
		
	$dirs=array();
	$files=array();
	
	if($mode=='search' && $find) {
	    $i=0;
	   	echo '<table width="100%" style="table-layout:fixed"><tr>';
	    foreach($matches as $name=>$address) {
	    	if($i>=ICONS_PER_ROW) {
	    		echo '</tr><tr>';
	    		$i=0;
	    	}
	    	echo '<td width="'.(100/ICONS_PER_ROW).'%">';
	    	if(!SHOW_NAMES_BESIDE)
		    	echo '<center>';
	    	if(is_image($name) && SHOW_THUMBNAIL) {
	    		echo make_image_in_frame($name,true);
	    	} else {
	    		echo make_file_icon($name);
	    	}
	    	
			if(!SHOW_NAMES_BESIDE)
	    		echo '<br/></center>';

	    	'</td>';
	    	$i++;
	    }
	    echo '</tr></table>';
	} else {
		if ($handle = opendir($path)) {
		    while (false !== ($file = readdir($handle))) {
		    	if(is_dir($path.$file)) {
		    		if($file!='.' && $file!='..') {
		    			$dirs[]=array('name'=>$file,'type'=>'p');
		    		}
		    	} elseif(is_file($path.$file) && get_file_extension($path.$file)=='imgal') {
		    		$temp_path=make_physical_path(substr($path.$file,0,-6));
		    		if(is_dir($temp_path)) {
		    			$dirs[]=array('name'=>substr($file,0,-6),'type'=>'v');
		    		} else {
		    			$files[]=substr($file,0,-6);
		    		}
		    	} elseif(get_file_extension($path.$file)!='imgaltemp') {
		    		$files[]=$file;
		    	}
		    }
		    closedir($handle);
			sort($dirs);
			sort($files);
	
		    $i=0;
		   	echo '<table width="100%" style="table-layout:fixed"><tr>';
		    foreach($dirs as $dir) {
		    	if($dir['type']=='p') {
		    		$icon='folder.png';
		    	} else {
		    		$icon='vfolder.png';
		    	}
		    	if($i>=ICONS_PER_ROW) {
		    		echo '</tr><tr>';
		    		$i=0;
		    	}
		    	echo '<td width="'.(100/ICONS_PER_ROW).'%"><a href="?path='.$browsing.$dir['name'].'/" style="text-decoration: none">';
		    	if(!SHOW_NAMES_BESIDE)
		    		echo '<center>';
		    	echo '<img src="'.DIR_ICONS.$icon.'" border="0" align="absmiddle"/>';
		    	if(!SHOW_NAMES_BESIDE)
		    		echo '<br/>';
		    	echo '<font face="Tahoma" style="font-size=8pt" color="black">'.$dir['name'].'</font>';
		    	if(!SHOW_NAMES_BESIDE)
		    		echo '</center>';
		    	echo '</a></td>';
		    	$i++;
		    }
	
		    foreach($files as $file) {
		    	if($i>=ICONS_PER_ROW) {
		    		echo '</tr><tr>';
		    		$i=0;
		    	}
		    	echo '<td width="'.(100/ICONS_PER_ROW).'%">';
		    	if(!SHOW_NAMES_BESIDE)
			    	echo '<center>';
		    	if(is_image($file) && SHOW_THUMBNAIL) {
		    		echo make_image_in_frame($browsing.$file,true);
		    	} else {
		    		echo make_file_icon($browsing.$file);
		    	}
		    	
				if(!SHOW_NAMES_BESIDE)
		    		echo '<br/></center>';
	
		    	'</td>';
		    	$i++;
		    }
		    echo '</tr></table>';
		}
	}
	
	generate_footer();
	
	function make_image_in_frame($image,$thumb=false) {
		global $browsing;
    	$rtn ='<table cellpadding="0" cellspacing="0" dir="ltr"><tr><td>';
    	
    	if($thumb)
    		$rtn.='<a href="?path='.$image.'" style="text-decoration: none"><img src="?path='.$image.'&mode=thumb" border="1" style="border-color:7f7f7f"></a>';
    	else
    		$rtn.='<img src="?path='.$image.'&mode=image" border="1" style="border-color:7f7f7f">';
    	
    	$rtn.='</td><td background="'.DIR_THEME.'images/middle-right.jpg" valign="top"><img src="'.DIR_THEME.'images/top-right.jpg"/></td><td>';
    	if(SHOW_NAMES_BESIDE)
			$rtn.='<a href="?path='.$browsing.get_file_name($image).'" style="text-decoration: none"><font face="Tahoma" size="2" color="black">'.get_file_name($image).'</font></a>';
    	$rtn.='</td></tr>';
    	$rtn.='<tr height="9px"><td background="'.DIR_THEME.'images/bottom-center.jpg"><img src="'.DIR_THEME.'images/bottom-left.jpg"/></td><td><img src="'.DIR_THEME.'images/bottom-right.jpg"/></td><td></td></tr>';
    	$rtn.='</table>';
    	if(!SHOW_NAMES_BESIDE)
			$rtn.='<a href="?path='.$browsing.get_file_name($image).'" style="text-decoration: none"><font face="Tahoma" style="font-size=8pt" color="black">'.get_file_name($image).'</font></a>';
    	return $rtn;
	}
	
	function make_file_icon($path) {
		$file_extension=get_file_extension($path);
		
		if(file_exists(realpath('./'.DIR_ICONS."$file_extension.png")))
			$img=$file_extension.'.png';
		else 
			$img='file.png';
    	$rtn ='<a href="?path='.$path.'" style="text-decoration: none"><img src="'.DIR_ICONS.$img.'" border="0" align="absmiddle"/></a>';
    	if(!SHOW_NAMES_BESIDE)
    		$rtn.='<br/>';
    	$rtn.='<a href="?path='.$path.'" style="text-decoration: none"><font face="Tahoma" style="font-size=8pt" color="black">'.get_file_name($path).'</font></a>';
    	return $rtn;
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
	
	function generate_header() {
		global $browsing;
		global $previous_file;
		global $next_file;
		global $current_image,$total_images;
		global $path;
		global $mode;
		echo '<html><head><title>'.IMGAL_VERSION.'</title><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body dir="'.label('OPTN_DIR').'">';
		echo '<table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;border-width:1px;border-style:solid;border-color=ffc000;"><tr bgcolor="fedd56"><td width="15%">';
		echo '<font face="Tahoma">';
		echo '<center><font size="5" color="darkred"><b>'.IMGAL_VERSION.'</b></font><br/><font size="1">I\'m Image Gallery!</font></center>';
		echo '</font>';
		echo '</td><td width="15%">';
		echo '<font face="Tahoma" style="font-size=8pt"><b>'.label('TEXT_CURRENTLY_BROWSING').':</b><br/>'.$browsing.'</font></td><td width="15%">';
		if($GLOBALS['message']) {
			echo '<font face="Tahoma" style="font-size=8pt"><b>'.label('TEXT_MESSAGE').':</b><br/>'.$GLOBALS['message'].'</font>';
		}
		echo '</td><td width="20%">';
		if(SEARCH_ENABLE) {
			echo '<form action="?path='.$browsing.'&mode=search" method="POST" style="margin-bottom:0;"><center><font face="Tahoma"  style="font-size=8pt">';
			echo '<input type="text" size="20" name="query" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><input type="submit" value="'.label('BUTN_FIND').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
		}
		echo '</td><td width="20%">';
		if(isset($total_images)) {
			echo '<font face="Tahoma" style="font-size=8pt">'.label('TEXT_IMAGE',array('no'=>($current_image+1),'total'=>$total_images)).'</font>';
		} elseif (is_text($path) || is_html($path) || is_code($path)) {
			echo '<a href="?path='.$browsing.'&mode=download"><font face="Tahoma" style="font-size=8pt">'.label('TEXT_DOWNLOAD_THIS_FILE').'</font></a>';
		} 
		if (is_dir($path) && DOWNLOAD_ZIP_DIR) {
			echo '<a href="?path='.$browsing.'&mode=download-zip"><font face="Tahoma" style="font-size=8pt">'.label('TEXT_DOWNLOAD_DIR_ZIP').'</font></a>';
		}
		if(is_dir($path) && DOWNLOAD_ZIP_DIR && DOWNLOAD_TAR_DIR) echo '<br/>';
		if (is_dir($path) && DOWNLOAD_TAR_DIR) {
			echo '<a href="?path='.$browsing.'&mode=download-tar"><font face="Tahoma" style="font-size=8pt">'.label('TEXT_DOWNLOAD_DIR_TAR').'</font></a>';
		}
		
		$palign='right';
		if(label('OPTN_DIR')=='rtl') $palign='left';
		echo '</td><td width="15%"><p align="'.$palign.'">';
		
		if(CHANGE_LANG_ENABLE) {
			echo '<form dir="'.label('OPTN_DIR').'" action="?path='.$browsing.'&language" method="POST" style="margin-bottom:0;"><select name="language" size="1" id="language" onChange="JavaScript:form.submit()" style="font-name:Tahoma;font-size=10;border-style:solid;border-width:1px;border-color=d6ba49;">';
	
			if ($handle = opendir(DIR_LANGUAGES)) {
			    while (false !== ($file = readdir($handle))) {
					if(is_file(DIR_LANGUAGES.$file) && substr($file,-4)=='.php') {
						$language=substr($file,0,-4);
						if($language==$_SESSION['language']) {
							$selected='selected';
						} else {
							$selected='';	
						}
						echo '<option value="'.$language.'" '.$selected.'>'.$language.'</option>';					
					}
			    }
			    closedir($handle);
			}
			
			echo '</select>';
			//echo '<input type="submit" value="go" style="font-name:Tahoma;font-size=10;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</form>';
		}
		
		if($browsing!='/' || ($mode=='search')) {
			if($i=strrpos(substr($browsing,0,-1),'/')) {
				$browsing_up=substr($browsing,0,$i).'/';
			} else {
				$browsing_up='/';
			}
			if($mode=='search') $browsing_up=$browsing;
			if($previous_file) {
				echo '<a href="?path='.$browsing_up.$previous_file.'"><img src="'.DIR_THEME.'images/previous.png" border="0"/></a>';
			} elseif(is_image($path)) {
				echo '<img src="images/previous_inactive.png" border="0"/>';
			}
			if($next_file) {
				echo '<a href="?path='.$browsing_up.$next_file.'"><img src="'.DIR_THEME.'images/next.png" border="0"/></a>';
			} elseif(is_image($path)) {
				echo '<img src="images/next_inactive.png" border="0"/>';
			}

			echo '<a href="?path='.$browsing_up.'"><img src="'.DIR_THEME.'images/up.png" border="0"/></a><a href="?path=/"><img src="'.DIR_THEME.'images/home.png" border="0"/></a>';
		}
		echo '</p></td></tr><tr><td colspan="6" bgcolor="ffff99"><br/>';
	}
	
	function generate_footer() {
		global $browsing;
		echo '<br/></td></tr><td colspan="6"><table bgcolor="fedd56" width="100%" style="table-layout:fixed"><tr>';


		if($_SESSION['username']=='hero') {
			echo '<td>';
			echo '<form action="?path='.$browsing.'&mode=mkdir" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="text" size="20" name="dir-name" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_MKDIR').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td colspan="2"><form enctype="multipart/form-data" action="?path='.$browsing.'&mode=upload" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="file" name="file-path" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_UPLOAD').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td><form action="?path='.$browsing.'&mode=copy" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="text" size="20" name="copy-url" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_COPY').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td><form action="?path='.$browsing.'&mode=link" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="text" size="20" name="address" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_LINK').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td><p align="center"><font face="Tahoma" size="1">';
			echo '<a href="?path='.$browsing.'&mode=logout" style="text-decoration: none"><img src="images/logout.png" border="0"/><br/>'.label('TEXT_LOGOUT').'</a>';
			echo '</font></p>';
			echo '</td>';
		} else {
			echo '<td colspan="5">';
			echo '<form action="?path='.$browsing.'&mode=login" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" style="font-name:Tahoma;font-size=8pt;">';
			echo label('TEXT_USERNAME').': <b>HERO</b> / '.label('TEXT_PASSWORD').': <input type="password" size="20" name="password" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><input type="submit" value="'.label('BUTN_LOGIN').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td>';
		}

		echo '</tr></table></td></tr></table>';
		echo '</body></html>';
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
	
	function search_add_files($browsing,$files,&$files_list) {
		foreach($files as $name=>$address) {
			if(is_array($address)) {
				search_add_files($browsing.'/'.$name,$address,$files_list);
			} else {
				$files_list[$browsing.'/'.$name]=$address;
			}
		}
	}
	
	function zip_add_files(&$create_zip,$files,$root_path='/') {
		foreach($files as $name=>$address) {
			if(is_array($address)) {
				$create_zip->addDirectory($root_path.$name.'/');
				zip_add_files($create_zip,$address,$root_path.$name.'/');
			} else {
				$create_zip->addFile(file_get_contents($address),$root_path.$name);
			}
		}
	}
	
	function tar_add_files($files,$root_path='/',&$files_list) {
		foreach($files as $name=>$address) {
			if(is_array($address)) {
				tar_add_files($address,$root_path.$name.'/',$files_list);
			} else {
				$files_list[$root_path.$name]=$address;
			}
		}
	}	
	/**
	 * Class to dynamically create a zip file (archive)
	 * @author Rochak Chauhan, modified by: Masoud Gheysari M <me@gheysari.com>
	 */
	class createZip  {  
	
		public $compressedData = array(); 
		public $centralDirectory = array(); // central directory   
		public $endOfCentralDirectory = "\x50\x4b\x05\x06\x00\x00\x00\x00"; //end of Central directory record
		public $oldOffset = 0;
		public $temp_file_name;
		private $file;
		private $data_length;
		
		function createZip($temp_path) {
			$this->temp_file_name=$temp_path.rand(100000,999999).'.imgaltemp';
			$this->file=fopen($this->temp_file_name,'w');
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
	
		public function forceDownload($file_name) {
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
			header("Content-Disposition: attachment; filename=".$file_name.";" );
			header("Content-Transfer-Encoding: binary");
			header("Content-Length: ".filesize($archiveName));
			readfile($archiveName);
			unlink($archiveName);
		 }
	
	}	
	/**
	 * @author		Josh Barger <joshb@npt.com>, modified by: Masoud Gheysari M <me@gheysari.com>
	 * @copyright	Copyright (C) 2002  Josh Barger
	 */
	function generateTAR($files_list,$tar_total_size) {
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false);
		header("Content-Type: application/x-tar");
		header("Content-Disposition: attachment; filename=".$GLOBALS['dir_name'].".tar;" );
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".($tar_total_size+512));
		
		foreach($files_list as $name => $address) {
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

			$checksum = str_pad(decoct(computeUnsignedChecksum($header)),6,"0",STR_PAD_LEFT);
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
	}
	
	function computeUnsignedChecksum($bytestring) {
		for($i=0; $i<512; $i++)
			$unsigned_chksum += ord($bytestring[$i]);
		for($i=0; $i<8; $i++)
			$unsigned_chksum -= ord($bytestring[148 + $i]);
		$unsigned_chksum += ord(" ") * 8;

		return $unsigned_chksum;
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
	
?>
