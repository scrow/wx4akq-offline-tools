<?php
/*	
#	This file is part of WX4AKQ Offline Tools
#	
#	Copyright (c) 2015-19, Steve Crow, Reid Barden
#	Licensed under the BSD 2-clause “Simplified” License
#	
#	For license information, see the LICENSE.md file or visit
#	http://wx4akq.org/software
*/
?><!doctype html>
<html>
<head>
	<title>WX4AKQ Offline Tools: XML Data Upload</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" type="text/css" href="resources/styles.css"/>
</head>
<body>
<div class="container">

<H1><?php includeLogo();?> XML Data Upload</H1>

<hr/>

<?php
	
	if((!isOnline()) && (!$Config['override_connect_detect'])) {
		echo('<P>You are not currently connected to the Internet.</P>');
		echo('<P><A HREF="index.php?form=menu">Return to the main menu.</A></P>');
		includeFooter();
		die();
	};

	// Validate the call sign and API key
	// Note that we will only refuse to upload a file IF the API key mismatches
	$ch = curl_init();
	$data = array(
		'user' => $Config['my_call'],
		'api_key' => $Config['api_key']
	);
	$opt_array = array(
		CURLOPT_URL => 'https://dev.wx4akq.org/ops/xml_apikey.php',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => $data
	);

	ob_flush(); flush();

	curl_setopt_array($ch, $opt_array);
	$result = curl_exec($ch);
	curl_close($ch);
	$xml = simplexml_load_string($result);

	if(trim($xml->result->auth)=='OK') {
		$api_passed = true;
	} else {
		$api_passed = false;
	};
	
	$rejected_due_to_acl = false;
	$rejected_due_to_hold = false;

	$api_key_required=array();
	$api_key_required[] = 'WX4AKQ_NCO_Report_Form';

	// Iterate through the queue folder and upload .xml files
	$uploaded = 0;
	$failed = 0;
	$skipped = 0;
	foreach(glob($Config['queue_folder'].DIRECTORY_SEPARATOR.'*.xml') as $filename) {

		$rejected_due_to_acl = false;
		$rejected_due_to_hold = false;

		// Let's make sure the API key and username are set to match current config
		$xml = new SimpleXMLElement(file_get_contents($filename));
		if(in_array($xml->variables->formname, $api_key_required)) {
			$rejected_due_to_acl = !$api_passed;
			$xml->variables->api_key = $Config['api_key'];
		};
		file_put_contents($filename, $xml->asXML());

		if(isset($xml->variables->formname) && ($xml->variables->formname=='WX4AKQ_NCO_Report_Form') && ($xml->variables->relayedvia=='6')) {
			$rejected_due_to_hold = true;
		};

		$success = true;

		if((!$rejected_due_to_acl) && (!$rejected_due_to_hold)) {
			$ch = curl_init();
			if(class_exists('CurlFile')) {
				$uploadFile = new CurlFile($filename, mime_content_type($filename), $filename);
				$uploadFile->setPostFilename(basename($filename));
			} else {
				if(!function_exists('curl_file_create')) {
				    function curl_file_create($filename, $mimetype = '', $postname = '') {
						return "@$filename;filename="
						. ($postname ?: basename($filename))
						. ($mimetype ? ";type=$mimetype" : '');
					}
				};
				$uploadFile = curl_file_create($filename, mime_content_type($filename), $filename);
			};
			$data = array(
				'attachment[]' => $uploadFile,
				'output' => 'xml'
				);
			$opt_array = array(
				CURLOPT_URL => $Config['upload_url'],
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST => 1,
				CURLOPT_POSTFIELDS => $data
			);
			curl_setopt_array($ch, $opt_array);
			$result = curl_exec($ch);
			curl_close($ch);
			$xml = simplexml_load_string($result);

			if(trim($xml->files->file['name']) !== trim(basename($filename))) {
				$success = false;
			};
			if(trim($xml->files->file) !== 'OK') {
				$success = false;
			};

		} else {
			$success = false;
		}; // end if(!$rejected_due_to_acl);

		if($success) {
			unlink($filename);
			$uploaded++;
		} else {
			if($rejected_due_to_hold) {
				$skipped++;
			} else {
				$failed++;
			};
		};
	}; // end foreach(glob(...
	echo('<P>'.$uploaded.' files uploaded, '.$skipped.' skipped, '.$failed.' failed.</P>');

	if($skipped > 0) {
		echo('<P>You have one or more reports on hold.  These reports were skipped.  Please <A HREF="?form=editreport">complete these reports and set message handling</A> and then retry the upload.</P>');
	};

	if($rejected > 0) {
		echo('<P>One or more files were rejected due to a bad API key. Check your <A HREF="?form=config">System Configuration</A> and try again.</P>');
	};
?>
<P><A HREF="index.php">Return to main menu.</A></P>

<?php includeFooter();?>
</div>
</body>
</html>
