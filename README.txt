#    Easy AJax ShoutCast Updater with Cacheing. v1.0
#    Copyright (C) 2011  Richard Cornwell
#    Website: http://thegeekoftheworld.com/
#    Email:   richard@techtoknow.net


You will need to add the following tag to install the AJax Updater Code:
<script type="text/javascript" src="/sc.php?ajaxsync=get-shoutcast-js"></script>

Like to change the Update time? use the &updatetime=numberhereinsec  tag:
<script type="text/javascript" src="/sc.php?ajaxsync=get-shoutcast-js&updatetime=20"></script>

After you install the AJax Updater Code you will need to use the <div id=""></div> tags to put the ShoutCast Data into the page 

To output the listener count: <div id="sc-listenercount"></div>
To output the onair/offair status: <div id="sc-status"></div>
To output the server count: <div id="sc-servercount"></div>
To output the peak listener count: <div id="sc-peaklisteners"></div>
To output the maxum listeners: <div id="sc-maxlisteners"></div>
To output the unique listeners: <div id="sc-uniquelisteners"></div>
To output the bitrate: <div id="sc-bitrate"></div>
To output the Son Playing: <div id="sc-song"></div>


