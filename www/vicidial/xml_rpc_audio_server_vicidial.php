<?php 
# xml_rpc_audio_server_vicidial.php
# 
# Used for integration with QueueMetrics of audio recordings
#
# Copyright (C) 2009  Matt Florell <vicidial@gmail.com>    LICENSE: AGPLv2
# Copyright (C) 2007  Lenz Emilitri <lenz.loway@gmail.com> LICENSE: ????
# 
# CHANGES
# 90525-1141 - First build
#

// $Id: xmlrpc_audio_server.php,v 1.3 2007/11/12 17:53:09 lenz Exp $
//
// This is an example of a remote audio XML-RPC server for QueueMetrics.
// In QueueMetrics, calls to this client are activated by entering its URL in
// the 'default.audioRpcServer' property.
//
// In order to make it very easy to customize, you should change the contents of the
// functions 'find_file()' and 'listen_call()' only. You can populate some or all of the 
// return fields, according to the data you actually have. As the calling user is passed along,
// it's pretty easy to log who listened to which calls if you need to do it.
// 
//
// IMPORTANT:
// in order to run this, you must have the XML_RPC module that comes with the PEAR library
// correctly installed on your PHP server. See http://pear.php.net 
//  "pear install XML_RPC-1.5.1"
//


require_once 'XML/RPC/Server.php';



// the following variables hold the return status for your file.

// Listening to a stored call
$FILE_FOUND      = false;
$FILE_LISTEN_URL = "";
$FILE_LENGTH     = "";
$FILE_ENCODING   = "";
$FILE_DURATION   = ""; 

// Listening to an ongoing call
$CALL_FOUND        = false;
$CALL_LISTEN_URL   = "";
$CALL_POPUP_WIDTH  = "";
$CALL_POPUP_HEIGHT = "";


//
// This function must be implemented by the user.
//
function find_file( $ServerID, $AsteriskID, $QMUserID, $QMUserName ) 
	{
	global $FILE_FOUND;
	global $FILE_LISTEN_URL;
	global $FILE_LENGTH;
	global $FILE_ENCODING;
	global $FILE_DURATION;

	require("dbconnect.php");
	require("functions.php");

	#############################################
	##### START QUEUEMETRICS LOGGING LOOKUP #####
	$stmt = "SELECT enable_queuemetrics_logging,queuemetrics_server_ip,queuemetrics_dbname,queuemetrics_login,queuemetrics_pass,queuemetrics_log_id FROM system_settings;";
	$rslt=mysql_query($stmt, $link);
	if ($DB) {echo "$stmt\n";}
	$qm_conf_ct = mysql_num_rows($rslt);
	if ($qm_conf_ct > 0)
		{
		$row=mysql_fetch_row($rslt);
		$enable_queuemetrics_logging =	$row[0];
		$queuemetrics_server_ip	=		$row[1];
		$queuemetrics_dbname =			$row[2];
		$queuemetrics_login	=			$row[3];
		$queuemetrics_pass =			$row[4];
		$queuemetrics_log_id =			$row[5];
		}
	##### END QUEUEMETRICS LOGGING LOOKUP #####
	###########################################
	if ($enable_queuemetrics_logging > 0)
		{
		$linkB=mysql_connect("$queuemetrics_server_ip", "$queuemetrics_login", "$queuemetrics_pass");
		mysql_select_db("$queuemetrics_dbname", $linkB);

		$stmt="SELECT time_id from queue_log where call_id='$AsteriskID' limit 1;";
		$rslt=mysql_query($stmt, $linkB);
		if ($DB) {echo "$stmt\n";}
		$QM_ql_ct = mysql_num_rows($rslt);
		if ($QM_ql_ct > 0)
			{
			$row=mysql_fetch_row($rslt);
			$time_id	= $row[0];
			$time_id_end = ($time_id + 14400);

			$lead_id = substr($AsteriskID, -9);
			$lead_id = ($lead_id + 0);
			$stmt = "SELECT start_epoch,length_in_sec,location from recording_log where start_epoch>=$time_id and start_epoch<=$time_id_end and lead_id='$lead_id' order by recording_id limit 1;";
			$rslt=mysql_query($stmt, $link);
			if ($DB) {echo "$stmt\n";}
			$rl_ct = mysql_num_rows($rslt);
			if ($rl_ct > 0)
				{
				$row=mysql_fetch_row($rslt);
				$start_epoch =		$row[0];
				$length_in_sec =	$row[1];
				$location =			$row[2];
				if (strlen($location)>2)
					{
					$extension = substr($location, strrpos($location, '.') + 1);
					if (ereg("mp3|gsm",$extension))
						{$filesize = (2000 * $length_in_sec);}
					else
						{$filesize = (15660 * $length_in_sec);}
					$URLserver_ip = $location;
					$URLserver_ip = eregi_replace('http://','',$URLserver_ip);
					$URLserver_ip = eregi_replace('https://','',$URLserver_ip);
					$URLserver_ip = eregi_replace("\/.*",'',$URLserver_ip);
					$stmt="select count(*) from servers where server_ip='$URLserver_ip';";
					$rsltx=mysql_query($stmt, $link);
					$rowx=mysql_fetch_row($rsltx);
					
					if ($rowx[0] > 0)
						{
						$stmt="select recording_web_link,alt_server_ip from servers where server_ip='$URLserver_ip';";
						$rsltx=mysql_query($stmt, $link);
						$rowx=mysql_fetch_row($rsltx);
						
						if (eregi("ALT_IP",$rowx[0]))
							{
							$location = eregi_replace($URLserver_ip, $rowx[1], $location);
							}
						}
					$FILE_FOUND      = true;
					$FILE_LISTEN_URL = "$location";
					$FILE_LENGTH     = "$filesize";
					$FILE_ENCODING   = "$extension";	
					$FILE_DURATION   = sec_convert($length_in_sec,'H'); 
					}
				else
					{
					$FILE_FOUND      = false;
					$FILE_LISTEN_URL = "";
					$FILE_LENGTH     = "0";
					$FILE_ENCODING   = "wav";	
					$FILE_DURATION   = "0:00"; 	
					}
				}
			else
				{
				$FILE_FOUND      = false;
				$FILE_LISTEN_URL = "";
				$FILE_LENGTH     = "0";
				$FILE_ENCODING   = "wav";	
				$FILE_DURATION   = "0:00"; 	
				}
			
			}
		else
			{
			$FILE_FOUND      = false;
			$FILE_LISTEN_URL = "";
			$FILE_LENGTH     = "0";
			$FILE_ENCODING   = "wav";	
			$FILE_DURATION   = "0:00"; 	
			}
		mysql_close($linkB);
		}

#	$FILE_FOUND      = true;
#	$FILE_LISTEN_URL = "http://listennow.server/$ServerID/$AsteriskID/$QMUserID/$QMUserName";
#	$FILE_LENGTH     = "125000";
#	$FILE_ENCODING   = "mp3";	
#	$FILE_DURATION   = "1:12"; 	
	}

