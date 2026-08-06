.PHONY: clean/coverage
clean/coverage:
	@rm -rf cov/*
	@rm -rf clover.xml

.PHONY: clean/tests/resources
clean/tests/resources:
	@rm -rf tests/Fixtures/resources/*.pid
	@rm -rf tests/Fixtures/resources/*.txt

.PHONY: clean/fixtures/cache
clean/fixtures/cache:
	@rm -rf tests/Fixtures/Symfony/app/var/cache/*
	@rm -rf tests/Fixtures/Symfony/app/var-*/cache/*

.PHONY: clean/fixtures/logs
clean/fixtures/logs:
	@rm -rf tests/Fixtures/Symfony/app/var/log/*
	@rm -rf tests/Fixtures/Symfony/app/var-*/log/*

# the per-worker var directories and controllers parallel feature tests render
.PHONY: clean/fixtures/workers
clean/fixtures/workers:
	@rm -rf tests/Fixtures/Symfony/app/var-*
	@rm -f tests/Fixtures/Symfony/TestBundle/Controller/ReplacedContentTestController[0-9]*.php

.PHONY: clean
clean: clean/coverage clean/fixtures/cache clean/fixtures/logs clean/fixtures/workers clean/tests/resources
