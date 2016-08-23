<?php

require './vendor/autoload.php';

define('NEWLINE','
');

define('NO_STATUS', -1);
define('MENU', 0);
define('REGISTER', 1);
define('SELECTING_TYPE', 2);
define('ENTERING_TITLE', 3);
define('ENTERING_HASHTAG', 4);
define('ENTERING_MAX', 5);
define('ENTERING_SHARE', 6);
define('ENTERING_DESC', 7);
define('ENTERING_DATE', 8);
define('SHOW_GV', 9);
define('JOIN_HASHTAG_PROMPT', 10);
define('JOINED', 11);
define('GV_NOT_VALID', 12);
define('SHOW_ALL', 13);
define('OPTIONS', 15);


class GiveAwayBot extends WiseDragonStd\HadesWrapper\Bot {

    public function processMessage() {
        $message = &$this->update['message'];
        if (isset($message['text'])) {
            // Text sent by the user
            $text = &$message['text'];
            $message_id = &$message['message_id'];
            $this->getLanguage();
            if (strpos($text, '/start') === 0) {
                if(!$this->database->checkUserExist()) {
                    sendMessageKeyboard($this->localization[$this->langauge]['Welcome_Msg'], $this->inline_keyboard->getChooseLanguageKeyboard());
                } else {
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Register_Button'], 'callback_data' => 'register']);
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Show_Button'], 'callback_data' => 'show']);
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Options_Button'], 'callback_data' => 'options']);
                    $this->sendMessageKeyboard($this->localization[$this->language]['Menu_Msg'], $this->inline_keyboard->getKeyboard());
                    $this->redis->set($this->chat_id . ':status', MENU);
                }
            } elseif (strpos($text, '/register') === 0) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Standard_Button'], 'callback_data' => 'standard'], ['text' => &$this->localization[$this->language]['Cumulative_Button'], 'callback_data' => 'cumulative']);
                $this->sendMessageKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard());
                $this->redis->set($this->chat_id . ':status', REGISTER);
            } elseif (strpos($text, '/join') === 0) {

            } elseif (strpos($text, '/stats') === 0) {

            } else {
                switch($this->getStatus()) {
                    case 'ENTERING_TITLE':
                        if (strlen($text) > 5) {
                            $this->editMessageText($this->localization[$this->language]['Title_Msg'] . $text, $this->redis->get($this->chat_id . 'message_id'));
                            $new_message = &$this->sendReplyMessageKeyboard($this->localization[$this->language]['EnterHashtag_Msg'], $this->inline_keyboard->getBackSkipButton(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            $this->redis->set($this->chat_id . ':status', ENTERING_HASHTAG);
                            $this->redis->hset($this->chat_id . ':create', 'title', $text);
                        } else {
                            $new_message = &$this->sendMessageKeyboard($this->localization[$this->language]['TitleLenght_Msg'], $this->inline_keyboard->getBackButton());
                            $this->redis->set($this->chat_id . 'message_id', $new_message['message_id']);
                        }
                        break;
                    case 'ENTERING_HASHTAG':
                        $hashtag = &$this->getHashtag($text);
                        $hashtag = $hashtag[0];
                        if (isset($hashtag)) {
                            // If hashtag doesn't exists already in db
                            if($hashtag) {
                                $this->editMessageText($this->localization[$this->language]['Hashatag_Msg'], $this->redis->get($this->chat_id . 'message_id'));
                                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringMaxPartecipant_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', ENTERING_MAX);
                                $this->redis->set($this->chat_id . ':create', 'hashtag', $hashtag);
                            }
                        } else {
                            $this->sendReplyMessageKeyboard($this->localization[$this->language]['ValidHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                        }
                        break;
                    case 'ENTERING_MAX':
                        if (is_integer($text) && $text < PHP_INT_MAX) {
                            $this->editMessageText($this->localization[$this->language]['MaxPartecipants_Msg'] . $text, $this->redis->get($this->chat_id . 'message_id'));
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnterDescription_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DESCRIPTION);
                            $this->redis->set($this->chat_id . 'message_id', $new_message['message_id']);
                        } else {
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['InfiniteButton'], 'callback_data' => 'infinite']);
                            $this->sendReplyMessageKeyboard($this->localization[$this->language]['MaxPartecipantNotValid'], $this->inline_keyboard->getKeyobard(), $message_id);
                        }
                        break;
                    case 'ENTERING_DESC':
                        $message_id = $this->redis->get($this->chat_id . ':message_id');
                        $this->editMessageText($this->localization[$this->language]['Description_Msg'] . $text, $message_id);
                        $new_message = $this->sendMessageKeyboard($this->localization[$this->language]['EnterExpirations_Msg'], $this->inline_keyboard->getBackButton());
                        $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        $this->redis->hset($this->chat_id . ':create', 'desc', substr($text, 0, 49));
                        $this->redis->set($this->chat_id . ':status', ENTERING_DATE);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function processCallbackQuery() {
         $callback_query = &$this->update['callback_query'];
         $this->chat_id = &$callback_query['from']['id'];
         $message_id = $callback_query['message']['message_id'] ?? null;
         $inline_message_id = $callback_query['inline_message_id'] ?? null;
         $data = $callback_query['data'];
         if (isset($data) && isset($this->chat_id)) {
             switch($data) {
                case 'register':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Standard_Button'], 'callback_data' => 'standard'], ['text' => $this->localization[$this->language]['Cumulative_Button'], 'callback_data' => 'cumulative']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', SELECTING_TYPE);
                    break;
                case 'standard':
                    // No break
                case 'cumulative':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['EnterTitle_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', ENTERING_TITLE);
                    $this->redis->hset($this->chat_id .':create', 'type', $data);
                    break;
                case 'back':
                    switch ($this->getStatus()) {
                        case OPTIONS:
                        case SELECTING_TYPE:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Register_Button'], 'callback_data' => 'register']);
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Show_Button'], 'callback_data' => 'show']);
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Options_Button'], 'callback_data' => 'options']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . 'status', MENU);
                            break;
                        case ENTERING_TITLE:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Standard_Button'], 'callback_data' => 'standard'], ['text' => $this->localization[$this->language]['Cumulative_Button'], 'callback_data' => 'cumulative']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', SELECTING_TYPE);
                            break;
                        case ENTERING_HASHTAG:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringTitle_Msg'], $this->inline_keyboard->getBackButton(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_TITLE);
                            break;
                        case ENTERING_MAX:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['Entering_hashtag'], $this->inline_keyboard->getBackSkipButton(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_HASHTAG);
                            break;
                        case ENTERING_DESC:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringMaxPartecipants_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id
                            break;
                        case ENTERING_DATE:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringDesc_Msg'], $this->inline_keyboard->getBackSkipButton(), $message_id);
                            break;
                    }
                    break;
             }
         }
    }

    public function &getHashtags(&$string) {
        $hashtags= FALSE;
        preg_match_all("/(#\w+)/u", $string, $matches);
        if ($matches) {
            $hashtagsArray = array_count_values($matches[0]);
            $hashtags = array_keys($hashtagsArray);
        }
        return $hashtags;
    }
}
