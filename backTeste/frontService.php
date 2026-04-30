<?php

function listarAnoletivo()
{
    try {
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $listarAnoletivo = $pdo_acesso->prepare("
            SELECT 
                id_anoletivo,
                ds_anoletivo
            FROM anoletivo
            ORDER BY ds_anoletivo  asc        
        ");
        $listarAnoletivo->execute();
        $resultado = $listarAnoletivo->fetchAll(PDO::FETCH_ASSOC);
        $pdo_acesso->commit();
        return json_encode($resultado);
    } catch (Exception $e) {
        echo 'Erro: ' . $e->getMessage();
    }
}

function listarClientes()
{
    try {
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $listarClientes = $pdo_acesso->prepare("
            SELECT 
                c.id_cliente,
                c.nome_cliente,
                c.email_cliente  
            FROM clientes c 
            WHERE c.email_cliente !~ '^[0-9]+$'
            ORDER BY c.email_cliente  asc        
        ");
        $listarClientes->execute();
        $resultado = $listarClientes->fetchAll(PDO::FETCH_ASSOC);
        $pdo_acesso->commit();
        return json_encode($resultado);
    } catch (Exception $e) {
        echo 'Erro: ' . $e->getMessage();
    }
}

function listarProvas($dados)
{
    try {
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $listarProvas = $pdo_acesso->prepare("
        SELECT DISTINCT 
            c.id_cliente,
            c.nome_cliente,
            a.id_avaliacao,
            a.descricao_avaliacao,
            a.id_anoletivo,
            a3.id_aplicador 
        FROM clientes c 
        JOIN municipio m  ON m.id_cliente = c.id_cliente 
        JOIN avaliacao a ON a.id_avaliacao = m.id_avaliacao 
        JOIN aplicador a3 ON a3.id_avaliacao = a.id_avaliacao 
        JOIN avaliacao_serie a2 ON a2.id_avaliacao = a.id_avaliacao 
        WHERE c.email_cliente !~ '^[0-9]+$'
            AND a3.id_aplicador = :id_aplicador
            AND a.is_excluido = false
        ORDER BY c.nome_cliente ASC
        ");

        $listarProvas->bindValue(':id_aplicador', $dados['id_aplicador'], PDO::PARAM_INT);
        $listarProvas->execute();
        $resultado = $listarProvas->fetchAll(PDO::FETCH_ASSOC);
        $pdo_acesso->commit();
        return json_encode($resultado);
    } catch (Exception $e) {
        echo 'Erro: ' . $e->getMessage();
    }
}

function loginCliente($dados)
{
    try {
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $loginCliente = $pdo_acesso->prepare("
        SELECT 
            id_aplicador,
            cpf_aplicador,
            senha_aplicador
        FROM aplicador
        WHERE cpf_aplicador = :cpf_aplicador::TEXT
            AND senha_aplicador = :senha_aplicador
            AND is_ativo = TRUE
        ");

        $loginCliente->bindValue(':cpf_aplicador', $dados['cpf_aplicador'], PDO::PARAM_STR);
        $loginCliente->bindValue(':senha_aplicador', $dados['senha_aplicador'], PDO::PARAM_STR);
        $loginCliente->execute();

        $usuario = $loginCliente->fetch(PDO::FETCH_ASSOC);

        $retorno = [
            'sucesso' => true,
            'mensagem' => 'Login realizado com sucesso',
            'recurso' => null
        ];

        if (empty($usuario)) {
            $retorno['sucesso'] = false;
            $retorno['mensagem'] = "Usuário ou senha inválidos.";
            return json_encode($retorno);
        }

        $token = $pdo_acesso->prepare("
        INSERT INTO aplicador_acesso (
            id_aplicador,
            aplicador_token,
            is_atual
        )	
        VALUES (
            (
                SELECT id_aplicador 
                FROM aplicador  
                WHERE cpf_aplicador = :cpf_aplicador::TEXT
            ),
            uuid_generate_v4(),
            TRUE
        )
        RETURNING *;
        ");

        $token->bindValue(':cpf_aplicador', $dados['cpf_aplicador'], PDO::PARAM_STR);
        $token->execute();
        $token_retorno = $token->fetch(PDO::FETCH_ASSOC);

        $usuario['token'] = $token_retorno;

        $retorno['recurso'] = $usuario;

        $updateToken = $pdo_acesso->prepare("
        UPDATE aplicador_acesso
        SET is_atual = FALSE
        WHERE id_aplicador = (
            SELECT id_aplicador 
            FROM aplicador  
            WHERE cpf_aplicador = :cpf_aplicador::TEXT
        )
        AND aplicador_token <> :aplicador_token;
        ");
        $updateToken->bindValue(':cpf_aplicador', $dados['cpf_aplicador'], PDO::PARAM_STR);
        $updateToken->bindValue(':aplicador_token', $token_retorno['aplicador_token'], PDO::PARAM_STR);
        $updateToken->execute();


        $pdo_acesso->commit();
        return json_encode($retorno);
    } catch (Exception $e) {
        echo 'Erro: ' . $e->getMessage();
    }
}