#!/usr/bin/env php
<?php
ini_set('memory_limit','1G');

require_once 'vendor/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

$dir = dirname(__FILE__);

// Get the timestamp of exactly one year ago.
$timestamp = time() - 31557600;

if (is_file("$dir/processed.log")) {
    $log = explode("\n", trim(file_get_contents("$dir/processed.log")));
} else {
    $log = [];
    touch("$dir/processed.log");
}

$csv = "$dir/tweets.csv";
if (($handle = fopen($csv, 'r')) === FALSE) {
    exit("Unable to open file \"$csv\"\n");
}

// Skip the headers.
fgetcsv($handle, 0, ',');

/*
 0 => 'tweet_id',
 1 => 'in_reply_to_status_id',
 2 => 'in_reply_to_user_id',
 3 => 'timestamp',
 4 => 'source',
 5 => 'text',
 6 => 'retweeted_status_id',
 7 => 'retweeted_status_user_id',
 8 => 'retweeted_status_timestamp',
 9 => 'expanded_urls',
*/

// The first in the list should be the newest tweet. If its timestamp is less
// than a year from now a new tweet archive is necessary to continue. Exit.
$t = fgetcsv($handle, 0, ',');
$tweetTimestamp = strtotime($t[3]);
if ($tweetTimestamp < $timestamp) {
    mail('dustin@dustinwilson.com', "Tweets Delete requires assistance: ".date("Y-m-d"), wordwrap("Tweets Delete requires a new saved Twitter archive (https://twitter.com/settings/account#tweet_export).", 70), 'From: Tweets Delete <system@dustinwilson.com>');
    exit(1);
}

$twitter = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

do {
    if (in_array($t[0], $log)) {
        continue;
    }

    $tweetTimestamp = strtotime($t[3]);
    if ($tweetTimestamp >= $timestamp) {
        continue;
    }

    $result = $twitter->post('statuses/destroy', [ 'id' => $t[0] ]);
    if (isset($result->errors) && count($result->errors) === 0) {
        echo "Deleted #{$t[0]} | {$t[5]}\n";
    }

    file_put_contents("$dir/processed.log", "{$t[0]}\n", FILE_APPEND);

    // Twitter API allows for 300 writes per minute, so delay for 200ms between each
    // call just to be safe.
    echo "Waiting for 200ms\n";
    usleep(200000);
} while (($t = fgetcsv($handle, 0, ',')) !== false);