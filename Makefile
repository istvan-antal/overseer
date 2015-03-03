init-dev:
	git clone git@github.com:istvan-antal/blueprint.git dev/blueprints
deploy:
	git push
	ssh overseer 'cd /opt/apps/overseer; git pull'