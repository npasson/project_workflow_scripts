# Project Workflow

This collection of files is the product of my work to combine JIRA, Crucible, Gitlab and a web server into an automated workflow. They introduce automated branch creations, tickets that update their status on their own, merge request creation and tracking, and automated deployment on development and live servers.

## Files

This list excludes meta files like README or LICENSE.

```
├── ci - CI files for GitLab.
│   │
│   ├── gitlab-ci.yml - A Gitlab CI/CD pipelines file.
│   │                   Include this in your projects
│   │                   to configure their settings.
│   │
│   └── include       - A different set of CI files that
│                       act as a callable hub for single
│                       projects. Needs to be set up as
│                       a separate project in GitLab.
│
└── rest - Needs to be running on a web server.
    │      These files get called by webhooks, so make sure
    │      they're available whenever your system is in use.
    │
    ├── create_branch
    │   └── index.php - Creates a branch based on the JIRA
    │                   issue name when called by JIRA.
    ├── crucible
    │   └── index.php - Creates a merge request into dev when
    │                   called by Crucible (triggered by commit).
    ├── gitlab-mr
    │   └── index.php - Catches accepted merge requests and
    │                   moves the JIRA ticket accordingly.
    ├── jira_send_live
    │   └── index.php - Merges the issue branch into master
    │                   when the issue is positively closed.
    │
    └── vars.php - The variables for the system to work. Need
                   to be filled out prior to usage.

```
