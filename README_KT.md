# KnowledgeTest

KnowledgeTest zamienia wklejony tekst źródłowy w gotowy zestaw fiszek do nauki. Wklejasz notatki, artykuł albo fragment skryptu — AI (Claude) generuje zestaw pytań i odpowiedzi, a Ty od razu możesz rozpocząć sesję nauki (pytanie → odkryj odpowiedź → oceń „znałem" / „nie znałem") i zobaczyć wynik na koniec.

Pełny kontekst produktowy: [`context/foundation/prd.md`](context/foundation/prd.md). Uzasadnienie stacku: [`context/foundation/tech-stack.md`](context/foundation/tech-stack.md). Wybór platformy deploymentu: [`context/foundation/infrastructure.md`](context/foundation/infrastructure.md).

## Stack

- **Laravel 13** (PHP 8.3+) — backend, Eloquent, migracje, sesje, kolejki
- **Laravel Breeze** — auth (rejestracja, logowanie, wylogowanie)
- **Blade + Alpine.js + Tailwind (Vite)** — UI, bez frontendowego SPA
- **SQLite** lokalnie, **Postgres/MySQL** (co-located service) na produkcji
- **Anthropic SDK** (`anthropic-ai/sdk`) — generowanie fiszek modelem `claude-haiku-4-5`, ze strukturalnym JSON Schema na wyjściu
- Docelowa platforma hostingowa: **Railway** (patrz `infrastructure.md`)

## Jak to działa

1. Zalogowany użytkownik wkleja tekst źródłowy na `/dashboard`.
2. `FlashcardGenerator` (implementacja: `AnthropicFlashcardGenerator`) wysyła tekst do Claude z promptem proszącym o 5–15 par pytanie–odpowiedź + krótki tytuł, wymuszając strukturę odpowiedzi przez `json_schema`.
3. Wygenerowany zestaw (`FlashcardSet` + powiązane `Flashcard`) zapisywany jest w bazie, przypisany do właściciela.
4. Użytkownik przegląda fiszki na stronie zestawu — może każdą **edytować** (pytanie/odpowiedź) lub **usunąć** (z potwierdzeniem w modalu), albo od razu **rozpocząć sesję nauki**.
5. W sesji nauki fiszki pokazywane są pojedynczo (flip pytanie → odpowiedź), z oceną „znałem" / „nie znałem"; na końcu wyświetlany jest wynik liczbowy.

Wszystkie zestawy i fiszki są prywatne — widoczne wyłącznie dla właściciela konta (autoryzacja sprawdzana na poziomie każdego route'a operującego na cudzym zasobie).

## Wymagania

- PHP 8.3+
- Composer
- Node.js + npm (assety przez Vite)
- Klucz API Anthropic (do generowania fiszek)

## Uruchomienie lokalne

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # domyślna baza lokalna to SQLite
php artisan migrate
npm install
```

Uzupełnij `.env` (patrz sekcja niżej), a następnie uruchom cały stack deweloperski jedną komendą:

```bash
composer run dev
```

Odpala równolegle: serwer PHP (`php artisan serve`), `queue:listen`, `pail` (podgląd logów na żywo) i `npm run dev` (Vite). Alternatywnie samo `npm run dev`, jeśli serwer PHP uruchamiasz osobno.

## Zmienne środowiskowe

Bazowa konfiguracja pochodzi z `.env.example` — poniżej tylko to, co istotne dla tego projektu (reszta to standardowe zmienne Laravela: `APP_KEY`, `DB_*`, `SESSION_*` itd., generowane/domyślne przez `php artisan key:generate` i skeleton).

| Zmienna | Wymagana | Opis |
|---|---|---|
| `ANTHROPIC_API_KEY` | Tak (do generowania fiszek) | Klucz API Anthropic używany przez `AnthropicFlashcardGenerator` do wywołań modelu `claude-haiku-4-5`. Bez niego generowanie zestawu zwróci błąd (`FlashcardGenerationException`) — reszta aplikacji (auth, przeglądanie/edycja/usuwanie istniejących zestawów, sesja nauki) działa bez tego klucza. |
| `DB_CONNECTION` | Nie (domyślnie `sqlite`) | Lokalnie SQLite; na produkcji (Railway) `pgsql` z danymi połączenia wstrzykiwanymi automatycznie przez co-located serwis Postgresa. |
| `APP_URL` | Nie | Bazowy URL aplikacji, m.in. do generowania linków. |

**Nigdy nie commituj prawdziwego `ANTHROPIC_API_KEY`** — na produkcji ustawiaj go przez `railway variables set ANTHROPIC_API_KEY=...`, nie przez dashboard ani w repo.

## Komendy

```bash
composer run dev              # serwer + queue:listen + pail (logi) + vite, równolegle
php artisan test               # pełny zestaw testów (czyści cache configu przed startem)
php artisan test --filter=X    # pojedynczy test
vendor/bin/pint                # formatowanie PHP (Pint, konwencje Laravela)
npm run dev                    # samo Vite
npm run build                  # build assetów produkcyjnych
```

Brak dodatkowego linta/statycznej analizy poza Pintem — nie zakładaj obecności PHPStan/Larastan.

## Struktura

Standardowa struktura Laravela — nic ponad `app/Http/Controllers`, `app/Models`, `app/Services` (generowanie fiszek), `routes/web.php`, `database/migrations`, `resources/views` (Blade).

Kluczowe modele: `User` → `FlashcardSet` (1:N) → `Flashcard` (1:N), z kaskadowym usuwaniem (usunięcie usera kasuje jego zestawy, usunięcie zestawu kasuje jego fiszki).

## Deployment

Docelowo Railway (Nixpacks, bez ręcznego Dockerfile, co-located baza danych). Szczegóły, ryzyka i checklistę startową — patrz [`context/foundation/infrastructure.md`](context/foundation/infrastructure.md). Katalog `deploy/` jest obecnie pusty — wcześniejszy pipeline pod self-hosting został usunięty po zmianie decyzji na Railway.

## Testy

```bash
php artisan test
```

Testy feature'owe pokrywają m.in. generowanie fiszek, listę zestawów, edycję i usuwanie fiszek oraz wymuszanie własności zasobu (użytkownik nie może operować na cudzym zestawie/fiszce — 403/404, gość przekierowywany do logowania).
