<?php

function dictate_get_config($engine) {
	$modulename = 'dictate';
	
	// This generates the dialplan
	global $ext;  
	global $asterisk_conf;
	switch($engine) {
		case "asterisk":
			if (is_array($featurelist = featurecodes_getModuleFeatures($modulename))) {
				foreach($featurelist as $item) {
					$featurename = $item['featurename'];
					$fname = $modulename.'_'.$featurename;
					if (function_exists($fname)) {
						$fcc = new featurecode($modulename, $featurename);
						$fc = $fcc->getCodeActive();
						unset($fcc);
						
						if ($fc != '')
							$fname($fc);
					} else {
						$ext->add('from-internal-additional', 'debug', '', new ext_noop($modulename.": No func $fname"));
						var_dump($item);
					}	
				}
			}
		break;
	}
}

function dictate_dodictate($c) {
	global $ext;
	global $asterisk_conf;

	$id = "app-dictate-record"; // The context to be included

	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal
	$ext->add($id, $c, '', new ext_answer(''));
	$ext->add($id, $c, '', new ext_macro('user-callerid'));
	$ext->add($id, $c, '', new ext_NoOp('CallerID is ${CALLERID(num)}'));
	$ext->add($id, $c, '', new ext_setvar('DICTENABLED','${DB(AMPUSER/${CALLERID(num)}/dictate/enabled)}'));
	$ext->add($id, $c, '', new ext_gotoif('$[$["x${DICTENABLED}"="x"]|$["x${DICTENABLED}"="xdisabled"]]','nodict', 'dictok'));
	$ext->add($id, $c, 'nodict', new ext_playback('feature-not-avail-line'));
	$ext->add($id, $c, '', new ext_hangup(''));
	$ext->add($id, $c, 'dictok', new ext_dictate($asterisk_conf['astvarlibdir'].'/sounds/dictate/${CALLERID(num)}'));
	$ext->add($id, $c, '', new ext_macro('dodictate'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));
}

function dictate_senddictate($c) {
	global $ext;
	global $asterisk_conf;

	$id = "app-dictate-send"; // The context to be included
	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal
	$ext->add($id, $c, '', new ext_answer(''));
	$ext->add($id, $c, '', new ext_macro('user-callerid'));
	$ext->add($id, $c, '', new ext_NoOp('CallerID is ${CALLERID(num)}'));
	$ext->add($id, $c, '', new ext_setvar('DICTENABLED','${DB(AMPUSER/${CALLERID(num)}/dictate/enabled)}'));
	$ext->add($id, $c, '', new ext_gotoif('$[$["x${DICTENABLED}"="x"]|$["x${DICTENABLED}"="xdisabled"]]','nodict', 'dictok'));
	$ext->add($id, $c, 'nodict', new ext_playback('feature-not-avail-line'));
	$ext->add($id, $c, '', new ext_hangup(''));
	$ext->add($id, $c, 'dictok', new ext_read('DICTFILE','enter-filename-short'));
	$ext->add($id, $c, '', new ext_setvar('DICTEMAIL','${DB(AMPUSER/${CALLERID(num)}/dictate/email)}'));
	$ext->add($id, $c, '', new ext_setvar('DICTFMT','${DB(AMPUSER/${CALLERID(num)}/dictate/format)}'));
	$ext->add($id, $c, '', new ext_setvar('NAME','${DB(AMPUSER/${CALLERID(num)}/cidname)}'));
	$ext->add($id, $c, '', new ext_playback('dictation-being-processed'));
	$ext->add($id, $c, '', new ext_system($asterisk_conf['astvarlibdir'].'/bin/audio-email.pl --file '.$asterisk_conf['astvarlibdir'].'/sounds/dictate/${CALLERID(num)}/${DICTFILE}.raw --attachment dict-${DICTFILE} --format ${DICTFMT} --to ${DICTEMAIL} --subject "Dictation from ${NAME} Attached"'));
	$ext->add($id, $c, '', new ext_playback('dictation-sent'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));
}

