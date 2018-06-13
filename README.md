Nothing here but chickens yet. Stay tuned.

## Features

Main purpose of the bot - urgent alerts about community member hard situations. As example - car break in the middle of nowhere or some accidents with injuries and so on. If you need some help from local community, where you engaged - this will be pretty useful.

Bot utilize regular user telegram account with real phone number, so it behave lile a normal user and can send direct messages,but haven't ability to use bot feautres like buttons. 

For now bot can:

1. Hold in internal JSON database status of desired alert level ( Alerts with Telegram messages only / With SMS / With Calls)
2. Create alert lists with text messages, adressed to bot with @ and ask to confirm sending of this message.
3. Create urgent alerts, if bot is mentioned with direct appeal, but not message provided (urgent alert, when you can't type)
4. Detect location / audio / video as agree response to send messages and attach additional media to Telegram alert messages.
5. Create urgent alert lists if location / audio / video was sended directly to bot user (not through channel)
6. Postpone new alert attempts if alert already created by user.
7. Autonomously log in to Telegram network.

## Services used

1. Telegram with regular user API.
2. MadelineProto project as transport between Telegram and PHP Daemon.
3. Nexmo service as calls provider.
4. Twilio service as provider for SMS sending and speech recognition service. Also Twillio numbers used as main bot number.
5. Bit.ly to generate short urls in SMS messages.

## Installation

This project build on top of PHP stack with Composer.

Needed software requirements:

1. PHP 7.1+ (Bot was build on 7.1.17 version)
2. Composer (1.0.0-alpha was used)
3. php-pcntl, php-curl, php-mbstring extensions

Register Twilio phone number (USA or UK preferred, it must be Calls / SMS capable).

Register Nexmo phone number for outgoing calls.

Register new Telegram account with BlueStacks virtual android tablet to Twilio phone number.

Register new Telegram API token.

Run `composer install` from root directory to install needed libraries and requirements.

Copy config.example.json to config.json, fill them with data from service providers.

Run `php ./Daemon.php` from root directory. If everything is right - bot will start authorization process.

## Telegram authorization process

Telegram itself authorize new clients with simple 5-digit codes which it send to first registered telegram client, or via SMS, or ditate it in Call. 

> NOTE! But as many services - Telegram send SMS codes via external SMS gateway and Gateway-to-Gateway SMS messages are not possible dut to providers limitations. You can receive SMS oto yor number only from real devices, but Telegram not uses real numbers. That's why we came to such difficult procedre.

To configure automatic logging in you will need to spend somme effort and configure speech recognition.

1. Make sure files from /public folder of this repo is accessible from external internet.
2. Fix config_twilio_speech_url variable in config.json to point speech.php script
3. Configure your Twilio number to consume gather.php script as sorce of TwiML for incomming calls.

After all this you must be able to authenticate your client autonomosly without reading and entering codes by yourself.

So, how we achieve this?

1. Bot ask Telegram for Authorization.
2. Telegram send 5-digit code to athenticated Application.
3. Sure we don't answer, becase we haven't access too this athenticated Application from Bot environment. Bot will wait for 2 minutes.
4. After pause Bot will ask Telegram to send SMS.
5. Telegram sends SMS with 2-minutes timeout.
6. Bot haven't ability to read this incoming SMS, so it force Telegram to call on registered number.
7. Telegram call Twilio number and speech authorization code.
8. Twilio consume gather.php TwiML script and receive command to convert speech to text and send it to speech.php script.
9. Speech.php parse texted message from Telegram, extract authorization code and put it into file.
10. After all timeouts Bot tracks prescence of athorization code and enter it.
11. This is it. Auth done.

## Alerts

Bot have 2 virtual level of alerts

1. Regular alerts. This alerts sended to Bot in public chat via mention (with @ symbol) and requires confirmation to create new messages list.
2. Urgent alerts. This alert levels do not require any confirmation, but need special media to be sent.

## Regular alerts creation

To create regular alert type bot mention with @ and type mssage after this mention.

Bor will ask for confirmation.

Answer to bot message with comment or with @ mention. Answer must be '+' or 'yes' (this is confiurable in locale.json)

After that bot will confirm list creation

## Urgent alerts in dialogs and preferred chat. Urgent alerts creation.

Sometimes situation can happen when you will not be able to type message, but instead can dictate something, send location or video message. for this purposes bot supports direct messages with Audio / Video / Location types, which will be converted
directly to urgent alert, message with media will be fowarded to preferred chat and messaging will be started.

You need to define `{"preferred_channel":"<int_id>"}` property in config.json file to send urgent alerts only to your most valuable
chat. As soon as boot can support many chats - urgeng messages from dialogs with bot will be sended only to preffered, or not sended at all if preferred chat is not set.

To create Urgent alert:

Ping bot with it username via @ in chat / Send location / Video / Audio media directly to bot in private dialog.

If bot consume media from private dialogs - it will forward this media to public chat and after reforward this media to every participant of chat.

## User settings

Simple user settings can be defined with direct messages to bot.

Available commands:

`settings` - review current settings, will return status of false possilibity to send sms and call. Otherwise will show status of current settings.

`sms` - command to enable SMS messaging from bot.

`no sms` - command to disable SMS messaging from bot.

`calls` - command to enable direct phone calls from bot.

`no calls` - command to disable direct phone calls from bot.

> NOTE! SMS / Calls is unaccessible while bot phone number is not in your phone contact list (exactly phone contacts, not telegram).
> To receive SMS and Calls please add bot number to your phone contacts, this will made your phone number available to bot.

> NOTE! SMS / Calls settings is on by default.

## Multi language support

Different locales with string text templates can be defined in "locale.json" configuration file. You can find it in root tree of repo.

locale is set in config.json file with property 'locale'
``{ "locale": "en_US" }``

You can get use any language-related text template after with LanguageTemplate object. Arguments utilized in same manner, as in `sprintf()` php function.

```php
use Eugenia\Misc;
...
$textVal = Misc\LangTemplate::getInstance()->get('bot_alert_ended_report', $alertObj->tg_count, $alertObj->sms_count, $alertObj->call_count, $alertObj->fail_count)
```

Locale file structure example:
```JSON
{
    "ru_RU": {
        "string": "%s %d string example",
        "multiline_string": [
            "%s first multiline\n",
            "second multiline %d"
        ],
        "array": {
            "0": "first ele",
            "1": "second ele"
        }
    }
    "en_US": {
    }
}
```

Main principals of string defines in `locale.json`:

1. Simple strings JSON fields will be utilized as string templates.
2. JSON array will be used as merged string (declared for big text chunks with multiple lines, like help text).
3. JSON objects will be used as simple arrays without keys maintain.

Plural forms currently not supported.


## Updates log:

1. Help status message implemented with simple instructions how to use this bot.

2. Locatiom answer type introduced as addenum to message.

3. DEBUG env variable introduced. If it present and == true - all messages will be sent only to developer.

Debug launch command: `DEBUG=true php Daemon.php`

4. Timeout variables for alerts and mentions introduced.

5. Fixed failed count in reports.

6. Simple multilanguage implemented.