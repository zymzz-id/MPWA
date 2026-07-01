import { parseIncomingMessage, formatReceipt, prepareMediaMessage, formatButtonMsg, Button, Section, formatListMsg } from '../lib/helper.js';
import 'dotenv/config';
import axios from 'axios';
import { isExistsEqualCommand, isExistsContainCommand, getUrlWebhook, getDevice } from '../database/model.js';
import path from 'path';
import { fileURLToPath } from 'url';
import { Jimp, HorizontalAlign, VerticalAlign } from 'jimp';
import { ulid } from 'ulid';
import { GoogleGenAI } from '@google/genai';
import { callHook, callHookUntilHandled } from '../pluginLoader.js';
import fs from 'fs/promises';
import FormData from 'form-data';
import { dbQuery } from '../database/index.js';
import webp from 'webp-wasm';
import { downloadContentFromMessage } from 'baileys';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const chatSessions = new Map();

function parseLlmModels(v) {
  try {
    if (!v) return {};
    if (typeof v === 'string') return JSON.parse(v);
    if (typeof v === 'object') return v;
    return {};
  } catch {
    return {};
  }
}

function getDeviceModel(devAll, provider, fallback) {
  const llm = parseLlmModels(devAll?.[0]?.llm_models);
  return llm?.[provider] || fallback;
}

function removeNameFromContent(content, name) {
  const regex = new RegExp(`\\s*${name}\\s*`, 'g');
  return content.replace(regex, '').trim();
}

function randomizeText(text) {
    return text.replace(/{([^{}]*)}/g, function(match, options) {
        let choices = options.split('|');
        return choices[Math.floor(Math.random() * choices.length)];
    });
}

async function upsertBotReply(incomingData, socket, waToken, io, text) {
  const recipientJid = incomingData.key.remoteJid
  const userNumber = (socket?.user?.id || '').replace(/:\d+/, '').split('@')[0]
  const dev = await getDevice(userNumber)
  const userIdDB = dev?.[0]?.user_id || 0
  const fakeUpsert = {
    messages: [
      {
        key: { remoteJid: recipientJid, fromMe: true },
        messageTimestamp: Math.floor(Date.now() / 1000),
        message: { conversation: text || 'Message' }
      }
    ]
  }
  await callHook('messageProcessor', 'handleUpsert', fakeUpsert, waToken, userIdDB, io)
}

