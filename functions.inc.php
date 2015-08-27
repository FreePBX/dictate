<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
function dictate_get_config($engine) {
	$modulename = 'dictate';

	// This generates the dialplan
	global $ext;
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
					}
				}
			}
		break;
	}
}

function dictate_dodictate($c) {
	global $ext;

	$id = "app-dictate-record"; // The context to be included

	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal
	$ext->add($id, $c, '', new ext_answer(''));
	$ext->add($id, $c, '', new ext_macro('user-callerid'));
	$ext->add($id, $c, '', new ext_NoOp('CallerID is ${AMPUSER}'));
	$ext->add($id, $c, '', new ext_setvar('DICTENABLED','${DB(AMPUSER/${AMPUSER}/dictate/enabled)}'));
	$ext->add($id, $c, '', new ext_gotoif('$[$["x${DICTENABLED}"="x"]|$["x${DICTENABLED}"="xdisabled"]]','nodict', 'dictok'));
	$ext->add($id, $c, 'nodict', new ext_playback('feature-not-avail-line'));
	$ext->add($id, $c, '', new ext_hangup(''));
	$ext->add($id, $c, 'dictok', new ext_dictate(\FreePBX::Config()->get('ASTVARLIBDIR').'/sounds/dictate/${AMPUSER}'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));
}

function dictate_senddictate($c) {
	global $ext;

	$id = "app-dictate-send"; // The context to be included
	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal
	$ext->add($id, $c, '', new ext_answer(''));
	$ext->add($id, $c, '', new ext_macro('user-callerid'));
	$ext->add($id, $c, '', new ext_NoOp('CallerID is ${AMPUSER}'));
	$ext->add($id, $c, '', new ext_setvar('DICTENABLED','${DB(AMPUSER/${AMPUSER}/dictate/enabled)}'));
	$ext->add($id, $c, '', new ext_gotoif('$[$["x${DICTENABLED}"="x"]|$["x${DICTENABLED}"="xdisabled"]]','nodict', 'dictok'));
	$ext->add($id, $c, 'nodict', new ext_playback('feature-not-avail-line'));
	$ext->add($id, $c, '', new ext_hangup(''));
	$ext->add($id, $c, 'dictok', new ext_read('DICTFILE','enter-filename-short'));
	$ext->add($id, $c, '', new ext_setvar('DICTEMAIL','${DB(AMPUSER/${AMPUSER}/dictate/email)}'));
	$ext->add($id, $c, '', new ext_setvar('DICTFMT','${DB(AMPUSER/${AMPUSER}/dictate/format)}'));
	$ext->add($id, $c, '', new ext_setvar('DICTFROM','${DB(AMPUSER/${AMPUSER}/dictate/from)}'));
	$ext->add($id, $c, '', new ext_setvar('NAME','${DB(AMPUSER/${AMPUSER}/cidname)}'));
	$ext->add($id, $c, '', new ext_playback('dictation-being-processed'));
	$ext->add($id, $c, '', new ext_system(\FreePBX::Config()->get('ASTVARLIBDIR').'/bin/audio-email.pl --file '.\FreePBX::Config()->get('ASTVARLIBDIR').'/sounds/dictate/${AMPUSER}/${DICTFILE}.raw --attachment dict-${DICTFILE} --format ${DICTFMT} --to ${DICTEMAIL} --from ${DICTFROM} --subject "Dictation from ${NAME} Attached"'));
	$ext->add($id, $c, '', new ext_playback('dictation-sent'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));
}

function dictate_configpageinit($pagename) {
	global $currentcomponent;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

	// We only want to hook 'users' or 'extensions' pages.
	if ($pagename != 'users' && $pagename != 'extensions')
		return true;
	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page.
	if ($tech_hardware != null || $pagename == 'users') {
		dictation_applyhooks();
		$currentcomponent->addprocessfunc('dictate_configprocess', 8);
	} elseif ($action=="add") {
		// We don't need to display anything on an 'add', but we do need to handle returned data.
		$currentcomponent->addprocessfunc('dictate_configprocess', 8);
	} elseif ($extdisplay != '') {
		// We're now viewing an extension, so we need to display _and_ process.
		dictation_applyhooks();
		$currentcomponent->addprocessfunc('dictate_configprocess', 8);
	}
}


