<?php
############################################################################
#    Easy AJax ShoutCast Updater with Cacheing. v1.0
#    Copyright (C) 2011  Richard Cornwell
#    Website: http://thegeekoftheworld.com/
#    Email:   richard@techtoknow.net
#
#    This program is free software: you can redistribute it and/or
#    modify it under the terms of the GNU General Public License as
#    published by the Free Software Foundation, either version 3 of the
#    License, or (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program. If not, see <http://www.gnu.org/licenses>.
############################################################################
#-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=- Setings -=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=#
############################################################################

$masterServer = "server.host.tld:port"; //e.g, "server.host.tld:port"
$cacheOn = true; //Use status cacheing? true OR false
$cacheTime = "20"; //Time in seconds 
$offAirStatus = "Off air";
$onAirStatus = "Live on Air";
$ajaxUpdateTime = "15"; //Time in seconds
$ajaxUpdateTimeLocked = false; //Disallow Ajax Update Timer changes via GET.
//uncomment $slaveServers for relays
$slaveServers = "server1.host.tld:port,server2.host.tld:port"; //e.g, $slaveServers = "server1.host.tld:port,server2.host.tld:port";


#=-=-=-=-=-=-=-=-=-=-=-=-=- END OF EDITING -=-=-=-=-=-=-=-=-=-=-=-=--=-=-=-#
############################################################################
############################################################################
############################################################################
function getSCData($server, $port) {
	$open = fsockopen($server, $port);
	if ($open) {
		fputs($open,"GET /7.html HTTP/1.1\nUser-Agent:Mozilla\n\n");
		$read = fread($open,1000);
		$text = explode("content-type:text/html",$read);
	}
	return str_replace('<HTML><meta http-equiv="Pragma" content="no-cache"></head><body>', '', str_replace('</body></html>', '' ,$text[1]));
}
function makeCache($data) {
	$fh = fopen('./sc-cache.txt', 'w') or die("Error Can't cache SC");
	fwrite($fh, time()."||".$data);
	fclose($fh);
}
function readCache($cacheTimeout, $cacheOn) {
	if($cacheOn) {
		$fh = fopen('./sc-cache.txt', 'r') or die("Error Can't Read cache SC");
		$cache = explode("||", fgets($fh));
		fclose($fh);
		$checkTime = time() - $cache[0];
		if($checkTime > $cacheTimeout) { return true; } else { return false; }
	} else {
	return true;
	}
}
function online($server, $port) {
	@socket = fsockopen($server, $port, $errno, $errstr, 1);
	if(!$socket) { return false; } else { return true; }
}
if($_GET['ajaxsync'] == "get-shoutcast-update" && readCache($cacheTime, $cacheOn)) {
	$masterServerData = explode(":", $masterServer);
	if($masterServer && online($masterServerData[0], trim($masterServerData[1]))) {
		$masterServerData = explode(",", getSCData($masterServerData[0], trim($masterServerData[1])));
		$shoutCast["listeners"] = $masterServerData[0];
		if(trim($masterServerData[1]) == 1) { $shoutCast["status"] = $onAirStatus; } else { $shoutCast["status"] = $offAirStatus; }
		$shoutCast["peaklisteners"] = (int)trim($masterServerData[2]);
		$shoutCast["maxlisteners"] = (int)trim($masterServerData[3]);
		$shoutCast["uniquelisteners"] = (int)trim($masterServerData[4]);
		$shoutCast["bitrate"] = (int)trim($masterServerData[5]);
		for ($i = 6; $i < count($masterServerData); $i++) { $shoutCast["song"] .= $masterServerData[$i] . ''; }
		$shoutCast["serverscount"] = 1;
		if((int)trim($masterServerData[1]) == 1) { 
			if($slaveServers) {
				$slaveServer = explode(",", $slaveServers);
				for ($i = 0; $i < count($slaveServer); $i++) {
					$slaveServerData = explode(":", $slaveServer[$i]);
					$slaveServerData = explode(",", getSCData($slaveServerData[0], trim($slaveServerData[1])));
					if(trim($slaveServerData[1]) == 1 && $slaveServerData[6] == $masterServerData[6]) { 
						$shoutCast["serverscount"]++;
						$slaveListenersCount = $slaveListenersCount + (int)trim($slaveServerData[0]);
						$slavePeakListenersCount = $slavepPeakListenersCount + (int)trim($slaveServerData[2]);
						$slaveMaxListenersCount = $slaveMaxListenersCount + (int)trim($slaveServerData[3]);
						$slaveUniqueListenersCount = $slaveUniqueListenersCount + (int)trim($slaveServerData[4]);
					}
				}
				if(count($slave) > 0) {
					$shoutCast["listeners"] = $masterServerData[0] + (int)$slaveListenersCount;
					$shoutCast["maxlisteners"] = $shoutCast["maxlisteners"] + (int)$slaveMaxListenersCount;
					$shoutCast["peaklisteners"] = $shoutCast["peaklisteners"] + (int)$slavePeakListenersCount;
					$shoutCast["uniquelisteners"] = $shoutCast["uniquelisteners"] + (int)$slaveUniqueListenersCount;
				}
			}
		}
	} else {
		$shoutCast["listeners"] = 0;
		$shoutCast["status"] = $offAirStatus;
		$shoutCast["serverscount"] = 0;
		$shoutCast["peaklisteners"] = 0;
		$shoutCast["maxlisteners"] = 0;
		$shoutCast["uniquelisteners"] = 0;
		$shoutCast["bitrate"] = 0;
		$shoutCast["song"] = "N/a";
	}
	$output = $shoutCast["listeners"]."|".$shoutCast["status"]."|".$shoutCast["serverscount"]."|".$shoutCast["peaklisteners"]."|".$shoutCast["maxlisteners"]."|".$shoutCast["uniquelisteners"]."|".$shoutCast["bitrate"]."|".$shoutCast["song"];
	makeCache($output);
	echo $output;
	die();
} else {
	if($_GET['ajaxsync'] == "get-shoutcast-update") {
		$fh = fopen('./sc-cache.txt', 'r') or die("Error Can't Read cache SC");
		$cache = explode("||", fgets($fh));
		echo $cache[1];
		fclose($fh);
		die();
	}
}
if($_GET['ajaxsync'] == "get-shoutcast-js") { 
	if(!$ajaxUpdateTimeLocked && $_GET['updatetime']) { $ajaxUpdateTime = $_GET['updatetime'] * 1000; } else { $ajaxUpdateTime = $ajaxUpdateTime * 1000; }
?>
function createRequestObject() {
	var ro;
	var browser = navigator.appName;
	if (browser == "Microsoft Internet Explorer") {
		ro = new ActiveXObject("Microsoft.XMLHTTP");
	} else {
	ro = new XMLHttpRequest();
	}
	return ro;
}
var ajaxsync = createRequestObject();
var shoutcast = new Array();
var shoutcastdata = null;
var lastshoutcastdata = null;
function runshoutcastpull() {
	ajaxsync.open('get', '<?php echo $_SERVER['PHP_SELF'];?>?ajaxsync=get-shoutcast-update&ms='+ new Date().getTime());
	ajaxsync.onreadystatechange = readshoutcaststatus;
	ajaxsync.send(null);
}
function readshoutcaststatus() {
	if (ajaxsync.readyState == 4) {
		shoutcastdata = ajaxsync.responseText;
		shoutcast = shoutcastdata.split('|');
		updateShoutcastDivs();
	}
}
function updateShoutcastDivs() {
	if (ajaxsync.responseText != lastshoutcastdata) {
		lastshoutcastdata = ajaxsync.responseText;
		if (document.getElementById("sc-listenercount")) {
			document.getElementById("sc-listenercount").innerHTML = shoutcast[0];
		}
		if (document.getElementById("sc-status")) {
			document.getElementById("sc-status").innerHTML = shoutcast[1];
		}
		if (document.getElementById("sc-servercount")) {
			document.getElementById("sc-servercount").innerHTML = shoutcast[2];
		}
		if (document.getElementById("sc-peaklisteners")) {
			document.getElementById("sc-peaklisteners").innerHTML = shoutcast[3];
		}
		if (document.getElementById("sc-maxlisteners")) {
			document.getElementById("sc-maxlisteners").innerHTML = shoutcast[4];
		}
		if (document.getElementById("sc-uniquelisteners")) {
			document.getElementById("sc-uniquelisteners").innerHTML = shoutcast[5];
		}
		if (document.getElementById("sc-bitrate")) {
			document.getElementById("sc-bitrate").innerHTML = shoutcast[6];
		}
		if (document.getElementById("sc-song")) {
			document.getElementById("sc-song").innerHTML = shoutcast[7];
		}
	}
	setTimeout("runshoutcastpull()", <?php echo $ajaxUpdateTime; ?>);
}
runshoutcastpull();
<?php die(); } ?>