<?php
/**
 * Kush Palace Mega Bot
 * User: Wodomon
 * Date: 15.08.2022
 * Time: 22:50
 */

use TelegramBot\Api\BotApi;

require_once "vendor/autoload.php";

$config = require(__DIR__ . '/config.php');

try {
    if (!empty($config['url'])) {
        $bot = new BotApi($config['key']);
        $bot->call('setWebhook', ['url' => $config['url'], 'allowed_updates' => ['message', 'channel_post']]);
    }
} catch (Exception $e) {
    $e->getMessage();
}