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

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and
says why. This short copy reaches sessions that do not load that file: cloud
sessions and checkouts outside `~/dev`. Every subagent gets the model and effort of
its role, and none runs on the session model (Fable).

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, stale-reference checks | `sonnet` | `medium` | `fleet-sweeper` |
| Readers, verifiers, refuters, graders, measurers | `sonnet` | `high` | `fleet-reader` |
| Well-scoped implementation: a bug whose cause is established, a feature whose design is settled, a task with a written recipe, tests against a stated contract | `sonnet` | `high` | `fleet-fixer` |
| Non-trivial implementation and its fixers: open design, several files, long tasks | `opus` | `xhigh` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `xhigh` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; the tool has no `effort`
  parameter, so the role's effort comes from that definition (`mdl claude-setup`
  installs them). Where they are not installed, pass `model`.
- Set `model` and `effort` on every Workflow `agent()`. Never `xhigh` or `max` on
  Sonnet.
- A subagent that changes code runs the gate its prompt names and reports the
  command with its counts.
- Workflows run only on the user's opt-in, and stay under 10 agents.

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

## MDL Shield reviews

Pull request reviews by MDL Shield are **manual only**. Comment `!mdlshield review` (what is
new since the last review) or `!mdlshield review full` (the whole pull request) on a pull
request; the commands work for the repository owner and the `trusted_users` of
`.mdlshield/config.yml`, and a forced command ignores the filters and a draft's silence.
Nothing runs on its own because `include.branches` is an empty list, which MDL Shield reads
as a rule that matches no branch.

- `.mdlshield/config.yml` and `.mdlshield/context.md` are read **from the default branch
  only** (`main`), so a pull request cannot change the rules that judge it, and the pull
  request that first adds them is judged by the website settings instead. Both are
  `export-ignore`d and never reach the release zip. The file overrides the website for each
  key it names; an invalid file stops reviews for the repository until it is fixed, and an
  unknown key only raises a warning on the summary comment.
- **One Moodle version per repository.** There is no per-branch mapping, so the file pins
  `moodle.versions: ["5.2"]`. The plugin also runs on older cores, so read a finding about a core API with that in mind.
- `fail_on.severity` is `high`: the check fails on an open finding at or above it. Code
  quality findings never block, only security findings do.
- The context file is project knowledge that **influences** the reviewer and is not a
  filter. Keep it in step with the code it states: capabilities, the web service list and
  the facts it calls deliberate. The website's own review context is replaced entirely by
  this file, not merged.
- On the website the repository still needs *Enable pull request reviews* and a granted
  preview access; the file cannot do either.
