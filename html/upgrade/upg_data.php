<?php

include('bootstrap.php');
require('../config.php');

$db = mysql_connect($cfg['db_host'], $cfg['db_user'], $cfg['db_pass']);
mysql_select_db($cfg['db_name']);

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

$enabled_step = 5;
$current_step = Get::gReq('cur_step', DOTY_INT);
$upg_step = Get::gReq('upg_step', DOTY_INT);

if ($_SESSION['start_version'] >= 3000 && $_SESSION['start_version'] < 4000) {
	echo 'error: version (' . $_SESSION['start_version'] . ') not supported for upgrade: too old (v3)';
	die();
}

if ( $current_step != $enabled_step ) {
	echo 'error: procedure must be called from upgrade step ' . $enabled_step . ' only!!';
	die();
}

if (!empty($_SESSION['to_upgrade_arr'])) {
	$to_upgrade_arr =$_SESSION['to_upgrade_arr'];
}
else {
	$to_upgrade_arr =getToUpgradeArray();
}

$last_ver =getVersionIntNumber($GLOBALS['cfg']['endversion']);
if ($_SESSION['upgrade_ok']) {
	$current_ver =$to_upgrade_arr[$upg_step-1];
	if ($current_ver != $last_ver) {
		$docebo_version =$GLOBALS['cfg']['versions'][$current_ver];
	}
	else {
		$docebo_version =$GLOBALS['cfg']['endversion'];
	}
	$upgrade_msg .="Upgrading to version: ".$docebo_version;


	// --- pre upgrade -----------------------------------------------------------
	$fn =_upgrader_.'/data/upg_data/'.$current_ver.'_pre.php';
	if (file_exists($fn)) {
		require($fn);
		$func ='preUpgrade'.$current_ver;
		if (function_exists($func)) {
			$res =$func();
			if (!$res) { $_SESSION['upgrade_ok']=false; }
		}
	}


	if ($_SESSION['upgrade_ok']) {
		// --- sql upgrade -----------------------------------------------------------
		$fn =_upgrader_.'/data/upg_data/'.$current_ver.'_db.sql';
		if (file_exists($fn)) {
			$res =importSqlFile($fn);
			if (!$res['ok']) {
				$_SESSION['upgrade_ok']=false;
			}
		}
	}

	if ($_SESSION['upgrade_ok']) {
		// --- post upgrade ----------------------------------------------------------
		$fn =_upgrader_.'/data/upg_data/'.$current_ver.'_post.php';
		if (file_exists($fn)) {
			require($fn);
			$func ='postUpgrade'.$current_ver;
			if (function_exists($func)) {
				$res =$func();
				if (!$res) {
					$_SESSION['upgrade_ok']=false;
				}
			}
		}
	}


	if ($_SESSION['upgrade_ok']) {
		// --- roles -----------------------------------------------------------------
		require_once(_installer_.'/lib/lib.role.php');
		$fn =_upgrader_.'/data/upg_data/'.$current_ver.'_role.php';
		if (file_exists($fn)) {
			require($fn);
			$func ='upgradeUsersRoles'.$current_ver;
			if (function_exists($func)) {
				$role_list =$func();
				if (!empty($role_list)) {
					$role_list_arr =explode("\n", $role_list);
					$oc0 =getGroupIdst('/oc_0'); // all users
					addRoles($roles, $oc0);
				}
			}
			$func ='upgradeGodAdminRoles'.$current_ver;
			if (function_exists($func)) {
				$role_list =$func();
				if (!empty($role_list)) {
					$role_list_arr =explode("\n", $role_list);
					$godadmin =getGroupIdst('/framework/level/godadmin'); // god admin
					addRoles($roles, $godadmin);
				}
			}
		}
	}

}


// Save version number if upgrade was successfull:
if ($_SESSION['upgrade_ok']) {
	$qtxt ="UPDATE core_setting SET param_value = '".$docebo_version."' WHERE param_name = 'core_version' ";
	$q =mysql_query($qtxt);
}

$GLOBALS['debug'] = $upgrade_msg
					. '<br/>' . 'Result: ' . ( $_SESSION['upgrade_ok'] ? 'OK ' : 'ERROR !!! ' )
					. '<br/>' . $GLOBALS['debug'];

echo $GLOBALS['debug'];

mysql_close($db);




// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------


?>