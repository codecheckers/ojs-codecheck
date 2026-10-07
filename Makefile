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

# OJS installation the plugin is linked into: beside the main checkout. Found
# through git's common directory rather than $(CURDIR), so that a linked
# worktree (.claude/worktrees/<name>/) resolves the same install as the main
# checkout; outside git (an unpacked package) it falls back to $(CURDIR).
MAIN_CHECKOUT := $(abspath $(or $(dir $(shell git -C "$(CURDIR)" rev-parse --path-format=absolute --git-common-dir 2>/dev/null)),$(CURDIR)))
# The shared development install. Nothing that writes may run against it
# without being asked; see THROWAWAY below for where tests run instead.
SHARED_OJS_ROOT := $(abspath $(MAIN_CHECKOUT)/../ojs-350)

# A throwaway OJS instance: `make throwaway-up THROWAWAY=<name>` builds one
# beside the shared install — the OJS code hard-linked from it (no copy of the
# 3 GB), its own config, cache and files, its own MariaDB in a Docker container
# and its own port — and then every target runs against it when given the same
# THROWAWAY=<name>: `make serve THROWAWAY=doi`, `make test-e2e THROWAWAY=doi`.
# `make throwaway-down THROWAWAY=<name>` removes it. Two at once need their own
# THROWAWAY_PORT and THROWAWAY_DB_PORT.
ifdef THROWAWAY
ifneq ($(shell echo '$(THROWAWAY)' | grep -Ex '[a-z0-9_]+'),$(THROWAWAY))
$(error THROWAWAY must be lower-case letters, digits and underscores)
endif
OJS_ROOT := $(abspath $(MAIN_CHECKOUT)/../ojs-350-$(THROWAWAY))
DB_NAME  := ojs_codecheck_$(THROWAWAY)
DB_PORT  := $(or $(THROWAWAY_DB_PORT),3307)
PORT     := $(or $(THROWAWAY_PORT),8352)
THROWAWAY_CONTAINER := ojs-codecheck-db-$(THROWAWAY)
endif
OJS_ROOT    ?= $(SHARED_OJS_ROOT)
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
# The database, for the e2e task that reopens the review close-review.cy.js
# closes (cypress/plugins/reviewAssignmentTasks.js).
CYPRESS_DB := CYPRESS_DB_HOST=$(DB_HOST) CYPRESS_DB_PORT=$(DB_PORT) CYPRESS_DB_NAME=$(DB_NAME) CYPRESS_DB_USER=$(DB_USER) CYPRESS_DB_PASS=$(DB_PASS)

export OJS_ROOT

