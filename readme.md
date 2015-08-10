## Passmanagement
A simple user interface to manage NFC-cards in LDAP.

## Requirements

## Installation
1. Install dependencies: `sudo apt-get install php5-fpm php5-ldap nginx git`.
1. Create a folder for the project: `mkdir /srv/passmanagement`.
1. Clone the project in that directory: `git clone https://github.com/jakobbuis/passmanagement.git /srv/passmanagement`.
1. Make sure nginx can access the files: `sudo chown -R jakob:www-data /srv/`.
1. Copy `config.example.php` to `config.php` and fill in the details.
1. Copy `public/javscripts/config.example.js` to `public/javscripts/config.js` and fill in the details.
1. Install the composer dependencies `php composer.phar install`.
1. Copy the included `nginx.conf` file to the right directory: `sudo cp /srv/passmanagement/nginx.conf /etc/nginx/sites-available/passmanagement`. Adapt as needed.
1. Symlink the website to enable it: `sudo ln -s /etc/nginx/sites-available/passmanagement /etc/nginx/sites-enabled/passmanagement`.
1. Reload nginx: `sudo /etc/init.d/nginx reload`.
1. Open the application in your browser.

## License
Copyright 2015 Jakob Buis. All rights reserved.
