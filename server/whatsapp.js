import { Boom } from '@hapi/boom';
import makeWASocket, {
  Browsers,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
  DisconnectReason,
  getUrlInfo,
  generateWAMessage,
  encodeNewsletterMessage,
  proto,
  downloadContentFromMessage,
  isJidNewsletter,
  isJidStatusBroadcast
} from 'baileys';
import axios from 'axios';
import path from 'path';
import { Jimp, HorizontalAlign, VerticalAlign } from 'jimp';
import { dbQuery } from './database/index.js';
import { callHook } from './pluginLoader.js';
import fs from 'fs';
import QRCode from 'qrcode';
import {
    isExistsEqualCommand,
    isExistsContainCommand,
    getUrlWebhook,
	getDevice,
  } from './database/model.js';
const sock = {}, qrcode = {}, pairingCode = {}, intervalStore = {};
let userIdDB;
import webp from 'webp-wasm';
import { setStatus } from './database/index.js';
import { IncomingMessage } from './controllers/incomingMessage.js';
import {
    formatReceipt,
    getSavedPhoneNumber,
    prepareMediaMessage,
	Button,
	formatButtonMsg,
	Section,
	formatListMsg
  } from './lib/helper.js';
import MAIN_LOGGER from './lib/pino.js';
import NodeCache from 'node-cache';
const logger = MAIN_LOGGER.child({});
const msgRetryCounterCache = new NodeCache();
import { ulid } from "ulid";
  let io = null;

  function setSocketIO(ioInstance) {
    io = ioInstance;
  }
  const connectToWhatsApp = async (token, socket = null, usePairingCode = false) => {

  const deviceRows = await dbQuery(`SELECT user_id FROM devices WHERE body = '${token}'`)
  if (!deviceRows.length) return { status: false, message: 'Device not found' }
  userIdDB = deviceRows[0].user_id;

  const { state, saveCreds } = await useMultiFileAuthState(`credentials/${token}`);
  const keyStore = makeCacheableSignalKeyStore(state.keys, logger);
  const { version, isLatest } = await fetchLatestBaileysVersion();

  console.log(`Using WA v${version.join('.')}. isLatest: ${isLatest}`);

  const checkConnectionStatus = async (connection) => {
    try {
      if (!connection?.user?.id) return false;
      const jid = `${connection.user.id.split(':')[0]}@s.whatsapp.net`;
      await connection.fetchStatus(jid);
      return true;
    } catch {
      return false;
    }
  };

  const attemptConnection = async () => {
    try {
      if (sock[token] && (await checkConnectionStatus(sock[token]))) {
		  console.log('Connection is active');
		  const userId = `${sock[token].user.id.split(':')[0]}@s.whatsapp.net`;
		  const ppUrl = await getPpUrl(token, userId);
		  socket?.emit('connection-open', { token, user: sock[token].user, ppUrl });
		  delete qrcode[token];
		  delete pairingCode[token];
		  if (intervalStore[token]) { clearInterval(intervalStore[token]); delete intervalStore[token]; }
		  return { status: true, message: 'Already connected' };
		} else {
		  console.log('Connection is not active, attempting to reconnect');
		}
    } catch (error) {
      console.error('Error checking existing connection:', error);
      socket?.emit('message', { token, message: 'Connecting.. (1)..' });
    }

    if (sock[token]) {
      try { sock[token].ev.removeAllListeners(); } catch {}
      try { if (sock[token].ws && sock[token].ws.readyState === 1) sock[token].ws.close(); } catch {}
    }

    const getDeviceAll = await getDevice(token);
    const markOnline = !!(getDeviceAll && getDeviceAll[0] && getDeviceAll[0].set_available !== 0);

    const connectionOptions = {
      version,
      logger,
      fireInitQueries: false,
      printQRInTerminal: false,
      auth: { creds: state.creds, keys: keyStore },
      browser: Browsers.macOS('Safari'),
      markOnlineOnConnect: markOnline,
      generateHighQualityLinkPreview: true,
      connectTimeoutMs: 30_000,
      defaultQueryTimeoutMs: undefined,
      keepAliveIntervalMs: 20_000,
      emitOwnEvents: false,
      retryRequestDelayMs: 2000,
      qrTimeout: 60000,
	  shouldIgnoreJid: jid => isJidNewsletter(jid) || isJidStatusBroadcast(jid),
    };

    sock[token] = makeWASocket(connectionOptions);

    if (sock[token]?.ws?.on) {
      sock[token].ws.on('CB:iq', async (frame) => {
        try {
          if (frame.attrs.type === 'error' && frame.content) {
            const errorNode = Array.isArray(frame.content) ? frame.content.find(c => c.tag === 'error') : null;
            if (errorNode && errorNode.attrs && errorNode.attrs.code === '429') {
              console.log('Rate limit error 429 detected via IQ stanza listener.');
              socket?.emit('rate-limit', {
                token,
                message: 'You have made too many requests. Please wait a while before trying again.'
              });
            }
          }
        } catch (e) {
          console.error('Error processing IQ stanza for rate limit check:', e);
        }
      });
    }

    sock[token].ev.on('connection.update', async (update) => {
	  const { connection, lastDisconnect, qr } = update

	  if (qr) {
		try {
		  const url = await QRCode.toDataURL(qr)
		  qrcode[token] = url
		  socket?.emit('qrcode', { token, data: url, message: 'Scan this QR code with your WhatsApp' })
		} catch {}
	  }

	  if (connection === 'close') {
		const code = lastDisconnect?.error?.output?.statusCode
		const shouldReconnect = code !== DisconnectReason.loggedOut
		if (intervalStore[token]) { clearInterval(intervalStore[token]); delete intervalStore[token] }
		if (shouldReconnect) {
		  if (code === 515) {
			setTimeout(() => attemptConnection(), 500)
		  } else {
			intervalStore[token] = setInterval(() => attemptConnection(), 10000)
		  }
		} else {
		  setStatus(token, 'Disconnect')
		  socket?.emit('message', { token, message: 'Connection closed. You are logged out.' })
		  await clearConnection(token)
		}
	  } else if (connection === 'open') {
		setStatus(token, 'Connected')
		const userId = `${sock[token].user.id.split(':')[0]}@s.whatsapp.net`
		const ppUrl = await getPpUrl(token, userId)
		socket?.emit('connection-open', { token, user: sock[token].user, ppUrl })
		delete qrcode[token]
		delete pairingCode[token]
		if (intervalStore[token]) { clearInterval(intervalStore[token]); delete intervalStore[token] }
	  }
	})

    sock[token].ev.on('creds.update', saveCreds);

    if (usePairingCode && !state.creds.registered) {
      const phoneNumber = await getSavedPhoneNumber(token);
      if (phoneNumber) {
        try {
          const code = await sock[token].requestPairingCode(phoneNumber);
          pairingCode[token] = code;
          socket?.emit('code', { token, data: code, message: 'Use this code to pair your device' });
        } catch (error) {
            console.error('Failed to request pairing code:', error);
          socket?.emit('message', { token, message: 'Failed to generate pairing code. Please try again.' });
        }
      } else {
        console.error('No saved phone number found for pairing code generation');
        socket?.emit('message', { token, message: 'No phone number available for pairing' });
      }
    }

	try {
		sock[token].ev.on('messages.upsert', async (messagesUpsert) => {
		  IncomingMessage(messagesUpsert, sock[token], userIdDB, token, io)
		  await callHook('messageProcessor', 'handleUpsert', messagesUpsert, token, userIdDB, io);
		  await callHook('whatsapp', 'onMessagesUpsert', messagesUpsert, sock[token], userIdDB, token, io);
		});
	} catch {}
  };

  await attemptConnection();

  try {
    if (sock[token]?.ws?.on) {
      sock[token].ws.on('CB:call', async call => {
        const getDeviceWa = await getDevice(sock[token].user.id.split(':')[0]);
        const TextCall = getDeviceWa[0].reject_message;
        if (TextCall !== null) {
          if (call.content[0].tag == 'offer') {
            const callerJid = call.content[0].attrs['call-creator'];
            const { platform, notify, t } = call.attrs;
            const caption = TextCall;
            await sock[token].sendMessage(callerJid, { text: caption });
          }
        }
      });
    }

  sock[token].ev.on('call', async calls => {
    const device = await getDevice(sock[token].user.id.split(':')[0]);
    const { reject_call, webhook_reject_call } = device[0];
    for (const call of calls) {
      if (call.status === 'offer' && (reject_call === 1 || webhook_reject_call === 1)) {
        await sock[token].rejectCall(call.id, call.from);
      }
    }
  });

  } catch {}

  return { sock: sock[token], qrcode: qrcode[token] };
};



