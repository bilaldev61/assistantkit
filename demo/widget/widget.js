/* AssistantKit reference widget — plain JS, no framework, no build step.
 * Drop this file on a page; it talks to POST /chat (see server.php in this folder).
 * The reply is rendered with textContent, never innerHTML: the assistant's words
 * are data, not markup. */
(function () {
  'use strict';

  var ENDPOINT = '/chat';
  var item = new URLSearchParams(location.search).get('item') || '';
  var chat = sessionStorage.getItem('ak.chat') || 'demo-' + Math.random().toString(36).slice(2, 10);

  sessionStorage.setItem('ak.chat', chat);

  var css = `
    .ak-bubble{position:fixed;right:22px;bottom:22px;z-index:9999;height:56px;padding:0 20px;border:0;border-radius:999px;
      background:#111827;color:#fff;font:600 15px/1 system-ui,sans-serif;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.25)}
    .ak-panel{position:fixed;right:22px;bottom:90px;z-index:9999;display:none;width:min(360px,calc(100vw - 44px));height:min(480px,70vh);
      background:#fff;border-radius:16px;box-shadow:0 18px 48px rgba(0,0,0,.28);overflow:hidden;
      font:15px/1.45 system-ui,sans-serif;color:#111827;flex-direction:column}
    .ak-panel[data-open="true"]{display:flex}
    .ak-head{padding:14px 16px;background:#111827;color:#fff;font-weight:600}
    .ak-head small{display:block;font-weight:400;opacity:.7;font-size:12px}
    .ak-log{flex:1;overflow-y:auto;padding:14px 16px;display:flex;flex-direction:column;gap:10px;background:#f8fafc}
    .ak-msg{max-width:85%;padding:10px 12px;border-radius:12px;white-space:pre-wrap;word-wrap:break-word}
    .ak-msg[data-who="them"]{background:#fff;border:1px solid #e5e7eb;align-self:flex-start}
    .ak-msg[data-who="you"]{background:#111827;color:#fff;align-self:flex-end}
    .ak-msg[data-who="them"][data-kind="rule"]{border-left:3px solid #10b981}
    .ak-form{display:flex;gap:8px;padding:12px;border-top:1px solid #e5e7eb}
    .ak-form input{flex:1;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font:inherit}
    .ak-form input:focus{outline:2px solid #111827;outline-offset:1px}
    .ak-form button{padding:0 16px;border:0;border-radius:10px;background:#111827;color:#fff;font:inherit;cursor:pointer}
    .ak-form button[disabled]{opacity:.4;cursor:progress}
    .ak-foot{font-size:11px;color:#6b7280;padding:0 12px 10px;display:flex;justify-content:space-between}
  `;

  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var bubble = document.createElement('button');
  bubble.className = 'ak-bubble';
  bubble.type = 'button';
  bubble.textContent = 'Ask about a listing';

  var panel = document.createElement('section');
  panel.className = 'ak-panel';
  panel.setAttribute('aria-label', 'Assistant');
  panel.innerHTML = '<div class="ak-head">Assistant<small></small></div>'
    + '<div class="ak-log" role="log" aria-live="polite"></div>'
    + '<form class="ak-form"><input name="message" autocomplete="off" placeholder="Type a message…" aria-label="Message">'
    + '<button type="submit">Send</button></form>'
    + '<div class="ak-foot"><span>Rules first, model optional</span><a href="#" data-reset>start over</a></div>';

  // The query string is visitor-controlled, so it is written as text, not markup.
  panel.querySelector('.ak-head small').textContent = item ? 'Listing: ' + item : 'Any page';

  document.body.appendChild(bubble);
  document.body.appendChild(panel);

  var log = panel.querySelector('.ak-log');
  var form = panel.querySelector('form');
  var input = form.querySelector('input');
  var send = form.querySelector('button');
  var started = false;

  function say(who, text, kind) {
    var line = document.createElement('div');
    line.className = 'ak-msg';
    line.dataset.who = who;
    if (kind) { line.dataset.kind = 'rule'; }
    line.textContent = text;
    log.appendChild(line);
    log.scrollTop = log.scrollHeight;
  }

  function toggle() {
    var open = panel.dataset.open !== 'true';
    panel.dataset.open = String(open);
    bubble.textContent = open ? 'Close' : 'Ask about a listing';

    if (open && !started) {
      started = true;
      say('them', 'Hello — ask me about the listings, how the process works, or leave your name and email and the team will come back to you.');
      input.focus();
    }
  }

  bubble.addEventListener('click', toggle);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && panel.dataset.open === 'true') { toggle(); }
  });

  panel.querySelector('[data-reset]').addEventListener('click', function (event) {
    event.preventDefault();
    sessionStorage.removeItem('ak.chat');
    chat = 'demo-' + Math.random().toString(36).slice(2, 10);
    sessionStorage.setItem('ak.chat', chat);
    log.textContent = '';
    started = false;
    say('them', 'Fresh start. What are you looking for?');
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var message = input.value.trim();
    if (message === '') { return; }

    say('you', message);
    input.value = '';
    send.disabled = true;

    fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat: chat, message: message, item: item })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) { say('them', data.reply || 'Sorry, something went wrong.', data.kind); })
      .catch(function () { say('them', 'I could not reach the assistant. Is the demo server running?'); })
      .finally(function () { send.disabled = false; input.focus(); });
  });
})();
