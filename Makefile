# Local development environment for the ojs-codecheck plugin.
#
# The plugin is developed in a standalone checkout and linked into an OJS
# installation that lives next to it. See README.md, "Local development
# environment", for the full walkthrough.
#
#   make setup     one-time bring-up (deps + link + config + database)
#   make serve     start the dev server
#   make test      run everything that can run locally
#
# Every value below can be overridden on the command line, e.g.
#   make serve PORT=9000

# --- Configuration ----------------------------------------------------------

# OJS installation the plugin is linked into.
OJS_ROOT    ?= $(abspath $(CURDIR)/../ojs-350)
# Version fetched by `make ojs-install`.
OJS_VERSION ?= 3.5.0-5

# Database. The `ojs` account is expected to exist already with rights to
# create databases; see README for the one-off root grant.
DB_NAME ?= ojs_codecheck_350
DB_USER ?= ojs
DB_PASS ?= ojs
# Must be 127.0.0.1, not localhost: mysqli reads "localhost" as a socket path.
DB_HOST ?= 127.0.0.1
DB_PORT ?= 3306

PORT     ?= 8350
BASE_URL ?= http://localhost:$(PORT)

DATASET  ?= $(CURDIR)/testData/stable-3_5_0-codecheck/mysql
PLUGIN_LINK := $(OJS_ROOT)/plugins/generic/codecheck

MYSQL := mysql -u$(DB_USER) -p$(DB_PASS) -h$(DB_HOST) -P$(DB_PORT)

export OJS_ROOT

.PHONY: help setup deps ojs-install ojs-link ojs-config db-create db-load db-reset \
        clear-cache serve test test-component test-e2e test-php screenshots inspect \
        build watch check-ojs clean

# --- Entry points -----------------------------------------------------------

help:
	@echo "ojs-codecheck development targets"
	@echo
	@echo "  Setup"
	@echo "    make setup           one-time bring-up: deps, link, config, database"
	@echo "    make ojs-install     download and unpack OJS $(OJS_VERSION) into $(OJS_ROOT)"
	@echo "    make deps            composer install + npm install + npm run build"
	@echo
	@echo "  Running"
	@echo "    make serve           php -S on port $(PORT)  ($(BASE_URL))"
	@echo "    make build           rebuild the Vue bundle into public/build/"
	@echo "    make watch           rebuild on change"
	@echo
	@echo "  Database"
	@echo "    make db-load         load the test dataset"
	@echo "    make db-reset        drop, recreate, reload"
	@echo
	@echo "  Tests"
	@echo "    make test            component + PHPUnit (everything not needing a server)"
	@echo "    make test-component  Cypress component tests"
	@echo "    make test-php        PHPUnit"
	@echo "    make test-e2e        Cypress e2e (needs 'make serve' running)"
	@echo "    make screenshots     capture UI screenshots (needs 'make serve' running)"
	@echo "    make inspect URL=... ad-hoc page inspection via Playwright"
	@echo
	@echo "  OJS_ROOT = $(OJS_ROOT)"
	@echo "  DB       = $(DB_NAME) as $(DB_USER)@$(DB_HOST):$(DB_PORT)"

setup: deps ojs-link ojs-config db-load
	@echo
	@echo "Setup complete. Start the server with:  make serve"
	@echo "Then open $(BASE_URL)  (admin / admin)"

# --- Dependencies and build -------------------------------------------------

deps:
	composer install --no-interaction
	npm install
	npm run build

build:
	npm run build

watch:
	npm run watch

# --- OJS installation -------------------------------------------------------

ojs-install:
	@if [ -e "$(OJS_ROOT)/index.php" ]; then \
		echo "OJS already installed at $(OJS_ROOT)"; \
	else \
		echo "Downloading OJS $(OJS_VERSION)..."; \
		tmp=$$(mktemp -d) && \
		curl -# -o "$$tmp/ojs.tar.gz" "https://pkp.sfu.ca/ojs/download/ojs-$(OJS_VERSION).tar.gz" && \
		tar xzf "$$tmp/ojs.tar.gz" -C "$$tmp" && \
		mkdir -p "$(dir $(OJS_ROOT))" && \
		mv "$$tmp/ojs-$(OJS_VERSION)" "$(OJS_ROOT)" && \
		rm -rf "$$tmp" && \
		echo "Installed OJS $(OJS_VERSION) at $(OJS_ROOT)"; \
	fi
	@echo "Installing OJS development dependencies (needed for PHPUnit)..."
	@# The tarball is not a git checkout, so the captainhook composer plugin
	@# fails when it tries to install git hooks. The dependency install itself
	@# has already completed at that point, so the failure is tolerated and the
	@# result verified instead.
	-cd "$(OJS_ROOT)/lib/pkp" && composer install --no-interaction --no-progress
	@test -f "$(OJS_ROOT)/lib/pkp/lib/vendor/phpunit/phpunit/phpunit" || { \
		echo "PHPUnit was not installed into $(OJS_ROOT)/lib/pkp/lib/vendor"; \
		exit 1; \
	}
	@echo "OJS ready at $(OJS_ROOT)"

check-ojs:
	@test -f "$(OJS_ROOT)/index.php" || { \
		echo "No OJS installation at $(OJS_ROOT)"; \
		echo "Run 'make ojs-install', or set OJS_ROOT to an existing install."; \
		exit 1; \
	}