function dictate_configpageinit($dispnum) {
	global $currentcomponent;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

	if ( $dispnum == 'users' || $dispnum == 'extensions' && ($action != 'del' && ($extdisplay != '' || $tech_hardware != '') ) )  {
		// Setup the drop down boxes here.
		$currentcomponent->addoptlistitem('dictena', 'enabled', 'Enabled');
		$currentcomponent->addoptlistitem('dictena', 'disabled', 'Disabled');
		$currentcomponent->setoptlistopts('dictena', 'sort', false);

		$currentcomponent->addoptlistitem('dictfmt', 'ogg', 'Ogg Vorbis');
		$currentcomponent->addoptlistitem('dictfmt', 'gsm', 'GSM');
		$currentcomponent->addoptlistitem('dictfmt', 'wav', 'WAV');
		$currentcomponent->setoptlistopts('dictfmt', 'sort', false);
		// Add the 'process' function - this gets called when the page is loaded, to hook into 
		// displaying stuff on the page.
		$currentcomponent->addguifunc('dictate_configpageload');

		// We also care about getting data back from the 'submit', so we need to hook in here, too.
		$currentcomponent->addprocessfunc('dictate_configprocess');
	}
}

// This is called before the page is actually displayed, so we can use addguielem().
function dictate_configpageload() {
	global $currentcomponent;

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	
	// Don't display this stuff it it's on a 'This xtn has been deleted' page.
	if ($action != 'del') {
		$dibox = dictate_get($extdisplay);
		// Defaults are in dictate_get, if they're not set.
		$dodict = $dibox['enabled'];
		$email = $dibox['email'];
		$format = $dibox['format'];

		$section = _('Dictation Services');
		$msgInvalidEmail = _('Please enter a valid Email Address');
		$currentcomponent->addguielem($section, new gui_selectbox('dictenabled', $currentcomponent->getoptlist('dictena'), $dodict, _('Dictation Service'), '', false));
		$currentcomponent->addguielem($section, new gui_selectbox('dictformat', $currentcomponent->getoptlist('dictfmt'), $format, _('Dictation Format'), '', false));
		$currentcomponent->addguielem($section, new gui_textbox('dictemail', $email, _('Email Address'), _('The email address that completed dictations are sent to.'), "!isEmail()", $msgInvalidEmail, true));
	}
}

function dictate_configprocess() {
	//create vars from the request
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$dictenabled = isset($_REQUEST['dictenabled'])?$_REQUEST['dictenabled']:null;
	$dictemail = isset($_REQUEST['dictemail'])?$_REQUEST['dictemail']:null;
	$dictformat = isset($_REQUEST['dictformat'])?$_REQUEST['dictformat']:null;

	if ($action == "add" || $action == "edit") {
		dictate_update($ext, $dictenabled, $dictformat, $dictemail);
	} elseif ($action == "del") {
		dictate_del($extdisplay);
	}
}

function dictate_get($xtn) {
	global $astman;

	// Retrieve the dictation configuraiton from this user from ASTDB
	$ena = $astman->database_get("AMPUSER",$xtn."/dictate/enabled");
	$format = $astman->database_get("AMPUSER",$xtn."/dictate/format");
	$email = $astman->database_get("AMPUSER",$xtn."/dictate/email");
	// If it's blank, set it to disabled
	if (!$ena) { $ena = "disabled"; }
	// Default format is ogg
	if (!$format) { $foramt = "ogg"; }

	return array('enabled' => $ena, 'format' => $format, 'email' => $email);
}

function dictate_update($ext, $ena, $fmt, $email) {
	global $astman;
	
	// Update the settings in ASTDB
	$astman->database_put("AMPUSER",$ext."/dictate/enabled",$ena);
	$astman->database_put("AMPUSER",$ext."/dictate/format",$fmt);
	$astman->database_put("AMPUSER",$ext."/dictate/email",$email);
}

function dictate_del($ext) {
	global $astman;

	// Clean up the tree when the user is deleted
	$astman->database_deltree("AMPUSER/$ext/dictate");
}

?>
