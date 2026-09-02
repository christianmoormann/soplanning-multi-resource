/* ============================================================
   Widget Chat IA - SOPlanning (streaming SSE)
   ============================================================ */

(function($) {
    'use strict';

    var AiChat = {

        endpoint: '',
        historyEndpoint: '',
        open: false,
        abortController: null,

        init: function(endpoint) {
            this.endpoint = endpoint;
            this.historyEndpoint = endpoint.replace('chat.php', 'history.php');
            this.bindEvents();
            this.loadHistory();
        },

        bindEvents: function() {
            var self = this;

            $('#ai-chat-btn').on('click', function() { self.toggle(); });
            $('#ai-chat-close').on('click', function() { self.hide(); });
            $('#ai-chat-reset').on('click', function() { self.resetConversation(); });
            $('#ai-chat-send').on('click', function() { self.sendMessage(); });

            $('#ai-chat-input').on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            $('#ai-chat-input').on('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });
        },

        toggle: function() { this.open ? this.hide() : this.show(); },

        show: function() {
            this.open = true;
            $('#ai-chat-panel').removeClass('ai-hidden');
            $('#ai-chat-input').focus();
            this.scrollToBottom();
        },

        hide: function() {
            this.open = false;
            $('#ai-chat-panel').addClass('ai-hidden');
        },

        resetConversation: function() {
            if (!confirm('Reinitialiser la conversation ?')) return;
            if (this.abortController) {
                this.abortController.abort();
                this.abortController = null;
            }
            this.setLoading(false);
            $('#ai-chat-messages').empty();
            this.addSystemMessage('Conversation reinitalisee.');

            fetch(this.endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message: '.', reset: true})
            }).catch(function() {});
        },

        sendMessage: function() {
            var input = $('#ai-chat-input');
            var text  = $.trim(input.val());
            if (!text || $('#ai-chat-send').prop('disabled')) return;

            this.addUserMessage(text);
            input.val('').css('height', 'auto');
            this.setLoading(true);

            var self = this;
            var $aiMsg = null;   // bulle de reponse IA creee a la reception du 1er token

            // Abort controller pour pouvoir annuler la requete
            this.abortController = window.AbortController ? new AbortController() : null;

            var fetchOptions = {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message: text, reset: false})
            };
            if (this.abortController) {
                fetchOptions.signal = this.abortController.signal;
            }

            fetch(self.endpoint, fetchOptions).then(function(response) {
                if (!response.ok) {
                    self.setLoading(false);
                    self.addErrorMessage('Erreur HTTP ' + response.status);
                    return;
                }

                var reader  = response.body.getReader();
                var decoder = new TextDecoder('utf-8');
                var buffer  = '';

                function processChunk(result) {
                    if (result.done) {
                        self.setLoading(false);
                        return;
                    }

                    buffer += decoder.decode(result.value, {stream: true});

                    // Traite toutes les lignes completes (terminant par \n)
                    var lines = buffer.split('\n');
                    buffer = lines.pop();   // garde la ligne incomplete

                    for (var i = 0; i < lines.length; i++) {
                        var line = lines[i].trim();
                        if (!line.startsWith('data: ')) continue;

                        var evt;
                        try { evt = JSON.parse(line.slice(6)); } catch(e) { continue; }

                        if (evt.type === 'token') {
                            // Cree la bulle IA au premier token
                            if (!$aiMsg) {
                                self.setLoading(false);
                                self.clearStatus();
                                $aiMsg = $('<div class="ai-msg ai-msg-ai ai-msg-streaming"></div>');
                                $('#ai-chat-messages').append($aiMsg);
                            }
                            // Ajoute le token comme noeud texte (pas d'injection HTML)
                            $aiMsg[0].appendChild(document.createTextNode(evt.t));
                            self.scrollToBottom();

                        } else if (evt.type === 'status') {
                            self.showStatus(evt.message);

                        } else if (evt.type === 'done') {
                            self.setLoading(false);
                            self.clearStatus();
                            if ($aiMsg) $aiMsg.removeClass('ai-msg-streaming');
                            if (evt.refresh) {
                                setTimeout(function() {
                                    self.addSystemMessage('Mise a jour du planning...');
                                    location.reload();
                                }, 800);
                            }

                        } else if (evt.type === 'error') {
                            self.setLoading(false);
                            self.clearStatus();
                            if ($aiMsg) $aiMsg.remove();
                            self.addErrorMessage(evt.message || 'Erreur inconnue');
                        }
                    }

                    reader.read().then(processChunk).catch(function(e) {
                        if (e.name !== 'AbortError') {
                            self.setLoading(false);
                            self.addErrorMessage('Connexion interrompue.');
                        }
                    });
                }

                reader.read().then(processChunk).catch(function(e) {
                    self.setLoading(false);
                    self.addErrorMessage('Erreur de lecture du flux.');
                });

            }).catch(function(e) {
                self.setLoading(false);
                if (e.name === 'AbortError') return;
                self.addErrorMessage('Impossible de contacter le serveur.');
            });
        },

        loadHistory: function() {
            var self = this;
            fetch(this.historyEndpoint)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.messages || !data.messages.length) return;
                    data.messages.forEach(function(m) {
                        if (m.role === 'user') {
                            self.addUserMessage(m.content);
                        } else if (m.role === 'assistant') {
                            self.addAiMessage(m.content);
                        }
                    });
                })
                .catch(function() {});
        },

        showStatus: function(msg) {
            var $s = $('#ai-status-indicator');
            if (!$s.length) {
                $s = $('<div id="ai-status-indicator" class="ai-status"></div>');
                $('#ai-chat-messages').append($s);
            }
            $s.text(msg);
            this.scrollToBottom();
        },

        clearStatus: function() {
            $('#ai-status-indicator').remove();
        },

        addAiMessage: function(text) {
            var $msg = $('<div class="ai-msg ai-msg-ai"></div>').text(text);
            $('#ai-chat-messages').append($msg);
            this.scrollToBottom();
        },

        addUserMessage: function(text) {
            var $msg = $('<div class="ai-msg ai-msg-user"></div>').text(text);
            $('#ai-chat-messages').append($msg);
            this.scrollToBottom();
        },

        addErrorMessage: function(text) {
            var $msg = $('<div class="ai-msg ai-msg-error"></div>').text(text);
            $('#ai-chat-messages').append($msg);
            this.scrollToBottom();
        },

        addSystemMessage: function(text) {
            var $msg = $('<div style="text-align:center;font-size:11px;color:#6c757d;padding:2px 0;"></div>').text(text);
            $('#ai-chat-messages').append($msg);
            this.scrollToBottom();
        },

        setLoading: function(on) {
            $('#ai-chat-send').prop('disabled', on);
            $('#ai-chat-input').prop('disabled', on);
            if (on) {
                if (!$('#ai-typing-indicator').length) {
                    var $t = $('<div class="ai-typing" id="ai-typing-indicator"><span></span><span></span><span></span></div>');
                    $('#ai-chat-messages').append($t);
                    this.scrollToBottom();
                }
            } else {
                $('#ai-typing-indicator').remove();
            }
        },

        scrollToBottom: function() {
            var el = document.getElementById('ai-chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        }
    };

    $(document).ready(function() {
        var btn = document.getElementById('ai-chat-btn');
        if (!btn) return;
        AiChat.init(btn.getAttribute('data-endpoint') || 'ai/chat.php');
    });

})(jQuery);
