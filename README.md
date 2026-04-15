# Easy Ajax Shoutcast

<p align="center">
  <img src="images/logo.png" alt="Easy Ajax Shoutcast logo" width="220">
</p>

A modernized, legacy-compatible refresh of the original **Easy Ajax Shoutcast** script by **Richard Cornwell**.

This project keeps the original spirit and public behavior intact while cleaning up the internals for modern PHP use. It is still a simple drop-in Shoutcast updater that polls a server with AJAX, caches results, and updates elements on the page.

## What it does

- Pulls live data from a Shoutcast server using the classic `/7.html` endpoint
- Supports a master server plus optional relay/slave servers
- Aggregates listener data across relays when they appear to be carrying the same live song
- Caches status output to reduce load on the Shoutcast server
- Outputs a small JavaScript loader for easy drop-in website integration
- Preserves the original pipe-delimited AJAX response format for compatibility

## Included files

```text
sc.php
README.md
images/logo.png
```

## Requirements

- PHP 7.4+ recommended
- A Shoutcast server that exposes `/7.html`
- Write access in the script directory for `sc-cache.txt` if caching is enabled

## Configuration

Edit the top section of `sc.php`:

```php
$masterServer = 'server.host.tld:port';
$cacheOn = true;
$cacheTime = 20;
$offAirStatus = 'Off air';
$onAirStatus = 'Live on Air';
$ajaxUpdateTime = 15;
$ajaxUpdateTimeLocked = false;
$slaveServers = 'server1.host.tld:port,server2.host.tld:port';
```

### Settings

- **$masterServer**: Primary Shoutcast server
- **$cacheOn**: Enable or disable caching
- **$cacheTime**: Cache lifetime in seconds
- **$offAirStatus**: Text shown when the stream is offline
- **$onAirStatus**: Text shown when the stream is live
- **$ajaxUpdateTime**: Default browser polling interval in seconds
- **$ajaxUpdateTimeLocked**: Prevent users from overriding the polling interval with a query string
- **$slaveServers**: Optional comma-separated list of relay servers

## Installation

Upload the files to your web server, then configure `sc.php` with your server details.

## JavaScript embed

Add this to your page:

```html
<script src="sc.php?ajaxsync=get-shoutcast-js"></script>
```

To override the update interval from the URL:

```html
<script src="sc.php?ajaxsync=get-shoutcast-js&updatetime=15"></script>
```

If `$ajaxUpdateTimeLocked = true;`, the `updatetime` override is ignored.

## Output placeholders

Place these elements anywhere on your page:

```html
<span id="sc-listenercount"></span>
<span id="sc-status"></span>
<span id="sc-servercount"></span>
<span id="sc-peaklisteners"></span>
<span id="sc-maxlisteners"></span>
<span id="sc-uniquelisteners"></span>
<span id="sc-bitrate"></span>
<span id="sc-song"></span>
```

## AJAX endpoints

### Get status

```text
sc.php?ajaxsync=get-shoutcast-update
```

Returns data in this legacy-compatible format:

```text
listeners|status|servercount|peaklisteners|maxlisteners|uniquelisteners|bitrate|song
```

### Get JavaScript loader

```text
sc.php?ajaxsync=get-shoutcast-js
```

Optional:

```text
sc.php?ajaxsync=get-shoutcast-js&updatetime=15
```

## Notes about relay aggregation

Relay/slave data is only added when:

- the relay is online,
- the relay is reporting live status,
- and the relay appears to be playing the same current song as the master.

That preserves the intended legacy behavior and helps avoid combining unrelated streams.

## What was modernized

The refreshed `sc.php` keeps the same outward functionality while improving internals:

- safer request handling
- cleaner socket reads
- stronger parsing of the Shoutcast response
- better cache handling
- fewer undefined variable problems
- modern JavaScript polling using `fetch()`
- `textContent` updates instead of raw `innerHTML`
- clearer function structure and comments

## License

See LICENSE file.
