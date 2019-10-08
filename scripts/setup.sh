#!/bin/bash
cd "$( dirname "${BASH_SOURCE[0]}" )"
cd ..
export PATH="$(pwd)/runtime/bin:$PATH"
npm install