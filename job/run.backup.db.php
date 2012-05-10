<?php
/*
 * Simple class to monitor one or an list of process
*
* How to install:
* mkdir /root/scripts/ && touch /root/scripts/run.process.monit.conf && vi /root/scripts/run.process.monit.conf
* # -----
* [iPORTO_ProcessMonit]
* #process_pid: /var/run/process.pid
* #alert_email: monitor@iporto.net.br
* #run_start: /etc/init.d/programtostart start
* #run_stop: /etc/init.d/programtostart stop
* ;
*
*/
# *       */1       *       *       *       /usr/bin/php -d safe_mode=Off /root/scripts/job/run.backup.db.php
# php -d safe_mode=Off run.backup.db.php


$Base	  		 = __DIR__;
$BaseConf 		 = $Base . '/run.backup.db.conf';
if( !is_file($BaseConf)):
	die("Unable to load configuration files\n");
endif;
$BaseConfContent = file_get_contents($BaseConf);

$Backups = BackupConf::Parser( $BaseConfContent);
foreach( $Backups as $BackupRun):
	if( @$BackupRun['run_before'] != ''):
		system( $BackupRun['run_before']);
	endif;
	
	$Obj = new BackupDb( 
	        	$BackupRun['db_host'], 
	        	$BackupRun['db_user'], 
	        	$BackupRun['db_pwds'], 
	        	$BackupRun['db_database'], 
	        	@$BackupRun['db_database_skiptable'],
	        	$BackupRun['backup_dir']
	      );
	$Obj
	->setBackupZip( @$BackupRun['backup_zip'])
	->setBackupDayOfWeek( @$BackupRun['backup_dayOfweek'])
	->setBackupRetention( @$BackupRun['backup_retention'])
	->setBackupTimeToRun( @$BackupRun['backup_timeToRun'])
	->setBackupEmail( @$BackupRun['backup_email'])
	->Bkp()
	->sendMailResults();

	if( @$BackupRun['run_afeter'] != ''):
		system( $BackupRun['run_afeter']);
	endif;
endforeach;

// CLASSE SIMPLES PARA EFETUAR BACKUP DE BASES
// IPORTO.COM - 2010-09-22
// IPORTO.COM - 2012-03-14
class BackupDb{
	function __construct( $conn_host = '', $conn_user = '', $conn_pwd = '', $conn_base = '', $conn_base_skiptable = '', $backup_dir = ''){
		
		$this ->conn_host 		= $conn_host;
		$this ->conn_user 		= $conn_user;
		$this ->conn_pwd 		= $conn_pwd;
		$this ->conn_base 		= $conn_base;
		$this ->conn_base_skiptable 	= explode(',', $conn_base_skiptable);
		
		if( $this ->conn_base != '-'):
			$this ->conn_base 			= explode(',', $this ->conn_base);
		endif;

		$this ->dont_save 		= array('mysql','test','#mysql50#lost+found');
		
		$this ->setDbs			= array();
		if( $backup_dir == ''):
			$this ->setBkpDir 	= '/home/backups/';
		else:
			$this ->setBkpDir 	= $backup_dir;
		endif;
		$this ->setBkpDirLog 	= $backup_dir.'logs/';
		if( @stristr( $this ->conn_base, ',') === false):
			$this ->setBkpDirLogPim = $this ->conn_host.'-'.$this ->conn_user.'-'.md5($this ->conn_base);
		else:
			$this ->setBkpDirLogPim = $this ->conn_host.'-'.$this ->conn_user.'-'.md5(implode(',', $this ->conn_base));
		endif;
		$this ->backup_retention= array('24');
		$this ->backup_dayOfweek= array('1','2','3','4','5','6','7');
		$this ->backup_timeToRun= array('05');
		$this ->backup_zip 		= 'Y';

		$this ->backup_log_content = '';
		
		$this ->Conn();
	}
	private function Conn()
	{		
	    $this ->Conn = @mysql_connect( $this ->conn_host, $this ->conn_user, $this ->conn_pwd);
		if( !$this ->Conn):
			$this ->backup_log_content = 'Erro (IMPOSSÍVEL CONECTAR) - BACKUP em '.date('Y-m-d H:i:s').' para '.$this ->conn_host;
		
			echo 'ERRO CONECTANDO -->'.$this ->conn_host;
			exit();
		endif;
	}
	
