
Contributors: Xcope
Tags: chat, AI, OpenAI, PDF
Requires at least: 5.0
Tested up to: 6.2
Requires PHP: 7.0
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Inteligentny chat AI oparty na pliku PDF, który odpowiada na pytania związane z treścią dokumentu.

== Description ==

GPT PDF Chat to wtyczka do WordPressa, która umożliwia zalogowanym użytkownikom korzystanie z inteligentnego chatu AI. Chat odpowiada na pytania na podstawie zawartości określonego pliku PDF, wykorzystując API OpenAI do generowania odpowiedzi.

== Installation ==

1. Prześlij folder `gpt-pdf-chat` do katalogu `/wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu administracyjnym WordPressa.
3. Przejdź do **Ustawienia > GPT PDF Chat** i wprowadź swój klucz API OpenAI.
4. Dodaj shortcode `[gpt_pdf_chat]` do dowolnej strony lub wpisu, aby wyświetlić okienko chatu.

== Biblioteka ==

Wykorzystana biblioteka 'PdfParser' url: https://github.com/smalot/pdfparser

== Oopenai ==

Do działania wtyczki potrzebny jest klucz API ChatGPT. Zaloguj się albo załóż konto https://platform.openai.com/api-keys

== Changelog ==

= 1.0 =
* Pierwsza wersja wtyczki.
