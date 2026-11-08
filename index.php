<?php
// Pre-fetch stream data & iTunes artwork on server-load for instant rendering
$history_file = __DIR__ . '/song_history.json';
$initial_current_artist = "Totally RAD Radio";
$initial_current_title = "80s to Early 2000s";
$initial_artwork = "/images/trr-logo.webp"; // Fallback logo
$initial_history = [
    "Michael Jackson - Billie Jean",
    "Prince - 1999",
    "Madonna - Like a Prayer"
];

function getITunesArtwork($artist, $title) {
    if (empty($artist) || $artist === "Totally RAD Radio") {
        return "/images/trr-logo.webp";
    }
    $query = urlencode($artist . ' ' . $title);
    $url = "https://itunes.apple.com/search?term={$query}&entity=song&limit=1";
    
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0\r\n",
            'timeout' => 2
        ]
    ];
    $context = stream_context_create($opts);
    $json = @file_get_contents($url, false, $context);
    if ($json) {
        $data = json_decode($json, true);
        if (!empty($data['results'][0]['artworkUrl100'])) {
            // Upgrade artwork resolution from 100x100 to 300x300
            return str_replace('100x100bb', '300x300bb', $data['results'][0]['artworkUrl100']);
        }
    }
    return "/images/trr-logo.webp";
}