async function connectWaBeforeSend(waToken) {
  let isConnected = undefined,
    connectionResult
  connectionResult = await connectToWhatsApp(waToken)
  await connectionResult.sock.ev.on('connection.update', (update) => {
    const { connection: connectionStatus, qr: qrCode } = update
    connectionStatus === 'open' && (isConnected = true)
    qrCode && (isConnected = false)
  })
  let retryCount = 0
  while (typeof isConnected === 'undefined') {
    retryCount++
    if (retryCount > 4) {
      break
    }
    await new Promise((resolve) => setTimeout(resolve, 1000))
  }
  return isConnected
}
const sendAvailable = async (body) => {
	const getDeviceAll = await getDevice(body);
    try {
	  if (getDeviceAll[0].set_available != 1) {
	     const sendAvailableResult = await sock[body].sendPresenceUpdate('available');
	  } else {
		 const sendAvailableResult = await sock[body].sendPresenceUpdate('unavailable');
	  }
      return sendAvailableResult
    } catch (error) {
      return false
    }
  };

const sendText = async (waToken, recipient, mgsid, message) => {
  let sendMessageResult;
  try {
    const jid = formatReceipt(recipient);
    const messageSpin = randomizeText(message);
	if (mgsid == '') {
		sendMessageResult = await sock[waToken].sendMessage(jid, { text: messageSpin });
	} else {
		const quotedTry1 = { key: { remoteJid: jid, id: mgsid, fromMe: false }, message: { conversation: '' } };
		sendMessageResult = await sock[waToken].sendMessage(jid, { text: messageSpin }, { quoted: quotedTry1 });
	}
	try {
		await callHook('messageProcessor', 'handleUpsert',sendMessageResult, waToken, userIdDB, io);
	} catch (error) {}
    return sendMessageResult;
  } catch (error) {
    return false;
  }
};