.PHONY: help setup deps ojs-install ojs-link ojs-config db-create db-load db-reset demo-db demo-screenshots \
        db-credentials db-credentials-clear tls-cert serve-tls serve-https \
        test-orcid-live \
        clear-cache serve test test-component test-e2e test-e2e-reverse test-e2e-shuffle \
        lint lint-deps lint-fix hooks \
        test-php screenshots inspect social-preview \
        build watch check-ojs clean \
        throwaway-check throwaway-up throwaway-down doi-test-config doi-export \
        check-scheduled-deposit

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
	@echo "    make serve-https     php -S announcing HTTPS, to sit behind serve-tls"
	@echo "    make serve-tls       TLS front-end on $(TLS_PORT), needed for live ORCID tests"
	@echo "    make build           rebuild the Vue bundle into public/build/"
	@echo "    make watch           rebuild on change"
	@echo
	@echo "  Database"
	@echo "    make db-load         load the test dataset"
	@echo "    make db-reset        drop, recreate, reload"
	@echo "    make demo-db         db-reset plus the live demo seed (dev/live-demo.md)"
	@echo "    make demo-screenshots  play the demo and capture every view to dev/out/demo/"
	@echo "    make db-credentials  write the secrets from .env into the database"
	@echo
	@echo "  Tests"
	@echo "    make test            component + PHPUnit (everything not needing a server)"
	@echo "    make test-component  Cypress component tests"
	@echo "    make test-php        PHPUnit"
	@echo "    make test-e2e        Cypress e2e (needs 'make serve' running)"
	@echo "    make test-e2e-reverse  the same specs backwards, to catch order dependence"
	@echo "    make test-e2e-shuffle [SEED=n]  the same specs in a seeded random order"
	@echo "    make screenshots     capture UI screenshots (needs 'make serve' running)"
	@echo "    make inspect URL=... ad-hoc page inspection via Playwright"
	@echo "    make social-preview  render the GitHub social preview to dev/out/"
	@echo
	@echo "  Code style"
	@echo "    make lint            report PHP coding-standard violations (changes nothing)"
	@echo "    make lint-fix        rewrite the files to the coding standard"
	@echo "    make hooks           install the git pre-commit hook that runs the linter"
	@echo
	@echo "  Throwaway instance (anything that writes: e2e, db-reset, DOI exports)"
	@echo "    make throwaway-up THROWAWAY=name    build one: hard-linked OJS, own DB and port"
	@echo "    make <target> THROWAWAY=name        run any target against it, e.g. serve, test-e2e"
	@echo "    make throwaway-down THROWAWAY=name  remove it"
	@echo "    make doi-test-config THROWAWAY=name [AGENCY=datacite] [REGISTERED=1] [CERTIFICATES=\"2 7\"]"
	@echo "                         Crossref/DataCite in test mode and fake DOIs (#19)"
	@echo "    make doi-export THROWAWAY=name ARTICLES=\"5 2\" [DOI_OUT=dir]  Crossref and DataCite XML, validated"
	@echo "    make check-scheduled-deposit THROWAWAY=name  the register deposit when the scheduled task publishes (#188)"
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
	composer install --working-dir=dev/tools --no-interaction
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
#
# A throwaway instance is a test instance, so it runs in OJS's sandbox mode: OJS
# runs no scheduled task or queued job from the web, sends no e-mail and makes
# no DOI deposit, and the plugin reads neither CODECHECK list (#65). The
# plugin's own writes (register issue, register.csv, ORCID) still go out, so
# they stay testable against a testing register and the ORCID sandbox. The
# shared install is left as it is.
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
		-e 's|^;\? *port = [0-9]*$$|port = $(DB_PORT)|' \
		-e 's|^files_dir = .*|files_dir = $(OJS_ROOT)/files|' \
		$(if $(THROWAWAY),-e 's|^sandbox = .*|sandbox = On|') \
		"$(OJS_ROOT)/config.inc.php"
	@# OJS 3.5 ships an empty app_key and refuses to serve any page without it
	@# (Laravel's encrypter throws during bootstrap, producing a bare HTTP 500).
	@grep -q '^app_key = .\+' "$(OJS_ROOT)/config.inc.php" \
		|| (cd "$(OJS_ROOT)" && php lib/pkp/tools/appKey.php generate >/dev/null && echo "Generated app_key")
	$(if $(THROWAWAY),@grep -q '^sandbox = On' "$(OJS_ROOT)/config.inc.php" || { echo "Could not put $(OJS_ROOT) into sandbox mode"; exit 1; })
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
	@$(MAKE) --no-print-directory db-credentials

# OJS caches plugin settings through Laravel's file cache, so rows written
# straight into plugin_settings stay invisible until it is dropped.
clear-cache: check-ojs
	@rm -rf "$(OJS_ROOT)/cache/opcache/"* "$(OJS_ROOT)/cache/_db/"* 2>/dev/null || true
	@rm -f "$(OJS_ROOT)/cache/"*.php 2>/dev/null || true
	@echo "Cleared OJS caches"

# Dropping the database throws away anything that is not in the dataset. The
# GitHub PAT used for live register tests is the usual casualty: it lives in
# plugin_settings, deliberately never in the dump. Confirm, or FORCE=1 to skip
# the prompt in a script.
db-reset:
	@if [ -z "$(FORCE)" ]; then \
		echo "This DROPS the database $(DB_NAME) and reloads it from the test dataset."; \
		echo "Anything not in the dataset is lost, including:"; \
		echo "  - the GitHub PAT in plugin_settings (see dev/live-register-tests.md)"; \
		echo "  - any settings changed through the UI, and every CODECHECK record"; \
		printf "Type 'yes' to continue: "; \
		read answer; \
		[ "$$answer" = "yes" ] || (echo "Cancelled; nothing was dropped." && exit 1); \
	fi
	@$(MAKE) --no-print-directory db-save-token
	$(MYSQL) -e "DROP DATABASE IF EXISTS \`$(DB_NAME)\`;"
	@$(MAKE) --no-print-directory db-load
	@$(MAKE) --no-print-directory db-restore-token

