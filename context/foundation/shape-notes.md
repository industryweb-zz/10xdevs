---
project: "KnowledgeTest"
context_type: greenfield
product_type: web-app
target_scale:
  users: small
  qps: low
  data_volume: small
timeline_budget:
  mvp_weeks: 3
  hard_deadline: null
  after_hours_only: true
created: 2026-06-09
updated: 2026-06-09
checkpoint:
  current_phase: 8
  phases_completed: [1, 2, 3, 4, 5, 6, 7]
  gray_areas_resolved:
    - topic: "rodzaj bólu"
      decision: "czas tworzenia fiszek — dominujący problem"
    - topic: "podział ról"
      decision: "ta sama osoba tworzy i korzysta z fiszek"
    - topic: "przewaga nad ChatGPT"
      decision: "dedykowany przepływ: wklej → fiszki → sesja nauki, wszystko w jednym miejscu"
    - topic: "autoryzacja"
      decision: "logowanie email + hasło lub OAuth; płaski model ról; tryb solo (bez współdzielenia)"
    - topic: "tryb nauki"
      decision: "prosta fiszka: pytanie → ocena samodzielna (znałem/nie znałem); bez SRS w MVP"
    - topic: "timeline"
      decision: "2–3 tygodnie pracy po godzinach"
  frs_drafted: 10
  quality_check_status: accepted
---

## Vision & Problem Statement

Tworzenie fiszek z materiału edukacyjnego jest czasochłonne: uczeń lub student musi najpierw przeczytać tekst, zrozumieć go, wyciągnąć kluczowe informacje i dopiero wtedy sformułować pytania i odpowiedzi. Bariera jest podwójna — wymaga czasu i wiedzy domenowej, której osoba ucząca się może jeszcze nie mieć.

Istniejące narzędzia (np. Anki, Quizlet) wymagają ręcznego tworzenia fiszek. ChatGPT może je wygenerować, ale nie oferuje zintegrowanego trybu nauki, historii powtórzeń ani jednego spójnego przepływu "wklej tekst → ucz się". Luka polega na tym, że nikt nie połączył generowania fiszek przez AI z pełnym cyklem nauki w jednym miejscu.

## User & Persona

**Persona główna**: Uczeń lub student — osoba ucząca się z materiałów tekstowych (notatki, skrypty, artykuły), która chce samodzielnie przygotować się do sprawdzianu lub egzaminu. Ta sama osoba wkleja tekst źródłowy, przegląda wygenerowane fiszki i korzysta z nich do nauki.

---

## Access Control

Użytkownik zakłada konto i loguje się przez email + hasło lub OAuth (np. Google). Dane fiszek i historia nauki są powiązane z kontem — dostępne po zalogowaniu z dowolnego urządzenia.

- Model ról: płaski — każdy zalogowany użytkownik ma te same uprawnienia.
- Brak współdzielenia zestawów w MVP — każdy pracuje wyłącznie na swoich fiszkach.
- Niezalogowany użytkownik nie ma dostępu do żadnej funkcji poza ekranem logowania / rejestracji.

## Success Criteria

### Primary
- Użytkownik wkleja tekst i w ciągu kilku sekund otrzymuje gotowy zestaw fiszek, z których może się natychmiast uczyć. Kompletny przepływ end-to-end (tekst → fiszki → sesja nauki) działa bez błędów.

### Secondary
- Po zakończeniu sesji nauki użytkownik widzi podsumowanie: ile fiszek znał / nie znał (prosty wynik liczbowy jako motywacja).

### Guardrails
- Tekst wklejony przez użytkownika nie może trafić do nieautoryzowanych osób (prywatność materiałów edukacyjnych).

## User Stories

### US-01: Użytkownik wkleja tekst i uczy się z wygenerowanych fiszek

- **Given** zalogowany użytkownik na stronie głównej
- **When** wkleja tekst źródłowy i zleca generowanie fiszek
- **Then** widzi gotowy zestaw par pytanie–odpowiedź i może natychmiast rozpocząć sesję nauki

#### Acceptance Criteria
- Zestaw fiszek pojawia się w ciągu kilku sekund od zlecenia generowania
- Każda fiszka zawiera pytanie i odpowiedź wynikające z treści wklejonego tekstu
- Przycisk "Zacznij sesję" jest dostępny bezpośrednio po wygenerowaniu

## Functional Requirements

### Autoryzacja
- FR-001: Użytkownik może zarejestrować konto przez email i hasło. Priority: must-have
  > Socrates: Kontrargument rozważony: "anonimowe sesje wystarczyłyby do walidacji MVP". Rozwiązanie: pozostaje — bez auth nie można zapisywać zestawów między sesjami (Guardrail: trwałość danych).
- FR-002: Użytkownik może zalogować się przez email i hasło lub OAuth. Priority: must-have
  > Socrates: j.w. — powiązany z FR-001.