const IncomingMessage = async (incomingData, socket, userIdDB, waToken, io) => {
  try {
    let isQuoted = false;
	let participantNumber;

    if (!incomingData.messages) return;

    incomingData = incomingData.messages[0];
	const remoteJid = incomingData.key.remoteJid && (incomingData.key.remoteJid.endsWith('s.whatsapp.net') || incomingData.key.remoteJid.endsWith('g.us')) ?
		incomingData.key.remoteJid :
		incomingData.key.remoteJidAlt && (incomingData.key.remoteJidAlt.endsWith('s.whatsapp.net') || incomingData.key.remoteJidAlt.endsWith('g.us')) ?
		incomingData.key.remoteJidAlt :
		null;
    const pushName = incomingData?.pushName || '';
    if (incomingData.key.fromMe === true) return;
    if (remoteJid === 'status@broadcast') return;
	if (remoteJid?.startsWith('155178684')) return;

	if(incomingData.key.participant === undefined){
		const participant = incomingData.key.participant && incomingData.key.participant.endsWith('s.whatsapp.net') ?
						   incomingData.key.participant :
						   incomingData.key.participantAlt && incomingData.key.participantAlt.endsWith('s.whatsapp.net') ?
						   incomingData.key.participantAlt :
						   parent.getJidFromParticipants(m.chat, (incomingData.key.participant || incomingData.key.participantAlt)) || incomingData.key.participant
		participantNumber = participant && formatReceipt(participant);
	} else {
		participantNumber = remoteJid && formatReceipt(remoteJid);
	}

    const { command, bufferImage, type, from } = await parseIncomingMessage(incomingData);

    const pluginHandled = await callHookUntilHandled('incomingMessage', 'onIncomingMessage', { incomingData, socket, userIdDB, waToken, io, command, bufferImage, from, remoteJid, pushName });
    if (pluginHandled) return;

    let replyContent, commandMatch;
    const userId = socket.user.id.split(':')[0];
    const exactMatch = await isExistsEqualCommand(command, userId);
    commandMatch = exactMatch.length > 0 ? exactMatch : await isExistsContainCommand(command, userId);

	const phoneOnly = (remoteJid || '').split('@')[0]
	const rows = await dbQuery(`SELECT stop_ai FROM chat_sessions WHERE user_id=${userIdDB} AND phone_number='${phoneOnly}' ORDER BY updated_at DESC LIMIT 1`)
	if(rows[0]?.stop_ai === 1){
		return;
	}

    if (commandMatch.length === 0) {
      const getDeviceAll = await getDevice(userId);
      const webhookUrl = await getUrlWebhook(userId);
      let webhookResponse = false;

      if (webhookUrl != null) {
        webhookResponse = await sendWebhook({
          command: command,
          bufferImage: bufferImage,
		  type: type,
          from: from,
		  name: pushName,
          url: webhookUrl,
          participant: participantNumber,
		  webhook_full: getDeviceAll[0].webhook_full,
		  data: incomingData
        });
      }

      if (webhookResponse === false || webhookResponse === undefined || typeof webhookResponse != 'object') {
        const replyorno = getDeviceAll[0].reply_when === 'All' ||
          (getDeviceAll[0].reply_when === 'Group' && remoteJid.includes('@g.us')) ||
          (getDeviceAll[0].reply_when === 'Personal' && !remoteJid.includes('@g.us'));

        if (replyorno === false) return;

        if (getDeviceAll[0].typebot == 1 && (getDeviceAll[0].bexa_api_key != null || getDeviceAll[0].chatgpt_api != null || getDeviceAll[0].gemini_api != null || getDeviceAll[0].claude_api != null || getDeviceAll[0].dalle_api != null || getDeviceAll[0].groq_api != null || getDeviceAll[0].deepseek_api != null)) {
          if (getDeviceAll[0].can_read_message != 0) {
            socket.readMessages([incomingData.key]);
          }
          await socket.presenceSubscribe(remoteJid);
          if (getDeviceAll[0].bot_typing != 0) {
            await socket.sendPresenceUpdate('composing', remoteJid);
          }
          if (getDeviceAll[0].delay > 0) {
            await new Promise(resolve => setTimeout(resolve, getDeviceAll[0].delay * 1000));
          }

          if (getDeviceAll[0].gemini_api) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            var geminiResponse = await sendGemini({
              command: command,
              from: from,
              geminiKey: getDeviceAll[0].gemini_api,
              participant: participantNumber,
			  systemInstructions: getDeviceAll[0].system_instructions,
			  bufferImage: bufferImage,
			  model: getDeviceModel(getDeviceAll, 'gemini', process.env.GEMINI_MODEL),
			  deviceToken: waToken
            });
            if (geminiResponse === false || geminiResponse === undefined) return;
            isQuoted = geminiResponse?.quoted ? true : false;
            replyContent = geminiResponse;
          } else if (getDeviceAll[0].chatgpt_api) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            var chatgptResponse = await sendChatgpt({
              command: command,
              from: from,
              chatgptKey: getDeviceAll[0].chatgpt_api,
              participant: participantNumber,
			  systemInstructions: getDeviceAll[0].system_instructions,
			  bufferImage: bufferImage,
			  model: getDeviceModel(getDeviceAll, 'chatgpt', process.env.CHATGPT_MODEL)
            });
            if (chatgptResponse === false || chatgptResponse === undefined) return;
            isQuoted = chatgptResponse?.quoted ? true : false;
            replyContent = chatgptResponse;
          } else if (getDeviceAll[0].dalle_api) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            var dalleResponse = await sendDalle({
              command: command,
              dalleKey: getDeviceAll[0].dalle_api,
            });
            if (dalleResponse === false || dalleResponse === undefined) return;
            isQuoted = dalleResponse?.quoted ? true : false;
            replyContent = dalleResponse;
          } else if (getDeviceAll[0].claude_api) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            var claudeResponse = await sendClaude({
              command: command,
              from: from,
              claudeKey: getDeviceAll[0].claude_api,
              participant: participantNumber,
			  systemInstructions: getDeviceAll[0].system_instructions,
			  bufferImage: bufferImage,
			  model: getDeviceModel(getDeviceAll, 'claude', process.env.CLAUDE_MODEL)
            });
            if (claudeResponse === false || claudeResponse === undefined) return;
            isQuoted = claudeResponse?.quoted ? true : false;
            replyContent = claudeResponse;
          } else if (getDeviceAll[0].groq_api) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            var groqResponse = await sendGroq({
              command: command,
              from: from,
              groqKey: getDeviceAll[0].groq_api,
              participant: participantNumber,
              systemInstructions: getDeviceAll[0].system_instructions,
              bufferImage: bufferImage,
              model: getDeviceModel(getDeviceAll, 'groq', 'llama-3.3-70b-versatile')
            });
            if (groqResponse === false || groqResponse === undefined) return;
            isQuoted = groqResponse?.quoted ? true : false;
            replyContent = groqResponse;
          } else if (getDeviceAll[0].deepseek_api) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            var deepseekResponse = await sendDeepseek({
              command: command,
              from: from,
              deepseekKey: getDeviceAll[0].deepseek_api,
              participant: participantNumber,
              systemInstructions: getDeviceAll[0].system_instructions,
              bufferImage: bufferImage,
              model: getDeviceModel(getDeviceAll, 'deepseek', 'deepseek-v4-flash')
            });
            if (deepseekResponse === false || deepseekResponse === undefined) return;
            isQuoted = deepseekResponse?.quoted ? true : false;
            replyContent = deepseekResponse;
          } else {
            return;
          }
        } else if (getDeviceAll[0].typebot == 2 && (getDeviceAll[0].chatgpt_api != null || getDeviceAll[0].gemini_api != null || getDeviceAll[0].claude_api != null || getDeviceAll[0].dalle_api != null || getDeviceAll[0].groq_api != null || getDeviceAll[0].deepseek_api != null)) {
		  const AiName = (incomingData.message?.conversation?.toLowerCase()) || (incomingData.message?.extendedTextMessage?.text?.toLowerCase()) || '';
          if (getDeviceAll[0].can_read_message != 0) {
            socket.readMessages([incomingData.key]);
          }
          if (getDeviceAll[0].bot_typing != 0) {
            await socket.presenceSubscribe(remoteJid);
            await socket.sendPresenceUpdate('composing', remoteJid);
          }
          if (getDeviceAll[0].delay > 0) {
            await new Promise(resolve => setTimeout(resolve, getDeviceAll[0].delay * 1000));
          }

          if (getDeviceAll[0].gemini_api && AiName.includes(getDeviceAll[0].gemini_name.toLowerCase())) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            const commandRemove = removeNameFromContent(command, getDeviceAll[0].gemini_name.toLowerCase());
            var geminiResponse = await sendGemini({
              command: commandRemove,
              from: from,
              geminiKey: getDeviceAll[0].gemini_api,
              participant: participantNumber,
			  systemInstructions: getDeviceAll[0].system_instructions,
			  bufferImage: bufferImage,
			  model: getDeviceModel(getDeviceAll, 'gemini', process.env.GEMINI_MODEL),
			  deviceToken: waToken
            });
            if (geminiResponse === false || geminiResponse === undefined) return;
            isQuoted = geminiResponse?.quoted ? true : false;
            replyContent = geminiResponse;
          } else if (getDeviceAll[0].chatgpt_api && AiName.includes(getDeviceAll[0].chatgpt_name.toLowerCase())) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            const commandRemove = removeNameFromContent(command, getDeviceAll[0].chatgpt_name.toLowerCase());
            var chatgptResponse = await sendChatgpt({
              command: commandRemove,
              from: from,
              chatgptKey: getDeviceAll[0].chatgpt_api,
              participant: participantNumber,
			  systemInstructions: getDeviceAll[0].system_instructions,
			  bufferImage: bufferImage,
			  model: getDeviceModel(getDeviceAll, 'chatgpt', process.env.CHATGPT_MODEL)
            });
            if (chatgptResponse === false || chatgptResponse === undefined) return;
            isQuoted = chatgptResponse?.quoted ? true : false;
            replyContent = chatgptResponse;
          } else if (getDeviceAll[0].dalle_api && AiName.includes(getDeviceAll[0].dalle_name.toLowerCase())) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            const commandRemove = removeNameFromContent(command, getDeviceAll[0].dalle_name.toLowerCase());
            var dalleResponse = await sendDalle({
              command: commandRemove,
              dalleKey: getDeviceAll[0].dalle_api,
            });
            if (dalleResponse === false || dalleResponse === undefined) return;
            isQuoted = dalleResponse?.quoted ? true : false;
            replyContent = dalleResponse;
          } else if (getDeviceAll[0].claude_api && AiName.includes(getDeviceAll[0].claude_name.toLowerCase())) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            const commandRemove = removeNameFromContent(command, getDeviceAll[0].claude_name.toLowerCase());
            var caludeResponse = await sendClaude({
              command: commandRemove,
              from: from,
              claudeKey: getDeviceAll[0].claude_api,
              participant: participantNumber,
			  systemInstructions: getDeviceAll[0].system_instructions,
			  bufferImage: bufferImage,
			  model: getDeviceModel(getDeviceAll, 'claude', process.env.CLAUDE_MODEL)
            });
            if (caludeResponse === false || caludeResponse === undefined) return;
            isQuoted = caludeResponse?.quoted ? true : false;
            replyContent = caludeResponse;
          } else if (getDeviceAll[0].groq_api && AiName.includes(getDeviceAll[0].groq_name.toLowerCase())) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            const commandRemoveGroq = removeNameFromContent(command, getDeviceAll[0].groq_name.toLowerCase());
            var groqResponse2 = await sendGroq({
              command: commandRemoveGroq,
              from: from,
              groqKey: getDeviceAll[0].groq_api,
              participant: participantNumber,
              systemInstructions: getDeviceAll[0].system_instructions,
              bufferImage: bufferImage,
              model: getDeviceModel(getDeviceAll, 'groq', 'llama-3.3-70b-versatile')
            });
            if (groqResponse2 === false || groqResponse2 === undefined) return;
            isQuoted = groqResponse2?.quoted ? true : false;
            replyContent = groqResponse2;
          } else if (getDeviceAll[0].deepseek_api && AiName.includes(getDeviceAll[0].deepseek_name.toLowerCase())) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            const commandRemoveDeepseek = removeNameFromContent(command, getDeviceAll[0].deepseek_name.toLowerCase());
            var deepseekResponse2 = await sendDeepseek({
              command: commandRemoveDeepseek,
              from: from,
              deepseekKey: getDeviceAll[0].deepseek_api,
              participant: participantNumber,
              systemInstructions: getDeviceAll[0].system_instructions,
              bufferImage: bufferImage,
              model: getDeviceModel(getDeviceAll, 'deepseek', 'deepseek-v4-flash')
            });
            if (deepseekResponse2 === false || deepseekResponse2 === undefined) return;
            isQuoted = deepseekResponse2?.quoted ? true : false;
            replyContent = deepseekResponse2;
          } else {
            return;
          }
        } else if (getDeviceAll[0].typebot == 3 && getDeviceAll[0].bexa_api_key) {
            if (getDeviceAll[0].bot_typing != 0) {
              await socket.presenceSubscribe(remoteJid);
              await socket.sendPresenceUpdate('composing', remoteJid);
            }
            if (getDeviceAll[0].delay > 0) {
              await new Promise(resolve => setTimeout(resolve, getDeviceAll[0].delay * 1000));
            }
            var bexaResponse = await sendBexa({
              command: command,
              from: from,
              bexaKey: getDeviceAll[0].bexa_api_key,
              participant: participantNumber,
			  bufferImage: bufferImage,
			  device: getDeviceAll[0]
            });
            if (bexaResponse === false || bexaResponse === undefined) return;
            isQuoted = bexaResponse?.quoted ? true : false;
            replyContent = bexaResponse;
        }
      } else {
        if (getDeviceAll[0].webhook_read != 0) {
          socket.readMessages([incomingData.key]);
        }
        if (getDeviceAll[0].webhook_typing != 0) {
          await socket.presenceSubscribe(remoteJid);
          await socket.sendPresenceUpdate('composing', remoteJid);
        }
        if (getDeviceAll[0].delay != 0) {
          await new Promise(resolve => setTimeout(resolve, getDeviceAll[0].delay + '000'));
        }
        isQuoted = webhookResponse?.quoted ? true : false;
        replyContent = JSON.stringify(webhookResponse);
      }
      if (!replyContent) return;
    } else {
      const replyorno = commandMatch[0].reply_when === 'All' ||
        (commandMatch[0].reply_when === 'Group' && remoteJid.includes('@g.us')) ||
        (commandMatch[0].reply_when === 'Personal' && !remoteJid.includes('@g.us'));

      if (replyorno === false) return;

	  if (commandMatch[0].is_read != 0) {
        socket.readMessages([incomingData.key]);
      }
	  if (commandMatch[0].is_typing != 0) {
        await socket.presenceSubscribe(remoteJid);
        await socket.sendPresenceUpdate('composing', remoteJid);
      }
      if (commandMatch[0].delay != 0) {
        await new Promise(resolve => setTimeout(resolve, commandMatch[0].delay + '000'));
      }

      isQuoted = commandMatch[0].is_quoted ? true : false;
      replyContent = typeof commandMatch[0].reply === 'object' ? JSON.stringify(commandMatch[0].reply) : commandMatch[0].reply;
    }

    let tmp = replyContent;
	  if (typeof tmp === 'string') {
		tmp = tmp.replace(/{name}/g, pushName);
		try {
		  tmp = JSON.parse(tmp);
		} catch {
		  tmp = { text: tmp };
		}
	  } else if (tmp && typeof tmp === 'object') {
		if (typeof tmp.text === 'string') tmp.text = tmp.text.replace(/{name}/g, pushName);
		if (typeof tmp.caption === 'string') tmp.caption = tmp.caption.replace(/{name}/g, pushName);
	  } else {
		return;
	  }
	  replyContent = tmp;

	if (replyContent.text && replyContent.text.trim() !== '') {
		replyContent.text = randomizeText(replyContent.text);
		if (replyContent.footer && replyContent.footer.trim() !== '') {
			replyContent.text = `${replyContent.text}\n\n> _${replyContent.footer}_`;
			delete replyContent.footer;
		}
	}

    if (replyContent && typeof replyContent === 'object' && 'type' in replyContent) {
      let userJid = socket.user.id.replace(/:\d+/, '');
      if (replyContent.type == 'audio') {
        await socket.sendMessage(remoteJid, { audio: { url: replyContent.url }, ptt: true, mimetype: 'audio/mpeg' })
		await upsertBotReply(incomingData, socket, waToken, io, 'Audio')
		return
      }

	  if (replyContent.caption && replyContent.caption.trim() !== '') {
		if (replyContent.footer && replyContent.footer.trim() !== '') {
		  replyContent.caption = `${replyContent.caption}\n\n> _${replyContent.footer}_`;
		  delete replyContent.footer;
		}
	  } else if (replyContent.footer && replyContent.footer.trim() !== '') {
		replyContent.caption = `> _${replyContent.footer}_`;
		delete replyContent.footer;
	  }

	if (replyContent.type == 'sticker') {
	  await webp.load();

	  const urlNoQuery = replyContent.url.split('?')[0];
	  const ext = path.extname(urlNoQuery).toLowerCase();

	  if (ext === '.webp') {
		const r = await axios.get(replyContent.url, { responseType: 'arraybuffer' });
		const buf = Buffer.from(r.data);
		await socket.sendMessage(remoteJid, { sticker: buf });
		await upsertBotReply(incomingData, socket, waToken, io, 'Sticker');
		return;
	  }

	  const resp = await axios.get(replyContent.url, { responseType: 'arraybuffer' });
	  const img = await Jimp.read(Buffer.from(resp.data));
	  img.background = 0x00000000;
	  img.contain({ w: 512, h: 512, align: HorizontalAlign.CENTER | VerticalAlign.MIDDLE });
	  const { data, width, height } = img.bitmap;
	  const rgba = new Uint8ClampedArray(data.buffer, data.byteOffset, data.byteLength);
	  const webpBuf = await webp.encode({ data: rgba, width, height }, { quality: 60 });

	  await socket.sendMessage(remoteJid, { sticker: webpBuf });
	  await upsertBotReply(incomingData, socket, waToken, io, 'Sticker');
	  return;
	}

      const preparedMedia = await prepareMediaMessage(socket, {
		  caption: replyContent.caption ? replyContent.caption : '',
		  fileName: replyContent.filename,
		  media: replyContent.url,
		  mediatype: replyContent.type !== 'video' && replyContent.type !== 'image' ? 'document' : replyContent.type,
	  });

		const forwardMessage = JSON.parse(JSON.stringify(preparedMedia.message));

		if (forwardMessage.imageMessage) {
		  forwardMessage.imageMessage.viewOnce = replyContent.viewonce;
		} else if (forwardMessage.videoMessage) {
		  forwardMessage.videoMessage.viewOnce = replyContent.viewonce;
		}

		await socket.sendPresenceUpdate('paused', remoteJid);

		await socket.sendMessage(
		  remoteJid,
		  {
			forward: {
			  key: {
				remoteJid: userJid,
				fromMe: true,
			  },
			  message: forwardMessage,
			},
		  },
		  {
			quoted: isQuoted ? incomingData : null,
		  }
		);
		let last = 'Message'
		if (replyContent.type === 'image') last = replyContent.caption || 'Image'
		else if (replyContent.type === 'video') last = replyContent.caption || 'Video'
		else if (replyContent.type === 'document') last = replyContent.caption || 'Document'
		await upsertBotReply(incomingData, socket, waToken, io, last);
		return
    } else if (replyContent && typeof replyContent === 'object' && 'buttons' in replyContent) {
		  const buttonObjects = replyContent.buttons.map(buttonRawData => {
			const raw = buttonRawData.buttonText?.displayText || {};
			return new Button({
			  type: raw.type || 'reply',
			  displayText: raw.displayText,
			  id: buttonRawData.buttonId,
			  phoneNumber: raw.phoneNumber,
			  url: raw.url,
			  copyCode: raw.copyCode
			});
		  });

		  const formattedButtonMessage = formatButtonMsg(
			buttonObjects,
			replyContent?.footer,
			replyContent.text ?? replyContent?.caption,
			socket,
			replyContent?.image?.url
		  );

		  const messageId = ulid(Date.now());

		  return await socket.relayMessage(
			remoteJid,
			formattedButtonMessage,
			{ messageId }
		  );
	} else if (replyContent && typeof replyContent === 'object' && 'sections' in replyContent) {
		  const sectionObj = new Section({
			buttonText: replyContent.buttonText || 'Select',
			list: replyContent.sections
		  });

		  const formattedListMessage = await formatListMsg(
			[sectionObj],
			replyContent?.footer,
			replyContent.text ?? replyContent?.caption,
			socket,
			replyContent?.image?.url
		  );

		  const messageId = ulid(Date.now());

		  return await socket.relayMessage(
			remoteJid,
			formattedListMessage,
			{ messageId }
		  );
	} else if (replyContent && typeof replyContent === 'object') {
      await socket.sendMessage(remoteJid, replyContent, { quoted: isQuoted ? incomingData : null })
      await upsertBotReply(incomingData, socket, waToken, io, replyContent.text || replyContent.caption || 'Message')
    } else {
	  return;
	}
    return true;
  } catch (error) {
    console.log(error);
  }
};

