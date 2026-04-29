<?php

function listarAnoletivo(){
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
    } catch(Exception $e){
        echo 'Erro: '.$e->getMessage();
    }
}

function listarClientes(){
    try{
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
    }catch(Exception $e){
        echo 'Erro: '.$e->getMessage();
    }
}

function listarProvas(){
    try{
        $pdo_acesso = $GLOBALS['pdo_acesso'];
        $pdo_acesso->beginTransaction();

        $listarProvas = $pdo_acesso->prepare("
        SELECT 
            c.id_cliente,
            c.nome_cliente,
            c.email_cliente,
            a.id_avaliacao,
            a.descricao_avaliacao
        FROM clientes c 
        JOIN municipio m  ON m.id_cliente = c.id_cliente 
        JOIN avaliacao a ON a.id_avaliacao = m.id_avaliacao 
        WHERE c.email_cliente !~ '^[0-9]+$'
            AND a.id_anoletivo = :id_anoletivo
            and c.id_cliente = :id_cliente
        ORDER BY c.email_cliente  asc
        ");
        $listarProvas->execute();
        $resultado = $listarProvas->fetchAll(PDO::FETCH_ASSOC);
        $pdo_acesso->commit();
        return json_encode($resultado);

    }catch(Exception $e){
        echo 'Erro: '.$e->getMessage();
    }
}