	public function setBackupZip( $Vlr){
	    if( $Vlr != ''):
	    	$this ->backup_zip = $Vlr;
	    endif;
	    return $this;
	}
	public function setBackupRetention( $Period){
	    if( !is_array( $Period)):
	    	$this ->backup_retention = '';
	    	$this ->backup_retention = explode(',', $Period);
	    endif;
	    return $this;
	}
	public function setBackupDayOfWeek( $Period){
	    if( !is_array( $Period)):
	    	$this ->backup_dayOfweek = '';
	    	$this ->backup_dayOfweek = explode(',', $Period);
	    endif;
	    return $this;
	}
	public function setBackupTimeToRun( $Period){
	    if( !is_array( $Period)):
	    	$this ->backup_timeToRun = '';
	    	$this ->backup_timeToRun = explode(',', $Period);
	    endif;
	    return $this;
	}
	public function setBackupEmail( $Vlr){
	    if( $Vlr != ''):
	    	$this ->backup_email = $Vlr;
	    endif;
	    return $this;
	}
	public function getDbs()
	{
		$GetDbs 		= @mysql_query("SHOW DATABASES;", $this ->Conn);
		
		$this ->setDbs 	= array();
		if( !$GetDbs):
			$this ->backup_log_content = 'Erro (IMPOSSÍVEL SELECIONAR BASES) - BACKUP em '.date('Y-m-d H:i:s').' para '.$this ->conn_host;
		else:
			while( $Db 	= mysql_fetch_assoc( $GetDbs)):
				if(!in_array( $Db['Database'], $this ->dont_save)):
					if( is_array( $this ->conn_base)):
						if( in_array( $Db['Database'], $this ->conn_base)):
							$this ->setDbs[] = $Db['Database'];
						endif;
					else:
						$this ->setDbs[] = $Db['Database'];
					endif;
				endif;
			endwhile;
		endif;
	}
	public function getTables( $Db)
	{
		if( $Db == ''):
			return '';
		endif;
		@mysql_query("USE ".$Db.";", $this ->Conn);
		
		$GetTables 		= @mysql_query("SHOW TABLES;", $this ->Conn); $this ->setTbls = array();
		
		$this ->setTbls = array();
		if( !$GetTables):
			$this ->backup_log_content = 'Erro (IMPOSSÍVEL SELECIONAR TABELAS) - BACKUP em '.date('Y-m-d H:i:s').' para '.$this ->conn_host;
		else:
			$Cnt = 0;
			while( $Tb 	= mysql_fetch_assoc( $GetTables)):
				if(!in_array( $Db, $this ->dont_save)):
					$GetTableStatus = mysql_query("SHOW TABLE STATUS LIKE '".$Tb['Tables_in_'.$Db]."';", $this ->Conn);
			
					while( $Sts 	= mysql_fetch_assoc( $GetTableStatus)):
						if( !in_array( trim( $Sts['Name']), $this ->conn_base_skiptable)):
							$this ->setTbls[$Cnt] = $Sts;
						endif;
					endwhile;
				endif;
				
			$Cnt ++;
			endwhile;
		endif;
	}
	public function Bkp()
	{	    
	    if( in_array( date('w'), $this ->backup_dayOfweek)):
      		
	    	if( in_array( date('H'), $this ->backup_timeToRun)):
		 		
	    		$this ->getDbs();
				// LOG DE QUE O BACKUP FOI EXECUTADO
	    		$CanCreateBackup = true;
	    		$LogBackupRun 	 = $this ->setBkpDirLog.$this ->setBkpDirLogPim.'.log';
				
	    		if( is_file( $LogBackupRun)):
	    			$LogBackupRunTime = filemtime( $LogBackupRun);
	    			
	    			if( date('H') == date( 'H',$LogBackupRunTime)):
	    				if( (time() - (1200)) < $LogBackupRunTime):
	    					$CanCreateBackup = false;
	    				endif;
	    			endif;
	    		endif;

	    		if( $CanCreateBackup):
		    		system('/bin/mkdir -p  '.$this ->setBkpDirLog);
					system('/bin/echo "'.@json_encode($this).'" > '.$LogBackupRun);
					
					echo "Read system logs: ".$LogBackupRun;
					echo "\n";
					  		
			  		// FAZENDO BACKUP DAS BASES
			  		foreach( $this ->setDbs as $Db):
			  			$this ->backup_log_content = "";
			  		
				  		# CRIAR DIRETORIO DE BACKUP
				  		for( $D = 1; $D < (count( $this ->backup_retention) +1); $D++):
					  		system('/bin/mkdir -p '.$this ->setBkpDir.$this ->conn_host.'/dump/'.$Db.'/'.$D.'/');
			  				system('/bin/mkdir -p '.$this ->setBkpDir.$this ->conn_host.'/dump/'.$Db.'/tmp/');
					  			
					  		$this ->getTables( $Db);
					  		
					  		foreach( $this ->setTbls as $Tbl):
					  		
						  		$DbBkpName1 = $this ->setBkpDir.$this ->conn_host.'/dump/'.$Db.'/tmp/'.$Tbl['Name'].'.sql';
					  			$DbBkpName2 = $this ->setBkpDir.$this ->conn_host.'/dump/'.$Db.'/'.$D.'/'.$Tbl['Name'].'.sql';			
						  		
					  			exec('/bin/echo "'.$DbBkpName2.'" >> '.$LogBackupRun);
						  			
						  		$CanCreateBackup 		= true;
						  		$CreateBackupTimeDiff 	= 60 * 60 * $this ->backup_retention[($D-1)];

						  		if( is_file( $DbBkpName2.( $this ->backup_zip == 'Y' ? '.tar.gz' : ''))):
							  		if( (time() - ($CreateBackupTimeDiff)) < filemtime( $DbBkpName2.( $this ->backup_zip == 'Y' ? '.tar.gz' : ''))):
							  			$CanCreateBackup = false;

							  			echo "Is not time to Run >> ".$DbBkpName2.( $this ->backup_zip == 'Y' ? '.tar.gz' : '');
							  			echo "\n";						  				
							  		endif;		
						  		endif;

						  		if( $CanCreateBackup):
							  		if( $D == 1):
							  			system("/usr/bin/mysqldump -h ".$this ->conn_host." -u ".$this ->conn_user." -p'".$this ->conn_pwd."' ".$Db." ".$Tbl['Name']." > ".$DbBkpName1);
							  		endif;
							  		if( $this ->backup_zip == 'Y'):
							  			system("/bin/tar -czf ".$DbBkpName2.".tar.gz ".$DbBkpName1);
							  		else:
							  			copy( $DbBkpName1,$DbBkpName2);
							  		endif;
						  		endif;
					  		endforeach;
					  		unset( $Rls);
					  		exec ( '/bin/ls -lAht '.$this ->setBkpDir.$this ->conn_host.'/dump/'.$Db.'/'.$D.'/', $Rls, $a2);
	
					  		$this ->backup_log_content .= "BACKUP em ".date('Y-m-d H:i:s')." para ".$this ->conn_host." \n\n ";
					  		if( is_array($Rls)):
						  		foreach( $Rls as $Dtls):
						  			$this ->backup_log_content .= $this ->setBkpDir.$this ->conn_host."/dump/".$Db."/".$D." >> ".$Dtls;
					  				$this ->backup_log_content .= "\n ";
						  		endforeach;
					  		endif;
				  		endfor;
				  		
				  		system('/bin/rm -rf '.$this ->setBkpDir.$this ->conn_host.'/dump/'.$Db.'/tmp');
				  		
			  		endforeach;
		  		else:
		  			echo "backup of (".(is_array( $this ->setDbs) ? implode('|', $this ->setDbs) : $this ->setDbs).") allready started \n";
		  			echo $LogBackupRun;
		  			echo "\n";
		  		endif;			  		
			endif;
		endif;
		
		return $this;
	}
	public function sendMailResults(){
	    if( $this ->backup_email != '' AND $this ->backup_log_content != ''):
		    $HEADERS  = "From: monitor@iporto.net.br <monitor@iporto.net.br> \n";
		    $HEADERS .= "To: ".$this ->backup_email."\n";
		    $HEADERS .= "Return-Path: consultoria@iporto.net.br \n";
		    $HEADERS .= "Content-Type: text/plain; charset=ISO-8859-1 \n";
		
		    mail( $this ->backup_email, 'BACKUP: Mysql ( host: '.$this ->conn_host.' date: '.date('d/M H:i').' base: '.(is_array( $this ->conn_base) ? implode('|', $this ->conn_base) : $this ->conn_base).')', $this ->backup_log_content, $HEADERS );
	    endif;
	}	
}





