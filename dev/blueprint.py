import subprocess
import json
import time
import os

class Infrastructure(object):
    
    def __init__(self, environment = 'cni-dev'):
        self.profile = environment
        self.keyname = environment
    
    def create_instance(self, instancetype = 't2.micro'):
        PEM_FILE = os.path.expanduser('~/.cni-blueprints/' + self.keyname + '.pem')
        def run(*args):
            return json.loads(subprocess.check_output(args))

        disks = [{
            "DeviceName": "/dev/sda1",
            "Ebs": { 
                "DeleteOnTermination": True,
                "VolumeSize": 50,
                "VolumeType": "gp2"
            }
        }]

        instance_info = run(
            'aws', 'ec2', 'run-instances',
            '--image-id', 'ami-f0b11187',
            '--instance-type', instancetype,
            '--block-device-mappings', json.dumps(disks),
            '--security-groups', 'web-server',
            '--key-name', self.keyname,
            '--profile', self.profile
        )

        instance_id = instance_info['Instances'][0]['InstanceId']
        running = False

        while running == False:
            time.sleep(5)
            state = run('aws', 'ec2', 'describe-instance-status', '--instance-id', instance_id, '--profile', self.profile)

            if len(state['InstanceStatuses']) > 0:
                running = state['InstanceStatuses'][0]['InstanceState']['Code'] == 16

        instance_data = run('aws', 'ec2', 'describe-instances', '--instance-ids', instance_id, '--profile', self.profile)

        instance_dns = instance_data['Reservations'][0]['Instances'][0]['PublicDnsName']

        reachable = False

        while not reachable:
            try:
                subprocess.check_output(['ssh', '-i', PEM_FILE, '-o', 'StrictHostKeyChecking no', 'ubuntu@' + instance_dns, 'whoami'])
                reachable = True
            except subprocess.CalledProcessError:
                time.sleep(5)
                reachable = False
                
        return Instance(instance_dns = instance_dns, pem_file = PEM_FILE)
    
    def create_database(self):
        password = 'randompassword123456'
        db_info = run(
            'aws', 'rds', 'create-db-instance',
            '--db-instance-identifier', 'cninow-dev',
            '--allocated-storage', '20',
            '--db-instance-class', 'db.t1.micro',
            '--engine', 'MySQL',
            '--storage-type', 'gp2',
            '--master-username', 'cninow',
            '--master-user-password', password,
            '--profile', 'cni-dev'
        )

class Instance(object):
    
    def __init__(self, instance_dns, pem_file):
        self.instance_dns = instance_dns
        self.pem_file = pem_file
        
    def provision(self):
        RSA_KEY = os.path.expanduser('~/.cni-blueprints/id_rsa')
        self.upload_file(RSA_KEY, '~/.ssh/id_rsa')
        self.upload_file('20auto-upgrades')
        self.run_commands(
            'sudo apt-get update',
            'sudo apt-get install unattended-upgrades',
            'sudo mv 20auto-upgrades /etc/apt/apt.conf.d/20auto-upgrades',
            'sudo service unattended-upgrades restart',
            'sudo mkdir -p /opt/cni; sudo chown ubuntu:ubuntu /opt/cni/',
            'ssh-keyscan github.com >> ~/.ssh/known_hosts'
        )     
    
    def setup_generic_php(self):
        self.run_commands(
            'sudo apt-get update',
            'sudo apt-get install --assume-yes git-core nginx php5-fpm php5-cli php5-curl php5-mysql',
            'curl -sS https://getcomposer.org/installer | php',
            'sudo mv composer.phar /usr/local/bin/composer',
            "sudo sed -i -e 's/post_max_size = [0-9]*M/post_max_size = 50M/g' /etc/php5/fpm/php.ini",
            "sudo sed -i -e 's/upload_max_filesize = [0-9]*M/upload_max_filesize = 50M/g' /etc/php5/fpm/php.ini",
            "sudo sed -i -e 's/max_file_uploads = [0-9]*/max_file_uploads = 50/g' /etc/php5/fpm/php.ini",
            "sudo service php5-fpm restart"
        )
    
    def clone_project(self, repo):
        self.run_command('cd /opt/cni; git clone ' + repo)
        
    def use_nginx_config(self, file):
        self.upload_file(file, '~/default.conf')
        self.run_command('sudo mv ~/default.conf /etc/nginx/sites-available/default')
        self.run_command('sudo service nginx restart')
        
    def upload_file(self, file, destination = None):
        if destination is None:
            destination = '~/' + file
       
        try:
            print subprocess.check_output(['scp', '-i', self.pem_file, file, 'ubuntu@' + self.instance_dns + ':' + destination], stderr=subprocess.STDOUT)
        except subprocess.CalledProcessError as e:
            print 'Error:'
            print e.output
            raise e
        
    def run_commands(self, *commands):
        return self.run_command(";".join(commands))
        
    def run_command(self, command):
        print subprocess.check_output(['ssh', '-i', self.pem_file, 'ubuntu@' + self.instance_dns, command], stderr=subprocess.STDOUT)
       