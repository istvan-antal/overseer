def runCommand(String command) {
    return (sh(returnStdout: true, script: command)).trim()
}

def APP_NAME = 'overseer';

node('nodejs') {
    dir('build') {
        deleteDir()
        stage('checkout') {
            checkout scm
        }

        def nodeVersion = runCommand("python -c \"import json; data = json.load(open('package.json')); print(data['engines']['node'])\"");
        def npmVersion = runCommand("python -c \"import json; data = json.load(open('package.json')); print(data['engines']['npm'])\"");
        def platform = 'linux-x64'

        stage('runtime') {
            dir('download') {
                sh "curl -LO \"https://nodejs.org/dist/v${nodeVersion}/node-v${nodeVersion}-${platform}.tar.gz\""
                sh "tar xvf \"node-v${nodeVersion}-${platform}.tar.gz\""
            }
            sh "mv download/node-v${nodeVersion}-${platform} runtime"
            dir('download') {
                deleteDir()
            }
        }
    }

    stage('clean') {
        cleanWs()
    }
}