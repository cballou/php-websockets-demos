<?php if (!defined('SAFETY_NET')) die('Where is your safety net?');

/**
 * Todo class implementation. Credit goes out to the tutorial found at:
 *
 * http://tutorialzine.com/2010/03/ajax-todo-list-jquery-php-mysql-css/
 */
class Todo {

	/**
	 * The database name.
	 *
	 * @var	string
	 */
	private static $_db = 'ws_todo';

	/**
	 * Storage array of todo items.
	 *
	 * @var	array
	 */
	private $_data;

	/**
	 * Default constructor.
	 *
	 * @access	public
	 * @param	array	$config
	 * @return	void
	 */
	public function __construct($config)
	{
		if (is_array($config)) {
			if (isset($config['db']['database'])) {
				self::$_db = $config['db']['database'];
			}
		}

		// load items
		$this->loadItems();
	}

	/**
	 * Load all todo items from the db into local storage.
	 *
	 * @access	public
	 * @return	void
	 */
	public function loadItems()
	{
		// retrieve all todos from the database
		$query = mysql_query("SELECT * FROM `ws_todo` ORDER BY `position` ASC");
		$todos = array();
		while ($row = mysql_fetch_assoc($query)) {
			$todos[] = $row;
		}

		// add any todo items
		if (!empty($todos)) {
			$this->addItems($todos);
		}
	}

	/**
	 * Ability to add an array of todo items. A todo item is essentially the
	 * returned database row.
	 *
	 * @access	public
	 * @param	array	$items
	 * @return	void
	 */
	public function addItems(array $items)
	{
		$this->_data = $items;
	}

	/**
	 * Magic method to pretty print todo items.
	 *
	 * @access	public
	 * @return	string
	 */
	public function __toString()
	{
		$return = '';

		if (!empty($this->_data)) {
			foreach ($this->_data as $todo) {
				$return .=
					'<li id="todo-' . (int) $todo['id'] . '" class="todo">
						<div class="text">' . self::clean($todo['text']) . '</div>
						<div class="actions">
							<a href="#" class="edit">Edit</a>
							<a href="#" class="delete">Delete</a>
						</div>
					</li>' . PHP_EOL;
			}
		}

		return $return;
	}

	/**
	 * Create a new todo item. We need to implement optimistic locking to prevent
	 * race conditions.
	 *
	 * http://stackoverflow.com/questions/2805041/insert-a-row-and-avoiding-race-condition-php-mysql
	 *
	 * @access	public static
	 * @param	string	$text
	 * @return	void
	 */
	public static function create($text)
	{
		$text = self::esc($text);
		if (empty($text)) {
			throw new Exception("No todo text was provided!");
		}

		try {

			// exclusive table lock during write
			mysql_query(sprintf("LOCK TABLE %s WRITE", self::$_db));

			// get the maximum position
			$posResult = mysql_query(
				sprintf("SELECT MAX(position) + 1 FROM %s", self::$_db)
			);

			$position = 1;
			if (mysql_num_rows($posResult)) {
				list($position) = mysql_fetch_array($posResult);
			}

			// insert new todo with proper position
			mysql_query(
				sprintf('INSERT INTO %s SET
						text = "%s",
						position = %d',
						self::$_db,
						$text,
						$position)
			);

			// get the id
			$id = mysql_insert_id();

			// unlock table
			mysql_query("UNLOCK TABLES");

			// push message data
			$data = array(
				'id' => $id,
				'text' => self::clean($text),
				'position' => $position
			);

			// handle the ZeroMQ push message
			self::pushMessage($data, 'create');

			// return the newly created data row
			return $data;

		} catch (Exception $e) {
			// log the exception
			error_log($e->getMessage());

			// ensure we unlock
			mysql_query("UNLOCK TABLES");
		}

		return false;
	}

