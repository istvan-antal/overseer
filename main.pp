node default {
    $maxUploadSize = 50
    $maxUploadedFileSize = $maxUploadSize
    $maxFileUploads = 50

    package { ["php5-fpm", "php5-cli", "php5-curl", "php5-mysql", "php5-redis"]: ensure => "installed" }

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