<?php
	$cwd=getcwd();
	$dir=dirname($cwd);
	chdir($dir);
    require_once('global.php');
	$username=$vbulletin->userinfo['username'];
	echo $username;
    $isAdmin = (bool)($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']);
	if (!$isAdmin)
	{
		header("Location: ../index.php");
		exit();
	}
	chdir($cwd);
	echo "end";
?>