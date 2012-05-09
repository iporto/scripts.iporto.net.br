<?php
# 1       5       *       *       *       /usr/bin/php -d safe_mode=Off /root/scripts/run.process.monit.php
# php -d safe_mode=Off run.process.monit.php
$phpversion		 = phpversion();
$phpversion		 = explode('.',$phpversion);
if( $phpversion[0] <= 5 && $phpversion[1] <= 1):
	$Base	  	 = '/root/scripts';
else:
	$Base	  	 = __DIR__;
endif;

$BaseConf 		 = $Base . '/run.process.monit.conf';
if( !is_file($BaseConf)):
	die("Unable to load configuration files\n");
endif;
$BaseConfContent = file_get_contents($BaseConf);

$Monits = ProcessMonitConf::Parser( $BaseConfContent);

if( !is_array( $Monits)):
	die("No data to run\n");
endif;
foreach( $Monits as $Monit):
	$Obj = new ProcessMonit( 
	        	$Monit['process_pid'], 
	        	$Monit['alert_email'], 
	        	$Monit['run_start'], 
	        	$Monit['run_stop'],
	        	@$Monit['run_chdir']
	      );
	$Obj
	->Monit()
	->sendMailResults();
endforeach;

// CLASSE SIMPLES PARA EFETUAR MONITORAMENTO DE PROCESSOS
// IPORTO.COM - 2012-05-02
class ProcessMonit{
	function __construct( $process_pid, $alert_email, $run_start, $run_stop, $run_chdir){
		
		$this ->process_pid 		= $process_pid;
		$this ->alert_email 		= $alert_email;
		$this ->run_start 			= $run_start;
		$this ->run_stop 			= $run_stop;
		$this ->run_chdir 			= $run_chdir;
		
		$this ->monit_log_content	= "";
	}

	public function Monit()
	{	    
	    if( $this ->run_chdir != ''):
	    	if( is_dir($this ->run_chdir)):
	    		chdir($this ->run_chdir);
	    	endif;
	    endif;
	    $RestartProcess = false;

	    if(!is_file( $this ->process_pid)):
	    	$RestartProcess = true;
	    else:
	    	$Pid = @file_get_contents( $this ->process_pid);
	    	$Pid = trim($Pid);
	    	$Pid = (int) $Pid;
	    	
	    	if( $Pid != ''):
	    		exec ( "/bin/ps aux | awk '{print $2 }' | grep ".$Pid, $Rls);
	    	else:
	    		$RestartProcess = true;
	    	endif;
	    	if( !isset( $Rls[0])):
	    		$RestartProcess = true;
	    	else:
	    		if( $Rls[0] != $Pid):
	    			$RestartProcess = true;
	    		endif;
	    	endif;
	    endif;
	    
	    if( $RestartProcess):
	    	
	    	if( @$this ->run_stop  != ''):
	    		exec( $this ->run_stop,  $R1);
	    	endif;
	    	if( @$this ->run_start != ''):
	    		exec( $this ->run_start, $R2);
	    	endif;

  			echo "Restarted process with pid file >> ".$this ->process_pid." \n";			
  			echo "\n";
  			
  			$this ->monit_log_content  = "";
	  		$this ->monit_log_content .= "Restarted process with pid file >> ".$this ->process_pid." \n\n ";
	  		if( is_array($R1)):
		  		foreach( $R1 as $Dtls):
		  			$this ->monit_log_content .=" Stop>> ".$Dtls;
	  				$this ->monit_log_content .= "\n ";
		  		endforeach;
	  		endif;
	  		if( is_array($R2)):
		  		foreach( $R2 as $Dtls):
			  		$this ->monit_log_content .=" Start>> ".$Dtls;
			  		$this ->monit_log_content .= "\n ";
		  		endforeach;
	  		endif;
	    endif;

		return $this;
	}
	public function sendMailResults(){
	    if( $this ->alert_email != '' AND $this ->monit_log_content != ''):
	    	$HEADERS  = "From: monitor@iporto.net.br <monitor@iporto.net.br> \n";
		    $HEADERS .= "To: ".$this ->alert_email."\n";
		    $HEADERS .= "Return-Path: consultoria@iporto.net.br \n";
		    $HEADERS .= "Content-Type: text/plain; charset=ISO-8859-1 \n";
		
		    mail( $this ->alert_email, 'MONIT: >> '.$this ->process_pid, $this ->monit_log_content, $HEADERS );
	    endif;
	}	
}





/*
 *
 * This class mount/create an array structure to create monit list;
 * Usage --> BackupConf::Parser( '[iPORTO_ProcessMonit]{flags};');
 *
*/
class ProcessMonitConf{

    public static function Parser( $Content = ''){

        $ConfMonit = array();
        preg_match_all("/\[iPORTO_ProcessMonit\](.*?)\;\;/is", $Content, $e);

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
					        	$ConfMonit[ $ConfUsed][ trim( $ConfDetailsUse[1])] = trim( $ConfDetailsUse[2]);
					        endif;
				        endif;
			        endif;
		        endforeach;
		        $ConfUsed ++;
	        endforeach;
        endif;
        return $ConfMonit;
    }
}
?>