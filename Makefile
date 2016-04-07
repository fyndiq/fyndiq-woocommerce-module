.PHONY: build test coverage

BASE = $(realpath ./)
SRC_DIR = $(BASE)/src
TESTS_DIR = $(BASE)/tests
BUILD_DIR = $(BASE)/build
COVERAGE_DIR = $(BASE)/coverage
BIN_DIR = $(BASE)/vendor/bin
COMMIT = $(shell git rev-parse --short HEAD)
MODULE_VERSION = $(shell perl -nle 'print $$& if /Version: \K([\d.]+)/' src/woocommerce-fyndiq.php)
WP_VERSION = latest

build: clean
	rsync -a --exclude='.*' $(SRC_DIR) $(BUILD_DIR)
	#cp $(DOCS)/* $(BUILD_DIR)/fyndiqmerchant
	cp LICENSE $(BUILD_DIR)/src
	sed -i'' -e 's/XXXXXX/$(COMMIT)/g' $(BUILD_DIR)/src/classes/FmHelpers.php
	cd $(BUILD_DIR); mkdir build;
	mv -f $(BUILD_DIR)/src $(BUILD_DIR)/build/woocommerce-fyndiq/
	cd $(BUILD_DIR)/build; zip -r -X ../fyndiq-woocommerce-module-v$(MODULE_VERSION)-$(COMMIT).zip .
	rm -rf $(BUILD_DIR)/build

clean:
	rm -rf $(BUILD_DIR)/*

test:
	$(BIN_DIR)/phpunit

test-integration:
	$(BIN_DIR)/phpunit --configuration phpintegration.xml.dist

php-lint:
	find $(SRC_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l

phpmd:
	$(BIN_DIR)/phpmd $(SRC_DIR) --exclude /include text cleancode,codesize,controversial,design,naming,unusedcode

coverage: clear_coverage
	$(BIN_DIR)/phpunit --coverage-html $(COVERAGE_DIR)

clear_coverage:
	rm -rf $(COVERAGE_DIR)

sniff:
	$(BIN_DIR)/phpcs --standard=ruleset.xml --extensions=php --ignore=shared,templates,api --colors $(SRC_DIR)

sniff-fix:
	$(BIN_DIR)/phpcbf --standard=ruleset.xml --extensions=php --ignore=include $(SRC_DIR)
	$(BIN_DIR)/phpcbf --standard=ruleset.xml --extensions=php $(TESTS_DIR)

sniff-fixer:
	php -n $(BIN_DIR)/php-cs-fixer fix --config-file=$(BASE)/.php_cs.php
	php -n $(BIN_DIR)/php-cs-fixer fix $(TESTS_DIR) --level=psr2

compatinfo:
	$(BIN_DIR)/phpcompatinfo analyser:run $(SRC_DIR)
