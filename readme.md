## Passmanagement
A simple user interface to manage NFC-cards in LDAP.

## Local installation
This project is locally installed using [vagrant](https://www.vagrantup.com/). Vagrant provides an super easy way to setup a local virtual machine with the perfect environment for starting work on Passmanagement.

1. Install [vagrant](https://www.vagrantup.com/) and [virtualbox](https://www.virtualbox.org/) if not installed already.
1. Point http://passmanagement.app to your local machine. On Linux, you usually add this to the `/etc/hosts` file.
1. Clone this repository `git clone git@github.com:jakobbuis/passmanagement.git`
1. Run `vagrant up` in the directory in which you've cloned.
1. In the project folder, copy `public/javascripts/config.example.js` to `public/javascripts/config.js` and fill in all details. You can get these values from an ICTcom member.
1. In the project folder, copy `config.example.php` to `config.php` and fill in all details. You can get these values from an ICTcom member.
1. Connect to the VM using `vagrant ssh`. Open the project folder (`cd /home/vagrant/bolknoms2`) and run all migrations `php artisan migrate`.
1. Open [http://bolknoms.app/](http://bolknoms.app/) in your browser.

## Deploying to production

## License
Copyright 2015 Jakob Buis. All rights reserved.
