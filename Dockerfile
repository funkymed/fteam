FROM php:7.4.20-zts-alpine3.13

LABEL org.opencontainers.image.authors="cyril.pereira@gmail.com"
LABEL org.opencontainers.image.vendor='Cyril PEREIRA'
LABEL org.opencontainers.image.documentation='https://github.com/funkymed/fteam'

ENV GITLAB_TOKEN xxx
ENV GITLAB_ID xxx
ENV GITLAB_PATH xxx
ENV GITLAB_URL xxx
ENV GITLAB_DEBUG false

WORKDIR /app

COPY . /app

CMD ["/app/bin/console"]