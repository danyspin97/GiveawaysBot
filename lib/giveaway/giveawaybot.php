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
define('SHOW_GIVEAWAY_DETAILS', 26);
define('JOIN_HASHTAG_PROMPT', 27);
define('JOINED', 28);
define('GV_NOT_VALID', 29);
define('SHOW_ALL', 30);
define('LANGUAGE', 31);
define('GIVEAWAY_CANCEL_PROMPT', 32);
define('PRIZE_CANCEL_PROMPT', 33);
define('SHOW_GIVEAWAY_LIST', 34);
define('SHOW_PRIZES', 35);
define('OBJECT_PER_LIST', 3);
define('CURRENCY', '€$');


class GiveAwayBot extends \WiseDragonStd\HadesWrapper\Bot {

    // Process inline queries
    public function processInlineQuery() {
        // Set the inline query
        $inline_query = &$this->update['inline_query'];
        // Get data
        $this->chat_id = &$inline_query['from']['id'];
        $text = &$inline_query['query'];
        $this->getLanguage();

        // If the user is registred then show him the giveaway he joined or created
        if ($this->database->exist("User", ["chat_id" => $this->chat_id])) {
            $this->results = new \WiseDragonStd\HadesWrapper\InlineQueryResults();

            // Is the query null?
            if (isset($text) && $text !== '') {
                /* Show all giveaway, both created or joined, that correspond to the query,
                 * Show only the ones that are active,
                 * Search also all the giveaway that has the hashtag or the id (for giveaway that has no hashtag) equal to the query
                 */
                $sth = $this->pdo->prepare('SELECT giveaway.id, giveaway.name, giveaway.owner_id, giveaway.description, giveaway.type, giveaway.hashtag, giveaway.last
                                                   FROM giveaway INNER JOIN joined
                                                   ON giveaway.id = joined.giveaway_id AND giveaway.last > NOW() AND joined.chat_id = :chat_id AND (name ~* :query OR hashtag ~* :query)
                                            UNION
                                                SELECT id, name, owner_id, description, type, hashtag, last FROM giveaway WHERE giveaway.last > NOW() AND (hashtag = :query OR (owner_id = :chat_id AND (name ~* :query OR hashtag ~* :query))) LIMIT 50');
                // If there is an hashtag in front of it, remove it and use it as query
                $query_inline = (mb_substr($text, 0, 1) === '#') ? mb_substr($text, 1) : $text;
                $sth->bindParam(':query', $query_inline);
            } else {
                // Show just the giveaways that the user joined or created
                $sth = $this->pdo->prepare('SELECT DISTINCT giveaway.id, giveaway.name, giveaway.owner_id, giveaway.description, giveaway.type, giveaway.hashtag, giveaway.last
                                               FROM giveaway INNER JOIN joined
                                               ON giveaway.id = joined.giveaway_id AND giveaway.last > NOW() AND joined.chat_id = :chat_id
                                               UNION
                                                   SELECT id, name, owner_id, description, type, hashtag, last FROM giveaway WHERE owner_id = :chat_id ORDER BY last LIMIT 50');
            }
            $sth->bindParam(':chat_id', $this->chat_id);
            try {
                $sth->execute();
            } catch (PDOException $e) {
                $e->getMessage();
            }

            // While we have giveaway got by the query
            while($row = $sth->fetch()) {
                $name = $row['name'];

                // The message that the article send
                $message = $this->localization[$this->language]['JoinLabel_Msg'] . ' <b>'. $this->removeUsernameFormattation($name, 'b') . '</b>'
                          .$this->localization[$this->language]['NowLabel_Msg'];

                // The description shown
                $description = $row['description'] === 'NULL' ? '' : $row['description'];
                $message .= NEWLINE . $description;

                // Create the join button
                $this->inline_keyboard->addLevelButtons([
                        'text' => $this->localization[$this->language]['Join_Button'],
                        'callback_data' => 'start_and_join/'. $this->chat_id . '_'
                                                            . $row['id'] . '_'
                                                            . $row['owner_id']
                ]);

                // Create the article
                $this->results->newArticleKeyboard($row['name'], $message, $description,
                                               $this->inline_keyboard->getNoJSONKeyboard());
                unset($description);
                unset($message);
            }

            $this->answerInlineQuerySwitchPMRef($this->results->getResults(), $this->localization[$this->language]['SwitchPM_InlineQuery'], 'start');
            $sth = null;

        } else {

            // If the user hasn't started the bot yet, but a button "Register"
            $this->answerEmptyInlineQuerySwitchPM($this->localization[$this->language]['Register_InlineQuery']);
        }
    }

    public function processMessage() {
        // Set data
        $message = &$this->update['message'];
        $this->chat_id = $this->update["message"]["chat"]["id"];

        // Skip empty message
        if (isset($message['text'])) {
            // Text sent by the user
            $text = &$message['text'];
            // Id of the message
            $message_id = &$message['message_id'];
            $this->getLanguage();
            $this->getStatus();

            if (strpos($text, '/start') !== false) {
                $parameter = explode(' ', $text)[1];

                if ($parameter == null) {
                    // Check if user exists in the db
                    if(!$this->database->exist('User', ['chat_id' => $this->chat_id])) {

                        // Then send him a welcome message with the language selection
                        $this->sendMessageKeyboard($this->localization['en']['Welcome_Msg'],
                                       $this->getStartLanguageKeyboard());
                    } else {
                        // Delete creation data, to prevent junk on next giveaway creation
                        if ($this->redis->exists($this->chat_id . ':create')) {
                            $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;

                            // Delete also prizes
                            for ($i = 0; $i < $prizes_count; $i++) {
                                $this->redis->delete($this->chat_id . ':prize:' . $i);
                            }
                            $this->redis->delete($this->chat_id . ':create');
                        }

                        $this->sendMessageKeyboard($this->localization[$this->language]['Menu_Msg'],
                                       $this->getStartKeyboard());
                        $this->redis->set($this->chat_id . ':status', MENU);
                    }
                } else {
                    // Get data of the parameter (respecively:
                    // 1) chat_id of who shared the giveaway
                    // 2) id of the giveaway
                    $data = explode('_', $parameter);
                    $ref_id = base64_decode($data[0]);
                    iconv(mb_detect_encoding($ref_id, mb_detect_order(), true), "UTF-8", $ref_id);
                    $giveaway_id = base64_decode($data[1]);
                    iconv(mb_detect_encoding($giveaway_id, mb_detect_order(), true), "UTF-8", $giveaway_id);

                    try {
                        // Add the user if he isn't in the db yet
                        if(!$this->database->exist("User", ["chat_id" => $this->chat_id])) {
                            $this->database->into('"User"')->insert([
                                    "chat_id" => $this->chat_id,
                                    "language" => 'en'
                            ]);
                        }
                    } catch (PDOException $e) {
                        echo $e->getMessage();
                    }

                    $sth = $this->pdo->prepare('SELECT id, owner_id, max_participants, last FROM giveaway WHERE id = :id');
                    $sth->bindParam(':id', $giveaway_id);
                    try {
                        $sth->execute();
                    } catch (PDOException $e) {
                        echo $e->getMessage();
                    }
                    $giveaway = $sth->fetch();
                    $sth = null;

                    // Add a menu button to the keyboard
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
                    $this->redis->set($this->chat_id . ':status', SHOW_GIVEAWAY_DETAILS);
                    // If the giveaway exists
                    if ($giveaway != false) {
                        // If the giveaway hasn't ended
                        if ($giveaway['last'] >= date('Y-m-d')) {
                            // If the giveaway hasn't been created by the user that want to join
                            if ($giveaway['owner_id'] != $this->chat_id) {
                                $sth = $this->pdo->prepare('SELECT COUNT (chat_id) FROM joined WHERE giveaway_id = :giveaway_id AND chat_id = :chat_id');
                                $sth->bindParam(':giveaway_id', $giveaway_id);
                                $sth->bindParam(':chat_id', $this->chat_id);
                                try {
                                    $sth->execute();
                                } catch (PDOException $e) {
                                    $e->getMessage();
                                }
                                $alredy_joined = $sth->fetch();
                                $sth = null;

                                // Has the user already joined this giveaway?
                                if ($already_joined == false) {
                                    if ($this->joinGiveaway($giveaway_id, $answer_callback) === true) {
                                        // Remove the Menu button we created before if/else
                                        $this->inline_keyboard->clearKeyboard();
                                        // Call addByReferral to handle the adding to the user and send the user the result
                                        $this->sendMessageKeyboard($this->showGiveaway($giveaway_id), $this->inline_keyboard->getKeyboard());
                                        $this->redid->set($this->chat_id . ':status', SHOW_GIVEAWAY_DETAILS);
                                    // There was an error with the joining
                                    } else {
                                        // Remove the Menu button we created before if/else
                                        $this->inline_keyboard->clearKeyboard();
                                        $this->sendMessage($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard());
                                    }
                                // The user has already joined the giveaway
                                } else {
                                    $this->sendMessage($this->localization[$this->language]['AlreadyIn_Msg'],
                                            $this->inline_keyboard->getKeyboard());
                                }
                            // The creator of the giveaway is trying to join
                            } else {
                                $this->sendMessageKeyboard($this->localization[$this->language]['Inception_Msg'], $this->inline_keyboard->getKeyboard());
                            }
                        // The giveaway has ended
                        } else {
                            $this->sendMessageKeyboard($this->localization[$this->language]['GiveawayEnded_Msg'], $this->inline_keyboard->getKeyboard());
                        }
                    // The giveaway is not valid
                    } else {
                        $this->sendMessageKeyboard($this->localization[$this->language]['GiveawayNotExists_Msg'], $this->inline_keyboard->getKeyboard());
                    }
                }
            // Received create command
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
            // Received browse command
            } elseif (preg_match('/^\/browse$/', $text, $matches)) {
                if ($this->getGiveawayList(1, $message) === true) {
                    $this->sendMessageKeyboard($message, $this->inline_keyboard->getKeyboard());
                } else {
                    $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
                    $this->sendMessageKeyboard($this->localization[$this->language]['StatsEmpty_Msg'], $this->inline_keyboard->getKeyboard());
                }
            // Received join command followed by an hashtag
            } elseif (preg_match('/^\/join \#(.*)$/', $text, $matches)) {
                $this->sendMessageKeyboard($this->showGiveaway('#' . $matches[1]), $this->inline_keyboard->getKeyboard());
            // Received join command without arguments
            } elseif (preg_match('/^\/join$/', $text, $matches)) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
                $this->sendMessageKeyboard($this->localization[$this->language]['Join_Msg'], $this->inline_keyboard->getKeyboard());
                $this->redis->set($this->chat_id . ':status', JOINING);
            // Received help command
            } elseif (strpos($text, '/help') !== false) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                $this->sendMessage($this->localization[$this->language]['Help_Msg']);
            // Received about command
            } elseif (strpos($text, '/about') !== false) {
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Updates_Button'], 'url' => 'https://telegram.me/wisedragonstd'], ['text' => '😈 HadesWrapper', 'url' => 'https://gitlab.com/WiseDragonStd/HadesWrapper']);
                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'menu']);
                $this->sendMessageKeyboard($this->localization[$this->language]['About_Msg'], $this->inline_keyboard->getKeyboard());
            // The user sent data in a message, processing it depends on bot status for the current user
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
                        $hashtag = mb_substr($hashtag[0], 1);
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
                                $new_message = $this->sendReplyMessageKeyboard($this->localization[$this->language]['DuplicatedHashtag_Msg'], $this->inline_keyboard->getBackSkipKeyboard(), $message_id);
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
                        $hashtag = mb_substr($hashtag[0], 1);
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
                        $this->checkPrizeValue($text, $money, $currency, $money_inserted, $currency_inserted);
                        if ($money_inserted) {
                            $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'value', $money);
                            if ($currency_inserted) {
                                $this->redis->hSet($this->chat_id . ':prize:' . $prizes_count, 'currency', $currency);
                                $this->editMessageText($this->localization[$this->language]['PrizeValue_Msg'] . $currency . $money, $this->redis->get($this->chat_id . ':message_id'));
                                $this->sendReplyMessageKeyboard($this->localization[$this->language]['EnteringPrizeType_Msg'], $this->getPrizeTypeKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', ENTERING_PRIZE_TYPE);
                            } else {
                                $this->editMessageText($this->localization[$this->language]['ValueNoCurrency_Msg'] . $money . '?', $this->redis->get($this->chat_id . ':message_id'));
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
                        $this->checkPrizeValue($text, $money, $currency, $money_inserted, $currency_inserted);
                        if ($money_inserted) {
                            $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'value', $money);
                            if ($currency_inserted) {
                                $this->redis->hSet($this->chat_id . ':prize:' . $prize, 'currency', $currency);
                                $this->editMessageText($this->localization[$this->language]['NewValue_Msg'] . $currency . $money, $this->redis->get($this->chat_id . ':message_id'));
                                $string = '';
                                $this->getPrizeInfo($string);
                                $this->sendReplyMessageKeyboard($string, $this->getPrizeEditKeyboard(), $message_id);
                                $this->redis->set($this->chat_id . ':status', PRIZE_SUMMARY);
                            } else {
                                $this->editMessageText($this->localization[$this->langauge]['NewValueNoCurrency_Msg'] . '?' . $money, $this->redis->get($this->chat_id . ':message_id'));
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
                            $this->sendMessageKeyboard($this->showGiveaway('#' . $matches[1], $this->localization[$this->language]['JoinInsertNewHashtag_Msg']), $this->inline_keyboard->getKeyboard());;
                        // No hashtag given
                        } else {
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
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
                    $this->answerCallbackQuery();
                case 'show':
                    if ($this->getGiveawayList(1, $message) === true) {
                        $this->editMessageTextKeyboard($message, $this->inline_keyboard->getKeyboard(), $message_id);
                    } else {
                        $this->answerCallbackQuery($this->localization[$this->language]['NoGiveawayToShow_AnswerCallbackQuery']);
                    }
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
                    $sth = $this->pdo->prepare('INSERT INTO Giveaway (name, type, hashtag, description, max_participants, owner_id, created, last) VALUES (:name, :type, :hashtag, :description, :max_participants, :owner_id, :created, :date) RETURNING id');
                    $sth->bindParam(':name',  mb_substr($giveaway['title'], 0, 49));
                    $sth->bindParam(':type', $giveaway['type']);
                    $sth->bindParam(':hashtag', mb_substr($giveaway['hashtag'], 0, 31));
                    $sth->bindParam(':description', mb_substr($giveaway['description'], 0, 139));
                    $sth->bindParam(':max_participants', $giveaway['max_participants']);
                    $sth->bindParam(':owner_id', $this->chat_id);
                    $sth->bindParam(':created', date('Y-m-d', time()));
                    $sth->bindParam(':date', date('Y-m-d', $giveaway['date']));
                    try {
                        $sth->execute();
                    } catch (PDOException $e) {
                        echo $e->getMessage();
                    }
                    $giveaway_id = $sth->fetch()['id'];
                    $sth = null;
                    $title = $giveaway['title'];
                    $prizes_count = $this->redis->hGet($this->chat_id . ':create', 'prizes') + 1;
                    $sth = $this->pdo->prepare('INSERT INTO Prize (name, value, currency, giveaway_id, type, key) VALUES (:name, :value, :currency, :giveaway_id, :type, :key)');
                    for ($i = 0; $i < $prizes_count; $i++) {
                        $prize = $this->redis->hGetAll($this->chat_id . ':prize:' . $i);
                        $sth->bindParam(':name', mb_substr($prize['name'], 0, 31));
                        $sth->bindParam(':value', $prize['value']);
                        $sth->bindParam(':currency', $prize['currency']);
                        $sth->bindParam(':giveaway_id', $giveaway_id);
                        $sth->bindParam(':type', $prize['type']);
                        $key = mb_substr($prize['key'], 0, 31);
                        $sth->bindParam(':key', $this->encryptKey($key));
                        try {
                            $sth->execute();
                        } catch (PDOException $e) {
                            echo $e->getMessage();
                        }
                        $this->redis->delete($this->chat_id . ':prize:' . $i);
                    }
                    $sth = null;
                    $this->redis->delete($this->chat_id . ':create');

                    $this->editMessageTextKeyboard($this->showGiveaway($giveaway_id), $this->inline_keyboard->getKeyboard(),
                                                   $message_id);

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
                case 'same/language':
                    $this->answerCallbackQueryRef($this->localization[$this->language]['SameLanguage_AnswerCallback']);
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
                    // Get giveaway invite link
                    } elseif (strpos($data, 'invite_') === 0) {
                        $data = explode('_', $data);
                        $this->generateReferralLink($data[1], $data[2], true);
                    // Join from an inline_message
                    } elseif (strpos($data, 'start_and_join/') === 0) {
                        $data = explode('_', explode('/', $data)[1]);
                        $ref_id = intval($data[0]);
                        $giveaway_id = $data[1];
                        $owner_id = intval($data[2]);

                        if ($this->joinGiveaway($giveaway_id, $answer_callback) === true) {
                        // If the user that shared the giveaway isn't the same that created it
                            if ($giveaway['owner_id'] !== $referral_id) {
                                // Increment the referral value of the user that shared the giveaway
                                $this->database->execute("UPDATE joined SET invites = invites + 1
                                                            WHERE chat_id = $referral_id
                                                            AND giveaway_id = $giveaway_id");

                            }

                            $this->sendMessageKeyboard($this->showGiveaway($giveaway_id), $this->inline_keyboard->getKeyboard());
                        }
                        $this->answerCallbackQueryRef($answer_callback);
                    // Browsing giveaway's prizes
                    } elseif (strpos($data, 'awards') === 0) {
                        $info = explode('/', $data);
                        $this->editMessageTextKeyboard($this->showGiveawayPrizes($info[1], $info[3], $info[2]), $this->inline_keyboard->getKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':status', SHOW_PRIZES);
                    // User clicked on a giveaway button while browsing them
                    } elseif (strpos($data, 'giveawayshow') === 0) {
                        $this->editMessageTextKeyboard($this->showGiveaway($info[1], '', $info[2]), $this->inline_keyboard->getKeyboard(), $message_id);
                        $this->redis->set($this->chat_id . ':status', SHOW_GIVEAWAY_DETAILS);
                    } elseif (strpos($data, 'list/') === 0) {
                        $index = explode('/', $data)[1]; 
                        $string = '';
                        if ($this->getGiveawayList($index, $string) === true) {
                            $this->editMessageTextKeyboard($string, $this->inline_keyboard->getKeyboard(), $message_id);
                            $this->redis->set($this->chat_id . ':status', SHOW_GIVEAWAY_LIST);
                        }
                    } elseif (strpos($data, 'join_') === 0) {
                        $giveaway_id = explode('_', $data)[1];

                        if ($this->joinGiveaway($giveaway_id, $answer_callback) === true) {
                            $this->answerCallbackQueryRef($answer_callback);
                            $this->sendMessageKeyboard($this->showGiveaway($giveaway_id), $this->inline_keyboard->getKeyboard());
                        } else {
                            $this->answerCallbackQueryRef($answer_callback);
                            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
                            $this->editMessageReplyMarkup($message_id, $this->inline_keyboard->getKeyboard());
                        }
                    } elseif (mb_strpos($data, 'cls') !== false) {
                        $info = explode('/', $data);
                        if(!$this->database->exist("User", ["chat_id" => $this->chat_id])) {
                            $sth = $this->pdo->prepare('INSERT INTO "User" (chat_id, language) VALUES(:chat_id, :language)');
                            $sth->bindParam(':chat_id', $this->chat_id);
                            $sth->bindParam(':language', $info[1]);
                            try {
                                $sth->execute();
                                $sth = null;
                            } catch (PDOException $e) {
                                echo $e->getMessage();
                            }
                        }
                        $this->language = $info[1];
                        $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard(), $message_id);
                        $this->answerCallbackQueryRef($this->localization[$this->language]['UserRegistred_AnswerCallbackQuery']);
                        $this->redis->set($this->chat_id . ':status', MENU);
                    } elseif (mb_strpos($data, 'cl') !== false) {
                        $info = explode('/', $data);
                        $this->setLanguage($info[1]);
                        $this->editMessageTextKeyboard($this->localization[$this->language]['Menu_Msg'], $this->getStartKeyboard(), $message_id);
                        $this->answerCallbackQueryRef($this->localization[$this->language]['LanguageChanged_AnswerCallback']);
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
        $this->prizes_button = [];
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
        $string = '<b>' . $this->removeUsernameFormattation($giveaway['title'], 'b') . '</b>' . NEWLINE .
                '<code>' . $this->localization[$this->language][$giveaway['type'] . '_Button'] . '</code>' . NEWLINE;
        if ($giveaway['hashtag'] !== 'NULL') {
            $string .= $giveaway['hashtag'] . NEWLINE;
        }
        if ($giveaway['description'] !== 'NULL') {
            $string .= '<i>' . $this->removeUsernameFormattation($giveaway['description'], 'i') . '</i>' . NEWLINE;
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

    private function joinGiveaway($giveaway_id, &$answer_callback) {
        // Get the giveaway the user want to join
        $sth = $this->pdo->prepare('SELECT id, name, hashtag, description, max_participants, owner_id, last FROM giveaway WHERE id = :giveaway_id');
        $sth->bindParam(':giveaway_id', $giveaway_id);
        $sth->execute();
        $giveaway = $sth->fetch();
        $sth = null;

        // Get how many users joined the giveaway
        $sth = $this->pdo->prepare('SELECT COUNT(*) FROM joined WHERE giveaway_id = :giveaway_id');
        $sth->bindParam(':giveaway_id', $giveaway_id);
        try {
            $sth->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
        $user_joined = $sth->fetch();
        $sth = null;

        // Check if the giveaway has ended
        if ($giveaway['last'] > date('Y-m-d')) {
            // If the user that wants to join hasn't created it
            if ($giveaway['owner_id'] != $this->chat_id) {
                // If there is enough room for the user
                if ($result !== false && ($giveaway['max_participants'] - $result) > 0) {
                    // Add the user to the table "joined"
                    try {
                        $sth = $this->pdo->prepare('INSERT INTO joined (giveaway_id, chat_id) VALUES (:giveaway_id, :chat_id)');
                        $sth->bindParam(':giveaway_id', $giveaway_id);
                        $sth->bindParam(':chat_id', $this->chat_id);
                        $sth->execute();
                        $sth = null;
                        $answer_callback = $this->localization[$this->language]['JoinedSuccess_AnswerCallbackQuery'];
                        return true;
                    } catch (PDOException $e) {
                        echo $e->getMessage();
                        return false;
                    }
                // There is no room
                } else {
                    $answer_callback = $this->localization[$this->language]['NoRoom_AnswerCallback'];
                    return false;
                }
            // The creator is trying to join
            } else {
                $answer_callback = $this->localization[$this->language]['CreatorJoining_AnswerCallback'];
                return false;
            }
        // The giveaway has ended
        } else {
            $answer_callback = $this->localization[$this->language]['GiveawayEnded_AnswerCallback'];
            return true;
        }
    }

    private function showGiveawayPrizes(&$giveaway_id, &$index, $from_browse = false) {
        $sth = $this->pdo->prepare('SELECT * FROM prize WHERE giveaway_id = :giveaway_id');
        $sth->bindParam(':giveaway_id', $giveaway_id);
        try {
           $sth->execute();
        } catch (PDOException $e) {
           echo $e->getMessage();
        }

        $string = '';

        $results = $sth->rowCount();
        if ($results !== 0) {
            // Calc the number the first giveaway to show
            $id = ($index - 1) * OBJECT_PER_LIST + 1;
            $list = intval($results / OBJECT_PER_LIST);
            if (($results % OBJECT_PER_LIST) > 0) {
                $list++;
            }
            $cont = 1;
            $displayed_row = 0;
            $this->inline_keyboard->getCompositeListKeyboard($index, $list, 'awards/' . $giveaway_id . '/' . $from_browse);
            while ($row = $sth->fetch()) {
                if ($displayed_row === 0 && $cont === $id) {
                    $this->getPrizeBrief($row, $string);
                    $displayed_row++;
                } elseif ($displayed_row > 0 && $displayed_row < OBJECT_PER_LIST) {
                    $string .= '::::::::::::::::::::::::::::::::::::::';
                    $this->getPrizeBrief($row, $string);
                    $displayed_row++;
                } elseif ($displayed_row === OBJECT_PER_LIST) {
                    break;
                } else {
                    $cont++;
                }
            }

            $sth = null;

            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Back_Button'], 'callback_data' => 'giveawayshow_' . $giveaway_id . '_' . $from_browse]);
            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
        }
        return $string;
    }

    private function getPrizeBrief(&$row, &$string) {
        $string .= '<b>' . $row['name'] . '</b>' . NEWLINE .
                $this->localization[$this->language]['PrizeValue_Msg'] . $row['currency'] . $row['value'] . ' | ' .
                mb_substr($this->localization[$this->language]['Type' . $row['type'] . '_Button'], 2) . NEWLINE;
    }

    // Get the giveaway to show for a specific index
    // Plus create the keyboard to navigate and add the buttons under it
    private function &getGiveawayList($index, &$string) {
        // Get all the giveaway that the user has joined or created
        $sth = $this->pdo->prepare('SELECT id, name, owner_id, description, type, hashtag, last FROM giveaway WHERE owner_id = :chat_id AND last > NOW()
                                            UNION
                                                SELECT DISTINCT giveaway.id, giveaway.name, giveaway.owner_id, giveaway.description, giveaway.type, giveaway.hashtag, giveaway.last
                                                    FROM giveaway INNER JOIN joined
                                                    ON giveaway.id = joined.giveaway_id AND giveaway.last > NOW() AND joined.chat_id = :chat_id
                                                ORDER BY last DESC');
        $sth->bindParam(':chat_id', $this->chat_id);
        try {
            $sth->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
        $results = $sth->rowCount();
        if ($results !== 0) {
            // Put the bot name at the start
            $string = $this->bot->localization[$this->language]['Bot_Title'] . NEWLINE;
            // Calc the number the first giveaway to show
            $id = ($index - 1) * OBJECT_PER_LIST + 1;
            $list = intval($results / OBJECT_PER_LIST);
            if (($results % OBJECT_PER_LIST) > 0) {
                $list++;
            }
            $cont = 1;
            $giveaway_buttons = [];
            $displayed_row = 0;
            $this->inline_keyboard->getCompositeListKeyboard($index, $list, 'list');
            while ($row = $sth->fetch()) {
                if ($displayed_row === 0 && $cont === $id) {
                    $this->getGiveawayBrief($row, $string);
                    $this->getGiveawayButton($row, $giveaway_buttons, $index);
                    $displayed_row++;
                } elseif ($displayed_row > 0 && $displayed_row < OBJECT_PER_LIST) {
                    $string .= '::::::::::::::::::::::::::::::::::::::';
                    $this->getGiveawayBrief($row, $string);
                    $this->getGiveawayButton($row, $giveaway_buttons, $index);
                    $displayed_row++;
                } elseif ($displayed_row === OBJECT_PER_LIST) {
                    break;
                } else {
                    $cont++;
                }
            }
            $buttons_number = count($giveaways_button);
            switch ($buttons_number) {
                case 0:
                    $this->inline_keyboard->addLevelButtons($giveaway_buttons[0]);
                    break;
                case 1:
                    $this->inline_keyboard->addLevelButtons($giveaway_buttons[0], $giveaway_buttons[1]);
                    break;
                case 2:
                    $this->inline_keyboard->addLevelButtons($giveaway_buttons[0], $giveaway_buttons[1], $giveaway_buttons[2]);
                    break;
            }
            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
            return true;
        // No giveaways to show
        } else {
            return false;
        }
    }

    private function getGiveawayButton(&$giveaway, &$buttons, &$index) {
        if ($giveaway['hashtag'] !== 'NULL') {
            $button_text = $giveaway['hashtag'];
        } else {
            $button_text = $giveaway['name'];
        }
        array_push($buttons, [
                'text' => $button_text,
                'callback_data' => 'giveawayshow_' . $giveaway['id'] . '_' . $index
        ]);
    }

    // Get a string with all the important data of a giveaway
    private function &getGiveawayBrief(&$giveaway, &$message, $joining = false) {
    // Add name (putting bold formattation and removing the bold for usernames),
    // type and hashtag (if it is set)
        $message .= '<b>' . $this->removeUsernameFormattation($giveaway['name'], 'b') . '</b>' . NEWLINE .
            '<code>' . $this->localization[$this->language][$giveaway['type'] . '_AnswerCallback'] . '</code>';

        if ($giveaway['hashatag'] !== 'NULL') {
            $message .= ' | #' . $giveaway['hashtag'];
        }

        // Get the value for each prize
        $sth = $this->pdo->prepare('SELECT value, currency FROM prize WHERE giveaway_id = :giveaway_id');
        $sth->bindParam(':giveaway_id', $giveaway['id']);
        try {
            $sth->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        // Get the number of the prizes
        $prizes_number = $sth->rowCount();
        $value = 0;
        $currency = 'NULL';

        while ($prize = $sth->fetch()) {
            // Add them to get how many money is the giveaway giving away
            $value += $prize['value'];
            // If the currency hasn't been set yet
            if ($currency === 'NULL') {
                $currency = $prize['currency'];
            }
        }

        $sth = null;

        $message .= NEWLINE . $prizes_number . ' ' . $this->localization[$this->language]['PrizesNumber_Msg'] . ' | ' . $this->localization[$this->language]['Value_Msg'] . $value . NEWLINE;

        // Check the relation with this giveaway (owned/joined)
        if ($giveaway['owner_id'] === $this->chat_id) {
            $message .= $this->localization[$this->language]['Owned_Msg'];
        } elseif (!$joining) {
            $message .= $this->localization[$this->language]['Joined_Msg'];
        }

        // Has it ended?
        if (date('Y-m-d') !== $giveaway['last']) {
            // Get days remaining
            $time_left = (strtotime(date('Y-m-d')) - strtotime($giveaway['last'])) / 3600 / 24;
            $message .= ' | ' . $left . ' ' . $this->localization[$this->language]['Days_Msg'] . NEWLINE;
        } else {
            $message .= ' | ' . $this->localization[$this->language]['LastDay_Msg'] . NEWLINE;
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

    // Passing an id, name or hashtag,
    // this method will take the giveaway from the db and get the data in a string
    // plus it will set the keyboard and set the status on redis
    private function &showGiveaway($target, $error_message = '', $list_button = false) {

        static $query = 'SELECT id, name, hashtag, description, max_participants, owner_id, type FROM giveaway WHERE last > NOW() AND ';

        // This method accepts various kinds of information in order
        // to search for the right giveaway so we need check it.
        // We received the id
        if (is_numeric($target) == 1) {
            $sth = $this->pdo->prepare($query . 'id = :id');
            $sth->bindParam(':id', $target);
        // We received the name
        } elseif ($target[0] != '#') {
            $sth = $this->pdo->prepare($query . 'name = :name');
            $sth->bindParam(':name', $target);
        // We received an hashtag
        } else {
            $sth = $this->pdo->prepare($query . 'hashtag = :hashtag');
            $sth->bindParam(':hashtag', mb_substr($target, 1));
        }

        try {
            $sth->execute();
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
        $giveaway = $sth->fetch();
        $sth = null;

        if ($giveaway !== false) {
            // Check if the user has joined already the giveaway
            $sth = $this->pdo->prepare('SELECT COUNT(*) FROM joined WHERE chat_id = :chat_id AND giveaway_id = :giveaway_id');
            $sth->bindParam(':chat_id', $this->chat_id);
            $sth->bindParam(':giveaway_id', $this->chat_id);
            $sth->execute();
            $result = $sth->fetch();
            $sth = null;

            $message = '';

            // The user hasn't joined it and he isn't the creator
            if ($result == false && $giveaway['owner_id'] !== $this->chat_id) {
                // Get a string with all the info of the giveaway
                $this->getGiveawayBrief($giveaway, $message, false);

                //Show the first 3 giveaway prizes
                $sth = $this->pod->prepare('SELECT name FROM prize WHERE giveaway = :giveaway_id LIMIT 4');
                $sth->bindParam(':giveaway_id', $giveaway['id']);
                $sth->execute();

                $count = 0;

                while ($prize = $sth->fetch()) {
                    if ($count === 4) {
                        $message .= NEWLINE . $this->localization[$this->language]['OtherPrize_Msg'];
                    } else {
                        $message .= NEWLINE . $prize['name'];
                    }
                }

                $sth = null;

                $this->inline_keyboard->addLevelButtons([
                            'text' => $this->localization[$this->language]['Join_Button'],
                            'callback_data' => 'join_' . $giveaway['id']
                        ],
                        [
                            'text' => $this->localization[$this->language]['Cancel_Button'],
                            'callback_data' => 'hide_join_button'
                        ]);

                $this->redis->set($this->chat_id . ':status', SHOW_GIVEAWAY_DETAILS);

            } else {
                $this->getGiveawayBrief($giveaway, $message, false);

                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['BrowsePrize_Button'], 'callback_data' => 'awards/' . $giveaway['id'] . '/' . $list_button . '/1']);

                $new_query = $giveaway['hashtag'] !== 'NULL' ? $giveaway['hashtag'] : $giveaway['name'];

                $this->inline_keyboard->addLevelButtons([
                        'text' => &$this->localization[$this->language]['ShareLink_Button'],
                        'switch_inline_query' => $new_query
                    ],
                    [
                        'text' => &$this->localization[$this->language]['ShowLink_Button'],
                        'callback_data' => 'invite_' . $giveaway['id']
                ]);

                // If we want the back button to return to giveaway browse
                if ($list_button != false) {
                    $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['Back_Button'], 'callback_data' => 'list/' . $list_button]);
                }

                $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);

                $this->redis->set($this->chat_id . ':status', SHOW_GIVEAWAY_DETAILS);
            }
        } else {
            $message = $this->localization[$this->language]['GiveawayNotExists_Msg'] . NEWLINE . $error_message;
            $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Menu_Button'], 'callback_data' => 'menu']);
        }
        return $message;
    }

    // Generate the referral link for the given giveaway (ID)
    private function generateReferralLink($giveaway, $title, $callback_query) {
        $link = "telegram.me/giveaways_bot?start=".base64_encode($this->chat_id)."_"
                                                  .base64_encode($giveaway);

        // Generate a message such as the following:
        //
        // Join TestGiveaway:
        // telegram.me/giveaways_bot?start=XNN31NKQKMNE==_AQ21n==
        $message = $this->localization[$this->language]['JoinLabel_Msg']
                   . '<b>' . $this->removeUsernameFormattation($title, 'b') . '</b>' . ':' . NEWLINE . NEWLINE . $link;

        if ($callback_query) {
            $message_id = $this->update['callback_query']['message']['message_id'];

            $this->editMessageReplyMarkup($message_id, $this->inline_keyboard->getKeyboard());
            $this->editMessageText($message, $message_id);
        } else {
            $this->sendMessage($message);
        }
    }

    private function getStartKeyboard() {
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Register_Button'], 'callback_data' => 'register'], ['text' => &$this->localization[$this->language]['Join_Button'], 'callback_data' => 'join']);
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Show_Button'], 'callback_data' => 'show']);
        if ($this->language == 'en') {
            $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['Language_Button'], 'callback_data' => 'options']);
        } else {
            $this->inline_keyboard->addLevelButtons(['text' => $this->localization[$this->language]['Language_Button'] . '/Language', 'callback_data' => 'options']);
        }
        $this->inline_keyboard->addLevelButtons(['text' => &$this->localization[$this->language]['Help_Button'], 'callback_data' => 'help'], ['text' => &$this->localization[$this->language]['About_Button'], 'callback_data' => 'about']);

        return $this->inline_keyboard->getKeyboard();
    }

    private function getStartLanguageKeyboard() {
        foreach ($this->localization['languages'] as $key => $message) {
            $this->inline_keyboard->addLevelButtons(['text' => $message, 'callback_data' => 'cls/' . $key]);
        }
        return $this->inline_keyboard->getKeyboard(); 
    }

    private function checkPrizeValue(&$text, &$money, &$currency, &$money_inserted, &$currency_inserted) {
        $money_inserted = false;
        $currency_inserted = false;
        $text = str_replace(',', '.', $text);
        $preg_value = preg_split('/(?<=\d)(?=[' . CURRENCY . '])/', $text);
        $money = floatval($preg_value[0]);
        if ($money != 0) {
            $money_inserted = true;
            preg_match('/[' . CURRENCY . '=*]+/', $preg_value[1], $currency);
            $currency = $currency[0];
            if (strpos(CURRENCY, $currency) !== false) {
                $currency_inserted = true;
            }
        } else {
            $money = mb_substr($preg_value[0], 1);
            $money = floatval($money);
            if ($money != 0) {
                $money_inserted = true;
                $currency = mb_substr($preg_value[0], 0, 1);
                if (strpos(CURRENCY, $currency) !== false) {
                    $currency_inserted = true;
                }
            }
        }
    }

    public function &removeUsernameFormattation(&$string, $modificator) {
        $usernames = FALSE;
        preg_match_all('/(@\w+)/u', $string, $matches);
        if ($matches) {
            $usernamesArray = array_count_values($matches[0]);
            $usernames = array_keys($usernamesArray);
        }
        $count = count($usernames);
        $delimitator_start = '<' . $modificator . '>';
        $delimitator_end = '</' . $modificator . '>';
        for($i = 0; $i < $count; $i++) {
            $string = str_replace($usernames[$i], $delimitator_end . $usernames[$i] . $delimitator_start, $string);
        }
        return $string;
    }
}
