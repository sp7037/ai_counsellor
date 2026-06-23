(function () {
    const script = document.currentScript;
    const widgetKey = script?.dataset?.widgetKey;
    const gatewayBase = (script?.dataset?.gateway || '').replace(/\/$/, '');

    if (!widgetKey || !gatewayBase) {
        console.error('[AI Counsellor Widget] Missing data-widget-key or data-gateway.');
        return;
    }

    const sessionStorageKey = `ac_widget_session_${widgetKey}`;
    const expandedStorageKey = `ac_widget_expanded_${widgetKey}`;

    const state = {
        token: null,
        expiresAt: null,
        open: false,
        expanded: false,
        messages: [],
        loading: false,
        sending: false,
        typing: false,
        config: null,
        mode: 'ai',
        pollTimer: null,
        lastMessageUuid: null,
        handoffRequestUuid: null,
        visitorMessageCount: 0,
        handoffProminent: false,
        handoffOfferShown: false,
        showLocationChip: false,
        locationChipDismissed: false,
        sessionExpired: false,
        stickToBottom: true,
        archivedSession: false,
        handoffRequested: false,
        humanMode: false,
        humanJoined: false,
        activeCounsellorName: null,
        handoffRequestInFlight: false,
        teaserDismissed: false,
        teaserTimer: null,
    };

    const HANDOFF_INTENT_PATTERNS = [
        /\b(?:talk|speak|chat|connect)\s+(?:to|with)\s+(?:a\s+)?(?:human|counsell?or|agent|person|staff)\b/i,
        /\b(?:human|real)\s+(?:counsell?or|agent|person|help|support)\b/i,
        /\b(?:call\s*me|callback|call\s*back|whatsapp|speak\s+to)\b/i,
        /\b(?:i\s+)?(?:want|need|prefer)\s+(?:a\s+)?(?:human|counsell?or|agent|person)\b/i,
    ];

    const FALLBACK_REPLY_MARKERS = [
        'temporarily unavailable',
        'try again shortly',
        'contact our team',
    ];

    const HUMAN_MODES = ['human'];
    const HUMAN_DISCLOSURE_MESSAGE = 'Human counsellor is assisting you now.';

    function persistSession(token, expiresAt) {
        try {
            sessionStorage.setItem(sessionStorageKey, JSON.stringify({ token, expiresAt }));
        } catch {
            // Ignore storage failures.
        }
    }

    function clearPersistedSession() {
        try {
            sessionStorage.removeItem(sessionStorageKey);
        } catch {
            // Ignore storage failures.
        }
    }

    function restorePersistedSession() {
        try {
            const raw = sessionStorage.getItem(sessionStorageKey);
            if (!raw) {
                return null;
            }

            const parsed = JSON.parse(raw);
            const token = typeof parsed?.token === 'string' ? parsed.token : null;
            const expiresAt = typeof parsed?.expiresAt === 'string' ? parsed.expiresAt : null;

            if (!token) {
                clearPersistedSession();

                return null;
            }

            if (expiresAt && Number.isFinite(Date.parse(expiresAt)) && Date.parse(expiresAt) <= Date.now()) {
                clearPersistedSession();

                return null;
            }

            return { token, expiresAt };
        } catch {
            clearPersistedSession();

            return null;
        }
    }

    function loadExpandedPreference() {
        try {
            return localStorage.getItem(expandedStorageKey) === '1';
        } catch {
            return false;
        }
    }

    function persistExpandedPreference() {
        try {
            localStorage.setItem(expandedStorageKey, state.expanded ? '1' : '0');
        } catch {
            // Ignore storage failures.
        }
    }

    function baseStyles(primary, position) {
        const horizontal = position === 'bottom_left' ? 'left: 20px; right: auto;' : 'right: 20px; left: auto;';
        const teaserHorizontal = position === 'bottom_left' ? 'left: 68px; right: auto;' : 'right: 68px; left: auto;';
        return `
        #ac-widget-root { position: fixed; bottom: 20px; ${horizontal} z-index: 2147483000; font-family: system-ui, -apple-system, sans-serif; }
        #ac-widget-toggle { width: 56px; height: 56px; border-radius: 999px; border: none; background: ${primary}; color: #fff; cursor: pointer; box-shadow: 0 8px 24px rgba(0,0,0,.2); font-size: 15px; font-weight: 700; padding: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        #ac-widget-toggle-badge { display: flex; width: 42px; height: 42px; border-radius: 999px; background: #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,.25), 0 0 0 1px rgba(255,255,255,.85); box-sizing: border-box; padding: 6px; align-items: center; justify-content: center; }
        #ac-widget-toggle-badge.ac-loading { animation: ac-badge-pulse 1.4s ease-in-out infinite; }
        #ac-widget-toggle-badge img { width: 100%; height: 100%; max-width: 27px; max-height: 27px; object-fit: contain; display: none; }
        #ac-widget-toggle-fallback { display: none; align-items: center; justify-content: center; line-height: 1; color: #fff; font-size: 15px; font-weight: 700; }
        @keyframes ac-badge-pulse { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
        #ac-widget-teaser { position: absolute; bottom: 13px; ${teaserHorizontal} max-width: 220px; padding: 8px 13px; border-radius: 999px; background: #ffffff; color: #0f172a; font-size: 12px; font-weight: 600; line-height: 1.2; box-shadow: 0 6px 20px rgba(0,0,0,.18); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0; transform: translateX(6px); pointer-events: none; transition: opacity .25s ease, transform .25s ease; }
        #ac-widget-teaser.visible { opacity: 1; transform: translateX(0); }
        #ac-widget-panel { display: none; width: 340px; max-width: calc(100vw - 24px); height: 480px; margin-bottom: 12px; border-radius: 14px; overflow: hidden; background: #111827; color: #f9fafb; box-shadow: 0 12px 40px rgba(0,0,0,.35); flex-direction: column; transition: width .2s ease, height .2s ease; }
        #ac-widget-panel.open { display: flex; }
        #ac-widget-panel.expanded { width: min(420px, calc(100vw - 24px)); height: min(640px, calc(100vh - 100px)); }
        #ac-widget-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 9px 12px; background: #1f2937; border-bottom: 1px solid #374151; }
        .ac-header-brand { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
        #ac-widget-avatar { width: 30px; height: 30px; border-radius: 10px; border: 1px solid rgba(255,255,255,.16); background: linear-gradient(160deg, rgba(255,255,255,.16), rgba(255,255,255,.04)); box-shadow: 0 6px 20px rgba(0,0,0,.25); display: flex; align-items: center; justify-content: center; color: #e5e7eb; font-size: 12px; font-weight: 700; flex-shrink: 0; overflow: hidden; }
        #ac-widget-avatar img { width: 100%; height: 100%; object-fit: cover; display: none; }
        #ac-widget-avatar span { display: inline-flex; }
        #ac-widget-title { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ac-header-actions { display: flex; gap: 4px; flex-shrink: 0; }
        .ac-icon-btn { width: 30px; height: 30px; border: none; border-radius: 8px; background: transparent; color: #d1d5db; cursor: pointer; font-size: 16px; line-height: 1; }
        .ac-icon-btn:hover { background: #374151; color: #fff; }
        #ac-widget-messages { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; scroll-behavior: smooth; scrollbar-width: thin; scrollbar-color: #4b5563 transparent; }
        #ac-widget-messages::-webkit-scrollbar { width: 6px; }
        #ac-widget-messages::-webkit-scrollbar-track { background: transparent; }
        #ac-widget-messages::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 999px; }
        #ac-widget-messages::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .ac-msg { max-width: 88%; padding: 9px 11px; border-radius: 12px; font-size: 13px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; }
        .ac-msg.visitor { align-self: flex-end; background: ${primary}; color: #fff; border-bottom-right-radius: 4px; }
        .ac-msg.system, .ac-msg.assistant { align-self: flex-start; background: #374151; color: #f3f4f6; border-bottom-left-radius: 4px; }
        .ac-msg.counsellor { align-self: flex-start; background: #065f46; color: #ecfdf5; border-bottom-left-radius: 4px; }
        .ac-msg.archived { opacity: 0.72; }
        .ac-msg.session-divider { align-self: center; max-width: 100%; background: transparent; color: #9ca3af; font-size: 11px; padding: 4px 0; text-align: center; }
        .ac-typing { align-self: flex-start; display: inline-flex; align-items: center; gap: 6px; padding: 9px 12px; border-radius: 12px; background: #374151; color: #d1d5db; font-size: 12px; }
        .ac-typing-dots { display: inline-flex; gap: 3px; }
        .ac-typing-dots span { width: 5px; height: 5px; border-radius: 999px; background: #9ca3af; animation: ac-bounce 1.2s infinite ease-in-out; }
        .ac-typing-dots span:nth-child(2) { animation-delay: .15s; }
        .ac-typing-dots span:nth-child(3) { animation-delay: .3s; }
        @keyframes ac-bounce { 0%, 80%, 100% { transform: translateY(0); opacity: .5; } 40% { transform: translateY(-4px); opacity: 1; } }
        #ac-widget-handoff-subtle { display: none; flex-shrink: 0; margin: 0; padding: 4px 10px; border-radius: 999px; border: 1px solid #334155; background: #1e293b; color: #cbd5e1; font-size: 11px; font-weight: 500; text-align: left; cursor: pointer; width: fit-content; max-width: 60%; }
        #ac-widget-handoff-subtle:hover { background: #263447; border-color: #475569; color: #f1f5f9; }
        #ac-widget-handoff-subtle::before { content: "⌁"; margin-right: 6px; font-size: 11px; opacity: .85; }
        #ac-human-transfer { display: none; margin: 0 12px 8px; border: none; border-radius: 8px; background: ${primary}; color: #fff; padding: 9px 12px; cursor: pointer; font-size: 13px; font-weight: 500; width: calc(100% - 24px); }
        #ac-human-transfer.prominent { display: block; }
        #ac-widget-location-chip { display: none; margin: 0 12px 6px; padding: 4px 10px; border-radius: 999px; border: 1px solid #334155; background: #1e293b; color: #cbd5e1; font-size: 11px; font-weight: 500; cursor: pointer; width: fit-content; }
        #ac-widget-location-chip:hover { background: #263447; border-color: #475569; color: #f1f5f9; }
        #ac-widget-handoff-status { display: none; margin: 0 12px 8px; padding: 6px 10px; border-radius: 999px; font-size: 11px; font-weight: 500; width: fit-content; max-width: calc(100% - 24px); }
        #ac-widget-handoff-status.waiting { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; }
        #ac-widget-handoff-status.waiting::before { content: "◌"; margin-right: 6px; opacity: .85; }
        #ac-widget-handoff-status.joined { background: #064e3b; border: 1px solid #065f46; color: #d1fae5; }
        #ac-widget-handoff-status.joined::before { content: "●"; margin-right: 6px; color: #34d399; font-size: 9px; }
        #ac-widget-recovery { display: none; margin: 0 12px 8px; padding: 10px; border-radius: 10px; background: #1f2937; border: 1px solid #374151; font-size: 12px; color: #d1d5db; }
        #ac-widget-recovery p { margin: 0 0 8px; }
        #ac-widget-recovery button { border: none; border-radius: 8px; background: ${primary}; color: #fff; padding: 8px 12px; cursor: pointer; font-size: 12px; font-weight: 500; }
        #ac-widget-form { display: grid; gap: 6px; padding: 8px 12px 10px; border-top: 1px solid #374151; }
        #ac-widget-input, #ac-offline-name, #ac-offline-email, #ac-offline-message { width: 100%; border: 1px solid #4b5563; background: #0f172a; color: #fff; border-radius: 10px; padding: 9px 10px; font-size: 13px; box-sizing: border-box; resize: none; }
        #ac-widget-input:focus { outline: 2px solid ${primary}33; border-color: ${primary}; }
        #ac-widget-send, #ac-offline-submit { border: none; border-radius: 10px; background: ${primary}; color: #fff; padding: 9px 12px; cursor: pointer; font-size: 13px; font-weight: 500; }
        #ac-widget-send:disabled { opacity: .55; cursor: not-allowed; }
        #ac-widget-error { color: #fca5a5; font-size: 12px; padding: 0 12px 6px; min-height: 0; }
        /* Status row only when human disclosure or an active handoff CTA needs it. */
        #ac-widget-statusbar { display: none; align-items: center; justify-content: space-between; gap: 8px; padding: 0 12px 2px; }
        #ac-widget-statusbar.visible { display: flex; }
        #ac-widget-disclosure { flex: 1; min-width: 0; font-size: 11px; color: #9ca3af; line-height: 1.3; }
        #ac-widget-disclosure:empty { display: none; }
        /* Powered-by is rendered as a very tiny footer at the bottom edge and never consumes message space. */
        #ac-widget-powered-by { display: none; padding: 3px 12px 5px; color: #6b7280; font-size: 9px; line-height: 1.1; text-align: center; }
        #ac-widget-powered-by.visible { display: block; }
        #ac-widget-powered-by .ac-powered-chip { display: inline-flex; align-items: center; gap: 5px; padding: 0; background: transparent; }
        #ac-widget-powered-by img { width: 11px; height: 11px; object-fit: contain; border-radius: 3px; opacity: .8; }
        @media (max-width: 640px) {
            #ac-widget-root { bottom: 12px; right: 12px; left: 12px; }
            #ac-widget-teaser { display: none; }
            #ac-widget-panel.open { width: 100%; max-width: 100%; height: min(72vh, 560px); }
            #ac-widget-panel.expanded.open { width: 100%; max-width: 100%; height: calc(100vh - 24px); margin-bottom: 8px; border-radius: 12px; }
        }
    `;
    }

    function injectStyles(config) {
        const branding = config?.branding || {};
        const styleId = 'ac-widget-styles';
        let el = document.getElementById(styleId);
        if (!el) {
            el = document.createElement('style');
            el.id = styleId;
            document.head.appendChild(el);
        }
        el.textContent = baseStyles(branding.primary_color || '#2563eb', branding.widget_position || 'bottom_right');
    }

    function assistantLabel() {
        const branding = state.config?.branding || {};
        return branding.assistant_name || branding.display_name || 'AI Counsellor';
    }

    function assistantInitials() {
        const name = assistantLabel().trim();
        if (name === '') {
            return 'AI';
        }

        const parts = name.split(/\s+/).filter(Boolean);
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }

        return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase();
    }

    // Floating launcher logo: try the platform-controlled launcher logo first, then the tenant
    // logo, then fall back to the assistant initials ("AI"). Image load errors advance the chain.
    function applyLauncher() {
        const badge = document.getElementById('ac-widget-toggle-badge');
        const logo = document.getElementById('ac-widget-toggle-logo');
        const fallback = document.getElementById('ac-widget-toggle-fallback');
        if (!badge || !logo || !fallback) {
            return;
        }

        fallback.textContent = assistantInitials() || 'AI';

        const launcher = state.config?.launcher || {};
        const tenantLogo = state.config?.branding?.logo_url || null;
        const candidates = [];
        if (typeof launcher.logo_url === 'string' && launcher.logo_url.trim() !== '') {
            candidates.push(launcher.logo_url.trim());
        }
        if (tenantLogo) {
            candidates.push(tenantLogo);
        }

        // Only fall back to initials/"AI" when logo sources are genuinely unavailable or fail.
        const showFallback = () => {
            logo.onload = null;
            logo.onerror = null;
            logo.removeAttribute('src');
            logo.style.display = 'none';
            badge.classList.remove('ac-loading');
            badge.style.display = 'none';
            fallback.style.display = 'inline-flex';
        };

        let index = 0;
        const tryNext = () => {
            if (index >= candidates.length) {
                showFallback();

                return;
            }

            const url = candidates[index];
            index += 1;
            logo.onload = () => {
                badge.classList.remove('ac-loading');
                badge.style.display = 'flex';
                logo.style.display = 'block';
                fallback.style.display = 'none';
            };
            logo.onerror = () => {
                tryNext();
            };
            logo.src = url;
        };

        if (candidates.length === 0) {
            showFallback();

            return;
        }

        // Keep the subtle white badge placeholder (no "AI" flash) while the logo loads.
        badge.classList.add('ac-loading');
        badge.style.display = 'flex';
        fallback.style.display = 'none';
        tryNext();
    }

    function teaserText() {
        return state.config?.launcher?.teaser_text || 'Ask AI Counsellor';
    }

    function applyTeaserText() {
        const teaser = document.getElementById('ac-widget-teaser');
        const text = teaserText();
        if (teaser) {
            teaser.textContent = text;
        }
        const toggle = document.getElementById('ac-widget-toggle');
        if (toggle) {
            toggle.setAttribute('title', text);
        }
    }

    function showTeaser(autoHideMs = 7000) {
        const teaser = document.getElementById('ac-widget-teaser');
        if (!teaser || state.open) {
            return;
        }

        teaser.classList.add('visible');
        if (state.teaserTimer) {
            clearTimeout(state.teaserTimer);
            state.teaserTimer = null;
        }
        if (autoHideMs) {
            state.teaserTimer = setTimeout(hideTeaser, autoHideMs);
        }
    }

    function hideTeaser() {
        const teaser = document.getElementById('ac-widget-teaser');
        if (teaser) {
            teaser.classList.remove('visible');
        }
        if (state.teaserTimer) {
            clearTimeout(state.teaserTimer);
            state.teaserTimer = null;
        }
    }

    function applyBranding(config) {
        const branding = config?.branding || {};
        const title = document.getElementById('ac-widget-title');
        const avatar = document.getElementById('ac-widget-avatar');
        const avatarImage = document.getElementById('ac-widget-avatar-image');
        const avatarFallback = document.getElementById('ac-widget-avatar-fallback');
        const toggle = document.getElementById('ac-widget-toggle');
        const root = document.getElementById('ac-widget-root');
        const panel = document.getElementById('ac-widget-panel');
        const poweredBy = document.getElementById('ac-widget-powered-by');
        const poweredByLabel = document.getElementById('ac-widget-powered-by-label');
        const poweredByLogo = document.getElementById('ac-widget-powered-by-logo');

        if (title) {
            title.textContent = assistantLabel();
        }
        if (avatar && avatarImage && avatarFallback) {
            const logoUrl = branding.logo_url || null;
            avatarFallback.textContent = assistantInitials();
            if (logoUrl) {
                avatarImage.src = logoUrl;
                avatarImage.alt = `${assistantLabel()} logo`;
                avatarImage.style.display = 'block';
                avatarFallback.style.display = 'none';
            } else {
                avatarImage.removeAttribute('src');
                avatarImage.style.display = 'none';
                avatarFallback.style.display = 'inline-flex';
            }
        }
        if (toggle) {
            toggle.setAttribute('aria-label', `Open chat with ${assistantLabel()}`);
        }
        applyLauncher();
        applyTeaserText();
        if (root && branding.widget_position === 'bottom_left') {
            root.style.left = '20px';
            root.style.right = 'auto';
        }
        if (panel) {
            panel.classList.toggle('expanded', state.expanded);
        }
        if (poweredBy && poweredByLabel && poweredByLogo) {
            const powered = state.config?.powered_by || {};
            const showPoweredBy = powered.enabled === true && typeof powered.label === 'string' && powered.label.trim() !== '';
            poweredBy.classList.toggle('visible', showPoweredBy);
            poweredByLabel.textContent = showPoweredBy ? powered.label.trim() : '';

            if (showPoweredBy && typeof powered.logo_url === 'string' && powered.logo_url !== '') {
                poweredByLogo.src = powered.logo_url;
                poweredByLogo.alt = 'Platform';
                poweredByLogo.style.display = 'inline-block';
            } else {
                poweredByLogo.removeAttribute('src');
                poweredByLogo.style.display = 'none';
            }
        }
        updateHandoffUi();
    }

    function messagesContainer() {
        return document.getElementById('ac-widget-messages');
    }

    function isNearBottom(container, threshold = 72) {
        return container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
    }

    function scrollToBottom(force = false) {
        const container = messagesContainer();
        if (!container) {
            return;
        }

        if (force || state.stickToBottom || isNearBottom(container)) {
            requestAnimationFrame(() => {
                container.scrollTop = container.scrollHeight;
            });
            state.stickToBottom = true;
        }
    }

    function renderTypingIndicator() {
        const container = messagesContainer();
        if (!container || !state.typing) {
            return;
        }

        let el = document.getElementById('ac-typing-indicator');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ac-typing-indicator';
            el.className = 'ac-typing';
            el.innerHTML = `<span>${assistantLabel()} is typing</span><span class="ac-typing-dots" aria-hidden="true"><span></span><span></span><span></span></span>`;
            container.appendChild(el);
        }
        scrollToBottom(true);
    }

    function removeTypingIndicator() {
        document.getElementById('ac-typing-indicator')?.remove();
    }

    function renderMessages() {
        const container = messagesContainer();
        if (!container) {
            return;
        }

        container.textContent = '';
        state.messages.forEach((message) => {
            const div = document.createElement('div');
            let roleClass = 'system';
            if (message.role === 'visitor') {
                roleClass = 'visitor';
            } else if (message.role === 'counsellor') {
                roleClass = 'counsellor';
            } else if (message.role === 'assistant') {
                roleClass = 'assistant';
            }

            if (message.type === 'session-divider') {
                div.className = 'ac-msg session-divider';
                div.textContent = message.body;
            } else {
                div.className = `ac-msg ${roleClass}${message.archived ? ' archived' : ''}`;
                const prefix = message.sender_name ? `${message.sender_name}: ` : '';
                div.textContent = prefix + message.body;
            }

            container.appendChild(div);
            state.lastMessageUuid = message.uuid || state.lastMessageUuid;
        });

        if (state.typing) {
            renderTypingIndicator();
        }

        scrollToBottom();
    }

    function shouldShowProminentHandoff() {
        if (!state.config?.human_transfer?.enabled) {
            return false;
        }

        return Boolean(state.handoffProminent);
    }

    function updateLocationChip() {
        const chip = document.getElementById('ac-widget-location-chip');
        if (!chip) {
            return;
        }

        const show = state.showLocationChip
            && !state.locationChipDismissed
            && !state.humanMode
            && !state.handoffRequested
            && !state.sessionExpired;

        chip.style.display = show ? 'block' : 'none';
    }

    function syncHumanState() {
        const counsellorMessages = state.messages.filter((m) => m.role === 'counsellor');
        const hasCounsellorMessage = counsellorMessages.length > 0;

        state.humanMode = HUMAN_MODES.includes(state.mode) || hasCounsellorMessage;
        state.humanJoined = state.humanMode;
        state.handoffRequested = ! state.humanMode
            && (state.mode === 'handoff_requested' || state.handoffRequestInFlight || state.handoffRequestUuid !== null);

        if (hasCounsellorMessage) {
            const last = counsellorMessages[counsellorMessages.length - 1];
            if (last.sender_name && last.sender_name.trim() !== '') {
                state.activeCounsellorName = last.sender_name.trim();
            }
        }
    }

    function updateDisclosure() {
        const el = document.getElementById('ac-widget-disclosure');
        if (!el) {
            return;
        }

        if (state.humanMode) {
            el.textContent = HUMAN_DISCLOSURE_MESSAGE;
            el.style.display = '';

            return;
        }

        // AI-first mode: keep disclosure in welcome/footer only, not a repeated status line.
        el.textContent = '';
        el.style.display = 'none';
    }

    function updateStatusbarVisibility() {
        const bar = document.getElementById('ac-widget-statusbar');
        const disclosure = document.getElementById('ac-widget-disclosure');
        const subtle = document.getElementById('ac-widget-handoff-subtle');

        if (!bar) {
            return;
        }

        const showDisclosure = disclosure
            && disclosure.style.display !== 'none'
            && (disclosure.textContent || '').trim() !== '';
        const showSubtle = subtle && subtle.style.display !== 'none';

        bar.classList.toggle('visible', Boolean(showDisclosure || showSubtle));
    }

    function updateHandoffUi() {
        syncHumanState();

        const subtle = document.getElementById('ac-widget-handoff-subtle');
        const prominent = document.getElementById('ac-human-transfer');
        const status = document.getElementById('ac-widget-handoff-status');
        const transfer = state.config?.human_transfer;

        const hideCtas = () => {
            if (subtle) subtle.style.display = 'none';
            if (prominent) prominent.classList.remove('prominent');
        };

        if (status) {
            status.style.display = 'none';
            status.classList.remove('joined', 'waiting');
            status.textContent = '';
        }

        updateDisclosure();
        updateLocationChip();

        // Counsellor has joined or a human/counsellor message exists: suppress every CTA.
        if (state.humanMode) {
            hideCtas();
            if (status) {
                const name = state.activeCounsellorName && state.activeCounsellorName.trim() !== ''
                    ? state.activeCounsellorName.trim()
                    : 'a counsellor';
                status.textContent = `You are chatting with ${name}`;
                status.classList.add('joined');
                status.style.display = 'block';
            }

            updateStatusbarVisibility();

            return;
        }

        // Human requested but not yet joined: show a non-clickable waiting status only.
        if (state.handoffRequested) {
            hideCtas();
            if (status) {
                status.textContent = 'Waiting for counsellor…';
                status.classList.add('waiting');
                status.style.display = 'block';
            }

            updateStatusbarVisibility();

            return;
        }

        if (!subtle || !prominent || !transfer?.enabled || state.sessionExpired) {
            hideCtas();
            updateStatusbarVisibility();

            return;
        }

        if (shouldShowProminentHandoff()) {
            subtle.style.display = 'none';
            prominent.classList.add('prominent');
            prominent.textContent = transfer.label || 'Talk to counsellor';
        } else {
            prominent.classList.remove('prominent');
            subtle.style.display = 'none';
        }

        updateStatusbarVisibility();
    }

    function detectHandoffIntent(text) {
        return HANDOFF_INTENT_PATTERNS.some((pattern) => pattern.test(text));
    }

    function detectAiFallback(reply) {
        if (!reply) {
            return true;
        }

        if (reply.role === 'system') {
            const body = (reply.body || '').toLowerCase();
            return FALLBACK_REPLY_MARKERS.some((marker) => body.includes(marker));
        }

        return false;
    }

    function maybeOfferHumanHandoff() {
        const offer = state.config?.human_transfer?.offer_message;
        if (!offer || offer.trim() === '' || state.handoffOfferShown || !state.config?.human_transfer?.enabled) {
            return;
        }

        if (state.humanMode || state.handoffRequested) {
            return;
        }

        state.handoffOfferShown = true;
        state.messages.push({ role: 'system', body: offer });
        state.handoffProminent = true;
        updateHandoffUi();
        renderMessages();
    }

    function isSessionAuthError(message) {
        const text = (message || '').toLowerCase();
        return text.includes('invalid or expired session') || text.includes('session token required');
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
            if (response.status === 401 && path !== '/session') {
                state.token = null;
                state.expiresAt = null;
                clearPersistedSession();
            }

            const error = new Error(data.message || 'Request failed');
            error.status = response.status;
            throw error;
        }

        return data;
    }

    function showDisclosure() {
        updateDisclosure();
    }

    function showError(message) {
        const el = document.getElementById('ac-widget-error');
        if (el) {
            el.textContent = message || '';
        }
    }

    function showSessionRecovery() {
        state.sessionExpired = true;
        state.messages = state.messages.map((message) => ({ ...message, archived: true }));
        state.archivedSession = true;
        state.token = null;
        state.expiresAt = null;
        clearPersistedSession();
        removeTypingIndicator();
        state.typing = false;
        setSendingState(false);

        const recovery = document.getElementById('ac-widget-recovery');
        if (recovery) {
            recovery.style.display = 'block';
        }

        showError('');
        updateHandoffUi();
        renderMessages();
    }

    async function startSession({ preserveMessages = false } = {}) {
        state.loading = true;
        showError('');

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
            state.expiresAt = data.expires_at || null;
            persistSession(state.token, state.expiresAt);
            state.config = data.configuration || null;
            state.sessionExpired = false;
            state.visitorMessageCount = 0;
            state.handoffProminent = false;
            state.handoffOfferShown = false;
            state.showLocationChip = false;
            state.locationChipDismissed = false;
            state.mode = 'ai';
            state.handoffRequested = false;
            state.humanMode = false;
            state.humanJoined = false;
            state.activeCounsellorName = null;
            state.handoffRequestUuid = null;
            state.handoffRequestInFlight = false;

            const recovery = document.getElementById('ac-widget-recovery');
            if (recovery) {
                recovery.style.display = 'none';
            }

            if (state.config) {
                injectStyles(state.config);
                applyBranding(state.config);
                showDisclosure(state.config);
            }

            if (!preserveMessages) {
                const welcome = data.welcome_message || state.config?.messages?.welcome;
                state.messages = welcome ? [{ role: 'system', body: welcome }] : [];
            } else if (state.archivedSession) {
                state.messages.push({ role: 'system', body: '— New conversation —', type: 'session-divider' });
                state.archivedSession = false;
            }

            renderMessages();
            updateHandoffUi();
        } catch (error) {
            showError(error.message);
        } finally {
            state.loading = false;
        }
    }

    async function startNewChat() {
        await startSession({ preserveMessages: true });
    }

    async function ensureSession() {
        if (state.token) {
            return true;
        }

        const restored = restorePersistedSession();
        if (restored) {
            state.token = restored.token;
            state.expiresAt = restored.expiresAt;

            try {
                const data = await api('/config');
                state.config = data.configuration || state.config;
                state.mode = data.mode || state.mode;
                if (Array.isArray(data.messages) && data.messages.length > 0) {
                    state.messages = data.messages;
                    state.visitorMessageCount = data.messages.filter((m) => m.role === 'visitor').length;
                }
                if (state.config) {
                    injectStyles(state.config);
                    applyBranding(state.config);
                    showDisclosure(state.config);
                }
                updateHandoffUi();
                renderMessages();
                return true;
            } catch (error) {
                if (error.status === 401) {
                    showSessionRecovery();
                    return false;
                }
            }
        }

        await startSession();
        return Boolean(state.token);
    }

    function setSendingState(active) {
        state.sending = active;
        const sendBtn = document.getElementById('ac-widget-send');
        const input = document.getElementById('ac-widget-input');
        if (sendBtn) {
            sendBtn.disabled = active || state.sessionExpired || !state.token;
        }
        if (input) {
            input.disabled = state.sessionExpired || !state.token;
        }
    }

    async function requestHandoff() {
        // Prevent duplicate handoff requests once one is in flight, requested, or a human is active.
        if (state.handoffRequestInFlight || state.handoffRequested || state.humanMode) {
            return;
        }

        state.handoffRequestInFlight = true;
        // Reflect "waiting" status immediately so the CTA cannot be triggered again.
        updateHandoffUi();

        try {
            state.handoffRequestUuid = window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const data = await api('/handoff', {
                method: 'POST',
                body: JSON.stringify({ handoff_request_uuid: state.handoffRequestUuid }),
            });
            state.mode = data.mode || 'handoff_requested';
            if (data.acknowledgement) {
                state.messages.push({
                    uuid: data.acknowledgement.uuid,
                    role: data.acknowledgement.role || 'system',
                    body: data.acknowledgement.body,
                });
                renderMessages();
            }
            updateHandoffUi();
            startPolling();
        } finally {
            state.handoffRequestInFlight = false;
            updateHandoffUi();
        }
    }

    function startPolling() {
        if (state.pollTimer) {
            return;
        }

        state.pollTimer = setInterval(async () => {
            if (!state.token || !state.open) {
                return;
            }

            try {
                const query = state.lastMessageUuid ? `?after=${encodeURIComponent(state.lastMessageUuid)}` : '';
                const data = await api(`/messages/poll${query}`);
                state.mode = data.mode || state.mode;
                (data.messages || []).forEach((message) => {
                    if (!state.messages.find((m) => m.uuid === message.uuid)) {
                        state.messages.push(message);
                    }
                });
                renderMessages();
                updateHandoffUi();
            } catch (error) {
                if (error.status === 401) {
                    showSessionRecovery();
                }
            }
        }, 5000);
    }

    async function sendMessage(body) {
        if (state.sending || state.sessionExpired || !state.token) {
            return;
        }

        state.sending = true;
        state.typing = true;
        setSendingState(true);
        showError('');
        renderMessages();

        try {
            const requestId = window.crypto?.randomUUID ? window.crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const data = await api('/messages', {
                method: 'POST',
                body: JSON.stringify({ body, request_id: requestId }),
            });

            if (data.session_expires_at) {
                state.expiresAt = data.session_expires_at;
                persistSession(state.token, state.expiresAt);
            }

            state.messages.push(
                { uuid: data.visitor_message.uuid, role: 'visitor', body: data.visitor_message.body },
            );
            state.visitorMessageCount += 1;

            if (typeof data.handoff_prominent === 'boolean') {
                state.handoffProminent = data.handoff_prominent;
            }

            if (detectHandoffIntent(body)) {
                state.handoffProminent = true;
            }

            if (typeof data.show_location_chip === 'boolean') {
                state.showLocationChip = data.show_location_chip;
            }

            if (data.reply) {
                state.messages.push({
                    uuid: data.reply.uuid,
                    role: data.reply.role || 'system',
                    body: data.reply.body,
                    sender_name: data.reply.sender_name,
                });
            }

            state.mode = data.mode || state.mode;
            if (state.mode === 'handoff_requested' || state.mode === 'human') {
                startPolling();
            }

            maybeOfferHumanHandoff();
            updateHandoffUi();
            updateLocationChip();
            renderMessages();
        } catch (error) {
            if (error.status === 401 || isSessionAuthError(error.message)) {
                showSessionRecovery();
            } else {
                showError('We could not send your message. Please try again.');
            }
        } finally {
            state.typing = false;
            removeTypingIndicator();
            state.sending = false;
            setSendingState(false);
            renderMessages();
        }
    }

    async function submitOffline(formData) {
        await api('/offline', {
            method: 'POST',
            body: JSON.stringify(formData),
        });
        state.messages.push({ role: 'system', body: 'Thanks — we received your message and will follow up.' });
        renderMessages();
    }

    async function useBrowserLocation() {
        if (!navigator.geolocation) {
            showError('Location is not available in this browser. Please type your city and state in chat.');
            state.locationChipDismissed = true;
            updateLocationChip();

            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                try {
                    const city = 'Nearby area';
                    await api('/location', {
                        method: 'POST',
                        body: JSON.stringify({
                            city,
                            state: null,
                            consent: true,
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                        }),
                    });
                    state.showLocationChip = false;
                    state.locationChipDismissed = true;
                    updateLocationChip();
                    showError('');
                } catch (error) {
                    showError('Could not save location. Please type your city and state in chat.');
                }
            },
            () => {
                showError('Location permission denied. Please type your city and state in chat.');
                state.locationChipDismissed = true;
                updateLocationChip();
            },
            { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
        );
    }

    function bindHandoffActions() {
        const subtle = document.getElementById('ac-widget-handoff-subtle');
        const prominent = document.getElementById('ac-human-transfer');

        const handler = async () => {
            try {
                await requestHandoff();
            } catch (error) {
                if (error.status === 401 || isSessionAuthError(error.message)) {
                    showSessionRecovery();
                } else {
                    showError(error.message);
                }
            }
        };

        subtle?.addEventListener('click', handler);
        prominent?.addEventListener('click', handler);
    }

    // Fetch lightweight public chrome (branding + launcher + powered-by) on first render so the
    // floating launcher shows the platform logo immediately, without creating a session or
    // requiring the visitor to open the widget. Falls back silently to initials on failure.
    async function bootstrapBranding() {
        try {
            const data = await api('/bootstrap', {
                method: 'POST',
                body: JSON.stringify({ widget_key: widgetKey }),
            });

            if (!data?.configuration) {
                return;
            }

            // Do not overwrite a full configuration already loaded by an in-flight session open.
            if (!state.config) {
                state.config = data.configuration;
                injectStyles(state.config);
                applyBranding(state.config);
                showDisclosure(state.config);
            } else {
                applyLauncher();
            }
        } catch {
            // Non-fatal: keep launcher initials until the widget is opened and a session loads config.
        }
    }

    function buildUi() {
        state.expanded = loadExpandedPreference();

        const root = document.createElement('div');
        root.id = 'ac-widget-root';

        const panel = document.createElement('div');
        panel.id = 'ac-widget-panel';
        panel.classList.toggle('expanded', state.expanded);
        panel.innerHTML = `
            <div id="ac-widget-header">
                <div class="ac-header-brand">
                    <div id="ac-widget-avatar" aria-hidden="true">
                        <img id="ac-widget-avatar-image" alt="" />
                        <span id="ac-widget-avatar-fallback">AI</span>
                    </div>
                    <span id="ac-widget-title">Chat with us</span>
                </div>
                <div class="ac-header-actions">
                    <button type="button" class="ac-icon-btn" id="ac-widget-expand" aria-label="Expand chat">⤢</button>
                    <button type="button" class="ac-icon-btn" id="ac-widget-minimize" aria-label="Minimize chat">−</button>
                </div>
            </div>
            <div id="ac-widget-messages"></div>
            <div id="ac-widget-recovery">
                <p>Your previous chat session expired. You can start a fresh chat to continue.</p>
                <button type="button" id="ac-widget-new-chat">Start new chat</button>
            </div>
            <div id="ac-widget-error"></div>
            <div id="ac-widget-handoff-status" role="status" aria-live="polite"></div>
            <button id="ac-widget-location-chip" type="button">Use my location</button>
            <div id="ac-widget-statusbar">
                <div id="ac-widget-disclosure"></div>
                <button id="ac-widget-handoff-subtle" type="button">Need human help?</button>
            </div>
            <button id="ac-human-transfer" type="button">Talk to counsellor</button>
            <form id="ac-widget-form">
                <textarea id="ac-widget-input" rows="2" placeholder="Type your question..." maxlength="4000" aria-label="Message"></textarea>
                <button id="ac-widget-send" type="submit">Send</button>
            </form>
            <form id="ac-offline-form" style="display:none; gap:8px; padding:10px 12px; border-top:1px solid #374151;">
                <input id="ac-offline-name" placeholder="Your name" maxlength="120" aria-label="Your name" />
                <input id="ac-offline-email" type="email" placeholder="Email (optional)" maxlength="255" aria-label="Email" />
                <textarea id="ac-offline-message" rows="3" placeholder="How can we help?" maxlength="2000" aria-label="Offline message"></textarea>
                <button id="ac-offline-submit" type="submit">Send offline message</button>
            </form>
            <div id="ac-widget-powered-by" aria-hidden="true">
                <span class="ac-powered-chip">
                    <img id="ac-widget-powered-by-logo" alt="" />
                    <span id="ac-widget-powered-by-label"></span>
                </span>
            </div>
        `;

        const toggle = document.createElement('button');
        toggle.id = 'ac-widget-toggle';
        toggle.type = 'button';
        toggle.setAttribute('aria-label', 'Open chat');
        toggle.setAttribute('title', 'Ask AI Counsellor');
        toggle.innerHTML = '<span id="ac-widget-toggle-badge" class="ac-loading"><img id="ac-widget-toggle-logo" alt="" /></span><span id="ac-widget-toggle-fallback">AI</span>';

        const teaser = document.createElement('div');
        teaser.id = 'ac-widget-teaser';
        teaser.setAttribute('role', 'status');
        teaser.textContent = 'Ask AI Counsellor';

        root.appendChild(teaser);
        root.appendChild(panel);
        root.appendChild(toggle);
        document.body.appendChild(root);

        injectStyles(null);
        bindHandoffActions();
        document.getElementById('ac-widget-location-chip')?.addEventListener('click', () => {
            useBrowserLocation();
        });

        // Subtle teaser: reveal shortly after load, auto-hide, and reappear only on hover/focus.
        toggle.addEventListener('mouseenter', () => showTeaser(4000));
        toggle.addEventListener('focus', () => showTeaser(4000));
        setTimeout(() => {
            if (!state.open && !state.teaserDismissed) {
                showTeaser(7000);
            }
        }, 1200);

        const messagesEl = messagesContainer();
        messagesEl?.addEventListener('scroll', () => {
            if (!messagesEl) {
                return;
            }
            state.stickToBottom = isNearBottom(messagesEl);
        });

        document.getElementById('ac-widget-expand')?.addEventListener('click', () => {
            state.expanded = !state.expanded;
            panel.classList.toggle('expanded', state.expanded);
            document.getElementById('ac-widget-expand').textContent = state.expanded ? '⤡' : '⤢';
            document.getElementById('ac-widget-expand').setAttribute('aria-label', state.expanded ? 'Collapse chat' : 'Expand chat');
            persistExpandedPreference();
            scrollToBottom(true);
        });

        document.getElementById('ac-widget-minimize')?.addEventListener('click', () => {
            state.open = false;
            panel.classList.remove('open');
        });

        document.getElementById('ac-widget-new-chat')?.addEventListener('click', async () => {
            await startNewChat();
        });

        toggle.addEventListener('click', async () => {
            state.open = !state.open;
            panel.classList.toggle('open', state.open);

            if (state.open) {
                state.teaserDismissed = true;
                hideTeaser();
                const ready = await ensureSession();
                if (ready) {
                    setSendingState(false);
                    scrollToBottom(true);
                }
            }
        });

        panel.querySelector('#ac-widget-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const input = panel.querySelector('#ac-widget-input');
            const value = input.value.trim();
            if (!value || !state.token || state.sessionExpired) {
                return;
            }
            input.value = '';
            await sendMessage(value);
        });

        panel.querySelector('#ac-offline-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!state.token) {
                return;
            }
            const name = panel.querySelector('#ac-offline-name').value.trim();
            const email = panel.querySelector('#ac-offline-email').value.trim();
            const message = panel.querySelector('#ac-offline-message').value.trim();
            if (!message) {
                return;
            }
            try {
                await submitOffline({ name, email, message });
                panel.querySelector('#ac-offline-form').style.display = 'none';
                panel.querySelector('#ac-widget-form').style.display = 'grid';
            } catch (error) {
                if (error.status === 401 || isSessionAuthError(error.message)) {
                    showSessionRecovery();
                } else {
                    showError(error.message);
                }
            }
        });
    }

    buildUi();
    bootstrapBranding();
})();
