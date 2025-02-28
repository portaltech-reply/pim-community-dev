var/tests/%:
	$(DOCKER_COMPOSE) run -u www-data --rm php mkdir -p $@

.PHONY: find-legacy-translations
find-legacy-translations:
	.circleci/find_legacy_translations.sh

.PHONY: coupling-back
coupling-back: structure-coupling-back user-management-coupling-back channel-coupling-back enrichment-coupling-back connectivity-connection-coupling-back communication-channel-coupling-back job-coupling-back data-quality-insights-coupling-back enrichment-product-coupling-back
	$(PHP_RUN) vendor/bin/php-coupling-detector detect --config-file=upgrades/.php_cd.php upgrades/schema

### Static tests
static-back: check-pullup check-sf-services enrichment-product-static-back
	echo "Job done! Nothing more to do here..."

.PHONY: check-pullup
check-pullup:
	${PHP_RUN} bin/check-pullup

.PHONY: check-sf-services
check-sf-services:
	$(PHP_RUN) bin/console lint:container

### Lint tests
.PHONY: lint-back
lint-back:
	$(DOCKER_COMPOSE) run -u www-data --rm php rm -rf var/cache/dev
	APP_ENV=dev $(DOCKER_COMPOSE) run -e APP_DEBUG=1 -u www-data --rm php bin/console cache:warmup
	$(DOCKER_COMPOSE) run -u www-data --rm php php -d memory_limit=1G vendor/bin/phpstan analyse src/Akeneo/Pim --level 2
	$(DOCKER_COMPOSE) run -u www-data --rm php rm -rf var/cache/dev
	${PHP_RUN} vendor/bin/php-cs-fixer fix --diff --dry-run --config=.php_cs.php
	$(MAKE) connectivity-connection-lint-back
	$(MAKE) communication-channel-lint-back
	$(MAKE) data-quality-insights-lint-back
	$(MAKE) data-quality-insights-phpstan
	$(MAKE) job-lint-back
	$(MAKE) enrichment-product-lint-back

.PHONY: lint-front
lint-front:
	$(YARN_RUN) lint
	$(MAKE) connectivity-connection-lint-front

### Unit tests
.PHONY: unit-back
unit-back: var/tests/phpspec
ifeq ($(CI),true)
	$(DOCKER_COMPOSE) run -T -u www-data --rm php php vendor/bin/phpspec run --format=junit > var/tests/phpspec/specs.xml
	.circleci/find_non_executed_phpspec.sh
else
	${PHP_RUN} vendor/bin/phpspec run
endif

.PHONY: unit-front
unit-front:
	$(YARN_RUN) unit
	$(MAKE) connectivity-connection-unit-front

### Acceptance tests
.PHONY: acceptance-back
acceptance-back:
	APP_ENV=behat ${PHP_RUN} vendor/bin/behat -p acceptance --format pim --out var/tests/behat --format progress --out std --colors
	$(MAKE) job-acceptance-back
ifeq ($(CI),true)
	.circleci/run_phpunit.sh . .circleci/find_phpunit.php Akeneo_Measurement_Acceptance
else
	APP_ENV=test $(PHP_RUN) ./vendor/bin/phpunit -c . --testsuite Akeneo_Measurement_Acceptance
endif

.PHONY: acceptance-front
acceptance-front:
	MAX_RANDOM_LATENCY_MS=100 $(YARN_RUN) acceptance run acceptance ./tests/features

### Integration tests
.PHONY: integration-front
integration-front:
	$(YARN_RUN) integration

.PHONY: pim-integration-back
pim-integration-back: var/tests/phpunit connectivity-connection-integration-back communication-channel-integration-back job-integration-back
ifeq ($(CI),true)
	.circleci/run_phpunit.sh . .circleci/find_phpunit.php PIM_Integration_Test
else
	@echo Run integration test locally is too long, please use the target defined for your bounded context (ex: bounded-context-integration-back)
endif

### Migration tests
.PHONY: migration-back
migration-back: var/tests/phpunit
ifeq ($(CI),true)
	.circleci/run_phpunit.sh . .circleci/find_phpunit.php PIM_Migration_Test
else
	APP_ENV=test $(PHP_RUN) ./vendor/bin/phpunit -c . --testsuite PIM_Migration_Test
endif

### End to end tests
.PHONY: end-to-end-back
end-to-end-back: var/tests/phpunit
ifeq ($(CI),true)
	.circleci/run_phpunit.sh . .circleci/find_phpunit.php End_to_End
else
	@echo Run end to end test locally is too long, please use the target defined for your bounded context (ex: bounded-context-end-to-end-back)
endif

end-to-end-front:
	$(DOCKER_COMPOSE) -f docker-compose-cypress.yml run --rm cypress

# How to debug a behat locally?
# -----------------------------
#
# Run the following command:
# make end-to-end-legacy O=my/feature/file.feature:23
#
# Don't forget to pass *O*ption to avoid to run the whole suite.
# Please add dependencies to this target and let it die

.PHONY: end-to-end-legacy
end-to-end-legacy: var/tests/behat
ifeq ($(CI),true)
	.circleci/run_behat.sh $(SUITE)
	.circleci/run_behat.sh critical
else
	${PHP_RUN} vendor/bin/behat -p legacy -s all ${O}
endif

.PHONY: test-database-structure
test-database-structure: #Doc: test database structure
	$(DOCKER_COMPOSE) run -e APP_DEBUG=1 -u www-data --rm php bash -c 'bin/console pimee:database:inspect -f --env=test && bin/console pimee:database:diff --env=test'

