<?php
    # /usr/bin/php -d safe_mode=Of /root/scripts/run/update.timthumb.php
    if( is_file( '/usr/bin/find')):
    	exec('/usr/bin/find /home/ -iname timthumb.php', $a);
    	
    elseif( is_file( '')):
    	exec('/bin/find /home/ -iname timthumb.php', $a);
    
    endif;
    
    if( is_array( $a)):
	    foreach( $a as $file):
	        if( is_file( $file)):
	            $cp = copy( $file, $file.'.'.date('Ymd').'.bkp');
	            if( $cp):
	                exec("/usr/bin/wget -O ".$file." http://timthumb.googlecode.com/svn/trunk/timthumb.php");
	                
	                echo "/usr/bin/wget -O ".$file." http://timthumb.googlecode.com/svn/trunk/timthumb.php";
	                echo "\n";
	            endif;
	        endif;
	    endforeach;
    endif;
?>