async function downloadMedia(mediaMessage) {
  const stream = await downloadContentFromMessage(mediaMessage, 'image');
  const filePath = `./storage/${Date.now()}.jpg`;
  const fsSync = await import('fs');
  const writer = fsSync.default.createWriteStream(filePath);

  return new Promise((resolve, reject) => {
    stream.pipe(writer);
    writer.on('finish', () => resolve(filePath));
    writer.on('error', reject);
  });
}

async function sendWebhook({ command, bufferImage, type, from, name, url, participant, webhook_full, data }) {
  function isNumericKeyObject(o) {
    if (!o || typeof o !== 'object' || Array.isArray(o)) return false;
    const keys = Object.keys(o);
    if (keys.length < 8) return false;
    for (let i = 0; i < keys.length; i++) if (!/^\d+$/.test(keys[i])) return false;
    return true;
  }

  const DROP_KEY_REGEX = /(sha(256|1|512)|md5|thumbnail|mediaKey)$/i;

  function sanitize(input) {
    if (input === null || typeof input !== 'object') return input;
    if (Array.isArray(input)) return input.map(sanitize).filter(v => v !== undefined);
    const out = {};
    for (const [k, v] of Object.entries(input)) {
      if (k === 'messageContextInfo') continue;
      if (DROP_KEY_REGEX.test(k)) continue;
      if (isNumericKeyObject(v)) continue;
      const cleaned = sanitize(v);
      if (cleaned !== undefined) out[k] = cleaned;
    }
    return out;
  }

  const baseFields = {
    message: command,
    bufferImage: bufferImage === undefined ? null : bufferImage,
	type: type,
    from: from,
    name: name,
    participant: participant
  };

  try {
    let payload;
    if (webhook_full != 1) {
      payload = baseFields;
    } else {
      const cloned = typeof structuredClone === 'function' ? structuredClone(data || {}) : JSON.parse(JSON.stringify(data || {}));
      const sanitizedData = sanitize(cloned);
      payload = { ...baseFields, data: sanitizedData };
    }

    const res = await axios.post(url, payload, { headers: { 'Content-Type': 'application/json; charset=utf-8' }, maxBodyLength: Infinity, maxContentLength: Infinity });
    return res && res.data !== undefined ? res.data : true;
  } catch (error) {
    console.log('error send webhook', error);
    return false;
  }
}

