<?php

function generateUuidV4() {
    $data = bin2hex(random_bytes(16));
    return sprintf('%s-%s-%s-%s-%s',
        substr($data, 0, 8),
        substr($data, 8, 4),
        substr($data, 12, 4),
        substr($data, 16, 4),
        substr($data, 20)
    );
}

function generateShortHexSid($length) {
    $chars = 'abcdef0123456789';
    $sid = '';
    for ($i = 0; $i < $length; $i++) {
        $sid .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $sid;
}

function grabEPG() {
    echo '[INFO] Grabbing EPG...' . PHP_EOL;

    $url = "https://i.mjh.nz/PlutoTV/.app.json";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (isset($data['regions']['us'])) {
        $channels = $data['regions']['us']['channels'];
        processEPG($channels);
    } else {
        echo '[ERROR] US region not found in data.' . PHP_EOL;
    }
}

function processEPG($channels) {
    $epgUrl = "https://raw.githubusercontent.com/gogetta69/public-files/main/Pluto-TV/us.xml";
    $m3u8 = "#EXTM3U url-tvg=\"{$epgUrl}\"\n";
    $tvElements = [];
    $processedPrograms = [];

    foreach ($channels as $channelId => $channel) {
        if (isset($channel['url'])) {
            $m3uUrl = $channel['url'];

            $m3u8 .= "#EXTINF:0 tvg-id=\"{$channelId}\" tvg-logo=\"{$channel['logo']}\" group-title=\"{$channel['group']}\", {$channel['name']}\n{$m3uUrl}\n\n";

            echo '[INFO] Adding ' . $channel['name'] . ' channel.' . PHP_EOL;

            $channelElement = [
                'name' => 'channel',
                'attrs' => ['id' => $channelId],
                'children' => [
                    ['name' => 'display-name', 'text' => $channel['name']],
                    ['name' => 'icon', 'attrs' => ['src' => $channel['logo']]]
                ]
            ];
            $tvElements[] = $channelElement;
        } else {
            echo "[DEBUG] Skipping 'fake' channel " . $channel['name'] . '.' . PHP_EOL;
        }

        if (isset($channel['programs'])) {
            foreach ($channel['programs'] as $programme) {
                $start = date('YmdHis O', $programme[0]);
                $stop = date('YmdHis O', strtotime('+2 hours', $programme[0])); // Adjust the duration as needed

                $programmeElement = [
                    'name' => 'programme',
                    'attrs' => [
                        'start' => $start,
                        'stop' => $stop,
                        'channel' => $channelId
                    ],
                    'children' => [
                        ['name' => 'title', 'attrs' => ['lang' => 'en'], 'text' => $programme[1]],
                        ['name' => 'category', 'attrs' => ['lang' => 'en'], 'text' => $channel['group']],
                        // Add more elements as needed
                    ]
                ];

                $tvElements[] = $programmeElement;
            }
        }
    }

    saveFile('Pluto-TV/us.m3u8', $m3u8);

    if (!empty($tvElements)) {
        $tv = new SimpleXMLElement('<tv/>');
        foreach ($tvElements as $element) {
            createElementFromJson($tv, $element);
        }
        saveFile('Pluto-TV/us.xml', $tv->asXML());
        echo '[SUCCESS] Wrote the EPG to us.xml!' . PHP_EOL;
    } else {
        echo '[ERROR] No valid data to generate EPG.' . PHP_EOL;
    }
}

function createElementFromJson($parent, $json) {
    $element = $parent->addChild($json['name']);

    if (isset($json['attrs'])) {
        foreach ($json['attrs'] as $key => $value) {
            $element->addAttribute($key, $value);
        }
    }

    if (isset($json['text'])) {
        $element[0] = $json['text'];
    }

    if (isset($json['children'])) {
        foreach ($json['children'] as $child) {
            createElementFromJson($element, $child);
        }
    }
}

function saveFile($filename, $content) {
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filename;
    file_put_contents($filePath, $content);
    echo "[SUCCESS] Saved file: {$filename}" . PHP_EOL;
}

// Run the function to grab the EPG
grabEPG();

?>
