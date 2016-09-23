<?php

/*
 * Hi, i'm GiveawayssBot and i'm here to create and manage giveaways on Telegram.
   Click on /start to show what i can do.
 * start - Show the menu
 * register - Create a new giveaway
 * show - Show a giveaway associated with a particular hashtag
 * stats - Show all your ongoing and won giveaway
 * help - Get help using me
 * about - Get info about me and my creators
 */ 


$localization = [];

$localization['languages'] = [
    'en' => 'English',
    'it' => 'Italian'
    ];
$localization['languages'] = [
    'en' => 'English',
    'it' => 'Italian'
    ];

$localization['en'] = [
    'Welcome_Msg' => "Welcome to GiveawayBot 🎰.
Choose a language to begin:",
    'Menu_Msg' => 'GiveawayBot let you create, join and manage giveaways on Telegram.
Select an option:',
    'Join_Msg' => 'Enter the giveaway hashtag you want join:',
    'Help_Msg' => 'These instructions will <b>guide you to use me.</b>

A giveaway is a promotion or contest in which prizes are given away.
This bot provides <b>two types</b> of giveaways:
<code>JoinIn</code>: Each member has the same chance of winning.
<code>ShareIt</code>: Each member increases chance of winning inviting other people to the giveaway.

You only have to join one of them for a chance to win great prizes: click on an invite link or press Join button and follow the instructions.
If you joined it, look the details by pressing <code>Browse</code> button on the menu. Anyway if you won you\'ll receive a notification with your prize.
If you want to create a giveaway, press <code>Create</code> button in the menu and follow the instructions; at the end you\'ll receive the link to share it. The winners will be chosen randomly and the bot will send them a notification containing the key.
Click /start to return to the menu.
If you experience problems click /start to reset the bot or contact @danyspin97.

<b>WE DO NOT PROVIDE WARRANTY FOR THE GIVEAWAYS. ONLY THE OWNER ADDS THE KEYS OF THE PRIZES AND WE DO NOT PROVIDE RELIABILITY FOR WHO USE THIS BOT.</b>
<i>Thank you for your attention and good luck.</i>
<code>WiseDragonStd</code>🐉',
    'About_Msg' => 'This bot is written in php and ruby. It has been developed by <code>WiseDragonStd</code>🐉 team and uses HadesWrapper. 
The code will be released as open source in early 2017.
For updates follow @WiseDragonStd channel.',
    'Register_Msg' => 'Welcome to the giveaway setup. Select one of the following types:
<code>JoinIn</code>: Each member has the same chance of winning.
<code>ShareIt</code>: Each member increases chance of winning inviting other people to the giveaway.',
    'EnteringTitle_Msg' => 'Enter the giveaway title:',
    'EnteringHashtag_Msg' => 'Enter a hashtag for this giveaway or skip. A giveaway can be joined inserting the hashtag after pressing <code>Join</code> button.',
    'EnteringDescription_Msg' => 'Enter a description for the giveaway:',
    'EnteringMaxparticipants_Msg' => 'Enter the MAX members allowed to join or skip for no limits:',
    'EnteringDate_Msg' => 'Enter the life (in days) of your giveaway:
(Enter a number between 3-40)',
    'EnteringPrizeName_Msg' => 'Enter the prize name:',
    'EnteringPrizeValue_Msg' => 'Enter how much this prize worth in the form 29.99€. You can use your own currency, if it isn\'t allowed you will choose it from a list:',
    'EnteringPrizeType_Msg' => 'Select prize type:',
    'EnteringPrizeCurrency_Msg' => 'Select a currency:',
    'EnteringPrizeKey_Msg' => 'Enter a key for this prize. You cannot modify the key after entering it, so please pay attention.
This Key will be sent to winner at the end of the giveaway.
It is securely stored on a database.',
    'EditTitle_Msg' => 'Enter the new <b>title</b>:',
    'EditHashtag_Msg' => 'Enter the new <b>hashtag</b>:',
    'EditDescription_Msg' => 'Enter the new <b>description</b>:',
    'EditDate_Msg' => 'Enter the new duration of the giveaway:
(Enter a number between 3-40)',
    'EditPrizeName_Msg' => 'Enter a new prize name:',
    'EditPrizeType_Msg' => 'Enter a new prize type:',
    'Title_Msg' => '<b>Title: </b>',
    'Hashatag_Msg' => '<b>Hashtag: </b>',
    'Maxparticipants_Msg' => '<b>MAX members allowed: </b>',
    'Description_Msg' => '<b>Description: </b>',
    'Date_Msg' => '<b>Date: </b>',
    'NewTitle_Msg' => '<b>New title: </b>',
    'NewHashtag_Msg' => '<b>New hashtag: </b>',
    'NewDescription_Msg' => '<b>New description: </b>',
    'NewDate_Msg' => '<b>New date: </b>',
    'PrizeName_Msg' => '<b>Prize name: </b>',
    'PrizeValue_Msg' => '<b>Value: </b>',
    'ValueNoCurrency_Msg' => '<b>Incomplete value: </b>',
    'PrizeKey_Msg' => '<b>Key: </b>',
    'PrizeType_Msg' => '<b>Type: </b>',
    'NewValue_Msg' => '<b>New value: </b>',
    'NewValueNoCurrency_Msg' => '<b>New incomplete value: </b>',
    'EditPrizeCurrency_Msg' => '<b>New value: </b>',
    'TitleLenght_Msg' => 'The title inserted is too short. Please insert a new one longer than 5 characters.',
    'ValidHashtag_Msg' => 'The hashtag inserted is not valid, please re-insert it.',
    'DuplicatedHashtag_Msg' => 'The hashtag entered is already used, please enter a new one.',
    'HashtagSkipped_Msg' => '<b>Hashtag:</b> <i>Skipped</i>.',
    'DescriptionSkipped_Msg' => '<b>Description:</b> <i>no description</i>.',
    'MaxparticipantsNotValid_Msg' => 'The MAX members allowed is not valid. Please insert a new one.',
    'MaxparticipantsInfinite_Msg' => '<b>Max members:</b> <i>no limit</i>.',
    'DateNotValid_Msg' => 'The date inserted is not valid, please insert a number between 3-40.',
    'ValueNotValid_Msg' => 'Please insert a valid value.',
    'NewValueNotValid_Msg' => 'The new value is not valid, please insert a new one.',
    'CancelGiveawayPrompt_Msg' => 'Are you really sure you want to cancel the setup of this giveaway?
It cannot be undone and all data inserted will be lost.',
    'ShowHashtagMissing_Msg' => 'You should insert the giveaway hashtag:',
    'ClosedGiveawayWarn_Msg' => '<b>The requested giveaway is closed.</b>',
    'NoGiveawayWarn_Msg' => '<b>Giveaway not found</b>',
    'MaxParticipants_Msg' => 'Sorry but the giveaway has reached the MAX member allowed.',
    'JoinedSuccess_Msg' => 'You joined this giveaway!',
    'CancelSuccess_Msg' => 'You deleted the giveaway  ¯\_(ツ)_/¯',
    'MissingHashtagWarn_Msg' => 'You should insert an hashtag:',
    'StatsEmpty_Msg' => 'Sorry, you didn\'t join giveaways',
    'Value_Msg' => 'For a value of ',
    'Owned_Msg' => '<code>Owned</code>',
    'Joined_Msg' => '<code>Joined</code>',
    'Closed_Msg' => '<code>Closed</code>',
    'LastDay_Msg' => '<code>Last day</code>',
    'UserError_Msg' => '<b>The giveaway or the user you\'re searching for doesn\'t exist.</b>',
    'AlreadyIn_Msg' => '<b>You already joined the giveaway.</b>',
    'ReferralLink_Msg' => 'This is your referral link, copy it and share with your friends:',
    'AfterCreation_Msg' => 'Giveaway successfully created!',
    'Hash_Msg' => '<b>Hashtag: </b>',
    'Type_Msg' => '<b>Type: </b>',
    'MPValue_Msg' => '<b>MAX members allowed: </b>',
    'EndDate_Msg' => '<b>End date: </b>',
    'Desc_Msg' => '<b>Description: </b>',
    'GiveawayPrizes_Msg' => '<b>Number of prizes: </b>',
    'Undefined_Msg' => '<i>Not defined</i>',
    'TotalValue_Msg' => '<b>For a value of: </b>',
    'JoinLabel_Msg' => 'Join ',
    'Unlimited_Msg' => '<i>Unlimited</i>',
    'Inception_Msg' => '<b>You created this giveaway. You can\'t join it ;)</b>',
    'NowLabel_Msg' => ' now and win wonderful prizes!',
    'UnavailableDesc_Msg' => 'No description found',
    'Days_Msg' => 'days',
    'Menu_Button' => '📜 Menu',
    'Register_Button' => '🖍 Create',
    'Show_Button' => '🕹 Browse',
    'Language_Button' => '🌐 Language',
    'standard_Button' => '➡️ JoinIn',
    'cumulative_Button' => '↪️ ShareIt',
    'Back_Button' => '🔙 Back',
    'Skip_Button' => '🔜 Skip',
    'Confirm_Button' => '✔️ Confirm',
    'Infinite_Button' => '⚪️ No limit',
    'EditTitle_Button' => '📝 Edit title',
    'EditHashtag_Button' => '#⃣ Edit hashtag',
    'EditMaxparticipants_Button' => '🔢 Edit members',
    'EditDescription_Button' => '📝 Edit description',
    'EditDate_Button' => '📅 Edit date',
    'DeleteHashtag_Button' => '❎ Delete hashtag',
    'DeleteDescription_Button' => '❎ Delete description',
    'AddHashtag_Button' => '#⃣ Add hashtag',
    'AddDescription_Button' => '📝 Add description',
    'AddMaxparticipants_Button' => '🔢 Add members limit',
    'ConfirmGiveaway_Button' => '✔️ Confirm',
    'ConfirmPrizes_Button' => '✔️ Confirm prizes',
    'AddPrize_Button' => '➕ Add prize',
    'CancelGiveaway_Button' => '✖️ Cancel giveaway',
    'EditPrizeName_Button' => '📝 Edit name',
    'EditPrizeType_Button' => '🎲 Edit type',
    'EditPrizeValue_Button' => '💲 Edit value',
    'EditPrizeCurrency_Button' => '💱 Edit currency',
    'DeletePrize_Button' => '❌ Delete',
    'Prizes_Button' => '🎁 All prizes',
    'Type1_Button' => '🎮 Videogame key',
    'Type2_Button' => '📃 Coupon',
    'Type3_Button' => '📄 Gift Card',
    'Type4_Button' => '🔑 Other',
    'Join_Button' => '#⃣ Join',
    'Cancel_Button' => '🚫 Cancel',
    'Help_Button' => '*⃣ Help',
    'About_Button' => 'ℹ️ About',
    'Source_Button' => '📖 Source',
    'Updates_Button' => '❕ Updates',
    'ClosedGiveaway_Msg' => '<b>The requested giveaway is closed.</b>',
    'standard_AnswerCallback' => 'JoinIn',
    'cumulative_AnswerCallback' => 'ShareIt',
    'HashtagSkipped_AnswerCallback' => 'Hashtag skipped',
    'DescriptionSkippet_AnswerCallback' => 'No description',
    'MaxparticipantsInfinite_AnswerCallback' => 'No limit',
    'Language_AnswerCallback' => 'Choose language',
    '€_AnswerCallback' => 'Euro',
    '$_AnswerCallback' => 'Dollar',
    'Register_InlineQuery' => 'Register',
    'SwitchPM_InlineQuery' => 'Create or browse',
];

