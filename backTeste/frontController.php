<?php

include_once 'conexao.php';
include_once 'frontService.php';

$input = json_decode(file_get_contents("php://input"), true);
$opcao = $input['s'] ?? null;

switch ($opcao) {
    case '1':
        f1();
        break;
    case '2':
        f2();
        break;
    case '3':
        f3();
        break;
}
function f1()
{
    echo listarAnoletivo();
}

function f2()
{
    echo listarClientes();
}

function f3()
{
    echo listarProvas();
}
