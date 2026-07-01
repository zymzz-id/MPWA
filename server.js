import * as wa from "./server/whatsapp.js";
import fs from "fs";
import { db } from './server/database/index.js';
import { init as specsInit } from './server/lib/specs.js';
import 'dotenv/config';
import lib from "./server/lib/index.js";
import { init as pluginInit, callHook, getPluginModules, reload as reloadPlugins } from './server/pluginLoader.js';
globalThis.log = lib.log;

import express from "express";
const app = express();
import http from "http";
const server = http.createServer(app);

import { Server } from "socket.io";
const io = new Server(server, {
  pingInterval: 25000,
  pingTimeout: 10000,
});

const port = process.env.PORT_NODE;
wa.setSocketIO(io);

app.use((req, res, next) => {
  res.set("Cache-Control", "no-store");
  req.io = io;
  next();
});

import bodyParser from "body-parser";

app.use(
  bodyParser.urlencoded({
    extended: false,
    limit: "50mb",
    parameterLimit: 100000,
  })
);

app.use(bodyParser.json());
app.use(express.static("src/public"));

import router from "./server/router/index.js";
app.use(router);

let pluginRouter = express.Router();
app.use((req, res, next) => pluginRouter(req, res, next));

async function rebuildPluginRouter() {
  const newRouter = express.Router();
  const routePlugins = await getPluginModules('route');
  for (const { module: routeMod } of routePlugins) {
    if (routeMod.default && typeof routeMod.default === 'function') {
      routeMod.default(newRouter);
    } else if (routeMod.register && typeof routeMod.register === 'function') {
      routeMod.register(newRouter);
    }
  }
  pluginRouter = newRouter;
}

router.post("/backend-reload-plugins", async (req, res) => {
  try {
    const result = await reloadPlugins({ io, app, server, rebuildPluginRouter });
    res.json({ status: "success", newPlugins: result.newPlugins, removedPlugins: result.removedPlugins });
  } catch (e) {
    res.status(500).json({ status: "error", message: e.message });
  }
});

(async () => {
  await pluginInit();
  await rebuildPluginRouter();

  await callHook('chat', 'setIO', io);
  await callHook('server', 'onInit', { io, app, server });

  io.on('connection', async (socket) => {
    console.log("A user connected");
    socket.on('specs', () => {
      specsInit(socket);
    });
    socket.on('StartConnection', data => wa.connectToWhatsApp(data, io));
    socket.on('ConnectViaCode', data => wa.connectToWhatsApp(data, io, true));
    socket.on('LogoutDevice', device => wa.deleteCredentials(device, io));
    socket.on('disconnect', () => console.log('A user disconnected:', socket.id));

    const serverPlugins = await getPluginModules('server');
    for (const { module } of serverPlugins) {
      if (typeof module.onConnection === 'function') {
        try { module.onConnection(socket, io); } catch (e) { console.error('Plugin onConnection error:', e.message); }
      }
    }
  });

  server.listen(port, () => {
    console.log(`Server running and listening on port: ${port}`);
  });

  db.query("SELECT * FROM devices WHERE status = 'Connected'", (err, results) => {
    if (err) {
      console.error('Error executing query:', err);
      return;
    }
    results.forEach(row => {
      const number = row.body;
      if (/^\d+$/.test(number)) {
        wa.connectToWhatsApp(number);
      }
    });
  });
})();