	/**
	 * Handles editing a passed in todo item by it's id.
	 *
	 * @access	public static
	 * @param	int		$id
	 * @param	string	$text
	 * @return	mixed
	 */
	public static function update($id, $text)
	{
		$text = self::esc($text);
		if (empty($text)) {
			throw new Exception("Invalid value supplied for a todo item!");
		}

		mysql_query(
			sprintf('UPDATE %s SET
					text = "%s"
					WHERE id = %d',
					self::$_db,
					$text,
					$id)
		);

		if (mysql_affected_rows() != 1) {
			throw new Exception("An error occurred attempting to update the todo item!");
		}

		// push message data
		$data = array(
			'id' => $id,
			'text' => self::clean($text)
		);

		// handle the ZeroMQ push message
		self::pushMessage($data, 'update');

		// return the data
		return $data;
	}


	/**
	 * Deletion of an existing todo item by id.
	 *
	 * @access	public
	 * @param	int		$id
	 * @return	void
	 */
	public static function delete($id)
	{
		mysql_query(
			sprintf('DELETE FROM %s WHERE id = %d',
					self::$_db,
					$id)
		);

		if (mysql_affected_rows() != 1) {
			throw new Exception("An error occurred attempting to delete the todo item!");
		}

		// push message data
		$data = array('id' => $id);

		// handle the ZeroMQ push message
		self::pushMessage($data, 'delete');

		// return the data
		return $data;
	}

	/**
	 * The rearrange method handles re-ordering the todo items based on the
	 * incoming array of todo ids.
	 *
	 * @access	public 	static
	 * @param	array	$sortOrders
	 */
	public static function sort($sortOrders)
	{
		$updateVals = array();

		// generate SWITCH CASE logic
		foreach ($sortOrders as $idx => $todo_id) {
			$updateVals[] = 'WHEN ' . (int) $todo_id . ' THEN ' . ((int) $idx + 1) . PHP_EOL;
		}

		if (empty($updateVals)) {
			throw new Exception("No todo items found to reposition!");
		}

		// update all at once
		mysql_query(
			sprintf('UPDATE %s SET
					position = CASE id %s
					ELSE position
					END',
					self::$_db,
					join($updateVals))
		);

		if (mysql_error()) {
			throw new Exception("An error occurred attempting to update the todo item positions!");
		}

		// handle the ZeroMQ push message
		self::pushMessage($sortOrders, 'sort');

		// return success
		return true;
	}

	/**
	 * Handler for sending out a ZeroMQ push message.
	 *
	 * @access	public
	 * @param	array	$arr
	 * @param	string	$type
	 * @return	void
	 */
	public static function pushMessage($arr, $type)
	{
		$context = new ZMQContext();
		$socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'todo-persistent');
		$socket->connect("tcp://127.0.0.1:5555");

		$socket->send(
			json_encode(
				array(
					'type' => $type,
					'data' => $arr
				)
			)
		);
	}

	/**
	 * Similar to pushing a message, but we also care about the response. ZMQ
	 * socket types are defined at: http://api.zeromq.org/3-2:zmq-socket
	 */
	public static function pushPullMessage($arr, $type)
	{
		$context = new ZMQContext();
		$socket = $context->getSocket(ZMQ::SOCKET_REQ, 'todo-push-pull-persistent');
		$socket->connect("tcp://127.0.0.1:5556");

		error_log('Sending push pull message.');

		$socket->send(
			json_encode(
				array(
					'type' => $type,
					'data' => $arr
				)
			)
		);

		error_log('Receiving response.');

		$data = $socket->recv();
		error_log(print_r($data, true));

		// return received response
		return $data;
	}

	/**
	 * A database sanitization helper.
	 *
	 * @access	public
	 * @param	string	$str
	 * @return	string
	 */
	public static function esc($str)
	{
		if (ini_get('magic_quotes_gpc')) {
			$str = stripslashes($str);
		}

		return mysql_real_escape_string(strip_tags($str));
	}

	/**
	 * Sanitize strings to prevent any XSS attempts.
	 *
	 * @access	public
	 * @param	string	$str
	 * @return	string
	 */
	public static function clean($str)
	{
		return htmlentities(
			strip_tags($str),
			ENT_COMPAT | ENT_HTML401,
			'UTF-8'
		);
	}

}
