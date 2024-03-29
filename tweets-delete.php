#!/usr/bin/env php
<?php
require_once 'vendor/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

$dir = dirname(__FILE__);
date_default_timezone_set(LOG_TIMEZONE);

// Get the timestamp of exactly one year ago.
$timestamp = time() - 31557600;

if (is_file("$dir/processed.log")) {
    $log = explode("\n", trim(file_get_contents("$dir/processed.log")));
} else {
    $log = [];
    touch("$dir/processed.log");
}

$jsonFile = "$dir/tweets.json";
if (!is_file($jsonFile)) {
    exit("Unable to open file \"$jsonFile\"\n");
}

$json = json_decode(file_get_contents($jsonFile), true);
if (is_null($json)) {
    exit("Supplied JSON file \"$jsonFile\" is formatted incorrectly. Did you remove the \"window.YTD.tweet.part0 =\" from the beginning?\n");
}

// In 2021 Twitter stopped sorting them by date, so let's do Twitter's work for
// them, shall we?
if (isset($json[0]['sorted']) && $json[0]['sorted']) {
    usort($json, function($a, $b) {
        $a = strtotime($a['tweet']['created_at']);
        $b = strtotime($b['tweet']['created_at']);

        return ($a > $b) ? -1 : 1;
    });

    $json[0]['sorted'] = true;
    file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT);
}

// The first in the list should be the newest tweet. If its timestamp is less
// than a year from now a new tweet archive is necessary to continue. Exit.
$tweetTimestamp = strtotime($json[0]['tweet']['created_at']);
if ($tweetTimestamp < $timestamp) {
    mail(EMAIL_ADDRESS, "Tweets Delete requires assistance: ".date("Y-m-d"), wordwrap("Tweets Delete requires a new saved Twitter archive (https://twitter.com/settings/account#tweet_export).", 70), 'From: Tweets Delete <' . EMAIL_ADDRESS . '>');
    exit(1);
}

$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

foreach ($json as $tweet) {
    $tweet = $tweet['tweet'];
    $id = $tweet['id_str'];

    if (in_array($id, $log)) {
        continue;
    }

    $tweetTimestamp = strtotime($tweet['created_at']);
    if ($tweetTimestamp >= $timestamp || in_array($id, IGNORE_TWEETS)) {
        continue;
    }

    $result = $twitter->post('statuses/destroy', [ 'id' => $id ]);
    if (!isset($result->errors) || count($result->errors) === 0) {
        echo date(LOG_TIMESTAMP_FORMAT) . " Deleted #{$id} | {$tweet['full_text']}\n";
    }

    file_put_contents("$dir/processed.log", "{$id}\n", FILE_APPEND);

    // Twitter API allows for 300 writes per minute, so delay for 200ms between each
    // call just to be safe.
    echo date(LOG_TIMESTAMP_FORMAT) . " Waiting for 200ms\n";
    usleep(200000);
}