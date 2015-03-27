#!/usr/bin/env python
from blueprint import Infrastructure
import argparse
import time

timestamp1 = time.time()

parser = argparse.ArgumentParser(description='Launch the application.')
parser.add_argument('--env', metavar='env', type=str, help='Environment to launch in.', default='cni-dev')
parser.add_argument('--name', metavar='name', type=str, help='Application name.', default='overseer-dev')
args = parser.parse_args()

blueprint = Infrastructure(environment=args.env)
instance = blueprint.create_instance(id=args.name)

instance.provision()
instance.setup_generic_php()
instance.install_node()
instance.install_bower()
instance.install_redis()
instance.use_redis_php_sessions();

instance.clone_project('git@github.com:istvan-antal/overseer.git')

instance.use_nginx_config('nginx/ubuntu.conf')

instance.run_script('setup.sh')

timestamp2 = time.time()

print "Launched in: " + time.strftime("%H:%M:%S", time.gmtime(timestamp2 - timestamp1)) + "\n"

print "Instance ready, use the following command to SSH in:"
print instance.get_ssh_command()

print "To install your public key run:"
print instance.get_ssh_command() + " \"echo '$(cat ~/.ssh/id_rsa.pub)' >> ~/.ssh/authorized_keys\""