function listen_call( $ServerID, $AsteriskID, $Agent, $QMUserID, $QMUserName, $Direction ) 
	{
	global $CALL_FOUND;
	global $CALL_LISTEN_URL;
	global $CALL_POPUP_WIDTH;
	global $CALL_POPUP_HEIGHT;

	$CALL_FOUND      = false;
	$CALL_LISTEN_URL = "http://listennow.server/$ServerID/$AsteriskID/$QMUserID/$QMUserName/$Agent/$Direction";
	$CALL_POPUP_WIDTH = "200";
	$CALL_POPUP_HEIGHT = "250";
	}


// 
// This function does the XML-RPC call handling
// All the PHP's XML-RPC details are handled here.
//
function xmlrpc_find_file( $params ) {
	global $FILE_FOUND;
	global $FILE_LISTEN_URL;
	global $FILE_LENGTH;
	global $FILE_ENCODING;	
	global $FILE_DURATION;
	
	$p0 = $params->getParam(0)->scalarval(); // server ID
	$p1 = $params->getParam(1)->scalarval(); // Asterisk call ID
	$p2 = $params->getParam(2)->scalarval(); // QM User ID
	$p3 = $params->getParam(3)->scalarval(); // Qm user name
	
	find_file( $p0, $p1, $p2, $p3 ); 		
	
	$response = new XML_RPC_Value(array(
        new XML_RPC_Value( $FILE_FOUND, 'boolean' ),
        new XML_RPC_Value( $FILE_LISTEN_URL ),
        new XML_RPC_Value( $FILE_LENGTH ),
        new XML_RPC_Value( $FILE_ENCODING ),
        new XML_RPC_Value( $FILE_DURATION ),        
    ), "array");
	
	return new XML_RPC_Response($response);
}

function xmlrpc_listen_call_inbound( $params ) {
	xmlrpc_listen_call( $params, "INBOUND" );
}

function xmlrpc_listen_call_outbound( $params ) {
	xmlrpc_listen_call( $params, "OUTBOUND" );
}


function xmlrpc_listen_call( $params, $direction ) {
	global $CALL_FOUND;
	global $CALL_LISTEN_URL;
	global $CALL_POPUP_WIDTH;
	global $CALL_POPUP_HEIGHT;

	$p0 = $params->getParam(0)->scalarval(); // server ID
	$p1 = $params->getParam(1)->scalarval(); // asterisk call ID
	$p2 = $params->getParam(2)->scalarval(); // agent code
	$p3 = $params->getParam(3)->scalarval(); // QM user ID
	$p4 = $params->getParam(3)->scalarval(); // QM user name
	
	listen_call( $p0, $p1, $p2, $p3, $p4, $direction ); 		
	
	$response = new XML_RPC_Value(array(
        new XML_RPC_Value( $CALL_FOUND, 'boolean' ),
        new XML_RPC_Value( $CALL_LISTEN_URL ),
        new XML_RPC_Value( $CALL_POPUP_WIDTH, 'int' ),
        new XML_RPC_Value( $CALL_POPUP_HEIGHT, 'int' ),             
    ), "array");
	
	return new XML_RPC_Response($response);
}



//
// Instantiates a very simple XML-RPC audio server for QueueMetrics
//
$server = new XML_RPC_Server(
    array(
        'QMAudio.findStoredFile' => array(
            'function' => 'xmlrpc_find_file'
        ),    
        'QMAudio.listenOngoingCall' => array(
            'function' => 'xmlrpc_listen_call_inbound'
        ),
        'QMAudio.listenOngoingCallOutbound' => array(
            'function' => 'xmlrpc_listen_call_outbound'
        ),
        
    ),
    1  // serviceNow
);


// $Log: xmlrpc_audio_server.php,v $
// Revision 1.3  2007/11/12 17:53:09  lenz
// Bug #182: queue direction for inbound/outbound call listen.
//
// Revision 1.2  2007/05/12 20:45:06  lenz
// Merge IMM4
//
// Revision 1.1.2.1  2007/04/03 17:43:06  lenz
// Prima versione.
//
//
//
//

?>