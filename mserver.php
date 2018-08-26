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
	echo "You should set port to run bracket-server. Use option --port=<port> (-p<port>) to define it.\n";
	return;
}


try
{
	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	if (socket_bind($socket, $address, $port) === false)
		throw new \Exception('Could not bind socket');
	socket_listen($socket, 2);
}
catch (\Throwable $e)
{
	echo "Error starting PHP Bracket Server\n";
	echo $e->getMessage() . "\n";
	exit;
}

echo "PHP Bracket Server is running\nPort: $port\nMain PID: " . posix_getpid() . "\n\n";

$usersCount = 0;
pcntl_signal(SIGCHLD, function ($signal) use (&$usersCount) {
	$usersCount--;
	echo $usersCount . " user(s) online.\n\n";
});

socket_set_nonblock($socket);

while (true) {
	$msgsock = socket_accept($socket);
	if ($msgsock === false) {
		usleep(200000);
		pcntl_signal_dispatch();
		continue;
	}

	$usersCount++;
	echo $usersCount . " user(s) online.\n\n";

	$pid = pcntl_fork();
	if ($pid == 0)
	{
		$message = "\nWelcome to PHP Bracket Server!\n";
		socket_write($msgsock, $message, strlen($message));
		echo "Hello, " . posix_getpid() . "!\n\n";

		while (true)
		{
			$buffer = socket_read($msgsock, 2048, PHP_BINARY_READ);
			$buffer = trim($buffer);
			if ($buffer == 'quit' || $buffer == 'exit')
			{
				echo "Bye, " . posix_getpid() . "!\n\n";
				socket_close($msgsock);
				exit;
			}

			echo "----" . posix_getpid() .  " query----\n";
			echo $buffer . "\n";
			echo "----end query----\n\n";

			try
			{
				sleep(mt_rand(2,5));
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
	}
}
