# Lessons Learned

> Append-only register of recurring rules and patterns. Re-read at start by /10x-frame, /10x-research, /10x-plan, /10x-plan-review, /10x-implement, /10x-impl-review.

## Wszystkie szablony muszą być po polsku

- **Context**: Wszystkie widoki Blade (w tym scaffolding Breeze), komunikaty walidacji/auth, flash messages z kontrolerów oraz prompt generujący fiszki AI.
- **Problem**: Domyślny szkielet Laravel/Breeze i prompt do AI domyślnie generują treść po angielsku, mimo że KnowledgeTest jest aplikacją wyłącznie polskojęzyczną — bez jawnej instrukcji locale i promptu AI treść wychodzi w niewłaściwym języku.
- **Rule**: Każdy nowy string widoczny dla użytkownika (przyciski, etykiety, komunikaty walidacji/flash) musi przechodzić przez __()/lang/pl, a prompt generujący fiszki musi jawnie instruować model, by pisał w języku tekstu źródłowego (czyli po polsku). APP_LOCALE ma pozostać 'pl'.
- **Applies to**: implement, impl-review