# The live demo: the test dataset plus testData/demo/seed.sql, which adds status
# histories, a draft, a submission waiting for a codechecker and one that did not
# opt in. See dev/live-demo.md. The e2e suites assert on the dataset as it ships,
# so `make db-reset` before running them again.
#
# GITHUB_TOKEN in the environment (not on the make command line, where it would
# be in the process list) stores the register PAT in TOKEN_STASH, as db-reset
# does; without it the one already stashed is used, and without either the
# register step of the walkthrough cannot run. db-reset asks before dropping. Compiled templates are dropped as well as
# the caches, so a demo never shows a form compiled from another worktree.
DEMO_SEED ?= $(CURDIR)/testData/demo/seed.sql

demo-db: check-ojs
	@$(MAKE) --no-print-directory db-reset FORCE=$(FORCE)
	@echo "Applying the demo seed..."
	$(MYSQL) $(DB_NAME) < "$(DEMO_SEED)"
	@# Read from the environment by the shell, never expanded by make, so the
	@# token stays out of every command line. After db-reset, which stashes the
	@# token the old database held.
	@if [ -n "$$GITHUB_TOKEN" ]; then \
		umask 077 && printf '%s' "$$GITHUB_TOKEN" > "$(TOKEN_STASH)"; \
	fi
	@$(MAKE) --no-print-directory db-restore-token >/dev/null
	@rm -rf "$(OJS_ROOT)/cache/t_compile/"* "$(OJS_ROOT)/cache/t_cache/"* 2>/dev/null || true
	@$(MAKE) --no-print-directory clear-cache
	@token=$$($(MYSQL) -N -B $(DB_NAME) -e "SELECT setting_value FROM plugin_settings WHERE plugin_name='codecheckplugin' AND context_id=$(CONTEXT_ID) AND setting_name='githubPersonalAccessToken';"); \
	[ -n "$$token" ] || \
		echo "[Warning] No GitHub PAT is set: the register step will fail. See dev/live-demo.md."
	@echo "Demo ready: $(BASE_URL)/index.php/codecheck — walkthrough in dev/live-demo.md"

# Play the demo in a browser and capture every view for backup slides. It
# submits and records statuses, so load the demo afresh before and after.
# REGISTER=1 also reserves an identifier in the testing register.
demo-screenshots:
	node dev/demo-screenshots.mjs --base $(BASE_URL) $(if $(REGISTER),--register)

# Keep the live-test PAT across a reset rather than making someone paste it
# again. It is written to a file outside the repository, readable only by its
# owner, and put back after the dataset is loaded.
TOKEN_STASH ?= $(HOME)/.codecheck-ojs-pat

db-save-token:
	@token=$$($(MYSQL) -N -B $(DB_NAME) -e "SELECT setting_value FROM plugin_settings WHERE plugin_name='codecheckplugin' AND setting_name='githubPersonalAccessToken';" 2>/dev/null); \
	if [ -n "$$token" ]; then \
		umask 077 && printf '%s' "$$token" > "$(TOKEN_STASH)"; \
		echo "Kept the GitHub PAT in $(TOKEN_STASH) to put back after the reload."; \
	fi

