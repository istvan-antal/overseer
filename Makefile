vendor: composer.json composer.lock
	composer install
	touch vendor

init-dev:
	git clone git@github.com:istvan-antal/blueprint.git dev/blueprint

env:
	./build.sh

deploy:
	git push
	ssh overseer 'cd /opt/apps/overseer; git pull'