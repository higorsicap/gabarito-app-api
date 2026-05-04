<?php

include_once 'conexao.php';
include_once 'frontService.php';

$input = json_decode(file_get_contents("php://input"), true);
$opcao = $_POST['s'];

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
    case '4':
        f4();
        break;
    // case '5':
    //     f5();
    //     break;
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
    $dados = [
        'id_aplicador' => $_POST['id_aplicador'],
    ];
    echo listarProvas($dados);
}

function f4(){
    $dados = [
        'cpf_aplicador' => $_POST['cpf_aplicador'],
        'senha_aplicador' => $_POST['senha_aplicador']
    ];
    echo loginCliente($dados);

}

// function f5(){
//     $dados = [
//         'id_avaliacao' => $_POST['id_avaliacao']
//     ];
//     echo baixarProva($dados);
// }