function dictation_applyhooks() {
	global $currentcomponent;

	$currentcomponent->addoptlistitem('dictena', 'enabled', _('Enabled'));
	$currentcomponent->addoptlistitem('dictena', 'disabled',_('Disabled'));
	$currentcomponent->setoptlistopts('dictena', 'sort', false);

	$currentcomponent->addoptlistitem('dictfmt', 'ogg', 'Ogg Vorbis');
	$currentcomponent->addoptlistitem('dictfmt', 'gsm', 'GSM');
	$currentcomponent->addoptlistitem('dictfmt', 'wav', 'WAV');
	$currentcomponent->setoptlistopts('dictfmt', 'sort', false);
	// Add the 'process' function - this gets called when the page is loaded, to hook into
	// displaying stuff on the page.
	$currentcomponent->addguifunc('dictate_configpageload');

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
		$from = $dibox['from'];

		$section = _('Dictation Services');
		$msgInvalidEmail = _('Please enter a valid Email Address');
		$category = "advanced";
		$currentcomponent->addguielem($section, new gui_selectbox('dictenabled', $currentcomponent->getoptlist('dictena'), $dodict, _('Dictation Service'), '', false),$category);
		$currentcomponent->addguielem($section, new gui_selectbox('dictformat', $currentcomponent->getoptlist('dictfmt'), $format, _('Dictation Format'), '', false),$category);
		$currentcomponent->addguielem($section, new gui_textbox('dictemail', $email, _('Email Address'), _('The email address that completed dictations are sent to.'), "!isEmail()", $msgInvalidEmail, true),$category);
		$currentcomponent->addguielem($section, new gui_textbox('dictfrom', $from, _('From Address'), _('The email address that completed dictations are sent FROM. Format is "A Persons Name &lt;email@address.com&gt;", without quotes, or just a plain email address.'), "!isEmail()", $msgInvalidEmail, true),$category);
	}
}

function dictate_configprocess() {
	//create vars from the request
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$dictenabled = isset($_REQUEST['dictenabled'])?$_REQUEST['dictenabled']:null;
	$dictemail = isset($_REQUEST['dictemail'])?$_REQUEST['dictemail']:null;
	$dictfrom = isset($_REQUEST['dictfrom'])?$_REQUEST['dictfrom']:null;
	$dictformat = isset($_REQUEST['dictformat'])?$_REQUEST['dictformat']:null;

	if ($ext==='') {
		$extdisplay = $extn;
	} else {
		$extdisplay = $ext;
	}
	if ($action == "add" || $action == "edit") {
		if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
			dictate_update($extdisplay, $dictenabled, $dictformat, $dictemail, $dictfrom);
		}
	} elseif ($action == "del") {
		dictate_del($extdisplay);
	}
}

function dictate_get($xtn) {
	global $astman;

	// Retrieve the dictation configuraiton from this user from ASTDB
	if ($astman) {
		$ena = $astman->database_get("AMPUSER",$xtn."/dictate/enabled");
		$format = $astman->database_get("AMPUSER",$xtn."/dictate/format");
		$email = $astman->database_get("AMPUSER",$xtn."/dictate/email");
		$from = base64_decode($astman->database_get("AMPUSER",$xtn."/dictate/from"));
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}
	// If it's blank, set it to disabled
	if (!$ena) { $ena = "disabled"; }
	// Default format is ogg
	if (!$format) { $format = "ogg"; }

	// No from address?
	if (!$from) {
		$from = "dictate@freepbx.org";
	}

	return array('enabled' => $ena, 'format' => $format, 'email' => $email, 'from' => $from);
}

function dictate_update($ext, $ena, $fmt, $email, $from) {
	global $astman;

	if ($astman) {
		// Update the settings in ASTDB
		$astman->database_put("AMPUSER",$ext."/dictate/enabled",$ena);
		$astman->database_put("AMPUSER",$ext."/dictate/format",$fmt);
		$astman->database_put("AMPUSER",$ext."/dictate/email",$email);
		$astman->database_put("AMPUSER",$ext."/dictate/from",base64_encode($from));
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}
}

function dictate_del($ext) {
	global $astman;

	// Clean up the tree when the user is deleted
	if ($astman) {
		$astman->database_deltree("AMPUSER/$ext/dictate");
	} else {
		fatal("Cannot connect to Asterisk Manager with ".$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"]);
	}
}

