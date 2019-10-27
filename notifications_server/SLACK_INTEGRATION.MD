1. Create app via https://api.slack.com/apps page 

2. After that we may configure Signing Secret on our app

2. Create BOT, enable Event subscription, configure Permissions

NOTE: Incoming Webhooks, Interactive Components, Slash commands is not used

4. Open Event Subscription and use url https://sip.loc:4430 for Request URL

* Click on Subscribe to workspace events
* create following events:
 ** message.channels
 ** message.im
 
5. Open OAuth & Permissions 
* configure following Permissions:
 ** chat:write:user
 ** chat:write:bot
 ** im:history
 ** channels:history

* Configure Restrict API Token Usage with ip address which should have access to slack API
