#DiSCuS
Database System for Compound Selection


##1. System requirements:

###1.1. Server side
* Openbabel + PHP/Python Bindings
* Apache (or any other popular webserver)
* PHP
* Python
* MySQL (or MariaDB, Percona)
* MyChem database cartridge

###1.2. Client Side:
* Modern web browser: Chrome, Firefox, IE 10+ might work, but it's not tested on regular basis

##2. Installation

###2.1 Debian Wheezy

2.1.1. Install dependencies

> apt-get install php5 php5-dev php5-xcache apache2 phpmyadmin mysql-server libmysqld-dev  libmysqlclient-dev git swig cmake

2.1.2. (optional) bonjour to resolve host names 

> apt-get -y install avahi-daemon libnss-mdns

2.1.3. Install dependencies for OB compilation (Eigen 2 needs to be removed, since new builds of openbabel prefer Eigen 3)

> apt-get -y build-dep openbabel

> apt-get remove libeigen2-dev

> apt-get install libeigen3-dev

2.1.4. Download Openbabel and Mychem (using discus-deploy repository)

> git clone --recursive https://github.com/mwojcikowski/discus-deploy.git discus-deploy

2.1.5. Compile and global install Openbabel and Mychem

> cd discus-deploy

> ./compile_ob

> cd openbabel-build && make install && cd ..

> ./compile_mychem

> cd mychem-build && make install && cd ..

> mysql -u root -p < mychem/src/mychemdb.sql

2.1.6. Create MySQL user for DiSCuS

You can do it via CLI or via PhpMyAdmin

2.1.7. Get DiSCuS code

> cd /var/www

> git clone https://github.com/mwojcikowski/discus.git discus

2.1.8. Open your instance of DiSCuS in web browser and proceed with installation.

> http://YOUR_HOSTNAME_OR_IP/discus/

2.1.9. DiSCuS is ready for use

Note: additional setup might be necessary for some plugins, f.e. you must get and copy your Xscore code, install Tripos Sybyl on host, etc.

[![githalytics.com alpha](https://cruel-carlota.pagodabox.com/22c804cc73ffadcef7f044636cd07a5c "githalytics.com")](http://githalytics.com/mwojcikowski/discus)
