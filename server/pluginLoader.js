import { dbQuery } from './database/index.js';
import fs from 'fs';
import path from 'path';
import { fileURLToPath, pathToFileURL } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const pluginsDir = path.join(__dirname, '..', 'plugins');

let enabledPlugins = [];
let loadedModules = {};
let importTimestamp = Date.now();

async function init() {
  try {
    const rows = await dbQuery("SELECT slug FROM plugins WHERE is_enabled = 1");
    enabledPlugins = rows.map(r => r.slug);
  } catch (e) {
    enabledPlugins = [];
  }
  loadedModules = {};
  importTimestamp = Date.now();
}

async function getPluginModules(hookName) {
  if (loadedModules[hookName]) return loadedModules[hookName];
  const modules = [];
  for (const slug of enabledPlugins) {
    const hookPath = path.join(pluginsDir, slug, 'nodejs', `${hookName}.js`);
    if (fs.existsSync(hookPath)) {
      try {
        const fileUrl = pathToFileURL(hookPath).href + '?t=' + importTimestamp;
        const mod = await import(fileUrl);
        modules.push({ slug, module: mod });
      } catch (e) {
        console.error(`Failed to load plugin hook ${slug}/nodejs/${hookName}.js:`, e.message);
      }
    }
  }
  loadedModules[hookName] = modules;
  return modules;
}

async function callHook(hookName, fnName, ...args) {
  const modules = await getPluginModules(hookName);
  for (const { module } of modules) {
    if (typeof module[fnName] === 'function') {
      try {
        await module[fnName](...args);
      } catch (e) {
        console.error(`Plugin hook error [${hookName}.${fnName}]:`, e.message);
      }
    }
  }
}

async function callHookForSlugs(hookName, fnName, slugs, ...args) {
  const modules = await getPluginModules(hookName);
  for (const { slug, module } of modules) {
    if (slugs.includes(slug) && typeof module[fnName] === 'function') {
      try {
        await module[fnName](...args);
      } catch (e) {
        console.error(`Plugin hook error [${hookName}.${fnName}]:`, e.message);
      }
    }
  }
}

async function callHookUntilHandled(hookName, fnName, ...args) {
  const modules = await getPluginModules(hookName);
  for (const { module } of modules) {
    if (typeof module[fnName] === 'function') {
      try {
        const result = await module[fnName](...args);
        if (result === true) return true;
      } catch (e) {
        console.error(`Plugin hook error [${hookName}.${fnName}]:`, e.message);
      }
    }
  }
  return false;
}

async function callHookCollect(hookName, fnName, ...args) {
  const modules = await getPluginModules(hookName);
  const results = [];
  for (const { module } of modules) {
    if (typeof module[fnName] === 'function') {
      try {
        const result = await module[fnName](...args);
        if (result !== undefined) results.push(result);
      } catch (e) {
        console.error(`Plugin hook error [${hookName}.${fnName}]:`, e.message);
      }
    }
  }
  return results;
}

async function reload({ io, app, server, rebuildPluginRouter }) {
  const oldEnabled = [...enabledPlugins];
  await init();

  const newPlugins = enabledPlugins.filter(s => !oldEnabled.includes(s));
  const removedPlugins = oldEnabled.filter(s => !enabledPlugins.includes(s));

  await rebuildPluginRouter();

  if (newPlugins.length > 0) {
    await callHookForSlugs('chat', 'setIO', newPlugins, io);
    await callHookForSlugs('server', 'onInit', newPlugins, { io, app, server });
  }

  console.log(`Plugins reloaded. New: [${newPlugins.join(', ')}], Removed: [${removedPlugins.join(', ')}]`);

  return { newPlugins, removedPlugins };
}

export { init, getPluginModules, callHook, callHookUntilHandled, callHookCollect, enabledPlugins, reload };
