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
	require_once('library.php');
	require_once('megan.php');

	define('IMGAL_VERSION','2.0.0');
	
	define('URI_ICONS'			,'icons/'.DEFAULT_ICONS.'/');
	define('URI_THEME'			,'themes/'.DEFAULT_THEME.'/');
	define('DIR_THEME'			,realpath('themes/'.DEFAULT_THEME).'/');
	define('DIR_LANGUAGES'		,realpath('./languages').'/');
	
	session_start();
	
	if(isset($_GET['language'])) {
		$_SESSION['language']=$_POST['language'];
	} elseif(!$_SESSION['language']) {
		$_SESSION['language']=DEFAULT_LANGUAGE;
	}
	
	require_once(DIR_LANGUAGES.'english.php');
	require_once(DIR_LANGUAGES.$_SESSION['language'].'.php');
	
	$image_extensions=array('png','jpg','jpeg','gif');
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
	
	$dir_name=get_file_name($browsing);
	if(!$dir_name) $dir_name='root';
	
	$megan=new Megan('template.html',DIR_THEME);
	$megan->PageTitle='imGal v'.IMGAL_VERSION;
	$megan->URITheme=URI_THEME;
	$megan->Browsing=$browsing;
	
	$do=$_GET['do'];
	
	switch($do) {
		case 'logout':
			session_unset();
			$megan->Message=label('TEXT_LOGOUT_SUCCESSFUL');
			break;
			
		case 'login':
			if($_POST['password']==HERO_PASSWORD) {
				$_SESSION['username']='hero';
				$megan->Message=label('TEXT_LOGIN_SUCCESSFUL',array('user'=>'HERO'));
			} else {
				$megan->Message=label('TEXT_INVALID_PASSWORD',array('user'=>'HERO'));
			}
			break;
			
		case 'mkdir':
			if($_SESSION['username']!='hero') {
				$megan->Message=label('TEXT_NO_ACCESS_MKDIR');
			} else {
				$mkdir=get_file_path($path).$_POST['dir-name'];;
				if(@mkdir($mkdir)) {
					$megan->Message=label('TEXT_MKDIR_SUCCESSFUL');
				} else {
					$megan->Message=label('TEXT_MKDIR_ERROR');
				}
			}
			break;
		
		case 'upload':
			if($_SESSION['username']!='hero') {
				$megan->Message=label('TEXT_NO_ACCESS_UPLOAD');
			} else {
				$uploaddir=get_file_path($path);
				$uploadfile=$uploaddir.basename($_FILES['file-path']['name']);
				if(move_uploaded_file($_FILES['file-path']['tmp_name'], $uploadfile)) {
					$megan->Message=label('TEXT_UPLOAD_SUCCESSFUL');
				} else {
					$megan->Message=label('TEXT_UPLOAD_ERROR');
				}
			}
			break;
			
		case 'link':
			if($_SESSION['username']!='hero') {
				$megan->Message=label('TEXT_NO_ACCESS_LINK');
			} else {
				$address=$_POST['address'];
				$address=str_replace('\\','/',$address);
				$file=fopen(get_file_path($path).get_file_name($address).'.imgal','w');
				fwrite($file,$address);
				fclose($file);
				$megan->Message=label('TEXT_LINK_SUCCESSFUL');
			}
			
		case 'thumb':
			if(is_image($path)) {
				$thumb_name=get_temp_name($path,'thumb',null,'jpg');
				if(!file_exists($thumb_name)) {
					// remove the previous versions
					foreach(glob(get_temp_name($path,'thumb','*','jpg')) as $file)
						unlink($file);
					
					switch (get_file_extension($path)) {
						case 'png':
							$im = imagecreatefrompng($path);
							break;
						case 'jpg':
						case 'jpeg':
							$im = imagecreatefromjpeg($path);
							break;
						case 'gif':
							$im = imagecreatefromgif($path);
							break;
					}
					if($im) {
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
						imagejpeg($im2,$thumb_name);
					}
				}
				header("Content-Type: image/jpeg");
				readfile($thumb_name);
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
			
		case 'zip':
			if(is_dir($path) && DOWNLOAD_ZIP_DIR) {
				require_once('zip.php');
				$zip=new imZip($path,$dir_name,TEMP_PATH);
				$zip->generate();
			}
			break;
		
		case 'tar':
			require_once('tar.php');
			$tar = new imTar($path,$dir_name);
			$tar->generate();
			break;
			
		case 'copy':
			if($_SESSION['username']!='hero') {
				$megan->Message=label('TEXT_NO_ACCESS_COPY');
			} else {
				$url = $_POST['copy-url'];
				$localfile = $path.get_file_name($url);
				if(copy($url, $localfile))
					$megan->Message=label('TEXT_COPY_SUCCESSFUL');
				else 
					$megan->Message=label('TEXT_COPY_ERROR');
			}
			break;
		
		case 'search':
			$query=$_GET['q'];
			if(!$query) $query=$_POST['q'];
			$matches=array();
			$files=prepare_file_list($path);
			search_add_files($browsing,$files,$files_list);
			foreach($files_list as $name=>$address) {
				if(($i=stripos(get_file_name($name),$query))!==false) {
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
			
			$megan->Contents='<center>'.make_image_in_frame($browsing,false).'</center>';
			/*generate_header();
			echo '<center>';
			echo make_image_in_frame($browsing,false);
			echo '</center>';
			generate_footer();*/
			die();
		} elseif(is_text($path) && PREVIEW_TEXT_FILES) {
			$megan->Contents='<pre>'.file_get_contents($path).'</pre>';
			/*generate_header();
			echo '<pre>';
			readfile($path);
			echo '</pre>';
			generate_footer();*/
			die();
		} elseif(is_html($path) && PREVIEW_HTML_FILES) {
			$megan->Contents=file_get_contents($path);
			/*generate_header();
			readfile($path);
			generate_footer();*/
			die();
		} elseif(is_code($path) && PREVIEW_CODE_FILES) {
			$megan->Contents='<table dir="ltr" width="100%"><tr><td>'.highlight_file($path,true).'</td></tr></table>';
			/*generate_header();
			echo '<table dir="ltr" width="100%"><tr><td>';
			highlight_file($path);
			echo '</td></tr></table>';
			generate_footer();*/
			die();
		} else {
			header('Content-Disposition: attachment; filename="'.get_file_name($path).'"');
			header('Content-Length: '.filesize($path));
			readfile($path);
			die();
		}
	}

	//generate_header();
		
	$dirs=array();
	$files=array();
	
	if($do=='search' && $query) {
	    $i=0;
		$megan->Contents.='<table width="100%" style="table-layout:fixed"><tr>';
	    foreach($matches as $name=>$address) {
	    	if($i>=ICONS_PER_ROW) {
				$megan->Contents='</tr><tr>';
	    		$i=0;
	    	}
			$megan->Contents.='<td width="'.(100/ICONS_PER_ROW).'%">';
	    	if(!SHOW_NAMES_BESIDE)
		    	$megan->Contents.='<center>';
	    	if(is_image($name) && SHOW_THUMBNAIL) {
	    		$megan->Contents.=make_image_in_frame($name,true);
	    	} else {
	    		$megan->Contents.=make_file_icon($name);
	    	}
	    	
			if(!SHOW_NAMES_BESIDE)
	    		$megan->Contents.='<br/></center>';

	    	$megan->Contents.='</td>';
	    	$i++;
	    }
	    $megan->Contents.='</tr></table>';
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
		   	$megan->Contents.='<table width="100%" style="table-layout:fixed"><tr>';
		    foreach($dirs as $dir) {
		    	if($dir['type']=='p') {
		    		$icon='folder.png';
		    	} else {
		    		$icon='vfolder.png';
		    	}
		    	if($i>=ICONS_PER_ROW) {
		    		$megan->Contents.='</tr><tr>';
		    		$i=0;
		    	}
		    	$megan->Contents.='<td width="'.(100/ICONS_PER_ROW).'%"><a href="?path='.$browsing.$dir['name'].'/" style="text-decoration: none">';
		    	if(!SHOW_NAMES_BESIDE)
		    		$megan->Contents.='<center>';
		    	$megan->Contents.='<img src="'.URI_ICONS.$icon.'" border="0" align="absmiddle"/>';
		    	if(!SHOW_NAMES_BESIDE)
		    		$megan->Contents.='<br/>';
		    	$megan->Contents.='<font face="Tahoma" style="font-size=8pt" color="black">'.$dir['name'].'</font>';
		    	if(!SHOW_NAMES_BESIDE)
		    		$megan->Contents.='</center>';
		    	$megan->Contents.='</a></td>';
		    	$i++;
		    }
	
		    foreach($files as $file) {
		    	if($i>=ICONS_PER_ROW) {
		    		$megan->Contents.='</tr><tr>';
		    		$i=0;
		    	}
		    	$megan->Contents.='<td width="'.(100/ICONS_PER_ROW).'%">';
		    	if(!SHOW_NAMES_BESIDE)
			    	$megan->Contents.='<center>';
		    	if(is_image($file) && SHOW_THUMBNAIL) {
		    		$megan->Contents.=make_image_in_frame($browsing.$file,true);
		    	} else {
		    		$megan->Contents.=make_file_icon($browsing.$file);
		    	}
		    	
				if(!SHOW_NAMES_BESIDE)
		    		$megan->Contents.='<br/></center>';
	
		    	$megan->Contents.='</td>';
		    	$i++;
		    }
		    $megan->Contents.='</tr></table>';
		}
	}
	
	//generate_footer();
	
	$megan->Generate();
	
	function make_image_in_frame($image,$thumb=false) {
		global $browsing;
    	$rtn ='<table cellpadding="0" cellspacing="0" dir="ltr"><tr><td>';
    	
    	if($thumb)
    		$rtn.='<a href="?path='.$image.'" style="text-decoration: none"><img src="?path='.$image.'&do=thumb" border="1" style="border-color:7f7f7f"></a>';
    	else
    		$rtn.='<img src="?path='.$image.'&do=image" border="1" style="border-color:7f7f7f">';
    	
    	$rtn.='</td><td background="'.URI_THEME.'images/middle-right.jpg" valign="top"><img src="'.URI_THEME.'images/top-right.jpg"/></td><td>';
    	if(SHOW_NAMES_BESIDE)
			$rtn.='<a href="?path='.$browsing.get_file_name($image).'" style="text-decoration: none"><font face="Tahoma" size="2" color="black">'.get_file_name($image).'</font></a>';
    	$rtn.='</td></tr>';
    	$rtn.='<tr height="9px"><td background="'.URI_THEME.'images/bottom-center.jpg"><img src="'.URI_THEME.'images/bottom-left.jpg"/></td><td><img src="'.URI_THEME.'images/bottom-right.jpg"/></td><td></td></tr>';
    	$rtn.='</table>';
    	if(!SHOW_NAMES_BESIDE)
			$rtn.='<a href="?path='.$browsing.get_file_name($image).'" style="text-decoration: none"><font face="Tahoma" style="font-size=8pt" color="black">'.get_file_name($image).'</font></a>';
    	return $rtn;
	}
	
	function make_file_icon($path) {
		$file_extension=get_file_extension($path);
		
		if(file_exists(realpath('./'.URI_ICONS."$file_extension.png")))
			$img=$file_extension.'.png';
		else 
			$img='file.png';
    	$rtn ='<a href="?path='.$path.'" style="text-decoration: none"><img src="'.URI_ICONS.$img.'" border="0" align="absmiddle"/></a>';
    	if(!SHOW_NAMES_BESIDE)
    		$rtn.='<br/>';
    	$rtn.='<a href="?path='.$path.'" style="text-decoration: none"><font face="Tahoma" style="font-size=8pt" color="black">'.get_file_name($path).'</font></a>';
    	return $rtn;
	}
		
	function generate_header() {
		global $browsing;
		global $previous_file;
		global $next_file;
		global $current_image,$total_images;
		global $path;
		global $do;
		echo '<html><head><title>'.IMGAL_VERSION.'</title><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body dir="'.label('OPTN_DIR').'">';
		echo '<table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;border-width:1px;border-style:solid;border-color=ffc000;"><tr bgcolor="fedd56"><td width="15%">';
		echo '<font face="Tahoma">';
		echo '<center><font size="5" color="darkred"><b>'.IMGAL_VERSION.'</b></font><br/><font size="1">I\'m Image Gallery!</font></center>';
		echo '</font>';
		echo '</td><td width="15%">';
		if($do=='search') {
			echo '<font face="Tahoma" style="font-size=8pt">'.label('TEXT_SEARCHING_FOR',array('query'=>$GLOBALS['query'],'path'=>$browsing)).'</font></td><td width="15%">';
		} else {
			echo '<font face="Tahoma" style="font-size=8pt">'.label('TEXT_CURRENTLY_BROWSING',array('path'=>$browsing)).'</font></td><td width="15%">';
		}
		if($GLOBALS['message']) {
			echo '<font face="Tahoma" style="font-size=8pt"><b>'.label('TEXT_MESSAGE').':</b><br/>'.$GLOBALS['message'].'</font>';
		}
		echo '</td><td width="20%">';
		if(SEARCH_ENABLE) {
			echo '<form action="?path='.$browsing.'&do=search" method="POST" style="margin-bottom:0;"><center><font face="Tahoma"  style="font-size=8pt">';
			echo '<input type="text" size="20" name="q" value="'.$GLOBALS['query'].'" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><input type="submit" value="'.label('BUTN_FIND').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
		}
		echo '</td><td width="20%">';
		if(isset($total_images)) {
			echo '<font face="Tahoma" style="font-size=8pt">'.label('TEXT_IMAGE',array('no'=>($current_image+1),'total'=>$total_images)).'</font>';
		} elseif (is_text($path) || is_html($path) || is_code($path)) {
			echo '<a href="?path='.$browsing.'&do=download"><font face="Tahoma" style="font-size=8pt">'.label('TEXT_DOWNLOAD_THIS_FILE').'</font></a>';
		} 
		if (is_dir($path) && DOWNLOAD_ZIP_DIR) {
			echo '<a href="?path='.$browsing.'&do=zip"><font face="Tahoma" style="font-size=8pt">'.label('TEXT_DOWNLOAD_DIR_ZIP').'</font></a>';
		}
		if(is_dir($path) && DOWNLOAD_ZIP_DIR && DOWNLOAD_TAR_DIR) echo '<br/>';
		if (is_dir($path) && DOWNLOAD_TAR_DIR) {
			echo '<a href="?path='.$browsing.'&do=tar"><font face="Tahoma" style="font-size=8pt">'.label('TEXT_DOWNLOAD_DIR_TAR').'</font></a>';
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
		
		if($browsing!='/' || ($do=='search')) {
			if($i=strrpos(substr($browsing,0,-1),'/')) {
				$browsing_up=substr($browsing,0,$i).'/';
			} else {
				$browsing_up='/';
			}
			if($do=='search') $browsing_up=$browsing;
			if($previous_file) {
				echo '<a href="?path='.$browsing_up.$previous_file.'"><img src="'.URI_THEME.'images/previous.png" border="0"/></a>';
			} elseif(is_image($path)) {
				echo '<img src="images/previous_inactive.png" border="0"/>';
			}
			if($next_file) {
				echo '<a href="?path='.$browsing_up.$next_file.'"><img src="'.URI_THEME.'images/next.png" border="0"/></a>';
			} elseif(is_image($path)) {
				echo '<img src="images/next_inactive.png" border="0"/>';
			}

			echo '<a href="?path='.$browsing_up.'"><img src="'.URI_THEME.'images/up.png" border="0"/></a><a href="?path=/"><img src="'.URI_THEME.'images/home.png" border="0"/></a>';
		}
		echo '</p></td></tr><tr><td colspan="6" bgcolor="ffff99"><br/>';
	}
	
	function generate_footer() {
		global $browsing;
		echo '<br/></td></tr><td colspan="6"><table bgcolor="fedd56" width="100%" style="table-layout:fixed"><tr>';


		if($_SESSION['username']=='hero') {
			echo '<td>';
			echo '<form action="?path='.$browsing.'&do=mkdir" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="text" size="20" name="dir-name" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_MKDIR').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td colspan="2"><form enctype="multipart/form-data" action="?path='.$browsing.'&do=upload" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="file" name="file-path" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_UPLOAD').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td><form action="?path='.$browsing.'&do=copy" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="text" size="20" name="copy-url" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_COPY').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td><form action="?path='.$browsing.'&do=link" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" size="1">';
			echo '<input type="text" size="20" name="address" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><br/><input type="submit" value="'.label('BUTN_LINK').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td><td><p align="center"><font face="Tahoma" size="1">';
			echo '<a href="?path='.$browsing.'&do=logout" style="text-decoration: none"><img src="'.URI_THEME.'images/logout.png" border="0"/><br/>'.label('TEXT_LOGOUT').'</a>';
			echo '</font></p>';
			echo '</td>';
		} else {
			echo '<td colspan="5">';
			echo '<form action="?path='.$browsing.'&do=login" method="POST" style="margin-bottom:0;"><center><font face="Tahoma" style="font-name:Tahoma;font-size=8pt;">';
			echo label('TEXT_USERNAME').': <b>HERO</b> / '.label('TEXT_PASSWORD').': <input type="password" size="20" name="password" style="font-name:Tahoma;font-size=8pt;border-style:solid;border-width:1px;border-color=d6ba49;"/><input type="submit" value="'.label('BUTN_LOGIN').'" style="font-name:Tahoma;font-size=8pt;font-weight=bold;color:fedd56;border-style:solid;border-width:2px;border-color=fedd56;background=233623;"/>';
			echo '</font></center></form>';
			echo '</td>';
		}

		echo '</tr></table></td></tr></table>';
		echo '</body></html>';
	}
?>