const sendMessage = async (waToken, recipient, message) => {
	  const messageSpin = [];
    try {
	  messageSpin.text = randomizeText(message.text);
      const sendMessageResult = await sock[waToken].sendMessage(
        formatReceipt(recipient),
        messageSpin
      )
	  try {
		await callHook('messageProcessor', 'handleUpsert',sendMessageResult, waToken, userIdDB, io);
	} catch (error) {}
      return sendMessageResult
    } catch (error) {
      return false
    }
  };

const sendTextChannel = async (waToken, recipient, message) => {
  const urlRegex = /(https?:\/\/[^\s]+)/g
  const urlMatch = message.match(urlRegex)
  try {
    let messageSpin = randomizeText(message)
    let msg

    if (urlMatch && urlMatch[0]) {
      const jid = recipient + '@newsletter'
      const fullMsg = await generateWAMessage(jid, { text: messageSpin }, {
        userJid: sock[waToken].user?.id,
        upload: sock[waToken].waUploadToServer,
        getUrlInfo: t => getUrlInfo(t, {
          thumbnailWidth: 1280,
          fetchOpts: { timeout: 5000 },
          uploadImage: sock[waToken].waUploadToServer
        })
      })
      msg = fullMsg.message
    } else {
      msg = { conversation: messageSpin }
    }

    const jid = recipient + '@newsletter'
    const plaintext = proto.Message.encode(msg).finish()
    const plaintextNode = { tag: 'plaintext', attrs: {}, content: plaintext }
    const node = { tag: 'message', attrs: { to: jid, type: 'text' }, content: [plaintextNode] }

    return await sock[waToken].query(node)
  } catch (error) {
    console.log(error)
    return false
  }
};


async function sendLocation(
  waToken,
  recipient,
  msgid,
  latitude,
  longitude
) {
  try {
    const jid = formatReceipt(recipient);
    const content = { location: { degreesLatitude: latitude, degreesLongitude: longitude } };
    let res;
    if (!msgid) {
      res = await sock[waToken].sendMessage(jid, content);
    } else {
      const quoted = { key: { remoteJid: jid, id: msgid, fromMe: false }, message: { conversation: '' } };
      res = await sock[waToken].sendMessage(jid, content, { quoted });
    }
	try {
		await callHook('messageProcessor', 'handleUpsert',res, waToken, userIdDB, io);
	} catch (error) {}

    return res;
  } catch (error) {
    return false;
  }
}
async function sendVcard(
  waToken,
  recipient,
  name,
  phone,
  msgid
) {
  try {
    const jid = formatReceipt(recipient);
    const vcard =
      'BEGIN:VCARD\n' +
      'VERSION:3.0\n' +
      'FN:' + name + '\n' +
      'TEL;type=CELL;type=VOICE;waid=' + phone + ':+'
      + phone + '\n' +
      'END:VCARD';
    const options = msgid ? { quoted: { key: { remoteJid: jid, id: msgid, fromMe: false }, message: { conversation: '' } } } : undefined;
    const sendVcardResult = await sock[waToken].sendMessage(
      jid,
      { contacts: { displayName: name, contacts: [{ vcard }] } },
      options
    );
	try {
		await callHook('messageProcessor', 'handleUpsert',sendVcardResult, waToken, userIdDB, io);
	} catch (error) {}
    return sendVcardResult;
  } catch (error) {
    return false;
  }
}

