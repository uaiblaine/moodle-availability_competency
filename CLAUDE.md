# Claude instructions for `availability_competency`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin.

Plugin context: a Moodle **availability condition** plugin ("Competency") that
restricts access to an activity or a course section until the learner has
reached a given proficiency in one or more competencies. It owns **no database
tables** and implements `null_provider`; state comes from core competencies.
Supports Moodle **4.5 through 5.2** (`$plugin->requires = 2024100700`,
`$plugin->supported = [405, 502]`), mounted at
`availability/condition/competency` (see `~/dev/moodle-dev/plugins.conf`).

## Agent orchestration budget (fleet rule, repeated here on purpose)

This is section 6 of `~/dev/CLAUDE.md`, mirrored into every repo of the fleet.
It is the one fleet rule these files are allowed to duplicate: a session opened
inside a plugin directory does not always carry the fleet file in context, and
the cost of missing this rule is paid immediately, in tokens, before anyone
notices it was missing.

**Every `Agent` call and every `agent()` inside a Workflow sets `model`
explicitly.** An omitted `model` runs that subagent on the session model — the
most expensive one — and is a defect, not a default:

- `sonnet` — readers, graders, refuters, verifiers, measurers, stale-reference
  sweeps, mechanical renames, test files written against a stated contract, and
  **well-scoped implementation**: a bug fix whose cause is already established, an
  iteration on a feature whose design is settled, a repeated task with a written
  recipe (a comment-audit batch). That last group follows Anthropic's guidance for
  Sonnet 5.5. The alias means the **newest Sonnet**: since 2026-10-01 that is
  Claude Sonnet 5.5 (`claude-sonnet-5-5`), measured by asking a subagent launched
  with `model: 'sonnet'` which model it runs on. Claude Code resolves the alias to
  5.5 from version 2.1.284; an older CLI still runs Sonnet 5. Never pin
  `claude-sonnet-5` or any older Sonnet id.
- `opus` — implementers of non-trivial code (the design is still being decided,
  the change spans several files, or the task runs long), ADR and documentation
  drafters, consolidators, critics, estimators. The alias means the **newest
  Opus**: since 2026-09-22 that is Claude Opus 5.5 (`claude-opus-5-5`), measured
  the same way. Never pin `claude-opus-5` or any older Opus id. The `Agent` tool
  accepts aliases only (`sonnet`, `opus`, `haiku`, `fable`); `agent()` in a
  Workflow accepts an explicit id as well, but the alias is what to write — it
  follows the newest model of its tier without an edit here.
- the session model — only for work done inline in the main loop, never for a
  subagent.
