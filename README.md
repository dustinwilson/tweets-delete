# Tweets Delete

Delete old tweets.

This is a php script meant to be run on a UNIX-compatible system as a cronjob where once a day tweets older than one year will be deleted.

## Warning

**This deletes your tweets. I accept no responsibility.**

## Install

1. [Request your Twitter archive](https://twitter.com/settings/account#tweet-export).
2. [Create an app on Twitter](https://apps.twitter.com) to get your keys.
3. Download or clone this repository.
4. Rename `config.example.php` to `config.php`.
5. `composer install`.
6. `./tweets-delete.php`.

[MIT License](https://opensource.org/licenses/MIT)