async function sendSticker(waToken, recipient, mediaPath, msgid) {
  await webp.load();

  const jid = formatReceipt(recipient);
  const quote = msgid ? { quoted: { key: { remoteJid: jid, id: msgid, fromMe: false }, message: { conversation: '' } } } : undefined;

  let buf;
  if (/^https?:\/\//i.test(mediaPath)) {
    const r = await axios.get(mediaPath, { responseType: 'arraybuffer' });
    buf = Buffer.from(r.data);
  } else {
    buf = await fs.promises.readFile(mediaPath);
  }

  const ext = path.extname(mediaPath).toLowerCase();
  if (ext === '.webp') {
    const r = await sock[waToken].sendMessage(jid, { sticker: buf }, quote);
    try { await callHook('messageProcessor', 'handleUpsert',r, waToken, userIdDB, io); } catch {}
    return r;
  }

  const img = await Jimp.read(buf);
  img.background = 0x00000000;
  img.contain({ w: 512, h: 512, align: HorizontalAlign.CENTER | VerticalAlign.MIDDLE });
  const { data, width, height } = img.bitmap;
  const rgba = new Uint8ClampedArray(data.buffer, data.byteOffset, data.byteLength);
  const webpBuf = await webp.encode({ data: rgba, width, height }, { quality: 60 });

  const res = await sock[waToken].sendMessage(jid, { sticker: webpBuf }, quote);
  try { await callHook('messageProcessor', 'handleUpsert',res, waToken, userIdDB, io); } catch {}
  return res;
}

async function sendMedia(
  waToken,
  recipient,
  mediaType,
  mediaPath,
  caption,
  message,
  viewonce,
  fileName,
  msgid
) {
  const formattedRecipient = formatReceipt(recipient);
  let userId = sock[waToken].user.id.replace(/:\d+/, '');
  const messageSpin = randomizeText(caption);
  const options = msgid ? { quoted: { key: { remoteJid: formattedRecipient, id: msgid, fromMe: false }, message: { conversation: '' } } } : undefined;

  if (mediaType === 'audio') {
    return await sock[waToken].sendMessage(
      formattedRecipient,
      { audio: { url: mediaPath }, ptt: true, mimetype: 'audio/mpeg' },
      options
    );
  }

  if (mediaType === 'image' || mediaType === 'video') {
    return await sock[waToken].sendMessage(
      formattedRecipient,
      { [mediaType]: { url: mediaPath }, caption: messageSpin ? messageSpin : '', viewOnce: viewonce },
      options
    );
  }

  const mediaMessage = await prepareMediaMessage(sock[waToken], {
    caption: messageSpin ? messageSpin : '',
    fileName: fileName,
    media: mediaPath,
    mediatype: mediaType !== 'video' && mediaType !== 'image' ? 'document' : mediaType,
  });

  const forwardMessage = { ...mediaMessage.message };

  const result = await sock[waToken].sendMessage(
    formattedRecipient,
    {
      forward: {
        key: { remoteJid: userId, fromMe: true },
        message: forwardMessage,
      },
    },
    options
  );
  try {
	await callHook('messageProcessor', 'handleUpsert',result, waToken, userIdDB, io);
  } catch (error) {}
  return result;
}

