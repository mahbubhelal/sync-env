.PHONY: up build down attach logs exec composer php artisan tinker reup buildup ua ra ba a version

s ?= local
p ?= 81
uid ?= $(shell id -u)
gid ?= $(shell id -g)

up:
	@export PORT=$(p) HOST_UID=$(uid) HOST_GID=$(gid); docker compose up -d $(s)

build:
	@export HOST_UID=$(uid) HOST_GID=$(gid); docker compose build $(s)

down:
	@docker compose rm -fvs $(s)

attach:
	@docker compose exec $(s) /bin/sh

logs:
	@docker compose logs $(s)

exec:
	@docker compose exec $(s) $(c)

composer:
	@docker compose exec $(s) composer $(c)

php:
	@docker compose exec $(s) php $(if $(c),$(c),--version)

artisan:
	@docker compose exec $(s) ./vendor/bin/testbench $(if $(c),$(c),--version)

tinker:
	@docker compose exec $(s) php ./vendor/bin/testbench tinker

reup: down up

buildup: build up

ua: up attach

ra: reup attach

ba: buildup attach

a: attach

version: artisan php
