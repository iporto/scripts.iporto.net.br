<?php
/*
*
* /usr/bin/php -d safe_mode=Off /root/scripts/scripts.iporto.net.br/job/run.backup.db.php
*
*/
include_once('class/class.backup.db.php');

$Base	  		 = __DIR__;
$BaseConf 		 = $Base . '/conf/run.backup.db.conf';
if( !is_file($BaseConf)):
	die("\nUnable to load configuration files at:\n >> ".$BaseConf);
endif;
$BaseConfContent = file_get_contents($BaseConf);

$Backups = BackupConf::Parser( $BaseConfContent);

foreach( $Backups as $BackupRun):
	if( @$BackupRun['run_before'] != ''):
		system( $BackupRun['run_before']);
	endif;
	
	$Obj = new BackupDb( 
	        	@$BackupRun['db_host'], 
	        	@$BackupRun['db_user'], 
	        	@$BackupRun['db_pwds'], 
	        	@$BackupRun['db_database'], 
	        	@$BackupRun['db_database_skiptable'],
	        	@$BackupRun['backup_dir']
	      );
	$Obj
	->setBackupZip( 		@$BackupRun['backup_zip'])
	->setBackupDayOfWeek( 	@$BackupRun['backup_dayOfweek'])
	->setBackupRetention( 	@$BackupRun['backup_retention'])
	->setBackupTimeToRun( 	@$BackupRun['backup_timeToRun'])
	->setBackupEmail( 		@$BackupRun['backup_email'])
	->Bkp()
	->sendMailResults();

	if( @$BackupRun['run_afeter'] != ''):
		system( $BackupRun['run_afeter']);
	endif;
endforeach;
?>