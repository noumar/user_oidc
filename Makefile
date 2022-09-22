PODMAN = podman
RUN = $(PODMAN) container run --rm -it
COMPOSER = -v $(PWD):/app composer:latest
NODE = -v $(PWD):/app node:16
PHP_PATHS = lib/Vendor/ vendor/ vendor-bin/mozart/vendor/
JS_PATHS = js/

build: php-build js-build

clean: php-clean js-clean

php-build:
	$(RUN) $(COMPOSER) install --no-dev -o
	chmod -R u=rwX,go=rX $(PHP_PATHS)

php-clean:
	rm -rf $(PHP_PATHS)

js-build: js-clean
	$(RUN) $(NODE) bash -c '\
	npm install -g npm; \
	cp -r /app /app2; \
	cd /app2; \
	npm ci; \
	npm run build; \
	mv /app2/js /app/'
	chmod -R u=rwX,go=rX $(JS_PATHS)

js-clean:
	rm -rf $(JS_PATHS)
