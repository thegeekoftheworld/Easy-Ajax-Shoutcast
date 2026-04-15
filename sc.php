<?php

declare(strict_types=1);

/**
 * Easy Ajax Shoutcast Updater (modernized legacy-compatible edition)
 * Original concept and project by Richard Cornwell.
 *
 * This version keeps the original public behavior:
 * - ?ajaxsync=get-shoutcast-update
 * - ?ajaxsync=get-shoutcast-js[&updatetime=15]
 * - Pipe-delimited response format for update requests
 * - Same DOM IDs for drop-in frontend compatibility
 */

/* -------------------------------------------------------------------------- */
/* Configuration                                                               */
/* -------------------------------------------------------------------------- */

$masterServer = 'server.host.tld:port'; // e.g. server.example.com:8000
$cacheOn = true;                        // true or false
$cacheTime = 20;                        // seconds
$offAirStatus = 'Off air';
$onAirStatus = 'Live on Air';
$ajaxUpdateTime = 15;                   // seconds
$ajaxUpdateTimeLocked = false;          // prevent ?updatetime= override when true

// Comma-separated relay list. Leave blank if unused.
$slaveServers = 'server1.host.tld:port,server2.host.tld:port';

/* -------------------------------------------------------------------------- */
/* End of user-edit section                                                    */
/* -------------------------------------------------------------------------- */

const SC_CACHE_FILE = __DIR__ . '/sc-cache.txt';
const SC_DEFAULT_SONG = 'N/a';
const SC_SOCKET_TIMEOUT = 2;

/**
 * Parse "host:port" into a structured array.
 *
 * @return array{host:string,port:int}|null
 */
