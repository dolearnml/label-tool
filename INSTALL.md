# Pull git repository
```bash
sudo apt update
sudo apt install -y git
git clone https://github.com/dolearnml/label-tool.git label
```

# Install LEMP stack
https://www.digitalocean.com/community/tutorials

```bash
sudo apt install -y nginx
sudo apt install -y mysql-server # choose 1234 as root password
mysql_secure_installation
sudo apt install -y php-fpm php-mysql php-memcached
```

### Config PHP
in `/etc/php/7.0/fpm/php.ini` set `cgi.fix_pathinfo=0`
```bash
sudo nano /etc/php/7.0/fpm/php.ini
sudo systemctl restart php7.0-fpm
```

### Config nginx
in nginx configuration file `/etc/nginx/sites-available/default`
* add `index.php` to `index` directive
* uncomment `include snippets/fastcgi-php.conf;`
* uncomment `location ~ \.php$ {}` block and within the block, uncomment `fastcgi_pass unix:/run/php/php7.0-fpm.sock;` and `include snippets/fastcgi-php.conf;`
* uncomment `location ~ /\.ht {}` block
```bash
sudo nano /etc/nginx/sites-available/default
sudo nginx -t # test nginx configuration
sudo systemctl reload nginx # reload nginx server
```

### Test PHP with `<?php phpinfo();`
```bash
sudo nano /var/www/html/info.php
```

### go to http://localhost/info.php (should WORK)

# Install Memcached
https://www.digitalocean.com/community/tutorials/how-to-install-and-secure-memcached-on-ubuntu-16-04
```bash
sudo apt install -y memcached libmemcached-tools
sudo nano /etc/memcached.conf # check if -l 127.0.0.1 uncommented
sudo systemctl restart memcached
sudo netstat -plunt | grep memcached
memcstat --servers="127.0.0.1"
```

### Config memcached 
in `/etc/memcached.conf`
* add SASL support directive -S
* set verbose -vv
```bash
sudo nano /etc/memcached.conf
sudo systemctl restart memcached
journalctl -u memcached.service | grep SASL # should show Initialize SASL
memcstat --servers="127.0.0.1" # should not work because SASL required
echo $? # should print 1 (authorization error)
```

### Add SASL user
```bash
sudo apt-get install -y sasl2-bin
sudo mkdir -p /etc/sasl2
sudo nano /etc/sasl2/memcached.conf 
```
Add the following lines to `/etc/sasl2/memcached.conf`
```
mech_list: plain
log_level: 5
sasldb_path: /etc/sasl2/memcached-sasldb2
```
Install user `www-data` with password *1234*
```bash
sudo saslpasswd2 -a memcached -c -f /etc/sasl2/memcached-sasldb2 www-data
sudo chown memcache:memcache /etc/sasl2/memcached-sasldb2
sudo systemctl restart memcached
memcstat --servers="127.0.0.1" --username=www-data --password=1234
echo $?
```
If the last step fails, check if `hostname` is exact in `/etc/sasl2/memcached-sasldb2`

### Enable SASL support for PHP
Add the following line to `/etc/php/7.0/fpm/php.ini` and restart `php7.0-fpm`
```
; enable SASL support
memcached.use_sasl=1
```
```bash
sudo nano /etc/php/7.0/fpm/php.ini 
sudo systemctl restart php7.0-fpm
```

### Copy the code and set permissions
```bash
sudo cp -R <path to label-tool repository>/* /var/www/html/
sudo chown $USER:www-data /var/www/html -R
sudo find /var/www/html -type d -exec chmod g+s {} \;
```

### Create `/var/www/html/images` folder structure 
Use the following structure
```
images/
  label1/
    1.jpg
    2.jpg
  label2/
    3.jpg
    4.jpg
  ...
```
For example,
```bash
mkdir /var/www/html/images/dog
mkdir /var/www/html/images/cat
cp ~/Downloads/dog1.jpg /var/www/html/images/dog
cp ~/Downloads/dog2.jpg /var/www/html/images/dog
cp ~/Downloads/cat1.jpg /var/www/html/images/cat
cp ~/Downloads/cat2.jpg /var/www/html/images/cat
```

### Create result file at `/var/www/html/results/results.txt`
```bash
touch /var/www/html/results/results.txt
```

### Goto http://localhost (should show random image, labels to pick and submit)