/*
 *
 * This class mount/create an array structure to create backup list;
 * Usage --> BackupConf::Parser( '[BackupDb]{flags};');
 *
*/
class BackupConf{

    public static function Parser( $Content = ''){

        $ConfBackup = array();
        preg_match_all("/\[iPORTO_BackupDb\](.*?)\;/is", $Content, $e);

        if(isset( $e[1])):
	        $ConfList = $e[1];
	        $ConfUsed = 0;
	        
	        foreach( $ConfList as $Conf):
		        $Conf = trim($Conf);
		        $ConfDetails = explode("\n", $Conf);
		        foreach( $ConfDetails as $Details):
			        $Details = trim( $Details);
			        if( strlen( $Details) >1):
				        if( substr( $Details, 0, 1) != '#'):
					        preg_match('/(.*)\:\s(.*)/is', $Details, $ConfDetailsUse);
					        if( isset( $ConfDetailsUse[1]) AND isset( $ConfDetailsUse[2])):
					        	$ConfBackup[ $ConfUsed][ trim( $ConfDetailsUse[1])] = trim( $ConfDetailsUse[2]);
					        endif;
				        endif;
			        endif;
		        endforeach;
		        $ConfUsed ++;
	        endforeach;
        endif;
        return $ConfBackup;
    }
}
?>