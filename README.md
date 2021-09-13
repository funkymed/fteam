# Feature-Team People Monitoring

A tool to display in cli the current status, progress, and celerity of the team.
## Gitlab
#### Milestone

You will have to add a milestone on every issues.

#### Weight

**If you have a Gitlab Premium** : you can use the weight.    
**If you don't** :  just add in your issue description at the end `Weight : x` replace x by the weight you want, the application will do the rest.   
You're welcome ;)

## Installation

Install the command `composer` and inside cli type the command

```bash
$ composer install
```
### Configuration

Copy the file .env.dist to .env

Edit the credential and configuration for your gitlab 

```bash
###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=63e1e6d0bfd4e3b5527d243a81e0fb4c
###< symfony/framework-bundle ###

###> gitlab ###
GITLAB_TOKEN=xxx
GITLAB_ID=123
GITLAB_PATH=xxx
GITLAB_URL=https://gitlab.com
GITLAB_DEBUG=false
GITLAB_LABELS=my-feature-team
GITLAB_WORKFLOW_BACKLOG=backlog
GITLAB_WORKFLOW_WIP=doing,review
GITLAB_WORKFLOW_BLOCKED=blocked
GITLAB_WORKFLOW_REJECTED=rejected
GITLAB_WORKFLOW_ACCEPTED=to_merged,merged
GITLAB_WORKFLOW_FINISHED=merged
###< gitlab ###
```

### Customize Workflow

- `GITLAB_WORKFLOW_BACKLOG` : the backlog label
- `GITLAB_WORKFLOW_WIP` : the labels in progress, separate with coma (start, in review, ...)
- `GITLAB_WORKFLOW_BLOCKED` : the blocked issues label
- `GITLAB_WORKFLOW_REJECTED` : the rejected issues label
- `GITLAB_WORKFLOW_ACCEPTED` : the labels in progress, separate with coma (merged, accepted, ...)

## How to use 

### Get board and celerity from a milestone

```bash
./bin/console celerity:milestone --milestone="sprint-2021-W21-23"
```

#### Display all the peoples stats from a milestone

```bash
$ ./bin/console celerity:milestone                                                                                  
Select the milestone
  [0 ] sprint-2021-W23-25
  [1 ] sprint-2021-W21-23
  [2 ] sprint-2021-W19-W21
  [3 ] sprint-2021-W17-W20
  [4 ] sprint-2021-W15-W17
  [5 ] sprint-2021-W13-W15
  [6 ] sprint-2021-W11-W13
  [7 ] sprint-2021-W9-W11
  [8 ] sprint-2021-W7-W9
  [9 ] sprint-2021-W5-W7
  [10] sprint-2021-W3-W5
  [11] sprint-2021-W1-W3
  [12] sprint-2020-W53-W55
  [13] sprint-2020-W51-W53
```

#### options

- `--epics`: display the epics of the milestone (optional)
- `--epics_detail`: display the epics with more detail of the milestone (optional)
- `--label` : display issue with the specifique label (optional)
- `--markdown` : display the table in markdown
- `--snapshot` : display a table simplifiy version in markdown of the board

```bash
./bin/console celerity:milestone --milestone="sprint-2021-W21-23"
```

```bash
./bin/console celerity:milestone --milestone="sprint-2021-W21-23" --epics --label="team::back" --label="team::front"
```

```bash
./bin/console celerity:milestone --milestone="sprint-2021-W21-23" --epics --label="team::devops" 
```


### Display all the issues of a milestone by people

```bash
./bin/console celerity:issue --milestone="sprint-2021-W21-23"
```
#### Options :
- `--with-weight` : display issue with weight (optional)
- `--without-weight` : display issue without weight (optional)
- `--not-assigned` : display issue not assigned (optional)
- `--assigned` : display issue assigned (optional)
- `--label` : display issue with the specifique label (optional)
- `--state` : Specify the state (backlog|started|review|staging|blocked|rejected|preprod|closed) (optional)

```bash
$ ./bin/console celerity:issue --milestone="sprint-2021-W21-23" --without-weight --state="closed" --label="team::back" --label="team::front"
```

### Display celerity by user from selected milestones.

```bash
./bin/console celerity:celerity --milestone="sprint-2021-W21-23" --milestone="sprint-2021-W23-25" --milestone="sprint-W25-W27" --label="team::back"
```

#### Options :
- `--milestone` : set the milestones you want to browse for celerity (optional)
- `--label` :  set the labels you want to browse for celerity (optional)

## Using docker 

create a .env file 

```bash
###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=123123123123123123123
###< symfony/framework-bundle ###

###> gitlab ###
GITLAB_TOKEN=xxx
GITLAB_ID=123
GITLAB_PATH=xxx
GITLAB_URL=https://gitlab.com
GITLAB_DEBUG=false
GITLAB_LABELS=in::tech,in::qa,in::product,in::support,team::virality,team::core,team::entreprise
GITLAB_WORKFLOW_BACKLOG=backlog
GITLAB_WORKFLOW_WIP=doing,review
GITLAB_WORKFLOW_BLOCKED=blocked
GITLAB_WORKFLOW_REJECTED=rejected
GITLAB_WORKFLOW_ACCEPTED=to_merged,merged
GITLAB_WORKFLOW_FINISHED=merged
###< gitlab ###
```

And type this command in cli

```bash
docker load < build/fteam.tar.gz
docker run --env-file .env -it fteam:php /app/bin/console celerity:milestone --milestone="sprint-2021-W21-23"
```

Every option above will works perfectly.  

## Compile phar version

### Install box

https://github.com/box-project/box/blob/master/doc/installation.md#installation

### Compile

```
make phar
```

The builded file will be available here `build/fteam.phar`

## Makefile

There is a Makefile in the directory which can be helpfull to simplify the usage of the app.

If type `make` in the directory you will have this result

```bash
> make
This Makefile help you using yout local development environment.
Usage: make <action>
	help:                Display this help dialog
	docker-build:        Build the docker image fteam:php
	docker-save:         Save the builded docker image /build/fteam.tar.gz
	docker-load:         Save the builded docker image /build/fteam.tar.gz
	run-milestone:       Run milestone command
	run-milestone-snapshot: Run milestone command with snapshot parameter
	run-issue:           Run issue command
	run-celerity:        Run celerity command
```