build: vendor web/bower_components storage
	./vendor/bin/doctrine migrations:migrate --no-interaction

vendor: composer.lock
	composer install
	touch vendor

composer.lock: composer.json
	composer update

web/bower_components: bower.json
	bower install
	touch web/bower_components

init-dev:
	git clone git@github.com:istvan-antal/blueprint.git dev/blueprint
	
storage:
	mkdir storage
	chmod 0777 storage

env:
	./build.sh

deploy:
	git push
	ssh overseer 'cd /opt/apps/overseer; git pull'