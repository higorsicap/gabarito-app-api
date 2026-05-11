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
            a3.id_aplicador,
            a2.id_serie,
            t.descricao_turma,
            t.id_escola,
            e.nome_escola,
            ap.id_caderno_prova_disciplina,
            cpd.descricao_caderno_prova_disciplina 
        FROM clientes c 
        JOIN municipio m ON m.id_cliente = c.id_cliente
        JOIN avaliacao a ON a.id_avaliacao = m.id_avaliacao 
        JOIN aplicador a3 ON a3.id_avaliacao = a.id_avaliacao 
        JOIN avaliacao_serie a2 ON a2.id_avaliacao = a.id_avaliacao 
        JOIN avaliacao_pergunta ap ON ap.id_avaliacao_serie = a2.id_avaliacao_serie 
        JOIN caderno_prova_disciplina cpd ON cpd.id_caderno_prova_disciplina = ap.id_caderno_prova_disciplina 
        JOIN serie s ON s.id_serie = a2.id_serie 
        JOIN escola e ON e.id_municipio = m.id_municipio
        JOIN turma t ON t.id_escola = e.id_escola
            AND t.id_serie = s.id_serie
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

function baixarProva($dados)
{
    try {
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $baixarProva = $pdo_acesso->prepare("
            SELECT 
                COALESCE(
                    json_agg(
                        json_build_object(
                            'nome_escola', base.nome_escola,
                            'id_anoletivo', base.id_anoletivo,
                            'id_serie', base.id_serie,
                            'id_avaliacao', base.id_avaliacao,
                            'descricao_turma', base.descricao_turma,
                            'id_caderno_prova_disciplina', base.id_caderno_prova_disciplina,
                            'questoes', base.questoes
                        )
                        ORDER BY 
                            base.nome_escola,
                            base.descricao_turma,
                            base.id_caderno_prova_disciplina
                    ),
                    '[]'::json
                ) AS dados
            FROM (
                SELECT 
                    e.nome_escola,
                    a.id_anoletivo,
                    t.id_serie,
                    t.id_avaliacao,
                    COALESCE(
                        t2.descricao_turma,
                        ''
                    ) AS descricao_turma,
                    ap.id_caderno_prova_disciplina,
                    json_agg(
                        json_build_object(
                            'numero_questao', ap.numero_questao,
                            'alternativa', apa.alternativa
                        )
                        ORDER BY ap.numero_questao::INTEGER ASC
                    ) AS questoes
                FROM avaliacao_serie t 
                JOIN avaliacao_pergunta ap 
                    ON ap.id_avaliacao_serie = t.id_avaliacao_serie 
                JOIN avaliacao_pergunta_alternativa apa 
                    ON apa.id_avaliacao_pergunta = ap.id_avaliacao_pergunta 
                JOIN avaliacao a 
                    ON a.id_avaliacao = t.id_avaliacao 
                JOIN municipio m 
                    ON m.id_avaliacao = a.id_avaliacao 
                JOIN escola e  
                    ON e.id_municipio = m.id_municipio 
                LEFT JOIN turma t2 
                    ON t2.id_escola = e.id_escola
                    AND t2.id_serie = t.id_serie
                WHERE apa.is_correta IS TRUE
                    AND apa.is_excluido IS FALSE
                    AND ap.is_excluido IS FALSE
                    AND ap.is_atual IS TRUE
                    AND a.id_anoletivo = :id_anoletivo
                    AND a.id_avaliacao = :id_avaliacao
                    AND e.id_escola = :id_escola
                    AND t.id_serie = :id_serie
                    AND t2.descricao_turma = :descricao_turma
                    and ap.id_caderno_prova_disciplina = :id_caderno_prova_disciplina
                GROUP BY 
                    e.nome_escola,
                    a.id_anoletivo,
                    t.id_serie,
                    t.id_avaliacao,
                    t2.descricao_turma,
                    ap.id_caderno_prova_disciplina
                ORDER BY 
                    t2.descricao_turma,
                    ap.id_caderno_prova_disciplina
            ) base;
        ");

        $baixarProva->bindValue(':id_avaliacao',(int)$dados['id_avaliacao'],PDO::PARAM_INT);
        $baixarProva->bindValue(':id_anoletivo',(int)$dados['id_anoletivo'], PDO::PARAM_INT);
        $baixarProva->bindValue(':id_escola',(int)$dados['id_escola'],PDO::PARAM_INT);
        $baixarProva->bindValue(':id_serie',(int)$dados['id_serie'],PDO::PARAM_INT);
        $baixarProva->bindValue(':descricao_turma', $dados['descricao_turma'],PDO::PARAM_STR);
        $baixarProva->bindValue(':id_caderno_prova_disciplina', (int)$dados['id_caderno_prova_disciplina'],PDO::PARAM_INT);

        $baixarProva->execute();

        $resultado = $baixarProva->fetch(PDO::FETCH_ASSOC);

        $pdo_acesso->commit();

        return json_encode(
            $resultado,
            JSON_UNESCAPED_UNICODE
        );
    } catch (Exception $e) {

        $pdo_acesso->rollBack();

        return json_encode([
            'erro' => true,
            'mensagem' => $e->getMessage()
        ]);
    }
}

function listarEscolas($dados)
{
    try {
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $listarEscolas = $pdo_acesso->prepare("
            SELECT 
                id_escola,
                nome_escola
            FROM escola e
            JOIN municipio m ON m.id_municipio = e.id_municipio 
            JOIN avaliacao a ON a.id_avaliacao = m.id_avaliacao 
            WHERE (:id_anoletivo = -1 or a.id_anoletivo = :id_anoletivo)
            ORDER BY nome_escola  asc   
        ");
        $listarEscolas->bindValue(':id_anoletivo', $dados['id_anoletivo'], PDO::PARAM_INT);
        $listarEscolas->execute();
        $resultado = $listarEscolas->fetchAll(PDO::FETCH_ASSOC);
        $pdo_acesso->commit();
        return json_encode($resultado);
    } catch (Exception $e) {
        echo 'Erro: ' . $e->getMessage();
    }
}