if (file_exists($history_file)) {
    $saved_data = json_decode(file_get_contents($history_file), true);
    if (!empty($saved_data) && is_array($saved_data)) {
        $display_saved = array_map(function($item) {
            return is_array($item) ? ($item['title'] ?? '') : $item;
        }, $saved_data);
        
        $display_saved = array_filter($display_saved);
        if (!empty($display_saved)) {
            // Top item is current live song
            $top_full = array_shift($display_saved);
            if (strpos($top_full, ' - ') !== false) {
                list($initial_current_artist, $initial_current_title) = explode(' - ', $top_full, 2);
            } else {
                $initial_current_title = $top_full;
            }
            $initial_artwork = getITunesArtwork($initial_current_artist, $initial_current_title);
            
            // Remaining items form the true past track history
            if (!empty($display_saved)) {
                $initial_history = array_values(array_slice($display_saved, 0, 3));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Totally RAD Radio - Listen Live</title>
    
    <!-- Preload critical assets -->
    <link rel="preload" href="css/main.css?v=1.6" as="style">
    <link rel="preload" href="/images/trr-logo.webp" as="image" fetchpriority="high">
    <link rel="stylesheet" href="css/main.css?v=1.6">

    <meta name="description" content="Tune into Totally RAD Radio for retro radio hits from the 80s to the early 2000s. Listen live now!">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://totallyradradio.com/">
    
    <!-- Open Graph / Social -->
    <meta property="og:title" content="Totally RAD Radio - Listen Live">
    <meta property="og:site_name" content="Totally RAD Radio">
    <meta property="og:description" content="Tune into Totally RAD Radio for retro radio hits from the 80s to the early 2000s. Listen live now!">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://totallyradradio.com/">
    <meta property="og:image" content="https://totallyradradio.com/images/trr-logo.webp">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@totallyradradio">
    <meta name="twitter:image" content="https://totallyradradio.com/images/trr-logo.webp">
    
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    
    <style>
        .song-artist, .history-artist { font-weight: bold; }
        .song-title, .history-title { font-weight: normal; }
        
        .player-card {
            background: #000000;
            border: 1px solid #2f63a0;
            border-radius: 8px;
            padding: 20px;
            margin: 0 auto 20px auto;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .now-playing-wrapper {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .album-art-box {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            border-radius: 6px;
            overflow: hidden;
            background: #111;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .album-art-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .track-info {
            flex-grow: 1;
            overflow: hidden;
        }

        .now-playing-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7094bf;
            display: block;
            margin-bottom: 4px;
        }

        .current-song-text {
            font-size: 15px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .history-section {
            margin-top: 12px;
        }

        .history-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            display: block;
            margin-bottom: 6px;
        }

        .history-list {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 13.5px;
        }

        .history-list li {
            padding: 6px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .history-list li:last-child {
            border-bottom: none;
        }
    </style>

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "RadioBroadcastService",
      "name": "Totally RAD Radio",
      "url": "https://totallyradradio.com/",
      "logo": "https://totallyradradio.com/images/trr-logo.webp",
      "slogan": "Retro radio hits from the 80s to the early 2000s.",
      "description": "Tune into Totally RAD Radio for retro radio hits from the 80s to the early 2000s. Listen live now!",
      "sameAs": [
        "https://www.facebook.com/trr.radio",
        "https://x.com/totallyradradio",
        "https://instagram.com/totallyradradio"
      ]
    }
    </script>
</head>
<body>
    <main class="container">
        <header class="header">
            <a href="https://totallyradradio.com/" class="logo-link">
                <img src="/images/trr-logo.webp" alt="Totally RAD Radio Logo" fetchpriority="high" loading="eager" width="300" height="300">
            </a>
            <h1>Totally RAD Radio!</h1>
            <h2>Listen to retro radio hits from the 80s to the early 2000s!</h2>
        </header>

        <section class="listen" id="main-listen-section">
            <h2>Listen LIVE!</h2>
            
            <!-- Metadata & Artwork Centered Card -->
            <div class="player-card">
                <div class="now-playing-wrapper">
                    <div class="album-art-box">
                        <img id="album-art" src="<?php echo htmlspecialchars($initial_artwork); ?>" alt="Album Artwork" width="90" height="90">
                    </div>
                    <div class="track-info">
                        <span class="now-playing-label">Now Playing</span>
                        <div class="current-song-text">
                            <span class="song-artist" id="curr-artist"><?php echo htmlspecialchars($initial_current_artist); ?></span> - 
                            <span class="song-title" id="curr-title"><?php echo htmlspecialchars($initial_current_title); ?></span>
                        </div>
                    </div>
                </div>

                <div class="history-section">
                    <span class="history-label">Recently Played</span>
                    <ul id="song-history-list" class="history-list">
                        <?php foreach($initial_history as $hist_item): 
                            if(strpos($hist_item, ' - ') !== false) {
                                list($h_art, $h_tit) = explode(' - ', $hist_item, 2);
                            } else {
                                $h_art = "Totally RAD Radio";
                                $h_tit = $hist_item;
                            }
                        ?>
                            <li>
                                <span class="history-artist"><?php echo htmlspecialchars(trim($h_art)); ?></span> - <span class="history-title"><?php echo htmlspecialchars(trim($h_tit)); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- OnlineRadioBox Player Widget (Commented Out)
            <div class="radio-widget-wrapper" style="position: relative; isolation: isolate; overflow: hidden; border-radius: var(--border-radius);">
                <div class="orbP cmpct" id="orb_player_31641a3484918225" vlm="0.8">
                ...
                </div>
            </div>
            -->

            <div class="stream-links">
                <a href="https://ais-sa2.cdnstream1.com/1977_128.aac" target="_blank" rel="noopener noreferrer">PLAY 128 kb/s AAC (desktop)</a>
                <a href="https://ais-sa2.cdnstream1.com/1977_64.aac" target="_blank" rel="noopener noreferrer">PLAY 64 kb/s HE-AAC (mobile)</a>
            </div>

            <div class="directory-section">
                <h3>Find us on these radio directories:</h3>
                
                <div class="expandable-wrapper" id="services-wrapper">
                    <ul class="twocolumns">
                        <li><a href="https://www.audacy.com/stations/totallyradradio" target="_blank" rel="noopener noreferrer">Audacy Radio</a></li>
                        <li><a href="https://mytuner-radio.com/radio/totally-rad-radio-507343/" target="_blank" rel="noopener noreferrer">MyTuner Radio</a></li>
                        <li><a href="https://www.radio.net/s/totallyradradio" target="_blank" rel="noopener noreferrer">Radio.net</a></li>
                        <li><a href="https://radiostationslive.com/station/totally-rad-radio" target="_blank" rel="noopener noreferrer">Radio Stations Live</a></li>
                        <li><a href="https://streema.com/radios/Totally_RAD_Radio" target="_blank" rel="noopener noreferrer">Streema</a></li>
                        <li><a href="https://us-radio.com/totally-rad-radio-r23635" target="_blank" rel="noopener noreferrer">US-Radio.com</a></li>
                        <li><a href="https://listenonlineradio.com/us/totally-rad-radio" target="_blank" rel="noopener noreferrer">Listen Online Radio</a></li>
                        <li><a href="https://onlineradiobox.com/us/totallyrad/" target="_blank" rel="noopener noreferrer">Online Radio Box</a></li>
                        <li><a href="https://netchoiceradio.com/station/totally-rad-radio" target="_blank" rel="noopener noreferrer">Net Choice Radio</a></li>
                        <li><a href="https://appradiofm.com/radio/Totally-RAD-Radio-Salt%20Lake%20City-Utah-US-yeeth" target="_blank" rel="noopener noreferrer">RadioFM</a></li>
                        <li><a href="https://www.radioplug.co.uk/channel/Totally-RAD-Radio" target="_blank" rel="noopener noreferrer">RadioPlug</a></li>
                        <li><a href="https://radoxo.com/usa/totally-rad-radio" target="_blank" rel="noopener noreferrer">Radoxo</a></li>
                        <li><a href="https://theonestopradio.com/radio/us-totally-rad-radio" target="_blank" rel="noopener noreferrer">OneStop Radio</a></li>
                        <li><a href="https://liveonlineradio.net/totally-rad-radio" target="_blank" rel="noopener noreferrer">Live Online Radio</a></li>
                        <li><a href="https://forwardmystream.com/station/totallyradradio" target="_blank" rel="noopener noreferrer">Forward My Stream</a></li>
                        <li><a href="https://www.liveradio.ie/stations/totally-rad-radio" target="_blank" rel="noopener noreferrer">LiveRadio IE</a></li>
                        <li><a href="https://www.liveradio.uk/stations/totally-rad-radio" target="_blank" rel="noopener noreferrer">LiveRadio UK</a></li>
                        <li><a href="https://liveradious.com/18572-totally-rad-radio.html" target="_blank" rel="noopener noreferrer">Live Radio US</a></li>
                        <li><a href="https://radioguide.fm/internet-radio-usa-utah/totally-rad-radio" target="_blank" rel="noopener noreferrer">RadioGuide.FM</a></li>
                    </ul>
                </div>
                <button type="button" onclick="toggleServices()" id="view-more-btn" class="show-more-btn">View All Services</button>
            </div>

            <p class="small-text">NOTE: Clicking the links above will open a new page and audio may begin playing automatically.</p>
        </section>

        <section class="social">
            <h2>Get Social!</h2>
            <ul class="social-links">
                <li>
                    <a href="https://www.facebook.com/trr.radio" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Facebook">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.instagram.com/totallyradradio" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Instagram">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://www.threads.net/@totallyradradio" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Threads">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.292 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.781 3.632 2.695 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.790-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.47-1.364 0 0-.648-.642-1.978-.69-1.118-.04-2.282.164-3.063.9-.843.794-1.222 1.974-1.222 1.974l-1.72-.453s.416-1.545 1.518-2.633c1.122-1.106 2.782-1.429 4.396-1.378 1.878.059 3.025.895 3.025.895.565.565 1.033 1.472 1.248 2.48a9.086 9.086 0 0 1 2.181 1.447c1.04.97 1.7 2.250 1.968 3.805.616 3.573-1.194 6.04-3.542 7.443-1.618.967-3.584 1.35-5.842 1.364zm3.314-14.7c-1.857-.03-3.583.123-4.844.372.494.372 1.375 1.193 1.375 3.908 0 .969-.179 1.827-.533 2.565a7.842 7.842 0 0 0 1.79-2.565c.533-1.07.794-2.59.794-4.51 0-1.193-.18-2.016-.582-2.77z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://x.com/totallyradradio" target="_blank" rel="noopener noreferrer" aria-label="Follow us on X">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                </li>
                <li>
                    <a href="https://bsky.app/profile/totallyradradio.com" target="_blank" rel="noopener noreferrer" aria-label="Follow us on Bluesky">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 2.105.139 3.107.139 5.498.139 6.789c0 1.24.375 2.533.936 3.714.561 1.181 1.354 2.293 2.209 3.293C5.225 15.849 7.724 17.849 12 18.4c4.276-.551 6.775-2.551 8.716-4.604.855-1 1.648-2.112 2.209-3.293.561-1.181.936-2.474.936-3.714 0-1.291 0-3.682-.763-4.684C22.439 1.266 21.434.944 18.798 2.805 16.046 4.747 13.087 8.686 12 10.8z"/></svg>
                    </a>
                </li>
            </ul>
        </section>
        
<address itemscope itemtype="https://schema.org/Organization">
    If you need help or something isn't working right, please <a href="mailto:help@totallyradradio.com"><span itemprop="email">help@totallyradradio.com</span></a>, text, or call us: <a href="tel:+18017884916" itemprop="telephone">+1 (801) 788-4916</a>.
</address>

    </main>

    <footer class="footer">
        <p class="smaller-text">Thank you for visiting Totally RAD Radio!</p>
        <p class="smaller-text">Copyright © 2024 - <span id="year"></span> SuiteSounds Entertainment, LLC. All rights reserved.</p>
        <div class="partner-logos">
            <a href="https://stationplaylist.com" target="_blank" rel="noopener noreferrer"><img src="images/splpowered.png" alt="Powered By Station Playlist" loading="lazy" width="88" height="31" class="spl-image"></a>
            <a href="https://angryaudio.com/products/c6s/" target="_blank" rel="noopener noreferrer"><img src="images/AAlogo88x31.jpg" alt="Angry Audio Chameleon Audio Processing" width="88" height="31" loading="lazy" class="spl-image"></a>
        </div>
        <form action="https://www.paypal.com/donate" method="post" target="_blank">
            <input type="hidden" name="campaign_id" value="97J5C2DDTULW4">
            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" name="submit" alt="Donate with PayPal">
        </form>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const yearElement = document.getElementById("year");
            if (yearElement) yearElement.textContent = new Date().getFullYear();

            // Initial fetch & poll loop
            fetchNowPlaying();
            setInterval(fetchNowPlaying, 15000);
        });

        let currentTrackKey = "";

        function fetchNowPlaying() {
            fetch('nowplaying.php?t=' + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (data && data.current) {
                        const artist = data.current.artist || 'Totally RAD Radio';
                        const title = data.current.title || '80s to Early 2000s';
                        
                        document.getElementById('curr-artist').textContent = artist;
                        document.getElementById('curr-title').textContent = title;

                        // Fetch iTunes artwork whenever track changes
                        const trackKey = artist + " - " + title;
                        if (trackKey !== currentTrackKey) {
                            currentTrackKey = trackKey;
                            updateArtwork(artist, title);
                        }
                    }
                    if (data && data.history && Array.isArray(data.history)) {
                        let historyItems = [...data.history];
                        
                        // Shift off top item if it matches current live song
                        const liveFull = data.current ? data.current.full : "";
                        if (historyItems.length > 0 && historyItems[0] === liveFull) {
                            historyItems.shift();
                        }

                        const historyList = document.getElementById('song-history-list');
                        historyList.innerHTML = '';
                        
                        historyItems.slice(0, 3).forEach(item => {
                            let art = "Totally RAD Radio";
                            let tit = item;
                            if (item.includes(' - ')) {
                                const parts = item.split(' - ');
                                art = parts[0].trim();
                                tit = parts.slice(1).join(' - ').trim();
                            }
                            const li = document.createElement('li');
                            li.innerHTML = `<span class="history-artist">${escapeHtml(art)}</span> - <span class="history-title">${escapeHtml(tit)}</span>`;
                            historyList.appendChild(li);
                        });
                    }
                })
                .catch(err => console.error("Error updating metadata:", err));
        }

        function updateArtwork(artist, title) {
            const imgEl = document.getElementById('album-art');
            if (artist === 'Totally RAD Radio' || !artist) {
                imgEl.src = '/images/trr-logo.webp';
                return;
            }

            const query = encodeURIComponent(artist + ' ' + title);
            fetch(`https://itunes.apple.com/search?term=${query}&entity=song&limit=1`)
                .then(res => res.json())
                .then(itunesData => {
                    if (itunesData.results && itunesData.results.length > 0) {
                        const artUrl = itunesData.results[0].artworkUrl100.replace('100x100bb', '300x300bb');
                        imgEl.src = artUrl;
                    } else {
                        imgEl.src = '/images/trr-logo.webp';
                    }
                })
                .catch(() => {
                    imgEl.src = '/images/trr-logo.webp';
                });
        }

        function escapeHtml(str) {
            return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        function toggleServices() {
            const wrapper = document.getElementById('services-wrapper');
            const btn = document.getElementById('view-more-btn');
            const expanded = wrapper.classList.toggle('expanded');
            
            btn.textContent = expanded ? 'Show Less' : 'View All Services';
            btn.setAttribute('aria-expanded', expanded);
            
            if (!expanded) {
                document.getElementById('main-listen-section').scrollIntoView({ behavior: 'smooth' });
            }
        }
    </script>
    
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2WKFJL655E"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-2WKFJL655E');
    </script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7092128557292662" crossorigin="anonymous"></script>
</body>
</html>
