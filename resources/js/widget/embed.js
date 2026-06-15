(function () {
    const script = document.currentScript;
    const widgetKey = script?.dataset?.widgetKey;
    const gatewayBase = (script?.dataset?.gateway || '').replace(/\/$/, '');

    if (!widgetKey || !gatewayBase) {
        console.error('[AI Counsellor Widget] Missing data-widget-key or data-gateway.');
        return;
    }

    const state = {
        token: null,
        open: false,
        messages: [],
        loading: false,
        offlineMode: false,
    };

    const styles = `
        #ac-widget-root { position: fixed; bottom: 20px; right: 20px; z-index: 2147483000; font-family: system-ui, sans-serif; }
        #ac-widget-toggle { width: 56px; height: 56px; border-radius: 999px; border: none; background: #2563eb; color: #fff; cursor: pointer; box-shadow: 0 8px 24px rgba(0,0,0,.2); }
        #ac-widget-panel { display: none; width: 320px; max-width: calc(100vw - 32px); height: 420px; margin-bottom: 12px; border-radius: 12px; overflow: hidden; background: #111827; color: #f9fafb; box-shadow: 0 12px 40px rgba(0,0,0,.35); flex-direction: column; }
        #ac-widget-panel.open { display: flex; }
        #ac-widget-header { padding: 12px 14px; background: #1f2937; font-weight: 600; font-size: 14px; }
        #ac-widget-messages { flex: 1; overflow-y: auto; padding: 12px; display: grid; gap: 8px; }
        .ac-msg { max-width: 85%; padding: 8px 10px; border-radius: 10px; font-size: 13px; line-height: 1.4; }
        .ac-msg.visitor { justify-self: end; background: #2563eb; }
        .ac-msg.system, .ac-msg.assistant { justify-self: start; background: #374151; }
        #ac-widget-form { display: grid; gap: 8px; padding: 10px; border-top: 1px solid #374151; }
        #ac-widget-input, #ac-offline-name, #ac-offline-email, #ac-offline-message { width: 100%; border: 1px solid #4b5563; background: #111827; color: #fff; border-radius: 8px; padding: 8px; font-size: 13px; }
        #ac-widget-send, #ac-offline-submit { border: none; border-radius: 8px; background: #2563eb; color: #fff; padding: 8px 12px; cursor: pointer; font-size: 13px; }
        #ac-widget-error { color: #fca5a5; font-size: 12px; padding: 0 10px 8px; }
    `;

    function injectStyles() {
        const el = document.createElement('style');
        el.textContent = styles;
        document.head.appendChild(el);
    }

    function renderMessages() {
        const container = document.getElementById('ac-widget-messages');
        if (!container) return;
        container.innerHTML = '';
        state.messages.forEach((message) => {
            const div = document.createElement('div');
            div.className = `ac-msg ${message.role === 'visitor' ? 'visitor' : 'system'}`;
            div.textContent = message.body;
            container.appendChild(div);
        });
        container.scrollTop = container.scrollHeight;
    }

    async function api(path, options = {}) {
        const headers = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(options.headers || {}),
        };

        if (state.token) {
            headers.Authorization = `Bearer ${state.token}`;
        }

        const response = await fetch(`${gatewayBase}${path}`, {
            ...options,
            headers,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    }

    async function startSession() {
        state.loading = true;
        try {
            const data = await api('/session', {
                method: 'POST',
                body: JSON.stringify({
                    widget_key: widgetKey,
                    source_url: window.location.href,
                    locale: navigator.language || 'en',
                }),
            });

            state.token = data.session_token;
            state.messages = (data.welcome_message
                ? [{ role: 'system', body: data.welcome_message }]
                : []);

            if (data.offline_form_enabled) {
                state.offlineMode = false;
            }

            renderMessages();
        } catch (error) {
            showError(error.message);
            state.offlineMode = true;
        } finally {
            state.loading = false;
        }
    }

    function showError(message) {
        const el = document.getElementById('ac-widget-error');
        if (el) {
            el.textContent = message;
        }
    }

    async function sendMessage(body) {
        const data = await api('/messages', {
            method: 'POST',
            body: JSON.stringify({ body }),
        });

        state.messages.push(
            { role: 'visitor', body: data.visitor_message.body },
            { role: 'system', body: data.reply.body },
        );
        renderMessages();
    }

    async function submitOffline(formData) {
        await api('/offline', {
            method: 'POST',
            body: JSON.stringify(formData),
        });
        state.messages.push({ role: 'system', body: 'Thanks — we received your message and will follow up.' });
        renderMessages();
    }

    function buildUi() {
        const root = document.createElement('div');
        root.id = 'ac-widget-root';

        const panel = document.createElement('div');
        panel.id = 'ac-widget-panel';
        panel.innerHTML = `
            <div id="ac-widget-header">Chat with us</div>
            <div id="ac-widget-messages"></div>
            <div id="ac-widget-error"></div>
            <form id="ac-widget-form">
                <textarea id="ac-widget-input" rows="2" placeholder="Type a message..." maxlength="4000"></textarea>
                <button id="ac-widget-send" type="submit">Send</button>
            </form>
            <form id="ac-offline-form" style="display:none; gap:8px; padding:10px; border-top:1px solid #374151;">
                <input id="ac-offline-name" placeholder="Your name" maxlength="120" />
                <input id="ac-offline-email" type="email" placeholder="Email (optional)" maxlength="255" />
                <textarea id="ac-offline-message" rows="3" placeholder="How can we help?" maxlength="2000"></textarea>
                <button id="ac-offline-submit" type="submit">Send offline message</button>
            </form>
        `;

        const toggle = document.createElement('button');
        toggle.id = 'ac-widget-toggle';
        toggle.type = 'button';
        toggle.setAttribute('aria-label', 'Open chat');
        toggle.textContent = 'Chat';

        root.appendChild(panel);
        root.appendChild(toggle);
        document.body.appendChild(root);

        toggle.addEventListener('click', async () => {
            state.open = !state.open;
            panel.classList.toggle('open', state.open);

            if (state.open && !state.token) {
                await startSession();
            }
        });

        panel.querySelector('#ac-widget-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = panel.querySelector('#ac-widget-input');
            const value = input.value.trim();
            if (!value || !state.token) return;
            input.value = '';
            try {
                await sendMessage(value);
            } catch (error) {
                showError(error.message);
            }
        });

        panel.querySelector('#ac-offline-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!state.token) return;
            const name = panel.querySelector('#ac-offline-name').value.trim();
            const email = panel.querySelector('#ac-offline-email').value.trim();
            const message = panel.querySelector('#ac-offline-message').value.trim();
            if (!message) return;
            try {
                await submitOffline({ name, email, message });
                panel.querySelector('#ac-offline-form').style.display = 'none';
                panel.querySelector('#ac-widget-form').style.display = 'grid';
            } catch (error) {
                showError(error.message);
            }
        });
    }

    injectStyles();
    buildUi();
})();
