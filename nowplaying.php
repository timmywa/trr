<?php
/**
 * Totally RAD Radio - now playing endpoint (improved drop-in replacement)
 * Save on the server as: nowplaying.php
 *
 * Same output and same song_history.json format as before:
 *   {"current": {"artist","title","full"}, "history": ["Artist - Title", ...up to 4]}
 *
 * Improvements: one stream check is shared by all visitors for a few seconds,
 * the stream data is read completely (no partial reads), the last known song is
 * kept if the stream briefly drops, and odd text encodings no longer break the JSON.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ---------------- SETTINGS ----------------
$stream_url   = 'https://ais-sa2.cdnstream1.com/1977_128.aac';
$ignore_tags  = ['adwtag_'];   // metadata containing any of these = commercial break
$cache_ttl    = 10;            // seconds; visitors within this window share one stream check

$history_file = __DIR__ . '/song_history.json';
$cache_file   = __DIR__ . '/nowplaying_cache.json';
$lock_file    = __DIR__ . '/nowplaying.lock';
// ------------------------------------------

function respond($payload) {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readCache($cache_file, $ttl = null) {
    clearstatcache(true, $cache_file);
    if (!is_file($cache_file)) return null;
    if ($ttl !== null && (time() - filemtime($cache_file)) >= $ttl) return null;
    $data = json_decode(@file_get_contents($cache_file), true);
    return is_array($data) ? $data : null;
}

// fread() on a network stream can return less than asked, so loop until we have it all
function readBytes($fp, $n) {
    $data = '';
    while (strlen($data) < $n && !feof($fp)) {
        $chunk = fread($fp, $n - strlen($data));
        if ($chunk === false || $chunk === '') break; // closed or timed out
        $data .= $chunk;
    }
    return $data;
}

// Returns the raw StreamTitle, '' if the stream sent an empty one, or null if it could not be read
function fetchStreamTitle($url) {
    $context = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "Icy-MetaData: 1\r\nUser-Agent: Winamp/5.66\r\n",
        'timeout' => 4,
    ]]);
    $fp = @fopen($url, 'r', false, $context);
    if (!$fp) return null;
    stream_set_timeout($fp, 4);

    $metaInterval = 0;
    $info = stream_get_meta_data($fp);
    foreach (($info['wrapper_data'] ?? []) as $header) {
        if (stripos($header, 'icy-metaint:') === 0) {
            $metaInterval = (int)trim(substr($header, 12));
            break;
        }
    }

    $title = null;
    if ($metaInterval > 0) {
        // Check up to 3 metadata blocks, since some servers send an empty one first
        for ($i = 0; $i < 3 && $title === null; $i++) {
            if (strlen(readBytes($fp, $metaInterval)) < $metaInterval) break;
            $lenByte = readBytes($fp, 1);
            if ($lenByte === '') break;
            $length = ord($lenByte) * 16;
            if ($length > 0) {
                $raw = readBytes($fp, $length);
                if (preg_match("/StreamTitle='(.*?)';/s", $raw, $m)) {
                    $title = trim($m[1]);
                }
            }
        }
    }
    fclose($fp);
    return $title;
}

// Make sure the text is valid UTF-8 so json_encode never fails
function toUtf8($s) {
    if (preg_match('//u', $s)) return $s;
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    return @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s) ?: $s;
}

// 1) Serve the cached answer if it is fresh
$cached = readCache($cache_file, $cache_ttl);
if ($cached) respond($cached);

// 2) Only one request at a time refreshes; the rest wait and then reuse its result
$lock = @fopen($lock_file, 'c');
if ($lock) flock($lock, LOCK_EX);

$cached = readCache($cache_file, $cache_ttl);
if ($cached) respond($cached);

// 3) Ask the stream what is playing
$raw = fetchStreamTitle($stream_url);

if ($raw === null) {
    // Stream unreachable right now: keep showing the last known answer and retry shortly
    $stale = readCache($cache_file);
    if ($stale) {
        @touch($cache_file);
        respond($stale);
    }
    $raw = '';
}

$raw = toUtf8($raw);

// Default (commercial break / no info)
$is_default     = true;
$current_full   = "Totally RAD Radio - 80s to Early 2000s";
$current_artist = "Totally RAD Radio";
$current_title  = "80s to Early 2000s";

if ($raw !== '') {
    $is_ad = false;
    foreach ($ignore_tags as $tag) {
        if (stripos($raw, $tag) !== false) { $is_ad = true; break; }
    }
    if (!$is_ad) {
        $is_default   = false;
        $current_full = $raw;
        if (strpos($raw, ' - ') !== false) {
            list($art, $tit) = explode(' - ', $raw, 2);
            $current_artist = trim($art);
            $current_title  = trim($tit);
        } else {
            $current_title  = $raw;
        }
    }
}

// 4) Update song history (same file and format as before)
$history = [];
if (is_file($history_file)) {
    $saved = json_decode(@file_get_contents($history_file), true);
    if (is_array($saved)) $history = $saved;
}

$titles   = array_map(function ($item) {
    return is_array($item) ? ($item['title'] ?? '') : $item;
}, $history);
$top_song = !empty($titles) ? $titles[0] : '';

if (!$is_default && $top_song !== $current_full) {
    // Remove this song if it already exists lower in the history
    $history = array_values(array_filter($history, function ($item) use ($current_full) {
        $t = is_array($item) ? ($item['title'] ?? '') : $item;
        return $t !== $current_full;
    }));

    // Put the new song at the top of the stack
    array_unshift($history, ['title' => $current_full, 'time' => time()]);

    // Keep 4 items total so shifting off the current song leaves 3 history items
    $history = array_slice($history, 0, 4);

    @file_put_contents($history_file, json_encode($history, JSON_UNESCAPED_UNICODE));
}

// 5) Build the answer
$display_history = array_map(function ($item) {
    return is_array($item) ? ($item['title'] ?? '') : $item;
}, array_slice($history, 0, 4));
$display_history = array_filter($display_history);

if (empty($display_history)) {
    $display_history = [
        "Michael Jackson - Billie Jean",
        "Prince - 1999",
        "Madonna - Like a Prayer"
    ];
}

$payload = [
    'current' => [
        'artist' => $current_artist,
        'title'  => $current_title,
        'full'   => $current_full
    ],
    'history' => array_values($display_history)
];

@file_put_contents($cache_file, json_encode($payload, JSON_UNESCAPED_UNICODE));
respond($payload);
