---
outline: deep
---

# Repositories

The Battoni Dev project utilizes GitHub for version control and code collaboration. The project repositories are hosted under the Organization [Battoni Dev](https://github.com/battoni). Developers can access the project's codebase, contribute, and track changes through GitHub.

## Branch Structure

The repository's branch structure is currently organized as follows:

- `main`: the branch in production;
  - `development`: the test branch were the dev should point its PRs to;
    - _`conventionalc/tasks-done`_: this branch should be created from **development**. For every task it is created a new branch. The first word should be a [Conventional Commit](https://www.conventionalcommits.org/en/v1.0.0/) followed by '/' and a short description of the task. After finishing the task, the dev creates a PR pointing to **development** and deletes the branch after merge.

    ::: tip
    Let's say you have a task with id 90. There you going to add a shopping cart button to the topnav. That is a feature. Hence, your branch name will be: **TASK-90-feat/add-shopping-cart-button**
    :::


## Pull Requests

In adherence to good development practices, all pull requests (PRs) within the project must adhere to a standardized template to ensure consistency and completeness in code submissions. Developers are encouraged to follow this template by providing detailed descriptions, testing procedures, and any necessary documentation. Moreover, each PR must designate _at least_ Guilherme Battoni as the reviewer, ensuring oversight from the project owner and maintaining quality control.

Once a PR has been successfully merged, the associated task branch (_`conventionalc/tasks-done`_) should be deleted. Subsequently, it is necessary to synchronize their working branches (_`devname`_) with the latest changes merged into the `development` branch.
