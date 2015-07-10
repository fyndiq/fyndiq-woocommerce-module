.PHONY: build test coverage

BASE = $(realpath ./)
SRC_DIR = $(BASE)/src
TESTS_DIR = $(BASE)/tests
BUILD_DIR = $(BASE)/build
COVERAGE_DIR = $(BASE)/coverage
BIN_DIR = $(BASE)/vendor/bin
COMMIT = $(shell git rev-parse --short HEAD)
MODULE_VERSION = $(shell grep -Po "VERSION = '\K[^']*" src/admin/fyndiq/FmUtils.php)

build: clean css
	rsync -a --exclude='.*' $(SRC_DIR) $(BUILD_DIR)
	#cp $(DOCS)/* $(BUILD_DIR)/fyndiqmerchant
	sed -i'' 's/XXXXXX/$(COMMIT)/g' $(BUILD_DIR)/src/admin/fyndiq/FmUtils.php
	cd $(BUILD_DIR); zip -r -X fyndiq-gambio-module-v$(MODULE_VERSION)-$(COMMIT).zip src/
	rm -rf $(BUILD_DIR)/src

clean:
	rm -rf $(BUILD_DIR)/*

dev: css
	cp -svr --remove-destination $(SRC_DIR)/* $(GAMBIO_ROOT)/

css:
	cd $(SRC_DIR)/admin/fyndiq/frontend/css; scss -C --sourcemap=none main.scss:main.css

test:
	$(BIN_DIR)/phpunit

scss-lint:
	scss-lint $(SRC_DIR)/admin/fyndiq/frontend/css/*.scss

php-lint:
	find $(SRC_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l

phpmd:
	$(BIN_DIR)/phpmd $(SRC_DIR) --exclude /shared/,/api/ text cleancode,codesize,controversial,design,naming,unusedcode

coverage: clear_coverage
	$(BIN_DIR)/phpunit --coverage-html $(COVERAGE_DIR)

clear_coverage:
	rm -rf $(COVERAGE_DIR)

sniff:
	$(BIN_DIR)/phpcs --standard=PSR2 --extensions=php --ignore=shared,templates,api --colors $(SRC_DIR)

sniff-fix:
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php --ignore=shared,templates,api $(SRC_DIR)
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php $(TESTS_DIR)

compatinfo:
	$(BIN_DIR)/phpcompatinfo analyser:run $(SRC_DIR)
