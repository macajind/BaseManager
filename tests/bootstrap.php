<?php

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer install` command.';
	exit(1);
}

Tester\Environment::setup();
