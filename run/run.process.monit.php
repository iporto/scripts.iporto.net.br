<?php
/*
*
* /usr/bin/php -d safe_mode=Off /root/scripts/scripts.iporto.net.br/run/run.process.monit.php
*
*/
include_once('class/class.process.monit.php');

$phpversion		 = phpversion();
$phpversion		 = explode('.',$phpversion);
if( $phpversion[0] <= 5 && $phpversion[1] <= 1):
	$Base	 = '/root/scripts/scripts.iporto.net.br/run';
else:
	$Base	 = __DIR__;
endif;

$BaseConf 		 = $Base . '/conf/run.process.monit.conf';
if( !is_file($BaseConf)):
	die("\nUnable to load configuration files at:\n >> ".$BaseConf);
endif;

$BaseConf 		 = $Base . '/conf/run.process.monit.conf';
if( !is_file($BaseConf)):
	die("\nUnable to load configuration files\n");
endif;
$BaseConfContent = file_get_contents($BaseConf);

$Monits = ProcessMonitConf::Parser( $BaseConfContent);

if( !is_array( $Monits)):
	die("No data to run\n");
endif;

foreach( $Monits as $Monit):
	$Obj = new ProcessMonit( @$Monit['process_type'], @$Monit['process_pid'], @$Monit['alert_email'], @$Monit['run_start'], @$Monit['run_stop'], @$Monit['run_chdir']);
	$Obj ->Monit() ->sendMailResults();
endforeach;
?>
