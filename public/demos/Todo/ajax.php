<?php
define('SAFETY_NET', true);
require_once('./inc/connect.php');
require_once('./inc/response.php');
require_once('./inc/todo.php');

try {

	// skip invalid AJAX requests
	if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
		strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
		throw new Exception('Invalid AJAX request.');
	}

	// handle particular action
	switch($_GET['action']) {

		case 'create':
			$response = Todo::create($_GET['text']);
			response($response);
			break;

		case 'update':
			$response = Todo::update((int) $_GET['id'], $_GET['text']);
			response($response);
			break;

		case 'delete':
			$response = Todo::delete((int) $_GET['id']);
			response($response);
			break;

		case 'sort':
			// handle sort
			$response = Todo::sort($_GET['positions']);
			response($response);
			break;

	}

} catch(Exception $e) {
	error_log($e->getMessage());
	response($e->getMessage(), true);
}

// unknown error
response(false);