- FR-003: Użytkownik może wylogować się z aplikacji. Priority: must-have
  > Socrates: Kontrargument rozważony: "wylogowanie to techniczny detail, nie bloker". Rozwiązanie: pozostaje — minimum bezpieczeństwa gdy aplikacja ma konta (współdzielone urządzenia).

### Generowanie fiszek
- FR-004: Użytkownik może wkleić tekst źródłowy i zlecić AI wygenerowanie zestawu fiszek. Priority: must-have
  > Socrates: Kontrargument rozważony: "słaba jakość AI dla tekstów specjalistycznych może zniechęcić". Rozwiązanie: pozostaje — nawet niedoskonałe generowanie jest szybsze niż ręczne tworzenie; jakość to pole do iteracji po MVP.
- FR-005: Użytkownik może zobaczyć wygenerowane fiszki (listę par pytanie–odpowiedź) przed sesją. Priority: must-have
  > Socrates: Kontrargument rozważony: "zbyteczny krok — można iść wprost do sesji". Rozwiązanie: pozostaje — użytkownik musi wiedzieć ile fiszek wygenerowano i czy są sensowne.

### Sesja nauki
- FR-006: Użytkownik może rozpocząć sesję nauki z wybranego zestawu fiszek. Priority: must-have
  > Socrates: Kontrargument rozważony: "samodzielna ocena (znałem/nie znałem) jest mniej obiektywna niż quiz". Rozwiązanie: pozostaje — metapoznanie jest celowe; quiz jako opcjonalny tryb v2.
- FR-007: Użytkownik może zobaczyć pytanie, a następnie odkryć odpowiedź (flip). Priority: must-have
  > Socrates: j.w. — powiązany z FR-006.
- FR-008: Użytkownik może ocenić każdą fiszkę jako „znałem" lub „nie znałem". Priority: must-have
  > Socrates: j.w. — powiązany z FR-006.
- FR-009: Użytkownik może zobaczyć wynik po zakończeniu sesji (X z Y fiszek znanych). Priority: must-have
  > Socrates: Kontrargument rozważony: "wynik liczbowy może być zniechęcający". Rozwiązanie: pozostaje — zamyka pętlę sprzężenia zwrotnego i motywuje do kolejnej sesji.

### Zarządzanie zestawami
- FR-010: Użytkownik może zobaczyć listę swoich zapisanych zestawów fiszek. Priority: must-have
  > Socrates: Kontrargument rozważony: "lista to feature v2, MVP ma jeden aktywny zestaw". Rozwiązanie: pozostaje — lista jest konieczna do spełnienia Guardrail (fiszki nie znikają po wylogowaniu).

## Business Logic

Aplikacja identyfikuje kluczowe pojęcia i definicje w wklejonym tekście i formułuje je jako otwarte pytania z pełnymi odpowiedziami, które użytkownik może przetestować na sobie.

Wejście: dowolny tekst edukacyjny wklejony przez użytkownika (notatki, fragment skryptu, artykuł). Wyjście: zestaw par pytanie–odpowiedź, gdzie pytanie zaczyna się od „co to jest / czym jest / co oznacza" lub podobnego, a odpowiedź to definicja lub wyjaśnienie wyekstrahowane z tekstu. Użytkownik napotyka wynik reguły na ekranie podglądu fiszek, zanim rozpocznie sesję nauki.

## Non-Functional Requirements

- Użytkownik widzi wygenerowane fiszki w nie dłużej niż 10 sekund od zlecenia dla tekstu o długości typowej strony A4 (ok. 400–500 słów).
- Tekst wklejony przez użytkownika nie jest dostępny operatorowi ani osobom trzecim po zakończeniu żądania, które go przetworzyło — tylko wygenerowane fiszki są zapisywane.

## Non-Goals

- **Brak algorytmu spaced repetition (SRS)** — użytkownik sam decyduje kiedy się uczy; aplikacja nie planuje sesji na przyszłe dni. Zakres intencjonalnie wąski — SRS to osobna domena, nie warunek wartości MVP.
- **Brak ręcznego tworzenia fiszek** — fiszki powstają wyłącznie przez AI z wklejonego tekstu. Ręczne dodawanie to v2.
- **Brak importu plików (PDF, DOCX)** — wejście to wyłącznie czysty tekst wklejony ręcznie. Parsowanie plików wydłużyłoby MVP.
- **Brak współdzielenia zestawów** — fiszki są prywatne i przypisane do konta właściciela. Udostępnianie to v2.
- **Brak trybu quiz (multiple choice)** — jedyny tryb nauki to klasyczna fiszka (flip + samoocena). Quiz to v2.
- **Brak natywnej aplikacji mobilnej** — aplikacja webowa (responsywna). Dedykowane iOS/Android poza zakresem MVP.
- **Brak edycji fiszek** — wygenerowane fiszki są finalne; użytkownik nie może modyfikować treści po wygenerowaniu. Edycja to v2.

## Open Questions

<!-- uzupełniane na bieżąco -->
