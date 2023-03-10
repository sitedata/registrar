<?php

use Swoole\Server;

// Create a new Swoole TCP server
$server = new Server('127.0.0.1', 9501, SWOOLE_PROCESS, SWOOLE_SOCK_TCP);

// Define the callback function for handling new connections
$server->on('connect', function (Server $server, $fd) {
    echo "New client connected: {$fd}\n";
});

// Define the callback function for handling incoming data
$server->on('receive', function (Server $server, $fd, $from_id, $data) {
    // Validate the input data
    $query = trim($data);
    if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $query)) {
        $error = "Invalid domain name";
        $server->send($fd, $error);
        return;
    }

    // Query the database for the specified domain
    $dsn = 'mysql:host=localhost;dbname=database_name';
    $username = 'username';
    $password = 'password';

    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $error = "Failed to connect to database: {$e->getMessage()}";
        $server->send($fd, $error);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM domains WHERE name=:name");
        $stmt->bindParam(':name', $query);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Failed to query database: {$e->getMessage()}";
        $server->send($fd, $error);
        return;
    }

    if (!$row) {
        $error = "Domain not found";
        $server->send($fd, $error);
        return;
    }

    $details = "Domain Name: {$row['name']}\n";
    $details .= "Registrar WHOIS Server: {$row['registrar_whois_server']}\n";
    $details .= "Registrar URL: {$row['registrar_url']}\n";
    $details .= "Updated Date: {$row['updated_date']}\n";
    // Include additional domain details as necessary

    // Send the details back to the client
    $server->send($fd, $details);
});

// Define the callback function for handling errors
$server->on('error', function (Server $server, $error_code, $error_message) {
    echo "Error {$error_code}: {$error_message}\n";
});

// Start the server
if (!$server->start()) {
    echo "Failed to start server\n";
}
