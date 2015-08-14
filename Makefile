build: vendor web/bower_components
	

vendor: composer.json composer.lock
	composer install
	touch vendor

web/bower_components: bower.json
	bower install
	touch web/bower_components

init-dev:
	git clone git@github.com:istvan-antal/blueprint.git dev/blueprint

env:
	./build.sh

deploy:
	git push
	ssh overseer 'cd /opt/apps/overseer; git pull'