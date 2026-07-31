moodle-availability_competency
=============================

[![ci](https://github.com/uaiblaine/moodle-availability_competency/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-availability_competency/actions/workflows/ci.yml?query=branch%3Amain)

An availability condition plugin for Moodle that restricts access to activities and course sections based on competency proficiency. Teachers can require students to have achieved a certain level of proficiency in one or more competencies before accessing an activity or section.

If your teachers want to release activities, resources, or sections only to students who have reached specific competencies in a course, this plugin is for you.

Take a look at an example:

- The Moodle site tracks competencies for each training pathway.
- Ana Teacher is an editing teacher in course A.
- Ana would like to publish an advanced assignment only for students who have already demonstrated the competency “Problem Solving—Intermediate.”
- With core Moodle conditions, she cannot directly gate that activity by competency proficiency.
- With availability_competency, Ana can add a competency condition to the activity or section and require a target proficiency level.
- Only students who meet that competency requirement get access.

Important: the competencies used in restrictions must be linked to the course . If a competency is not associated with the course context, it cannot be used effectively by this availability condition.


Settings
--------

To further configure the plugin and its behaviour, please visit: Site administration -> Plugins -> Availability restrictions -> Restriction by competency

There, you find a setting section:

### 1. Clean up restrictions on competency deletion

With this setting, you can control whether the plugin automatically removes all availability restrictions which require a particular competency as soon as this competency is deleted. This prevents the affected activities and sections from staying hidden from everyone due to an orphaned restriction which can never be fulfilled anymore. As this cleanup changes existing availability restrictions in a way which can not be undone, it is disabled by default and has to be enabled explicitly on the plugin settings page. If it is disabled, an orphaned restriction remains in place and is shown as "(Competency missing)" instead.


Requirements
------------

- Moodle 4.5 or later (tested up to Moodle 5.2)
- Core competencies enabled (`tool_lp`)


Installation
------------

Install the plugin like any other plugin to folder `/availability/condition/competency`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.


Plugin repositories
-------------------

This plugin is not yet published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/uaiblaine/moodle-availability_competency


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/uaiblaine/moodle-availability_competency/issues


Feature proposals
-----------------

Please issue feature proposals on Github:
https://github.com/uaiblaine/moodle-availability_competency/issues

Please create pull requests on Github:
https://github.com/uaiblaine/moodle-availability_competency/pulls


Translating this plugin
-----------------------

This Moodle plugin is shipped with English language pack by default. All translations into other languages must be managed through AMOS (https://lang.moodle.org) by which they will become part of Moodle's official language pack.


Privacy
-------

This plugin does not store any persistent personal profile data. It only reads competency framework data and user proficiency information from Moodle's core competency system.


Contributors
------------

- Anderson Blaine


Copyright
---------

The copyright of this plugin is held by Anderson Blaine.
