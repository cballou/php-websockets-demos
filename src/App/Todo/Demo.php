<?php
namespace App\Todo;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

require __DIR__ . '/../../../vendor/autoload.php';

/**
 * This example application is based on the Ratchet Push tutorial found at:
 * http://socketo.me/docs/push
 */
class Demo implements WampServerInterface {

    /**
     * A lookup of all the topics  clients have subscribed to. A topic is a common
     * word for PubSub namespaces.
     *
     * @var array
     */
    protected $_subscribedTopics = array();

    /**
     * We also keep track of the count of subscribed topics for purposes of
     * garbage collection.
     *
     * @var array
     */
    protected $_subscribedCount = array();

    /**
     * An array of locked todos due to editing.
     *
     * @var array
     */
    protected $_locked = array();

    /**
     * Whether one of the users is repositioning the sortable.
     *
     * @var array
     */
    protected $_repositioning = FALSE;

    /**
     * This method gets called when a client subscribes to a particular PubSub
     * category/topic.
     *
     * @access  public
     * @param   ConnectionInterface     $conn
     * @param   object                  $topic
     * @return  void
     */
    public function onSubscribe(ConnectionInterface $conn, $topic)
    {
        // ensure we add the topic to the list of subscribed topics
        if (!isset($this->_subscribedTopics[$topic->getId()])) {
            error_log('User ' . $conn->WAMP->sessionId . ' subscribed to topic ' . $topic->getId());
            $this->_subscribedTopics[$topic->getId()] = $topic;
            $this->_subscribedCount[$topic->getId()] = 0;
        }

        // increment subscribed count
        $this->_subscribedCount[$topic->getId()]++;
    }

    /**
     * This method gets called when a client unsubscribes from a particular PubSub
     * category/topic.
     *
     * @access  public
     * @param   ConnectionInterface     $conn
     * @param   object                  $topic
     * @return  void
     */
    public function onUnSubscribe(ConnectionInterface $conn, $topic)
    {
        // ensure we add the topic to the list of subscribed topics
        if (!isset($this->_subscribedTopics[$topic->getId()])) {
            return;
        }

        error_log('User ' . $conn->WAMP->sessionId . ' unsubscribed from topic ' . $topic->getId());

        // decrement the topic count
        $this->_subscribedCount[$topic->getId()]--;

        // if the count is 0, we can remove the topic (garbage collection)
        if ($this->_subscribedCount[$topic->getId()] <= 0) {
            unset(
                $this->_subscribedTopics[$topic->getId()],
                $this->_subscribedCount[$topic->getId()]
            );
        }
    }

