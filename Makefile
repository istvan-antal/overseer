init-dev:
	git clone git@github.com:conde-nast-international/blueprints.git dev/blueprints
deploy:
	ssh overseer 'cd /opt/cni/overseer; git pull'