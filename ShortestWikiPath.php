<?php
	
	/*
	 * ShortestWikiPath
	 * 
	 * author		komed3
	 * version		1.0.0
	 * date			2018-08-03
	 * modified		2018-08-03
	 * license		MIT
	 * 
	 */
	
	if(function_exists('wfLoadExtension')) {
		
		wfLoadExtension('ShortestWikiPath');
		
		# Keep i18n globals so mergeMessageFileList.php doesn't break
		$wgMessagesDirs['ShortestWikiPath'] = __DIR__ . '/i18n';
		
		wfWarn(
			'Deprecated PHP entry point used for ShortestWikiPath extension. ' .
			'Please use wfLoadExtension instead, ' .
			'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
		);
		
		return;
	
	} else {
		
		die('This version of the ShortestWikiPath extension requires MediaWiki 1.25+');
		
	}
	
?>
