const fs = require('fs');
const yaml = require('js-yaml');
const http = require('http');
const url = require('url');
const log4js = require('log4js');
const Netmask = require('@dschenkelman/netmask').Netmask;

const { WebClient } = require('@slack/client');
const { createEventAdapter } = require('@slack/events-api');

const Telegraf = require('telegraf');
const Telegram = require('telegraf/telegram');


/* Common settings */

var logger = log4js.getLogger();

logger.level = 'info';

const api_bindhost = "0.0.0.0";
const api_bindport = 3001;

const allowed_subnets = [
	'185.181.228.3/32',
	'185.181.228.32/26',
	'185.181.229.32/26',
	'185.181.230.192/27',
	'89.28.42.226',
	'127.0.0.1'
];

/* 
 * Slack configuration 
 */

// An access token (from your Slack app or custom integration - xoxa, xoxp, or xoxb)
const token = '';

// Initialize using signing secret from environment variables
const slackEvents = createEventAdapter('');

// port for http listener for slack events
const port = 3002;

/* Telegram configuration */
const telegram_token = '';

/* 
 * slack code part 
 */

const web = new WebClient(token);

// Attach listeners to events by Slack Event "type". See: https://api.slack.com/events/message.im
slackEvents.on('message', (event) => {
	logger.info(`[slack] Received a message event: user ${event.user} in channel ${event.channel} says ${event.text}`);
	if(event.text == 'myid') {
		web.chat.postMessage({ channel: event.channel, text: 'chat id is '+event.channel }).then((res2) => {
			// `res` contains information about the posted message
			logger.debug('Message sent: ', res2.ts);
		}).catch(console.error);
	}
});

// Handle errors (see `errorCodes` export)
slackEvents.on('error', console.error);

// Start a basic HTTP server
slackEvents.start(port).then(() => {
	logger.info(`[slack] Event listener running at http://0.0.0.0:${port}/`);
});

/* 
 * telegram code part 
 */

if(telegram_token !== '') {
	const bot = new Telegraf(telegram_token);
	const telegram = new Telegram(telegram_token);

	bot.on('message', (ctx) => {
		var msg = ctx.update.message;
		logger.info('[telegram] Received a message event: user '+msg.from.username+' in group '+msg.chat.title+' ('+msg.chat.id+') says '+msg.text);
		if(ctx.update.message.text == "/myid") {
			ctx.reply('chat id is '+ctx.update.message.chat.id);
		}
	});
	bot.startPolling()
} else {
	logger.error("Telegram token is not configured");
}

/* 
 * common code part 
 */
var allowed_subnets_object = [];

for(var i=0; i < allowed_subnets.length; i++) {
	allowed_subnets_object[i] = new Netmask(allowed_subnets[i]);
}

const api_server = http.createServer((req, res) => {
	logger.debug("Received http request "+req.url);
	res.statusCode = 200;
	res.setHeader('Content-Type', 'text/yaml');
	
	var remote_addr = req.socket.remoteAddress;
	var allowed = false;
	for(var i=0; i < allowed_subnets_object.length; i++) {
		allowed = allowed_subnets_object[i].contains(remote_addr);
		logger.info(remote_addr+' is '+allowed);
		if(allowed==true) {
			break;
		}
	}
	if(allowed==false) {
		res.end();
		logger.info("[http] rejected request from "+remote_addr);
		return;
	}
	
	let url = require('url').parse(req.url, true);
	
	var body = '';
	req.on('data', function (data) {
		body += data;
		
		// Too much POST data, kill the connection!
		// 1e6 === 1 * Math.pow(10, 6) === 1 * 1000000 ~~~ 1MB
		if (body.length > 1e6)
			req.connection.destroy();
	});
	
	req.on('end', function () {
		var body_req;
		try {
			body_req = JSON.parse(body);
		} catch(err) {
			logger.debug(err);
		};
		logger.debug(body_req);
		
		switch(url.pathname) {
			case '/slack_message': {
				try {
					if(!body_req.message || !body_req.channel_id) {
						
					}
				} catch(err) {
					var message = yaml.safeDump({'error':'missing message or channel_id'});
					logger.debug(message);
					res.end(message);
					return;
				}
				

				web.chat.postMessage({ channel: body_req.channel_id, text: body_req.message }).then((res2) => {
					// `res` contains information about the posted message
					logger.debug('Message sent: ', res2.ts);
					res.end(yaml.safeDump(res2));
				}).catch((err) => {
					logger.error(err);
					res.end(yaml.safeDump({'error':'please check logs'}));
				});
				break;
			}
			case '/telegram_message': {
				try {
					if(!body_req.message || !body_req.chat_id) {
						
					}
				} catch(err) {
					var message = yaml.safeDump({'error':'missing message or chat_id'});
					logger.error(message);
					res.end(message);
					return;
				}
				
				if(telegram_token == '') {
					logger.error("Telegram token is not configured");
					res.end();
					break;
				}
				
				telegram.sendMessage(body_req.chat_id, body_req.message);
				res.end();
				break;
			}
			default: {
				res.end();
			}
		}
	});
});

api_server.listen(api_bindport, api_bindhost, () => {
	logger.info(`API server running at http://${api_bindhost}:${api_bindport}/`);
});
