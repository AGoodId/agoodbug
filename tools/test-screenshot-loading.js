'use strict';
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/agoodbug.js'), 'utf8');
const vendor = fs.readFileSync(path.join(root, 'vendor/html2canvas.min.js'));
// Expose only the real closure's test handles in the served test copy.
const fixtureSource = source.replace('\tfunction boot() {', '\twindow.testWidget = {loadHtml2Canvas, instance: () => agoodbugInstance};\n\tfunction boot() {');
assert.notEqual(fixtureSource, source);
const counts = new Map();
let modernScriptFetches = 0;
const sockets = new Set();
const server = http.createServer((req, res) => {
 const url = new URL(req.url, 'http://localhost');
 if (url.pathname === '/widget.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(fixtureSource); return; }
 if (url.pathname === '/widget.css') { res.setHeader('Content-Type', 'text/css'); res.end(fs.readFileSync(path.join(root, 'public/css/agoodbug.css'))); return; }
 if (url.pathname === '/modern.css') {
  if(req.headers['sec-fetch-dest']==='empty') modernScriptFetches++;
  res.setHeader('Content-Type', 'text/css');
  res.end(`body{background-image:linear-gradient(120deg,color(display-p3 .1 .5 .6),oklch(.8 .12 80))}
   #modern{color:color(display-p3 .1 .2 .3);background-color:color-mix(in srgb,#fff 90%,#007d8f);box-shadow:0 3px 12px color-mix(in srgb,#000 25%,transparent),inset 0 0 1px color(display-p3 .1 .2 .3);text-shadow:1px 1px oklch(.9 .02 30)}
   #modern::before{content:'';display:block;width:60px;height:30px;background-color:color-mix(in srgb,#007d8f 80%,white);box-shadow:0 2px 4px color(display-p3 .2 .2 .2 / .3)}
   #modern::after{content:'color(display-p3) stays text';display:block;color:oklch(.4 .1 200)}`);
  return;
 }
 if (url.pathname === '/vendor.js') {
  const mode = url.searchParams.get('mode'); const n = (counts.get(mode) || 0) + 1; counts.set(mode, n);
  if (mode === 'fail' && n === 1) { res.writeHead(503); res.end(); return; }
  if (mode === 'invalid' && n === 1) { res.setHeader('Content-Type', 'text/javascript'); res.end('window.invalidLibrary = true;'); return; }
  if (mode === 'timeout' && n === 1) return;
  const send = () => { res.setHeader('Content-Type', 'text/javascript'); res.end(vendor); };
  if (mode.startsWith('slow')) setTimeout(send, 250); else send(); return;
 }
 if (url.pathname === '/') {
  const mode = url.searchParams.get('mode') || 'normal';
  if(mode==='csp'||mode==='modern-csp') res.setHeader('Content-Security-Policy', "script-src 'nonce-fixture-nonce'; style-src 'self' 'nonce-fixture-nonce'; object-src 'none'");
  res.setHeader('Content-Type', 'text/html');
  res.end(`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/widget.css"><style nonce="fixture-nonce">body{margin:0;min-height:700px;background:linear-gradient(120deg,#1a7b8a,#e9ab43);font:18px sans-serif}main{padding:40px;width:600px}h1{color:white}section{padding:30px;background:#fff8;margin:20px 0}</style>${mode.startsWith('modern')?'<link rel="stylesheet" href="/modern.css">':''}</head><body><main><h1>Screenshot regression fixture</h1><section id="modern" style="outline-color:oklch(.5 .1 200)!important;--keep:yes">Public test content. No customer data or report submission.</section></main><div id="agoodbug-widget"></div><script nonce="fixture-nonce">window.agoodbugConfig={enabled:true,html2canvasUrl:'/vendor.js?mode=${mode}',buttonStyle:'tab-side',tabLabel:'Feedback',showEmailField:false,strings:{buttonTitle:'Report',commentLabel:'Describe',submitButton:'Send',cancelButton:'Cancel',closeButton:'Close',screenshotFailed:'Screenshot unavailable'}};Object.defineProperty(navigator,'mediaDevices',{value:undefined,configurable:true});</script><script id="agoodbug-js" nonce="fixture-nonce" src="/widget.js"></script></body></html>`); return;
 }
 res.writeHead(404); res.end();
});
server.on('connection', socket => { sockets.add(socket); socket.on('close', () => sockets.delete(socket)); });
(async () => {
 await new Promise(r => server.listen(0, '127.0.0.1', r));
 const base = `http://127.0.0.1:${server.address().port}`;
 const browser = await chromium.launch({ headless: true, ...(process.env.AGOODBUG_CHROME_PATH ? { executablePath: process.env.AGOODBUG_CHROME_PATH } : {}) });
 let cases = 0;
 try {
  const context = await browser.newContext({ viewport: { width: 1100, height: 800 }, reducedMotion: 'reduce' });
  const open = async mode => { const p = await context.newPage(); await p.goto(`${base}/?mode=${mode}`); await p.waitForFunction(() => !!window.testWidget?.instance()); return p; };
  const takeScreenshot = async p => {
   await p.locator('.agoodbug-button').click(); await p.locator('[data-mode="screenshot"]').click();
   await p.mouse.move(130, 120); await p.mouse.down(); await p.mouse.move(340, 260); await p.mouse.up();
  };
  const preview = async p => { await p.waitForFunction(() => { const i = document.querySelector('.agoodbug-modal__preview img'); return i && i.complete && i.naturalWidth > 0 && document.querySelector('.agoodbug-modal.is-open'); }); };
  let p = await open('normal');
  assert.equal(counts.get('normal') || 0, 0); assert.equal(await p.locator('img').count(), 0); cases++;
  await p.locator('.agoodbug-button').click(); await p.locator('[data-mode="general"]').click();
  assert.equal(await p.locator('.agoodbug-modal.is-open').count(), 1); assert.equal(await p.locator('img').count(), 0); assert.equal(counts.get('normal') || 0, 0);
  await p.locator('.agoodbug-modal__cancel').click(); cases++;
  await p.evaluate(() => { Object.defineProperty(navigator, 'mediaDevices', {value:{getDisplayMedia(){}},configurable:true}); const widget=testWidget.instance(); widget.captureViaScreenShare=async()=>{window.screenPath=true;}; return widget.captureScreenshot(); });
  assert.equal(await p.evaluate(() => window.screenPath), true); assert.equal(counts.get('normal') || 0, 0); cases++;
  await p.close();

  p = await open('concurrent');
  assert.equal(await p.evaluate(async () => { const a=testWidget.loadHtml2Canvas(),b=testWidget.loadHtml2Canvas(); return a===b && (await a)===(await b); }), true);
  assert.equal(counts.get('concurrent'), 1); cases++;
  await takeScreenshot(p); await preview(p); assert.equal(counts.get('concurrent'), 1);
  await p.locator('.agoodbug-modal__cancel').click(); assert.equal(await p.locator('img').count(), 0);
  await p.locator('.agoodbug-button').click(); await p.locator('[data-mode="general"]').click(); assert.equal(await p.locator('img').count(), 0);
  await p.locator('.agoodbug-modal__cancel').click(); await takeScreenshot(p); await preview(p); assert.equal(counts.get('concurrent'), 1); cases++;
  if(process.env.AGOODBUG_TEST_SCREENSHOT) await p.screenshot({path:process.env.AGOODBUG_TEST_SCREENSHOT});
  await p.close();

  p = await open('existing'); await p.evaluate(() => { window.html2canvas=()=>Promise.resolve('existing'); });
  assert.equal(await p.evaluate(async()=> (await testWidget.loadHtml2Canvas())===window.html2canvas),true); assert.equal(counts.get('existing') || 0,0);cases++;await p.close();

  p = await open('fail'); await takeScreenshot(p);
  await p.waitForFunction(()=>document.querySelector('.agoodbug-modal.is-open') && !document.querySelector('.agoodbug-modal__screenshot-notice').hidden);
  assert.equal(await p.locator('img').count(),0); assert.equal(await p.locator('.agoodbug-modal__submit').isEnabled(),true);
  await p.locator('#agoodbug-comment').fill('This report remains editable after a screenshot error.');
  await p.locator('.agoodbug-modal__cancel').click(); await takeScreenshot(p); await preview(p); assert.equal(counts.get('fail'),2);cases++;await p.close();

  p = await open('invalid');
  assert.equal(await p.evaluate(()=>testWidget.loadHtml2Canvas().then(()=>false,()=>true)),true);
  assert.equal(await p.evaluate(async()=>typeof await testWidget.loadHtml2Canvas()),'function'); assert.equal(counts.get('invalid'),2);cases++;await p.close();

  p = await open('slow-cancel'); await takeScreenshot(p); await p.keyboard.press('Escape');
  await p.waitForFunction(()=>typeof window.html2canvas==='function'); assert.equal(await p.locator('.agoodbug-modal.is-open').count(),0); assert.equal(await p.locator('img').count(),0);
  await takeScreenshot(p);await preview(p);assert.equal(counts.get('slow-cancel'),1);cases++;await p.close();

  p = await open('csp');await takeScreenshot(p);await preview(p);
  assert.equal(await p.locator('script[src*="/vendor.js"]').evaluate(e=>e.nonce),'fixture-nonce');
  assert.equal(counts.get('csp'),1);cases++;await p.close();

  for(const mode of ['modern','modern-csp']) {
   p=await open(mode);const requestsBefore=modernScriptFetches;
   const before=await p.locator('#modern').evaluate(e=>({css:e.style.cssText,priority:e.style.getPropertyPriority('outline-color'),shadow:getComputedStyle(e).boxShadow,pseudo:getComputedStyle(e,'::before').backgroundColor,styleCount:document.querySelectorAll('style').length}));
   assert.match(before.shadow,/color\(/);assert.match(before.pseudo,/color\(/);
   const patched=await p.evaluate(async()=>{
    const restore=await testWidget.instance().applyColorPatch();
    const e=document.querySelector('#modern'),s=getComputedStyle(e),b=getComputedStyle(e,'::before'),a=getComputedStyle(e,'::after');
    const values=[s.outlineColor,s.boxShadow,s.textShadow,b.backgroundColor,b.boxShadow,a.color,getComputedStyle(document.body).backgroundImage];
    const literal=a.content,nonce=[...document.querySelectorAll('style')].at(-1).nonce;
    restore.forEach(fn=>fn());return {values,literal,nonce};
   });
   assert.ok(patched.values.every(v=>!/(?:color|oklch|oklab|lab|lch|color-mix)\(/.test(v)),JSON.stringify(patched));
   assert.equal(patched.literal,'"color(display-p3) stays text"');assert.equal(patched.nonce,'fixture-nonce');
   await takeScreenshot(p);await preview(p);
   const after=await p.locator('#modern').evaluate(e=>({css:e.style.cssText,priority:e.style.getPropertyPriority('outline-color'),shadow:getComputedStyle(e).boxShadow,pseudo:getComputedStyle(e,'::before').backgroundColor,styleCount:document.querySelectorAll('style').length}));
   assert.deepEqual(after,before);assert.equal(modernScriptFetches,requestsBefore,'Colour normalization does not fetch stylesheets; the renderer may clone stylesheet links');
   assert.equal(await p.evaluate(()=>[...document.querySelectorAll('*')].some(e=>[...e.attributes].some(a=>a.name.startsWith('data-agoodbug-colour-')))),false);
   cases++;await p.close();
  }

  p=await open('modern-failure');
  const failureBefore=await p.locator('#modern').evaluate(e=>e.style.cssText);
  await p.evaluate(async()=>{await testWidget.loadHtml2Canvas();window.html2canvas=async()=>{throw new Error('Deliberate renderer failure after colour normalization');};});
  await takeScreenshot(p);
  await p.waitForFunction(()=>document.querySelector('.agoodbug-modal.is-open')&&!document.querySelector('.agoodbug-modal__screenshot-notice').hidden);
  assert.equal(await p.locator('#modern').evaluate(e=>e.style.cssText),failureBefore);
  assert.equal(await p.evaluate(()=>[...document.querySelectorAll('*')].some(e=>[...e.attributes].some(a=>a.name.startsWith('data-agoodbug-colour-')))),false);
  cases++;await p.close();

  p = await open('timeout'); await p.clock.install();
  await p.evaluate(()=>{window.timeoutResult=null;testWidget.loadHtml2Canvas().then(()=>window.timeoutResult='success',()=>window.timeoutResult='timeout');});
  await p.waitForFunction(()=>!!document.querySelector('script[src*="/vendor.js"]'));
  for(let i=0;i<200&&!counts.get('timeout');i++) await new Promise(r=>setTimeout(r,5));
  assert.equal(counts.get('timeout'),1); await p.clock.runFor(15001);await p.waitForFunction(()=>window.timeoutResult==='timeout');
  assert.equal(await p.locator('script[src*="/vendor.js"]').count(),0);
  assert.equal(await p.evaluate(async()=>typeof await testWidget.loadHtml2Canvas()),'function');assert.equal(counts.get('timeout'),2);cases++;await p.close();
  console.log(JSON.stringify({passed:true,cases,scope:'Full widget UI, real bundled html2canvas, cancellation, retry, timeout and dependency reuse; no reports submitted'}));
 } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{for(const socket of sockets)socket.destroy();server.close();});