$localization['it'] = [
    'Register_Button' => 'Crea',
    'Show_Button' => 'Sfoglia',
    'Language_Button' => 'Cambia Lingua',
    'UserRegistred_AnswerCallback' => 'Registrato',
    'Menu_Msg' => 'GiveawayBot ti permette di creare e parteciapre a giveaway senza uscire da Telegram.
Per iniziare premi uno dei seguenti pulsanti oppure /help per ricevere aiuto.',
    'Register_Button' => 'Crea',
    'Show_Button' => 'Sfoglia',
    'Options_Button' => 'Opzioni',
    'ShowHashtagMissing_Msg' => 'Devi specificare l\'hashtag del giveaway:',
    'Owned_Msg' => 'Creato',
    'Joined_Msg' => 'Partecipo',
    'Closed_Msg' => 'Chiuso',
    'Back_Button' => 'Indietro',
    'LastDay_Msg' => 'Ultimo giorno',
    'Days_Msg' => 'giorni',
    'ClosedGiveaway_Msg' => '<b>Il giveaway richiesto è chiuso.</b>',
    'NoGiveawayWarn_Msg' => '<b>Giveaway non trovato</b>',
    'Join_Button' => 'Partecipa',
    'Cancel_Button' => 'Annulla',
    'MaxParticipants_Msg' => 'Il giveaway ha raggiunto il numero massimo di partecipanti, impossible partecipare.',
    'JoinedSuccess_Msg' => 'Hai preso parte a questo giveaway! ',
    'CancelSuccess_Msg' => 'Hai deciso di non partecipare ¯\_(ツ)_/¯',
    'MissingHashtagWarn_Msg' => 'Devi specificare un hashtag:',
    'StatsEmpty_Msg' => 'Non stai partecipando a nessun giveaway',
    'Value_Msg' => 'Per un valore di ',
    'UserError_Msg' => '<b>Il giveaway o l\'utente a cui si fa riferimento non esistono.</b>',
    'AlreadyIn_Msg' >= '<b>Stai già partecipando al giveaway.</b>',
    'ReferralLink_Msg' => 'Ecco qui il tuo link d\'invito, copialo e invialo ai tuoi amici:',
    'AfterCreation_Msg' => 'Giveaway creato con successo!',
    'Hash_Msg' => '<b>Hashtag: </b>',
    'Title_Msg' => '<b>Titolo: </b>',
    'Type_Msg' => '<b>Tipo giveaway: </b>',
    'MPValue_Msg' => '<b>Limite partecipanti: </b>',
    'EndDate_Msg' => '<b>Data di chiusura: </b>',
    'Desc_Msg' => '<b>Descrizione: </b>',
    'GiveawayPrizes_Msg' => '<b>Premi: </b>',
    'Undefined_Msg' => '<i>Non definito</i>',
    'TotalValue_Msg' => '<b>Per un valore di: </b>',
    'JoinLabel_Msg' => 'Partecipa a ',
    'Unlimited_Msg' => '<i>Illimitato</i>',
    'Inception_Msg' => '<b>Non puoi partecipare a un giveaway da te creato.</b>',
    'NowLabel_Msg' => ' subito e vinci fantastici premi!',
    'UnavailableDesc_Msg' => 'Nessuna descrizione disponibile',
    'Prizes_Button' => 'Tutti i premi',
    'Type1_Button' => 'Videogioco',
    'Type2_Button' => 'Coupon',
    'Type3_Button' => 'Gift Card',
    'Type4_Button' => 'Altro',
    'AddPrize_Button' => 'Aggiungi premio',
    'EditPrizeName_Button' => 'Modifica nome',
    'EditPrizeType_Button' => 'Modifica tipo',
    'EditPrizeValue_Button' => 'Modifica valore',
    'EditPrizeCurrency_Button' => 'Modifica moneta',
    'DeletePrize_Button' => 'Cancella'
];

