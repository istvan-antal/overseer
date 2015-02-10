#!/usr/bin/env python
from blueprint import Infrastructure
import argparse

parser = argparse.ArgumentParser(description='Launch the application.')
parser.add_argument('--env', metavar='env', type=str, help='Environment to launch in.', default='cni-dev')
args = parser.parse_args()

blueprint = Infrastructure(environment=args.env)
instance = blueprint.create_instance()

instance.provision()
instance.setup_generic_php()

instance.clone_project('git@github.com:conde-nast-international/overseer')

instance.use_nginx_config('nginx/ubuntu.conf')

instance.upload_file('setup.sh')

instance.run_command('~/setup.sh')