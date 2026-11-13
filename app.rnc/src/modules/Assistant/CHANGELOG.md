# Changelog — Assistant Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

- The Reforma assistant's screens (rnc-3 F5, R10–R15, AM4, AM19, C4): `AssistantView` (`/equipe/assistente`: greeting, input, chips by note id, transcript, `MAnswerCard` with rule, source and reviewer, `MHandoffCard` with the fixed sentence and the specialist picker, the answers counter), `QuestionsView` (`/equipe/assistente/perguntas`: the inbox, the assigned specialist's answer, "Virar nota"), `KnowledgeView` (`/equipe/base-da-reforma`: state chips incl. "Revisão vencida", "N notas vencem em 7 dias", review, edit, retire, "Perguntas sem nota"); `MAddEditNoteForm`; eleven services; rail entries "Assistente da Reforma" and "Base da Reforma" (specialist or `assistant.manage`). Tests: `AssistantView.view.integration.spec.ts`, `MAnswerCard.spec.ts` (A14), `QuestionsView.view.integration.spec.ts` (A13, A14), `KnowledgeView.view.integration.spec.ts` (A14, A28), `MAddEditNoteForm.spec.ts`, `e2e/assistant/assistant.spec.ts`.