async function sendDalle({ command, dalleKey }) {
  try {
    const dalleUrl = process.env.DALLE_URL;
    const dalleData = {
      prompt: command,
      n: 1,
      size: process.env.DALLE_SIZE,
    };
    const headers = {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + dalleKey,
    };
    const response = await axios.post(dalleUrl, dalleData, { headers: headers }).catch(() => {
      return false;
    });
    if (!response) return false;
    const responseDall = {
      url: response.data.data[0].url,
      type: 'image',
      quoted: 0
    };
    return JSON.stringify(responseDall, null, 2);
  } catch (error) {
    console.log('error sendDalle', error);
    return false;
  }
}

async function sendChatgpt({ command, from, chatgptKey, participantNumber, systemInstructions, bufferImage, model }) {
  try {
    const gptUrl = process.env.CHATGPT_URL;
    let messages = [];

    if (bufferImage) {
      messages = [
        {
          role: 'user',
          content: [
            {
              type: 'text',
              text: command
            },
            {
              type: 'image_url',
              image_url: {
                url: `data:image/jpeg;base64,${bufferImage}`
              }
            }
          ]
        }
      ];
    } else {
      messages = [
        {
          role: 'user',
          content: command
        },
		{
          role: 'system',
          content: systemInstructions
        }
      ];
    }

    const chatgptData = {
      model: model || process.env.CHATGPT_MODEL,
      messages: messages
    };

    const headers = {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + chatgptKey,
    };

    const response = await axios.post(gptUrl, chatgptData, { headers: headers }).catch(() => {
      return false;
    });

    if (!response) {
      throw new Error('Failed to get response from ChatGPT');
    }

    let content = response.data.choices[0].message.content;
    content = content.replace(/["']/g, '');
    return JSON.stringify({ text: content, quoted: false });

  } catch (error) {
    console.log('error send gptchat', error);
    return false;
  }
}

async function sendGemini({ command, from, geminiKey, participantNumber, systemInstructions, bufferImage, model, deviceToken }) {
  try {
    const ai = new GoogleGenAI({ apiKey: geminiKey });
    const GEMINI_MODEL = model || process.env.GEMINI_MODEL;

    const generationConfig = {
      maxOutputTokens: 1024,
      temperature: 0.9,
      topP: 0.8,
      topK: 40
    };

    const messageParts = [{ text: command }];

    if (bufferImage) {
      const base64Image = Buffer.isBuffer(bufferImage) ? bufferImage.toString('base64') : bufferImage;
      messageParts.push({
        inlineData: {
          mimeType: 'image/jpeg',
          data: base64Image
        }
      });
    }

    let content;
    const sessionKey = deviceToken + ':' + from;

    if (!bufferImage) {
      let chat;
      if (!chatSessions.has(sessionKey)) {
        chat = ai.chats.create({
          model: GEMINI_MODEL,
          config: {
            systemInstruction: systemInstructions,
            ...generationConfig
          }
        });
        chatSessions.set(sessionKey, chat);
      } else {
        chat = chatSessions.get(sessionKey);
      }

      const response = await chat.sendMessage({ message: messageParts });
      content = response.text;
    } else {
      const response = await ai.models.generateContent({
        model: GEMINI_MODEL,
        contents: messageParts,
        config: {
          systemInstruction: systemInstructions,
          ...generationConfig
        }
      });
      content = response.text;
    }

    content = String(content || '').replace(/["']/g, '');
    return JSON.stringify({
      text: content,
      quoted: false,
      participantNumber
    });
  } catch (error) {
    console.log('error send Gemini', error);
    return false;
  }
}

async function sendClaude({ command, from, claudeKey, participantNumber, systemInstructions, bufferImage, model }) {
  try {
    const claudeUrl = process.env.CLAUDE_URL;

    let messageContent;
    if (bufferImage) {
      const base64Image = bufferImage;
      messageContent = [
        {
          type: "text",
          text: command
        },
        {
          type: "image",
          source: {
            type: "base64",
            media_type: "image/jpeg",
            data: base64Image
          }
        }
      ];
    } else {
      messageContent = command;
    }

    const claudeData = {
      model: model || process.env.CLAUDE_MODEL,
      max_tokens: 1024,
      messages: [
        {
          role: 'user',
          content: messageContent
        },
      ],
      system: systemInstructions,
    };

    const headers = {
      'Content-Type': 'application/json',
      'x-api-key': claudeKey,
      'anthropic-version': '2023-06-01'
    };

    const response = await axios.post(claudeUrl, claudeData, { headers: headers });

    if (response.data && response.data.content && response.data.content[0] && response.data.content[0].text) {
      let content = response.data.content[0].text;
      content = content.replace(/["']/g, '');
      return JSON.stringify({
        text: content,
        quoted: false,
      });
    } else {
      console.log('Unexpected response structure:', response.data);
      return false;
    }
  } catch (error) {
    console.log('Error sending Claude request:');
    if (error.response) {
      console.log('Error data:', error.response.data);
      console.log('Error status:', error.response.status);
      console.log('Error headers:', error.response.headers);
    } else if (error.request) {
      console.log('Error request:', error.request);
    } else {
      console.log('Error message:', error.message);
    }
    return false;
  }
}

async function sendBexa({
  command,
  from,
  bexaKey,
  participant,
  bufferImage,
  device
}) {
  const convDir  = path.join(__dirname, "conversations");
  const convFile = path.join(convDir, `${device.body}_${from}.json`);

  try { await fs.access(convDir); } catch { await fs.mkdir(convDir, { recursive: true }); }

  let history = [];
  try { const raw = await fs.readFile(convFile, "utf8"); history = JSON.parse(raw); } catch { history = []; }

  const isText = typeof command === 'string' && command.trim().length > 0;
  if (isText) {
    history.push({ role: "user", content: command.trim() });
    if (history.length > 10) history = history.slice(-10);
    await fs.writeFile(convFile, JSON.stringify(history), "utf8");
  }

  const form = new FormData();
  history.forEach((msg, i) => {
    form.append(`messages[${i}][role]`, msg.role);
    form.append(`messages[${i}][content]`, msg.content);
  });

  form.append("from", from || "");
  if (device) form.append("device", JSON.stringify(device));

  if (bufferImage) {
    const buffer = Buffer.from(bufferImage, "base64");
    form.append("file", buffer, {
      filename: "file.jpg",
      contentType: "image/jpeg"
    });
  }

  const headers  = {
    ...form.getHeaders(),
    Authorization: `Bearer ${bexaKey}`
  };
  const response = await axios.post(process.env.BEXA_URL, form, { headers });

  const assistantText = response.data?.results?.[0]?.message?.content;
  if (assistantText) {
    const content = String(assistantText).replace(/["']/g, "");
    history.push({ role: "assistant", content });
    if (history.length > 10) history = history.slice(-10);
    await fs.writeFile(convFile, JSON.stringify(history), "utf8");
    return JSON.stringify({ text: content, quoted: false });
  }

  return false;
}

async function sendGroq({ command, from, groqKey, participant, systemInstructions, bufferImage, model }) {
  try {
    const groqUrl = 'https://api.groq.com/openai/v1/chat/completions';
    let messages;

    if (bufferImage) {
      messages = [
        {
          role: 'user',
          content: [
            { type: 'text', text: command },
            { type: 'image_url', image_url: { url: `data:image/jpeg;base64,${bufferImage}` } }
          ]
        }
      ];
    } else {
      messages = [
        { role: 'system', content: systemInstructions || '' },
        { role: 'user', content: command }
      ];
    }

    const data = {
      model: model || 'llama-3.3-70b-versatile',
      messages: messages,
      max_tokens: 1024
    };

    const headers = {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + groqKey
    };

    const response = await axios.post(groqUrl, data, { headers }).catch(() => false);
    if (!response) return false;

    let content = response.data.choices[0].message.content;
    content = content.replace(/["']/g, '');
    return JSON.stringify({ text: content, quoted: false });
  } catch (error) {
    console.log('error sendGroq', error);
    return false;
  }
}

async function sendDeepseek({ command, from, deepseekKey, participant, systemInstructions, bufferImage, model }) {
  try {
    const deepseekUrl = 'https://api.deepseek.com/chat/completions';
    let messages;

    if (bufferImage) {
      messages = [
        {
          role: 'user',
          content: [
            { type: 'text', text: command },
            { type: 'image_url', image_url: { url: `data:image/jpeg;base64,${bufferImage}` } }
          ]
        }
      ];
    } else {
      messages = [
        { role: 'system', content: systemInstructions || '' },
        { role: 'user', content: command }
      ];
    }

    const data = {
      model: model || 'deepseek-v4-flash',
      messages: messages,
      max_tokens: 1024
    };

    const headers = {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + deepseekKey
    };

    const response = await axios.post(deepseekUrl, data, { headers }).catch(() => false);
    if (!response) return false;

    let content = response.data.choices[0].message.content;
    content = content.replace(/["']/g, '');
    return JSON.stringify({ text: content, quoted: false });
  } catch (error) {
    console.log('error sendDeepseek', error);
    return false;
  }
}

function clearChatSession() {
  console.log("The session has been cleaned.");
  chatSessions.clear();
}

export { IncomingMessage, clearChatSession };
