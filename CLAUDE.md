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
  sweeps, mechanical renames, test files written against a stated contract.
- `opus` — implementers of non-trivial code, ADR and documentation drafters,
  consolidators, critics, estimators.
- the session model — only for work done inline in the main loop, never for a
  subagent.

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
classes/privacy/         null_provider
settings.php             admin settings
yui/src, yui/build       the form-side widget — core's availability UI is YUI,
                         not AMD, so this is the exception to the fleet's
                         "no YUI in new code" rule; rebuild yui/build with the
                         core shifter, never hand-edit it
tests/                   condition, observer and integration tests
```

## Deviations from the fleet standard, recorded so they are not mistaken for a choice

- **`ci.yml` calls the Catalyst reusable workflow**, not the moodle-an-hochschulen
  one the fleet standardises on. That matters beyond consistency: Catalyst gates
  every job behind an eligibility step that requires a pull request or a
  **protected** branch, so a push to an ordinary branch here **runs nothing and
  still reports success** (fleet `CLAUDE.md`, section 4). A green tick on a push
  in this repo is evidence of nothing; verify with
  `gh run view <id> --json jobs --jq '.jobs[].name'`, or locally with
  `mdl ci ... --matrix`.
- The workflow also carries a bare `on: [push, pull_request]`, without the branch
  filter and `concurrency` block the fleet template now ships — every feature-branch
  push pays for the pipeline twice.