async function sendButtonMessage(
  token,
  number,
  button,
  message,
  footer,
  image
) {
  let type = "url";
  const msg = randomizeText(message);
  try {
    const buttons = button.map((x, i) => {
      return new Button(x);
    });
    const message = await formatButtonMsg(
      buttons,
      footer,
      msg,
      sock[token],
      image
    );
    const msgId = ulid(Date.now());
    const sendMsg = await sock[token].relayMessage(
      formatReceipt(number),
      message,
      { messageId: msgId }
    );
    return sendMsg;
  } catch (error) {
    console.log(error);
    return false;
  }
}
async function sendProduct(
  waToken,
  recipient,
  {
    product_id,
    phone,
    title,
    company,
    description,
    price,
    old_price,
    currency,
    image,
    message,
    msgid
  }
) {
  try {
    const hasDiscount = price && old_price

    const productPayload = {
      productImage: {
        url: image ?? 'https://placehold.co/600x400?text=No+Image'
      },
      productId: product_id ?? '',
      productImageCount: 1,
      title: title ?? '',
      description: description ?? '',
      currencyCode: currency ?? 'IDR',
      retailerId: company ?? '',
      url: '',
      signedUrl: ''
    }

	msgid = msgid ?? '';

    if (hasDiscount) {
      productPayload.priceAmount1000 = parseInt(old_price) * 1000
      productPayload.salePriceAmount1000 = parseInt(price) * 1000
    } else {
      productPayload.priceAmount1000 = parseInt(price || 0) * 1000
    }

    const jid = formatReceipt(recipient)
    const options = msgid ? { quoted: { key: { remoteJid: jid, id: msgid, fromMe: false }, message: { conversation: '' } } } : undefined

    const sendProductResult = await sock[waToken].sendMessage(
      jid,
      {
        product: productPayload,
        businessOwnerJid: `${phone}@s.whatsapp.net`,
        caption: randomizeText(description) ?? '',
        title: title ?? '',
        footer: message ?? '',
        media: true
      },
      options
    )

    return sendProductResult
  } catch (error) {
    return false
  }
}
async function sendListMessage(waToken, recipient, sections, message, footer, title, buttonText, msgid, image) {
  try {
    const jid = formatReceipt(recipient);
    const messageSpin = randomizeText(message);
    const sectionObj = new Section({
      buttonText: buttonText,
      list: sections
    });
    const listMessage = await formatListMsg(
      [sectionObj],
      footer,
      messageSpin,
      sock[waToken],
      image
    );
    const msgId = ulid(Date.now());
    const sendListMessageResult = await sock[waToken].relayMessage(
      jid,
      listMessage,
      { messageId: msgId }
    );
    return sendListMessageResult;
  } catch (error) {
    console.log(error);
    return false;
  }
}

async function sendPollMessage(waToken, recipient, pollName, pollValues, selectableCount, msgid) {
  try {
    const jid = formatReceipt(recipient);
    const content = { poll: { name: pollName, values: pollValues, selectableCount: selectableCount } };
    let sendPollMessageResult;
    if (!msgid) {
      sendPollMessageResult = await sock[waToken].sendMessage(jid, content);
    } else {
      const quoted = { key: { remoteJid: jid, id: msgid, fromMe: false }, message: { conversation: '' } };
      sendPollMessageResult = await sock[waToken].sendMessage(jid, content, { quoted });
    }
    return sendPollMessageResult;
  } catch (error) {
    return false;
  }
}