# The dataset has no row for the token, so it is inserted, not updated: an
# UPDATE matched nothing and the token was silently never restored. The SQL goes
# in on stdin, keeping the token out of the process list, and a token is only a
# word, so anything else is refused rather than quoted into SQL. $(MAKE) stays
# off the line that writes: make runs such a line even under `make -n`.
db-restore-token:
	@if [ -s "$(TOKEN_STASH)" ]; then \
		token=$$(cat "$(TOKEN_STASH)"); \
		case "$$token" in *[!A-Za-z0-9_]*) \
			echo "[Error] $(TOKEN_STASH) does not hold a GitHub token; nothing restored."; exit 1;; \
		esac; \
		printf "INSERT INTO plugin_settings (plugin_name, context_id, setting_name, setting_value, setting_type) VALUES ('codecheckplugin', %s, 'githubPersonalAccessToken', '%s', 'string') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);\n" \
			"$(CONTEXT_ID)" "$$token" | $(MYSQL) $(DB_NAME); \
		echo "Restored the GitHub PAT from $(TOKEN_STASH)."; \
	fi
	@$(MAKE) --no-print-directory clear-cache >/dev/null

# --- Credentials ------------------------------------------------------------

# Secrets live in plugin_settings, never in the dataset dump, so a reset drops
# them. `.env` is the durable copy: gitignored, mode 600, read only by make.
# This target writes what it holds into the database, and db-load calls it, so
# the database can be dropped and rebuilt at any time without re-typing a
# secret. See dev/live-orcid-tests.md.
ENV_FILE ?= .env
CONTEXT_ID ?= 1

# .env is read by phpdotenv and written with prepared statements, both in
# dev/db-credentials.php — see the comment there for why neither is done in
# make, and for why phpdotenv is taken from the OJS install rather than from the
# plugin's vendor/. OJS_ROOT is passed for that reason.
db-credentials: check-ojs
	@if [ ! -f "$(ENV_FILE)" ]; then \
		echo "No $(ENV_FILE); nothing to apply."; \
		exit 0; \
	fi
	@php dev/db-credentials.php apply "$(DB_NAME)" "$(DB_USER)" "$(DB_PASS)" "$(DB_HOST)" "$(DB_PORT)" "$(CONTEXT_ID)" "$(OJS_ROOT)"
	@$(MAKE) --no-print-directory clear-cache >/dev/null

# Take the credentials back out, leaving the journal as the dataset ships it.
db-credentials-clear: check-ojs
	@php dev/db-credentials.php clear "$(DB_NAME)" "$(DB_USER)" "$(DB_PASS)" "$(DB_HOST)" "$(DB_PORT)" "$(CONTEXT_ID)" "$(OJS_ROOT)"
	@$(MAKE) --no-print-directory clear-cache >/dev/null

# --- Running ----------------------------------------------------------------

# PHP's built-in server handles one request at a time by default, which
# deadlocks as soon as a page issues a second request to itself — the Cypress
# suites hang rather than fail. PHP_CLI_SERVER_WORKERS forks several handlers.
SERVER_WORKERS ?= 8

# ORCID accepts only https:// redirect URIs, including on the sandbox, so a
# live ORCID round trip cannot run against `make serve` alone — php -S speaks
# no TLS. This puts a TLS front-end in front of it with socat and a self-signed
# certificate, which is enough for a browser that is told to trust it once.
#
# OJS's base_url must be the https origin too, or the redirect URI the plugin
# builds will not be the one registered with ORCID:
#
#   make ojs-config BASE_URL=https://$(TLS_HOST):$(TLS_PORT)
#   make serve                     # in one terminal
#   make serve-tls                 # in another
#
# See dev/live-orcid-tests.md.
TLS_PORT ?= 8443
TLS_DIR  ?= dev/tls

# ORCID's registration form refuses `localhost` as a redirect URI host, whatever
# the scheme, so a live run needs a name that looks like a domain and resolves
# to the loopback address. `*.lvh.me` does that with no hosts file and no DNS of
# your own; a subdomain of a domain you control works too, via /etc/hosts.
TLS_HOST ?= codecheck.lvh.me

