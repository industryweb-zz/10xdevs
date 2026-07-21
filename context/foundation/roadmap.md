---
project: "KnowledgeTest"
version: 1
status: draft
created: 2026-07-19
updated: 2026-07-21
prd_version: 1
main_goal: market-feedback
top_blocker: capacity
---

# Roadmap: KnowledgeTest

> Wygenerowane z `context/foundation/prd.md` (v1) + auto-zbadanego stanu bazowego kodu.
> Edytuj w miejscu; archiwizuj przy pełnej regeneracji.
> Slice'y poniżej są w kolejności zależności. Tabela "W skrócie" to indeks.

## Streszczenie wizji

Ręczne tworzenie fiszek z materiału edukacyjnego jest czasochłonne i wymaga wiedzy domenowej, której uczący się może jeszcze nie mieć. Istniejące narzędzia (Anki, Quizlet) wymagają ręcznej pracy, a ChatGPT generuje fiszki, ale bez zintegrowanego trybu nauki. Luka rynkowa, którą KnowledgeTest wypełnia: nikt nie połączył generowania fiszek przez AI z pełnym cyklem nauki (wklej tekst → ucz się) w jednym miejscu.

## Gwiazda przewodnia

**S-02: Użytkownik wkleja tekst, otrzymuje fiszki wygenerowane przez AI i uczy się z nich, widząc wynik na koniec** — to jedyna historyjka użytkownika w PRD (US-01) i dokładnie odpowiada Primary Success Criterion; sprawdza najbardziej niepewny element całego pomysłu: czy AI potrafi w kilka sekund zamienić dowolny wklejony tekst w fiszki na tyle dobre, że da się z nich realnie uczyć. Jeśli to nie zadziała, reszta funkcji nie ma znaczenia.

> "Gwiazda przewodnia" (north star) oznacza tu: najmniejszy kompletny (od wejścia do wyjścia) fragment produktu, którego udane dostarczenie dowodzi, że główna hipoteza produktu się broni — umieszczony tak wcześnie, jak pozwalają na to jego zależności, bo wszystko inne ma znaczenie tylko wtedy, gdy to zadziała.

## W skrócie

| ID   | Change ID                     | Wynik (użytkownik może …)                                              | Zależności | Odniesienia PRD                              | Status   |
| ---- | ------------------------------ | ----------------------------------------------------------------------- | ---------- | --------------------------------------------- | -------- |
| S-01 | minimal-auth-for-generation     | założyć konto, zalogować się i wylogować                                 | —          | FR-001, FR-002, FR-003                        | done    |
| S-02 | generate-and-study-flashcards   | wkleić tekst, zobaczyć wygenerowane fiszki i przejść sesję nauki z wynikiem | S-01       | US-01, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009 | proposed |
| S-03 | saved-sets-list                 | zobaczyć listę swoich zapisanych zestawów fiszek                          | S-02       | FR-010                                          | proposed |

## Stan bazowy

Co już istnieje w kodzie na dzień `2026-07-19` (auto-zbadane + potwierdzone przez użytkownika).

- **Frontend:** brak — poza domyślnym Vite + widokiem `welcome.blade.php` szkieletu Laravela, nic projektowego nie istnieje.
- **Backend / API:** częściowo — czysty szkielet Laravel 13; `routes/web.php` zawiera wyłącznie trasę powitalną, żadnych kontrolerów poza pustym bazowym `Controller.php`.
- **Dane:** częściowo — tylko domyślne migracje (`users`, `cache`, `jobs`); brak tabel domenowych (zestawy fiszek, fiszki, sesje nauki).
- **Auth:** brak — Breeze/Sanctum nie zainstalowane mimo że `tech-stack.md` je zakłada; model `User` jest domyślny.
- **Deploy / infra:** obecne — pipeline Railway działa produkcyjnie (`nixpacks.toml`, `.github/workflows/deploy.yml`, pierwszy deploy zweryfikowany, HTTP 200 na `knowledge-test-app-production.up.railway.app`).
- **Observability:** brak — brak Sentry/Bugsnag/logowania poza domyślnym driverem logów Laravela.

## Foundations

_Nie zidentyfikowano żadnych Foundations._ Jedyny brakujący element bazowy o charakterze przekrojowym — autoryzacja — jest w pełni bezpośrednio widoczny dla użytkownika (rejestracja/logowanie/wylogowanie to same w sobie funkcje must-have, FR-001–003), więc został ujęty jako zwykły slice (S-01), a nie jako Foundation bez własnego rezultatu. Schemat danych dla zestawów fiszek/fiszek/sesji nie ma własnego uzasadnienia poza tym, czego potrzebuje pierwszy konsumujący slice (S-02) — jest więc wprowadzany wewnątrz S-02, zgodnie z zasadą stopniowego ujawniania elementów technicznych, a nie budowany z góry jako oddzielna warstwa. Deploy/infra już istnieje (patrz Stan bazowy) i nie wymaga dodatkowej pracy fundamentowej.

## Slices

### S-01: Autoryzacja — konto, logowanie, wylogowanie

