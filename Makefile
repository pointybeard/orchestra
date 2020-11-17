MAKEFLAGS += --warn-undefined-variables
OUT_DIR := build
VERSION := 1.0.0
EOL := "\\r\\n"
SHELL := bash
.SHELLFLAGS := -eu -o pipefail -c
.DEFAULT_GOAL := compile
.DELETE_ON_ERROR:
.SUFFIXES:
.RECIPEPREFIX +=
.PHONY: $(filter-out clean compile install,$(MAKECMDGOALS))

target = /usr/local/sbin

$(OUT_DIR):
  mkdir $@

$(target):
  @echo "Invalid target directory specified! $(target) does not exist."
  @exit 1

help:
  @printf "Orchestra Makefile $(VERSION), compiles and installs Orchestra binary to specified target\n"
  @printf "Usage: make [TARGET] [OPTIONS]$(EOL)$(EOL)"
  @printf "\033[33mTargets:\033[0m$(EOL)"
  @grep -E '^[-a-zA-Z0-9_\.\/]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-15s\033[0m %s\r\n", $$1, $$2}'
  @printf "$(EOL)\033[33mOptions:\033[0m$(EOL)"
  @printf "  \033[32mtarget\033[0m         path that orchestra will be installed to (default is /usr/local/sbin)$(EOL)$(EOL)"
  @printf "\033[33mExamples:\033[0m$(EOL)"
  @printf "  make$(EOL)"
  @printf "  make install target=/usr/sbin$(EOL)"
  @printf "  make clean$(EOL)$(EOL)"
  @printf "\033[33mSupport:\033[0m$(EOL)"
  @printf "If you believe you have found a bug, please report it using the GitHub issue tracker at https://github.com/pointybeard/orchestra/issues, or better yet, fork the library and submit a pull request.\n\n"
  @printf "Copyright 2019-2020 Alannah Kearney. See LICENCE for software licence information.$(EOL)$(EOL)"

compile: app/composer.json $(wildcard app/composer.lock) | $(OUT_DIR) ## <default> builds all reqired packages and compiles the orchestra binary
  composer update -v \
    --no-dev \
    --working-dir="./app/" \
    --no-ansi \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist \
    --no-cache
  php -dphar.readonly=0 -f lib/compile.php
  chmod 0755 "build/orchestra.phar"

install: build/orchestra.phar | $(target) ## moves build/orchestra.phar to target directory
  cp build/orchestra.phar "$(target)/orchestra"
  chmod 0755 "$(target)/orchestra"
  @echo "installation complete!"

clean: ## removes build and app/vendor
  rm -rf $(OUT_DIR) app/vendor
