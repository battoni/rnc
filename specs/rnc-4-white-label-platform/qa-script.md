# rnc-4 — Roteiro de QA no Chrome (white-label platform)

Escrito a partir de `spec.md` (A1–A49, C1–C23), `plan.md` → `## QA` e AM8, `state.md` e do i18n (`app.rnc/src/locales/local/pt-BR.json`, `app.rnc/src/locales/pt-BR.json`). Corrija passo errado AQUI. Só registros inventados dos seeders.

## Preflight

- Ambiente: API `make serve-api` (http://localhost:8000; neste worktree pode estar na 8001, e então o SPA sobe com `VITE_API_PROXY_TARGET=http://localhost:8001 npm run dev`), SPA `make serve-app` (http://localhost:5173). Pare a pilha do checkout principal antes. Node 24 no app.
- Hosts: raiz/plataforma `http://localhost:5173`; Franco `http://franco.localhost:5173`; demo `http://alvorada.localhost:5173`; `http://ipe.localhost:5173` só existe depois da rodada 5. Nunca `127.0.0.1`; nunca a porta 8000 no navegador.
- Banco: `make fresh-seed` UMA vez antes da rodada 1 (recria `api.rnc/database/database.sqlite`). Rodada 5 cria `ipe` ao vivo; nenhuma outra rodada depende de linha que outra mudou, salvo o que está dito na abertura da rodada.
- Login do motorista (AM8, C2): abrir `http://<host-da-firma>:5173/api/qa/login-as/<usuário>?to=/<rota>` NO HOST DA FIRMA (passa pelo proxy do Vite; o host dá a firma). Antes de CADA rodada: limpar os cookies de `localhost` (e dos subdomínios) e nunca reaproveitar a sessão da rodada anterior. ANTES de abrir o link, no mesmo host: `localStorage.setItem('app.rnc:session-hint','1')` (via javascript_tool numa página desse host) — o app só confere a sessão com essa marca, que o login real grava e o atalho de QA pula (rnc-1 qa.local.md). O "Sair" do app APAGA `app.rnc:session-hint`: marcar de novo antes de CADA link de login, não só no começo da rodada. Senha errada no login real mostra "Usuário ou senha incorretos". Resposta esperada: redireciona para `<host>:5173/<rota>` já logado. Sem ferramenta para limpar cookies: abrir aba nova e, antes do login da rodada, chamar `fetch('/api/auth/logout',{method:'POST',headers:{Accept:'application/json'}})` no host da rodada anterior.
- Usuários (senha `seeding.password`, mas o motorista entra pelo `login-as`): `battoni` (operador, só raiz) · `gestao.hub` (gestor da plataforma) · `helena.alvorada` (gestora demo) · `equipe.alvorada` (equipe demo), `bruno.alvorada` (especialista) · `reinaldo` (gestao, Franco) · `patricia` (equipe, Franco) · `parceiro.1` (Franco), `parceiro.alvorada.1`.
- Larguras: 390 e 1440 nas páginas públicas; 1440 no back office. Trocar com `resize_window`; a barra de rolagem lateral se mede com `document.documentElement.scrollWidth <= window.innerWidth`.
- Checagens de TODA rodada (marcar no fim de cada uma): (a) nenhum texto "Franco", "Reinaldo", "Willy" ou "APW" visível em páginas de alvorada, ipe e da raiz (usar `get_page_text`; na rodada 4 são esperados só no host Franco); (b) nenhum erro no console (`read_console_messages`); (c) nenhuma rolagem lateral.
- Segurança: só dados locais inventados; e-mails vão para `api.rnc/storage/logs/laravel.log` (`MAIL_MAILER=log`); não fechar janelas do Chrome ao terminar.
- `Chrome: skip (<spec>)` = o Playwright em `app.rnc/e2e/` já prova aquele A# naquela largura (com API simulada); o motorista pula o passo.

### Consultas necessárias (o juiz roda depois do sim, não quem escreve o roteiro)

- Firmas: `select id, slug, name, is_platform, offboarded_at from firms;` (esperado `franco`, `hub` plataforma, `alvorada`).
- Leads da alvorada com reunião e sem anonimização (rodada 7): `select l.id, l.company, l.name, l.email from leads l join firms f on f.id = l.firm_id where f.slug = 'alvorada' and l.anonymised_at is null order by l.id limit 5;` (NÃO VERIFICADO o nome da coluna `anonymised_at`: conferir no schema). Usar o 1º para "Exportar" e o 2º para "Anonimizar".
- Consentimento: `select firm_id, version, text from consent_versions order by firm_id, version;` (rodadas 3 e 4).

---

## Rodada 1 — Visitante na raiz: landing do produto e beta

Dados: `make fresh-seed` (uma vez, agora). Login: nenhum para os passos 1–9 (aba anônima ou cookies limpos); o passo 10 usa `http://localhost:5173/api/qa/login-as/gestao.hub?to=/equipe/pipeline`; o 11 usa `reinaldo` em `franco.localhost` e `helena.alvorada` em `alvorada.localhost` (marcar `session-hint` antes de cada link). Larguras: 390 e 1440 (landing), 1440 (back office).

Campo → onde precisa aparecer (passo 8 digita, o 10 lê de volta):

| Campo do formulário | Valor a digitar | Onde precisa aparecer depois |
| --- | --- | --- |
| Escritório | `Contábil Teste Aurora` | cartão do pipeline de `gestao.hub`; título "Inscrição recebida" (texto da confirmação cita o nome) |
| Seu nome | `Marta Teste` | ficha do lead (`/equipe/leads/:id`) |
| Cargo | `Sócia` | ficha do lead, perfil |
| E-mail | `marta@aurora.exemplo.com.br` | ficha do lead; e-mail do aviso à equipe em `laravel.log` |
| Telefone ou WhatsApp | `(11) 98888-7777` | ficha do lead |
| Cidade / UF | `Campinas` / `SP` | ficha do lead, perfil |
| Quantos clientes o escritório atende? | `301 a 1.000` | ficha do lead, perfil |
| Algo que a gente deva saber? (opcional) | `Teste de QA do beta` | ficha do lead |
| Consentimento | marcado | ficha do lead › "Consentimento", `versão` da plataforma |

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 1.1 | Abrir `http://localhost:5173/` em 1440 | nada | Kicker "Para escritórios de contabilidade"; título "Seus clientes vão perguntar da Reforma."; botão "Quero participar do beta"; link "Veja um escritório de exemplo"; menu "Como funciona", "Módulos", "Beta", "Perguntas". [A32] | Aparece o diagnóstico ("Fazer o diagnóstico gratuito") ou o nome Franco |
| 1.2 | Mesma página, rolar até o fim | nada | Seções "Por que agora", "Como funciona", "Módulos" com "Sempre ligados" e "Opcionais", "Programa beta" ("Poucos escritórios", "Cerca de três meses", "Preço", "Vocês no produto"), "Perguntas", rodapé "O hub da Reforma Tributária para escritórios de contabilidade." e a data "01/01/2027". [A32] | Seção faltando; data diferente de 01/01/2027 |
| 1.3 | Abrir `http://localhost:5173/diagnostico` | nada | Cai na landing do produto (a raiz nunca mostra o diagnóstico). [A32] | Formulário do diagnóstico abre |
| 1.4 | Redimensionar para 390 e recarregar `/` | nada | Mesmas seções empilhadas; sem rolagem lateral (`scrollWidth <= innerWidth`). `Chrome: skip (e2e/product/landing.spec.ts)` para a rolagem a 390; ler o texto ainda vale. [A34] | Rolagem lateral; texto cortado |
| 1.5 | Clicar "Quero participar do beta" (cabeçalho) | nada | Rola até o formulário "Quero participar do beta" com o lede "Leva 2 minutos. A gente responde pelo e-mail ou pelo telefone que você deixar." `Chrome: skip (e2e/product/landing.spec.ts)` para o clique do cabeçalho; aqui só conferir o formulário. [A33] | Nada acontece |
| 1.6 | Formulário, clicar "Quero participar do beta" com tudo vazio | nada | Erros "Preencha este campo.", "Escolha a UF.", "Escolha uma faixa." e "Para falarmos com você, precisamos da sua autorização."; nada enviado. `Chrome: skip (e2e/product/landing.spec.ts)`. [A33] | Envia vazio |
| 1.7 | Preencher só e-mail `abc` e telefone `123`, enviar | `abc` / `123` | "Informe um e-mail válido." e "Informe o telefone com DDD." [A33] | Aceita |
| 1.8 | Preencher a tabela "Campo → onde" acima, marcar o consentimento, clicar "Quero participar do beta" | valores da tabela | Estado "Inscrição recebida" com "Obrigado! A inscrição da Contábil Teste Aurora chegou à equipe do" + nome da plataforma. [A31, A33] | Erro "Não conseguimos enviar agora."; nome da plataforma vazio |
| 1.9 | Rede (`read_network_requests`) | nada | `POST /api/public/beta-signups` → 201, SEM cabeçalho `X-Firm`. [A31] | 404, 422 ou `X-Firm` presente |
| 1.10 | Login `gestao.hub` → `/equipe/pipeline`; procurar "Contábil Teste Aurora" | nada | Cartão "Contábil Teste Aurora" com o chip "Beta" (filtro "Canal: Beta"); abrir o cartão (`/equipe/leads/:id`) e ler CADA linha da tabela "Campo → onde": nome, cargo, e-mail, telefone, cidade/UF, faixa, mensagem, consentimento. [A33, A31] | Qualquer campo `N/A` ou vazio; cartão sem "Beta" |
| 1.11 | Limpar cookies; login `reinaldo` em `franco.localhost:5173` → `/equipe/pipeline`, buscar "Contábil Teste"; limpar cookies; login `helena.alvorada` em `alvorada.localhost:5173` → `/equipe/pipeline`, buscar "Contábil Teste" | busca `Contábil Teste` | Nos dois: "Nenhum lead" na busca (texto de `pipeline.emptySearch`). NÃO VERIFICADO o texto exato: citar o que aparecer. Não buscar "Aurora": cada firma tem o seu "Centro Médico Aurora" do seed, que apareceria por design. [A31, A1] | O lead beta ("Contábil Teste Aurora") aparece fora do hub |
| 1.12 | Em `franco.localhost:5173/`, sem sessão | nada | A página mostra o diagnóstico do Franco ("Fazer o diagnóstico gratuito"), não o beta; `POST /api/public/beta-signups` por `fetch` neste host, com o cabeçalho `X-Firm: franco` (o app sempre o manda; sem ele o pedido vai para a raiz), responde 404. [A32] | Landing do produto no host Franco |

Conferir depois (rodada 1): `laravel.log` tem o e-mail de aviso à equipe da plataforma; `leads` tem 1 linha `channel = beta`, `stage = lead`, `firm_id` do `hub` com a versão de consentimento da plataforma.

---

## Rodada 2 — Visitante em alvorada: diagnóstico, resultado, agendamento

Dados: o seed da rodada 1. Login: nenhum (cookies limpos). Host `http://alvorada.localhost:5173`. Larguras: 390 e 1440. A rodada cria 1 lead novo; nenhuma outra depende dele.

Campo → onde precisa aparecer:

| Campo | Valor | Onde precisa aparecer depois |
| --- | --- | --- |
| Empresa | `Padaria Trigo Dourado` | ficha do lead (rodada 3, passo 3.2), relatório "Preparado para", e-mail de confirmação |
| Seu nome | `Rita Teste` | ficha do lead |
| E-mail | `rita@trigodourado.exemplo.com.br` | e-mail em `laravel.log` (para: rita@…) |
| WhatsApp ou telefone | `(11) 97777-6666` | ficha do lead |
| Setor | `Comércio` | ficha do lead, perfil; resultado "Perfil: …" |
| Regime tributário | um que não seja o padrão (ex.: Lucro Presumido) | resultado "Perfil: …" |
| Faturamento anual | faixa que NÃO seja a primeira | resultado e ficha |
| Consentimento (parceiros) | marcado | ficha do lead › "Consentimento" "Autorizou ser apresentado a parceiros" |
| Especialista | `Camila` (não a indicada) | confirmação "com Camila Rocha" e e-mail |

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 2.1 | Abrir `http://alvorada.localhost:5173/` em 1440 | nada | Nome "Alvorada Contabilidade", tagline "Contabilidade de perto para a empresa que está crescendo", botão "Fazer o diagnóstico gratuito"; cores verdes (primária `#0f5c4d`); rodapé "© <ano> Alvorada Contabilidade". [A8, A11, A16] | Cores/nome do Franco; "Contabilidade e consultoria empresarial em São Paulo há 35 anos." (frase do Franco) |
| 2.2 | Mesma página em 390 | nada | Mesmo conteúdo empilhado, sem rolagem lateral; faixa de provas = as quatro de alvorada (ou nenhuma). [A11, C10] | Prova do Franco ("RNC"); rolagem lateral |
| 2.3 | `<html>`: `getComputedStyle(document.documentElement).getPropertyValue('--color-primary-500')` | nada | Valor verde próximo de `#0f5c4d`, não o ciano/azul-marinho do Franco (`#00375a`). [A11] | Azul do Franco |
| 2.4 | Clicar "Fazer o diagnóstico gratuito" | nada | Abre `/diagnostico`, título "Diagnóstico gratuito da Reforma"; alternância "Formulário" / "Conversar no chat" (ficar em "Formulário"); primeiro passo "Tenho o CNPJ" / "Não tenho o CNPJ" (escolher "Não tenho o CNPJ"). [A1] | 404 |
| 2.5 | Passos do diagnóstico, na ordem em que aparecem | Preencher conforme a tabela; "Continuar" em cada passo; recarregar a página no meio e continuar. Não há botão "Voltar" | Ordem: escolha do CNPJ → "Perfil" ("Setor", "Regime tributário") → contato "Para onde enviamos o resultado?" (Empresa, "Seu nome", "WhatsApp ou telefone", "E-mail") → "Perfil da empresa" ("Quanto das vendas vai para empresas (B2B)") → "Faturamento e operação" ("Faturamento anual", "Tenho incentivos fiscais de ICMS", "Exporto produtos ou serviços"); os valores seguem preenchidos depois do recarregamento. [A1] | Valor perdido ao recarregar; ordem diferente |
| 2.6 | Passo de contato (o 2º passo, antes de "Perfil da empresa"): ler o texto de consentimento | nada | Frase "Autorizo Alvorada Contabilidade a me apresentar a especialistas parceiros quando o diagnóstico indicar." (nome de alvorada); link "Como tratamos os seus dados" abre `/privacidade` em nova aba SEM marcar a caixa. [A10, C5] | Nome Franco na frase; link marca a caixa |
| 2.7 | Depois de "Faturamento e operação", clicar "Ver meu resultado" | nada | Título "Seu resultado"; "Encontramos N oportunidades e N riscos"; "Perfil: Comércio …"; "Detalhe liberado na reunião com o especialista"; "Em 30 minutos, online ou na Alvorada, ele explica a conta por trás de cada valor e o plano de ação."; botão "Agendar com um especialista". [A16] | "Franco" no texto |
| 2.8 | Clicar "Agendar com um especialista" | nada | "Agende com um especialista"; "Em 30 minutos, online ou na Alvorada, ele explica a conta por trás de cada valor e o plano de ação."; abas "Especialista" com Bruno e Camila, "Dia", "Horário". [A37] | Especialistas do Franco (Mariana, Carlos…) |
| 2.9 | Escolher Camila, um dia e um horário; "Confirmar …" (a tela não tem campo de e-mail: o e-mail vem do passo de contato) | nada | "Reunião confirmada"; "com Camila Rocha"; "30 minutos, online ou no escritório da Alvorada."; botão "Abrir o Relatório de Impacto" (não há "Anote na agenda"). [A9] | Horário não bloqueado; erro `slotTaken` |
| 2.10 | Clicar "Abrir o Relatório de Impacto" | nada | `/relatorio/<token>`, "Relatório de Impacto da Reforma Tributária", "Preparado para" `Padaria Trigo Dourado`, "Reunião … com Camila Rocha"; sem "Franco". [A16, A9] | Nome em branco; "Franco" |
| 2.11 | Repetir 2.1 e 2.4 e 2.10 em 390 | nada | Sem rolagem lateral; texto igual. [A11] | Rolagem lateral |
| 2.12 | Recarregar o link do relatório numa janela anônima | nada | Relatório abre sem login. [A16] | "Este link expirou ou não existe" |
| 2.13 | Abrir `http://alvorada.localhost:5173/reforma/comercio` | nada | Página de setor com a marca de alvorada e sem "Franco". [A16] | Marca do Franco |

Conferir depois (rodada 2): em `laravel.log`, o e-mail de confirmação e o de relatório têm remetente "Alvorada Contabilidade", reply-to `contato@alvorada.exemplo.com.br`, links em `http://alvorada.localhost:5173/…`, e `grep -ci "franco"` do corpo = 0 (HTML e texto) [A9, C6]; `leads` novo com `firm_id` = alvorada [A1]; rede: toda chamada a `/api/…` leva `X-Firm: alvorada` [A6]; consentimento com `version` = 1 e o texto de alvorada [A10].

---

## Rodada 3 — Gestora da demo: briefing, pipeline, métricas, configurações

Dados: o seed (lead `Padaria Trigo Dourado` da rodada 2 existe; se a rodada 2 não rodou, usar qualquer lead do pipeline). Login: `http://alvorada.localhost:5173/api/qa/login-as/helena.alvorada?to=/gestao/briefing` (cookies limpos antes). Largura: 1440.

Campo → onde precisa aparecer:

| Campo | Valor | Onde precisa aparecer depois |
| --- | --- | --- |
| Endereço | `Rua das Acácias, 120 — Curitiba/PR` | rodapé de `http://alvorada.localhost:5173/` (aba anônima) |
| Telefone | `(41) 3000-1234` | rodapé e `/privacidade` ("Telefone: …") |
| E-mail de contato | `atendimento@alvorada.exemplo.com.br` | rodapé; remetente/reply-to dos próximos e-mails |
| Linha de credenciais | `Associada ao CRC-PR (exemplo)` | rodapé › "Credenciais" |
| Horário (Faixa 1) | Seg–Sex, 09:00–17:00 | rodapé › "Atendimento", agenda do diagnóstico |
| Novo acesso: Nome / Sobrenome / Usuário / Papel | `Teste` / `Equipe` / `teste.alvorada` / equipe | lista "Acessos da equipe" com "Ativo"; senha mostrada uma vez |
| Horário do especialista (Bruno) | trocar a faixa de terça para 10:00–12:00 | agenda do diagnóstico (rodada 2, mesmo dia da semana) |
| Texto do consentimento | `Autorizo {escritorio} a apresentar o meu contato a parceiros, para falar sobre a Reforma.` | histórico "v2", visitante lê com o nome de alvorada; leads antigos ficam em v1 |

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 3.1 | `/gestao/briefing` | nada | Título "Briefing do dia"; grupo da rail "Gestão"; logo-texto "Alvorada Contabilidade" e o nome da plataforma no cabeçalho; sem "Franco", "Reinaldo". [A13, A11, A16] | Rail "Reinaldo"; nome Franco |
| 3.2 | Rail › Equipe › "Pipeline" (`/equipe/pipeline`) | nada | "Pipeline de leads", colunas "Lead", "Reunião marcada", "Proposta", "Cliente", "Perdido"; ≥ 40 leads; abrir o lead `Padaria Trigo Dourado` (`/equipe/leads/:id`; o seed e rodadas anteriores podem deixar várias com esse nome: a certa é a que tem a reunião de 16/11 às 10:00, a da rodada 2) e ler empresa, nome, e-mail, telefone, setor, "Consentimento › Autorizou ser apresentado a parceiros", "Reunião" "com Camila Rocha". [A23, A1] | Lead de outra firma; campo vazio |
| 3.3 | Rail › Gestão › "Métricas" (`/gestao/metricas`) | nada | Título "Métricas"; funil "Funil dos últimos N dias"; sem "Canal RNC". [A15] | "RNC" fora do canal |
| 3.4 | Rail › Gestão › "Contato do escritório" (`/gestao/escritorio`) | preencher a tabela; Faixa 1: dias Seg–Sex, "Abre" 09:00, "Fecha" 17:00; "Salvar" | "Contato salvo. O site mostra na próxima visita." Recarregar e ler cada campo de volta. Não há campos de cor, logo ou módulos (a lede diz "A identidade visual do escritório é definida pela plataforma."). [A35, A39] | Campo de cor visível; campo não persiste |
| 3.5 | Aba anônima: `http://alvorada.localhost:5173/` (rolar ao rodapé) | nada | "Contato", "Atendimento: Segunda a sexta, 9h às 17h" (horas sem zero à esquerda), endereço, telefone, e-mail e "Credenciais: Associada ao CRC-PR (exemplo)". [A35] | Dados antigos ou do Franco |
| 3.6 | Rail › Gestão › "Acessos da equipe" (`/gestao/equipe`) → "Novo acesso" | Nome `Teste`, Sobrenome `Equipe`, Usuário `teste.alvorada`, Papel `equipe`, marcar "Especialista — o visitante pode agendar uma reunião com esta pessoa"; "Criar acesso" | Painel "teste.alvorada foi criado. A senha:" com botão "Copiar" e o aviso "Copie agora: ela não será mostrada de novo. Uma nova pode ser gerada no login do gestor."; na lista, "Teste Equipe" com "Ativo" e o selo "Especialista". Papel `operador` NÃO está entre as opções. [A36, A39] | Senha reaparece depois; opção `operador` |
| 3.7 | Mesma linha › "Desativar" | confirmar | "Desativar Teste Equipe? O acesso para de funcionar até ser reativado." → "Inativo"; depois "Reativar" volta a "Ativo". [A36] | Linha some |
| 3.8 | Mesma linha › "Redefinir senha" › "Gerar senha nova" | nada | Senha nova mostrada uma vez ("Redefinir senha"), com o mesmo aviso "Copie agora: ela não será mostrada de novo. Uma nova pode ser gerada no login do gestor." [A36] | Senha igual à anterior |
| 3.9 | Rail › Gestão › "Especialistas" (`/gestao/especialistas`) | Bruno › "Horário": mudar a faixa de terça para 10:00–12:00; "Salvar horário" | A lista mostra "Bruno Siqueira" com o horário novo em "Horário semanal"; `teste.alvorada` aparece com o horário do escritório (09:00–17:00) por ser recém-marcado. [A37, C17] | Teste sem horário |
| 3.10 | Aba anônima: diagnóstico de alvorada até "Agende com um especialista", escolher o próximo dia que seja terça | nada | Horários de Bruno só entre 10:00 e 12:00 nesse dia. [A37] | Horários antigos |
| 3.11 | Rail › Gestão › "Consentimento" (`/gestao/consentimento`) | nada | Título "Consentimento"; "A versão 1 é a que o visitante vê hoje."; "Histórico" com `v1` "Padrão da plataforma". [A38, A39] | Sem histórico |
| 3.12 | Mesma tela: apagar o texto e digitar a frase da tabela; "Salvar nova versão" | frase da tabela | "Como o visitante lê" mostra "Autorizo Alvorada Contabilidade a apresentar …" (o nome entra no lugar de `{escritorio}`); depois de salvar: "Versão 2 salva. Os novos diagnósticos usam ela."; histórico com `v2` e `v1`, v2 com o nome da autora (Helena Martins). [A38, A39] | `{escritorio}` literal; v1 apagada |
| 3.13 | Mesma tela: salvar de novo sem mudar | nada | "Este é o mesmo texto da versão atual. Mude antes de salvar." (nenhuma v3). [A38] | v3 criada |
| 3.14 | Abrir o lead `Padaria Trigo Dourado` de novo | nada | "Consentimento" ainda "versão 1" (não passou para 2). [A38, A10] | v2 no lead antigo |
| 3.15 | Escrever `{escritorio}` fora: salvar o texto sem `{escritorio}` | `Autorizo tudo.` | Erro "O texto precisa nomear o escritório: escreva {escritorio} onde vai o nome dele." (nada salvo). [C15, A39] | Salva |
| 3.16 | `/gestao/escritorio`, `/gestao/equipe`, `/gestao/consentimento`, `/gestao/especialistas` em 1440 | nada | Sem rolagem lateral. `Chrome: skip (e2e/firm-settings/settings.spec.ts)` para a rolagem a 1440 (o spec cobre 390 e 1440, claro e escuro). [A40] | Rolagem lateral |
| 3.17 | Cabeçalho da conta › "Sair" | nada | "Até logo!" e tela de login. [A7] | Sessão continua |

Conferir depois (rodada 3): `PATCH /api/firm/settings` → 200 com `X-Firm: alvorada`; `POST /api/firm/consent-versions` → 201 `version` 2; `GET /api/auth/users` lista só usuários da alvorada [A36].

---

## Rodada 4 — Tenant Franco: briefing, canal RNC, site Express/Prime, consentimento v1

Dados: `fresh-seed`; sem dependência das rodadas anteriores (rodada 3 mexeu só na alvorada). Login: `http://franco.localhost:5173/api/qa/login-as/reinaldo?to=/gestao/briefing` (cookies limpos antes). Larguras: 390 e 1440 (site público), 1440 (back office). Nesta rodada "Franco", "Reinaldo" e "RNC" SÃO esperados no host franco (a regra de ausência vale só para alvorada, ipe e raiz).

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 4.1 | `/gestao/briefing` | nada | "Briefing do dia" com as seções "As 3 ações de hoje", "Reforma: CBS em vigor em", "Próximas reuniões", "Indicações e SLA de 24h úteis" e "Pipeline por etapa"; grupo "Gestão"; logo "Franco Contabilidade"; paleta azul-marinho/ciano. Radar, Contratos, Reajuste e Parceiros NÃO são seções do briefing: são telas próprias da rail (4.4). [A13, A20, A11] | Seção faltando; `/reinaldo/briefing` abre |
| 4.2 | Abrir `http://franco.localhost:5173/reinaldo/briefing` | nada | Tela de não encontrado do app (a rota não existe); NÃO VERIFICADO o texto da 404 interna: citar o que aparecer. [A13] | A página renderiza |
| 4.3 | Rail › Gestão › "Canal RNC" (`/gestao/canal-rnc`) | nada | Tela do canal carrega com dados; "RNC" aparece aqui (módulo `channel`). [A15, A17] | 404 |
| 4.4 | Rail › Gestão › "Parceiros", "Reajuste", "Radar da carteira", "Alerta de contratos" | nada | Todas abrem (módulos ligados no Franco). [A24] | 404 |
| 4.5 | Aba anônima: `http://franco.localhost:5173/` em 1440 | nada | Landing do Franco com "Franco Contabilidade", frase "Contabilidade e consultoria empresarial em São Paulo há 35 anos." e a faixa de provas do Franco. [A8, C10] | Marca da plataforma |
| 4.6 | Mesma aba: `http://franco.localhost:5173/?marca=prime` | nada | Landing da submarca Express/Prime (nome "Franco Prime …", cores próprias) só nas páginas públicas. [A11, C9] | Marca principal |
| 4.7 | Mesma aba: `…/?marca=express` e depois `…/?marca=franco` | nada | Express mostra sua marca; `?marca=franco` solta a submarca e volta à marca principal. [C9] | Submarca fica presa |
| 4.8 | Mesma aba: `…/?marca=principal` | nada | Marca principal. [C9] | Submarca |
| 4.9 | Em 390, repetir 4.5 | nada | Sem rolagem lateral. [A11] | Rolagem lateral |
| 4.10 | Em `franco.localhost:5173/diagnostico?marca=prime`, avançar até o contato e ler o consentimento | nada | Frase com "Franco Prime …" ("Autorizo a Franco Prime Contabilidade a me apresentar…"). [A10] | Sem nome da submarca |
| 4.11 | Abrir `franco.localhost:5173/diagnostico?marca=principal` com carga completa da página (depois de 4.10 a marca Prime fica guardada na aba, por desenho — rnc-3 R2; o `?marca=principal` é o que limpa), mesmo ponto | nada | "Autorizo a Franco Contabilidade a me apresentar…" (C5). [A10, C5] | Nome ausente |
| 4.12 | Fazer o diagnóstico completo com a empresa `Mercado Boa Safra`, e-mail `boasafra@exemplo.com.br`, e abrir a ficha como `reinaldo` | nada | Ficha do lead › "Consentimento" "versão 1". Um lead antigo do seed também "versão 1". [A10] | versão 2 |
| 4.13 | `/gestao/consentimento` como `reinaldo` | nada | "A versão 1 é a que o visitante vê hoje." (nada mudou por causa da rodada 3). [A38, A10] | v2 no Franco |

Conferir depois (rodada 4): `consent_versions` do Franco só com `version = 1`; leads do Franco com `firm_id` do Franco.

---

## Rodada 5 — Operador da plataforma: criar `ipe`, módulos, logo, "Acessar como suporte"

Dados: `battoni` entra pela raiz. Esta rodada CRIA `ipe` ao vivo (`http://ipe.localhost:5173`); nenhuma outra rodada depende dele. Login: `http://localhost:5173/api/qa/login-as/battoni?to=/plataforma/escritorios` (cookies limpos; NUNCA na porta 8000). Largura: 1440 (e 390 só onde o passo disser).

Campo → onde precisa aparecer:

| Campo | Valor | Onde precisa aparecer depois |
| --- | --- | --- |
| Nome do escritório | `Ipê Contábil` | linha do console; `http://ipe.localhost:5173/` (logo-texto); `Concluir` mostra "Ipê Contábil foi criado em …" |
| Endereço (subdomínio) | `ipe` | "O escritório vai responder em ipe.localhost:5173"; a linha da lista |
| Usuário do gestor | `gestao.ipe` | "O gestor entra lá como gestao.ipe."; `/api/qa/login-as/gestao.ipe` |
| E-mail do gestor (opcional) | `gestao@ipe.exemplo.com.br` | `GET /api/auth/users` do ipe |
| Nome curto / Slogan | `Ipê` / `Contabilidade que floresce` | landing do ipe |
| Cor principal | `#7fd3ff` primeiro, depois `#6b2a8a` | recusa de contraste; depois o ipe painted com roxo |
| Cor de destaque | `#e8a317` | landing do ipe |
| Provas (4 pares) | `10+` / `anos` e três mais | faixa de provas do ipe |
| Logo | PNG pequeno (ex.: 300 KB) | landing do ipe e rail do back office; SVG recusado |

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 5.1 | `/plataforma/escritorios` | nada | Título "Escritórios"; lede "Todos os escritórios da plataforma …"; linhas Franco, Alvorada e "Plataforma" com "N leads", "Criado em …", botões "Visual", "Módulos", "Login do gestor"; botão "Novo escritório". [A29, A26] | Franco aparece como plataforma; rail com telas do back office |
| 5.2 | Rail: conferir entradas | nada | Só "Plataforma" › "Escritórios", "Consentimento padrão", "Acessos de suporte"; nenhuma tela de pipeline para o operador fora do suporte. [A26] | Pipeline/Métricas visíveis |
| 5.3 | Aba anônima/limpa: `http://franco.localhost:5173/api/qa/login-as/battoni?to=/` | nada | Recusado (o operador só entra na raiz): erro 422/redireciona ao login. [A26] | Entra no Franco |
| 5.4 | Voltar: "Novo escritório" | Nome `Ipê Contábil`; digitar Endereço `Www` (o campo converte para minúsculas: aparece `www`), depois `-ipe`, `franco`, e por fim `ipe`; clicar "Criar escritório" a cada tentativa | os erros de endereço aparecem ao clicar "Criar escritório", não ao digitar | `www`/`hub` → "Este endereço é reservado pela plataforma. Escolha outro."; `-ipe` → "Use letras minúsculas, números e hífens, começando e terminando com letra ou número."; `franco` → "Já existe um escritório com este endereço."; `ipe` aceito; "O escritório vai responder em ipe.localhost:5173". [A22, A29] | Aceita `www`; erro só aparece sem clicar |
| 5.5 | Mesma tela: Usuário do gestor `gestao.ipe`, E-mail `gestao@ipe.exemplo.com.br`, "Criar escritório" | nada | "Ipê Contábil foi criado em ipe.localhost:5173. O gestor entra lá como gestao.ipe."; painel "Senha" com botão "Copiar" e o aviso "Copie agora: ela não será mostrada de novo. …". Clicar "Copiar" → "Copiada". "Concluir" fecha. [A27, A29] | Senha reaparece ao reabrir |
| 5.6 | Linha "Ipê Contábil" na lista | nada | Só o núcleo nos módulos, com "Briefing do dia" ligado por padrão (default de `firm:create`, por design); link do subdomínio `ipe.localhost:5173`; "0 leads". [A22, A29] | Módulos opcionais ligados |
| 5.7 | Abrir `http://ipe.localhost:5173/` em outra aba (sem login) | nada | Landing do ipe com "Ipê Contábil" nas cores padrão de um escritório novo (neutro, slate; por design, antes de 5.9), sem "Franco", "Reinaldo", "Willy", "APW"; a chamada `GET /api/public/firm` com `X-Firm: ipe` → 200. [A27, A16] | "Escritório não encontrado"; marca do Franco |
| 5.8 | "Visual" na linha | Cor principal `#7fd3ff` | Erro no campo, na hora (validação do lado do cliente, sem pedido à API): "Texto branco não se lê nesta cor (abaixo de 4,5:1). Escolha uma mais escura."; em outras cores com contraste ruim a mensagem é "Texto branco nesta cor fica em N:1 …". O 422 `brand.lowContrast` do servidor se prova no Pest. [A27, A29, C7] | Salva |
| 5.9 | Mesma tela: Cor principal `#6b2a8a`, Cor de destaque `#e8a317`, Nome curto `Ipê`, Slogan `Contabilidade que floresce`, quatro provas (valor `10+`/legenda `anos` e três mais), "Salvar" | valores da tabela | Salva; `http://ipe.localhost:5173/` (recarregar) mostra roxo, o slogan e as quatro provas. [A27, A11] | Cor antiga |
| 5.10 | "Visual" › "Logo": "Escolher imagem" com um PNG de ~300 KB, "Enviar logo" | PNG ≤ 512 KB, ≤ 1024 px | "Prévia da imagem escolhida"; depois "Logo atual"; na landing do ipe e no rail do back office aparece a imagem (nos dois layouts). `Chrome: skip (e2e/platform/view-as.spec.ts)` para a recusa de SVG antes de enviar (A43); a recusa da API (SVG renomeado `.png`, JPEG de 600 KB, PNG de 2000 px) se prova no Pest (A41); aqui só conferir o PNG ok. [A41, A43] | Imagem quebrada |
| 5.11 | Tentar o SVG pelo campo | arquivo `.svg` | "Use uma imagem PNG, JPEG ou WebP — SVG e outros arquivos não são aceitos." (antes de enviar). [A43, A41] | Aceita |
| 5.12 | Linha › "Módulos" | ligar "Radar" sem "Carteira" | "Radar precisa de Carteira ligado." (mensagem do lado do cliente; o 422 `modules.missingDependency` do servidor se prova no Pest); ligar "Carteira" e depois "Radar" → salva. "Canal da rede de contabilidade" aparece em "Só a plataforma". [A19, A29] | Radar liga sem carteira |
| 5.13 | Mesma tela: ligar "Briefing do dia" (já ligado ao nascer) e desligar "Rede de parceiros" | nada | Salva; o ipe fica com `partners` desligado (usado na rodada 6 só para alvorada). [A17] | Perde módulo do núcleo |
| 5.14 | Linha › "Login do gestor" | Usuário `gestao.ipe`, "Gerar senha" | "A senha de gestao.ipe foi redefinida; as sessões abertas foram encerradas." e uma senha nova (uma vez). Digitar um usuário novo livre `gestao.ipe2` mostra "gestao.ipe2 foi criado como login do gestor de Ipê Contábil." [A27] | Senha aparece sem aviso |
| 5.15 | Aba nova, limpar cookies; `http://ipe.localhost:5173/api/qa/login-as/gestao.ipe?to=/gestao/briefing` | nada | Back office do ipe: "Briefing do dia", logo do ipe (a imagem do 5.10), nome da plataforma no cabeçalho; nenhum dado de alvorada ou Franco. [A27, A43] | Dados de outra firma |
| 5.16 | Voltar à raiz, limpar cookies, login `battoni`; `/plataforma/escritorios` | nada | Linha da alvorada › "Acessar como suporte" | Botão ausente |
| 5.17 | Clicar "Acessar como suporte" na linha "Alvorada Contabilidade" | nada | Continua no host da raiz (`localhost:5173`, não vai a `alvorada.localhost`; por design, W91), mostrando o back office da alvorada; faixa fixa no topo "Você está acessando Alvorada Contabilidade como operador da plataforma" com o botão "Sair"; o pipeline lista ≥ 40 leads da alvorada. `Chrome: skip (e2e/platform/view-as.spec.ts)` para o banner e o "Sair" em 1440 (mocked); aqui vale o dado vivo (o quadro lista leads da alvorada). [A42, A43] | Sem faixa; leads do Franco |
| 5.18 | Navegar por três telas (briefing, pipeline, métricas) | nada | A faixa continua visível em todas. [A43] | Faixa some |
| 5.19 | Clicar "Sair" na faixa | nada | Volta para `/plataforma/escritorios`; sem faixa. [A42, A43] | Continua em alvorada |
| 5.20 | Rail › "Acessos de suporte" (`/plataforma/acessos`) | nada | Título "Acessos de suporte"; uma linha de alvorada no formato "<firma> / <operador> / De <ip> / Começou … / Terminou … / Saiu" (o motivo é "Saiu"). [A42, C18] | "Ninguém acessou um escritório como suporte ainda." |
| 5.21 | Entrar de novo em alvorada via "Acessar como suporte", depois, na faixa, abrir o cabeçalho da conta e clicar "Sair" da conta (logout), e então logar de novo como `battoni` | nada | Segunda linha em "Acessos de suporte" com motivo "Saiu da conta"; ao reentrar na raiz, o operador não está mais em alvorada. [A42] | Visão de suporte continua depois do logout |
| 5.22 | Rail › "Consentimento padrão" (`/plataforma/consentimento`) | trocar para `Autorizo {escritorio} a me apresentar a parceiros, quando o diagnóstico indicar.`; "Salvar" | "Consentimento padrão salvo. O próximo escritório criado vai copiá-lo."; sem `{escritorio}`: "A frase precisa nomear o escritório: escreva {escritorio} onde vai o nome." [A28, C15] | Existentes mudam |
| 5.23 | `/plataforma/escritorios` em 390 e 1440 | nada | Sem rolagem lateral. `Chrome: skip (e2e/platform/console.spec.ts)` (390 e 1440, claro/escuro). [A30] | Rolagem lateral |
| 5.24 | `gestao.hub` (limpar cookies): `http://localhost:5173/api/qa/login-as/gestao.hub?to=/plataforma/escritorios` | nada | Recusado / tela sem acesso (gestor não é operador): 403 nos `platform/*`. [A27] | Console abre |

Conferir depois (rodada 5): `POST /api/platform/firms` 201; `PATCH …` com `#7fd3ff` 422 `brand.lowContrast`; `PUT …/modules` 422 `modules.missingDependency` para radar sem carteira; `POST …/view-as` cria linha em `view_as_logs` (reason `sair` depois `logout`); a linha do ipe tem `consent_versions` v1 igual ao padrão de ANTES do passo 5.22 (A28).

---

## Rodada 6 — Varredura de acesso

Dados: `fresh-seed` (alvorada com `partners` ligado no início; o passo 6.7 o desliga e o 6.9 liga de novo). Login: por passo. Cookies limpos entre os logins. Largura: 1440.

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 6.1 | `http://alvorada.localhost:5173/api/qa/login-as/patricia?to=/equipe/pipeline` | nada | Recusado (patricia é do Franco): sem sessão; a tela de login ou erro; `GET /api/auth/me` → 401. [A3] | Entra |
| 6.2 | Em `http://alvorada.localhost:5173/login`, tentar o login normal de `patricia` com a senha dela | `patricia` + senha (= `SEEDER_PWD` do `api.rnc/.env`) | Erro "Usuário ou senha incorretos". `Chrome: skip (Pest: tests/Feature/Auth/LoginFirmTest.php — A3)`: o motorista não tem a senha do seed; um humano faz o passo com a senha do `.env`. [A3] | Entra |
| 6.3 | Login `patricia` em `franco.localhost:5173/login` (mesmo usuário) | `patricia` + senha (= `SEEDER_PWD` do `api.rnc/.env`) | Entra (200). `Chrome: skip (Pest: tests/Feature/Auth/LoginFirmTest.php — A3)` pelo mesmo motivo; um humano faz o passo. [A3] | Recusa |
| 6.4 | Com a sessão da patricia aberta, na aba abrir o console e `fetch('/api/leads', {headers: {'X-Firm': 'alvorada'}})` em `franco.localhost:5173` | nada | Resposta 403 com `firms.mismatch`. [A4] | 200 com leads da alvorada |
| 6.5 | Ainda com a sessão do Franco, abrir `http://alvorada.localhost:5173/equipe/pipeline` | nada | Sessão do Franco não vale em alvorada (cookie por host): tela de login. [A4, A7] | Pipeline de alvorada abre |
| 6.6 | `http://localhost:5173/api/qa/login-as/battoni?to=/plataforma/escritorios`, depois `fetch('/api/leads')` no console | nada | `GET leads` fora do suporte não lê leads de Franco nem de alvorada; o operador vê só os leads da própria plataforma (o pipeline beta, por design, AM1). [A4, A26] | Lista leads de Franco ou alvorada |
| 6.7 | Login `battoni` na raiz › "Visual"/"Módulos" na linha da alvorada: desligar "Rede de parceiros" | nada | Salva. [A17] | Erro |
| 6.8 | Login `helena.alvorada`: `http://alvorada.localhost:5173/api/qa/login-as/helena.alvorada?to=/gestao/parceiros` | nada | A rota mostra a tela "Esta página não existe" (`/gestao/parceiros`); no rail não há "Parceiros", "Extrato da rede" nem "Portal do parceiro"; `GET /api/partners` e `GET /api/ledger` → 404, `GET /api/portal/me` → 403, enquanto `GET /api/leads` → 200. `Chrome: skip (e2e/tenancy/modules.spec.ts)` para a rota e a rail (com API simulada); aqui vale a resposta de 404 da API real. [A17, A24] | Rail com parceiros; 200 em `partners` |
| 6.9 | Login `battoni` e ligar "Rede de parceiros" de novo; recarregar como `helena.alvorada` | nada | `/gestao/parceiros` abre e a rail mostra "Parceiros". [A17] | Segue 404 |
| 6.10 | Login `parceiro.alvorada.1` (host alvorada) e `parceiro.1` (host franco) | `?to=/parceiro` | Cada um vê o portal da própria firma; `parceiro.1` em `alvorada.localhost` é recusado como `patricia` (6.1). [A3] | Portal cruzado |
| 6.11 | Abrir `http://naoexiste.localhost:5173/qualquer-coisa` | nada | Página "Escritório não encontrado" com "Confira o endereço ou volte para a página inicial." e o botão "Ir para a página inicial"; clicar leva a `http://localhost:5173/`. Em 390 e 1280 sem rolagem lateral. `Chrome: skip (e2e/tenancy/firm-host.spec.ts)` para o texto nas duas larguras; clicar o botão vale aqui. [A6, A2] | Tela em branco ou 500 |
| 6.12 | Abrir `http://www.localhost:5173/` | nada | Serve a raiz (a landing do produto): `www` é reservado e cai na raiz, por design; não é firma. [A2] | "Escritório não encontrado"; marca de um escritório |
| 6.13 | No console da raiz, `fetch` POST em `/api/auth/register` | `fetch('/api/auth/register', {method:'POST'})` | 404. [A5] | 200/422 |
| 6.14 | `http://localhost:5173/api/health` | nada | `{"data":{"status":"ok","db":"ok"}}` (embrulhado em `data`); nenhum campo de firma. [A44] | 500; campo de firma |
| 6.15 | Login `gestao.hub` e `equipe.alvorada`: `fetch('/api/platform/firms')` | nada | 403 nos dois. [A27] | 200 |

Conferir depois (rodada 6): `GET /api/public/firm` com `X-Firm: hub` é 404 (a plataforma só responde sem cabeçalho) (AM9).

---

## Rodada 7 — LGPD: privacidade, exportar e anonimizar

Dados: `fresh-seed`; usa leads da consulta "Leads da alvorada". Login: por passo. Larguras: 390 e 1440 em `/privacidade`; 1440 no resto. Esta rodada ANONIMIZA o 2º lead da alvorada; nenhuma outra depende dele.

Campo → onde precisa aparecer:

| Campo | Onde precisa aparecer depois |
| --- | --- |
| Nome, e-mail, telefone, CNPJ, empresa do lead anonimizado | vazios/apagados na ficha e no JSON de exportação |
| Consentimentos do lead | continuam na ficha (prova), com texto e versão |
| Linha da timeline | "Dados anonimizados a pedido" |

| # | Onde clicar | O que digitar / escolher | O que deve aparecer | O que é "errado" |
| --- | --- | --- | --- | --- |
| 7.1 | Aba anônima: `http://alvorada.localhost:5173/privacidade` em 1440 | nada | Selo "LGPD"; "Privacidade e proteção de dados"; lede "Como Alvorada Contabilidade trata os dados de quem usa este site…"; "Quem é o responsável" "Alvorada Contabilidade é a controladora dos seus dados…" com o endereço de alvorada; "Quem opera a plataforma" com o nome da plataforma ("é a operadora …"); "O que guardamos", "Por quanto tempo", "Com quem compartilhamos", "Os seus direitos", "Como fazer um pedido" com o telefone de alvorada. [A47] | "Franco"; endereço de outra firma |
| 7.2 | Mesma página em 390 | nada | Sem rolagem lateral. `Chrome: skip (e2e/site/privacy.spec.ts)`. [A47] | Rolagem lateral |
| 7.3 | Rodapé de `http://alvorada.localhost:5173/` | clicar "Privacidade" | Abre `/privacidade` de alvorada. `Chrome: skip (e2e/site/privacy.spec.ts)`. [A47] | Link ausente |
| 7.4 | `http://localhost:5173/privacidade` (raiz) em 1440 e 390 | nada | "Privacidade e proteção de dados"; texto "… é a controladora dos dados deixados neste site: …" com o NOME DA PLATAFORMA como controladora; NÃO mostra "Quem opera a plataforma". Sem rolagem lateral. [A47, C21] | Bloco de operadora; "Franco" |
| 7.5 | Usar `http://alvorada.localhost:5173/api/qa/login-as/helena.alvorada?to=/equipe/leads/<id do 1º lead>` | `<id>` de um lead da coluna "Reunião marcada" do pipeline (os ids baixos são visitantes anônimos, sem dados a exportar) | Ficha do lead; bloco "Dados do lead (LGPD)" com o texto "Exporte os dados deste lead quando ele pedir uma cópia, ou anonimize-o quando ele pedir para ser apagado." e os botões "Exportar dados" e "Anonimizar". [A48] | Bloco ausente |
| 7.6 | Clicar "Exportar dados" | nada | Baixa o arquivo `lead-<id>-dados.json` (indentado 2 espaços) com os campos e os consentimentos do lead; abrir e CITAR o nome e o e-mail que aparecem. [A48] | Download vazio; só id |
| 7.7 | Abrir a ficha do 2º lead; clicar "Anonimizar" | nada | Pede confirmação com o texto "Anonimizar apaga para sempre o nome, o e-mail, o telefone, o CNPJ e a empresa deste lead. Isto não pode ser desfeito." e os botões "Anonimizar" e "Cancelar". [A48] | Anonimiza sem perguntar |
| 7.8 | Clicar "Cancelar" | nada | Nada muda; dados do lead intactos. [A48] | Apagou |
| 7.9 | "Anonimizar" de novo e confirmar | nada | Toast "Lead anonimizado."; a ficha mostra "Dados anonimizados em <data>" e o texto "Este lead foi anonimizado: quem ele era não está mais guardado. A exportação traz o que restou."; nome, e-mail, telefone, CNPJ e empresa vazios; a linha do tempo "Dados anonimizados a pedido"; "Consentimento" ainda mostra a resposta, versão e data. [A48, C19] | Nome ainda visível; consentimento apagado |
| 7.10 | Recarregar a ficha; no pipeline abrir o cartão do lead | nada | O estado anonimizado persiste; o cartão não mostra pessoa. [A48] | Dados voltam |
| 7.11 | Clicar "Exportar dados" no lead anonimizado | nada | O JSON traz o que restou: nome, e-mail, telefone, CNPJ e empresa vazios/nulos, consentimentos presentes. [A48] | Dados antigos no arquivo |
| 7.12 | Limpar cookies; `http://alvorada.localhost:5173/api/qa/login-as/equipe.alvorada?to=/equipe/leads/<id do 1º lead>` | nada | A ficha abre mas NÃO há "Exportar dados" nem "Anonimizar" (nem o bloco "Dados do lead (LGPD)"); `GET /api/leads/<id>/export` por `fetch` → 403. [A48] | Botões visíveis |
| 7.13 | Em `equipe.alvorada`, `fetch('/api/leads/<id>/anonymise', {method:'POST'})` | nada | 403. [A48] | 200 |
| 7.14 | Anônimo + `reinaldo` (Franco) tentando o id da alvorada: `franco.localhost:5173/api/qa/login-as/reinaldo?to=/equipe/leads/<id da alvorada>` | nada | Ficha não encontrada (404, outro firm). [A48, A1] | A ficha de alvorada abre |

Conferir depois (rodada 7): NÃO VERIFICADO os endpoints exatos de exportar/anonimizar: ler em `api.rnc/routes/api.php` antes de rodar; `leads` do 2º com nome/e-mail/telefone/CNPJ nulos e `anonymised_at` preenchido; `consent_records` do lead ainda existem com texto; a segunda chamada de "Anonimizar" responde igual e não cria linha extra (C19).

---

## Resultados

Database: local SQLite built by make fresh-seed (seeders only)
