jQuery(document).ready(function($) {
    /**
     * Funkcja do wysyłania wiadomości
     */
    function sendMessage() {
        var userInput = $('#user-input').val().trim();

        if (userInput === '') {
            alert('Wpisz pytanie!');
            return;
        }

        // Dodanie wiadomości użytkownika do okna chatu
        $('#chat-window').append('<div class="user-message"><strong>Ty:</strong> ' + userInput + '</div>');

        // Przewinięcie do dołu
        $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);

        // Wysłanie zapytania do backendu
        $.ajax({
            url: gptChatData.ajax_url,
            type: 'POST',
            data: {
                action: 'gpt_pdf_chat_response',
                question: userInput,
                nonce: gptChatData.nonce
            },
            beforeSend: function() {
                // Dodanie wskaźnika ładowania
                $('#chat-window').append('<div class="ai-message"><strong>AI:</strong> ...</div>');
                $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);
            },
            success: function(response) {
                // Usunięcie wskaźnika ładowania
                $('#chat-window .ai-message:last-child').remove();

                if (response.success) {
                    $('#chat-window').append('<div class="ai-message"><strong>AI:</strong> ' + response.data + '</div>');
                } else {
                    $('#chat-window').append('<div class="ai-message"><strong>AI:</strong> ' + response.data + '</div>');
                }

                // Przewinięcie do dołu
                $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);

                // Wyczyść pole wejściowe
                $('#user-input').val('');
            },
            error: function() {
                // Usunięcie wskaźnika ładowania
                $('#chat-window .ai-message:last-child').remove();

                $('#chat-window').append('<div class="ai-message"><strong>AI:</strong> Wystąpił błąd podczas przetwarzania twojego pytania.</div>');
                $('#chat-window').scrollTop($('#chat-window')[0].scrollHeight);
            }
        });
    }

    // Obsługa kliknięcia przycisku "Wyślij"
    $('#send-button').on('click', function() {
        sendMessage();
    });

    // Obsługa wysyłania wiadomości po naciśnięciu Enter
    $('#user-input').on('keypress', function(e) {
        if (e.which === 13) {
            sendMessage();
            return false; // Zablokuj domyślne działanie
        }
    });
});
