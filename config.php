<?php

/**
 * @package		imgal: Image Gallery and File Browser
 * @name 		imgal Configuration File
 * @author		Masoud Gheysari M <me@gheysari.com>
 * @license		GPLv3
 * @copyright 	2009 Masoud Gheysari M
 */
	
	define('ROOT_PATH'			,'gallery');	// physical root path where you want to browse.
	define('SHOW_THUMBNAIL'		,true);			// if set to true, imgal shows thumbnail for images.
	define('FAST_RENDER'		,false);		// if set to true, thumbnail images will render more quickly but with less quality.
	define('HERO_PASSWORD'		,'imgal');		// password of the hero user (the superuser) who can upload files.
	define('MAX_THUMB_WIDTH'	,96);
	define('MAX_THUMB_HEIGHT'	,64);
	define('ICONS_PER_ROW'		,8);
	define('PREVIEW_TEXT_FILES'	,true);			// should imgal preview text files.
	define('PREVIEW_HTML_FILES'	,true);			// should imgal preview html files.
	define('PREVIEW_CODE_FILES'	,true);			// should imgal preview code files.
	define('SHOW_NAMES_BESIDE'	,false);		// should imgal show file/folder names beside icons (or below them). need more work.
	define('DOWNLOAD_ZIP_DIR'	,true);			// directories can be downloaded as a zip file
	define('DOWNLOAD_TAR_DIR'	,true);			// directories can be downloaded as a tar file
	define('TEMP_PATH'			,sys_get_temp_dir()); // temporary path for storing zipped files
	define('DEFAULT_LANGUAGE'	,'english');	// default interface language
	define('SEARCH_ENABLE'		,true);			// should imgal let users to search.
	define('CHANGE_LANG_ENABLE'	,true);			// should imgal let users to change language.
	define('DEFAULT_THEME'		,'gold');		// the default theme
	define('DEFAULT_ICONS'		,'imgal');		// the default icon pack
	define('DIR_THUMB_CACHE'	,TEMP_PATH);	// physical directory for storing thumbnails cache
	define('URI_THUMB_CACHE'	,'');			// the URI of the thumbnail cache directory (empty if not directly accessible by the browser)
?>
