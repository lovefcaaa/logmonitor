<?php
$conf = array(
"errlog_std" => array("regular" => "stderr", "fun" => "/nginx/logs/error.log", "threshold" => 10, "emails" => array(array("mail"=>"sohu@sohu.com", "phone"=>"13333333333"))),
"accesslog_std" => array("regular" => "error", "fun" => "/nginx/logs/access.log", "threshold" => 100, "emails" => array(array("mail"=>"sohu@sohu.com", "phone"=>"13333333333"))),
);
/*conflogmonitor*/

/*
 * PHP General log monitoring
 *
 * PHP General log monitoring is distributed under GPL 2
 * Copyright (C) 2014 lovefcaaa <https://github.com/lovefcaaa>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2 of the License, or any later version.
 */

/*
 * cli
 */
if (PHP_SAPI == 'cli'){
    echo "logmonitor by lovefcaaa\n";

    $opts = array(
        "p:", // project name
    );
    $p = 'all';
    $options = getopt("", $opts);
    if(!empty($options["p"]) && array_key_exists($options["p"], $conf)){
    	$p = $options["p"];
    }
	if($p != 'all'){
		$conf_tmp = array($p => $conf[$p]);
		$conf = $conf_tmp;
	}
	//begin
    foreach($conf as $k => $v){
        if(!file_exists($v['fun'])) {
            die($v['fun']." is not exits");
        }
        echo $statuFile = "/log/".md5($v['regular'].'--'.$v['fun']).'.logstat';

        $fileSize = filesize($v['fun']);
        $prePos   = $fileSize;
        $preTime  = time();
        
        if(file_exists($statuFile)) {
            $status = file_get_contents($statuFile);
            list($prePos, $preTime) = explode("\n", $status);
        }
        echo "prePos:".$prePos."\n";
        echo "fileSize:".$fileSize."\n";
        
        $filePos = $prePos;
        $fileTime = $preTime;
        $logArray = array();
        
        if($prePos > $fileSize) {
            $prePos = $filePos = $fileSize;
        }
        
        if($prePos != $fileSize) {
            $fp = fopen($v['fun'], "r");
            fseek($fp, $prePos);
            if($fp) {
                $count = 0;
                while (($buffer = fgets($fp, 5120)) !== false) {
                    if(preg_match("#".$v['regular']."#", $buffer)) {
                        $count++;
                        $logArray[] = $buffer;
                        if(count($logArray) > $v['threshold']) {
                            array_shift( $logArray );
                        }
                    }
                }
        
                $filePos =  ftell($fp);
                $fileTime = time();
                fclose($fp);
                $status = $filePos . "\n" . $fileTime;
                file_put_contents($statuFile, $status);
                
                if($count >= $v['threshold']) {
                    //Call the police 
    				alarm($k, $v['fun'], $v['threshold'], $count, $v['emails'], $preTime, $fileTime, $logArray);
                }
                echo  'pattern ' . $v['regular'] . ', count:' , $count;
            }
        }else{
                $fileTime = time();
                $status = $filePos . "\n" . $fileTime;
                file_put_contents($statuFile, $status);
        }
		sleep(10);
    }
}
/*
 * web
 */
