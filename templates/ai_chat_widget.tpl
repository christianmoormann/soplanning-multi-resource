{* Widget Chat IA - SOPlanning *}
<link rel="stylesheet" href="{$BASE}/assets/css/ai-chat.css" type="text/css" />

<button id="ai-chat-btn"
        data-endpoint="{$BASE}/ai/chat.php"
        title="Assistant IA"
        aria-label="Assistant IA">
    <i class="fa fa-robot" aria-hidden="true"></i>
</button>

<div id="ai-chat-panel" class="ai-hidden" role="dialog" aria-label="Chat IA">
    <div id="ai-chat-header">
        <i class="fa fa-robot" aria-hidden="true"></i>
        <span class="ai-title">Assistant IA</span>
        <button id="ai-chat-reset" title="Reinitialiser la conversation" aria-label="Reinitialiser">
            <i class="fa fa-refresh" aria-hidden="true"></i>
        </button>
        <button id="ai-chat-close" title="Fermer" aria-label="Fermer">
            <i class="fa fa-times" aria-hidden="true"></i>
        </button>
    </div>
    <div id="ai-chat-messages" role="log" aria-live="polite">
        <div class="ai-msg ai-msg-ai">Bonjour ! Je suis votre assistant SOPlanning. Comment puis-je vous aider ?</div>
    </div>
    <div id="ai-chat-footer">
        <textarea id="ai-chat-input"
                  placeholder="Votre message... (Entree pour envoyer)"
                  rows="1"
                  aria-label="Message"></textarea>
        <button id="ai-chat-send" aria-label="Envoyer">
            <i class="fa fa-paper-plane" aria-hidden="true"></i>
        </button>
    </div>
</div>

<script src="{$BASE}/assets/js/ai-chat.js"></script>
