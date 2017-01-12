# FreePBX-gentoo

1. Configure /etc/portage/package.use

    net-misc/asterisk http mysql
    dev-lang/php mysql opcache fpm pdo curl gd mbstring gettext
    media-sound/sox flac opus ogg wavpack
    media-video/ffmpeg vorbis

2. Install asterisk & required packages

    # emerge -avu nginx php mariadb pear PEAR-Console_Getopt sox mpg123 sudo asterisk
    
3. Install additional packages

    * for chan_dongle
    # emerge -avu picocom
    
4. Configure nginx & php-fpm
    * run:
    cd /etc/nginx; mkdir conf.d; touch freepbx.conf;
    
    * write in to freepbx.conf
    server {
	listen 80;
	server_name sip;
	return 301 https://$host$request_uri;
    }
    
    server {
	listen  443 ssl http2;
        server_name sip;
#       disable_symlinks if_not_owner from=/var/www/html;
        root /var/www/html;

	ssl on;
	ssl_certificate /etc/letsencrypt/live/sip/fullchain.pem;
	ssl_certificate_key /etc/letsencrypt/live/sip/privkey.pem;
	ssl_stapling on;
	ssl_stapling_verify on;
	ssl_trusted_certificate /etc/letsencrypt/live/sip/chain.pem;
	ssl_session_cache shared:SSL:10m;
	ssl_ciphers HIGH:!aNULL:!MD5;
	ssl_dhparam /etc/nginx/dhparam.pem;
	ssl_protocols TLSv1.2 TLSv1.1 TLSv1;

        index index.php;
        fastcgi_read_timeout 300s;
        fastcgi_param  SCRIPT_FILENAME    $document_root$fastcgi_script_name;

        gzip off;
	include fastcgi_params;
        location ^~ /admin {
                allow 1.1.1.1;
                deny all;
                location ~* \.php$ {
                        try_files    $uri =404;
                        fastcgi_pass unix:/var/run/php-fpm.sock;
                }
        }

        location ~* \.php$ {
                try_files    $uri =404;
                fastcgi_pass unix:/var/run/php-fpm.sock;
        }
        location ~* /\.ht {
                deny all;
        }
    }

    * append to php.ini
    memory_limit=512M
    max_execution_time=300
    post_max_size=32M
    upload_max_filesize=32M
    cgi.fix_pathinfo=0
    error_reporting=E_ALL & ~E_STRICT & ~E_NOTICE
    allow_url_fopen=On
    output_buffering=On
    always_populate_raw_post_data=-1

    * replace php-fpm.conf with this:
    [www]
    env[PATH] = /usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/bin:/usr/x86_64-pc-linux-gnu/gcc-bin/4.9.3

    listen = /var/run/php-fpm.sock
    listen.owner = nginx
    listen.group = nginx
    listen.mode = 0600
    user = asterisk
    group = asterisk
    catch_workers_output = no
    pm = ondemand
    pm.max_children = 20
    pm.start_servers = 2
    pm.min_spare_servers = 5
    pm.max_spare_servers = 20
    pm.max_requests = 100
    
    * run:
    echo php-fpm -y /etc/php/fpm-php5.6/php-fpm.conf >> /etc/local.d/php.start; chmod +x /etc/local.d/php.start; 
    
    rc-update add local
    rc-update add nginx
    rc-update add mysql
    
    * replace from /etc/mysql/my.cnf
    #log-bin
    skip-networking
    
5. Generate dh-param for ssl_certificate
    openssl dhparam -out /etc/nginx/dhparam.pem 2048


6. Download and install FreePBX

    # cd /var/www
    # wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-13.0-latest.tgz -O freepbx-13.0-latest.tgz
    # tar xf freepbx-13.0-latest.tgz
    # 