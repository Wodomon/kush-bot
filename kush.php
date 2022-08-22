<?php
/**
 * Kush Palace Mega Bot
 * User: Wodomon
 * Date: 15.08.2022
 * Time: 21:03
 */

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Client;
use TelegramBot\Api\Exception;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\MessageEntity;
use TelegramBot\Api\Types\Update;

require_once 'vendor/autoload.php';

/**
 * Data class
 */
class Data
{
    public static function log($data)
    {
        file_put_contents('./data/log.txt', $data . PHP_EOL, FILE_APPEND);
    }

    public static function get($user, $param = null)
    {
        $data = file_get_contents('./data/user' . intval($user) . '.txt');

        try {
            $data = json_decode($data, true);
        } catch (\Exception $e) {
            $data = [];
        }

        return $param ? ($data[$param] ?? null) : $data;
    }

    public static function set($user, $param, $value)
    {
        $data = self::get($user);

        $data[$param] = $value;

        file_put_contents('./data/user' . intval($user) . '.txt', json_encode($data));
    }
}

/**
 * Class for Kush-bot
 */
class Kush
{
    /**
     * @var Client|BotApi $bot
     */
    protected static $bot;

    /**
     * @var string Key for Telegram API
     */
    private static $key;

    /**
     * @var bool User filter
     */
    protected static $filter_users;

    /**
     * @var bool Chat filter
     */
    protected static $filter_chats;

    /**
     * @var int Cooldown for reposts
     */
    protected static $cooldown_repost;
    /**
     * @var int Cooldown for media posts
     */
    protected static $cooldown_media;

    /**
     * @var array Users to be filtered
     */
    protected static $users;

    /**
     * @var array Chats to be filtered
     */
    protected static $chats;

    /**
     * @var array Chats to be logged
     */
    protected static $logs;

    /**
     * Initialization
     */
    public static function init()
    {
        $config = require(__DIR__ . '/config.php');

        self::$key = $config['key'];
        self::$users = $config['users'];
        self::$chats = $config['chats'];
        self::$cooldown_media = $config['cooldown_media'] ?? 3600;
        self::$cooldown_repost = $config['cooldown_repost'] ?? 4320;
        self::$filter_users = $config['filter_users'] ?? false;
        self::$filter_chats = $config['filter_chats'] ?? true;
    }

    /**
     * Main method
     */
    public static function run()
    {
        try {
            self::$bot = new Client(self::$key);

            //Handle text messages
            self::$bot->on(function (Update $update) {
                $message = $update->getMessage();
                if (!$message) return;

                $chat = $message->getChat();
                if (!$chat) return;

                $chatId = $message->getChat()->getId();
                $userId = $message->getFrom()->getId();

                Kush::log($chatId, '----New message: ' . $chatId);
                Kush::log($chatId, 'UserId: ' . $userId);

                Kush::filterUsers($userId, $chatId, $message);
                Kush::filterForwards($userId, $chatId, $message);
                Kush::filterMedia($userId, $chatId, $message);

                Kush::log($chatId, PHP_EOL);
            }, function () {
                return true;
            });

            self::$bot->run();

        } catch (Exception $e) {
            $e->getMessage();
        }
    }

    /**
     * Filter all users posts
     *
     * @param $userId
     * @param $chatId
     * @param Message $message
     */
    protected static function filterUsers($userId, $chatId, Message $message)
    {
        # ban all posts with links from users
        if (in_array($userId, Kush::$users) && Kush::$filter_users) {
            /** @var MessageEntity $entity */
            foreach ($message->getEntities() as $entity) {
                if (in_array($entity->getType(), ['url', 'text_link'])) {
                    Kush::$bot->deleteMessage($chatId, $message->getMessageId());
                }
            }
        }
    }

    /**
     * Filter users forwards in chats
     *
     * @param $userId
     * @param $chatId
     * @param Message $message
     */
    protected static function filterForwards($userId, $chatId, Message $message)
    {
        $forward = $message->getForwardFromChat();
        if ($forward) {
            $forwardId = $forward->getId();

            Kush::log($chatId, 'ForwardId: ' . $forwardId);

            if (Kush::$cooldown_repost && in_array($userId, Kush::$users)) {
                # ban all reposts until cooldown
                $last_repost = (int) Data::get($userId, 'last_repost') ?: 0;

                if ($last_repost + Kush::$cooldown_repost < time()) {
                    Data::set($userId, 'last_repost', time());
                } else {
                    Kush::$bot->deleteMessage($chatId, $message->getMessageId());
                }
            }

            if (Kush::$filter_chats && in_array($forwardId, Kush::$chats)) {
                # ban all reposts from channels
                Kush::$bot->deleteMessage($chatId, $message->getMessageId());
            }
        }
    }

    /**
     * Filter media from users
     *
     * @param $userId
     * @param $chatId
     * @param Message $message
     */
    protected static function filterMedia($userId, $chatId, Message $message)
    {
        $photo = $message->getPhoto();
        $video = $message->getVideo();

        if ($photo || $video) {
            if (Kush::$cooldown_media && in_array($userId, Kush::$users)) {
                # ban all media posts until cooldown
                $last_media = (int) Data::get($userId, 'last_media') ?: 0;

                if ($last_media + Kush::$cooldown_media < time()) {
                    Data::set($userId, 'last_media', time());
                } else {
                    Kush::$bot->deleteMessage($chatId, $message->getMessageId());
                }
            }
        }
    }


    protected static function log($chatId, $data)
    {
        if (in_array($chatId, self::$logs)) {
            Data::log($data);
        }
    }
}

Kush::init();
Kush::run();