<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$stream_url = 'https://ais-sa2.cdnstream1.com/1977_128.aac';
$history_file = __DIR__ . '/song_history.json';

$current_full = "Totally RAD Radio! Retro Radio Hits from the 80s to the early 2000s!";
$current_artist = "Totally RAD Radio";
$current_title = "Retro Radio Hits";

$options = [
    'http' => [
        'method' => 'GET',
        'header' => "Icy-MetaData: 1\r\nUser-Agent: Winamp/5.66\r\n",
        'timeout' => 3
    ]
];

$context = stream_context_create($options);
$fp = @fopen($stream_url, 'r', false, $context);

if ($fp) {
    $metaInterval = 0;
    $metaDataHeaders = stream_get_meta_data($fp);
    $wrapperData = $metaDataHeaders['wrapper_data'] ?? [];

    foreach ($wrapperData as $header) {
        if (stripos($header, 'icy-metaint:') === 0) {
            $metaInterval = (int)trim(substr($header, 12));
            break;
        }
    }

    if ($metaInterval > 0) {
        @fread($fp, $metaInterval);
        $lenByte = @fread($fp, 1);
        if ($lenByte !== false) {
            $length = ord($lenByte) * 16;
            if ($length > 0) {
                $rawMeta = @fread($fp, $length);
                if (preg_match("/StreamTitle='(.*?)';/i", $rawMeta, $matches)) {
                    $rawTitle = trim($matches[1]);
                    if (!empty($rawTitle)) {
                        $current_full = $rawTitle;
                        if (strpos($rawTitle, ' - ') !== false) {
                            list($art, $tit) = explode(' - ', $rawTitle, 2);
                            $current_artist = trim($art);
                            $current_title = trim($tit);
                        } else {
                            $current_title = $rawTitle;
                        }
                    }
                }
            }
        }
    }
    @fclose($fp);
}

// Load existing history
$history = [];
if (file_exists($history_file)) {
    $saved_data = json_decode(file_get_contents($history_file), true);
    if (is_array($saved_data)) {
        $history = $saved_data;
    }
}

// Convert history items to flat title strings for checking
$titles = array_map(function($item) {
    return is_array($item) ? ($item['title'] ?? '') : $item;
}, $history);

$is_default = (strpos($current_full, "Three DECADES of Country") !== false);
$top_song = !empty($titles) ? $titles[0] : '';

// If we got a real track and it's different from the current top song
if (!$is_default && $top_song !== $current_full) {
    // Filter out this song if it already exists lower in the history
    $history = array_values(array_filter($history, function($item) use ($current_full) {
        $t = is_array($item) ? ($item['title'] ?? '') : $item;
        return $t !== $current_full;
    }));

    // Put the new song at the top of the stack
    array_unshift($history, [
        'title' => $current_full,
        'time'  => time()
    ]);

    // Slice to keep exactly 3
    $history = array_slice($history, 0, 3);

    // Save back to disk
    @file_put_contents($history_file, json_encode($history));
}

// Build final display array
$display_history = array_map(function($item) {
    return is_array($item) ? ($item['title'] ?? '') : $item;
}, array_slice($history, 0, 3));

$display_history = array_filter($display_history);

// Fallback if empty
if (empty($display_history)) {
    $display_history = [
        "George Strait - Troubadour",
        "Alan Jackson - Chattahoochee",
        "Tim McGraw - Live Like You Were Dying"
    ];
}

echo json_encode([
    'current' => [
        'artist' => $current_artist,
        'title'  => $current_title,
        'full'   => $current_full
    ],
    'history' => array_values($display_history)
]);
exit;
