<?php
// Created By gogetta.teams@gmail.com
// Please leave this in this script.
// https://github.com/gogetta69/TMDB-To-VOD-Playlist

set_time_limit(0); // Suppress the PHP timeout limit
error_reporting(E_ALL);
ini_set('display_errors', 1);

$categoriesUrl = 'https://www.freeomovie.to/wp-json/wp/v2/categories?per_page=100';
$postsUrl = 'https://www.freeomovie.to/wp-json/wp/v2/posts?per_page=100&page=';

function fetchUrl($url) {
    $options = [
        "http" => [
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === FALSE) {
        throw new Exception("Failed to fetch URL: $url");
    }
    return $response;
}

// Fetch categories and extract their IDs and names
$categories = [];
try {
    $response = fetchUrl($categoriesUrl);
    $categoriesData = json_decode($response, true);
    foreach ($categoriesData as $category) {
        $categories[$category['id']] = $category['name'];
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
    exit;
}

// Set total page count (adjust as needed)
$totalPageCount = 100; // Example value, set this as required

// Function to fetch posts with pagination
function fetchPosts($url, $totalPages) {
    $allPosts = [];
    for ($page = 1; $page <= $totalPages; $page++) {
        try {
            $response = fetchUrl($url . $page);
            $posts = json_decode($response, true);
            if (empty($posts)) {
                break;
            }
            $allPosts = array_merge($allPosts, $posts);
            sleep(1);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            break;
        }
    }
    return $allPosts;
}

// Fetch all posts
$allPosts = fetchPosts($postsUrl, $totalPageCount);

// Function to extract all stream URLs from the content
function extractStreamUrls($content) {
    $pattern = '/https?:\/\/[^\s"<]+/i';
    $streamUrls = [];
    if (preg_match_all($pattern, $content, $matches)) {
        foreach ($matches[0] as $url) {
            if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                $url = str_replace('/d/', '/e/', $url);
                $streamUrls[] = $url;
            }
        }
    }
    return $streamUrls;
}

// Function to clean the description by removing URLs and stripping HTML tags
function cleanDescription($content) {
    // Remove URLs
    $content = preg_replace('/https?:\/\/[^\s"<]+/i', '', $content);
    // Decode HTML entities
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
    // Remove newlines
    $content = str_replace("\n", '', $content);
    // Strip HTML tags
    $content = strip_tags($content);
    return $content;
}

// Function to extract the first image URL from the content
function extractFirstImageUrl($content) {
    $pattern = '/https?:\/\/\S+\.(?:jpg|jpeg|png|gif|webp)/i';
    if (preg_match($pattern, $content, $matches)) {
        return $matches[0];
    }
    return '';
}

// Process the data and generate the JSON
$moviesData = [];

$counter = 999999999;
$counter++;
foreach ($allPosts as $movie) {    
    $counter++;
    // Check if necessary fields are set
    if (!isset($movie['title']['rendered']) || !isset($movie['content']['rendered']) || !isset($movie['categories'])) {
        continue;
    }

    $categoryIds = $movie['categories'];
    $categoryNames = [];
    foreach ($categoryIds as $categoryId) {
        if (isset($categories[$categoryId])) {
            $categoryNames[] = $categories[$categoryId];
        }
    }
    $categoryNamesList = implode(', ', $categoryNames);

    // Check if title exists
    $title = html_entity_decode($movie['title']['rendered']);
    if (empty($title)) {
        continue;
    }

    // Extract stream URLs
    $streamUrls = extractStreamUrls($movie['content']['rendered']);
    if (empty($streamUrls)) {
        continue;
    }

    // Clean description and extract poster path
    $description = cleanDescription($movie['content']['rendered']);
    if (empty($description)) {
        $description = "Description: Unknown";
    }

    $posterPath = extractFirstImageUrl($movie['content']['rendered']);

    $moviesData[] = [
        'num' => $counter,
        'name' => $title,
        'stream_type' => 'adult',
        'stream_id' => 0 . $counter,
        'stream_icon' => $posterPath,
        'rating' => 0,
        'rating_5based' => 0,
        'added' => time(),
        'category_id' => '999993',
        'parent_id' => 2,
        'container_extension' => "mp4",
        'custom_sid' => null,
        'direct_source' => '[[SERVER_URL]]/play.php?movieId=' . $counter,
        'plot' => $description,
		'genres' => $categoryNamesList,
        'backdrop_path' => '',
        'group' => 'Adult Movies',
        'sub_group' => isset($categories[$categoryIds[0]]) ? $categories[$categoryIds[0]] : 'Unknown',
        'sources' => $streamUrls
    ];
}

// Save the data as JSON
file_put_contents('adult-movies.json', json_encode($moviesData, JSON_PRETTY_PRINT));

echo "Scraping complete.";
