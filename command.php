<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

if ( ! class_exists( 'Host_Check_Command' ) ) {
	require_once __DIR__ . '/src/Host_Check_Command.php';
}

WP_CLI::add_command( 'host-check', 'Host_Check_Command' );
