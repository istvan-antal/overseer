#!/bin/bash
cd "$( dirname "${BASH_SOURCE[0]}" )"
cd ..
NODE_VERSION=$(python3 -c "import json; data = json.load(open('package.json')); print(data['engines']['node'])")
NPM_VERSION=$(python3 -c "import json; data = json.load(open('package.json')); print(data['engines']['npm'])")

PLATFORM="linux-x64"

mkdir download
cd download
curl -LO "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-${PLATFORM}.tar.gz"
tar xvf "node-v${NODE_VERSION}-${PLATFORM}.tar.gz"
cd ..
mv download/node-v${NODE_VERSION}-${PLATFORM} runtime