function parseServerAddress(string $server): ?array
{
    $server = trim($server);
    if ($server === '') {
        return null;
    }

    $parts = explode(':', $server, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $host = trim($parts[0]);
    $port = (int) trim($parts[1]);

    if ($host === '' || $port <= 0 || $port > 65535) {
        return null;
    }

    return [
        'host' => $host,
        'port' => $port,
    ];
}

/**
 * Check if the remote socket is reachable.
 */
function isServerOnline(string $host, int $port): bool
{
    $socket = @fsockopen($host, $port, $errno, $errstr, SC_SOCKET_TIMEOUT);
    if (!is_resource($socket)) {
        return false;
    }

    fclose($socket);
    return true;
}

/**
 * Fetch the Shoutcast legacy /7.html endpoint.
 */
function fetchShoutcastRaw(string $host, int $port): ?string
{
    $socket = @fsockopen($host, $port, $errno, $errstr, SC_SOCKET_TIMEOUT);
    if (!is_resource($socket)) {
        return null;
    }

    stream_set_timeout($socket, SC_SOCKET_TIMEOUT);

    $request = sprintf(
        "GET /7.html HTTP/1.1\r\nHost: %s\r\nUser-Agent: Easy-Ajax-Shoutcast/2.0\r\nConnection: Close\r\n\r\n",
        $host
    );

    fwrite($socket, $request);

    $response = '';
    while (!feof($socket)) {
        $chunk = fread($socket, 2048);
        if ($chunk === false) {
            break;
        }
        $response .= $chunk;
    }

    fclose($socket);

    if ($response === '') {
        return null;
    }

    $parts = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
    $body = $parts[1] ?? $response;

    return trim(strip_tags($body));
}

/**
 * Parse /7.html body into a normalized stats array.
 *
 * Shoutcast legacy format is typically:
 * currentlisteners,streamstatus,peaklisteners,maxlisteners,reportedlisteners,bitrate,songtitle
 *
 * @return array{
 *   listeners:int,
 *   status:string,
 *   peaklisteners:int,
 *   maxlisteners:int,
 *   uniquelisteners:int,
 *   bitrate:int,
 *   song:string,
 *   rawStatus:int
 * }|null
 */
function parseShoutcastStats(string $body, string $onAirStatus, string $offAirStatus): ?array
{
    $body = trim($body);
    if ($body === '') {
        return null;
    }

    $parts = array_map('trim', explode(',', $body));
    if (count($parts) < 7) {
        return null;
    }

    $song = implode(',', array_slice($parts, 6));
    $rawStatus = (int) $parts[1];

    return [
        'listeners' => max(0, (int) $parts[0]),
        'status' => $rawStatus === 1 ? $onAirStatus : $offAirStatus,
        'peaklisteners' => max(0, (int) $parts[2]),
        'maxlisteners' => max(0, (int) $parts[3]),
        'uniquelisteners' => max(0, (int) $parts[4]),
        'bitrate' => max(0, (int) $parts[5]),
        'song' => $song !== '' ? $song : SC_DEFAULT_SONG,
        'rawStatus' => $rawStatus,
    ];
}

/**
 * Read a Shoutcast server and return normalized stats.
 */
function getServerStats(string $server, string $onAirStatus, string $offAirStatus): ?array
{
    $parsed = parseServerAddress($server);
    if ($parsed === null) {
        return null;
    }

    if (!isServerOnline($parsed['host'], $parsed['port'])) {
        return null;
    }

    $raw = fetchShoutcastRaw($parsed['host'], $parsed['port']);
    if ($raw === null) {
        return null;
    }

    return parseShoutcastStats($raw, $onAirStatus, $offAirStatus);
}

/**
 * Return the legacy empty/offline payload structure.
 *
 * @return array{
 *   listeners:int,
 *   status:string,
 *   serverscount:int,
 *   peaklisteners:int,
 *   maxlisteners:int,
 *   uniquelisteners:int,
 *   bitrate:int,
 *   song:string
 * }
 */
function getOfflinePayload(string $offAirStatus): array
{
    return [
        'listeners' => 0,
        'status' => $offAirStatus,
        'serverscount' => 0,
        'peaklisteners' => 0,
        'maxlisteners' => 0,
        'uniquelisteners' => 0,
        'bitrate' => 0,
        'song' => SC_DEFAULT_SONG,
    ];
}

/**
 * Build final payload, including relay aggregation when the relay appears to
 * be carrying the same current song and is live.
 */
function buildPayload(
    string $masterServer,
    string $slaveServers,
    string $onAirStatus,
    string $offAirStatus
): array {
    $master = getServerStats($masterServer, $onAirStatus, $offAirStatus);
    if ($master === null) {
        return getOfflinePayload($offAirStatus);
    }

    $payload = [
        'listeners' => $master['listeners'],
        'status' => $master['status'],
        'serverscount' => 1,
        'peaklisteners' => $master['peaklisteners'],
        'maxlisteners' => $master['maxlisteners'],
        'uniquelisteners' => $master['uniquelisteners'],
        'bitrate' => $master['bitrate'],
        'song' => $master['song'],
    ];

    if ($master['rawStatus'] !== 1) {
        return $payload;
    }

    $slaves = array_filter(array_map('trim', explode(',', $slaveServers)));
    foreach ($slaves as $slaveServer) {
        $slave = getServerStats($slaveServer, $onAirStatus, $offAirStatus);
        if ($slave === null) {
            continue;
        }

        if ($slave['rawStatus'] !== 1) {
            continue;
        }

        if ($slave['song'] !== $master['song']) {
            continue;
        }

        $payload['serverscount']++;
        $payload['listeners'] += $slave['listeners'];
        $payload['peaklisteners'] += $slave['peaklisteners'];
        $payload['maxlisteners'] += $slave['maxlisteners'];
        $payload['uniquelisteners'] += $slave['uniquelisteners'];
    }

    return $payload;
}

/**
 * Convert payload to legacy pipe-delimited output.
 */
function payloadToLegacyString(array $payload): string
{
    return implode('|', [
        $payload['listeners'],
        $payload['status'],
        $payload['serverscount'],
        $payload['peaklisteners'],
        $payload['maxlisteners'],
        $payload['uniquelisteners'],
        $payload['bitrate'],
        $payload['song'],
    ]);
}

/**
 * Save cache contents.
 */
function writeCache(string $data): bool
{
    $contents = time() . '||' . $data;
    return file_put_contents(SC_CACHE_FILE, $contents, LOCK_EX) !== false;
}

/**
 * Read cache if present.
 *
 * @return array{timestamp:int,data:string}|null
 */
function readCache(): ?array
{
    if (!is_file(SC_CACHE_FILE) || !is_readable(SC_CACHE_FILE)) {
        return null;
    }

    $contents = file_get_contents(SC_CACHE_FILE);
    if ($contents === false || $contents === '') {
        return null;
    }

    $parts = explode('||', $contents, 2);
    if (count($parts) !== 2) {
        return null;
    }

    return [
        'timestamp' => (int) $parts[0],
        'data' => $parts[1],
    ];
}

/**
 * Determine whether cache refresh is needed.
 */
function shouldRefreshCache(bool $cacheOn, int $cacheTime): bool
{
    if (!$cacheOn) {
        return true;
    }

    $cache = readCache();
    if ($cache === null) {
        return true;
    }

    return (time() - $cache['timestamp']) > $cacheTime;
}

/**
 * Output JavaScript used by legacy embeds.
 */
function outputJavascript(int $pollIntervalMs): void
{
    header('Content-Type: application/javascript; charset=UTF-8');
    ?>
(function () {
    'use strict';

    const pollInterval = <?php echo $pollIntervalMs; ?>;
    let lastShoutcastData = null;

    function updateText(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    function updateShoutcastDivs(parts) {
        updateText('sc-listenercount', parts[0] ?? '0');
        updateText('sc-status', parts[1] ?? 'Off air');
        updateText('sc-servercount', parts[2] ?? '0');
        updateText('sc-peaklisteners', parts[3] ?? '0');
        updateText('sc-maxlisteners', parts[4] ?? '0');
        updateText('sc-uniquelisteners', parts[5] ?? '0');
        updateText('sc-bitrate', parts[6] ?? '0');
        updateText('sc-song', parts[7] ?? 'N/a');
    }

    function runShoutcastPull() {
        const url = new URL(window.location.href);
        url.searchParams.set('ajaxsync', 'get-shoutcast-update');
        url.searchParams.set('ms', Date.now().toString());

        fetch(url.toString(), {
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (text) {
                if (text !== lastShoutcastData) {
                    lastShoutcastData = text;
                    updateShoutcastDivs(text.split('|'));
                }
            })
            .catch(function () {
                // Keep polling silently for legacy drop-in behavior.
            })
            .finally(function () {
                window.setTimeout(runShoutcastPull, pollInterval);
            });
    }

    runShoutcastPull();
}());
    <?php
}

$ajaxSync = isset($_GET['ajaxsync']) ? (string) $_GET['ajaxsync'] : '';

if ($ajaxSync === 'get-shoutcast-update') {
    header('Content-Type: text/plain; charset=UTF-8');

    if (shouldRefreshCache($cacheOn, $cacheTime)) {
        $payload = buildPayload($masterServer, $slaveServers, $onAirStatus, $offAirStatus);
        $output = payloadToLegacyString($payload);
        writeCache($output);
        echo $output;
        exit;
    }

    $cache = readCache();
    if ($cache !== null) {
        echo $cache['data'];
        exit;
    }

    $payload = getOfflinePayload($offAirStatus);
    echo payloadToLegacyString($payload);
    exit;
}

if ($ajaxSync === 'get-shoutcast-js') {
    $effectiveUpdateTime = $ajaxUpdateTime;

    if (!$ajaxUpdateTimeLocked && isset($_GET['updatetime']) && is_numeric($_GET['updatetime'])) {
        $effectiveUpdateTime = max(1, (int) $_GET['updatetime']);
    }

    outputJavascript($effectiveUpdateTime * 1000);
    exit;
}

?>