$(TLS_DIR)/$(TLS_HOST).pem:
	@mkdir -p "$(TLS_DIR)"
	@openssl req -x509 -newkey rsa:2048 -nodes -days 825 \
		-keyout "$(TLS_DIR)/$(TLS_HOST).key" -out "$(TLS_DIR)/$(TLS_HOST).crt" \
		-subj "/CN=$(TLS_HOST)" \
		-addext "subjectAltName=DNS:$(TLS_HOST),DNS:localhost,IP:127.0.0.1" 2>/dev/null
	@cat "$(TLS_DIR)/$(TLS_HOST).crt" "$(TLS_DIR)/$(TLS_HOST).key" > "$@"
	@chmod 600 "$(TLS_DIR)"/*
	@echo "Generated a self-signed certificate for $(TLS_HOST) (valid 825 days)."

tls-cert: $(TLS_DIR)/$(TLS_HOST).pem

# php -S with the HTTPS router, for use behind `make serve-tls`. OJS decides
# http vs https from $_SERVER['HTTPS'] alone — it does not read
# X-Forwarded-Proto, and base_url is only consulted when host auto-detection
# fails — so the server has to declare it. See dev/https-router.php.
serve-https: check-ojs
	@echo "OJS at https://$(TLS_HOST):$(TLS_PORT) once 'make serve-tls' is running"
	PHP_CLI_SERVER_WORKERS=$(SERVER_WORKERS) php -S localhost:$(PORT) \
		-t "$(OJS_ROOT)" dev/https-router.php

serve-tls: $(TLS_DIR)/$(TLS_HOST).pem
	@command -v socat >/dev/null || { echo "socat is not installed."; exit 1; }
	@echo "TLS front-end: https://$(TLS_HOST):$(TLS_PORT) -> http://localhost:$(PORT)"
	@echo "The certificate is self-signed; the browser will ask once."
	socat OPENSSL-LISTEN:$(TLS_PORT),reuseaddr,fork,cert=$(TLS_DIR)/$(TLS_HOST).pem,verify=0 \
		TCP4:127.0.0.1:$(PORT)

serve: check-ojs
	@echo "OJS at $(BASE_URL)  (admin / admin)"
	@echo "Journal: $(BASE_URL)/index.php/codecheck"
	PHP_CLI_SERVER_WORKERS=$(SERVER_WORKERS) php -S localhost:$(PORT) -t "$(OJS_ROOT)"

# --- Code style -------------------------------------------------------------

# PHP coding standard: PSR-12 plus PKP's own additions, in
# .php-cs-fixer.dist.php. `lint` reports and exits non-zero (what CI and the
# pre-commit hook run); `lint-fix` rewrites the files.
#
# The tool lives in dev/tools/, with its own composer.json, and deliberately
# NOT in the plugin's require-dev: the plugin's vendor/autoload.php is loaded at
# file scope by three runtime classes and registers itself *prepended*, so
# anything installed there outranks OJS's own copy of the same library for every
# request. php-cs-fixer drags in a third of Symfony, which would then be the
# version OJS runs against, tested by nothing. A formatter has no business in
# the runtime autoloader.
PHP_CS_FIXER = dev/tools/vendor/bin/php-cs-fixer

lint-deps:
	@test -x $(PHP_CS_FIXER) || composer install --working-dir=dev/tools --no-interaction

lint: lint-deps
	$(PHP_CS_FIXER) fix --dry-run --diff --show-progress=none

lint-fix: lint-deps
	$(PHP_CS_FIXER) fix --show-progress=none

# Git hooks live outside the repository, so they have to be installed per
# checkout. dev/hooks/pre-commit lints the staged PHP files only.
#
# The guard is `git rev-parse`, not `test -d .git`: in a git worktree — the
# arrangement CLAUDE.md describes for working on this repo — `.git` is a file.
hooks:
	@git rev-parse --git-dir >/dev/null 2>&1 || { echo "[Error] not a git checkout."; exit 1; }
	@target="$$(git rev-parse --git-path hooks)/pre-commit"; \
	if [ -e "$$target" ] && ! cmp -s dev/hooks/pre-commit "$$target"; then \
		cp "$$target" "$$target.bak"; \
		echo "An existing pre-commit hook was moved to $$target.bak"; \
	fi; \
	install -m 755 dev/hooks/pre-commit "$$target"
	@echo "Installed the pre-commit hook. Skip it for one commit with: git commit --no-verify"

# --- Tests ------------------------------------------------------------------

test: test-component test-php

test-component:
	npm run test:component

test-php: check-ojs
	cd tests && sh runTests.sh

test-e2e:
	$(CYPRESS_DB) CYPRESS_BASE_URL=$(BASE_URL) npm run test:e2e

# The same specs, in the opposite order.
#
# Several specs share submission fixtures — 8 and 9 are written by three of them
# — and each is supposed to restore what it changed. Running the suite backwards
# is what checks that: it inverts every dependency between them, so a spec that
# secretly relies on what an earlier one left behind fails here and nowhere else.
#
# Deliberately a separate target rather than randomising the order of the normal
# run: a suite whose order changes every time turns a coupling bug into a flake
# nobody can reproduce, which is the problem this is meant to prevent, not cause.
test-e2e-reverse:
	$(CYPRESS_DB) CYPRESS_BASE_URL=$(BASE_URL) npx cypress run --e2e \
	  --spec "$$(ls -r cypress/tests/e2e/*.cy.js | tr '\n' ',' | sed 's/,$$//')"

# The same specs in a random order — but a *seeded* one, printed before the run
# and accepted back as SEED=..., so an order that finds a coupling bug can be
# replayed. That is the difference between this and randomising the normal run:
# the normal run stays deterministic, and a failure here comes with a repro.
#
#   make test-e2e-shuffle            # a fresh seed, printed
#   make test-e2e-shuffle SEED=42    # exactly the order seed 42 produced
SEED ?=

test-e2e-shuffle:
	@seed="$(SEED)"; \
	if [ -z "$$seed" ]; then seed=$$(date +%s); fi; \
	specs="$$(node dev/shuffle-specs.mjs $$seed)" || exit 1; \
	echo "Replay this order with: make test-e2e-shuffle SEED=$$seed"; \
	$(CYPRESS_DB) CYPRESS_BASE_URL=$(BASE_URL) npx cypress run --e2e --spec "$$specs"

# Live tests write to the real CODECHECK register on GitHub and leave issues
# behind, so they are not in any suite: specPattern excludes cypress/tests/live/
# and this target opts in explicitly. Point it at a TESTING register, never the
# real one, and see dev/live-register-tests.md before running it.
#
#   make test-live GITHUB_TOKEN=ghp_xxx
#
# LIVE_SUBMISSION picks which submission the register issue will name.
LIVE_SUBMISSION ?= 8
REGISTER_ORG    ?= codecheckers
REGISTER_REPO   ?= testing-dev-register

test-live:
	@test -n "$(GITHUB_TOKEN)" || (echo "[Error] GITHUB_TOKEN=... is required: the test reads the register back through the GitHub API." && exit 1)
	@echo "Live test against $(REGISTER_ORG)/$(REGISTER_REPO), submission $(LIVE_SUBMISSION) — this creates a real issue."
	CYPRESS_BASE_URL=$(BASE_URL) \
	CYPRESS_live=1 \
	CYPRESS_githubToken=$(GITHUB_TOKEN) \
	CYPRESS_liveSubmissionId=$(LIVE_SUBMISSION) \
	CYPRESS_registerOrganization=$(REGISTER_ORG) \
	CYPRESS_registerRepository=$(REGISTER_REPO) \
	npx cypress run --e2e --config specPattern='cypress/tests/live/**/*.cy.js'

