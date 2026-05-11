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
    case '5':
        f5();
        break;
    case '6':
        f6();
        break;
    default:
        echo "Opção inválida.";
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

function f4()
{
    $dados = [
        'cpf_aplicador' => $_POST['cpf_aplicador'],
        'senha_aplicador' => $_POST['senha_aplicador']
    ];
    echo loginCliente($dados);
}

function f5()
{
    $dados = [
        'id_avaliacao' => $_POST['id_avaliacao'],
        'id_anoletivo' => $_POST['id_anoletivo'],
        'id_escola' => $_POST['id_escola'],
        'id_serie' => $_POST['id_serie'],
        'descricao_turma' => $_POST['descricao_turma'],
        'id_caderno_prova_disciplina' => $_POST['id_caderno_prova_disciplina']
    ];
    echo baixarProva($dados);
}

function f6()
{
    $dados = [
        'id_anoletivo' => $_POST['id_anoletivo']
    ];
    echo listarEscolas($dados);
}
