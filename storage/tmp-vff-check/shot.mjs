// Usage : node shot.mjs <url> <out.png> [waitMs] [jsToEvalBeforeShot]
import { writeFileSync } from 'node:fs';
const [url, out, waitMs = '12000', evalJs = ''] = process.argv.slice(2);
const target = await (await fetch('http://127.0.0.1:9333/json/new?about:blank', { method: 'PUT' })).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
let id = 0; const pending = new Map(); const logs = [];
const send = (method, params = {}) => new Promise((res) => { const i = ++id; pending.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });
ws.onmessage = (m) => {
  const d = JSON.parse(m.data);
  if (d.id && pending.has(d.id)) { pending.get(d.id)(d.result ?? d.error); pending.delete(d.id); return; }
  if (d.method === 'Runtime.consoleAPICalled') logs.push(`[console.${d.params.type}] ` + d.params.args.map((a) => a.value ?? a.description ?? '').join(' '));
  if (d.method === 'Runtime.exceptionThrown') logs.push('[exception] ' + (d.params.exceptionDetails.exception?.description ?? d.params.exceptionDetails.text));
  if (d.method === 'Log.entryAdded') logs.push(`[log.${d.params.entry.level}] ${d.params.entry.text} ${d.params.entry.url ?? ''}`);
};
await new Promise((r) => (ws.onopen = r));
await send('Runtime.enable'); await send('Log.enable'); await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1400, height: 1000, deviceScaleFactor: 1, mobile: false });
await send('Page.navigate', { url });
await new Promise((r) => setTimeout(r, Number(waitMs)));
if (evalJs) { const r = await send('Runtime.evaluate', { expression: evalJs, awaitPromise: true, returnByValue: true }); logs.push('[eval] ' + JSON.stringify(r?.result?.value ?? r)); await new Promise((r) => setTimeout(r, 6000)); }
const shot = await send('Page.captureScreenshot', { format: 'png' });
writeFileSync(out, Buffer.from(shot.data, 'base64'));
console.log(logs.filter((l) => !l.includes('[vite]')).join('\n') || '(aucun log)');
await fetch(`http://127.0.0.1:9333/json/close/${target.id}`);
process.exit(0);
