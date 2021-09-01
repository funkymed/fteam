.DEFAULT_GOAL := help
.SILENT :

BIYellow = \033[1;93m
GREEN = \033[0;32m
BIGreen = \033[1;92m
NC = \033[0m

## Display this help dialog
help:
	echo "This Makefile help you using yout local development environment."
	echo "Usage: make <action>"
	awk '/^[a-zA-Z\-\_0-9]+:/ { \
		separator = match(lastLine, /^## --/); \
		if (separator) { \
			helpCommand = substr($$1, 0, index($$1, ":")-1); \
			printf "\t${BIYellow}= %s =${NC}\n", helpCommand; \
		} \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			if(helpMessage!="--") { \
				printf "\t${GREEN}%-20s${NC} %s\n", helpCommand, helpMessage; \
			} \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST)
.PHONY: help

## Build the docker image fteam:php
docker-build:
	docker build -t fteam:php .

## Save the builded docker image /build/fteam.tar.gz
docker-save:
	docker save fteam:php | gzip > build/fteam.tar.gz

## Save the builded docker image /build/fteam.tar.gz
docker-load:
	docker load < build/fteam.tar.gz

## Run milestone command
run-milestone:
	docker run --env-file .env -it fteam:php /app/bin/console celerity:milestone   

## Run milestone command with snapshot parameter
run-milestone-snapshot:
	docker run --env-file .env -it fteam:php /app/bin/console celerity:milestone --snapshot

## Run issue command
run-issue:
	docker run --env-file .env -it fteam:php /app/bin/console celerity:issue

## Run celerity command
run-celerity:
	docker run --env-file .env -it fteam:php /app/bin/console celerity:celerity   

## Compile the app in a .phar archive
phar:
	rm -rf ./cache/* ./log/* 
	box compile -vvv