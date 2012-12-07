<?php
class BackupDb{
	function __construct( $conn_host = '', $conn_user = '', $conn_pwd = '', $conn_base = '', $conn_base_skiptable = '', $backup_dir = ''){
		
		$this ->conn_host 				= $conn_host;
		$this ->conn_user 				= $conn_user;
		$this ->conn_pwd 				= $conn_pwd;
		$this ->conn_base 				= $conn_base == '' ? '-' : $conn_base;
		$this ->conn_base_skiptable		= $conn_base_skiptable == '' ? '-' : $conn_base_skiptable;
		$this ->conn_base_skiptable 	= explode(',', $conn_base_skiptable);
		
		if( $this ->conn_base != '-'):
			$this ->conn_base 			= explode(',', $this ->conn_base);
		endif;

		$this ->dont_save 			= array('mysql','test','#mysql50#lost+found');
		
		$this ->setDbs				= array();
		
		if(!defined('DIR_SEPARATOR')):
			define('DIR_SEPARATOR', (PHP_OS == 'WINNT' ? '\\' : '/'));
		endif;

		if( $backup_dir == ''):
			if(PHP_OS == 'WINNT'):
				$this ->setBkpDir 		= 'c:'.DIR_SEPARATOR.'backup'.DIR_SEPARATOR;
			else:
				$this ->setBkpDir 		= DIR_SEPARATOR.'var'.DIR_SEPARATOR.'backup'.DIR_SEPARATOR;
			endif;
		else:
			$this ->setBkpDir 		= $backup_dir;
		endif;
		if(PHP_OS == 'WINNT'):
			$this ->CommandMkdir 	 = 'mkdir';
			$this ->CommandEcho	 	 = 'echo';
			$this ->CommandMysqlDump = 'mysqldump';
			$this ->CommandTar		 = 'tar';
			$this ->CommandLs		 = 'dir';
			$this ->CommandRm		 = 'rd /s /q';			
		else:
			$this ->CommandMkdir 	 = '/bin/mkdir -p';
			$this ->CommandEcho	 	 = '/bin/echo';
			$this ->CommandMysqlDump = '/usr/bin/mysqldump';
			$this ->CommandTar		 = '/bin/tar';
			$this ->CommandLs		 = '/bin/ls -lAht';
			$this ->CommandRm		 = '/bin/rm -rf';				
		endif;
		
		
		$this ->setBkpDirLog 		= $backup_dir.'logs'.DIR_SEPARATOR;
		if( @stristr( $this ->conn_base, ',') === false):
			$this ->setBkpDirLogPim = substr($this ->conn_host,0,25).'-'.$this ->conn_user.'-'.md5($this ->conn_base);
		else:
			$this ->setBkpDirLogPim = substr($this ->conn_host,0,25).'-'.$this ->conn_user.'-'.md5(implode(',', $this ->conn_base));
		endif;
		$this ->backup_retention	= array('24','120');
		$this ->backup_dayOfweek	= array('1','2','3','4','5','6','7');
		$this ->backup_timeToRun	= array('02');
		$this ->backup_zip 			= 'Y';

		$this ->backup_log_content 	= '';
		
		$this ->Conn();
	}
	private function Conn()
	{		
	    $this ->Conn = @mysql_connect( $this ->conn_host, $this ->conn_user, $this ->conn_pwd);
		if( !$this ->Conn):
			$this ->backup_log_content = 'Erro (IMPOSSÍVEL CONECTAR) - BACKUP em '.date('Y-m-d H:i:s').' para '.$this ->conn_host;
			
			echo PHP_EOL;
			echo $this ->backup_log_content;
			echo PHP_EOL;
			
			$this ->Log( $this ->backup_log_content);
			
			exit();
		endif;
	}
	private function Log( $String){
	 	@file_put_contents( $this ->setBkpDirLog.'backup.log', PHP_EOL.$String, FILE_APPEND);  
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
			
			$this ->Log( $this ->backup_log_content);
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
			
			$this ->Log( $this ->backup_log_content);
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
	    			if( !is_dir($this ->setBkpDirLog)):
		    			system($this ->CommandMkdir.' '.$this ->setBkpDirLog);
	    			endif;
					system($this ->CommandEcho .' "BACKUP INICIADO ('.date('d/m/Y H:i:s').'): '.$this ->conn_host.'" > "'.$LogBackupRun.'"');
					
					echo PHP_EOL;
					echo "Read system logs: ".$LogBackupRun;
					echo PHP_EOL;

			  		// FAZENDO BACKUP DAS BASES
			  		foreach( $this ->setDbs as $Db):
			  			$this ->backup_log_content = "";

				  		# CRIAR DIRETORIO DE BACKUP
				  		if( !is_dir($this ->CommandMkdir.' '.$this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.'tmp'.DIR_SEPARATOR)):
				  			 system($this ->CommandMkdir.' '.$this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.'tmp'.DIR_SEPARATOR);
				  		endif;

				  		echo PHP_EOL;
				  		
				  		for( $D = 1; $D < (count( $this ->backup_retention) +1); $D++):
				  		
					  		echo "RETER: ".$D." para ".$this ->conn_host." >> ".$Db;
					  		echo PHP_EOL;				  		
				  		
					  		if( !is_dir($this ->CommandMkdir.' '.$this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.$D)):
				  				system($this ->CommandMkdir.' '.$this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.$D.DIR_SEPARATOR);
				  			endif;

					  		$this ->getTables( $Db);
					  		
					  		foreach( $this ->setTbls as $Tbl):
					  		
						  		$DbBkpName1 = $this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.'tmp'.DIR_SEPARATOR.$Tbl['Name'].'.sql';
					  			$DbBkpName2 = $this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.$D.DIR_SEPARATOR.$Tbl['Name'].'.sql';			
						  		
					  			exec($this ->CommandEcho .' "'.$DbBkpName2.'" >> '.$LogBackupRun);
						  			
						  		$CanCreateBackup 		= true;
						  		$CreateBackupTimeDiff 	= 60 * 60 * $this ->backup_retention[($D-1)];

						  		if( is_file( $DbBkpName2.( $this ->backup_zip == 'Y' ? '.tar.gz' : ''))):
							  		if( (time() - ($CreateBackupTimeDiff)) < filemtime( $DbBkpName2.( $this ->backup_zip == 'Y' ? '.tar.gz' : ''))):
							  			$CanCreateBackup = false;

							  			echo "Is not time to Run >> ".$DbBkpName2.( $this ->backup_zip == 'Y' ? '.tar.gz' : '');
							  			echo PHP_EOL;						  				
							  		endif;		
						  		endif;

						  		if( $CanCreateBackup):
							  		if( $D == 1):
							  			system($this ->CommandMysqlDump." -h ".$this ->conn_host." -u ".$this ->conn_user." -p\"".$this ->conn_pwd."\" ".$Db." ".$Tbl['Name']." > ".$DbBkpName1);
							  		endif;
							  		if( $this ->backup_zip == 'Y'):
							  			system($this ->CommandTar." -czf ".$DbBkpName2.".tar.gz ".$DbBkpName1);
							  		else:
							  			if( is_file($DbBkpName1)):
							  				copy( $DbBkpName1, $DbBkpName2);
							  			endif;
							  		endif;
						  		endif;
					  		endforeach;
					  		unset( $Rls);
					  		exec ( $this ->CommandLs.' '.$this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.$D.DIR_SEPARATOR, $Rls, $a2);
	
					  		$this ->backup_log_content .= "BACKUP em ".date('Y-m-d H:i:s')." para ".$this ->conn_host." ".PHP_EOL.PHP_EOL;
					  		if( is_array($Rls)):
						  		foreach( $Rls as $Dtls):
						  			$this ->backup_log_content .= $this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR."dump".DIR_SEPARATOR.$Db.DIR_SEPARATOR.$D." >> ".$Dtls;
					  				$this ->backup_log_content .= PHP_EOL;
						  		endforeach;
					  		endif;
				  		endfor;
				  		
				  		$this ->Log( $this ->backup_log_content);
				  		
				  		system($this ->CommandRm.' '.$this ->setBkpDir.$this ->conn_host.DIR_SEPARATOR.'dump'.DIR_SEPARATOR.$Db.DIR_SEPARATOR.'tmp');
				  		
			  		endforeach;
		  		else:
		  			echo "backup of (".(is_array( $this ->setDbs) ? implode('|', $this ->setDbs) : $this ->setDbs).") allready started".PHP_EOL;
		  			echo $LogBackupRun;
		  			echo PHP_EOL;
		  		endif;			  		
			endif;
		endif;
		
		return $this;
	}
	public function sendMailResults(){
	    if( $this ->backup_email != '' AND $this ->backup_log_content != ''):
		    $HEADERS  = "From: backup@iporto.net.br <backup@iporto.net.br> \n";
		    $HEADERS .= "To: ".$this ->backup_email."\n";
		    $HEADERS .= "Return-Path: backup@iporto.net.br \n";
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
		        $ConfDetails = explode(PHP_EOL, $Conf);
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