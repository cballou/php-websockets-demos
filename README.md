## About This Repository ##

This repo contains both web-viewable presentations slides in addition to three
PHP websocket demos that you may setup and run locally. All of these demos are
based on the work of the following two projects:

* **[ReactPHP](http://reactphp.org/)** Event-driven, non-blocking I/O with PHP
* **[Ratchet](http://socketo.me/)** a loosely coupled PHP library providing developers with tools to create real time, bi-directional applications between clients and servers over WebSockets.

Below you will find details on how to view the slides in addition to setup and
run the demos. The following assumptions are made regarding your local machine
(and are pre-requisites to running):

* You are running PHP 5.3+
* You have globally installed composer or will install it locally in the application root directory
* You have MySQL installed and setup (only for the Todo demo)

## Live Demo ##

It's only fun with a buddy, so please grab a friend: [PHP Websocket Demos](http://websockets.coreyballou.com)

## Viewing the Presentation Slides ##

You can view the slides on the SlideShare presentation, [Creating Realtime Applications with PHP and Websockets](http://www.slideshare.net/CoreyBallou/creating-realtime-applications-with-php-and-websockets).

Alternatively, you can perform the following steps to view these slides locally:

* Clone this repository via `git clone path/to/repository.git`
* Open up your browser of choice, ideally something with CSS3 support (Chrome, Firefox, or Safari)
* Navigate to `file:///absolute/path/to/presentation/index.html`, replacing `/absolute/path/to` with the proper absolute path.
* Enjoy!

If you setup this directory under Apache or another web server, you also have the
ability to view the speaker notes window via pressing the `S` key. Since setting
up the site to run via a web server is required for the complex demo, this may
be ideal.

## Running Demos ##

All three included WebSocket demos require you to run a server script from the
command line / console. Each of the three demos runs on a different port for
the purpose of being able to run all three demos simultaneously.

Before you get into each section, you must first install all composer packages.
If you aren't familiar with composer, please [follow these composer installation instructions](https://github.com/composer/composer).
From the command line and the base directory of this repository:

```bash
# if composer is installed globally
composer update

# if you installed composer locally to the application
composer.phar update
```

### Running the User Logger Demo ###

The user logger demo is the simplest of the three demos. The backend WebSocket
server PHP code is fairly stock and the frontend JavaScript adds a very small
amount of application logic on top of the core HTML5 WebSockets API. The application
itself demonstrates real-time monitoring of user interaction with a website by
recording their browser settings and broadcasting them out to all other connected
users.

#### Running the WebSocket Server ####

* Open up your command line / console.
* From the base repository directory, run the command `php src/App/UserLogger/Demo.php`
* You should now have a WebSocket server listening on port `8092`.

#### Connecting to the WebSocket Server as a Client ####

* Open up your WebSocket supported web browser (Chrome, FF, Safari, IE10)
* Create two new tabs
* Navigate both tabs to `file:///absolute/path/to/public/demos/UserLogger/index.html`
* Notice that on the first opened tab, the second user was logged
* Opening subsequent tabs to the application will log the information

### Running the Mouse Tracking Demo ###

The mouse tracking demo takes things a step further. The WebSocket server backend
used is exactly the same as that used in the User Logger demo. The true difference
is in the amount of client-side JavaScript. In this demo, we bind mouse movement
and click events to a callback function which records the current position. Each
user receives a custom generated circular dot with their own color and size. On
each movement, a WebSocket send event is triggered sending the server the user's
new coordinates. The server broadcasts the new coordinates and accompanying user
data to the remainder of the connected clients and they update their screens
accordingly.

#### Running the WebSocket Server ####

* Open up your command line / console.
* From the base repository directory, run the command `php src/App/Mouse/Demo.php`
* You should now have a WebSocket server listening on port `8090`.

#### Connecting to the WebSocket Server as a Client ####

* Open up your WebSocket supported web browser (Chrome, FF, Safari, IE10)
* Create two new tabs
* Navigate both tabs to `file:///absolute/path/to/public/demos/Mouse/index.html`
* Notice that on the first opened tab, the second user's mouse position is showing up
* Opening subsequent tabs to the application will track each user's mouse location

### Running the Todo List Demo ###

The todo list demo takes a basic CRUD implementation of a todo list and turns it
on it's head. This demo introduces the ability to add WebSockets to an existing
PHP application. This does come at quite a cost, however, as it entails adding
a messaging layer to your existing application so it may talk to the WebSocket
server. In our case, we use ZeroMQ as it is supported by [react/zeromq](https://github.com/reactphp/zmq).
The application also introduces the [WAMP](http://wamp.ws) sub-protocol of WebSockets,
allowing us to use RPC and PubSub patterns to manage the sheer number of different
event types. One of the key takeaways from this demo is the amount of edge-cases
that need to be handled due to concurrency. This includes various forms of locking
to ensure users don't step on each other's toes.

Since the todo list demo is the most complex of the three demos, it has additional
pre-requisites in order to run it:

* Your environment must currently have MySQL installed
* You are capable of creating a new MySQL database and importing a SQL file
* You have the ability to install ZeroMQ and the PHP ZeroMQ module
* You have a web server at your disposal (Apache, nginx, etc) for setting up a virtual host

In order to install ZeroMQ, you will have to do one of the following depending on your environment:

#### Installing ZeroMQ on Debian ####
* `sudo add-apt-repository ppa:chris-lea/zeromq && apt-get update && apt-get install php-zmq zeromq`

#### Installing ZeroMQ on Fedora / CentOS / RHEL ####
* Install the REMI repository for your distro (http://rpms.famillecollet.com/)
* `yum install php-zmq zeromq`

#### Installing ZeroMQ on X ####
* You're on your own, check this out -> http://www.zeromq.org/bindings:php

#### Importing the MySQL Database ####
From within MySQL, create a new database and user:

```bash
CREATE DATABASE ws_todo;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, LOCK TABLES, INDEX ON 'ws_todo'.* TO 'ws_todo'@localhost IDENTIFIED BY 'somuchtodo';
FLUSH PRIVILEGES;
```
Now that we're back at the command line, we need to import the database table:

```bash
mysql -u YourUserName -p ws_todo < public/demos/Todo/inc/table.sql
```

Simply change the relative path to `public/demos/Todo/inc/table.sql` as necessary.

#### Running the Web Server ####

* Create an Apache or nginx VirtualHost file pointing the basepath (public directory) to `/absolute/path/to/public/demos/Todo/`
* Restart Apache/nginx to enable
* If you are using a hostname and not an IP address and port, don't forget to add `127.0.0.1 hostname` to your `/etc/hosts` file

#### Running the WebSocket Server ####

* Open up your command line / console.
* From the base repository directory, run the command `php src/App/Todo/Demo.php`
* You should now have a WebSocket server listening on port `8091`.

#### Connecting to the WebSocket Server as a Client ####

* Open up your WebSocket supported web browser (Chrome, FF, Safari, IE10)
* Create two new tabs
* Navigate both tabs to `http:///hostname/`, as setup in your web server
* When editing a Todo item, notice on the other screen that it is locked until completed
* When moving a todo item, notice on the other screen that sorting is locked until completed

## References &amp; Sources ##

* http://srchea.com/blog/2011/12/build-a-real-time-application-using-html5-websockets/
* WebSocket RFC 6455 - http://tools.ietf.org/html/rfc6455
* http://lucumr.pocoo.org/2012/9/24/websockets-101/
* buffered amounts http://www.w3.org/TR/websockets/


## Credits ##

* [ReactPHP](http://reactphp.org/)
* [Ratchet](http://socketo.me/)
* [WAMP](http://wamp.ws)
* [Tutorialzine | AJAX-ed Todo List With PHP, MySQL & jQuery](http://tutorialzine.com/2010/03/ajax-todo-list-jquery-php-mysql-css/)
* https://speakerdeck.com/vikgamov/websockets-the-current-state-of-the-most-valuable-html5-api-for-java-developers


[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/cballou/php-websockets-demos/trend.png)](https://bitdeli.com/free "Bitdeli Badge")

