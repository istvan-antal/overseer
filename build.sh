#!/bin/bash
sudo apt-get install --assume-yes puppet
cd "$(dirname ${BASH_SOURCE[0]})"
sudo puppet apply --verbose --debug --modulepath=./modules main.pp