- `effort` is set beside `model` on every call, never inherited. An omitted
  effort inherits the session's, and both 5.5 models default to `medium` in
  Claude Code, so neither the session nor the model picks the role's level:
  - `medium` — mechanical sweeps, greps, renames, stale-reference checks.
  - `high` — verifiers, readers, refuters, graders, and Sonnet's well-scoped
    implementers.
  - `xhigh` — Opus implementers and fixers (the owner's rule of 2026-09-17).

  Never `xhigh` or `max` on Sonnet: Anthropic recommends them only where an
  evaluation shows a gain, and a task that needs them is not well scoped — give
  it to `opus`. Sonnet 5.5 recalibrated its effort levels, so a level does not
  buy the thinking it bought on Sonnet 5; judge the output, not the label.
- Every subagent that changes code is told in its prompt which gate to run
  (`mdl phpunit <stack> <component>`, `mdl ci <repo> --only …`) and reports the
  command it ran with its counts. Anthropic recommends exactly this for code
  changes at lower effort; a report without counts is unverified, whatever it
  claims.

Multi-agent workflows stay opt-in and lean whatever mode is on: size the fan-out
to the question (roughly 10 to 25 agents), one refuter per finding and only for
blocking findings, no open-ended "investigate every gap" rounds. Stop and resume
with `resumeFromRunId` rather than relaunching, so completed agents stay cached.
State which model each role got when reporting a launch.

Measured 2026-09-02 on the hub category-context gap analysis: 7 lenses x 2
refuters x 2 measurers plus a critic round, every one of them on the session
model, had to be interrupted for cost — 36 agents with the refuters on Sonnet
produced the same verified result. The rule has been restated three times
(2026-09-01, 2026-09-02, 2026-09-04), the last time over implementers launched
without `model` while the reviewers around them were correctly downgraded.

## Commands

```sh
mdl ci moodle-availability_competency            # one leg (defaults to 5.01)
mdl ci moodle-availability_competency --matrix   # every leg the pipeline runs
mdl phpunit m501 availability_competency         # targeted tests
mdl purge m501                                   # after PHP changes affecting output
```

## Code layout

```
classes/condition.php    the condition itself (is_available, get_description)
classes/frontend.php     feeds the YUI availability form UI with its options
classes/observer.php     reacts to competency events (db/events.php)
classes/task/            ad hoc task running the optional cleanup on deletion
classes/local/           condition_report, behind the CLI report
classes/privacy/         null_provider
cli/report_conditions.php  read-only report of restrictions with no usable competency
settings.php             admin settings
yui/src, yui/build       the form-side widget — core's availability UI is YUI,
                         not AMD, so this is the exception to the fleet's
                         "no YUI in new code" rule; rebuild yui/build with the
                         core shifter, never hand-edit it
tests/                   condition, frontend, observer, Bootstrap class and
                         report (tests/local/) tests, Behat features and the
                         "activity restrictions" Behat generator
```

## Design decisions (owner, 2026-09-23)

- **Linking the competency to the course is the authoring filter, not an evaluation gate.** The
  form offers only linked competencies (a site can hold thousands, and core's course competency
  picker decides which frameworks a course may use). Evaluation never checks the link, the
  framework's context or whether competencies are enabled: a stored rating keeps counting after an
  unlink or a course move, so learners already rated proficient keep access.
- **Two scopes per condition**: `scope = course` reads `competency_usercompcourse`, `scope = global`
  reads `competency_usercomp`. JSON without `scope` is read as `course` (conditions saved before
  1.2.0).
- **A competency that does not exist counts as not proficient**, so a "not proficient" condition on
  it is met.
- **Never call `\core_competency\api` getters from `is_available()`.** They check the current
  user's capabilities and throw `required_capability_exception` / `moodle_exception`, which core's
  `info::is_available()` does not catch (it only catches `coding_exception`), so the whole course
  page breaks for guests, for category-level capability overrides and when competencies are
  disabled. `get_user_competency_in_course()` also inserts a record on read.
- **Credit ssystems-de/moodle-availability_competencies** (README "Credits", CHANGELOG, and a note at
  the implementing code) for anything taken from it, ideas included — the owner's rule.

## Verified correct in classes/observer.php — do not "fix"

Checked against core on 4.5 and 5.2 (2026-09-23):

- The `LIKE '%"competency"%'` prefilter only narrows candidates; removal is decided by
  `type === 'competency'` and the ID, and it does not match other plugins' types (e.g. ssystems'
  `competencies`).
- `rebuild_course_cache($courseid, true)` after writing `availability` is what core's own restore
  step does.
- Core has no API that removes one condition from a stored tree (the removal normally happens in the
  YUI form), so walking the JSON is the only way; `c` and `showc` are rebuilt by appending so that
  they stay parallel and remain JSON arrays.

## Rebuilding the YUI module

`mdl grunt` refuses a plugin without `amd/src`, so run core's grunt directly (eslint at CI
strictness, then shifter), and commit `yui/build` with the source change. This holds for a
comment-only edit too: shifter copies the source comments into the `.js` and `-debug.js` builds,
so the grunt gate fails on a stale build.


```sh
docker run --rm -v ~/dev/moodle-502:/app \
  -v ~/dev/moodle-availability_competency:/app/public/availability/condition/competency \
  -w /app node:22-bookworm bash -lc \
  "npx --no-install grunt yui --root=public/availability/condition/competency --max-lint-warnings=0"
```
