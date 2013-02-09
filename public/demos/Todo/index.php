<?php
// include todo class core files and grab all todos from DB
define('SAFETY_NET', true);
require_once('./inc/connect.php');
require_once('./inc/todo.php');

// load instance of the Todo app
$todo = new Todo($config);
?>

<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>Websockets | Todo Demo</title>
        <link href="../../css/bootstrap.css" rel="stylesheet" media="screen">
        <link href="../../css/custom.css" rel="stylesheet" media="screen">
		<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.0/themes/humanity/jquery-ui.css" type="text/css" media="all" />
		<link href="todo.css" rel="stylesheet" media="screen">
		<style>
		html, body { width: 100%; height: 100%; background: #f0f0f0; color: #222; }
		a { text-decoration: none; }
		#main { padding: 20px; }
		#main > p { font-size: 32px; line-height: 1.4; }
		#console { position: fixed; min-width: 320px; max-width: 100%; bottom: 0; right: 20px; height: 100px; padding: 10px; margin-bottom: -120px; color: #f0f0f0; background: #222; }
		#console ul { margin: 0; padding: 0; height: 100px; overflow-y: auto; }
		#console ul li { display: block; margin: 0 0 2px; padding: 2px 0; border-bottom: 1px solid #333; font: 11px/11px Consolas, "Andale Mono WT", "Andale Mono", "Lucida Console", "Lucida Sans Typewriter", "DejaVu Sans Mono", "Bitstream Vera Sans Mono", "Liberation Mono", "Nimbus Mono L", Monaco, "Courier New", Courier, monospace; }
		#toggleConsole { position: absolute; display: inline-block; top: -32px; right: 0; height: 32px; line-height: 32px; background: #222; padding: 0 10px; text-decoration: none; }
		</style>
    </head>
    <body class="unselectable">
        <div id="main" class="unselectable">
            <h1><a href="../">WebSockets PHP Demos</a> &rsaquo; Multi-User Todo List</h1>
            <div id="credits">
                <span>
                    Authored by <a href="http://coreyballou.co">Corey Ballou</a><br />
                    <a href="http://twitter.com/cballou">@cballou</a> on twitter<br />
                    <a href="http://github.com/cballou/php-websockets-demos/">fork on github</a>
                </span>
            </div>
            <p>
            In this demo, we demonstrate the ability to add Websocket support to an
			existing PHP application. In our case, we have taken a simple PHP/MySQL
			Todo list with CRUD functionality and modified it for multi-user
			push notification support. This requires some backend changes to our
			existing application; namely the addition of a ZeroMQ messaging layer
			to notify our Websocket server of any database changes so it may broadcast
			them to other users.
            </p>

			<div id="wrapper">
				<ul id="todoList">
					<?php echo $todo; ?>
				</ul>

				<a id="btn-add" class="green-button" href="#">Add Item</a>
			</div>
        </div>

		<div id="console">
			<a href="#" id="toggleConsole">Console <span>+</span></a>
			<ul></ul>
		</div>

        <script src="../../js/jquery.min.1.8.3.js"></script>
		<script src="../../js/jquery-ui.min.1.9.2.js"></script>
		<script src="../../js/when/when.js"></script>
		<script src="../../js/autobahn.min.js"></script>
        <script src="../../js/bootstrap.js"></script>
        <script src="../../js/demos/todo.js"></script>
    </body>
</html>


</body>
</html>
