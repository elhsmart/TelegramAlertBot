Nothing here but chickens yet. Stay tuned.

## Updates log:

1. Help status message implemented with simple instructions how to use this bot.

2. Locatiom answer type introduced as addenum to message.

3. DEBUG env variable introduced. If it present and == true - all messages will be sent only to developer.

Debug launch command: `DEBUG=true php Daemon.php`

4. Timeout variables for alerts and mentions introduced.

5. Fixed failed count in reports.

6. Simple multilanguage implemented.

## Urgent alerts in dialogs and preferred chat

Sometimes situation can happen when you will not be able to type message, but instead can dictate something, send location or video message. for this purposes bot supports direct messages with Audio / Video / Location types, which will be converted
directly to urgent alert, message with media will be fowarded to preferred chat and messaging will be started.

You need to define `{"preferred_channel":"<int_id>"}` property in config.json file to send urgent alerts only to your most valuable
chat. As soon as boot can support many chats - urgeng messages from dialogs with bot will be sended only to preffered, or not sended at all if preferred chat is not set.

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