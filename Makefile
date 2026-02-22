.PHONY: help dev-sass dev-sass-watch dev-assets prod-assets prod-cache-clear

PHP := php
CONSOLE := $(PHP) bin/console

help:
	@printf "%s\n" \
	"Targets:" \
	"  make dev-sass        Compile le Sass (dev, une fois)" \
	"  make dev-sass-watch   Compile le Sass en watch (dev)" \
	"  make dev-assets       Compile les assets AssetMapper (optionnel en dev)" \
	"  make prod-cache-clear Clear cache en prod" \
	"  make prod-assets      Compile Sass + AssetMapper en prod"

dev-sass:
	$(CONSOLE) sass:build

dev-sass-watch:
	$(CONSOLE) sass:build --watch

dev-assets:
	$(CONSOLE) asset-map:compile

prod-cache-clear:
	APP_ENV=prod APP_DEBUG=0 $(CONSOLE) cache:clear

prod-assets:
	APP_ENV=prod APP_DEBUG=0 $(CONSOLE) sass:build
	APP_ENV=prod APP_DEBUG=0 $(CONSOLE) asset-map:compile
