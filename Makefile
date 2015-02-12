init-dev:
	git clone git@github.com:conde-nast-international/blueprints.git dev/blueprints
deploy:
	git push
	ssh overseer 'cd /opt/cni/overseer; git pull'