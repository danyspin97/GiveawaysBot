<?php

define('NEWLINE','
');

define('NO_STATUS', -1);
define('MENU', 0);
define('JOINING', 1);
define('SELECTING_TYPE', 2);
define('ENTERING_TITLE', 3);
define('ENTERING_HASHTAG', 4);
define('ENTERING_MAX', 5);
define('ENTERING_SHARE', 6);
define('ENTERING_DESCRIPTION', 7);
define('ENTERING_DATE', 8);
define('GIVEAWAY_SUMMARY', 9);
define('GIVEAWAY_EDIT_TITLE', 10);
define('GIVEAWAY_EDIT_HASHTAG', 11);
define('GIVEAWAY_EDIT_MAX', 12);
define('GIVEAWAY_EDIT_DESCRIPTION', 13);
define('GIVEAWAY_EDIT_DATE', 14);
define('ENTERING_PRIZE_NAME', 15);
define('ENTERING_PRIZE_TYPE', 16);
define('ENTERING_PRIZE_VALUE', 17);
define('ENTERING_PRIZE_CURRENCY', 18);
define('ENTERING_PRIZE_KEY', 19);
define('PRIZE_SUMMARY', 20);
define('PRIZE_DETAIL', 21);
define('PRIZE_DETAIL_EDIT_NAME', 22);
define('PRIZE_DETAIL_EDIT_TYPE', 23);
define('PRIZE_DETAIL_EDIT_VALUE', 24);
define('PRIZE_DETAIL_EDIT_CURRENCY', 25);
define('SHOW_GV', 26);
define('JOIN_HASHTAG_PROMPT', 27);
define('JOINED', 28);
define('GV_NOT_VALID', 29);
define('SHOW_ALL', 30);
define('LANGUAGE', 31);
define('GIVEAWAY_CANCEL_PROMPT', 32);
define('PRIZE_CANCEL_PROMPT', 33);
define('OBJECT_PER_LIST', 3);
define('CURRENCY', '€$');


class GiveAwayBot extends \WiseDragonStd\HadesWrapper\Bot {

    public $listLength = 1;
    public $currentPage = 1;
    private $userGiveaway = array();
    private $userGiveawaySize = 0;
    private $userGiveawayFull = false;


    public function processInlineQuery() {
        $inline_query = &$this->update['inline_query'];
        $this->chat_id = &$inline_query['from']['id'];
        $text = &$inline_query['query'];
        $this->getLanguage();

        if ($this->database->exist("User", ["chat_id" => $this->chat_id])) {
            $this->results = new \WiseDragonStd\HadesWrapper\InlineQueryResults();
            if (isset($text) && $text !== '') {
                $sth = $this->pdo->prepare('SELECT T.id, T.name, T.owner_id, T.description, T.hashtag FROM (
                                                SELECT DISTINCT giveaway.id, giveaway.name, giveaway.owner_id, giveaway.description, giveaway.type, giveaway.hashtag, giveaway.last
                                                   FROM giveaway INNER JOIN joined
                                                   ON giveaway.id = joined.giveaway_id AND joined.chat_id = :chat_id AND giveaway.type = \'cumulative\' OR giveaway.owner_id = :chat_id
                                            ) AS T WHERE T.name ~* :query OR T.hashtag ~* :query ORDER BY T.last LIMIT 50');
                $sth->bindParam(':query', $text);
            } else {
                // Show all giveaways that the user joined or created
                $sth = $this->pdo->prepare('SELECT DISTINCT giveaway.id, giveaway.name, giveaway.owner_id, giveaway.description, giveaway.type, giveaway.hashtag, giveaway.last
                                               FROM giveaway INNER JOIN joined
                                               ON giveaway.id = joined.giveaway_id AND joined.chat_id = :chat_id AND giveaway.type = \'cumulative\' OR giveaway.owner_id = :chat_id ORDER BY last LIMIT 50');
            }
            $sth->bindParam(':chat_id', $this->chat_id);
            $sth->execute();
            while($row = $sth->fetch()) {
                $message = $this->localization[$this->language]['JoinLabel_Msg'].' <b>'.$row['name'].'</b>'
                    . $this->localization[$this->language]['NowLabel_Msg'];
                $link = 'telegram.me/giveaways_bot?start='.base64_encode($row['owner_id']).'_'
                                                   .base64_encode($row['id']);
                $this->inline_keyboard->addLevelButtons([
                        'text' => $this->localization[$this->language]['Join_Button'],
                        'url' => $link
                ]);

                $description = $row['description'] === 'NULL' ? '' : $row['description'];
                $this->results->newArticleKeyboard($row['name'], $message, $description,
                                               $this->inline_keyboard->getNoJSONKeyboard());
                unset($description);
            }
            $this->answerInlineQuerySwitchPMRef($this->results->getResults(), $this->localization[$this->language]['SwitchPM_InlineQuery'], 'start');
            $sth = null;
        } else {
            $this->answerEmptyInlineQuerySwitchPM($this->localization[$this->language]['Register_InlineQuery']);
        }
    }

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

            if (strpos($text, '/start') !== false) {
                $parameter = explode(' ', $text)[1];

                if ($parameter == null) {
                    $this->startAction();
                }

                if ($parameter != null) {
                    $data = explode('_', $parameter);
                    $ref_id = base64_decode($data[0]);
                    $chat_id = $this->chat_id;
                    $giveaway_id = base64_decode($data[1]);

                    $this->inline_keyboard->addLevelButtons([
                        'text' => $this->localization[$this->language]['Menu_Button'],
                        'callback_data' => 'menu'
                    ]);

                    if ($this->update["message"]["chat"]["id"] == intval($ref_id)) {
                        $this->sendMessage($this->localization[$this->language]['Inception_Msg']);
                    } else {
                        if(!$this->database->exist("User", ["chat_id" => $this->chat_id])) {
                            $this->database->into('"User"')->insert([
                                    "chat_id" => $this->chat_id,
                                    "language" => 'en'
                            ]);
                        }
                        if($this->database->exist('joined', ["giveaway_id" => $giveaway_id, "chat_id" => $ref_id]) ||
                           $this->database->exist('giveaway', ["id" => $giveaway_id])) {
                            if(!$this->database->exist('joined', ["giveaway_id" => $giveaway_id,
                                                       "chat_id" => $this->update["message"]["chat"]["id"]])) {
                                $this->addByReferral($giveaway_id, $ref_id, $this->update["message"]["chat"]["id"]);
                            } else {
                                $this->sendMessage($this->localization[$this->language]['AlreadyIn_Msg'],
                                                   $this->inline_keyboard->getKeyboard());
                            }
                        } else {
                            $this->sendMessage($this->localization[$this->language]['UserError_Msg'],
                                               $this->inline_keyboard->getKeyboard());
                        }
                    }
                }

                $this->updateStats();
            } elseif (strpos($text, '/create') === 0) {
                if ($this->redis->exists($this->chat_id . ':create')) {
                    $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;
                    for ($i = 0; $i < $prizes_count; $i++) {
                        $this->redis->delete($this->chat_id . ':prize:' . $i);
                    }
                    $this->redis->delete($this->chat_id . ':create');
                }
                $this->inline_keyboard->addLevelButtons([
                    'text' => &$this->localization[$this->language]['standard_Button'],
                    'callback_data' => 'standard'],
                    ['text' => &$this->localization[$this->language]['cumulative_Button'],
                     'callback_data' => 'cumulative']);
                $this->inline_keyboard->addLevelButtons([
                    'text' => &$this->localization[$this->language]['Back_Button'],
                    'callback_data' => 'back']);
                $this->sendMessageKeyboard($this->localization[$this->language]['Register_Msg'],
                                           $this->inline_keyboard->getKeyboard());
                $this->redis->set($this->chat_id . ':status', SELECTING_TYPE);
            } elseif (preg_match('/^\/browse$/', $text, $matches)) {
                $this->getStatsList();
            } elseif (preg_match('/^\/join \#(.*)$/', $text, $matches)) {
                $this->showGiveaway('#'.$matches[1]);
            } elseif (preg_match('/^\/join$/', $text, $matches)) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                $this->sendMessage($this->localization[$this->language]['MissingHashtagWarn_Msg'].NEWLINE.'<code>/join #giveaway</code>');
            } elseif (strpos($text, '/help') !== false) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                $this->sendMessage($this->localization[$this->language]['Help_Msg']);
            } elseif (strpos($text, '/about') !== false) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Updates_Button'], 'url' => 'https://telegram.me/wisedragonstd'], ['text' => '😈 HadesWrapper', 'url' => 'https://gitlab.com/WiseDragonStd/HadesWrapper']);
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                $this->sendMessageKeyboard($this->localization[$this->language]['About_Msg'], $this->inline_keyboard->getKeyboard());
            } else {
                switch($this->getStatus()) {
                    case ENTERING_TITLE:
                        if (strlen($text) > 4) {
                            $this->editMessageText($this->localization[$this->language]['Title_Msg'] . '<i>' . $text . '</i>', $this->redis->get($this->chat_id . ':message_id'));
                            $new_message = &$this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            $this->redis->set($this->chat_id . ':status', ENTERING_HASHTAG);
                            $this->redis->hSet($this->chat_id . ':create', 'title', $text);
                        } else {
                            $new_message = &$this->sendMessageKeyboard($this->localization[$this->language]['TitleLenght_Msg'], $this->inline_keyboard->getBackKeyboard());
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_HASHTAG:
                        $hashtag = &$this->getHashtags($text);
                        $hashtag = $hashtag[0];
                        if (isset($hashtag)) {
                            // If hashtag doesn't exists already in db
                            $sth = $this->pdo->prepare('SELECT COUNT(hashtag) FROM Giveaway WHERE LOWER(hashtag) = LOWER(:hashtag)');
                            $sth->bindParam(':hashtag', $hashtag);
                            $sth->execute();
                            $duplicated_hashtag = $sth->fetchColumn();
                            $sth = null;
                            if ($duplicated_hashtag == false) {
                                $this->editMessageText($this->localization[$this->language]['Hashatag_Msg'] . $hashtag, $this->redis->get($this->chat_id . ':message_id'));
                                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                                $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringMaxparticipants_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                                $this->redis->set($this->chat_id . ':status', ENTERING_MAX);
                                $this->redis->hSet($this->chat_id . ':create', 'hashtag', $hashtag);
                            } else {
                                $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['DuplicatedHashtag'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            }
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['ValidHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_MAX:
                        $text = intval($text);
                        if (is_integer($text) && $text < PHP_INT_MAX && $text !== 0) {
                            $this->editMessageText($this->localization[$this->language]['Maxparticipants_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringDescription_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DESCRIPTION);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            $this->redis->hSet($this->chat_id . ':create', 'max_participants', $text);
                        } else {
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['MaxparticipantsNotValid_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_DESCRIPTION:
                        $this->editMessageText($this->localization[$this->language]['Description_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringDate_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        $this->redis->hSet($this->chat_id . ':create', 'description', $text);
                        $this->redis->set($this->chat_id . ':status', ENTERING_DATE);
                        break;
                    case ENTERING_DATE:
                        $text = intval($text);
                        if (is_integer($text) && $text > 2 && $text < 41) {
                            $date = strtotime($text . ' days');
                            $this->redis->hSet($this->chat_id . ':create', 'date', $date);
                            $this->editMessageText($this->localization[$this->language]['Date_Msg'] . date('Y-m-d', $date), $this->redis->get($this->chat_id . ':message_id'));
                            //$new_message = $this->sendMessageKeyboard($this->localization[$this->language]['EnteringPrizeName_Msg'], $this->inline_keyboard->getBackKeyboard());
                            $this->sendReplyMessageKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['DateNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case GIVEAWAY_EDIT_TITLE:
                        if (strlen($text) > 4) {
                       	    $this->redis->hSet($this->chat_id . ':create', 'title', $text);
                            $this->editMessageText($this->localization[$this->language]['NewTitle_Msg'] . '<i>' . $text . '</i>', $this->redis->get($this->chat_id . ':message_id'));
                            $this->sendMessageKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard());
                            $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['TitleLenght_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case GIVEAWAY_EDIT_HASHTAG:
                        $hashtag = &$this->getHashtags($text);
                        $hashtag = $hashtag[0];
                        if (isset($hashtag)) {
                            // If hashtag doesn't exists already in db
                            $sth = $this->pdo->prepare('SELECT COUNT(hashtag) FROM Giveaway WHERE LOWER(hashtag) = LOWER(:hashtag)');
                            $sth->bindParam(':hashtag', $hashtag);
                            $sth->execute();
                            $duplicated_hashtag = $sth->fetchColumn();
                            $sth = null;
                            if ($duplicated_hashtag == false) {
                                $this->redis->hSet($this->chat_id . ':create', 'hashtag', $hashtag);
                                $this->editMessageText($this->localization[$this->language]['NewHashtag_Msg'] . $hashtag, $this->redis->get($this->chat_id . ':message_id'));
                                $this->sendMessageKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard());
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                            } else {
                                $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['DuplicatedHashtag'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                            }
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['ValidHashtag_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case GIVEAWAY_EDIT_MAX:
                        $text = intval($text);
                        if (is_integer($text) && $text < PHP_INT_MAX && $text !== 0) {
                            $this->redis->hSet($this->chat_id . ':create', 'max_participants', $text);
                            $this->editMessageText($this->localization[$this->language]['NewMaxparticipants_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                            $this->sendMessageKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard());
                            $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['MaxparticipantsNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case GIVEAWAY_EDIT_DESCRIPTION:
                        $this->redis->hSet($this->chat_id . ':create', 'description', $text);
                        $this->editMessageText($this->localization[$this->language]['NewDescription_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $this->sendMessageKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard());
                        $this->redis->set($this->chat_id . ':status', ENTERING_DATE);
                        break;
                    case GIVEAWAY_EDIT_DATE:
                        $text = intval($text);
                        if (is_integer($text) && $text > 2 && $text < 41) {
                            $date = strtotime($text . ' days');
                            $this->redis->hSet($this->chat_id . ':create', 'date', $date);
                            $this->editMessageText($this->localization[$this->language]['NewDate_Msg'] . date('Y-m-d', $date), $this->redis->get($this->chat_id . ':message_id'));
                            $this->sendReplyMessageKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['DateNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_PRIZE_NAME:
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'name', $text);
                        $this->editMessageText($this->localization[$this->language]['PrizeName_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringPrizeValue_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_VALUE);
                        break;
                    case ENTERING_PRIZE_VALUE:
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $text = str_replace(',', '.', $text);
                        $money = preg_split('/(?<=\d)(?=[' . CURRENCY . '])/', $text);
                        if ((is_numeric((float)$money[0]) && (float)$money[0] != 0) || (is_numeric((float)$money[1]) && (float)$money[1] != '0')) {
                            if ((float)$money[0] != 0 && is_numeric($money[0])) {
                                $i = 0;
                                $j = 1;
                            } else {
                                $i = 1;
                                $j = 0;
                            }
                            $value = $money[$i];
                            $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'value', $value);
                            preg_match('/[' . CURRENCY . '=*]+/', $money[$j], $currency);
                            $currency = $currency[0];
                            if (isset($currency) && strpos(CURRENCY, $currency) !== false) {
                                $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'currency', $currency);
                                $this->editMessageText($this->localization[$this->language]['PrizeValue_Msg'] . $currency . $value, $this->redis->get($this->chat_id . ':message_id'));
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringPrizeType_Msg'], $this->getPrizeTypeKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_TYPE);
                            } else {
                                $this->editMessageText($this->localization[$this->language]['ValueNoCurrency_Msg'] . $value . '?', $this->redis->get($this->chat_id . ':message_id'));
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringPrizeCurrency_Msg'], $this->getCurrencyKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_CURRENCY);
                            }
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['ValueNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case ENTERING_PRIZE_KEY:
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'key', $text);
                        if (!$this->redis->hExists($this->chat_id . ':create', 'prizes_index')) {
                            $this->redis->hSet($this->chat_id . ':create', 'prizes_index', 1);
                        }
                        $this->editMessageText($this->localization[$this->language]['PrizeKey_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $this->sendMessageKeyboard($this->getPrizesBrowse(), $this->inline_keyboard->getKeyboard());
                        $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                        break;
                    case PRIZE_DETAIL_EDIT_NAME:
                        $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'name', $text);
                        $this->editMessageText($this->localization[$this->language]['NewPrizeName_Msg'] . $text, $this->redis->get($this->chat_id . ':message_id'));
                        $string = '';
                        $this->getPrizeInfo($string);
                        $this->sendReplyMessageKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                        break;
                    case PRIZE_DETAIL_EDIT_VALUE:
                        $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                        $text = str_replace(',', '.', $text);
                        $money = preg_split('/(?<=\d)(?=[' . CURRENCY . '])/', $text);
                        if ((is_numeric((float)$money[0]) && (float)$money[0] != 0) || (is_numeric((float)$money[1]) && (float)$money[1] != '0')) {
                            if ((float)$money[0] != 0 && is_numeric($money[0])) {
                                $i = 0;
                                $j = 1;
                            } else {
                                $i = 1;
                                $j = 0;
                            }
                            $value = $money[$i];
                            $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'value', $value);
                            preg_match('/[' . CURRENCY . '=*]+/', $money[$j], $currency);
                            $currency = $currency[0];
                            if (isset($currency) && strpos(CURRENCY, $currency) !== false) {
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'currency', $currency);
                                $this->editMessageText($this->localization[$this->language]['NewValue_Msg'] . $currency . $value, $this->redis->get($this->chat_id . ':message_id'));
                                $string = '';
                                $this->getPrizeInfo($string);
                                $this->sendReplyMessageKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                            } else {
                                $this->editMessageText($this->localization[$this->langauge]['NewValueNoCurrency_Msg'] . $value . '?', $this->redis->get($this->chat_id . ':message_id'));
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['EditPrizeCurrency_Msg'], $this->getCurrencyKeyboard(true), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_CURRENCY);
                            }
                        } else {
                            $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['NewValueNotValid_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($chat_id . ':message_id', $new_message['message_id']);
                        }
                        break;
                    case JOINING:
                        if (preg_match('/\#(.*)$/', $text, $matches)) {
                            //$this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                            //$this->sendMessage($this->localization[$this->language]['Hashtag_Msg'] . $matches[1], $this->inline_keyboard->getKeyboard());
                            $this->showGiveaway('#'.$matches[1]);
                        } else {
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                            $this->sendMessageKeyboard($this->localization[$this->language]['MissingHashtagWarn_Msg'], $this->inline_keyboard->getKeyboard());
                        }
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
                case 'hide_join_button':
                    $this->answerCallbackQuery($this->localization[$this->language]['CancelSuccess_Msg']);
                case 'menu':
                    $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', MENU);
                    break;
                case 'null':
                case 'show':
                    $stats = $this->getStatsList(true);
                    $this->editMessageText($stats[0], $message_id, $stats[1]);
                    break;
                case 'join':
                    $this->editMessageTextKeyboard($this->localization[$this->language]['Join_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', JOINING);
                    $this->redis->set($this->chat_id . ':message_id', $message_id);
                    break;
                case 'help':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['Help_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    break;
                case 'about':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Updates_Button'], 'url' => 'https://telegram.me/wisedragonstd'], ['text' => '😈 HadesWrapper', 'url' => 'https://gitlab.com/WiseDragonStd/HadesWrapper']);
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['About_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    break;
                case 'register':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['standard_Button'], 'callback_data' => 'standard'], ['text' => $this->localization[$this->language]['cumulative_Button'], 'callback_data' => 'cumulative']);
                    $this->inline_keyboard->addLevelButtons([
                    'text' => &$this->localization[$this->language]['Back_Button'],
                    'callback_data' => 'back']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', SELECTING_TYPE);
                    break;
                case 'standard':
                    // No break
                case 'cumulative':
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                    $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringTitle_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->answerCallbackQueryRef($this->localization['en'][$data . '_AnswerCallback']);
                    $this->redis->set($this->chat_id . ':status', ENTERING_TITLE);
                    $this->redis->hSet($this->chat_id .':create', 'type', $data);
                    $this->redis->set($this->chat_id . ':message_id', $message_id);
                    break;
                case 'back':
                    switch ($this->getStatus()) {
                        case SELECTING_TYPE:
                            // User might have inserted data so delete this scrap
                            if ($this->redis->exists($this->chat_id . ':create')) {
                                $this->redis->delete($this->chat_id . ':create');
                            }
                        case LANGUAGE:
                        case JOINING:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', MENU);
                            $this->redis->delete($this->chat_id . ':create');
                            break;
                        case ENTERING_TITLE:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['standard_Button'], 'callback_data' => 'standard'], ['text' => $this->localization[$this->language]['cumulative_Button'], 'callback_data' => 'cumulative']);
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['Register_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', SELECTING_TYPE);
                            break;
                        case ENTERING_HASHTAG:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringTitle_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_TITLE);
                            break;
                        case ENTERING_MAX:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_HASHTAG);
                            break;
                        case ENTERING_DESCRIPTION:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringMaxparticipants_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_MAX);
                            break;
                        case ENTERING_DATE:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringDescription_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DESCRIPTION);
                            break;
                        case ENTERING_PRIZE_NAME:
                            $this->redis->hIncrBy($this->chat_id . ':create', 'prizes', -1);
                            $this->editMessageTextKeyboard($this->getPrizesBrowse(), $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                            break;
                        case GIVEAWAY_CANCEL_PROMPT:
                        case GIVEAWAY_EDIT_TITLE:
                        case GIVEAWAY_EDIT_HASHTAG:
                        case GIVEAWAY_EDIT_DESCRIPTION:
                        case GIVEAWAY_EDIT_MAX:
                        case GIVEAWAY_EDIT_DATE:
                            $this->editMessageTextKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                            break;
                        case ENTERING_PRIZE_VALUE:
                            if ($this->redis->hGet($this->chat_id . ':create', 'prizes') == 0) {
                                $this->editMessageText($this->localization[$this->language]['EnteringPrizeName_Msg'], $message_id);
                            } else {
                                $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeName_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            }
                            $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_NAME);
                            $this->redis->set($this->chat_id . ':message_id', $message_id);
                            break;
                        case ENTERING_PRIZE_CURRENCY:
                        case ENTERING_PRIZE_TYPE:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeValue_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_VALUE);
                            $this->redis->set($this->chat_id . ':message_id', $message_id);
                            break;
                        case ENTERING_PRIZE_KEY:
                            $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeType_Msg'], $this->getPrizeTypeKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_TYPE);
                            break;
                        case PRIZE_DETAIL_EDIT_NAME:
                        case PRIZE_DETAIL_EDIT_VALUE:
                        case PRIZE_DETAIL_EDIT_CURRENCY:
                        case PRIZE_DETAIL_EDIT_TYPE:
                            $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                            $string = '';
                            $this->getPrizeInfo($string);
                            $this->editMessageTextKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                            break;
                        case GIVEAWAY_SUMMARY:
                            $status2 = GIVEAWAY_CANCEL_PROMPT;
                        case PRIZE_SUMMARY:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Confirm_Button'], 'callback_data' => 'delete_giveaway_confirm']);
                            $this->editMessageTextKeyboard($this->localization[$this->language]['CancelGiveawayPrompt_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', $status2 ?? PRIZE_CANCEL_PROMPT);
                            break;
                        case PRIZE_CANCEL_PROMPT:
                            $this->editMessageTextKeyboard($this->getPrizesBrowse(), $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                            break;
                    }
                    break;
                case 'skip':
                    switch ($this->getStatus()) {
                        case ENTERING_HASHTAG:
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'infinite']);
                            $this->editMessageText($this->localization[$this->language]['HashtagSkipped_Msg'] . NEWLINE . $this->localization[$this->language]['EnteringMaxparticipants_Msg'], $message_id, $this->inline_keyboard->getKeyboard());
                            $this->answerCallbackQuery($this->localization[$this->language]['HashtagSkipped_AnswerCallback']);
                            $this->redis->hSet($this->chat_id . ':create', 'hashtag', 'NULL');
                            $this->redis->set($this->chat_id . ':status', ENTERING_MAX);
                            break;
                        case ENTERING_DESCRIPTION:
                            $this->editMessageText($this->localization[$this->language]['DescriptionSkipped_Msg'] . NEWLINE . $this->localization[$this->language]['EnteringDate_Msg'], $message_id, $this->inline_keyboard->getBackKeyboard());
                            $this->answerCallbackQuery($this->localization[$this->language]['DescriptionSkipped_AnswerCallback']);
                            $this->redis->set($this->chat_id . ':status', ENTERING_DATE);
                            $this->redis->hSet($this->chat_id . ':create', 'description', 'NULL');
                            break;
                    }
                    break;
                case 'infinite':
                    $this->editMessageText($this->localization[$this->language]['MaxparticipantsInfinite_Msg'] . NEWLINE . $this->localization[$this->language]['EnteringDescription_Msg'], $message_id, $this->inline_keyboard->getBackSkipKeyboard());
                    $this->answerCallbackQuery($this->localization[$this->language]['MaxparticipantsInfinite_AnswerCallback']);
                    $this->redis->set($this->chat_id . ':status', ENTERING_DESCRIPTION);
                    $this->redis->set($this->chat_id . ':message_id', $message_id);
                    $this->redis->hSet($this->chat_id . ':create', 'max_participants', 0);
                    break;
                case 'confirm_giveaway':
                    if(!$this->redis->hExists($this->chat_id . ':create', 'prizes')) {
                        $this->redis->hSet($this->chat_id . ':create', 'prizes', 0);
                    } else {
                        $this->redis->hIncrBy($this->chat_id . ':create', 'prizes', 1);
                    }
                    $this->editMessageText($this->localization[$this->language]['EnteringFirstPrize_Msg'] . NEWLINE . $this->localization[$this->language]['EnteringPrizeName_Msg'], $message_id);
                    $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_NAME);
                    $this->redis->set($this->chat_id . ':message_id', $message_id);
                    break;
                case 'confirm_prizes':
                    if(!$this->database->exist("User", ["chat_id" => $this->chat_id])) {
                        $sth = $this->pdo->prepare('INSERT INTO "User" (chat_id, language) VALUES(:chat_id, :language)');
                        $sth->bindParam(':chat_id', $this->chat_id);
                        $sth->bindParam(':language', $info[1]);
                        $sth->execute();
                        $sth = null;
                    }
                    $giveaway = $this->redis->hGetAll($this->chat_id . ':create');
                    $sth = $this->pdo->prepare('INSERT INTO Giveaway (name, type, hashtag, description, max_participants, owner_id, created, last) VALUES (:name, :type, :hashtag, :description, :max_participants, :owner_id, :created, :date)');
                    $sth->bindParam(':name',  mb_substr($giveaway['title'], 0, 49));
                    $sth->bindParam(':type', $giveaway['type']);
                    $sth->bindParam(':hashtag', mb_substr($giveaway['hashtag'], 0, 31));
                    $sth->bindParam(':description', mb_substr($giveaway['description'], 0, 139));
                    $sth->bindParam(':max_participants', $giveaway['max_participants']);
                    $sth->bindParam(':owner_id', $this->chat_id);
                    $sth->bindParam(':created', date('Y-m-d', time()));
                    $sth->bindParam(':date', date('Y-m-d', $giveaway['date']));
                    $sth->execute();
                    $giveaway_id = $this->getMostRecent()['id'];
                    $sth = null;
                    $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;
                    $sth = $this->pdo->prepare('INSERT INTO Prize (name, value, currency, giveaway, type, key) VALUES (:name, :value, :currency, :giveaway, :type, :key)');
                    for ($i = 0; $i < $prizes_count; $i++) {
                        $prize = $this->redis->hGetAll($this->chat_id . ':prize:' . $i);
                        $sth->bindParam(':name', mb_substr($prize['name'], 0, 31));
                        $sth->bindParam(':value', $prize['value']);
                        $sth->bindParam(':currency', $prize['currency']);
                        $sth->bindParam(':giveaway', $giveaway_id);
                        $sth->bindParam(':type', $prize['type']);

                        $key = mb_substr($prize['key'], 0, 31);
                        $sth->bindParam(':key', $this->encryptKey($key));

                        $sth->execute();
                        $this->redis->delete($this->chat_id . ':prize:' . $i);
                    }
                    $sth = null;
                    $this->redis->delete($this->chat_id . ':create');
                    $response = $this->showCreatedGiveaway($giveaway_id);

                    if ($response[1] == 'cumulative') {
                        $this->inline_keyboard->addLevelButtons([
                            'text' => &$this->localization[$this->language]['ShareLink_Button'],
                            'callback_data' => 'invite_'.$giveaway_id.'_'.$giveaway['title']
                        ]);
                    }

                    $this->inline_keyboard->addLevelButtons([
                        'text' => &$this->localization[$this->language]['Menu_Button'],
                        'callback_data' => 'menu'
                    ]);

                    $this->editMessageTextKeyboard($response[0], $this->inline_keyboard->getKeyboard(),
                                                   $message_id);

                    // Update giveaways' list readable using `/browse`
                    $this->updateStats();
                    break;
                case 'delete_hashtag':
                    $this->redis->hSet($this->chat_id . ':create', 'hashtag', 'NULL');
                    $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                    $this->editMessageTextKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard(), $message_id);
                    break;
                case 'delete_description':
                    $this->redis->hSet($this->chat_id . ':create', 'description', 'NULL');
                    $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                    $this->editMessageTextKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard(), $message_id);
                    break;
                case 'prizes':
                    $this->editMessageTextKeyboard($this->getPrizesBrowse(), $this->inline_keyboard->getKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                    break;
                case 'add_prize':
                    $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeName_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                    $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_NAME);
                    $this->redis->hIncrBy($this->chat_id . ':create', 'prizes', 1);
                    $this->redis->set($this->chat_id . ':message_id', $message_id);
                    break;
                case 'delete_prize':
                    $selected_prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                    $this->redis->delete($this->chat_id . ':prize:' . $selected_prize);
                    // Check if there are other prizes to show
                    if ($this->redis->hGet($this->chat_id . ':create', 'prizes') != 0) {
                        $last_prize = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        // swap the last with the one that has been deleted
                        if ($last_prize != $selected_prize) {
                            $prize = $this->redis->hGetAll($this->chat_id . ':prize:' . $last_prize);
                            $this->redis->hMSet($this->chat_id . ':prize:' . $selected_prize, $prize);
                            $this->redis->delete($this->chat_id . ':prize:' . $last_prize);
                        }
                        $this->redis->hIncrBy($this->chat_id . ':create', 'prizes', -1);
                        $this->editMessageTextKeyboard($this->getPrizesBrowse(true), $this->inline_keyboard->getKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                    // If not let the user insert a new prize
                    } else {
                        $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_NAME);
                        $this->editMessageText($this->localization[$this->language]['EnteringPrizeName_Msg'], $message_id);
                        $this->redis->set($this->chat_id . ':message_id', $message_id);
                    }
                    break;
                case 'delete_giveaway_confirm':
                    switch ($this->getStatus()) {
                        case PRIZE_CANCEL_PROMPT:
                            // Prizes might exists so delete them
                            $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;
                            for ($i = 0; $i < $prizes_count; $i++) {
                                $this->redis->delete($this->chat_id . ':prize:' . $i);
                            }
                        case GIVEAWAY_CANCEL_PROMPT:
                            // User might have inserted data so delete this scrap
                            if ($this->redis->exists($this->chat_id . ':create')) {
                                $this->redis->delete($this->chat_id . ':create');
                            }
                            $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', MENU);
                            $this->redis->delete($this->chat_id . ':create');
                            break;
                    }
                    break;
                case 'options':
                    $this->editMessageText($this->localization[$this->language]['Language_AnswerCallback'] . ':', $message_id, $this->inline_keyboard->getChooseLanguageKeyboard());
                    $this->redis->set($this->chat_id . ':status', LANGUAGE);
                    $this->answerCallbackQueryRef($this->localization[$this->language]['Language_AnswerCallback']);
                    break;
                case 'null':
                    $this->answerEmptyCallbackQuery();
                    break;
                default:
                    $info = explode('_', $data);
                    if (strpos($info[0], 'currency') !== false) {
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->editMessageText($this->localization[$this->language]['PrizeValue_Msg'] . $info[1] . $this->redis->hGet($this->chat_id . ':prize:' . $prizes_count, 'value'), $this->redis->get($this->chat_id . ':message_id')); 
                        $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'currency', $info[1]);
                        $this->editMessageText($this->localization[$this->language]['EnteringPrizeType_Msg'], $message_id, $this->getPrizeTypeKeyboard());
                        $this->answerCallbackQueryRef($this->localization[$this->language][$info[1] . '_AnswerCallback']);
                        $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_TYPE);
                    } elseif (strpos($info[0], 'type') !== false) {
                        $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes');
                        $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'type', $info[1]);
                        $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeKey_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id) ;
                        $this->answerCallbackQueryRef($this->localization[$this->language]['Type' . $info[1] . '_Button']);
                        $this->redis->set($this->chat_id . ':message_id', $message_id);
                        $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_KEY);
                    // list keyboard use slash as limiter so parse $data instead of $info[0]
                    } elseif (strpos($data, 'indpr') !== false) {
                        $info = explode('/', $data);
                        $this->redis->hSet($this->chat_id . ':create', 'prizes_index', $info[1]);
                        $this->editMessageTextKeyboard($this->getPrizesBrowse(), $this->inline_keyboard->getKeyboard(), $message_id);
                    } elseif (strpos($info[0], 'edit') !== false) {
                        switch ($info[1]) {
                            case 'title':
                                $this->editMessageTextKeyboard($this->localization[$this->language]['EditTitle_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_EDIT_TITLE);
                                $this->redis->set($this->chat_id . ':message_id', $message_id);
                                break;
                            case 'hashtag':
                                if ($this->redis->hGet($this->chat_id . ':create', 'hashtag') !== 'NULL') {
                                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['DeleteHashtag_Button'], 'callback_data' => 'delete_hashtag']);
                                } else {
                                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                                }
                                $this->editMessageTextKeyboard($this->localization[$this->language]['EditHashtag_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_EDIT_HASHTAG);
                                $this->redis->set($this->chat_id . ':message_id', $message_id);
                                break;
                            case 'max':
                                if ($this->redis->hGet($this->chat_id . ':create', 'max_participants') == 0) {
                                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                                } else {
                                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['Infinite_Button'], 'callback_data' => 'edit_nolimit']);
                                }
                                $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringMaxparticipants_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_EDIT_MAX);
                                $this->redis->set($this->chat_id . ':message_id', $message_id);
                                break;
                            case 'description':
                                if ($this->redis->hGet($this->chat_id . ':create', 'description') !== 'NULL') {
                                     $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['DeleteDescription_Button'], 'callback_data' => 'delete_description']);
                                } else {
                                     $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
                                }
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_EDIT_DESCRIPTION);
                                $this->editMessageTextKeyboard($this->localization[$this->language]['EditDescription_Msg'], $this->inline_keyboard->getKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $message_id);
                                break;
                            case 'date':
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_EDIT_DATE);
                                $this->editMessageTextKeyboard($this->localization[$this->language]['EditDate_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':message_id', $message_id);
                                break;
                            case 'nolimit':
                                $this->redis->hSet($this->chat_id . ':create', 'max_participants', 0);
                                $this->editMessageTextKeyboard($this->getGiveawaySummary(), $this->getGiveawayEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', GIVEAWAY_SUMMARY);
                                break;
                            case 'prize':
                                switch ($info[2]) {
                                    case 'name':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_NAME);
                                        $this->redis->set($this->chat_id . ':message_id', $message_id);
                                        $this->editMessageTextKeyboard($this->localization[$this->language]['EditPrizeName_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                                        break;
                                    case 'type':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_TYPE);
                                        $this->editMessageTextKeyboard($this->localization[$this->language]['EditPrizeType_Msg'], $this->getPrizeTypeKeyboard(true), $message_id);
                                        break;
                                    case 'value':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_VALUE);
                                        $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeValue_Msg'], $this->inline_keyboard->getBackKeyboard(), $message_id);
                                        $this->redis->set($this->chat_id . ':message_id', $message_id);
                                        break;
                                    case 'currency':
                                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL_EDIT_CURRENCY);
                                        $this->editMessageTextKeyboard($this->localization[$this->language]['EnteringPrizeCurrency_Msg'], $this->getCurrencyKeyboard(true), $message_id);
                                        break;
                                }
                                break;
                        }
                    } elseif (strpos($info[0], 'prize') !== false) {
                        $this->redis->hSet($this->chat_id . ':create', 'prizes_selected', $info[1]);
                        $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                        $string = '';
                        $this->getPrizeInfo($string);
                        $this->editMessageTextKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                    // When editing prize
                    } elseif (strpos($info[0], 'new') !== false) {
                        switch ($info[1]) {
                            case 'currency':
                                $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'currency', $info[2]);
                                $string = '';
                                $this->getPrizeInfo($string);
                                $this->editMessageTextKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                                break;
                            case 'type':
                                $prize = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'type', $info[2]);
                                $string = '';
                                $this->getPrizeInfo($string);
                                $this->editMessageTextKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_DETAIL);
                                break;
                         }
                    } elseif (strpos($data, 'invite_') === 0) {
                        $data = explode('_', $data);
                        $this->generateReferralLink($data[1], $data[2], true);
                    } elseif (strpos($data, 'awards_') === 0) {
                        $data = explode('_', $data);
                        $this->currentPage = $data[2];

                        $this->showGiveawayPrizes($data[1]);
                    } elseif (strpos($data, 'show_') === 0) {
                        $data = explode('_', $data);
                        $this->currentPage = $data[2];

                        $this->showGiveaway($data[1], true);
                    } elseif (strpos($data, 'list/') === 0) {
                        $page = intval(explode('/', $data)[1]);
                        $response = array();
                        $details = array();
                        $limit = OBJECT_PER_LIST * $page;
                        $start = $limit - OBJECT_PER_LIST;

                        $this->totalLength = $this->userGiveawaySize - ($this->userGiveawaySize % OBJECT_PER_LIST);
                        $this->listLength = $this->totalLength / OBJECT_PER_LIST;

                        // Show other giveaway if there are any
                        if ($page == $this->listLength && $this->userGiveawaySize % OBJECT_PER_LIST > 0) {
                          $limit += $this->userGiveawaySize % OBJECT_PER_LIST;
                        }

                        for($pos = $start; $pos < $limit; $pos++) {
                            if ($this->userGiveaway[$pos] != null) {
                                array_push($response, $this->userGiveaway[$pos]);
                                $giveaway = explode("\n", $this->userGiveaway[$pos]);

                                // Choose if search for name or hashtag
                                if ($giveaway[1] == $this->localization[$this->language]['UndefinedHashtag_Msg']) {
                                    $target = $this->adjustName($giveaway[0]);
                                } else {
                                    $target = $giveaway[1];
                                }

                                array_push($details, [
                                    'text' => $target,
                                    'callback_data' => 'show_'.$target.'_'.$page
                                ]);
                            }
                        }

                        if ($this->listLength == 0) { $this->listLength++; }
                        $this->inline_keyboard->getCompositeListKeyboard($page, intval($this->listLength), "list");
                        call_user_func_array([$this->inline_keyboard, "addLevelButtons"], $details);

                        $this->inline_keyboard->addLevelButtons([
                            'text' => $this->localization[$this->language]['Menu_Button'],
                            'callback_data' => 'menu'
                        ]);

                        $this->editMessageText(join("\n=======================\n\n", $response), $message_id,
                                               $this->inline_keyboard->getKeyboard());

                    } elseif (strpos($data, 'join_') === 0) {
                        $this->editMessageReplyMarkup($message_id, []);
                        $giveaway_id = explode('_', $data)[1];

                        // Check for joined' number
                        $this->joined = 0;
                        $this->max_joined = 0;
                        $this->database->from("joined")->where('giveaway_id='.$giveaway_id)->select(["count(*)"], function($row){ $this->joined = $row['count']; });

                        $this->database->from("giveaway")->where('id='.$giveaway_id)->select(["max_participants"],
                            function($row){ $this->max_joined = $row['max_participants']; });

                        // Controls that the number of participants is limited and that
                        // this number hasn't reached.
                        if ($this->max_joined > 0 && ($this->joined == $this->max_joined)) {
                             $this->inline_keyboard->addLevelButtons([
                                'text' => &$this->localization[$this->language]['Menu_Button'],
                                'callback_data' => 'menu'
                             ]);

                             $this->editMessageReplyMarkup($message_id, $this->inline_keyboard->getKeyboard());
                             $this->answerCallbackQuery($this->localization[$this->language]['Maxjoined_Msg'], true);
                        } else {
                             $this->database->into('joined')->insert([
                                 'chat_id' => $this->chat_id,
                                 'giveaway_id' => $giveaway_id
                             ]);

                             $this->editMessageReplyMarkup($message_id, []);
                             $this->answerCallbackQuery($this->localization[$this->language]['JoinedSuccess_Msg']);

                             $this->updateStats();
                        }
                    } elseif (strpos('cls', $info[0]) !== false) {
                        $sth = $this->pdo->prepare('INSERT INTO "User" (chat_id, language) VALUES(:chat_id, :language)');
                        $sth->bindParam(':chat_id', $this->chat_id);
                        $sth->bindParam(':language', $info[1]);
                        $sth->execute();
                        $sth = null;
                        $this->language = $info[1];
                        $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard(), $message_id);
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

    public function &getCurrencyKeyboard($editing = false) {
        if ($editing) {
            $prefix = 'new_';
        }
        $this->inline_keyboard->addLevelButtons(['text' => '€', 'callback_data' => $prefix . 'currency_€'], ['text' => '$', 'callback_data' => $prefix . 'currency_$']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
        return $this->inline_keyboard->getKeyboard();
    }

    public function &getPrizeTypeKeyboard($editing = false) {
        if ($editing) {
            $prefix = 'new_';
        }
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Type1_Button'], 'callback_data' => $prefix . 'type_1']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Type2_Button'], 'callback_data' => $prefix . 'type_2']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Type3_Button'], 'callback_data' => $prefix . 'type_3']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Type4_Button'], 'callback_data' => $prefix . 'type_4']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'back']);
        return $this->inline_keyboard->getKeyboard();
    }

    public function &getPrizeEditKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['EditPrizeName_Button'], 'callback_data' => 'edit_prize_name'], ['text' => &$this->localization[$this->language]['EditPrizeType_Button'], 'callback_data' => 'edit_prize_type']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['EditPrizeValue_Button'], 'callback_data' => 'edit_prize_value'], ['text' => &$this->localization[$this->language]['EditPrizeCurrency_Button'], 'callback_data' => 'edit_prize_currency']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['DeletePrize_Button'], 'callback_data' => 'delete_prize']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Prizes_Button'], 'callback_data' => 'prizes']);
        return $this->inline_keyboard->getKeyboard();
    }

    public function &getPrizesBrowse($check_index = false) {
        $container = [];
        $index = $this->redis->hGet($this->chat_id . ':create', 'prizes_index');
        $this->prizes_index = [];
        $prizes = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1; 
        $list = intval($prizes / OBJECT_PER_LIST);
        if (($prizes % OBJECT_PER_LIST) > 0) {
            $list++;
        }
        if ($check_index && $index > $list) {
            $index--;
            $this->redis->hIncrBy($this->chat_id . ':create', 'prizes_index', -1);
        }
        $i = ($index - 1) * OBJECT_PER_LIST;
        $i_last = $i + 2;
        while ($i <= $i_last && $this->redis->exists($this->chat_id . ':prize:' . $i)) {
            $this->redis->hSet($this->chat_id . ':create', 'prizes_selected', $i);
            $this->getPrizeInfo($string, true);
            $string .= NEWLINE . '=======================' . NEWLINE;
            $i++;
        }
        $this->inline_keyboard->getCompositeListKeyboard($index, $list, 'indpr'); 
        if (isset($this->prizes_button[2])) {
            $this->inline_keyboard->addLevelButtons($this->prizes_button[0], $this->prizes_button[1], $this->prizes_button[2]);
        } elseif (isset($this->prizes_button[1])) {
            $this->inline_keyboard->addLevelButtons($this->prizes_button[0], $this->prizes_button[1]);
        } else {
            $this->inline_keyboard->addLevelButtons($this->prizes_button[0]);
        }
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['AddPrize_Button'], 'callback_data' => 'add_prize']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['CancelGiveaway_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['ConfirmPrizes_Button'], 'callback_data' => 'confirm_prizes']);
        $this->prizes_button = [];
        return $string;
    }

    public function getPrizeInfo(&$string, $summary = false) {
        $i = $this->redis->hGet($this->chat_id . ':create', 'prizes_selected');
        $prize = $this->redis->hGetAll($this->chat_id . ':prize:' . $i);
        if ($summary) {
            $this->prizes_button[] = [
                    'text' => &$prize['name'],
                    'callback_data' => 'prize_' . $i];
        }
        $string .= $this->localization[$this->language]['PrizeName_Msg'] . $prize['name'] . NEWLINE . $this->localization[$this->language]['PrizeType_Msg'] . '<code>' . $this->localization[$this->language]['Type' . $prize['type'] . '_Button'] . '</code>' . NEWLINE . $this->localization[$this->language]['PrizeValue_Msg'] . $prize['currency'] . $prize['value'] . NEWLINE;
    }

    private function &getGiveawaySummary() {
        $giveaway = $this->redis->hGetAll($this->chat_id . ':create');
        $string = '<b>' . $giveaway['title'] . '</b>' . NEWLINE .
                '<code>' . $this->localization[$this->language][$giveaway['type'] . '_Button'] . '</code>' . NEWLINE;
        if ($giveaway['hashtag'] !== 'NULL') {
            $string .= $giveaway['hashtag'] . NEWLINE;
        }
        if ($giveaway['description'] !== 'NULL') {
            $string .= '<i>' . $giveaway['description'] . '</i>' . NEWLINE;
        }
        $string .= NEWLINE . $this->localization[$this->language]['Maxparticipants_Msg']; 
        if ($giveaway['max_participants'] != 0) {
            $string .= $giveaway['max_participants'];
        } else {
            $string .= $this->localization[$this->language]['Infinite_Button'];
        }
        $string .= NEWLINE . $this->localization[$this->language]['Date_Msg'] . date('Y-m-d', $giveaway['date']); 
        return $string;
    }

    private function &getGiveawayEditKeyboard() {
        $giveaway = $this->redis->hGetAll($this->chat_id . ':create');
        if ($giveaway['hashtag'] === 'NULL') {
            $hashtag_button = 'AddHashtag_Button';
        } else {
            $hashtag_button = 'EditHashtag_Button';
        }
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['EditTitle_Button'], 'callback_data' => 'edit_title'], ['text' => &$this->localization[$this->language][$hashtag_button], 'callback_data' => 'edit_hashtag']);
        if ($giveaway['description'] === 'NULL') {
            $description_button = 'AddDescription_Button';
        } else {
            $description_button = 'EditDescription_Button';
        }
        if ($giveaway['max_participants'] == 0) {
            $max_button = 'AddMaxparticipants_Button';
        } else {
            $max_button = 'EditMaxparticipants_Button';
        }
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language][$description_button], 'callback_data' => 'edit_description'], ['text' => &$this->localization[$this->language][$max_button], 'callback_data' => 'edit_max']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['EditDate_Button'], 'callback_data' => 'edit_date']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['CancelGiveaway_Button'], 'callback_data' => 'back'], ['text' => &$this->localization[$this->language]['ConfirmGiveaway_Button'], 'callback_data' => 'confirm_giveaway']);
        return $this->inline_keyboard->getKeyboard();
    }

    private function showGiveawayPrizes($giveaway_id) {
        $this->response = "";
        $this->database->from("prize")->where("giveaway=$giveaway_id")->select(['*'], function($row){
            $this->counter++;
            $this->response .= NEWLINE.'<b>'.$row['name'].'</b>'.NEWLINE.'<i>'.$this->localization[$this->language]['Value_Msg'].$row['value'].' '.$row['currency'].'</i>'.NEWLINE.NEWLINE;
        });

        $this->inline_keyboard->getCompositeListKeyboard($this->currentPage,
                                                         intval($this->listLength), "list");
        $this->inline_keyboard->addLevelButtons([
            'text' => $this->localization[$this->language]['Back_Button'],
            'callback_data' => 'list/'.$this->currentPage
        ]);

        $this->inline_keyboard->addLevelButtons([
            'text' => $this->localization[$this->language]['Menu_Button'],
            'callback_data' => 'menu'
        ]);

        $this->editMessageText($this->response, $this->update['callback_query']['message']['message_id'],
                               $this->inline_keyboard->getKeyboard());
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

        $query = "SELECT giveaway_id FROM joined WHERE chat_id='".$this->message['from']['id']."' UNION ALL "
                ."SELECT id FROM giveaway WHERE owner_id=".$this->message['from']['id'];

        $this->database->execute($query, function($row){
          $this->found = true;

          $id = $row['id'] ?? $row['giveaway_id'];

          $this->database->from("Giveaway")->where("id=$id")->select(["*"], function($row){
            $this->sanitizeGiveawayDetails($row);

            $partial .= "<b>".$row['name']."</b>".NEWLINE.$row['hashtag'].NEWLINE.NEWLINE.$row['description'].NEWLINE.NEWLINE;

            if ($row['owner_id'] == $this->message["from"]["id"])
            {
                $partial .= $this->localization[$this->language]['Owned_Msg']."  |  ";
            } else {
                $partial .= $this->localization[$this->language]['Joined_Msg']."  |  ";
            }

            // Show giveaway's status
            if (date("Y-m-d") > $row['last'])
            {
                $partial .= $this->localization[$this->language]['Closed_Msg'].NEWLINE;
            } else if (date("Y-m-d") == $row['last']) {
                $partial .= $this->localization[$this->language]['LastDay_Msg'].NEWLINE;
            } else {
                $left = (strtotime(date("Y-m-d")) - strtotime($row['last'])) / 3600 / 24;
                $partial .= $left.' '.$this->localization[$this->language]['Days_Msg'].NEWLINE;
            }

            array_push($this->records, $partial);
            $this->counter++;
          });
        });

        if ($this->found == true) {
            $this->userGiveawayFull = true;
            return [$this->records, $this->counter];
        }

        $this->userGiveawayFull = false;
        return false;
    }

    private function getStatsList($browse_button = false) {
        $response = array();
        $details = array();
        $limit = OBJECT_PER_LIST;

        // Avoids users to remember to press `/start` before use browsing.
        $this->userGiveaway = array();
        $this->updateStats();

        // Show other giveaway if there are any
        if ($this->listLength == 1 && $this->userGiveawaySize % OBJECT_PER_LIST > 0) {
          $limit += $this->userGiveawaySize % OBJECT_PER_LIST;
        }

        // Fill user's giveaways
        if (!empty($this->userGiveaway)) {
            for($pos = 0; $pos < $limit; $pos++) {
                if ($this->userGiveaway[$pos] != null) {
                    array_push($response, $this->userGiveaway[$pos]);
                    $giveaway = explode("\n", $this->userGiveaway[$pos]);
                    $name = $giveaway[0];

                    // Choose if search for name or hashtag
                    if ($giveaway[1] == $this->localization[$this->language]['UndefinedHashtag_Msg']) {
                        $target = $this->adjustName($name);
                    } else {
                        $target = $giveaway[1];
                    }

                    array_push($details, [
                        "text" => $target,
                        "callback_data" => 'show_'.$target.'_1'
                    ]);
                }
            }

            if ($this->listLength == 0) { $this->listLength++; }

            $this->inline_keyboard->getCompositeListKeyboard(1, intval($this->listLength), "list");
            call_user_func_array([$this->inline_keyboard, "addLevelButtons"], $details);
            $this->inline_keyboard->addLevelButtons([
                'text' => $this->localization[$this->language]['Menu_Button'],
                'callback_data' => 'menu'
            ]);

            if ($browse_button) {
                return [join("\n=======================\n\n", $response),
                        $this->inline_keyboard->getKeyboard()];
            } else {
                $this->sendMessage(join("\n=======================\n\n", $response),
                                   $this->inline_keyboard->getKeyboard());
            }
        } else {
            if ($browse_button) {
                $this->inline_keyboard->addLevelButtons([
                    'text' => $this->localization[$this->language]['Menu_Button'],
                    'callback_data' => 'menu'
                ]);

                return [$this->localization[$this->language]['StatsEmpty_Msg'],
                        $this->inline_keyboard->getKeyboard()];
            } else {
                $this->sendMessage($this->localization[$this->language]['StatsEmpty_Msg']);
            }
        }
    }

    private function adjustName($name) {
        if (substr($name, 0, 3) == '<b>') {
            return substr($name, 3, -4);
        }

        return $name;
    }

    // Use OpenSSL features in order to encrypt prizes' keys.
    private function encryptKey($key) {
        return openssl_encrypt($key, 'AES-128-ECB', $this->token);
    }

    private function updateStats() {
        # Update user's giveaway stats
        $response = $this->getUserRecords();

        if ($response != false) {
            $this->userGiveaway = $response[0];
            $this->userGiveawaySize = $response[1];
            $this->listLength = ($this->userGiveawaySize - ($this->userGiveawaySize % OBJECT_PER_LIST)) / OBJECT_PER_LIST;
            $this->userGiveawayFull = true;
        } else {
            $this->userGiveawayFull = false;
        }
    }

    // This method implements the mechanism behind show giveaways through
    // `/show` command or `/browse`.
    //
    // When $callback_query_origin is true, we expect it's a call from `/browse`.
    private function showGiveaway($target, $callback_query_origin = false) {
        $this->callback_query_origin = $callback_query_origin;
        $this->response = "";
        $this->giveaway_id;
        $this->owner_id;
        $condition = "hashtag='$target' OR name='$target'";

        if ($target[0] != '#') {
            $condition = "name='$target'";
        }

        $this->database->from('giveaway')->where($condition)->select(["*"], function($row) {
          $this->sanitizeGiveawayDetails($row);

          $response = "";
          $response .= '<b>'.$row['name'].'</b>'.NEWLINE.$row['hashtag'].NEWLINE.NEWLINE;
          $response .= $row['description'].NEWLINE.NEWLINE;

          $this->already_joined = false;
          $this->owner_id = $row['owner_id'];
          $this->giveaway_id = $row['id'];

          if ($this->callback_query_origin == false) {
              // Check if the user is already a participant
              $this->database->from("joined")->where("chat_id='".$this->chat_id."' and giveaway_id=".$row['id'])
                   ->select(["*"], function($row) { $this->already_joined = true; });

              if ($this->already_joined == false) {
                  if ($this->owner_id != $this->chat_id) {
                      $this->inline_keyboard->addLevelButtons([
                          'text' => $this->localization[$this->language]['Join_Button'],
                          'callback_data' => 'join_'.$row['id']
                      ], [
                          'text' => $this->localization[$this->language]['Cancel_Button'],
                          'callback_data' => 'hide_join_button'
                      ]);
                  } else {
                      $response .= $this->localization[$this->language]['Owned_Msg'].'  |  ';
                  }
              } else {
                  $response .= $this->localization[$this->language]['Joined_Msg'].'  |  ';
              }
          } else {
              if ($this->owner_id == $this->chat_id) {
                  $response .= $this->localization[$this->language]['Owned_Msg'].'  |  ';
              } else {
                  $response .= $this->localization[$this->language]['Joined_Msg'].'  |  ';
              }

          }

          // Show giveaway's status
          if (date("Y-m-d") > $row['last'])
          {
              $response .= $this->localization[$this->language]['Closed_Msg'].NEWLINE;
              $this->inline_keyboard->getKeyboard();
              $response = $this->localization[$this->language]['ClosedGiveaway_Msg'];
          } else if (date("Y-m-d") == $row['last']) {
              $response .= $this->localization[$this->language]['LastDay_Msg'].NEWLINE;
          } else {
              $left = (strtotime(date("Y-m-d")) - strtotime($row['last'])) / 3600 / 24;
              $response .= $left.' '.$this->localization[$this->language]['Days_Msg'].NEWLINE;
          }

          $this->response = $response;
        });
        if ($callback_query_origin == false) {
            if ($this->response == null) {
                $this->inline_keyboard->addLevelButtons([
                    'text' => &$this->localization[$this->language]['Back_Button'],
                    'callback_data' => 'menu'
                ]);
                $this->sendMessageKeyboard($this->localization[$this->language]['NoGiveawayWarn_Msg'],
                                           $this->inline_keyboard->getKeyboard());
            } else {
                $this->sendMessage($this->response, $this->inline_keyboard->getKeyboard());
            }
        } else {
            $this->inline_keyboard->getCompositeListKeyboard($this->currentPage,
                                                             intval($this->listLength), "list");
            $this->inline_keyboard->addLevelButtons([
                'text' => $this->localization[$this->language]['Back_Button'],
                'callback_data' => 'list/'.$this->currentPage
            ], [
                'text' => 'Browse Prize',
                'callback_data' => 'awards_'.$this->giveaway_id.'_'.$this->currentPage
            ]);

            $this->inline_keyboard->addLevelButtons([
                'text' => $this->localization[$this->language]['Menu_Button'],
                'callback_data' => 'menu'
            ]);
            $this->editMessageTextKeyboard($this->response, $this->inline_keyboard->getKeyboard(), $this->update['callback_query']['message']['message_id']);
        }
    }

    // Users are allowed to skip some details about the giveaway and replace it with
    // an useless NULL.
    //
    // This method replace this NULLs with more human-readable sentences.
    private function sanitizeGiveawayDetails(&$giveaway) {
        $localization = $this->localization[$this->language];
        $SANITIZERS = [
            'description' => $localization['Undefined_Msg'],
            'hashtag' => $localization['UndefinedHashtag_Msg']
        ];

        foreach ($SANITIZERS as $field => $replacement) {
            if ($giveaway[$field] == 'NULL') {
                $giveaway[$field] = $replacement;
            }
        }

        return;
    }

    // Users are allowed to join giveaways through invite links when the giveaway
    // is a cumulative one or when they use inline bot's features.
    //
    // This method implements the logic behind the invite links' mechanism.
    private function addByReferral($giveaway_id, $referral_id, $chat_id) {
        $this->usersJoined = 0;
        $this->limit = 0;

        $this->database->from("joined")->where('giveaway_id='.$giveaway_id)
                                       ->select(["count(*)"], function($row) {
            $this->usersJoined = intval($row['count']);
        });

        $this->database->from("giveaway")->where('id='.$giveaway_id)
                                         ->select(["max_participants"], function($row) {
            $this->limit = $row['max_participants'];
        });

        $this->inline_keyboard->clearKeyboard();
        $this->inline_keyboard->addLevelButtons([
            'text' => $this->localization[$this->language]['Show_Button'],
            'callback_data' => 'show'
        ]);

        if ($this->limit > 0 && ($this->usersJoined == $this->limit)) {
             $this->sendMessage($this->localization[$this->language]['MaxParticipants_Msg'],
                                $this->inline_keyboard->getKeyboard());
        } else {
             $this->database->into('joined')->insert([
                 'chat_id' => $chat_id,
                 'giveaway_id' => $giveaway_id
             ]);

             $this->database->execute("UPDATE joined SET invites = invites + 1 WHERE chat_id = '$referral_id'");
             $this->sendMessage($this->localization[$this->language]['JoinedSuccess_Msg'],
                                $this->inline_keyboard->getKeyboard());

             $this->updateStats();
        }
    }

    // Generate the referral link for the given giveaway (ID)
    private function generateReferralLink($giveaway, $title, $callback_query) {
        $link = "telegram.me/giveaways_bot?start=".base64_encode($this->chat_id)."_"
                                                  .base64_encode($giveaway);

        $message = $this->localization[$this->language]['JoinLabel_Msg'] . '<b>'.$title.'</b>'.':'
                  .NEWLINE.NEWLINE.$link;
        
        if ($callback_query) {
            $message_id = $this->update['callback_query']['message']['message_id'];

            $this->editMessageReplyMarkup($message_id, $this->inline_keyboard->getKeyboard());
            $this->editMessageText($message, $message_id);
        } else {
            $this->sendMessage($message);
        }
    }

    // Returns the most recent giveaway from the Giveaway table.
    private function getMostRecent() {
        $this->giveaway = null;

        $this->database->execute('SELECT * FROM Giveaway ORDER BY id DESC LIMIT 1', function($row){
            $this->giveaway = $row;
        });

        return $this->giveaway;
    }

    // Show the given giveaway after it's created.
    private function showCreatedGiveaway($id) {
        $this->result = '';
        $this->prizes = 0;
        $this->totalValue = 0.0;
        $this->currency = '';
        $this->type = null;
        $this->title = null;

        $this->database->from('Giveaway')->where("id=$id")->select(['*'], function($row){
            $this->type = $row['type'];
            $this->title = $row['name'];

            foreach ($row as $key => $value) {
                if ($value == 'NULL' || ($key == 'max_participants' && $value == 0)) {
                    if ($key == 'max_participants') {
                        $row[$key] = $this->localization[$this->language]['Unlimited_Msg'];
                        continue;
                    }

                    $row[$key] = $this->localization[$this->language]['Undefined_Msg'];
                }
            }

            $this->result .= $this->localization[$this->language]['Title_Msg'] . $row['name'] . NEWLINE
                            .$this->localization[$this->language]['Hash_Msg'] . $row['hashtag'] . NEWLINE
                            .$this->localization[$this->language]['Type_Msg'] . $row['type'] . NEWLINE
                            .$this->localization[$this->language]['MPValue_Msg'] . $row['max_participants'] . NEWLINE
                            .$this->localization[$this->language]['EndDate_Msg'] . $row['last'] . NEWLINE
                            .$this->localization[$this->language]['Desc_Msg'] . $row['description'] . NEWLINE;
        });

        $this->database->from('prize')->where("giveaway=$id")->select(['*'], function($row){
            $this->prizes++;
            $this->totalValue += floatval($row['value']);
            $this->currency = $row['currency'];
        });

        $this->result .=  NEWLINE.$this->localization[$this->language]['GiveawayPrizes_Msg'].$this->prizes;
        $this->result .= NEWLINE.$this->localization[$this->language]['TotalValue_Msg']."$this->totalValue"
                                .$this->currency;

        $this->answerCallbackQuery($this->localization[$this->language]['AfterCreation_Msg']);

        return [$this->result, $this->type];
    }

    private function startAction() {
        if(!$this->database->exist("User", ["chat_id" => $this->chat_id])) {
            $this->sendMessageKeyboard($this->localization['en']['Welcome_Msg'], $this->getStartLanguageKeyboard());
        } else {
            if ($this->redis->exists($this->chat_id . ':create')) {
                $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;
                for ($i = 0; $i < $prizes_count; $i++) {
                    $this->redis->delete($this->chat_id . ':prize:' . $i);
                }
                $this->redis->delete($this->chat_id . ':create');
            }
                $this->sendMessageKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard());
                $this->redis->set($this->chat_id . ':status', MENU);
        }
    }

    private function getStartKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Register_Button'], 'callback_data' => 'register'], ['text' => &$this->localization[$this->language]['Join_Button'], 'callback_data' => 'join']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Show_Button'], 'callback_data' => 'show']);
        $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['Language_Button'] . '/Language', 'callback_data' => 'options']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Help_Button'], 'callback_data' => 'help'], ['text' => &$this->localization[$this->language]['About_Button'], 'callback_data' => 'about']);
        return $this->inline_keyboard->getKeyboard();
    }

    private function getStartLanguageKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization['languages']['en'], 'callback_data' => 'cls_en']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization['languages']['it'], 'callback_data' => 'cls_it']);
        return $this->inline_keyboard->getKeyboard(); 
    }
}
