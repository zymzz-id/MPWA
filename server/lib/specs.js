import { OSUtils } from 'node-os-utils';
const osu = new OSUtils();

let cpuCount, cpuModel;
(async () => {
  const info = await osu.cpu.info();
  if (info.success) {
    cpuCount = info.data.cores;
    cpuModel = info.data.model;
  }
})();

async function sendStats(socket) {
  const cpuResult = await osu.cpu.usage();
  const memResult = await osu.memory.info();

  const cpuPct = cpuResult.success ? cpuResult.data : 0;
  const memData = memResult.success ? memResult.data : {};
  const toMb = (bytes) => Math.round((bytes || 0) / (1024 * 1024));

  socket.emit('serverStats', {
    cpu:        cpuPct.toFixed(2),
    ram_total:  toMb(memData.total?.bytes),
    ram_used:   toMb(memData.used?.bytes),
    ram_free:   toMb(memData.free?.bytes),
    cpu_name:   cpuCount,
    cpu_model:  cpuModel
  });
}

function init(socket) {
  sendStats(socket);
  socket._specsInterval = setInterval(() => sendStats(socket), 2000);
  socket.on('disconnect', () => clearInterval(socket._specsInterval));
}

export { init };
