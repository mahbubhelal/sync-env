.PHONY: up build down destory downall attach logs exec composer php artisan tinker testing reup buildup ua ra ba a

s ?= local
p ?= 80
uid ?= $(shell id -u)
gid ?= $(shell id -g)

up:
	@export PORT=$(p) UID=$(uid) GID=$(gid); docker compose up -d $(s)

build:
	@export UID=$(uid) GID=$(gid); docker compose build $(s)

down:
	@docker compose rm -fvs $(s)

destory:
	@docker compose down --rmi local $(s)

downall:
	@docker compose down

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
	@docker compose exec $(s) php artisan $(c)

tinker:
	@docker compose exec $(s) php artisan tinker

reup: down up

buildup: build up

ua: up attach

ra: reup attach

ba: buildup attach

a: attach