else{
	systemAuth();
	if(!empty($_POST)){
		$conf_num = count($_POST['key']);
		$conf_arr = array();
		for($i = 0; $i < $conf_num; $i++){
			eval( '$emails_='.html_entity_decode($_POST['emails'][$i][0], ENT_QUOTES, 'GB2312').';');
			$fun_ = html_entity_decode($_POST['fun'][$i][0], ENT_QUOTES);
			if(empty($fun_) || !file_exists($fun_)){
				die('<script type="text/javascript">alert("Monitor log does not exist! Will return to the previous page! ");history.go(-1);</script>');
			}

			if(empty($_POST['regular'][$i][0]) || strlen($_POST['regular'][$i][0]) > 20){
				die('<script type="text/javascript">alert("The key regular cannot exceed 20 characters! Will return to the previous page! ");history.go(-1);</script>');
			}

			$conf_arr[$_POST['key'][$i][0]] = array(
				"regular" => $_POST['regular'][$i][0], 
				"fun" => $fun_, 
				"threshold" => intval($_POST['threshold'][$i][0]), 
				"emails" => $emails_, 
			);
		}
		wconf($conf_arr);
	}
	echo <<<EOF
	<meta http-equiv="content-type" content="text/html; charset=GB2312" />
	<title>Log incremental monitoring configuration</title>
	<FORM accept-charset="GB2312" action="" method="post" >
	<DIV style="font-size: 40px;">Log delta center of monitoring configuration </DIV>
EOF;
	$i = 0;
	foreach($conf as $k => $v):
	echo <<<EOF
    <DIV>
     <FIELDSET id="conf{$i}">
      <LEGEND>{$k}</LEGEND>
          <DIV><LABEL for="port">Project name: </LABEL><INPUT maxLength="1024" size="150" name="key[$i][]" value="{$k}" /></DIV>
          <DIV><LABEL for="port">The key of regular: </LABEL><INPUT maxLength="1024" size="150" name="regular[$i][]" value="{$v['regular']}" /></DIV>
          <DIV><LABEL for="port">Monitoring logs: </LABEL><INPUT maxLength="1024" size="150" name="fun[$i][]" value="{$v['fun']}" /></DIV>
          <DIV><LABEL for="port">Threshold limit: </LABEL><INPUT maxLength="1024" size="150" name="threshold[$i][]" value="{$v['threshold']}" /></DIV>
          <DIV><LABEL for="port">Notify the array: </LABEL><INPUT maxLength="1024" size="150" name="emails[$i][]" value="
EOF;
    	   echo trim(var_export($v['emails'], TRUE));
           echo <<<EOF
           " /></DIV>
     </FIELDSET>
     <button onClick="javascript:alert('TODO');return false;">Adding new monitoring </button>
    </DIV>
EOF;
	$i ++;
	endforeach;
    echo <<<EOF
    <hr />
    <INPUT type="submit" name="submit" value="Save the configuration" />
    </FORM>
EOF;
}

/*
 * Call the police --- Change
 */
function alarm($app, $fun, $threshold, $count, $emails = array(array("mail"=>"sohu@sohu.com", "phone"=>"13333333333")), $preTime, $fileTime, $logArray){
    ini_set("memory_limit", -1);
    $from = array("mail" => "sohu@sohu.com");
    $title = "[ $app ]alarm[" . date("Y-m-d H:i:s") . "]";
    $msg = "from: " . date("Y-m-d H:i:s", $preTime) . " to " . date("Y-m-d H:i:s", $fileTime)."Project name: $app<br /> monitoring statements: 
    		$fun <br/> threshold limit: $threshold <br /> the current value: $count <br /> last log:<br />" . implode("<br />" ,$logArray );
    $phone = '';
    $mailto = '';

    foreach($emails as $t){
    	if(isset($t["mail"])){
            $mailto .= $t["mail"] . ",";
        }
        if(isset($t["phone"])){
            $phone .= $t["phone"] . ",";
        }
    }

    if(!empty($phone)){
        //TODO Send a text message 
    }
    if (!empty($mailto)){
        //TODO Send a email
    }
}

/*
 * Write files 
 */
function wconf($conf = array()) {

    if($fp = fopen(__FILE__, 'r')) {
  		$buffer = "<?php\n";
  		$buffer .= "\$conf = " . var_export($conf, TRUE) . ";\n";
  		$begin = false;
        while(!feof($fp)) {
        	$line = fgets($fp);
        	if(!$begin && strpos($line, '/*confdbmonitor*/') !== false){
        		$begin = true;
        	}
        	if($begin){
        		$buffer .= $line;
        	}
    	}
    	fclose($fp); 
        if(!file_put_contents(__FILE__, $buffer, LOCK_EX)) {
            echo '<script type="text/javascript">alert("Modify the failure! [ write failure ] will return to the previous page! ");history.go(-1);</script>';
        }else {
            echo '<script type="text/javascript">alert("Modify the success! Will return to the previous page! ");history.go(-1);</script>';
        }
    }else {
        echo '<script type="text/javascript">alert("Modify the failure! [ file permissions problems ]will return to the previous page! ");history.go(-1);</script>';
    }
    error_log("dbmonitor");
}

/*
 * Auth  ---  Change
 */
function systemAuth($now_url = "#"){
    define('ADMIN_USERNAME','admin1'); 	// Admin Username
	define('ADMIN_PASSWORD','admin1');  // Admin Password
    if(!isset($_GET['u']) || !isset($_GET['p']) || $_GET['u'] != ADMIN_USERNAME || $_GET['p'] != ADMIN_PASSWORD) {
    	die("After login, please use this feature.<a href=$now_url>login</a>");
    }
}
