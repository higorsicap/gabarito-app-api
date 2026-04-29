<?php

	$host		= "85.31.63.172";	
	$port		= "5432";
	$dbname		= "adm_sae";
	$user		= "postgres";
	$password	= "vSoj3WaPHUaa6MrADKtzayy46ub5YS69S2K3JXrQtqkeV8VtYv";
	
	$conexao_acesso = "pgsql: host={$host} port={$port} dbname={$dbname} user={$user} password={$password} ";

	$pdo_acesso = new PDO($conexao_acesso);
	$pdo_acesso->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);