    /**
     * Triggered on initial opening of connection with a given browser client
     * (end user).
     *
     * @access  public
     * @param   ConnectionInterface $clientConn
     * @return  mixed
     */
    public function onOpen(ConnectionInterface $conn)
    {
        error_log('onOpen event triggered for user ' . $conn->WAMP->sessionId);

        try {

            // add to list of connected users
            $this->_connections[$conn->WAMP->sessionId] = TRUE;

            // broadcast connected message to others
            $this->broadcastMessage(
                'connected',
                json_encode(array('id' => $conn->WAMP->sessionId))
            );

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Triggered on closing of connection with a given browser client (end user).
     * Simply detaches (removes the client connection from the list of active
     * clients).
     *
     * @access  public
     * @param   ConnectionInterface $clientConn
     * @return  mixed
     */
    public function onClose(ConnectionInterface $conn)
    {
        error_log('onClose event triggered for user ' . $conn->WAMP->sessionId);

        try {

            $user_id = $conn->WAMP->sessionId;

            // check if the user held any locks
            foreach ($this->_locked as $id => $user_lock_id) {
                if ($user_id == $user_lock_id) {
                    // remove lock
                    unset($this->_locked[$id]);

                    // inform all users of unlock
                    $this->broadcastMessage(
                        'unlock',
                        json_encode(array('user_id' => $user_id))
                    );
                }
            }

            if ($this->_repositioning == $user_id) {
                $this->_repositioning = false;

                // inform all users of unlock
                $this->broadcastMessage(
                    'finish-reposition',
                    json_encode(array('user_id' => $user_id))
                );
            }

            $this->broadcastMessage(
                'disconnected',
                json_encode(array('id' => $conn->WAMP->sessionId))
            );

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     *
     */
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params)
    {
        // In this application if clients send data it's because the user hacked around in console
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }

    /**
     * Handler for events triggered by Autobahn's publish method.
     *
     * @access  public
     * @param   ConnectionInterface $conn
     * @param   string              $topic
     * @param   array               $event      The event data
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible)
    {
        try {

            // get the publisher id
            $return = false;
            $user_id = $conn->WAMP->sessionId;
            $type = $topic->getId();

            error_log('onPublish event triggered: ' . $type);

            // determine how to handle publish event
            if ($type == 'lock') {
                // check if the field is locked
                if (!isset($this->_locked[$event['id']])) {
                    // set as locked and notify parties
                    $this->_locked[$event['id']] = $event['user_id'];
                    $return = true;
                }
            } else if ($type == 'unlock') {
                if (isset($this->_locked[$event['id']])) {
                    unset($this->_locked[$event['id']]);
                    $return = true;
                }
            } else if ($type == 'reposition') {
                if (!$this->_repositioning) {
                    $this->_repositioning = $event['user_id'];
                    $return = true;
                }
            } else if ($type == 'finish-reposition') {
                if ($this->_repositioning) {
                    $this->_repositioning = false;
                    $return = true;
                }
            } else if ($type == 'sort') {
                // send the new sort data to other clients
                $return = true;
            } else if ($type == 'update') {
                return $true;
            }

            // broadcast the data out to subscribers
            if ($return === true) {
                $this->broadcastMessage($type, json_encode($event));
            }

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Triggered on receiving an error from a given browser client (end user).
     * Simply close the existing socket connection.
     *
     * @access  public
     * @param   ConnectionInterface $conn
     * @return  mixed
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        error_log('An error occurred, closing connection.');
        error_log($e->getMessage());

        $conn->close();
    }

    /**
     * Handles incoming "message" data from ZeroMQ. In this case, we're assuming
     * incoming data is JSON encoded. We take the incoming data, check it for
     * a particular message type, and broadcast the message out to all clients
     * subscribed to the particular pubsub category.
     *
     * @param   mixed   $entry
     * @return  void
     */
    public function onMessage($entry)
    {
        try {

            error_log('Message received.');

            // decode the message
            $message = json_decode($entry, true);

            // wrapper for broadcasting topic by type
            $this->broadcastMessage($message['type'], $entry);

        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Handles broadcasting the message out to all topic subscribers.
     *
     * @access  public
     * @param   string  $type
     * @param   mixed   $entry
     */
    public function broadcastMessage($type, $entry)
    {
        try {

            // If the lookup topic object isn't set there is no one to publish to
            if (!isset($this->_subscribedTopics[$type])) {
                return false;
            }

            // grab the topic for broadcast
            $topic = $this->_subscribedTopics[$type];

            // re-send the serialized JSON to all the clients subscribed to that category
            $topic->broadcast($entry);

            return true;

        } catch (Exception $e) {
            error_log($e->getMessage());
        }

        return false;
    }

}

// initiate the loop listener
$loop = \React\EventLoop\Factory::create();
$todo = new \App\Todo\Demo();

// bind a ZeroMQ PULL listener
$context = new \React\ZMQ\Context($loop);
$pull = $context->getSocket(\ZMQ::SOCKET_PULL);
$pull->bind('tcp://127.0.0.1:5555');
$pull->on('message', array($todo, 'onMessage'));

// Set up our WebSocket server for clients wanting real-time updates
$websock = new \React\Socket\Server($loop);
$websock->listen(8091, '0.0.0.0');

$webserver = new \Ratchet\Server\IoServer(
    new \Ratchet\WebSocket\WsServer(
        new \Ratchet\Wamp\WampServer(
            $todo
        )
    ),
    $websock
);

$loop->run();
