<?php
set_time_limit(1200);
ini_set('memory_limit', '512M');

function updateEpgToGitHub() {
    $epgContent = mergeEPGData();
    if ($epgContent) {
        file_put_contents('epg.xml', $epgContent); 
        commitAndPushChanges(); 
    } else {
        error_log('Failed to fetch or merge EPG data.');
    }
}

function fetchEPGContent($url) {
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Enable following redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Set timeout

    // Execute the cURL request and fetch response
    $content = curl_exec($ch);
    
    // Check for cURL errors
    if ($content === false) {
        $error = curl_error($ch);
        error_log("Failed to fetch URL: $url. cURL Error: $error");
        curl_close($ch);
        return false;
    }

    curl_close($ch);
    return $content;
}

function fixXMLIssues($xmlContent) {
    $xmlContent = str_replace('&amp;amp;', '&amp;', $xmlContent);   
    $xmlContent = preg_replace('/<\/programme>\s*<programme/', "</programme>\n<programme", $xmlContent);
    $xmlContent = preg_replace('/[^\x20-\x7E]/', '', $xmlContent);
    return $xmlContent;
}

function mergeEPGData() {
    $gasKey = getenv('GAS_KEY') ?: ($_ENV['GAS_KEY'] ?? $_SERVER['GAS_KEY'] ?? false);

    // Log the presence of the GAS_KEY for debugging (log only part of it for security)
    if ($gasKey !== false) {
        error_log('GAS_KEY is set and starts with: ' . substr($gasKey, 0, 4) . '...');
    } else {
        error_log('GAS_KEY is not set.');
    }

    $epgUrls = [
        "https://script.google.com/macros/s/AKfycbzAQcsx5OgIXo0VS6RXVCQ4BCP6J7A6AstliLtNpvUoijt2lXA7IZ-rL0ekJPTu0GPXQg/exec?pass=$gasKey",
        "https://raw.githubusercontent.com/matthuisman/i.mjh.nz/master/PlutoTV/us.xml",
        "https://epg.pw/xmltv/epg_ZA.xml",
        "https://epg.pw/api/epg.xml?channel_id=9025",
        "https://epg.pw/api/epg.xml?channel_id=8862",
        "https://epg.pw/api/epg.xml?channel_id=8306"
    ];

    $mergedXml = new SimpleXMLElement('<tv/>');

    foreach ($epgUrls as $url) {
        $epgContent = null;
        $attempts = 0;
        $maxAttempts = 3;
        $delay = 1; // 1 second delay between retries

        while ($attempts < $maxAttempts && $epgContent === null) {
            $epgContent = fetchEPGContent($url);
            if ($epgContent === false) {
                $attempts++;
                error_log("Attempt $attempts failed for URL: $url. Retrying in $delay seconds...");
                sleep($delay);
            }
        }

        if ($epgContent) {
            $fixedEpgContent = fixXMLIssues($epgContent);
            $xml = @simplexml_load_string($fixedEpgContent);
            if ($xml === false) {
                error_log("Failed to parse XML from URL: $url");
                continue;
            }

            foreach ($xml->channel as $channel) {
                $dom = dom_import_simplexml($mergedXml);
                $dom2 = dom_import_simplexml($channel);
                $dom->appendChild($dom->ownerDocument->importNode($dom2, true));
            }

            foreach ($xml->programme as $programme) {
                $dom = dom_import_simplexml($mergedXml);
                $dom2 = dom_import_simplexml($programme);
                $dom->appendChild($dom->ownerDocument->importNode($dom2, true));
            }
        } else {
            error_log("Failed to fetch EPG content from URL: $url after $maxAttempts attempts.");
        }
    }

    return $mergedXml->asXML();
}

function commitAndPushChanges() {
    $commands = [
        'git config --global user.email "action@github.com"',
        'git config --global user.name "GitHub Action"',
        'git add epg.xml',
        'git commit -m "Update EPG data" || echo "No changes to commit"',
        'git push https://$GITHUB_ACTOR:$GITHUB_TOKEN@github.com/$GITHUB_REPOSITORY.git'
    ];

    foreach ($commands as $command) {
        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            error_log("Command failed: $command");
            error_log("Output: " . implode("\n", $output));
        }
    }
}

updateEpgToGitHub();