# A LIVE ORCID test: talks to the real ORCID sandbox and writes a peer-review
# item to a real sandbox record, which nothing here deletes. Outside
# specPattern, so it only ever runs on purpose. See dev/live-orcid-tests.md.
#
# Credentials and the sandbox user come from .env via dev/env-value.php, so
# nothing secret is typed on the command line or kept in shell history.
#
# The base URL is NOT localhost: ORCID's registration form refuses `localhost`
# as a redirect URI host, and OJS builds the redirect URI from the request's
# Host header — so the test must reach OJS by the same name that is registered.
LIVE_ORCID_SUBMISSION ?= 9
LIVE_ORCID_BASE_URL   ?= http://codecheck.lvh.me:$(PORT)

test-orcid-live:
	@test -f "$(ENV_FILE)" || (echo "[Error] no $(ENV_FILE); see dev/live-orcid-tests.md" && exit 1)
	@test -n "$$(php dev/env-value.php ORCID_CLIENT_ID)" \
		|| (echo "[Error] ORCID_CLIENT_ID is blank in $(ENV_FILE)" && exit 1)
	@echo "Live ORCID test against the sandbox, submission $(LIVE_ORCID_SUBMISSION)."
	@echo "This writes to a real sandbox ORCID record and leaves it there."
	@echo "Base URL: $(LIVE_ORCID_BASE_URL)"
	@$(MAKE) --no-print-directory db-credentials
	CYPRESS_BASE_URL=$(LIVE_ORCID_BASE_URL) \
	CYPRESS_live=1 \
	CYPRESS_liveSubmissionId=$(LIVE_ORCID_SUBMISSION) \
	CYPRESS_orcidUserEmail="$$(php dev/env-value.php ORCID_TEST_USER_EMAIL)" \
	CYPRESS_orcidUserPassword="$$(php dev/env-value.php ORCID_TEST_USER_PASSWORD)" \
	CYPRESS_orcidUserId="$$(php dev/env-value.php ORCID_TEST_USER_ID)" \
	npx cypress run --e2e --config specPattern='cypress/tests/live/orcid-deposit.cy.js'