- **Outcome:** użytkownik może założyć konto (email + hasło), zalogować się (email + hasło lub tożsamość zewnętrzna) i wylogować się.
- **Change ID:** minimal-auth-for-generation
- **PRD refs:** FR-001, FR-002, FR-003
- **Prerequisites:** —
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Sekwencjonowany jako pierwszy, bo Access Control blokuje dostęp do każdej innej funkcji dla niezalogowanego użytkownika — bez tego nic dalej nie da się realnie zweryfikować end-to-end.
- **Status:** done

### S-02: Generowanie fiszek przez AI i sesja nauki (gwiazda przewodnia)

- **Outcome:** zalogowany użytkownik wkleja tekst źródłowy, w ciągu kilku sekund widzi gotowy zestaw fiszek (pytanie–odpowiedź), rozpoczyna sesję nauki, dla każdej fiszki odkrywa odpowiedź i ocenia się jako "znałem"/"nie znałem", a na koniec widzi wynik liczbowy.
- **Change ID:** generate-and-study-flashcards
- **PRD refs:** US-01, FR-004, FR-005, FR-006, FR-007, FR-008, FR-009
- **Prerequisites:** S-01
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Ten slice łączy generowanie i sesję nauki w jeden kawałek zamiast dzielić je dalej, celowo — PRD ma dokładnie jedną historyjkę użytkownika (US-01) opisującą to jako jeden ciągły przepływ, a fiszki bez sesji nauki (albo sesja bez fiszek) nie mają żadnej samodzielnej wartości dla użytkownika. Główne ryzyko techniczne: czy generowanie AI zmieści się w budżecie 10 sekund (NFR) i czy jakość wygenerowanych fiszek będzie wystarczająca, by walidacja miała sens.
- **Status:** proposed

### S-03: Lista zapisanych zestawów fiszek

- **Outcome:** zalogowany użytkownik widzi listę swoich zapisanych zestawów fiszek.
- **Change ID:** saved-sets-list
- **PRD refs:** FR-010
- **Prerequisites:** S-02
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Sekwencjonowany po S-02, bo lista jest bezużyteczna bez co najmniej jednego wygenerowanego zestawu do wyświetlenia; niski samodzielny ryzyko techniczne — to prosty odczyt danych zapisanych w S-02.
- **Status:** proposed

## Backlog Handoff

| Roadmap ID | Change ID                     | Sugerowany tytuł zgłoszenia                                  | Gotowe do `/10x-plan` | Uwagi |
| ---------- | ------------------------------ | -------------------------------------------------------------- | --------------------- | ----- |
| S-01       | minimal-auth-for-generation     | Autoryzacja: rejestracja, logowanie, wylogowanie                | yes                   | Uruchom `/10x-plan minimal-auth-for-generation` |
| S-02       | generate-and-study-flashcards   | Generowanie fiszek przez AI + sesja nauki z wynikiem            | no                    | Zablokowane przez S-01 (Prerequisites) |
| S-03       | saved-sets-list                 | Lista zapisanych zestawów fiszek                                | no                    | Zablokowane przez S-02 (Prerequisites) |

## Open Roadmap Questions

Brak — PRD nie zawiera otwartych pytań (`quality_check_status: accepted` z sesji `/10x-shape`), a wywiad roadmapowy nie ujawnił żadnych nowych pytań przekrojowych ponad to, co PRD już rozstrzygnęło.

## Parked

- **Algorytm spaced repetition (SRS)** — Dlaczego zaparkowane: PRD Non-Goals — użytkownik sam decyduje kiedy się uczy, SRS to osobna domena, nie warunek wartości MVP.
- **Ręczne tworzenie fiszek** — Dlaczego zaparkowane: PRD Non-Goals — fiszki powstają wyłącznie przez AI z wklejonego tekstu; ręczne dodawanie to v2.
- **Import plików (PDF, DOCX)** — Dlaczego zaparkowane: PRD Non-Goals — wejście to wyłącznie czysty wklejony tekst; parsowanie plików wydłużyłoby MVP.
- **Współdzielenie zestawów** — Dlaczego zaparkowane: PRD Non-Goals — fiszki są prywatne i przypisane do konta właściciela; udostępnianie to v2.
- **Tryb quiz (multiple choice)** — Dlaczego zaparkowane: PRD Non-Goals — jedyny tryb nauki w MVP to klasyczna fiszka (flip + samoocena).
- **Natywna aplikacja mobilna** — Dlaczego zaparkowane: PRD Non-Goals — aplikacja webowa (responsywna) wystarcza dla MVP; iOS/Android poza zakresem.
- **Edycja fiszek** — Dlaczego zaparkowane: PRD Non-Goals — wygenerowane fiszki są finalne; edycja to v2.

## Done

- **S-01: użytkownik może założyć konto (email + hasło), zalogować się (email + hasło lub tożsamość zewnętrzna) i wylogować się.** — Archived 2026-07-21 → `context/archive/2026-07-21-minimal-auth-for-generation/`. Lesson: —.