# Link this checkout into the OJS plugin directory so OJS loads the working
# copy. PHPUnit resolves __FILE__ through the symlink, which is why
# tests/bootstrap.php honours OJS_ROOT.
ojs-link: check-ojs
	@mkdir -p "$(OJS_ROOT)/plugins/generic"
	@if [ -L "$(PLUGIN_LINK)" ]; then \
		echo "Plugin already linked: $(PLUGIN_LINK) -> $$(readlink $(PLUGIN_LINK))"; \
	elif [ -e "$(PLUGIN_LINK)" ]; then \
		echo "$(PLUGIN_LINK) exists and is not a symlink; refusing to touch it."; \
		exit 1; \
	else \
		ln -s "$(CURDIR)" "$(PLUGIN_LINK)"; \
		echo "Linked $(PLUGIN_LINK) -> $(CURDIR)"; \
	fi

# Point the OJS config at our database, files directory and base URL. The
# dataset ships a config.inc.php with a MAMP files_dir and root credentials,
# so it cannot be used as-is.
ojs-config: check-ojs
	@test -f "$(OJS_ROOT)/config.inc.php" || cp "$(OJS_ROOT)/config.TEMPLATE.inc.php" "$(OJS_ROOT)/config.inc.php"
	@mkdir -p "$(OJS_ROOT)/files" "$(OJS_ROOT)/public"
	@sed -i \
		-e 's|^installed = .*|installed = On|' \
		-e 's|^base_url = .*|base_url = "$(BASE_URL)"|' \
		-e 's|^driver = .*|driver = mysqli|' \
		-e 's|^host = .*|host = $(DB_HOST)|' \
		-e 's|^username = .*|username = $(DB_USER)|' \
		-e 's|^password = .*|password = $(DB_PASS)|' \
		-e 's|^name = .*|name = $(DB_NAME)|' \
		-e 's|^files_dir = .*|files_dir = $(OJS_ROOT)/files|' \
		"$(OJS_ROOT)/config.inc.php"
	@# OJS 3.5 ships an empty app_key and refuses to serve any page without it
	@# (Laravel's encrypter throws during bootstrap, producing a bare HTTP 500).
	@grep -q '^app_key = .\+' "$(OJS_ROOT)/config.inc.php" \
		|| (cd "$(OJS_ROOT)" && php lib/pkp/tools/appKey.php generate >/dev/null && echo "Generated app_key")
	@echo "Configured $(OJS_ROOT)/config.inc.php"

# --- Database ---------------------------------------------------------------

db-create:
	$(MYSQL) -e "CREATE DATABASE IF NOT EXISTS \`$(DB_NAME)\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

db-load: check-ojs db-create
	@echo "Loading dataset into $(DB_NAME)..."
	$(MYSQL) $(DB_NAME) < "$(DATASET)/database.sql"
	@echo "Syncing article files and public assets..."
	@cp -r "$(DATASET)/files/." "$(OJS_ROOT)/files/"
	@cp -r "$(DATASET)/public/." "$(OJS_ROOT)/public/"
	@$(MAKE) --no-print-directory clear-cache

# OJS caches plugin settings through Laravel's file cache, so rows written
# straight into plugin_settings stay invisible until it is dropped.
clear-cache: check-ojs
	@rm -rf "$(OJS_ROOT)/cache/opcache/"* "$(OJS_ROOT)/cache/_db/"* 2>/dev/null || true
	@rm -f "$(OJS_ROOT)/cache/"*.php 2>/dev/null || true
	@echo "Cleared OJS caches"

db-reset:
	$(MYSQL) -e "DROP DATABASE IF EXISTS \`$(DB_NAME)\`;"
	@$(MAKE) --no-print-directory db-load

# --- Running ----------------------------------------------------------------

# PHP's built-in server handles one request at a time by default, which
# deadlocks as soon as a page issues a second request to itself — the Cypress
# suites hang rather than fail. PHP_CLI_SERVER_WORKERS forks several handlers.
SERVER_WORKERS ?= 8

serve: check-ojs
	@echo "OJS at $(BASE_URL)  (admin / admin)"
	@echo "Journal: $(BASE_URL)/index.php/codecheck"
	PHP_CLI_SERVER_WORKERS=$(SERVER_WORKERS) php -S localhost:$(PORT) -t "$(OJS_ROOT)"

# --- Tests ------------------------------------------------------------------

test: test-component test-php

test-component:
	npm run test:component

test-php: check-ojs
	cd tests && sh runTests.sh

test-e2e:
	CYPRESS_BASE_URL=$(BASE_URL) npm run test:e2e

SHOT_WIDTH  ?= 1920
SHOT_HEIGHT ?= 1200

screenshots:
	@# The viewport must come from the environment, not --config: a key already
	@# present in the e2e block of cypress.config.js overrides the command line.
	CYPRESS_BASE_URL=$(BASE_URL) \
	CYPRESS_VIEWPORT_WIDTH=$(SHOT_WIDTH) CYPRESS_VIEWPORT_HEIGHT=$(SHOT_HEIGHT) \
	npx cypress run --e2e --config specPattern='cypress/tests/visual/**/*.cy.js'
	@echo "Screenshots written to cypress/screenshots/"

URL ?= $(BASE_URL)/index.php/codecheck
inspect:
	node dev/inspect.mjs "$(URL)"

clean:
	rm -rf public/build cypress/screenshots cypress/videos tests/results dev/out