SHOT_WIDTH  ?= 1920
SHOT_HEIGHT ?= 1200
# Its own directory: cypress empties screenshotsFolder before every run, so
# sharing one with the e2e suite means whichever runs second wipes the other.
SHOT_DIR    ?= cypress/ui-screenshots

screenshots:
	@# These must come from the environment, not --config: a key already present
	@# in the e2e block of cypress.config.js overrides the command line.
	CYPRESS_BASE_URL=$(BASE_URL) \
	CYPRESS_VIEWPORT_WIDTH=$(SHOT_WIDTH) CYPRESS_VIEWPORT_HEIGHT=$(SHOT_HEIGHT) \
	CYPRESS_SCREENSHOTS_FOLDER=$(SHOT_DIR) \
	npx cypress run --e2e --config specPattern='cypress/tests/visual/**/*.cy.js'
	@echo "Screenshots written to $(SHOT_DIR)/"

# --- Throwaway instance -------------------------------------------------------

THROWAWAY_IMAGE ?= mariadb:10.6

throwaway-check:
	@test -n "$(THROWAWAY)" || { echo "Name the instance: THROWAWAY=<name>"; exit 1; }

throwaway-up: throwaway-check
	@test -f "$(SHARED_OJS_ROOT)/index.php" || { echo "No shared OJS at $(SHARED_OJS_ROOT) to copy from"; exit 1; }
	@if [ -e "$(OJS_ROOT)" ]; then \
		echo "$(OJS_ROOT) exists; keeping it"; \
	else \
		echo "Linking OJS into $(OJS_ROOT)..."; \
		cp -al "$(SHARED_OJS_ROOT)" "$(OJS_ROOT)"; \
		rm -rf "$(OJS_ROOT)/cache" "$(OJS_ROOT)/files" "$(OJS_ROOT)/public" "$(OJS_ROOT)/config.inc.php"; \
		mkdir -p "$(OJS_ROOT)/cache/t_compile" "$(OJS_ROOT)/cache/t_cache" "$(OJS_ROOT)/cache/opcache" "$(OJS_ROOT)/cache/_db"; \
		cp "$(SHARED_OJS_ROOT)/config.inc.php" "$(OJS_ROOT)/config.inc.php"; \
	fi
	@# The code is hard-linked, so nothing here may write into a file in place:
	@# the config is a copy, and sed -i replaces files rather than editing them.
	@if docker inspect "$(THROWAWAY_CONTAINER)" >/dev/null 2>&1; then \
		docker start "$(THROWAWAY_CONTAINER)" >/dev/null; \
	else \
		docker run -d --name "$(THROWAWAY_CONTAINER)" \
			-e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=$(DB_NAME) \
			-e MARIADB_USER=$(DB_USER) -e MARIADB_PASSWORD=$(DB_PASS) \
			-p 127.0.0.1:$(DB_PORT):3306 $(THROWAWAY_IMAGE) >/dev/null; \
	fi
	@printf "Waiting for MariaDB on port $(DB_PORT)"; \
		for i in $$(seq 60); do $(MYSQL) -e 'SELECT 1' $(DB_NAME) >/dev/null 2>&1 && break; printf .; sleep 1; done; echo; \
		$(MYSQL) -e 'SELECT 1' $(DB_NAME) >/dev/null
	@$(MAKE) --no-print-directory ojs-config ojs-link THROWAWAY=$(THROWAWAY)
	@# Like the directory, an existing database is kept: rerunning this after a
	@# reboot brings the instance back as it was. `make db-reset` reloads it.
	@if [ "$$($(MYSQL) -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$(DB_NAME)'")" = 0 ]; then \
		$(MAKE) --no-print-directory db-load THROWAWAY=$(THROWAWAY); \
	else \
		echo "$(DB_NAME) has tables; keeping it (make db-reset THROWAWAY=$(THROWAWAY) reloads the dataset)"; \
	fi
	@# The dataset ships the journal not publicly enabled, which sends every
	@# visitor who is not logged in to the login page; a throwaway shows readers.
	$(MYSQL) $(DB_NAME) -e "UPDATE journals SET enabled = 1 WHERE path = 'codecheck'"
	@$(MAKE) --no-print-directory clear-cache THROWAWAY=$(THROWAWAY)
	@echo "Throwaway instance '$(THROWAWAY)' ready: make serve THROWAWAY=$(THROWAWAY)  ->  $(BASE_URL)"

