import {
  downloadContentFromMessage,
  prepareWAMessageMedia,
  generateWAMessageFromContent,
} from 'baileys';
import mime from 'mime-types';
import fs from 'fs';
import { join } from 'path';
import axios from 'axios';
import { ulid } from 'ulid';
import sharp from 'sharp';
import os from 'os';

function formatReceipt(phoneNumber) {
  try {
    if (phoneNumber.endsWith('@g.us')) {
      return phoneNumber;
    }
    if (phoneNumber.endsWith('@newsletter')) {
      return phoneNumber;
    }
    let formattedNumber = phoneNumber.replace(/\D/g, '');
    if (formattedNumber.startsWith('08')) {
      formattedNumber = '62' + formattedNumber.substr(1);
    }
    if (formattedNumber.startsWith('00')) {
      formattedNumber = formattedNumber.substr(2);
    }
    if (!formattedNumber.endsWith('@s.whatsapp.net')) {
      formattedNumber += '@s.whatsapp.net';
    }
    return formattedNumber;
  } catch (error) {
    return phoneNumber;
  }
}

async function asyncForEach(array, callback) {
  for (let index = 0; index < array.length; index++) {
    await callback(array[index], index, array);
  }
}

async function removeForbiddenCharacters(inputString) {
  return inputString.replace(/[\x00-\x1F\x7F-\x9F'\\"]/g, '');
}

async function parseIncomingMessage(incomingMessage) {
  const msgObj = incomingMessage.message || {};
  const messageType = Object.keys(msgObj)[0];
  let messageContent = '';

  if (msgObj.interactiveResponseMessage) {
    const ir = msgObj.interactiveResponseMessage;
    messageContent = (ir.body?.text || '').split('\n')[0];
    if (!messageContent) {
      try {
        const nfr = ir.nativeFlowResponseMessage;
        if (nfr && nfr.paramsJson) {
          const params = JSON.parse(nfr.paramsJson);
          messageContent = params.display_text || params.title || '';
        }
      } catch(e) {}
    }
  } else if (messageType === 'conversation' && msgObj.conversation) {
    messageContent = msgObj.conversation;
  } else if (messageType == 'imageMessage' && msgObj.imageMessage?.caption) {
    messageContent = msgObj.imageMessage.caption;
  } else if (messageType == 'videoMessage' && msgObj.videoMessage?.caption) {
    messageContent = msgObj.videoMessage.caption;
  } else if (messageType == 'extendedTextMessage' && msgObj.extendedTextMessage?.text) {
    messageContent = msgObj.extendedTextMessage.text;
  } else if (messageType == 'templateButtonReplyMessage' && msgObj.templateButtonReplyMessage?.selectedDisplayText) {
    messageContent = msgObj.templateButtonReplyMessage.selectedDisplayText;
  } else if (msgObj.listResponseMessage?.title) {
    messageContent = msgObj.listResponseMessage.title;
  } else if (msgObj.buttonsResponseMessage?.selectedDisplayText) {
    messageContent = msgObj.buttonsResponseMessage.selectedDisplayText;
  }

  const lowerCaseContent = (messageContent || '').toLowerCase();
  const sanitizedContent = await removeForbiddenCharacters(lowerCaseContent);
  const pushName = incomingMessage?.pushName || '';
  const remoteJid = incomingMessage.key.remoteJid && incomingMessage.key.remoteJid.endsWith('s.whatsapp.net') ?
                       incomingMessage.key.remoteJid :
                       incomingMessage.key.remoteJidAlt && incomingMessage.key.remoteJidAlt.endsWith('s.whatsapp.net') ?
                       incomingMessage.key.remoteJidAlt :
                       null;
  const senderNumber = remoteJid ? remoteJid?.split('@')[0] : '';

  let imageBuffer = null;
  const mediaMap = {
    imageMessage: 'image',
    videoMessage: 'video',
    audioMessage: 'audio',
    documentMessage: 'document',
    stickerMessage: 'sticker'
  };

  let targetMessage = msgObj[messageType];
  let streamType = mediaMap[messageType];

  if (messageType === 'documentWithCaptionMessage' && msgObj.documentWithCaptionMessage?.message?.documentMessage) {
    targetMessage = msgObj.documentWithCaptionMessage.message.documentMessage;
    streamType = 'document';
  } else if (messageType === 'ephemeralMessage' && msgObj.ephemeralMessage?.message) {
    const ephMessageType = Object.keys(msgObj.ephemeralMessage.message)[0];
    targetMessage = msgObj.ephemeralMessage.message[ephMessageType];
    streamType = mediaMap[ephMessageType];
  } else if (messageType === 'viewOnceMessage' && msgObj.viewOnceMessage?.message) {
    const viewOnceType = Object.keys(msgObj.viewOnceMessage.message)[0];
    targetMessage = msgObj.viewOnceMessage.message[viewOnceType];
    streamType = mediaMap[viewOnceType];
  }

  if (streamType && targetMessage) {
    try {
      const mediaStream = await downloadContentFromMessage(targetMessage, streamType);
      let buffer = Buffer.from([]);
      for await (const chunk of mediaStream) {
        buffer = Buffer.concat([buffer, chunk]);
      }
      imageBuffer = buffer.toString('base64');
    } catch (error) {}
  }

  return {
    command: sanitizedContent,
    bufferImage: imageBuffer,
	type: streamType ?? null,
    from: senderNumber,
  };
}

function getSavedPhoneNumber(token) {
  return new Promise((resolve, reject) => {
    const savedPhoneNumber = token;
    if (savedPhoneNumber) {
      setTimeout(() => {
        resolve(savedPhoneNumber);
      }, 2000);
    } else {
      reject(new Error('Phone number not found.'));
    }
  });
}

const convertWebpToJpg = async (imageUrl) => {
  const response = await axios.get(imageUrl, { responseType: 'arraybuffer' });
  const jpgBuffer = await sharp(Buffer.from(response.data)).jpeg({ quality: 90 }).toBuffer();
  const tmpPath = join(os.tmpdir(), `${ulid(Date.now())}.jpg`);
  fs.writeFileSync(tmpPath, jpgBuffer);
  return tmpPath;
};

const prepareMediaMessage = async (socket, mediaOptions) => {
  let convertedTmpFile = null;
  try {
    if (mediaOptions.mediatype === 'image') {
      let isWebp = false;
      const urlLower = mediaOptions.media.toLowerCase().split('?')[0];
      if (urlLower.endsWith('.webp')) {
        isWebp = true;
      } else {
        try {
          const headResp = await axios.head(mediaOptions.media);
          const ct = (headResp.headers['content-type'] || '').toLowerCase();
          if (ct.includes('image/webp')) {
            isWebp = true;
          }
        } catch (_) {}
      }
      if (isWebp) {
        convertedTmpFile = await convertWebpToJpg(mediaOptions.media);
        mediaOptions.media = convertedTmpFile;
      }
    }

    const preparedMedia = await prepareWAMessageMedia(
      { [mediaOptions.mediatype]: { url: mediaOptions.media } },
      { upload: socket.waUploadToServer }
    );
    const messageKey = mediaOptions.mediatype + 'Message';

    if (mediaOptions.mediatype === 'document' && !mediaOptions.fileName) {
      const fileNameRegex = /.*\/(.+?)\./;
      const fileNameMatch = fileNameRegex.exec(mediaOptions.media);
      mediaOptions.fileName = fileNameMatch[1];
    }

    let mimetype = mime.lookup(mediaOptions.media);
    if (!mimetype) {
      const response = await axios.head(mediaOptions.media);
      mimetype = response.headers['content-type'];
    }
    if (mediaOptions.media.includes('.cdr')) {
      mimetype = 'application/cdr';
    }

    preparedMedia[messageKey].caption = mediaOptions?.caption;
    preparedMedia[messageKey].mimetype = mimetype;
    preparedMedia[messageKey].fileName = mediaOptions.fileName;

    if (mediaOptions.mediatype === 'video') {
      preparedMedia[messageKey].jpegThumbnail = Uint8Array.from(
        fs.readFileSync(join(process.cwd(), 'public', 'images', 'video-cover.png'))
      );
      preparedMedia[messageKey].gifPlayback = false;
    }

    let userJid = socket.user.id.replace(/:\d+/, '');
    const result = await generateWAMessageFromContent(
      '',
      { [messageKey]: { ...preparedMedia[messageKey] } },
      { userJid: userJid }
    );
    if (convertedTmpFile) fs.unlinkSync(convertedTmpFile);
    return result;
  } catch (prepareError) {
    if (convertedTmpFile) try { fs.unlinkSync(convertedTmpFile); } catch (_) {}
    return false;
  }
};

class Button {
  constructor(buttonData) {
    this.type = buttonData.type || 'reply';
    this.displayText = buttonData.displayText || '';
    this.id = buttonData.id;
    this.url = buttonData.url;
    this.copyCode = buttonData.copyCode;
    this.phoneNumber = buttonData.phoneNumber;
    this.type === 'reply' && !this.id && (this.id = ulid());
    this.mapType = new Map([
      ['reply', 'quick_reply'],
      ['copy', 'cta_copy'],
      ['url', 'cta_url'],
      ['call', 'cta_call'],
    ]);
  }
  get ['typeButton']() {
    return this.mapType.get(this.type);
  }
  ['toJSONString']() {
    const stringify = (val) => JSON.stringify(val),
      typeMap = {
        call: () =>
          stringify({
            display_text: this.displayText,
            phone_number: this.phoneNumber,
          }),
        reply: () =>
          stringify({
            display_text: this.displayText,
            id: this.id,
          }),
        copy: () =>
          stringify({
            display_text: this.displayText,
            copy_code: this.copyCode,
          }),
        url: () =>
          stringify({
            display_text: this.displayText,
            url: this.url,
            merchant_url: this.url,
          }),
      };
    return typeMap[this.type]?.() || '';
  }
}

const formatButtonMsg = async (
  buttons,
  footerText,
  bodyText,
  sock,
  imageUrl
) => {
  const mediaPrepared = await (async () => {
    if (imageUrl) {
      return await prepareMediaMessage(sock, {
        mediatype: 'image',
        media: imageUrl,
      });
    }
  })();
  return {
    interactiveMessage: {
      carouselMessage: {
        cards: [
          {
            body: {
              text: (() => {
                return bodyText;
              })(),
            },
            footer: { text: footerText ?? '..' },
            header: (() => {
              if (mediaPrepared?.message?.imageMessage) {
                return {
                  hasMediaAttachment: !!mediaPrepared.message.imageMessage,
                  imageMessage: mediaPrepared.message.imageMessage,
                };
              }
            })(),
            nativeFlowMessage: {
              buttons: buttons.map((btn) => {
                return {
                  name: btn.typeButton,
                  buttonParamsJson: btn.toJSONString(),
                };
              }),
              messageParamsJson: JSON.stringify({
                from: 'api',
                templateId: ulid(Date.now()),
              }),
            },
          },
        ],
        messageVersion: 1,
      },
    },
  };
};

class Row {
  constructor(rowData) {
    Object.assign(this, rowData);
    if (!this.id) {
      this.id = ulid(Date.now());
    }
    if (!this.header) {
      this.header = '';
    }
  }
}

class ListSection {
  constructor(sectionData) {
    Object.assign(this, sectionData);
    this.rows = sectionData.rows.map((row) => new Row(row));
  }
}

class Section {
  constructor(sectionData) {
    Object.assign(this, sectionData);
    this.list = sectionData.list.map((item) => new ListSection(item));
  }
  toSectionsString() {
    return JSON.stringify({
      title: this.buttonText,
      sections: this.list,
    });
  }
}

const formatListMsg = async (
  sections,
  footerText,
  bodyText,
  sock,
  imageUrl
) => {
  const mediaPrepared = await (async () => {
    if (imageUrl) {
      return await prepareMediaMessage(sock, {
        mediatype: 'image',
        media: imageUrl,
      });
    }
  })();
  return {
    interactiveMessage: {
      carouselMessage: {
        cards: [
          {
            body: {
              text: (() => {
                return bodyText;
              })(),
            },
            footer: { text: footerText ?? '..' },
            header: (() => {
              if (mediaPrepared?.message?.imageMessage) {
                return {
                  hasMediaAttachment: !!mediaPrepared.message.imageMessage,
                  imageMessage: mediaPrepared.message.imageMessage,
                };
              }
            })(),
            nativeFlowMessage: {
              buttons: sections.map((section) => {
                return {
                  name: 'single_select',
                  buttonParamsJson: section.toSectionsString(),
                };
              }),
              messageParamsJson: JSON.stringify({
                from: 'api',
                templateId: ulid(Date.now()),
              }),
            },
          },
        ],
        messageVersion: 1,
      },
    },
  };
};

export {
  formatReceipt,
  asyncForEach,
  removeForbiddenCharacters,
  parseIncomingMessage,
  getSavedPhoneNumber,
  prepareMediaMessage,
  Button,
  formatButtonMsg,
  Row,
  ListSection,
  Section,
  formatListMsg,
};