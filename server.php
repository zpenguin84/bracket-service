<?php
fclose(STDERR);

require 'vendor/autoload.php';

use BracketValidator\BracketValidator as B;


$address = '127.0.0.1';
$port = getenv('BRACKET_SERVICE_PORT');
if ($port === false)
{
	$shortopts = 'p::';
	$longopts = ['port::'];
	$options = getopt($shortopts, $longopts);
	$port = $options['p'] ?? $options['port'] ?? false;
}

if ($port === false)
{
	echo "You should set port to run bracket-server. Use option --port=<port> (-p<port>) to define it.";
	return;
}


try
{
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if (socket_bind($socket, $address, $port) === false)
		throw new \Exception('Could not bind socket');
	socket_listen($socket, 1);
}
catch (\Throwable $e)
{
	echo "Error starting PHP Bracket Server\n";
	echo $e->getMessage() . "\n";
	exit;
}

echo "PHP Bracket Server is running\nPort: $port\nPID: " . posix_getpid() . "\n\n";

while (true)
{
	$msgsock = socket_accept($socket);

	$message = "\nWelcome to PHP Bracket Server!\n";
	socket_write($msgsock, $message, strlen($message));
	echo "Enter\n\n";

	while (true)
	{
		$buffer = socket_read($msgsock, 2048, PHP_BINARY_READ);
		$buffer = trim($buffer);
		if ($buffer == 'quit' || $buffer == 'exit')
		{
			echo "Exit\n\n";
			break;
		}

		echo "----start query----\n";
		echo $buffer . "\n";
		echo "----end query----\n";

		try
		{
			sleep(3);
			$res = B::process($buffer) ? 'TRUE' : 'FALSE';
		}
		catch (InvalidArgumentException $e)
		{
			$res = 'Invalid Argument in position: ' . $e->getMessage();
		}
		$res .= "\n\n";
		socket_write($msgsock, $res, strlen($res));
		echo "Result for " . posix_getpid() . ": " . $res;
	}

	socket_close($msgsock);
}