throwaway-down: throwaway-check
	-docker rm -f "$(THROWAWAY_CONTAINER)"
	@case "$(OJS_ROOT)" in "$(SHARED_OJS_ROOT)") echo "Refusing to remove the shared install"; exit 1;; esac
	rm -rf "$(OJS_ROOT)"

# Crossref and DataCite in test mode with fake credentials, and a fake DOI for
# every published article (dev/doi-test-config.php). It writes, so it refuses
# the shared install unless FORCE=1.
AGENCY ?= crossref
REGISTERED ?=
CERTIFICATES ?=
doi-test-config: check-ojs
	@if [ "$(OJS_ROOT)" = "$(SHARED_OJS_ROOT)" ] && [ -z "$(FORCE)" ]; then \
		echo "This writes DOI settings into the shared instance; use THROWAWAY=<name>, or FORCE=1"; exit 1; \
	fi
	php dev/doi-test-config.php codecheck $(AGENCY) "$(REGISTERED)" $(CERTIFICATES)
	@$(MAKE) --no-print-directory clear-cache

# The register deposit when the scheduled task publishes (#188): schedules an
# opted-in article for yesterday, runs PublishSubmissions as the scheduler
# does and checks the deposit ran (dev/check-scheduled-deposit.php). Makes no
# call to GitHub. It writes, so it refuses the shared install unless FORCE=1.
SCHEDULED_ARTICLE ?= 5
check-scheduled-deposit: check-ojs
	@if [ "$(OJS_ROOT)" = "$(SHARED_OJS_ROOT)" ] && [ -z "$(FORCE)" ]; then \
		echo "This unpublishes and republishes an article; use THROWAWAY=<name>, or FORCE=1"; exit 1; \
	fi
	php dev/check-scheduled-deposit.php codecheck $(SCHEDULED_ARTICLE)

# The Crossref and DataCite records OJS would deposit for ARTICLES, built and
# validated by OJS's own exporters, into DOI_OUT (dev/doi-export.php). Nothing
# is deposited. Booted as a command-line tool, OJS loads every generic plugin
# with no journal — as a deposit worker does — so this is also the path the
# CODECHECK links must survive.
ARTICLES ?= 5
DOI_OUT  ?= dev/out/doi
doi-export: check-ojs
	@mkdir -p "$(DOI_OUT)"
	php dev/doi-export.php codecheck "$(DOI_OUT)" $(ARTICLES)

URL ?= $(BASE_URL)/index.php/codecheck
inspect:
	node dev/inspect.mjs "$(URL)"

# The repository's social preview image (#41). Static page, no OJS needed.
social-preview:
	node dev/social-preview/render.mjs

clean:
	rm -rf public/build cypress/screenshots cypress/ui-screenshots cypress/videos tests/results dev/out