async function fetchGroups(waToken) {
  try {
	if (typeof sock[waToken] === 'undefined') {
      const connectionResult = await connectWaBeforeSend(waToken)
      if (!connectionResult) {
        return false
      }
    }
    let allGroups = await sock[waToken].groupFetchAllParticipating();
    let groupList = Object.entries(allGroups)
        .slice(0)
        .map((groupEntry) => groupEntry[1])
    return groupList
  } catch (error) {
    return false
  }
}
async function fetchChannel(waToken, code) {
  try {
    const iq = {
      tag: 'iq',
      attrs: {
        id: sock[waToken].generateMessageTag(),
        type: 'get',
        xmlns: 'w:mex',
        to: 's.whatsapp.net'
      },
      content: [
        {
          tag: 'query',
          attrs: { query_id: '6620195908089573' },
          content: Buffer.from(JSON.stringify({
            variables: {
              newsletter_id: code,
              input: {
                key: code,
                type: 'INVITE',
                view_role: 'GUEST'
              },
              fetch_viewer_metadata: true,
              fetch_full_image: true,
              fetch_creation_time: true
            }
          }))
        }
      ]
    }

    const res = await sock[waToken].query(iq)
    const resultNode = res.content.find(n => n.tag === 'result')
    const json = JSON.parse(resultNode.content.toString())
    const meta = json.data.xwa2_newsletter
	return meta
  } catch (err) {
    console.error('fetchChannelInfo error:', err)
    return false
  }
}
async function isExist(waToken, phoneNumber) {
  try {
    if (typeof sock[waToken] === 'undefined') {
      const connectionResult = await connectWaBeforeSend(waToken)
      if (!connectionResult) {
        return false
      }
    }
    if (phoneNumber.includes('@g.us')) {
      return true
	} else if (phoneNumber.includes('@newsletter')) {
      return true
    } else {
      const [isOnWhatsApp] = await sock[waToken].onWhatsApp('+' + phoneNumber)
      return phoneNumber.length > 11 ? isOnWhatsApp : true
    }
  } catch (error) {
    return false
  }
}
async function getPpUrl(waToken, userId, error) {
  let profilePictureUrl
  try {
    return (
      (profilePictureUrl = await sock[waToken].profilePictureUrl(userId, 'image')),
      profilePictureUrl
    )
  } catch (error) {
    return 'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/WhatsApp.svg/1200px-WhatsApp.svg.png'
  }
}
async function deleteCredentials(waToken, socket = null) {
  socket !== null &&
    socket.emit('message', {
      token: waToken,
      message: 'Logout Progres..',
    })
  try {
    if (typeof sock[waToken] === 'undefined') {
      const connectionResult = await connectWaBeforeSend(waToken)
      connectionResult && (sock[waToken].logout(), delete sock[waToken])
    } else {
      sock[waToken].logout()
      delete sock[waToken]
    }
    return (
      delete qrcode[waToken],
      clearInterval(intervalStore[waToken]),
      setStatus(waToken, 'Disconnect'),
      socket != null &&
        (socket.emit('Unauthorized', waToken),
        socket.emit('message', {
          token: waToken,
          message: 'Connection closed. You are logged out.',
        })),
      fs.existsSync('./credentials/' + waToken) &&
        fs.rmSync(
          './credentials/' + waToken,
          {
            recursive: true,
            force: true,
          },
          (error) => {
            if (error) {
              console.log(error)
            }
          }
        ),
      {
        status: true,
        message: 'Deleting session and credential',
      }
    )
  } catch (error) {
    return (
      console.log(error),
      {
        status: true,
        message: 'Nothing deleted',
      }
    )
  }
}
function randomizeText(text){
    return text.replace(/{([^{}]*)}/g, function(match, content){
        if(content.toLowerCase() === 'random_text'){
            return Array.from({length:4},()=>String.fromCharCode(97+Math.floor(Math.random()*26))).join('')
        }else if(content.toLowerCase() === 'random_num'){
            return Array.from({length:4},()=>Math.floor(Math.random()*10)).join('')
        }else if(content.includes('|')){
            let parts = content.split('|')
            return parts[Math.floor(Math.random()*parts.length)]
        }else{
            return match
        }
    })
}
function clearConnection(waToken) {
  clearInterval(intervalStore[waToken]);
  delete sock[waToken];
  delete qrcode[waToken];
  delete pairingCode[waToken];
  setStatus(waToken, 'Disconnect');
  fs.existsSync('./credentials/' + waToken) &&
    (fs.rmSync(
      './credentials/' + waToken,
      {
        recursive: true,
        force: true,
      },
      (error) => {
        if (error) {
          console.log(error)
        }
      }
    ),
    console.log('credentials/' + waToken + ' is deleted'))
}
async function initialize(req, res) {
  const { token: token } = req.body
  if (token) {
    const credentialsPath = './credentials/' + token
    if (fs.existsSync(credentialsPath)) {
      sock[token] = undefined
      const connectionResult = await connectWaBeforeSend(token)
      return connectionResult
        ? res.status(200).json({
            status: true,
            message: token + ' connection restored',
          })
        : res.status(200).json({
            status: false,
            message: token + ' connection failed',
          })
    }
    return res.send({
      status: false,
      message: token + ' Connection failed,please scan first',
    })
  }
  return res.send({
    status: false,
    message: 'Wrong Parameterss',
  })
}
export {
  connectToWhatsApp,
  sendAvailable,
  sendText,
  sendLocation,
  sendProduct,
  sendTextChannel,
  sendVcard,
  sendSticker,
  sendMedia,
  sendButtonMessage,
  sendListMessage,
  sendPollMessage,
  randomizeText,
  isExist,
  getPpUrl,
  fetchGroups,
  fetchChannel,
  deleteCredentials,
  sendMessage,
  initialize,
  connectWaBeforeSend,
  sock,
  setSocketIO,
};
