# Completion search (report_completionsearch)

[![Moodle Plugin CI](https://github.com/daniloalexandre182/moodle-report_completionsearch/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/daniloalexandre182/moodle-report_completionsearch/actions/workflows/ci.yml)

Moodle course report that brings the core **Course completion** (`report_completion`) and
**Activity completion** (`report_progress`) reports into a single page with a user search filter
(full name, email or username) instead of the first/last name initials bars.

*Versão em português abaixo.*

## Requirements

- Moodle 5.2 (branch `MOODLE_502_STABLE`), PHP 8.3 – 8.4.
- Moodle 4.1 (branch `MOODLE_401_STABLE`), PHP 7.4 – 8.1.
- Completion tracking enabled on the site and in the course.
- Core plugins `report_progress` and `report_completion` (their strings and AMD modules are reused).

## Branches

| Moodle version | Branch              |
|----------------|---------------------|
| 4.1            | `MOODLE_401_STABLE` |
| 5.2            | `MOODLE_502_STABLE` |
| Development    | `main`              |

## Installation

Using git, from the Moodle root:

```bash
# Moodle 5.2 (plugins live under public/)
git clone -b MOODLE_502_STABLE https://github.com/daniloalexandre182/moodle-report_completionsearch.git public/report/completionsearch
# Moodle 4.1
git clone -b MOODLE_401_STABLE https://github.com/daniloalexandre182/moodle-report_completionsearch.git report/completionsearch
```

Or download the ZIP and extract it to `public/report/completionsearch` (5.2) or `report/completionsearch` (4.1). Then visit
*Site administration → Notifications* to complete the installation.

## Usage

1. Open a course with completion tracking enabled.
2. Go to *Course → Reports → Completion search* (or `/report/completionsearch/index.php?course=ID`).
3. Choose the field to search in, type the text (partial, case-insensitive) and the report
   (*Course completion* is selected by default), then click *Search*.

The activity completion view keeps the core 5.2 tools: group, activity type, order and section
selectors, and download in any enabled dataformat (CSV, Excel, ODS, JSON, HTML, PDF).

No users are loaded when the page is first opened. Submitting an empty search lists every tracked
user. Email and username searches are only offered to users with `moodle/site:viewuseridentity`.

## Capabilities

- `report/completionsearch:view` – view the report (teacher, editing teacher and manager by
  default; cloned from `report/progress:view`).

## Privacy

The plugin does not store any personal data (privacy null provider).

---

## Português

Relatório de curso para o Moodle 5.2 e 4.1 que reúne os relatórios nativos **Conclusão do curso** e
**Conclusão de atividades** em uma única página, com filtro de usuários por nome completo, e-mail
ou nome de usuário.

### Instalação

Na raiz do Moodle:

```bash
# Moodle 5.2 (plugins live under public/)
git clone -b MOODLE_502_STABLE https://github.com/daniloalexandre182/moodle-report_completionsearch.git public/report/completionsearch
# Moodle 4.1
git clone -b MOODLE_401_STABLE https://github.com/daniloalexandre182/moodle-report_completionsearch.git report/completionsearch
```

Depois acesse *Administração do site → Notificações*.

### Uso

1. Acesse um curso com a conclusão de atividades habilitada.
2. Em *Curso → Relatórios*, selecione **Pesquisa de conclusão**.
3. Escolha o campo (*Nome completo*, *E-mail* ou *Nome de usuário*), digite o texto (busca parcial,
   sem diferenciar maiúsculas/minúsculas) e o relatório (*Conclusão do curso* vem selecionado) e
   clique em **Consultar**.

Ao abrir a página nenhum usuário é carregado; consultar com o texto vazio lista todos os usuários.
As buscas por e-mail e nome de usuário só aparecem para quem tem `moodle/site:viewuseridentity`.

## License

2026 Danilo Machado <danilo.alexandre182@gmail.com>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version. See [LICENSE](LICENSE).
