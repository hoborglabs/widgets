
test: deps
	./vendor/bin/phpunit

deps: composer.phar
	./composer.phar install

composer.phar:
	php -r "readfile('https://getcomposer.org/installer');" | php
	chmod +x composer.phar
