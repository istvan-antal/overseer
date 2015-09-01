node default {
    $maxUploadSize = 50
    $maxUploadedFileSize = $maxUploadSize
    $maxFileUploads = 50

    package { ["npm", "nodejs-legacy"]: ensure => "installed" }

    exec { "install_bower":
        require => Package["npm"],
        command => "/bin/bash -c 'npm install -g bower'",
        creates => "/usr/local/bin/bower",
        logoutput => true,
        timeout => 1800
    }

    exec { "install_composer":
        require => Package["php5-cli"],
        command => "/bin/bash -c 'curl -sS https://getcomposer.org/installer | php; mv composer.phar /usr/local/bin/composer'",
        creates => "/usr/local/bin/composer",
        logoutput => true,
        timeout => 1800
    }

    package { ["php5-fpm", "php5-cli", "php5-curl", "php5-sqlite", "php5-redis"]: ensure => "installed" }

    service { "php5-fpm":
        ensure => running,
        enable => true
    }

    file { "/etc/php5/fpm/pool.d/www.conf":
        require => Package["php5-fpm"],
        source  => "puppet:///modules/main/www.conf",
        notify  => Service["php5-fpm"]
    }

    file { "/etc/php5/fpm/php.ini":
        require => Package["php5-fpm"],
        content  => template('main/php.ini.erb'),
        notify  => Service["php5-fpm"]
    }

    package { "nginx": ensure => "installed" }

    service { "nginx":
        ensure => running,
        enable => true
    }

    file { "/etc/nginx/sites-available/default":
        require => Package["nginx"],
        content  => template('main/nginx.conf.erb'),
        notify  => Service["nginx"]
    }
}