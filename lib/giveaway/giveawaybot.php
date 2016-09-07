<?php

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
define('ENTERING_PRIZE_NAME', 16);
define('ENTERING_PRIZE_TYPE', 17);
define('ENTERING_PRIZE_VALUE', 18);
define('ENTERING_PRIZE_CURRENCY', 19);
define('PRIZE_SUMMARY', 20);
define('PRIZE_DETAIL', 21);
define('PRIZE_DETAIL_EDIT_NAME', 22);
define('PRIZE_DETAIL_EDIT_TYPE', 23);
define('PRIZE_DETAIL_EDIT_VALUE', 24);
define('PRIZE_DETAIL_EDIT_CURRENCY', 25);
define('OBJECT_PER_LIST', 3);
define('CURRENCY', '€$');


class GiveAwayBot extends \WiseDragonStd\HadesWrapper\Bot {

    public $listLength = 0;
    public $currentPage = 0;
    public $userGiveaway = array();
    public $userGiveawaySize = 0;
    public $userGiveawayFull = false;

    public function processMessage() {
        $message = &$this->update['message'];
        $this->chat_id = $this->update["message"]["chat"]["id"];
        $this->totalLength = 0;
        $this->currentPage = 1;
        $this->rest = 0;
        $this->counter = 0;

        if (isset($message['text'])) {
            // Text sent by the user
            $text = &$message['text'];
            $message_id = &$message['message_id'];
            $this->getLanguage();
            $this->getStatus();

            if (strpos($text, '/start') === 0) {
                if(!$this->database->exist("User", ["chat_id" => $message["chat"]["id"]])) {
                    $sth = $this->pdo->prepare('SELECT COUNT(chat_id) FROM "User" WHERE chat_id = :chat_id');
                    $sth->bindParam(':chat_id', $this->chat_id);
                    $sth->execute();
                    $user_registred = $sth->fetchColumn();
                    if($user_registred == false) {
                        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization['languages']['en'], 'callback_data' => 'cls_en']);
                        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization['languages']['it'], 'callback_data' => 'cls_it']);
                        $this->sendMessageKeyboard($this->localization['en']['Welcome_Msg'], $this->inline_keyboard->getKeyboard());
                    }
                } else {
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Register_Button'], 'callback_data' => 'register']);
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Show_Button'], 'callback_data' => 'show']);
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Options_Button'], 'callback_data' => 'options']);
                    $this->sendMessageKeyboard($this->localization[$this->language]['Menu_Msg'], $this->inline_keyboard->getKeyboard());
                    $this->redis->set($this->chat_id . ':status', MENU);
                }

                $response = $this->getUserRecords();

                if ($response != false) {
                    $this->userGiveaway = $response[0];
                    $this->userGiveawaySize = $response[1];
                    $this->userGiveawayFull = true;
                }
            } elseif (strpos($text, '/register') === 0) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Standard_Button'], 'callback_data' => 'standard'], ['text' => &$this->localization[$this->language]['Cumulative_Button'], 'callback_data' => 'cumulative']);
                $this->sendMessageKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard());
                $this->redis->set($this->chat_id . ':status', REGISTER);
            } elseif (preg_match('/^\/stats$/', $text, $matches)) {
                $this->statsAction();
                print_r($this->userGiveaway);
            } elseif (preg_match('/^\/show \#(.*)$/', $text, $matches)) {
                $this->showGiveaway('#'.$matches[1]);
            } elseif (preg_match('/^\/show$/', $text, $matches)) {
                $this->sendMessage($this->localization[$this->language]['MissingHashtagWarn_Msg'].NEWLINE.'<code>/show #giveaway</code>');
            } else {
                switch($this->getStatus()) {
                    case ENTERING_TITLE:
                        if (strlen($text) > 5) {
                            $this->editMessageText($this->localization[$this->language]['Title_Msg'] . $text, $this->redis->get($this->chat_id . 'message_id'));
                            $new_message = &$this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            $this->redis->set($this->chat_id . ':status', ENTERING_HASHTAG);
                            $this->redis->hSet($this->chat_id . ':create', 'title', $text);
                        } else {
                            $new_message = &$this->sendMessageKeyboard($this->localization[$this->language]['TitleLenght_Msg'], $this->inline_keyboard->getBackKeyboard());
                            $this->redis->set($this->chat_id . 'message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_HASHTAG:
                        $hashtag = &$this->getHashtag($text);
                        $hashtag = $hashtag[0];
                        if (isset($hashtag)) {
                            // If hashtag doesn't exists already in db
                            $sth = $this->pdo->prepare('SELECT COUNT(hashtag) FROM Giveaway WHERE LOWER(hashtag) LOWER(:hashtag)');
                            $sth->bindParam(':hashtag', $hashtag);
                            $sth->execute();
                            $duplicated_hashtag = $sth->fetchColumn();
                            if ($duplicated_hashtag == false) {
                                $this->editMessageText($this->localization[$this->language]['Hashatag_Msg'], $this->redis->get($this->chat_id . 'message_id'));
                                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringMaxPartecipant_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', ENTERING_MAX);
                                $this->redis->set($this->chat_id . ':create', 'hashtag', $hashtag);
                            } else {
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['DuplicatedHashtag'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            }
                        } else {
                            $this->sendReplyMessageKeyboard($this->localization[$this->language]['ValidHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                        }
                        break;
                    case ENTERING_MAX:
                        if (is_integer($text) && $text < PHP_INT_MAX) {
                            $this->editMessageText($this->localization[$this->language]['MaxPartecipants_Msg'] . $text, $this->redis->get($this->chat_id . 'message_id'));
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringDescription_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DESCRIPTION);
                            $this->redis->set($this->chat_id . 'message_id', $new_message['message_id']);
                        } else {
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['InfiniteButton'], 'callback_data' => 'infinite']);
                            $this->sendReplyMessageKeyboard($this->localization[$this->language]['MaxPartecipantNotValid_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                        }
                        break;
                    case ENTERING_DESC:
                        $this->editMessageText($this->localization[$this->language]['Description_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringDate_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        $this->redis->hSet($this->chat_id . ':create', 'description', substr($text, 0, 49));
                        $this->redis->set($this->chat_id . ':status', ENTERING_DATE);
                        break;
                    case ENTERING_DATE:
                        if (is_integer($text) && $text > 2 && $text < 41) {
                            $date = time() + strtotime($text . ' days');
                            $this->editMessageText($this->localization[$this->language]['Date_Msg'] . date('Y-d-m', $date), $this->redis->get($this->chat_id . ':message_id'));
                            $new_message = $this->sendMessageKeyboard($this->localization[$this->language]['EnteringPrizeName_Msg'], $this->inline_keyboard->getBackKeyboard());
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            $this->redis->hSet($this->chat_id . ':create', 'date', $date);
                            $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_NAME);
                            if(!$this->redis->hExists($this->chat_id . ':create', 'prizes')) {
                                $this->redis->hSet($this->chat_id . ':create', 'prizes', 0);
                            } else {
                                $this->redis->hIncrBy($this->chat_id . ':create', 'prizes', 1);
                            }
                        } else {
                            $this->sendReplyMessageKeyboard($this->localization[$this->language]['DateNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_PRIZE_NAME:
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->redis->hSet($chat_id . ':prize:' . $prizes_count, 'name', $text);
                        $this->editMessageText($this->localization[$this->language]['PrizeName_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $new_message = $this->sendReplyMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeValue_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_VALUE);
                        break;
                    case ENTERING_PRIZE_VALUE:
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $text = str_replace(',', '.', $text);
                        $money = preg_split('/(?<=\d)(?=[' . CURRENCY . '])/', $text);
                        if ((is_float($money[0]) || is_integer($money[0])) || (is_float($money[1] || is_integer($money[1])))) {
                            if (is_float($money[0]) || is_integer($money[0])) {
                                $i = 0;
                                $j = 1;
                            } else {
                                $i = 1;
                                $j = 0;
                            }
                            $value = $money[$i];
                            $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'value', $value);
                            $currency = preg_match('/[' . CURRENCY . '=*]+/', $money[$j]);
                            $currency = $currecy[$j][0];
                            if (isset($currency)) {
                                $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'currency', $currency);
                                $this->editMessageText($this->localization[$this->language]['Value_Msg'] . $currency . $value, $this->redis->get($this->chat_id . ':message_id'));
                                $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringPrizeType_Msg'], $this->getTypeKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                                $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_TYPE);
                            } else {
                                $this->editMessageText($this->localization[$this->langauge]['ValueNoCurrency_Msg'] . '?' . $value, $this->redis->get($this->chat_id . ':message_id'));
                                $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->langauge]['EnteringPrizeCurrency_Msg'], $this->getCurrencyKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                                $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_CURRENCY);
                            }
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['ValueNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case PRIZE_DETAIL_EDIT_NAME:
                        $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'name', $text);
                        $this->editMessageText($this->localization[$this->language]['NewPrizeName_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $container = [];
                        $this->getPrizeInfo($container);
                        $this->sendReplyMessageKeyboard($container['string'], $this->getPrizeEditKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                        break;
                    case PRIZE_DETAIL_EDIT_VALUE:
                        $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                        $text = str_replace(',', '.', $text);
                        $money = preg_split('/(?<=\d)(?=[' . CURRENCY . '])/', $text);
                        if ((is_float($money[0]) || is_integer($money[0])) || (is_float($money[1] || is_integer($money[1])))) {
                            if (is_float($money[0]) || is_integer($money[0])) {
                                $i = 0;
                                $j = 1;
                            } else {
                                $i = 1;
                                $j = 0;
                            }
                            $value = $money[$i];
                            $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'value', $value);
                            $currency = preg_match('/[' . CURRENCY . '=*]+/', $money[$j]);
                            $currency = $currecy[$j][0];
                            if (isset($currency)) {
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'currency', $currency);
                                $this->editMessageText($this->localization[$this->language]['NewValue_Msg'] . $currency . $value, $this->redis->get($this->chat_id . ':message_id'));
                                $container = [];
                                $this->getPrizeInfo($container);
                                $this->sendReplyMessageKeyboard($container['string'], $this->getTypeKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                            } else {
                                $this->editMessageText($this->localization[$this->langauge]['NewValueNoCurrency_Msg'] . '?' . $value, $this->redis->get($this->chat_id . ':message_id'));
                                $this->sendReplyMessageKeyboard($this->localization[$this->langauge]['EditPrizeCurrency_Msg'], $this->getCurrencyKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_CURRENCY);
                            }
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['NewValueNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($chat_id . ':message_id', $new_message['message_id']);
                        }
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
         $this->getLanguage();
         $this->getStatus();
         if (isset($data) && isset($this->chat_id)) {
             switch($data) {
                case 'null':
                    break;
                case 'hide_join_button':
                    $this->editMessageReplyMarkup($message_id, []);
                    $this->answerCallbackQuery($this->localization[$this->language]['CancelSuccess_Msg']);
                    break;
                case 'register':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Standard_Button'], 'callback_data' => 'standard'], ['text' => $this->localization[$this->language]['Cumulative_Button'], 'callback_data' => 'cumulative']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', SELECTING_TYPE);
                    echo "aihgfai";
                    break;
                case 'standard':
                    // No break
                case 'cumulative':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringTitle_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', ENTERING_TITLE);
                    $this->redis->hSet($this->chat_id .':create', 'type', $data);
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
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringHashtag_Msg'], $this->inline_keyboard->getBackSkipButton(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_HASHTAG);
                            break;
                        case ENTERING_DESC:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringMaxPartecipants_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_MAX);
                            break;
                        case ENTERING_DATE:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringDesc_Msg'], $this->inline_keyboard->getBackSkipButton(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DESC);
                            break;
                        case ENTERING_PRIZE_NAME:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringDate_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DATE);
                    }
                    break;
                case 'confirm':
                    $giveaway = $this->redis->hGetAll($this->chat_id . ':create');
                    $sth = $this->pdo->prepare('INSERT INTO Giveaway (name, hashtag, description, max_partecipants, owner_id, created, last) VALUES (:name, :hashtag, :description, :max_partecipants, :owner_id, :created, :date)');
                    $sth->bindParam(':name',  substr($giveaway['name'], 0, 31));
                    $sth->bindParam(':hashtag', substr($giveaway['hashtag'], 0, 31));
                    $sth->bindParam(':description', substr($giveaway['description'], 0, 49));
                    $sth->bindParam(':max_partecipants', $giveaway['max_partecipants']);
                    $sth->bindParam(':owner_id', $this->chat_id);
                    $sth->bindParam(':created', time());
                    $sth->bindParam(':date', $giveaway['date']);
                    $sth->execute();
                    $sth = null;
                    $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes_count');
                    $sth = $this->pdo->prepare('INSERT INTO Prize (name, value, currency, giveaway, type) VALUES (:name, :value, :currency, :giveaway, :type)');
                    for ($i = 0; $i < $prizes_count; $i++) {
                        $prize = $this->redis->hGetAll($this->chat_id . ':prize:' . $i);
                        $sth->bindParam(':name', substr($prize['name'], 0, 31));
                        $sth->bindParam(':value', $prize['value']);
                        $sth->bindParam(':currency', substr($prize['currency'], 0, 1));
                        $sth->bindParam(':giveaway', $prize['giveaway']);
                        $sth->bindParam(':type', $prize['type']);
                        $sth->execute();
                    }
                    $sth = null;
                default:
                    $info = explode('_', $data);
                    if (strpos('currency', $info[0])) {
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'currency', $info[1]);
                        $this->editMessageTextKeyboard($this->localization[$this->langauge]['EnteringPrizeValue_Msg'], $this->getTypeKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_TYPE);
                    } elseif (strpos('type', $info[0])) {
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'type', $info[1]);
                        $container = $this->getPrizesBrowse();
                        $this->editMessageTextKeyboard($container['string'], $this->inline_keyboard->getListKeyboard($container['index'], $container['list'], true, false, false, $container['extra_names'], $container['extra_callback'], ['text' => $this->localization[$this->language]['Confirm_Button'], 'callback_data' => 'confirm'], ['text' => $this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']), $message_id);
                        $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                    } elseif (strpos('prize', $info[0])) {
                        $this->redis->hSet($chat_id . ':create', 'prizes_selected', $info[1]);
                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                        $container = [];
                        $this->getPrizeInfo($container);
                        $this->editMessageTextKeyboard($container['string'], $this->getPrizeEditList(), $message_id);
                    } elseif (strpos('edit', $info[0])) {
                        switch ($info[1]) {
                            case 'prize':
                                switch ($info[3]) {
                                    case 'name':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_NAME);
                                        $this->editMessageText($this->chat_id . ':message_id', $message_id);
                                        $this->editMessageTextKeyboard($this->localization[$this->langauge]['EditPrizeName_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                                        break;
                                    case 'type':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_TYPE);
                                        $this->editMessageTextKeyboard($this->localization[$this->langauge]['EditPrizeType_Msg'], $this->getTypeKeyboard(), $message_id);
                                        break;
                                    case 'value':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_VALUE);
                                        $this->editMessageTextKeyboard($this->localization[$this->langauge]['EditPrizeValue_Msg'], $this->getBackKeyboard(), $message_id);
                                        $this->redis->set($this->chat_id . ':message_id', $message_id);
                                        break;
                                    case 'currency':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_CURRENCY);
                                        $this->editMessageTextKeyboard($this->localization[$this->language]['EditPrizeCurrency_Msg'], $this->getCurrencyKeyboard(), $message_id);
                                        break;
                                }
                                break;
                        }
                    } elseif (strpos('new', $info[0])) {
                        switch ($info[1]) {
                            case 'currency':
                                $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'currency', $info[1]);
                                $container = [];
                                $this->getPrizeInfo($container);
                                $this->editMessageTextKeyboard($container['string'], $this->getPrizeEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                                break;
                            case 'type':
                                $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'type', $info[1]);
                                $container = [];
                                $this->getPrizeInfo($container);
                                $this->editMessageTextKeyboard($container['string'], $this->getPrizeEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                                break;
                         }
                    } elseif (strpos($data, 'show_') === 0) {
                        $data = explode('_', $data);
                        $this->currentPage = $data[2];

                        $this->showGiveaway($data[1], true);
                    } elseif (strpos($data, 'list/') === 0) {
                        $stats = $this->getUserGiveaway();
                        $page = intval(explode('/', $data)[1]);
                        $limit = OBJECT_PER_LIST * $page;
                        $response = array();
                        $details = array();

                        $this->counter = $stats[1] - ($stats[1] % OBJECT_PER_LIST);
                        $this->rest = $stats[1] % OBJECT_PER_LIST;
                        $this->records = $stats[0];

                        $this->totalLength = $this->counter / OBJECT_PER_LIST;

                        for($i = $limit - OBJECT_PER_LIST; $i < $limit; $i++) {
                            array_push($response, $this->records[$i]);
                            $hashtag = explode("\n", $this->records[$i])[1];

                            array_push($details, [
                                'text' => $hashtag,
                                'callback_data' => 'show_'.$hashtag.'_'.$page
                            ]);
                        }

                        $this->editMessageText(join("\n=======================\n\n", $response), $message_id,
                                               $this->inline_keyboard->getListKeyboard($page, $this->totalLength, false, false, false, $details));

                    } elseif (strpos($data, 'join_') === 0) {
                        $this->editMessageReplyMarkup($message_id, []);
                        $giveaway_id = explode('_', $data)[1];

                        // Check for joined' number
                        $this->joined = 0;
                        $this->max_joined = 0;
                        $this->database->from("joined")->where('giveaway_id='.$giveaway_id)->select(["count(*)"], function($row){ $this->joined = $row['count']; });

                        $this->database->from("giveaway")->where('id='.$giveaway_id)->select(["max_partecipants"],
                            function($row){ $this->max_joined = $row['max_partecipants']; });

                        if ($this->joined == $this->max_joined) {
                             $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
                             $this->editMessageReplyMarkup($message_id, $this->inline_keyboard->getKeyboard());
                             $this->answerCallbackQuery($this->localization[$this->language]['Maxjoined_Msg'], true);
                        } else {
                             $this->database->into('joined')->insert([
                                 'chat_id' => $this->chat_id,
                                 'giveaway_id' => $giveaway_id
                             ]);

                             $this->editMessageReplyMarkup($message_id, []);
                             $this->answerCallbackQuery($this->localization[$this->language]['JoinedSuccess_Msg']);

                             # Update user's giveaway stats
                             $response = $this->getUserRecords();

                             if ($response != false) {
                                 $this->userGiveaway = $response[0];
                                 $this->userGiveawaySize = $response[1];
                                 $this->userGiveawayFull = true;
                             }
                        }
                    } elseif (strpos('cls', $info[0]) !== false) {
                        $sth = $this->pdo->prepare('INSERT INTO "User" (chat_id, language) VALUES (:chat_id, :language)');
                        $sth->bindParam(':chat_id', $this->chat_id);
                        $sth->bindParam(':language', $info[1]);
                        $sth->execute();
                        $sth = null;
                        $this->language = $info[1];
                        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Register_Button'], 'callback_data' => 'register']);
                        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Show_Button'], 'callback_data' => 'show']);
                        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Options_Button'], 'callback_data' => 'options']);
                        $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                        $this->answerCallbackQueryRef($this->localization[$this->language]['UserRegistred_AnswerCallbackQuery']);
                        $this->redis->set($this->chat_id . ':status', MENU);
                    } elseif (strpos('cl', $info[0]) !== false) {
                        $this->setLanguage($info[1]);
                        $this->editMesssageTextKeyboard($this->localization[$this->language]['Options_Msg'], $this->getOptionsKeyboard(), $message_id);
                        $this->anwerCallbackQueryRef($this->localization[$this->language]['LanguageChanged_AnswerCallback']);
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

    public function &getCurrencyKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => '€', 'callback_data' => 'currency_€'], ['text' => '$', 'callback_data' => 'currency_$']);
        return $this->inline_keyboard->getKeyboard();
    }

    public function &getPrizeTypeKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['Type0_Button'], 'callback_data' => 'type_0']);
        return $this->inline_keyboard->getKeyboard();
    }

    public function &getPrizeEditKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['EditPrizeName_Button'], 'callback_data' => 'edit_prize_name'], ['text' => $this->localization[$this->language]['EditPrizeType_Button'], 'callback_data' => 'edit_prize_type']);
        $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['EditPrizeValue_Button'], 'callback_data' => 'edit_prize_value'], ['text' => $this->localization[$this->language]['EditPrizeCurrenty_Button'], 'callback_data' => 'edit_prize_currency']);
        $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->langauge]['DeletePrize_Button'], 'callback_data' => 'delete_prize']);
        $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['Prizes_Button'], 'callback_data' => 'prizes']);
        return $this->inline_keyboard->getKeyboard();
    }

    public function &getPrizesBrowse() {
        $container = [];
        $index = $this->redis->hGet($this->chat_id . ':create', 'prizes_index') + 1;
        $i = ($index - 1) * SPACEPERVIEW + 1;
        $i_last = $i + 2;
        while ($i <= $i_last) {
            $this->redis->hSet($this->chat_id . ':create', 'prizes_index', $i);
            $this->getPrizeInfo($container);
        }
        $container['index'] = $index;
        $container['list'] = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;
        return $container;
    }

    public function getPrizeInfo(&$container) {
        $i = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
        $prize = $redis->hGetAll($chat_id . ':prize:' . $i);
        if (!empty($container)) {
            array_push($container['extra_names'], $prize['name']);
            array_push($container['extra_callback'], 'prize_' . $i);
        }
        $container['string'] += $this->localization[$this->language]['PrizeName_Msg'] . $prize['name'] . NEWLINE . $this->localization[$this->language]['PrizeType_Msg'] . $this->localization[$this->language]['Type' . $prize['type'] . '_Msg'] . NEWLINE . $this->localization[$this->language]['Value_Msg'] . $prize['currency'] . $prize['value'] . NEWLINE;
    }

    private function getUserRecords() {
        $this->response = " ";
        $this->records = array();
        $this->counter = 0;
        $this->found = false;

        // Take all giveaways

        if ($this->update["callback_query"] === null) {
            $this->message = $this->update["message"];
        } else {
            $this->message = $this->update["callback_query"];
        }

        $this->database->from("participants")->where("chat_id=".$this->message["from"]["id"])->select(["*"], function($row){
          $this->found = true;

          $this->database->from("Giveaway")->where("id=".$row["giveaway_id"])->select(["*"], function($row){
            $partial .= "<b>".$row['name']."</b>".NEWLINE.$row['hashtag'].NEWLINE.NEWLINE.$row['desc'].NEWLINE.NEWLINE;

            if ($row['owner_id'] == $this->message["from"]["id"])
            {
                $partial .= $this->localization[$this->language]['Owned_Msg']."  |  ";
            } else {
                $partial .= $this->localization[$this->language]['Joined_Msg']."  |  ";
            }

            // Show giveaway's status
            if (date("Y-m-d") > $row['end'])
            {
                $partial .= $this->localization[$this->language]['Closed_Msg'].NEWLINE;
            } else if (date("Y-m-d") == $row['end']) {
                $partial .= $this->localization[$this->language]['LastDay_Msg'].NEWLINE;
            } else {
                $left = (strtotime(date("Y-m-d")) - strtotime($row['end'])) / 3600 / 24;
                $partial .= $left.' '.$this->localization[$this->language]['Days_Msg'].NEWLINE;
            }

            array_push($this->records, $partial);
            $this->counter++;
          });
        });

        if ($this->found) {
            return [$this->records, $this->counter];
        }

        return false;
    }

    private function getUserGiveaway() {
        if ($this->userGiveawayFull == false) {
            $this->sendMessage($this->localization[$this->language]['StatsEmpty_Msg']);
            return false;
        } else {
            return array($this->userGiveaway, $this->userGiveawaySize);
        }

        return true;
    }

    // Respond to `/stats` command which returns information about
    // user's giveaways (both - owned and joined).
    private function statsAction() {
        $data = $this->getUserGiveaway();

        if ($data == false) {
            return;
        }

        if ($this->userGiveawaySize <= OBJECT_PER_LIST) {
            $this->sendMessage(join("\n=======================\n\n", $this->userGiveaway));
        } else {
            $this->listLength = intval($this->userGiveawaySize / OBJECT_PER_LIST); 
            $this->rest = $this->userGiveawaySize % OBJECT_PER_LIST;

            $this->getStatsList($this->listLength);
        }
    }

    private function getStatsList($listLength) {
        $response = array();
        $details = array();

        if ($listLength == 1) {
            $this->sendMessage(join("\n=======================\n\n", $this->records));
            return;
        }

        for($i = 0; $i < OBJECT_PER_LIST; $i++) {
            array_push($response, $this->records[$i]);
            $hashtag = explode("\n", $this->records[$i])[1];

            array_push($details, [
                'text' => $hashtag,
                'callback_data' => 'show_'.$hashtag.'_1'
            ]);
        }

        $this->sendMessage(join("\n=======================\n\n", $response), $this->inline_keyboard->getListKeyboard(1, $listLength, false, false, false, $details));
    }

    // Respond to `/show <hashtag>` command which returns information about
    // a specific giveaway and permits user to join it if possible.
    private function showGiveaway($hashtag, $callback_query_origin = false) {
        $this->callback_query_origin = $callback_query_origin;
        $this->response = ' ';

        $this->database->from('Giveaway')->where("hashtag='".$hashtag."'")->select(["*"], function($row){
          $this->response = '<b>'.$row['name'].'</b>'.NEWLINE.$row['hashtag'].NEWLINE.NEWLINE;
          $this->response .= $row['description'].NEWLINE.NEWLINE;
          $this->already_joined = false;

          if ($this->update["callback_query"] === null) {
              $this->chat_id = $this->update["message"]["from"]["id"];
          } else {
              $this->chat_id = $this->update["callback_query"]["from"]["id"];
          }
          
          if ($this->callback_query_origin == false) {
              // Check if the user is already a participant
              $this->database->from("participants")->where("chat_id=".$this->chat_id." and giveaway_id=".$row['id'])
                   ->select(["*"], function($row) { $this->already_joined = true; });

              if ($this->already_joined == false) {
                  $this->inline_keyboard->addLevelButtons([
                      'text' => $this->localization[$this->language]['Join_Button'],
                      'callback_data' => 'join_'.$row['id']
                  ], [
                      'text' => $this->localization[$this->language]['Cancel_Button'],
                      'callback_data' => 'hide_join_button'
                  ]);
              } else {
                  $this->response .= $this->localization[$this->language]['Joined_Msg'].'  |  ';
              }
          } else {
              $this->response .= $this->localization[$this->language]['Joined_Msg'].'  |  ';
          }

          // Show giveaway's status
          if (date("Y-m-d") > $row['end'])
          {
              $this->response .= $this->localization[$this->language]['Closed_Msg'].NEWLINE;
              $this->inline_keyboard->getKeyboard();
              $this->response = $this->localization[$this->language]['ClosedGiveaway_Msg'];
          } else if (date("Y-m-d") == $row['end']) {
              $this->response .= $this->localization[$this->language]['LastDay_Msg'].NEWLINE;
          } else {
              $left = (strtotime(date("Y-m-d")) - strtotime($row['end'])) / 3600 / 24;
              $this->response .= $left.' '.$this->localization[$this->language]['Days_Msg'].NEWLINE;
          }
        });

        if ($callback_query_origin == false) {
            if ($this->response == ' ') {
                $this->sendMessage($this->localization[$this->language]['NoGiveawayWarn_Msg']);
            } else {
                $this->sendMessage($this->response, $this->inline_keyboard->getKeyboard());
            }
        } else {
            $this->editMessageText($this->response, $this->update['callback_query']['message']['message_id'],
                $this->inline_keyboard->getListKeyboard($this->currentPage, intval($this->totalLength), false, false, false,
                    [['text' => $this->localization[$this->language]['Back_Button'], 'callback_data' => 'list/'.$this->currentPage],
                     ['text' => 'Browse Prize', 'callback_data' => 'null']]));
        }
    }
}
