<?php
namespace App\Mouse;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

require __DIR__ . '/../../../vendor/autoload.php';

/**
 *
 */
class Demo implements MessageComponentInterface {

    /**
     * An SPLObjectStorage container for all connected clients.
     * @var SPLObjectStorage
     */
    protected $_clients;

    /**
     * Default constructor to initialize clients.
     *
     * @access  public
     * @return  void
     */
    public function __construct()
    {
        $this->log('Initialized Mouse demo.');

        $this->_clients = new \SplObjectStorage();
    }

    /**
     * Triggered on initial opening of connection with a given browser client
     * (end user).
     *
     * @access  public
     * @param   ConnectionInterface $clientConn
     * @return  mixed
     */
    public function onOpen(ConnectionInterface $clientConn)
    {
        $this->log('Connection opened.');

        $this->_clients->attach($clientConn);
    }

    /**
     * Triggered on receiving a message from a given browser client (end user).
     * Subsequently sends out the message to all other connected clients.
     *
     * @access  public
     * @param   ConnectionInterface $clientConn
     * @param   mixed               $message
     * @return  mixed
     */
    public function onMessage(ConnectionInterface $clientConn, $msg)
    {
        $this->log('Message received.');

        // clean message up
        $msg = json_encode(
            array_map(function($arr) {
                return htmlentities($arr, ENT_QUOTES, 'UTF-8');
            }, json_decode($msg, TRUE))
        );

        // broadcast message to all clients
        foreach ($this->_clients as $otherClient) {
            if ($clientConn != $otherClient) {
                $otherClient->send($msg);
            }
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
    public function onClose(ConnectionInterface $clientConn)
    {
        $this->log('Closing connection.');

        $this->_clients->detach($clientConn);
    }

    /**
     * Triggered on receiving an error from a given browser client (end user).
     * Simply close the existing socket connection.
     *
     * @access  public
     * @param   ConnectionInterface $clientConn
     * @return  mixed
     */
    public function onError(ConnectionInterface $clientConn, \Exception $e)
    {
        // log the error
        $this->log($e);

        $clientConn->close();
    }

    /**
     * A very simple console logger.
     *
     * @access  public
     * @return  void
     */
    public function log($msg)
    {
        echo 'Demo App: ' . print_r($msg, true) . PHP_EOL;
    }
}

// Run the server application through the WebSocket protocol on port 8090
$server = IoServer::factory(
    new WsServer(
        new \App\Mouse\Demo()
    ),
    8090
);

$server->run();
